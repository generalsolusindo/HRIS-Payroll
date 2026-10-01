<?php

namespace App\Services;

use Carbon\CarbonInterface;
use Illuminate\Support\Carbon;

/**
 * Per-type business rules for leave requests, kept separate from
 * StoreLeaveRequestRequest so the rules can be unit-tested directly
 * against a controlled clock, without needing a real HTTP request.
 *
 * All deadlines are checked against the server clock (now()) — never
 * trust a client-submitted time for these decisions.
 */
class LeaveTypeValidator
{
    public const TYPES = ['late', 'half_day', 'temporary_exit', 'leave', 'emergency', 'sick'];

    /**
     * @param  array<string, mixed>  $input
     * @return array<string, string> field => error message
     */
    public function validate(string $type, array $input): array
    {
        return match ($type) {
            'late' => $this->validateLate($input),
            'half_day' => $this->validateHalfDay($input),
            'temporary_exit' => $this->validateTemporaryExit($input),
            'leave' => $this->validateLeave($input),
            'emergency' => $this->validateEmergency($input),
            'sick' => $this->validateSick($input),
            default => [],
        };
    }

    /**
     * @param  array<string, mixed>  $input
     */
    private function leaveDate(array $input): ?CarbonInterface
    {
        if (empty($input['leave_date'])) {
            return null;
        }

        try {
            return Carbon::parse($input['leave_date'])->startOfDay();
        } catch (\Throwable) {
            return null;
        }
    }

    private function timeToday(string $time): CarbonInterface
    {
        return today()->setTimeFromTimeString($time);
    }

    /**
     * @param  array<string, mixed>  $input
     * @return array<string, string>
     */
    private function validateLate(array $input): array
    {
        $errors = [];
        $date = $this->leaveDate($input);

        if ($date && ! $date->isSameDay(today())) {
            $errors['leave_date'] = 'Izin Terlambat hanya bisa diajukan untuk hari ini.';
        }

        if (now()->greaterThan($this->timeToday('07:00')) && ! ($input['emergency'] ?? false)) {
            $errors['emergency'] = 'Sudah lewat jam 07:00 — centang "darurat" untuk tetap mengajukan.';
        }

        if (empty($input['estimated_arrival'])) {
            $errors['estimated_arrival'] = 'Estimasi jam kedatangan wajib diisi.';
        } elseif ($this->timeToday($input['estimated_arrival'])->greaterThan($this->timeToday('09:30'))) {
            $errors['estimated_arrival'] = 'Estimasi kedatangan maksimal jam 09:30.';
        }

        return $errors;
    }

    /**
     * @param  array<string, mixed>  $input
     * @return array<string, string>
     */
    private function validateHalfDay(array $input): array
    {
        $errors = [];
        $date = $this->leaveDate($input);

        if ($date && ! $date->isAfter(today())) {
            $errors['leave_date'] = 'Izin Setengah Hari minimal diajukan H-1 (besok).';
        }

        if (empty($input['start_time'])) {
            $errors['start_time'] = 'Jam mulai wajib diisi.';
        } elseif ($this->timeToday($input['start_time'])->lessThan($this->timeToday('12:30'))) {
            $errors['start_time'] = 'Jam mulai Izin Setengah Hari minimal jam 12:30.';
        }

        return $errors;
    }

    /**
     * @param  array<string, mixed>  $input
     * @return array<string, string>
     */
    private function validateTemporaryExit(array $input): array
    {
        $errors = [];
        $date = $this->leaveDate($input);

        if ($date && $date->isBefore(today())) {
            $errors['leave_date'] = 'Tanggal tidak boleh di masa lalu.';
        }

        if (empty($input['start_time'])) {
            $errors['start_time'] = 'Jam keluar wajib diisi.';
        }

        if (empty($input['estimated_arrival'])) {
            $errors['estimated_arrival'] = 'Estimasi jam kembali wajib diisi.';
        }

        return $errors;
    }

    /**
     * @param  array<string, mixed>  $input
     * @return array<string, string>
     */
    private function validateLeave(array $input): array
    {
        $errors = [];
        $date = $this->leaveDate($input);

        if ($date && $date->lessThan(today()->addDays(7))) {
            $errors['leave_date'] = 'Cuti minimal diajukan H-7.';
        }

        if (empty($input['duration']) || (float) $input['duration'] < 1) {
            $errors['duration'] = 'Durasi cuti (hari) wajib diisi, minimal 1 hari.';
        }

        return $errors;
    }

    /**
     * @param  array<string, mixed>  $input
     * @return array<string, string>
     */
    private function validateEmergency(array $input): array
    {
        $errors = [];
        $date = $this->leaveDate($input);

        if ($date && ! $date->isSameDay(today())) {
            $errors['leave_date'] = 'Izin Darurat hanya bisa diajukan untuk hari ini.';
        }

        if (now()->greaterThan($this->timeToday('08:30'))) {
            $errors['type'] = 'Sudah lewat batas waktu pengajuan Izin Darurat (08:30). '
                .'Ketidakhadiran hari ini akan dicatat sebagai Alpha oleh HRD.';
        }

        if (! array_key_exists('contactable', $input) || $input['contactable'] === null) {
            $errors['contactable'] = 'Wajib diisi: apakah Anda bisa dihubungi untuk koordinasi?';
        }

        return $errors;
    }

    /**
     * @param  array<string, mixed>  $input
     * @return array<string, string>
     */
    private function validateSick(array $input): array
    {
        $errors = [];
        $date = $this->leaveDate($input);

        if ($date && $date->isBefore(today())) {
            $errors['leave_date'] = 'Tanggal tidak boleh di masa lalu.';
        }

        if (now()->greaterThan($this->timeToday('08:30')) && ! ($input['emergency'] ?? false)) {
            $errors['emergency'] = 'Sudah lewat jam 08:30 — centang kondisi darurat untuk tetap mengajukan.';
        }

        if (! array_key_exists('contactable', $input) || $input['contactable'] === null) {
            $errors['contactable'] = 'Wajib diisi: apakah Anda bisa dihubungi untuk koordinasi?';
        }

        return $errors;
    }
}
