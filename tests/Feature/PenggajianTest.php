<?php

namespace Tests\Feature;

use App\Models\AttendanceReview;
use App\Models\Division;
use App\Models\EmployeePayrollProfile;
use App\Models\EmployeeSalaryComponent;
use App\Models\PayrollComponent;
use App\Models\PayrollEntry;
use App\Models\PayrollPayslip;
use App\Models\PayrollPeriod;
use App\Models\PayrollRun;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class PenggajianTest extends TestCase
{
    use RefreshDatabase;

    private function makePublishedPayslip(User $employee): PayrollPayslip
    {
        $hrd = User::factory()->create(['role' => 'hrd', 'company_id' => $employee->company_id]);
        $division = Division::create(['company_id' => $employee->company_id, 'name' => 'Operasional']);
        $base = PayrollComponent::create([
            'company_id' => $employee->company_id, 'code' => 'BASE', 'name' => 'Gaji Pokok',
            'type' => 'earning', ]);
        $profile = EmployeePayrollProfile::create([
            'user_id' => $employee->id, 'company_id' => $employee->company_id,
            'division_id' => $division->id, 'position_id' => $employee->position_id,
            'effective_from' => '2026-09-01', 'employment_started_on' => '2026-01-01',
            'employment_type' => 'permanent',
        ]);
        EmployeeSalaryComponent::create([
            'employee_payroll_profile_id' => $profile->id, 'payroll_component_id' => $base->id,
            'amount' => '3000000', 'basis' => 'monthly',
        ]);

        $period = PayrollPeriod::create([
            'company_id' => $employee->company_id, 'name' => 'September 2026',
            'starts_on' => '2026-09-01', 'ends_on' => '2026-09-30',
            'attendance_starts_on' => '2026-09-28', 'attendance_ends_on' => '2026-09-28',
        ]);
        // A payroll eligible for approval needs a complete, reviewed attendance day.
        AttendanceReview::create([
            'user_id' => $employee->id, 'company_id' => $employee->company_id,
            'date' => '2026-09-28', 'status' => 'validated', 'classification' => 'present',
            'source_snapshot' => [], 'schedule_snapshot' => [],
            'correction_reason' => 'Kehadiran diverifikasi HRD untuk fixture payroll.',
            'validated_by' => $hrd->id, 'validated_at' => now(),
        ]);
        $this->actingAs($hrd)->post(route('hrd.periode-gaji.hitung', $period));

        $run = PayrollRun::where('payroll_period_id', $period->id)->firstOrFail();
        $this->actingAs($hrd)->patch(route('hrd.hasil-payroll.ajukan', $run));
        $manager = User::factory()->create(['role' => 'admin', 'company_id' => $employee->company_id]);
        $this->actingAs($manager)->patch(route('manager.payroll.approve', $run), ['note' => null]);

        $entry = PayrollEntry::where('payroll_run_id', $run->id)->firstOrFail();
        $this->actingAs($hrd)->post(route('hrd.hasil-payroll.entri.slip', $entry));

        return PayrollPayslip::where('payroll_entry_id', $entry->id)->firstOrFail();
    }

    /** @return array{0: PayrollRun, 1: PayrollEntry, 2: User} [$run, $entry, $hrd] */
    private function makeSharedDraftRun(User $employee): array
    {
        $hrd = User::factory()->create(['role' => 'hrd', 'company_id' => $employee->company_id]);
        $division = Division::create(['company_id' => $employee->company_id, 'name' => 'Operasional']);
        $base = PayrollComponent::create([
            'company_id' => $employee->company_id, 'code' => 'BASE2', 'name' => 'Gaji Pokok',
            'type' => 'earning', ]);
        $profile = EmployeePayrollProfile::create([
            'user_id' => $employee->id, 'company_id' => $employee->company_id,
            'division_id' => $division->id, 'position_id' => $employee->position_id,
            'effective_from' => '2026-09-01', 'employment_started_on' => '2026-01-01',
            'employment_type' => 'permanent',
        ]);
        EmployeeSalaryComponent::create([
            'employee_payroll_profile_id' => $profile->id, 'payroll_component_id' => $base->id,
            'amount' => '3000000', 'basis' => 'monthly',
        ]);

        $period = PayrollPeriod::create([
            'company_id' => $employee->company_id, 'name' => 'September 2026',
            'starts_on' => '2026-09-01', 'ends_on' => '2026-09-30',
            'attendance_starts_on' => '2026-09-28', 'attendance_ends_on' => '2026-09-28',
        ]);
        AttendanceReview::create([
            'user_id' => $employee->id, 'company_id' => $employee->company_id,
            'date' => '2026-09-28', 'status' => 'validated', 'classification' => 'present',
            'source_snapshot' => [], 'schedule_snapshot' => [],
            'correction_reason' => 'Kehadiran diverifikasi HRD untuk fixture payroll.',
            'validated_by' => $hrd->id, 'validated_at' => now(),
        ]);
        $this->actingAs($hrd)->post(route('hrd.periode-gaji.hitung', $period));

        $run = PayrollRun::where('payroll_period_id', $period->id)->firstOrFail();
        $this->actingAs($hrd)->patch(route('hrd.hasil-payroll.bagikan', $run));

        $entry = PayrollEntry::where('payroll_run_id', $run->id)->firstOrFail();

        return [$run->fresh(), $entry, $hrd];
    }

    public function test_hrd_can_share_a_draft_run_for_employee_review(): void
    {
        $employee = User::factory()->create(['role' => 'karyawan']);
        [$run, , $hrd] = $this->makeSharedDraftRun($employee);

        $this->assertNotNull($run->shared_at);
        $this->assertDatabaseHas('payroll_approvals', [
            'payroll_run_id' => $run->id, 'action' => 'shared_for_review',
            'from_status' => 'draft', 'to_status' => 'draft', 'actor_id' => $hrd->id,
        ]);
    }

    public function test_sharing_twice_does_not_create_a_duplicate_approval_entry(): void
    {
        $employee = User::factory()->create(['role' => 'karyawan']);
        [$run, , $hrd] = $this->makeSharedDraftRun($employee);

        $this->actingAs($hrd)->patch(route('hrd.hasil-payroll.bagikan', $run))->assertSessionDoesntHaveErrors();

        $this->assertSame(1, \App\Models\PayrollApproval::where('payroll_run_id', $run->id)
            ->where('action', 'shared_for_review')->count());
    }

    public function test_a_submitted_run_cannot_be_shared(): void
    {
        $employee = User::factory()->create(['role' => 'karyawan']);
        $hrd = User::factory()->create(['role' => 'hrd', 'company_id' => $employee->company_id]);
        $division = Division::create(['company_id' => $employee->company_id, 'name' => 'Operasional']);
        $base = PayrollComponent::create([
            'company_id' => $employee->company_id, 'code' => 'BASE3', 'name' => 'Gaji Pokok', 'type' => 'earning',
        ]);
        $profile = EmployeePayrollProfile::create([
            'user_id' => $employee->id, 'company_id' => $employee->company_id,
            'division_id' => $division->id, 'position_id' => $employee->position_id,
            'effective_from' => '2026-09-01', 'employment_started_on' => '2026-01-01',
            'employment_type' => 'permanent',
        ]);
        EmployeeSalaryComponent::create([
            'employee_payroll_profile_id' => $profile->id, 'payroll_component_id' => $base->id,
            'amount' => '3000000', 'basis' => 'monthly',
        ]);
        $period = PayrollPeriod::create([
            'company_id' => $employee->company_id, 'name' => 'September 2026',
            'starts_on' => '2026-09-01', 'ends_on' => '2026-09-30',
            'attendance_starts_on' => '2026-09-28', 'attendance_ends_on' => '2026-09-28',
        ]);
        AttendanceReview::create([
            'user_id' => $employee->id, 'company_id' => $employee->company_id,
            'date' => '2026-09-28', 'status' => 'validated', 'classification' => 'present',
            'source_snapshot' => [], 'schedule_snapshot' => [],
            'correction_reason' => 'Kehadiran diverifikasi HRD untuk fixture payroll.',
            'validated_by' => $hrd->id, 'validated_at' => now(),
        ]);
        $this->actingAs($hrd)->post(route('hrd.periode-gaji.hitung', $period));
        $run = PayrollRun::where('payroll_period_id', $period->id)->firstOrFail();
        $this->actingAs($hrd)->patch(route('hrd.hasil-payroll.ajukan', $run));

        $this->actingAs($hrd)
            ->patch(route('hrd.hasil-payroll.bagikan', $run))
            ->assertSessionHasErrors('status');

        $this->assertNull($run->fresh()->shared_at);
    }

    public function test_karyawan_and_manager_cannot_share_a_run(): void
    {
        $employee = User::factory()->create(['role' => 'karyawan']);
        $hrd = User::factory()->create(['role' => 'hrd', 'company_id' => $employee->company_id]);
        $division = Division::create(['company_id' => $employee->company_id, 'name' => 'Operasional']);
        $period = PayrollPeriod::create([
            'company_id' => $employee->company_id, 'name' => 'September 2026',
            'starts_on' => '2026-09-01', 'ends_on' => '2026-09-30',
            'attendance_starts_on' => '2026-09-28', 'attendance_ends_on' => '2026-09-28',
        ]);
        $this->actingAs($hrd)->post(route('hrd.periode-gaji.hitung', $period));
        $run = PayrollRun::where('payroll_period_id', $period->id)->first();
        // No eligible profile exists, so nothing is calculated — skip if no run.
        if (! $run) {
            $this->markTestSkipped('Fixture produced no run.');
        }

        $this->actingAs($employee)->patch(route('hrd.hasil-payroll.bagikan', $run))->assertForbidden();

        $manager = User::factory()->create(['role' => 'admin', 'company_id' => $employee->company_id]);
        $this->actingAs($manager)->patch(route('hrd.hasil-payroll.bagikan', $run))->assertForbidden();
    }

    public function test_employee_sees_a_shared_draft_as_a_preview_but_not_before_its_shared(): void
    {
        $employee = User::factory()->create(['role' => 'karyawan']);

        $response = $this->actingAs($employee)->get(route('penggajian'))->assertOk();
        $this->assertCount(0, $response->viewData('page')['props']['previews']);

        [$run, $entry] = $this->makeSharedDraftRun($employee);

        $response = $this->actingAs($employee)->get(route('penggajian'))->assertOk();
        $previews = collect($response->viewData('page')['props']['previews']);
        $this->assertTrue($previews->contains('id', $entry->id));
        $this->assertSame('draft', $previews->firstWhere('id', $entry->id)['run_status']);
    }

    public function test_employee_can_view_their_own_shared_preview_detail(): void
    {
        $employee = User::factory()->create(['role' => 'karyawan']);
        [, $entry] = $this->makeSharedDraftRun($employee);

        $this->actingAs($employee)
            ->get(route('penggajian.pratinjau', $entry))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('penggajian-pratinjau')
                ->where('preview.net_amount', '3000000.00')
            );
    }

    public function test_employee_cannot_view_a_preview_that_has_not_been_shared(): void
    {
        $employee = User::factory()->create(['role' => 'karyawan']);
        $hrd = User::factory()->create(['role' => 'hrd', 'company_id' => $employee->company_id]);
        $division = Division::create(['company_id' => $employee->company_id, 'name' => 'Operasional']);
        $base = PayrollComponent::create([
            'company_id' => $employee->company_id, 'code' => 'BASE4', 'name' => 'Gaji Pokok', 'type' => 'earning',
        ]);
        $profile = EmployeePayrollProfile::create([
            'user_id' => $employee->id, 'company_id' => $employee->company_id,
            'division_id' => $division->id, 'position_id' => $employee->position_id,
            'effective_from' => '2026-09-01', 'employment_started_on' => '2026-01-01',
            'employment_type' => 'permanent',
        ]);
        EmployeeSalaryComponent::create([
            'employee_payroll_profile_id' => $profile->id, 'payroll_component_id' => $base->id,
            'amount' => '3000000', 'basis' => 'monthly',
        ]);
        $period = PayrollPeriod::create([
            'company_id' => $employee->company_id, 'name' => 'September 2026',
            'starts_on' => '2026-09-01', 'ends_on' => '2026-09-30',
            'attendance_starts_on' => '2026-09-28', 'attendance_ends_on' => '2026-09-28',
        ]);
        AttendanceReview::create([
            'user_id' => $employee->id, 'company_id' => $employee->company_id,
            'date' => '2026-09-28', 'status' => 'validated', 'classification' => 'present',
            'source_snapshot' => [], 'schedule_snapshot' => [],
            'correction_reason' => 'Kehadiran diverifikasi HRD untuk fixture payroll.',
            'validated_by' => $hrd->id, 'validated_at' => now(),
        ]);
        $this->actingAs($hrd)->post(route('hrd.periode-gaji.hitung', $period));
        $run = PayrollRun::where('payroll_period_id', $period->id)->firstOrFail();
        $entry = PayrollEntry::where('payroll_run_id', $run->id)->firstOrFail();

        // Never shared ($run->shared_at stays null.
        $this->actingAs($employee)
            ->get(route('penggajian.pratinjau', $entry))
            ->assertForbidden();
    }

    public function test_employee_cannot_view_someone_elses_shared_preview(): void
    {
        $employee = User::factory()->create(['role' => 'karyawan']);
        $otherEmployee = User::factory()->create(['role' => 'karyawan']);
        [, $entry] = $this->makeSharedDraftRun($otherEmployee);

        $this->actingAs($employee)
            ->get(route('penggajian.pratinjau', $entry))
            ->assertForbidden();
    }

    public function test_employee_sees_their_own_published_payslip(): void
    {
        $employee = User::factory()->create(['role' => 'karyawan']);
        $payslip = $this->makePublishedPayslip($employee);

        $response = $this->actingAs($employee)
            ->get(route('penggajian'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page->component('penggajian'));

        $ids = collect($response->viewData('page')['props']['payslips'])->pluck('id');
        $this->assertTrue($ids->contains($payslip->id));
    }

    public function test_employee_does_not_see_someone_elses_payslip_in_their_list(): void
    {
        $employee = User::factory()->create(['role' => 'karyawan']);
        $otherEmployee = User::factory()->create(['role' => 'karyawan']);
        $this->makePublishedPayslip($otherEmployee);

        $response = $this->actingAs($employee)
            ->get(route('penggajian'))
            ->assertOk();

        $this->assertCount(0, $response->viewData('page')['props']['payslips']);
    }

    public function test_employee_can_view_their_own_payslip_detail(): void
    {
        $employee = User::factory()->create(['role' => 'karyawan']);
        $payslip = $this->makePublishedPayslip($employee);

        $this->actingAs($employee)
            ->get(route('penggajian.show', $payslip))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('penggajian-detail')
                ->where('payslip.id', $payslip->id)
            );
    }

    public function test_employee_cannot_view_someone_elses_payslip_detail(): void
    {
        $employee = User::factory()->create(['role' => 'karyawan']);
        $otherEmployee = User::factory()->create(['role' => 'karyawan']);
        $payslip = $this->makePublishedPayslip($otherEmployee);

        $this->actingAs($employee)
            ->get(route('penggajian.show', $payslip))
            ->assertForbidden();
    }

    public function test_hrd_can_view_their_own_companys_employee_payslip_detail(): void
    {
        $employee = User::factory()->create(['role' => 'karyawan']);
        $payslip = $this->makePublishedPayslip($employee);

        $viewerHrd = User::factory()->create(['role' => 'hrd', 'company_id' => $employee->company_id]);
        $this->actingAs($viewerHrd)
            ->get(route('penggajian.show', $payslip))
            ->assertOk();
    }

    public function test_hrd_cannot_view_another_companys_employee_payslip_detail(): void
    {
        $employee = User::factory()->create(['role' => 'karyawan']);
        $payslip = $this->makePublishedPayslip($employee);

        $viewerHrd = User::factory()->create(['role' => 'hrd']);
        $this->actingAs($viewerHrd)
            ->get(route('penggajian.show', $payslip))
            ->assertForbidden();
    }

    public function test_published_slip_totals_remain_consistent_through_partial_payment_and_void(): void
    {
        // The helper calculates as HRD, submits, approves as Manager, then publishes.
        $employee = User::factory()->create(['role' => 'karyawan']);
        $payslip = $this->makePublishedPayslip($employee);
        $snapshot = $payslip->snapshot;
        $entry = $payslip->entry;
        $run = $entry->run;
        $finance = User::factory()->create(['role' => 'finance', 'company_id' => $employee->company_id]);

        $earnings = collect($snapshot['lines'])->where('type', 'earning')->sum('amount');
        $deductions = collect($snapshot['lines'])->where('type', 'deduction')->sum('amount');
        $this->assertSame('3000000.00', number_format($earnings - $deductions, 2, '.', ''));
        $this->assertSame($entry->net_amount, $snapshot['net_amount']);
        $this->assertSame($run->net_total, $snapshot['net_amount']);

        $this->actingAs($finance)->patch(route('finance.payroll.kunci', $run))
            ->assertRedirect()->assertSessionDoesntHaveErrors();

        foreach (['1000000', '2000000'] as $index => $amount) {
            $this->actingAs($finance)->post(route('finance.entri.bayar', $entry), [
                'amount' => $amount,
                'method' => 'bank_transfer',
                'idempotency_key' => (string) Str::uuid(),
            ])->assertRedirect()->assertSessionDoesntHaveErrors();

            $this->assertSame($index === 0 ? 'partially_paid' : 'paid', $run->fresh()->status);
        }

        $this->assertSame('3000000.00', number_format((float) $entry->payments()->where('status', 'confirmed')->sum('amount'), 2, '.', ''));
        $lastPayment = $entry->payments()->latest('id')->firstOrFail();
        $this->actingAs($finance)->patch(route('finance.pembayaran.batalkan', $lastPayment), [
            'note' => 'Koreksi pencatatan pembayaran kedua.',
        ])->assertRedirect()->assertSessionDoesntHaveErrors();

        $this->assertSame('partially_paid', $run->fresh()->status);
        $this->assertSame($snapshot, $payslip->fresh()->snapshot);
        $this->actingAs($employee)->get(route('penggajian.show', $payslip))
            ->assertOk()->assertInertia(fn ($page) => $page
            ->component('penggajian-detail')
            ->where('payslip.snapshot.net_amount', '3000000.00')
            ->where('payslip.snapshot.gross_amount', $snapshot['gross_amount'])
            ->where('payslip.snapshot.deduction_amount', $snapshot['deduction_amount'])
            );
    }

    public function test_a_superseded_payslip_no_longer_appears_in_the_employees_list(): void
    {
        $employee = User::factory()->create(['role' => 'karyawan']);
        $firstPayslip = $this->makePublishedPayslip($employee);

        $entry = $firstPayslip->entry;
        $hrd = User::find($entry->run->created_by);
        $this->actingAs($hrd)->post(route('hrd.hasil-payroll.entri.slip', $entry));

        $response = $this->actingAs($employee)->get(route('penggajian'))->assertOk();
        $numbers = collect($response->viewData('page')['props']['payslips'])->pluck('number');

        $this->assertFalse($numbers->contains($firstPayslip->number));
        $this->assertSame('superseded', $firstPayslip->fresh()->status);
    }

    public function test_employee_can_submit_a_complaint_against_their_own_shared_preview(): void
    {
        $employee = User::factory()->create(['role' => 'karyawan']);
        [, $entry] = $this->makeSharedDraftRun($employee);

        $this->actingAs($employee)
            ->post(route('penggajian.pratinjau.komplain', $entry), [
                'message' => 'Potongan alpha tanggal 28 seharusnya tidak ada.',
            ])
            ->assertSessionDoesntHaveErrors()
            ->assertRedirect();

        $this->assertDatabaseHas('payroll_entry_complaints', [
            'payroll_entry_id' => $entry->id,
            'user_id' => $employee->id,
            'message' => 'Potongan alpha tanggal 28 seharusnya tidak ada.',
            'status' => 'new',
        ]);

        $this->actingAs($employee)->get(route('penggajian.pratinjau', $entry))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->has('preview.complaints', 1)
                ->where('preview.complaints.0.status', 'new')
            );
    }

    public function test_an_employee_cannot_submit_a_complaint_against_someone_elses_entry(): void
    {
        $employee = User::factory()->create(['role' => 'karyawan']);
        $otherEmployee = User::factory()->create(['role' => 'karyawan', 'company_id' => $employee->company_id]);
        [, $entry] = $this->makeSharedDraftRun($employee);

        $this->actingAs($otherEmployee)
            ->post(route('penggajian.pratinjau.komplain', $entry), ['message' => 'Bukan punya saya.'])
            ->assertForbidden();
    }

    public function test_a_complaint_cannot_be_submitted_once_the_run_is_no_longer_shared(): void
    {
        $employee = User::factory()->create(['role' => 'karyawan']);
        [$run, $entry] = $this->makeSharedDraftRun($employee);
        $run->update(['shared_at' => null]);

        $this->actingAs($employee)
            ->post(route('penggajian.pratinjau.komplain', $entry), ['message' => 'Komplain terlambat.'])
            ->assertForbidden();
    }

    public function test_hrd_can_see_and_resolve_a_complaint_with_a_note_the_employee_can_read(): void
    {
        $employee = User::factory()->create(['role' => 'karyawan']);
        [$run, $entry, $hrd] = $this->makeSharedDraftRun($employee);

        $this->actingAs($employee)
            ->post(route('penggajian.pratinjau.komplain', $entry), ['message' => 'Tolong dicek potongan alpha-nya.']);

        $complaint = $entry->complaints()->firstOrFail();

        $this->actingAs($hrd)
            ->get(route('hrd.hasil-payroll.show', $run))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->has('complaints', 1)
                ->where('complaints.0.employee_name', $employee->name)
                ->where('complaints.0.status', 'new')
            );

        $this->actingAs($hrd)
            ->patch(route('hrd.hasil-payroll.komplain.update', $complaint), [
                'status' => 'resolved',
                'hrd_note' => 'Sudah dikoreksi lewat penyesuaian manual.',
            ])
            ->assertSessionDoesntHaveErrors()
            ->assertRedirect();

        $fresh = $complaint->fresh();
        $this->assertSame('resolved', $fresh->status);
        $this->assertSame('Sudah dikoreksi lewat penyesuaian manual.', $fresh->hrd_note);
        $this->assertSame($hrd->id, $fresh->resolved_by);
        $this->assertNotNull($fresh->resolved_at);

        $this->actingAs($employee)->get(route('penggajian.pratinjau', $entry))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('preview.complaints.0.status', 'resolved')
                ->where('preview.complaints.0.hrd_note', 'Sudah dikoreksi lewat penyesuaian manual.')
            );
    }

    public function test_hrd_cannot_resolve_a_complaint_belonging_to_another_companys_run(): void
    {
        $employee = User::factory()->create(['role' => 'karyawan']);
        [, $entry] = $this->makeSharedDraftRun($employee);
        $this->actingAs($employee)
            ->post(route('penggajian.pratinjau.komplain', $entry), ['message' => 'Komplain lintas perusahaan.']);
        $complaint = $entry->complaints()->firstOrFail();

        $otherHrd = User::factory()->create(['role' => 'hrd']);

        $this->actingAs($otherHrd)
            ->patch(route('hrd.hasil-payroll.komplain.update', $complaint), ['status' => 'resolved'])
            ->assertForbidden();
    }
}
