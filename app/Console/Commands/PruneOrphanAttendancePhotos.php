<?php

namespace App\Console\Commands;

use App\Models\Attendance;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

/**
 * Attendance selfies are written to disk before the attendance row that
 * would reference them is committed (see AttendanceController). A normal
 * failure (mismatch, double-submit, a re-check losing a race) already
 * deletes its own file; this command is the backstop for the case that
 * code can't catch — the PHP process itself dying between the write and
 * that cleanup (OOM kill, worker timeout, server restart).
 */
class PruneOrphanAttendancePhotos extends Command
{
    protected $signature = 'attendance:prune-orphan-photos
        {--hours=1 : Hanya hapus file yang lebih tua dari N jam}
        {--dry-run : Tampilkan file yang akan dihapus tanpa benar-benar menghapus}';

    protected $description = 'Hapus foto absen yatim (tidak tertaut ke attendance manapun) yang tersisa akibat proses yang mati di tengah jalan';

    public function handle(): int
    {
        $disk = Storage::disk('attendance_photos');
        $cutoff = now()->subHours(max(0, (int) $this->option('hours')));
        $dryRun = (bool) $this->option('dry-run');

        $referenced = Attendance::query()
            ->whereNotNull('clock_in_photo_path')
            ->pluck('clock_in_photo_path')
            ->merge(
                Attendance::query()->whereNotNull('clock_out_photo_path')->pluck('clock_out_photo_path'),
            )
            ->flip();

        $removed = 0;
        $kept = 0;

        foreach ($disk->allFiles() as $path) {
            if ($referenced->has($path)) {
                continue;
            }

            // Too fresh to judge safely — could be mid-request (file
            // written, attendance row not committed yet). Leave it for a
            // later run instead of racing an in-flight request.
            if ($disk->lastModified($path) > $cutoff->getTimestamp()) {
                $kept++;

                continue;
            }

            $removed++;
            $this->line(($dryRun ? '[dry-run] ' : '').'Menghapus foto yatim: '.$path);

            if (! $dryRun) {
                $disk->delete($path);
            }
        }

        $this->info("Selesai. Dihapus: {$removed}; dipertahankan (tertaut atau masih baru): {$kept}.");

        return self::SUCCESS;
    }
}
