<?php

namespace Tests\Feature\Hrd;

use App\Models\AttendanceReview;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class AttendanceReviewManagementTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('attendance_evidence');
    }

    public function test_visiting_the_page_auto_generates_reviews_for_the_date(): void
    {
        $hrd = User::factory()->create(['role' => 'hrd']);
        $karyawan = User::factory()->create(['company_id' => $hrd->company_id, 'role' => 'karyawan', 'status' => 'active']);

        $this->actingAs($hrd)
            ->get(route('hrd.koreksi-absensi.index', ['tanggal' => '2026-10-05']))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('hrd/attendance-reviews')
                ->where('tanggal', '2026-10-05')
                ->has('reviews', 1)
                ->where('reviews.0.user_name', $karyawan->name)
                ->where('reviews.0.classification', 'unresolved')
            );

        $this->assertDatabaseHas('attendance_reviews', [
            'user_id' => $karyawan->id, 'date' => '2026-10-05', 'classification' => 'unresolved',
        ]);
    }

    public function test_a_future_date_is_rejected(): void
    {
        $hrd = User::factory()->create(['role' => 'hrd']);

        $this->actingAs($hrd)
            ->get(route('hrd.koreksi-absensi.index', ['tanggal' => now()->addDay()->toDateString()]))
            ->assertStatus(422);
    }

    public function test_hrd_can_correct_a_review_with_evidence(): void
    {
        $hrd = User::factory()->create(['role' => 'hrd']);
        $karyawan = User::factory()->create(['company_id' => $hrd->company_id, 'role' => 'karyawan', 'status' => 'active']);
        $review = AttendanceReview::create([
            'user_id' => $karyawan->id, 'company_id' => $karyawan->company_id, 'date' => '2026-10-05',
            'status' => 'draft', 'classification' => 'unresolved',
            'source_snapshot' => [], 'schedule_snapshot' => [], 'version' => 0,
        ]);

        $file = UploadedFile::fake()->create('surat-sakit.pdf', 100, 'application/pdf');

        $this->actingAs($hrd)
            ->put(route('hrd.koreksi-absensi.update', $review), [
                'classification' => 'sick',
                'correction_reason' => 'Karyawan mengirim surat sakit via WhatsApp.',
                'evidence' => $file,
            ])
            ->assertSessionDoesntHaveErrors()
            ->assertRedirect();

        $review->refresh();
        $this->assertSame('sick', $review->classification);
        $this->assertSame('validated', $review->status);
        $this->assertSame('Karyawan mengirim surat sakit via WhatsApp.', $review->correction_reason);
        $this->assertSame($hrd->id, $review->validated_by);
        $this->assertSame(1, $review->version);
        $this->assertNotNull($review->evidence_path);
        Storage::disk('attendance_evidence')->assertExists($review->evidence_path);
    }

    public function test_evidence_must_be_an_image_or_pdf(): void
    {
        $hrd = User::factory()->create(['role' => 'hrd']);
        $karyawan = User::factory()->create(['company_id' => $hrd->company_id, 'role' => 'karyawan', 'status' => 'active']);
        $review = AttendanceReview::create([
            'user_id' => $karyawan->id, 'company_id' => $karyawan->company_id, 'date' => '2026-10-05',
            'status' => 'draft', 'classification' => 'unresolved',
            'source_snapshot' => [], 'schedule_snapshot' => [], 'version' => 0,
        ]);

        $file = UploadedFile::fake()->create('script.exe', 100, 'application/x-msdownload');

        $this->actingAs($hrd)
            ->put(route('hrd.koreksi-absensi.update', $review), [
                'classification' => 'sick',
                'correction_reason' => 'Mencoba unggah berkas tidak valid.',
                'evidence' => $file,
            ])
            ->assertSessionHasErrors('evidence');

        $this->assertNull($review->fresh()->evidence_path);
    }

    public function test_correction_reason_is_required(): void
    {
        $hrd = User::factory()->create(['role' => 'hrd']);
        $karyawan = User::factory()->create(['company_id' => $hrd->company_id, 'role' => 'karyawan', 'status' => 'active']);
        $review = AttendanceReview::create([
            'user_id' => $karyawan->id, 'company_id' => $karyawan->company_id, 'date' => '2026-10-05',
            'status' => 'draft', 'classification' => 'unresolved',
            'source_snapshot' => [], 'schedule_snapshot' => [], 'version' => 0,
        ]);

        $this->actingAs($hrd)
            ->put(route('hrd.koreksi-absensi.update', $review), [
                'classification' => 'sick',
                'correction_reason' => '',
            ])
            ->assertSessionHasErrors('correction_reason');
    }

    public function test_regenerating_the_page_preserves_a_prior_correction(): void
    {
        $hrd = User::factory()->create(['role' => 'hrd']);
        $karyawan = User::factory()->create(['company_id' => $hrd->company_id, 'role' => 'karyawan', 'status' => 'active']);
        $date = Carbon::parse('2026-10-05');

        // First visit generates the "unresolved" draft.
        $this->actingAs($hrd)->get(route('hrd.koreksi-absensi.index', ['tanggal' => $date->toDateString()]));
        $review = AttendanceReview::where('user_id', $karyawan->id)->whereDate('date', $date)->firstOrFail();

        $this->actingAs($hrd)->put(route('hrd.koreksi-absensi.update', $review), [
            'classification' => 'permission',
            'correction_reason' => 'Dinas luar kota, dikonfirmasi lisan.',
        ]);

        // A second visit (e.g. page reload) must not silently revert it.
        $this->actingAs($hrd)->get(route('hrd.koreksi-absensi.index', ['tanggal' => $date->toDateString()]));

        $this->assertSame('permission', $review->fresh()->classification);
        $this->assertSame('Dinas luar kota, dikonfirmasi lisan.', $review->fresh()->correction_reason);
    }

    public function test_karyawan_cannot_access_attendance_reviews(): void
    {
        $karyawan = User::factory()->create(['role' => 'karyawan']);
        $review = AttendanceReview::create([
            'user_id' => $karyawan->id, 'company_id' => $karyawan->company_id, 'date' => '2026-10-05',
            'status' => 'draft', 'classification' => 'unresolved',
            'source_snapshot' => [], 'schedule_snapshot' => [], 'version' => 0,
        ]);

        $this->actingAs($karyawan)
            ->get(route('hrd.koreksi-absensi.index'))
            ->assertForbidden();

        $this->actingAs($karyawan)
            ->put(route('hrd.koreksi-absensi.update', $review), [
                'classification' => 'sick',
                'correction_reason' => 'x',
            ])
            ->assertForbidden();
    }

    public function test_hrd_can_download_evidence_but_karyawan_cannot(): void
    {
        $hrd = User::factory()->create(['role' => 'hrd']);
        $karyawan = User::factory()->create(['company_id' => $hrd->company_id, 'role' => 'karyawan', 'status' => 'active']);
        $review = AttendanceReview::create([
            'user_id' => $karyawan->id, 'company_id' => $karyawan->company_id, 'date' => '2026-10-05',
            'status' => 'draft', 'classification' => 'unresolved',
            'source_snapshot' => [], 'schedule_snapshot' => [], 'version' => 0,
            'evidence_path' => $karyawan->id.'/2026-10-05/surat.pdf',
        ]);
        Storage::disk('attendance_evidence')->put($review->evidence_path, 'dummy-pdf-content');

        $this->actingAs($hrd)
            ->get(route('hrd.koreksi-absensi.bukti', $review))
            ->assertOk();

        $this->actingAs($karyawan)
            ->get(route('hrd.koreksi-absensi.bukti', $review))
            ->assertForbidden();
    }

    public function test_missing_evidence_file_returns_404(): void
    {
        $hrd = User::factory()->create(['role' => 'hrd']);
        $review = AttendanceReview::create([
            'user_id' => $hrd->id, 'company_id' => $hrd->company_id, 'date' => '2026-10-05',
            'status' => 'draft', 'classification' => 'unresolved',
            'source_snapshot' => [], 'schedule_snapshot' => [], 'version' => 0,
        ]);

        $this->actingAs($hrd)
            ->get(route('hrd.koreksi-absensi.bukti', $review))
            ->assertNotFound();
    }
}
