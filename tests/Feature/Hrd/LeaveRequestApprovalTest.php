<?php

namespace Tests\Feature\Hrd;

use App\Models\Division;
use App\Models\LeaveRequest;
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
            'status' => 'pending',
        ], $overrides));
    }

    public function test_karyawan_cannot_access_leave_request_approval()
    {
        $karyawan = User::factory()->create(['role' => 'karyawan']);

        $this->actingAs($karyawan)
            ->get(route('hrd.izin.index'))
            ->assertForbidden();
    }

    public function test_hrd_sees_pending_leave_requests()
    {
        $hrd = User::factory()->create(['role' => 'hrd']);
        $employee = User::factory()->create(['company_id' => $hrd->company_id]);
        $leaveRequest = $this->makeLeaveRequest($employee);

        $response = $this->actingAs($hrd)
            ->get(route('hrd.izin.index'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page->component('hrd/leave-request-approval'));

        $ids = collect($response->viewData('page')['props']['leaveRequests'])->pluck('id');
        $this->assertContains($leaveRequest->id, $ids);
    }

    public function test_hrd_does_not_see_another_companys_pending_leave_requests()
    {
        $hrd = User::factory()->create(['role' => 'hrd']);
        $outsider = User::factory()->create();
        $leaveRequest = $this->makeLeaveRequest($outsider);

        $response = $this->actingAs($hrd)
            ->get(route('hrd.izin.index'))
            ->assertOk();

        $ids = collect($response->viewData('page')['props']['leaveRequests'])->pluck('id');
        $this->assertNotContains($leaveRequest->id, $ids);
    }

    public function test_hrd_approve_moves_to_pending_manager()
    {
        $hrd = User::factory()->create(['role' => 'hrd']);
        $employee = User::factory()->create(['company_id' => $hrd->company_id]);
        $leaveRequest = $this->makeLeaveRequest($employee);

        $this->actingAs($hrd)
            ->patch(route('hrd.izin.approve', $leaveRequest), ['note' => 'Oke, silakan.'])
            ->assertSessionDoesntHaveErrors()
            ->assertRedirect();

        $leaveRequest->refresh();

        $this->assertSame('pending_manager', $leaveRequest->status);
        $this->assertSame($hrd->id, $leaveRequest->hrd_processed_by);
        $this->assertSame('approved', $leaveRequest->hrd_decision);
        $this->assertSame('Oke, silakan.', $leaveRequest->hrd_note);
        $this->assertNull($leaveRequest->processed_by);
    }

    public function test_hrd_cannot_approve_another_companys_leave_request()
    {
        $hrd = User::factory()->create(['role' => 'hrd']);
        $outsider = User::factory()->create();
        $leaveRequest = $this->makeLeaveRequest($outsider);

        $this->actingAs($hrd)
            ->patch(route('hrd.izin.approve', $leaveRequest), ['note' => 'Oke, silakan.'])
            ->assertForbidden();

        $this->assertSame('pending', $leaveRequest->fresh()->status);
    }

    public function test_hrd_cannot_approve_their_own_leave_request()
    {
        // Submitting one's own is already blocked at StoreLeaveRequestRequest,
        // but the row is created directly here to prove the controller's
        // own guard holds independently (e.g. if the role changed after
        // submission).
        $hrd = User::factory()->create(['role' => 'hrd']);
        $leaveRequest = $this->makeLeaveRequest($hrd);

        $this->actingAs($hrd)
            ->patch(route('hrd.izin.approve', $leaveRequest), ['note' => 'Oke, silakan.'])
            ->assertForbidden();

        $this->assertSame('pending', $leaveRequest->fresh()->status);
    }

    public function test_hrd_reject_is_final_immediately()
    {
        $hrd = User::factory()->create(['role' => 'hrd']);
        $employee = User::factory()->create(['company_id' => $hrd->company_id]);
        $leaveRequest = $this->makeLeaveRequest($employee);

        $this->actingAs($hrd)
            ->patch(route('hrd.izin.reject', $leaveRequest), ['note' => 'Bukti tidak lengkap.'])
            ->assertSessionDoesntHaveErrors();

        $leaveRequest->refresh();

        $this->assertSame('rejected', $leaveRequest->status);
        $this->assertSame($hrd->id, $leaveRequest->hrd_processed_by);
        $this->assertSame('rejected', $leaveRequest->hrd_decision);
        $this->assertSame($hrd->id, $leaveRequest->processed_by);
        $this->assertSame('Bukti tidak lengkap.', $leaveRequest->rejection_reason);
    }

    public function test_hrd_cannot_reject_another_companys_leave_request()
    {
        $hrd = User::factory()->create(['role' => 'hrd']);
        $outsider = User::factory()->create();
        $leaveRequest = $this->makeLeaveRequest($outsider);

        $this->actingAs($hrd)
            ->patch(route('hrd.izin.reject', $leaveRequest), ['note' => 'Bukti tidak lengkap.'])
            ->assertForbidden();

        $this->assertSame('pending', $leaveRequest->fresh()->status);
    }

    public function test_reject_requires_a_note()
    {
        $hrd = User::factory()->create(['role' => 'hrd']);
        $employee = User::factory()->create(['company_id' => $hrd->company_id]);
        $leaveRequest = $this->makeLeaveRequest($employee);

        $this->actingAs($hrd)
            ->patch(route('hrd.izin.reject', $leaveRequest), [])
            ->assertSessionHasErrors('note');

        $this->assertSame('pending', $leaveRequest->fresh()->status);
    }

    public function test_cannot_process_an_already_processed_request()
    {
        $hrd = User::factory()->create(['role' => 'hrd']);
        $employee = User::factory()->create(['company_id' => $hrd->company_id]);
        $leaveRequest = $this->makeLeaveRequest($employee, ['status' => 'rejected']);

        $this->actingAs($hrd)
            ->patch(route('hrd.izin.approve', $leaveRequest), [])
            ->assertSessionHasErrors('status');

        $this->assertSame('rejected', $leaveRequest->fresh()->status);
    }

    public function test_dashboard_stats_count_only_the_hrds_own_company()
    {
        $hrd = User::factory()->create(['role' => 'hrd']);
        $employee = User::factory()->create(['company_id' => $hrd->company_id]);
        $this->makeLeaveRequest($employee, ['status' => 'pending']);
        $this->makeLeaveRequest($employee, ['status' => 'pending']);
        $this->makeLeaveRequest($employee, ['status' => 'approved']);
        $this->makeLeaveRequest($employee, ['status' => 'rejected']);

        $outsider = User::factory()->create();
        $this->makeLeaveRequest($outsider, ['status' => 'pending']);

        $stats = $this->actingAs($hrd)
            ->get(route('hrd.izin.index'))
            ->assertOk()
            ->viewData('page')['props']['stats'];

        $this->assertSame(4, $stats['total']);
        $this->assertSame(2, $stats['pending']);
        $this->assertSame(1, $stats['approved']);
        $this->assertSame(1, $stats['rejected']);
        $this->assertSame(4, $stats['today']);
    }

    public function test_index_search_filters_by_name_or_request_number()
    {
        $hrd = User::factory()->create(['role' => 'hrd']);
        $budi = User::factory()->create(['company_id' => $hrd->company_id, 'name' => 'Budi Santoso']);
        $rahmat = User::factory()->create(['company_id' => $hrd->company_id, 'name' => 'Rahmat Hidayat']);
        $match = $this->makeLeaveRequest($budi);
        $noMatch = $this->makeLeaveRequest($rahmat);

        $response = $this->actingAs($hrd)
            ->get(route('hrd.izin.index', ['search' => 'Budi']))
            ->assertOk();

        $ids = collect($response->viewData('page')['props']['leaveRequests'])->pluck('id');
        $this->assertContains($match->id, $ids);
        $this->assertNotContains($noMatch->id, $ids);
    }

    public function test_index_filters_by_type()
    {
        $hrd = User::factory()->create(['role' => 'hrd']);
        $employee = User::factory()->create(['company_id' => $hrd->company_id]);
        $late = $this->makeLeaveRequest($employee, ['type' => 'late', 'estimated_arrival' => '08:00']);
        $exit = $this->makeLeaveRequest($employee, ['type' => 'temporary_exit']);

        $response = $this->actingAs($hrd)
            ->get(route('hrd.izin.index', ['type' => 'late']))
            ->assertOk();

        $ids = collect($response->viewData('page')['props']['leaveRequests'])->pluck('id');
        $this->assertContains($late->id, $ids);
        $this->assertNotContains($exit->id, $ids);
    }

    public function test_index_filters_by_division()
    {
        $hrd = User::factory()->create(['role' => 'hrd']);
        $divisionA = Division::factory()->create(['company_id' => $hrd->company_id]);
        $divisionB = Division::factory()->create(['company_id' => $hrd->company_id]);
        $employeeA = User::factory()->create(['company_id' => $hrd->company_id, 'division_id' => $divisionA->id]);
        $employeeB = User::factory()->create(['company_id' => $hrd->company_id, 'division_id' => $divisionB->id]);
        $reqA = $this->makeLeaveRequest($employeeA);
        $reqB = $this->makeLeaveRequest($employeeB);

        $response = $this->actingAs($hrd)
            ->get(route('hrd.izin.index', ['division_id' => $divisionA->id]))
            ->assertOk();

        $ids = collect($response->viewData('page')['props']['leaveRequests'])->pluck('id');
        $this->assertContains($reqA->id, $ids);
        $this->assertNotContains($reqB->id, $ids);
    }

    public function test_karyawan_cannot_access_riwayat_or_laporan()
    {
        $karyawan = User::factory()->create(['role' => 'karyawan']);

        $this->actingAs($karyawan)->get(route('hrd.izin.riwayat'))->assertForbidden();
        $this->actingAs($karyawan)->get(route('hrd.izin.laporan'))->assertForbidden();
    }

    public function test_riwayat_shows_every_status_for_the_hrds_own_company_only()
    {
        $hrd = User::factory()->create(['role' => 'hrd']);
        $employee = User::factory()->create(['company_id' => $hrd->company_id]);
        $pending = $this->makeLeaveRequest($employee, ['status' => 'pending']);
        $approved = $this->makeLeaveRequest($employee, ['status' => 'approved']);

        $outsider = User::factory()->create();
        $outsiderRequest = $this->makeLeaveRequest($outsider);

        $response = $this->actingAs($hrd)
            ->get(route('hrd.izin.riwayat'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page->component('hrd/leave-request-history'));

        $ids = collect($response->viewData('page')['props']['leaveRequests']['data'])->pluck('id');
        $this->assertContains($pending->id, $ids);
        $this->assertContains($approved->id, $ids);
        $this->assertNotContains($outsiderRequest->id, $ids);
    }

    public function test_riwayat_can_be_narrowed_by_status()
    {
        $hrd = User::factory()->create(['role' => 'hrd']);
        $employee = User::factory()->create(['company_id' => $hrd->company_id]);
        $approved = $this->makeLeaveRequest($employee, ['status' => 'approved']);
        $pending = $this->makeLeaveRequest($employee, ['status' => 'pending']);

        $response = $this->actingAs($hrd)
            ->get(route('hrd.izin.riwayat', ['status' => 'approved']))
            ->assertOk();

        $ids = collect($response->viewData('page')['props']['leaveRequests']['data'])->pluck('id');
        $this->assertContains($approved->id, $ids);
        $this->assertNotContains($pending->id, $ids);
    }

    public function test_laporan_downloads_a_csv_scoped_to_the_hrds_own_company()
    {
        $hrd = User::factory()->create(['role' => 'hrd']);
        $employee = User::factory()->create(['company_id' => $hrd->company_id, 'name' => 'Budi Santoso']);
        $leaveRequest = $this->makeLeaveRequest($employee, ['status' => 'approved']);

        $outsider = User::factory()->create(['name' => 'Orang Luar Perusahaan']);
        $this->makeLeaveRequest($outsider);

        $response = $this->actingAs($hrd)->get(route('hrd.izin.laporan'));

        $response->assertOk();
        $this->assertStringContainsString('csv', (string) $response->headers->get('content-type'));

        $content = $response->streamedContent();
        $this->assertStringContainsString($leaveRequest->request_number, $content);
        $this->assertStringContainsString('Budi Santoso', $content);
        $this->assertStringNotContainsString('Orang Luar Perusahaan', $content);
    }

    public function test_laporan_neutralizes_formula_like_names_against_csv_injection()
    {
        $hrd = User::factory()->create(['role' => 'hrd']);
        // A name is free text the employee sets themselves (see
        // ProfileValidationRules) — a formula-looking one must never reach
        // the export as a live formula for Excel/Sheets to execute.
        $employee = User::factory()->create([
            'company_id' => $hrd->company_id,
            'name' => '=HYPERLINK("http://evil.example","click")',
        ]);
        $this->makeLeaveRequest($employee);

        $content = $this->actingAs($hrd)
            ->get(route('hrd.izin.laporan'))
            ->assertOk()
            ->streamedContent();

        $this->assertStringContainsString('HYPERLINK', $content, 'The name should still appear, just neutralized.');
        $this->assertStringNotContainsString('"=HYPERLINK', $content, 'A cell must never start with = directly after the opening quote — that is a live formula to Excel/Sheets.');
        $this->assertStringContainsString("\t=HYPERLINK", $content, 'A tab prefix should keep the value readable but inert as a formula.');
    }
}
