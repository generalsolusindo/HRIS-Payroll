<?php

namespace Tests\Feature\Manager;

use App\Models\LeaveRequest;
use App\Models\Position;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LeaveRequestApprovalTest extends TestCase
{
    use RefreshDatabase;

    private function makeLeaveRequest(User $employee, array $overrides = []): LeaveRequest
    {
        return LeaveRequest::create(array_merge([
            'request_number' => 'IZN-2026-'.str_pad((string) random_int(1, 999999), 6, '0', STR_PAD_LEFT),
            'user_id' => $employee->id,
            'type' => 'temporary_exit',
            'leave_date' => today(),
            'start_time' => '10:00',
            'estimated_arrival' => '11:00',
            'status' => 'pending_manager',
            'hrd_processed_by' => User::factory()->create(['role' => 'hrd', 'company_id' => $employee->company_id])->id,
            'hrd_processed_at' => now(),
            'hrd_decision' => 'approved',
        ], $overrides));
    }

    public function test_hrd_cannot_access_manager_approval()
    {
        $hrd = User::factory()->create(['role' => 'hrd']);

        $this->actingAs($hrd)
            ->get(route('manager.izin.index'))
            ->assertForbidden();
    }

    public function test_karyawan_cannot_access_manager_approval()
    {
        $karyawan = User::factory()->create(['role' => 'karyawan']);

        $this->actingAs($karyawan)
            ->get(route('manager.izin.index'))
            ->assertForbidden();
    }

    public function test_manager_sees_requests_awaiting_their_decision()
    {
        $manager = User::factory()->create(['role' => 'admin']);
        $employee = User::factory()->create(['company_id' => $manager->company_id]);
        $leaveRequest = $this->makeLeaveRequest($employee);

        $response = $this->actingAs($manager)
            ->get(route('manager.izin.index'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page->component('manager/leave-request-approval'));

        $ids = collect($response->viewData('page')['props']['leaveRequests'])->pluck('id');
        $this->assertContains($leaveRequest->id, $ids);
    }

    public function test_manager_does_not_see_another_companys_requests()
    {
        $manager = User::factory()->create(['role' => 'admin']);
        $outsider = User::factory()->create();
        $leaveRequest = $this->makeLeaveRequest($outsider);

        $response = $this->actingAs($manager)
            ->get(route('manager.izin.index'))
            ->assertOk();

        $ids = collect($response->viewData('page')['props']['leaveRequests'])->pluck('id');
        $this->assertNotContains($leaveRequest->id, $ids);
    }

    public function test_manager_approve_is_final()
    {
        $manager = User::factory()->create(['role' => 'admin']);
        $employee = User::factory()->create(['company_id' => $manager->company_id]);
        $leaveRequest = $this->makeLeaveRequest($employee);

        $this->actingAs($manager)
            ->patch(route('manager.izin.approve', $leaveRequest), ['note' => 'Disetujui.'])
            ->assertSessionDoesntHaveErrors();

        $leaveRequest->refresh();

        $this->assertSame('approved', $leaveRequest->status);
        $this->assertSame($manager->id, $leaveRequest->processed_by);
        $this->assertSame('Disetujui.', $leaveRequest->approval_note);
    }

    public function test_manager_cannot_approve_their_own_leave_request()
    {
        // Submitting one's own is already blocked at StoreLeaveRequestRequest,
        // but the row is created directly here to prove the controller's
        // own guard holds independently (e.g. if the role changed after
        // submission).
        $manager = User::factory()->create(['role' => 'admin']);
        $leaveRequest = $this->makeLeaveRequest($manager);

        $this->actingAs($manager)
            ->patch(route('manager.izin.approve', $leaveRequest), ['note' => 'Disetujui.'])
            ->assertForbidden();

        $this->assertSame('pending_manager', $leaveRequest->fresh()->status);
    }

    public function test_manager_cannot_approve_another_companys_request()
    {
        $manager = User::factory()->create(['role' => 'admin']);
        $outsider = User::factory()->create();
        $leaveRequest = $this->makeLeaveRequest($outsider);

        $this->actingAs($manager)
            ->patch(route('manager.izin.approve', $leaveRequest), ['note' => 'Disetujui.'])
            ->assertForbidden();

        $this->assertSame('pending_manager', $leaveRequest->fresh()->status);
    }

    public function test_manager_reject_is_final()
    {
        $manager = User::factory()->create(['role' => 'admin']);
        $employee = User::factory()->create(['company_id' => $manager->company_id]);
        $leaveRequest = $this->makeLeaveRequest($employee);

        $this->actingAs($manager)
            ->patch(route('manager.izin.reject', $leaveRequest), ['note' => 'Tidak disetujui.'])
            ->assertSessionDoesntHaveErrors();

        $leaveRequest->refresh();

        $this->assertSame('rejected', $leaveRequest->status);
        $this->assertSame($manager->id, $leaveRequest->processed_by);
        $this->assertSame('Tidak disetujui.', $leaveRequest->rejection_reason);
    }

    public function test_manager_cannot_reject_another_companys_request()
    {
        $manager = User::factory()->create(['role' => 'admin']);
        $outsider = User::factory()->create();
        $leaveRequest = $this->makeLeaveRequest($outsider);

        $this->actingAs($manager)
            ->patch(route('manager.izin.reject', $leaveRequest), ['note' => 'Tidak disetujui.'])
            ->assertForbidden();

        $this->assertSame('pending_manager', $leaveRequest->fresh()->status);
    }

    public function test_reject_requires_a_note()
    {
        $manager = User::factory()->create(['role' => 'admin']);
        $employee = User::factory()->create(['company_id' => $manager->company_id]);
        $leaveRequest = $this->makeLeaveRequest($employee);

        $this->actingAs($manager)
            ->patch(route('manager.izin.reject', $leaveRequest), [])
            ->assertSessionHasErrors('note');

        $this->assertSame('pending_manager', $leaveRequest->fresh()->status);
    }

    public function test_manager_cannot_process_a_request_hrd_has_not_approved_yet()
    {
        $manager = User::factory()->create(['role' => 'admin']);
        $employee = User::factory()->create(['company_id' => $manager->company_id]);
        $leaveRequest = $this->makeLeaveRequest($employee, ['status' => 'pending', 'hrd_processed_by' => null, 'hrd_decision' => null]);

        $this->actingAs($manager)
            ->patch(route('manager.izin.approve', $leaveRequest), [])
            ->assertSessionHasErrors('status');

        $this->assertSame('pending', $leaveRequest->fresh()->status);
    }

    public function test_cannot_process_an_already_finalized_request()
    {
        $manager = User::factory()->create(['role' => 'admin']);
        $employee = User::factory()->create(['company_id' => $manager->company_id]);
        $leaveRequest = $this->makeLeaveRequest($employee, ['status' => 'approved']);

        $this->actingAs($manager)
            ->patch(route('manager.izin.reject', $leaveRequest), ['note' => 'Coba ubah.'])
            ->assertSessionHasErrors('status');

        $this->assertSame('approved', $leaveRequest->fresh()->status);
    }

    public function test_dashboard_stats_count_only_the_managers_own_company()
    {
        $manager = User::factory()->create(['role' => 'admin']);
        $employee = User::factory()->create(['company_id' => $manager->company_id]);
        $this->makeLeaveRequest($employee, ['status' => 'pending_manager']);
        $this->makeLeaveRequest($employee, ['status' => 'approved']);
        $this->makeLeaveRequest($employee, ['status' => 'rejected']);

        $outsider = User::factory()->create();
        $this->makeLeaveRequest($outsider, ['status' => 'pending_manager']);

        $stats = $this->actingAs($manager)
            ->get(route('manager.izin.index'))
            ->assertOk()
            ->viewData('page')['props']['stats'];

        $this->assertSame(3, $stats['total']);
        $this->assertSame(1, $stats['pending']);
        $this->assertSame(1, $stats['approved']);
        $this->assertSame(1, $stats['rejected']);
    }

    public function test_index_search_filters_by_name_or_request_number()
    {
        $manager = User::factory()->create(['role' => 'admin']);
        $budi = User::factory()->create(['company_id' => $manager->company_id, 'name' => 'Budi Santoso']);
        $rahmat = User::factory()->create(['company_id' => $manager->company_id, 'name' => 'Rahmat Hidayat']);
        $match = $this->makeLeaveRequest($budi);
        $noMatch = $this->makeLeaveRequest($rahmat);

        $response = $this->actingAs($manager)
            ->get(route('manager.izin.index', ['search' => 'Budi']))
            ->assertOk();

        $ids = collect($response->viewData('page')['props']['leaveRequests'])->pluck('id');
        $this->assertContains($match->id, $ids);
        $this->assertNotContains($noMatch->id, $ids);
    }

    public function test_index_filters_by_position()
    {
        $manager = User::factory()->create(['role' => 'admin']);
        $positionA = Position::factory()->create();
        $positionB = Position::factory()->create();
        $employeeA = User::factory()->create(['company_id' => $manager->company_id, 'position_id' => $positionA->id]);
        $employeeB = User::factory()->create(['company_id' => $manager->company_id, 'position_id' => $positionB->id]);
        $reqA = $this->makeLeaveRequest($employeeA);
        $reqB = $this->makeLeaveRequest($employeeB);

        $response = $this->actingAs($manager)
            ->get(route('manager.izin.index', ['position_id' => $positionA->id]))
            ->assertOk();

        $ids = collect($response->viewData('page')['props']['leaveRequests'])->pluck('id');
        $this->assertContains($reqA->id, $ids);
        $this->assertNotContains($reqB->id, $ids);
    }

    public function test_karyawan_cannot_access_riwayat_or_laporan()
    {
        $karyawan = User::factory()->create(['role' => 'karyawan']);

        $this->actingAs($karyawan)->get(route('manager.izin.riwayat'))->assertForbidden();
        $this->actingAs($karyawan)->get(route('manager.izin.laporan'))->assertForbidden();
    }

    public function test_riwayat_shows_every_status_for_the_managers_own_company_only()
    {
        $manager = User::factory()->create(['role' => 'admin']);
        $employee = User::factory()->create(['company_id' => $manager->company_id]);
        $pending = $this->makeLeaveRequest($employee, ['status' => 'pending_manager']);
        $approved = $this->makeLeaveRequest($employee, ['status' => 'approved']);

        $outsider = User::factory()->create();
        $outsiderRequest = $this->makeLeaveRequest($outsider);

        $response = $this->actingAs($manager)
            ->get(route('manager.izin.riwayat'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page->component('manager/leave-request-history'));

        $ids = collect($response->viewData('page')['props']['leaveRequests']['data'])->pluck('id');
        $this->assertContains($pending->id, $ids);
        $this->assertContains($approved->id, $ids);
        $this->assertNotContains($outsiderRequest->id, $ids);
    }

    public function test_laporan_downloads_a_csv_scoped_to_the_managers_own_company()
    {
        $manager = User::factory()->create(['role' => 'admin']);
        $employee = User::factory()->create(['company_id' => $manager->company_id, 'name' => 'Budi Santoso']);
        $leaveRequest = $this->makeLeaveRequest($employee, ['status' => 'approved']);

        $outsider = User::factory()->create(['name' => 'Orang Luar Perusahaan']);
        $this->makeLeaveRequest($outsider);

        $response = $this->actingAs($manager)->get(route('manager.izin.laporan'));

        $response->assertOk();
        $this->assertStringContainsString('csv', (string) $response->headers->get('content-type'));

        $content = $response->streamedContent();
        $this->assertStringContainsString($leaveRequest->request_number, $content);
        $this->assertStringContainsString('Budi Santoso', $content);
        $this->assertStringNotContainsString('Orang Luar Perusahaan', $content);
    }
}
