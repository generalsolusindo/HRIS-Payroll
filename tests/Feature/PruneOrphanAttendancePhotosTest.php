<?php

namespace Tests\Feature;

use App\Models\Attendance;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class PruneOrphanAttendancePhotosTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('attendance_photos');
    }

    public function test_it_removes_only_old_unreferenced_files(): void
    {
        $disk = Storage::disk('attendance_photos');
        $user = User::factory()->create();
        $date = today()->toDateString();

        $referencedPath = "{$user->id}/{$date}/masuk-referenced.jpg";
        $disk->put($referencedPath, 'x');
        touch($disk->path($referencedPath), now()->subDays(5)->getTimestamp());
        Attendance::create([
            'user_id' => $user->id,
            'tanggal' => today(),
            'clock_in_photo_path' => $referencedPath,
        ]);

        $freshOrphanPath = "{$user->id}/{$date}/masuk-fresh-orphan.jpg";
        $disk->put($freshOrphanPath, 'x');

        $oldOrphanPath = "{$user->id}/{$date}/masuk-old-orphan.jpg";
        $disk->put($oldOrphanPath, 'x');
        touch($disk->path($oldOrphanPath), now()->subHours(5)->getTimestamp());

        $this->artisan('attendance:prune-orphan-photos', ['--hours' => 1])
            ->assertSuccessful();

        $disk->assertExists($referencedPath);
        $disk->assertExists($freshOrphanPath);
        $disk->assertMissing($oldOrphanPath);
    }

    public function test_dry_run_does_not_delete_anything(): void
    {
        $disk = Storage::disk('attendance_photos');
        $date = today()->toDateString();

        $oldOrphanPath = "1/{$date}/masuk-old-orphan.jpg";
        $disk->put($oldOrphanPath, 'x');
        touch($disk->path($oldOrphanPath), now()->subHours(5)->getTimestamp());

        $this->artisan('attendance:prune-orphan-photos', ['--hours' => 1, '--dry-run' => true])
            ->assertSuccessful();

        $disk->assertExists($oldOrphanPath);
    }
}
