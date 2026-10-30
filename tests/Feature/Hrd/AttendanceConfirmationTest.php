<?php

namespace Tests\Feature\Hrd;

use App\Models\Attendance;
use App\Models\AttendanceReview;
use App\Models\User;
use App\Models\WorkSchedule;
use App\Services\AttendanceReviewGenerator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class AttendanceConfirmationTest extends TestCase
{
    use RefreshDatabase;

    private function setupReview(): array
    {
        $this->travelTo(Carbon::parse('2026-10-10 10:00:00'));
        $hrd = User::factory()->create(['role' => 'hrd']);
        $employee = User::factory()->create(['company_id' => $hrd->company_id]);
        WorkSchedule::create(['company_id' => $hrd->company_id, 'name' => 'Reguler', 'jam_masuk' => '08:30:00', 'jam_pulang' => '16:30:00', 'toleransi_telat_menit' => 0]);
        $attendance = Attendance::create(['user_id' => $employee->id, 'tanggal' => '2026-10-09', 'clock_in_time' => '2026-10-09 08:40:00', 'clock_in_status' => 'telat', 'clock_in_late_minutes' => 10]);
        app(AttendanceReviewGenerator::class)->generateForDate(Carbon::parse('2026-10-09'), $hrd->company_id);

        return [$hrd, AttendanceReview::where('user_id', $employee->id)->firstOrFail(), $attendance];
    }

    public function test_hrd_confirmation_preserves_source_and_is_audited_and_survives_regeneration(): void
    {
        [$hrd, $review, $attendance] = $this->setupReview();
        $this->actingAs($hrd)->patch(route('hrd.konfirmasi-absensi.update', $review), ['version' => $review->version, 'clock_out' => '11:00', 'reason' => 'Jam pulang diverifikasi dengan atasan.'])->assertSessionHasNoErrors()->assertRedirect();
        $review->refresh();
        $this->assertSame('half_day', $review->classification);
        $this->assertSame(140, $review->worked_minutes);
        $this->assertSame(10, $review->late_minutes);
        $this->assertSame($hrd->id, $review->validated_by);
        $this->assertNull($attendance->fresh()->clock_out_time);
        $this->assertDatabaseHas('payroll_audit_logs', ['attendance_review_id' => $review->id, 'action' => 'clock_out_confirmed', 'actor_id' => $hrd->id]);
        app(AttendanceReviewGenerator::class)->generateForDate(Carbon::parse('2026-10-09'), $hrd->company_id);
        $this->assertSame('half_day', $review->fresh()->classification);
        $this->actingAs($hrd)->patch(route('hrd.konfirmasi-absensi.update', $review), ['version' => 1, 'clock_out' => '16:30', 'reason' => 'Permintaan lama terkirim ulang.'])->assertSessionHasErrors('confirmation');
    }

    public function test_hrd_can_manual_correct_clock_in_and_clock_out_for_unresolved_review(): void
    {
        [$hrd, $review] = $this->setupReview();

        $this->actingAs($hrd)->patch(route('hrd.konfirmasi-absensi.update', $review), [
            'version' => $review->version,
            'clock_in' => '07:45',
            'clock_out' => '11:00',
            'reason' => 'Jam masuk dan pulang dikoreksi berdasarkan bukti valid.',
        ])->assertSessionHasNoErrors()->assertRedirect();

        $review->refresh();
        $this->assertSame('07:45', $review->clock_in_at->format('H:i'));
        $this->assertSame('11:00', $review->clock_out_at->format('H:i'));
        $this->assertSame(195, $review->worked_minutes);
        $this->assertSame(0, $review->late_minutes);
        $this->assertSame('half_day', $review->classification);
    }

    public function test_company_role_invalid_time_and_reason_are_enforced(): void
    {
        [$hrd, $review] = $this->setupReview();
        $payload = ['version' => $review->version, 'clock_out' => '16:30', 'reason' => 'Dikonfirmasi oleh atasan.'];
        $other = User::factory()->create(['role' => 'hrd']);
        $this->actingAs($other)->patch(route('hrd.konfirmasi-absensi.update', $review), $payload)->assertForbidden();
        $this->actingAs($review->user)->patch(route('hrd.konfirmasi-absensi.update', $review), $payload)->assertForbidden();
        $this->actingAs($hrd)->patch(route('hrd.konfirmasi-absensi.update', $review), [...$payload, 'clock_out' => '08:00'])->assertSessionHasErrors('clock_out');
        $this->actingAs($hrd)->patch(route('hrd.konfirmasi-absensi.update', $review), [...$payload, 'reason' => ''])->assertSessionHasErrors('reason');
        $this->assertSame('unresolved', $review->fresh()->classification);
    }

    public function test_current_day_and_changed_source_cannot_be_confirmed(): void
    {
        [$hrd, $review, $attendance] = $this->setupReview();
        $payload = ['version' => $review->version, 'clock_out' => '16:30', 'reason' => 'Dikonfirmasi oleh atasan.'];
        $this->travelTo(Carbon::parse('2026-10-09 10:00:00'));
        $this->actingAs($hrd)->patch(route('hrd.konfirmasi-absensi.update', $review), $payload)->assertSessionHasErrors('confirmation');
        $this->travelTo(Carbon::parse('2026-10-10 10:00:00'));
        $attendance->update(['clock_out_time' => '2026-10-09 16:30:00']);
        $this->actingAs($hrd)->patch(route('hrd.konfirmasi-absensi.update', $review), $payload)->assertSessionHasErrors('confirmation');
    }
}
