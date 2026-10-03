<?php

namespace App\Console\Commands;

use App\Models\Attendance;
use App\Models\User;
use App\Services\AttendancePunctuality;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;

class RecalculateAttendanceLateness extends Command
{
    protected $signature = 'attendance:recalculate-lateness {date : Tanggal WIB YYYY-MM-DD} {--apply : Simpan koreksi; tanpa opsi ini hanya pratinjau}';

    protected $description = 'Koreksi status masuk berdasarkan jadwal saat ini tanpa toleransi, dengan log nilai sebelum/sesudah';

    public function handle(AttendancePunctuality $punctuality): int
    {
        $date = $this->argument('date');
        $validator = Validator::make(['date' => $date], ['date' => 'required|date_format:Y-m-d']);
        if ($validator->fails()) {
            $this->error('Tanggal harus berformat YYYY-MM-DD.');

            return self::FAILURE;
        }

        $changed = 0;
        $skipped = 0;
        Attendance::whereDate('tanggal', $date)->whereNotNull('clock_in_time')
            ->chunkById(100, function ($rows) use ($punctuality, &$changed, &$skipped) {
                foreach ($rows as $row) {
                    DB::transaction(function () use ($row, $punctuality, &$changed, &$skipped) {
                        $attendance = Attendance::whereKey($row->id)->lockForUpdate()->firstOrFail();
                        $user = User::findOrFail($attendance->user_id);
                        $schedules = $user->company->workSchedules()->get();
                        // Historical schedules are not snapshotted by the old attendance module.
                        // Do not guess when the current schedule is ambiguous.
                        if ($schedules->count() !== 1 || $attendance->clock_in_time === null) {
                            $skipped++;

                            return;
                        }
                        $result = $punctuality->evaluate($attendance->clock_in_time, $schedules->first()->jam_masuk);
                        if ($attendance->clock_in_status === $result['status'] && $attendance->clock_in_late_minutes === $result['minutes']) {
                            return;
                        }
                        $changed++;
                        if ($this->option('apply')) {
                            $before = $attendance->only(['clock_in_status', 'clock_in_late_minutes']);
                            $attendance->fill([
                                'clock_in_status' => $result['status'],
                                'clock_in_late_minutes' => $result['minutes'],
                            ])->save();
                            Log::info('Attendance lateness corrected without grace period.', [
                                'attendance_id' => $attendance->id,
                                'before' => $before,
                                'after' => $attendance->only(['clock_in_status', 'clock_in_late_minutes']),
                            ]);
                        }
                    });
                }
            });

        $this->info(($this->option('apply') ? 'Diperbaiki' : 'Pratinjau').": {$changed} catatan; dilewati: {$skipped}.");
        $this->comment('Menggunakan jadwal saat ini. Pastikan jadwal tersebut berlaku pada tanggal yang dipilih.');

        return self::SUCCESS;
    }
}
