<?php

namespace Tests\Feature\Hrd;

use App\Models\OvertimeRequest;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OvertimeApprovalTest extends TestCase
{
    use RefreshDatabase;

    private function makeSubmittedOvertime(User $hrd): OvertimeRequest
    {
        $karyawan = User::factory()->create(['company_id' => $hrd->company_id, 'role' => 'karyawan']);

        return OvertimeRequest::create([
            'user_id' => $karyawan->id, 'company_id' => $karyawan->company_id,
            'starts_at' => '2026-09-28 18:00:00', 'ends_at' => '2026-09-28 21:00:00',
            'break_minutes' => 30, 'reason' => 'Laporan bulanan', 'status' => 'submitted',
        ]);
    }

    /**
     * @return array{0: User, 1: OvertimeRequest}
     */
    private function makeHrdAndSubmittedOvertime(): array
    {
        $hrd = User::factory()->create(['role' => 'hrd']);

        return [$hrd, $this->makeSubmittedOvertime($hrd)];
    }

    public function test_hrd_sees_submitted_overtime_requests_with_raw_minutes(): void
    {
        [$hrd, $overtimeRequest] = $this->makeHrdAndSubmittedOvertime();

        $response = $this->actingAs($hrd)
            ->get(route('hrd.persetujuan-lembur.index'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page->component('hrd/overtime-approval'));

        $row = collect($response->viewData('page')['props']['overtimeRequests'])->firstWhere('id', $overtimeRequest->id);
        // 3h - 30min break = 150 minutes.
        $this->assertSame(150, $row['requested_minutes']);
    }

    public function test_hrd_cannot_see_another_companys_overtime_request_in_the_list(): void
    {
        [$hrd] = $this->makeHrdAndSubmittedOvertime();
        [, $otherCompanysRequest] = $this->makeHrdAndSubmittedOvertime();

        $response = $this->actingAs($hrd)->get(route('hrd.persetujuan-lembur.index'))->assertOk();

        $ids = collect($response->viewData('page')['props']['overtimeRequests'])->pluck('id');
        $this->assertFalse($ids->contains($otherCompanysRequest->id));
    }

    public function test_hrd_can_approve_the_full_requested_minutes(): void
    {
        [$hrd, $overtimeRequest] = $this->makeHrdAndSubmittedOvertime();

        $this->actingAs($hrd)
            ->patch(route('hrd.persetujuan-lembur.approve', $overtimeRequest), [
                'approved_minutes' => 150, 'note' => 'OK',
            ])
            ->assertSessionDoesntHaveErrors()
            ->assertRedirect();

        $overtimeRequest->refresh();
        $this->assertSame('approved', $overtimeRequest->status);
        $this->assertSame(150, $overtimeRequest->approved_minutes);
        $this->assertSame($hrd->id, $overtimeRequest->processed_by);
    }

    public function test_hrd_cannot_approve_another_companys_overtime_request(): void
    {
        [$hrd] = $this->makeHrdAndSubmittedOvertime();
        [, $otherCompanysRequest] = $this->makeHrdAndSubmittedOvertime();

        $this->actingAs($hrd)
            ->patch(route('hrd.persetujuan-lembur.approve', $otherCompanysRequest), ['approved_minutes' => 150])
            ->assertForbidden();

        $this->assertSame('submitted', $otherCompanysRequest->fresh()->status);
    }

    public function test_hrd_cannot_approve_their_own_overtime_request(): void
    {
        // Submitting one's own is already blocked at
        // StoreOvertimeRequestRequest, but the row is created directly
        // here to prove the controller's own guard holds independently
        // (e.g. if the role changed after submission).
        $hrd = User::factory()->create(['role' => 'hrd']);
        $overtimeRequest = OvertimeRequest::create([
            'user_id' => $hrd->id, 'company_id' => $hrd->company_id,
            'starts_at' => '2026-09-28 18:00:00', 'ends_at' => '2026-09-28 21:00:00',
            'break_minutes' => 30, 'reason' => 'Laporan bulanan', 'status' => 'submitted',
        ]);

        $this->actingAs($hrd)
            ->patch(route('hrd.persetujuan-lembur.approve', $overtimeRequest), ['approved_minutes' => 150])
            ->assertForbidden();

        $this->assertSame('submitted', $overtimeRequest->fresh()->status);
    }

    public function test_hrd_can_approve_fewer_minutes_than_requested(): void
    {
        [$hrd, $overtimeRequest] = $this->makeHrdAndSubmittedOvertime();

        $this->actingAs($hrd)
            ->patch(route('hrd.persetujuan-lembur.approve', $overtimeRequest), [
                'approved_minutes' => 60,
            ])
            ->assertSessionDoesntHaveErrors();

        $this->assertSame(60, $overtimeRequest->fresh()->approved_minutes);
    }

    public function test_approved_minutes_cannot_exceed_the_requested_amount(): void
    {
        [$hrd, $overtimeRequest] = $this->makeHrdAndSubmittedOvertime();

        $this->actingAs($hrd)
            ->patch(route('hrd.persetujuan-lembur.approve', $overtimeRequest), [
                'approved_minutes' => 999,
            ])
            ->assertSessionHasErrors('approved_minutes');

        $this->assertSame('submitted', $overtimeRequest->fresh()->status);
    }

    public function test_hrd_can_reject_with_a_required_note(): void
    {
        [$hrd, $overtimeRequest] = $this->makeHrdAndSubmittedOvertime();

        $this->actingAs($hrd)
            ->patch(route('hrd.persetujuan-lembur.reject', $overtimeRequest), ['note' => ''])
            ->assertSessionHasErrors('note');

        $this->actingAs($hrd)
            ->patch(route('hrd.persetujuan-lembur.reject', $overtimeRequest), [
                'note' => 'Tidak ada bukti pekerjaan tambahan.',
            ])
            ->assertSessionDoesntHaveErrors();

        $overtimeRequest->refresh();
        $this->assertSame('rejected', $overtimeRequest->status);
        $this->assertSame('Tidak ada bukti pekerjaan tambahan.', $overtimeRequest->decision_note);
    }

    public function test_hrd_cannot_reject_another_companys_overtime_request(): void
    {
        [$hrd] = $this->makeHrdAndSubmittedOvertime();
        [, $otherCompanysRequest] = $this->makeHrdAndSubmittedOvertime();

        $this->actingAs($hrd)
            ->patch(route('hrd.persetujuan-lembur.reject', $otherCompanysRequest), ['note' => 'x'])
            ->assertForbidden();

        $this->assertSame('submitted', $otherCompanysRequest->fresh()->status);
    }

    public function test_an_already_processed_request_cannot_be_decided_again(): void
    {
        [$hrd, $overtimeRequest] = $this->makeHrdAndSubmittedOvertime();
        $overtimeRequest->update(['status' => 'approved', 'approved_minutes' => 150]);

        $this->actingAs($hrd)
            ->patch(route('hrd.persetujuan-lembur.approve', $overtimeRequest), ['approved_minutes' => 150])
            ->assertSessionHasErrors('status');
    }

    public function test_karyawan_and_manager_cannot_access_overtime_approval(): void
    {
        [$hrd, $overtimeRequest] = $this->makeHrdAndSubmittedOvertime();

        $karyawan = User::factory()->create(['role' => 'karyawan']);
        $this->actingAs($karyawan)->get(route('hrd.persetujuan-lembur.index'))->assertForbidden();

        $manager = User::factory()->create(['company_id' => $hrd->company_id, 'role' => 'admin']);
        $this->actingAs($manager)
            ->patch(route('hrd.persetujuan-lembur.approve', $overtimeRequest), ['approved_minutes' => 10])
            ->assertForbidden();
    }
}
