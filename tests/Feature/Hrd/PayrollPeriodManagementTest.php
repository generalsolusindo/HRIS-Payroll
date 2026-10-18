<?php

namespace Tests\Feature\Hrd;

use App\Models\Company;
use App\Models\Division;
use App\Models\EmployeePayrollProfile;
use App\Models\PayrollComponent;
use App\Models\PayrollPeriod;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PayrollPeriodManagementTest extends TestCase
{
    use RefreshDatabase;

    private function createCalculatedPeriod(User $hrd): PayrollPeriod
    {
        $division = Division::create(['company_id' => $hrd->company_id, 'name' => 'Operasional']);
        $component = PayrollComponent::create([
            'company_id' => $hrd->company_id, 'code' => 'BASE', 'name' => 'Gaji Pokok', 'type' => 'earning',
        ]);
        $employee = User::factory()->create([
            'company_id' => $hrd->company_id,
            'role' => 'karyawan',
            'status' => 'active',
        ]);
        $profile = EmployeePayrollProfile::create([
            'user_id' => $employee->id,
            'company_id' => $hrd->company_id,
            'division_id' => $division->id,
            'position_id' => $employee->position_id,
            'effective_from' => '2026-10-01',
            'employment_started_on' => '2026-01-01',
            'employment_type' => 'permanent',
        ]);
        $profile->salaryComponents()->create([
            'payroll_component_id' => $component->id,
            'amount' => '2500000',
            'basis' => 'monthly',
        ]);
        $period = PayrollPeriod::create($this->payload($hrd->company_id));

        $this->actingAs($hrd)
            ->post(route('hrd.periode-gaji.hitung', $period))
            ->assertSessionDoesntHaveErrors();

        return $period->refresh();
    }

    private function payload(int $companyId, array $overrides = []): array
    {
        return array_merge([
            'company_id' => $companyId,
            'name' => 'Oktober 2026',
            'starts_on' => '2026-10-01',
            'ends_on' => '2026-10-31',
            'attendance_starts_on' => '2026-09-26',
            'attendance_ends_on' => '2026-10-25',
        ], $overrides);
    }

    public function test_hrd_can_create_a_payroll_period(): void
    {
        $hrd = User::factory()->create(['role' => 'hrd']);

        $this->actingAs($hrd)
            ->post(route('hrd.periode-gaji.store'), $this->payload($hrd->company_id))
            ->assertSessionDoesntHaveErrors();

        $this->assertDatabaseHas('payroll_periods', ['name' => 'Oktober 2026']);
    }

    public function test_period_name_must_be_unique_within_a_company(): void
    {
        $hrd = User::factory()->create(['role' => 'hrd']);
        PayrollPeriod::create($this->payload($hrd->company_id));

        $this->actingAs($hrd)
            ->post(route('hrd.periode-gaji.store'), $this->payload($hrd->company_id, [
                'starts_on' => '2026-11-01', 'ends_on' => '2026-11-30',
            ]))
            ->assertSessionHasErrors('name');
    }

    public function test_a_different_name_for_the_same_date_range_is_rejected(): void
    {
        // The DB has its own unique constraint on (company_id, starts_on,
        // ends_on) — this must surface as a normal validation error, not
        // a raw SQL exception, when the name differs but the dates match.
        $hrd = User::factory()->create(['role' => 'hrd']);
        PayrollPeriod::create($this->payload($hrd->company_id));

        $this->actingAs($hrd)
            ->post(route('hrd.periode-gaji.store'), $this->payload($hrd->company_id, [
                'name' => 'Oktober 2026 (revisi)',
            ]))
            ->assertSessionHasErrors('starts_on');

        $this->assertSame(1, PayrollPeriod::where('company_id', $hrd->company_id)->count());
    }

    public function test_ends_on_must_not_be_before_starts_on(): void
    {
        $hrd = User::factory()->create(['role' => 'hrd']);

        $this->actingAs($hrd)
            ->post(route('hrd.periode-gaji.store'), $this->payload($hrd->company_id, [
                'starts_on' => '2026-10-31', 'ends_on' => '2026-10-01',
            ]))
            ->assertSessionHasErrors('ends_on');
    }

    public function test_hrd_only_sees_their_own_companys_periods(): void
    {
        $hrd = User::factory()->create(['role' => 'hrd']);
        $otherHrd = User::factory()->create(['role' => 'hrd']);
        PayrollPeriod::create($this->payload($otherHrd->company_id));

        $response = $this->actingAs($hrd)->get(route('hrd.periode-gaji.index'))->assertOk();

        $this->assertCount(0, $response->viewData('page')['props']['periods']);
    }

    public function test_a_period_created_for_another_company_is_ignored(): void
    {
        $hrd = User::factory()->create(['role' => 'hrd']);
        $otherCompany = Company::factory()->create();

        $this->actingAs($hrd)
            ->post(route('hrd.periode-gaji.store'), $this->payload($otherCompany->id))
            ->assertSessionDoesntHaveErrors();

        $this->assertDatabaseHas('payroll_periods', ['name' => 'Oktober 2026', 'company_id' => $hrd->company_id]);
        $this->assertDatabaseMissing('payroll_periods', ['name' => 'Oktober 2026', 'company_id' => $otherCompany->id]);
    }

    public function test_karyawan_cannot_access_payroll_period_management(): void
    {
        $karyawan = User::factory()->create(['role' => 'karyawan']);

        $this->actingAs($karyawan)
            ->get(route('hrd.periode-gaji.index'))
            ->assertForbidden();
    }

    public function test_hrd_can_delete_a_period_that_has_never_been_calculated(): void
    {
        $hrd = User::factory()->create(['role' => 'hrd']);
        $period = PayrollPeriod::create($this->payload($hrd->company_id));

        $this->actingAs($hrd)
            ->delete(route('hrd.periode-gaji.destroy', $period))
            ->assertSessionDoesntHaveErrors();

        $this->assertModelMissing($period);
    }

    public function test_a_period_with_an_existing_run_cannot_be_deleted(): void
    {
        $hrd = User::factory()->create(['role' => 'hrd']);
        $division = Division::create(['company_id' => $hrd->company_id, 'name' => 'Operasional']);
        $base = PayrollComponent::create([
            'company_id' => $hrd->company_id, 'code' => 'BASE', 'name' => 'Gaji Pokok', 'type' => 'earning',
        ]);
        $employee = User::factory()->create(['company_id' => $hrd->company_id, 'role' => 'karyawan', 'status' => 'active']);
        $profile = EmployeePayrollProfile::create([
            'user_id' => $employee->id, 'company_id' => $hrd->company_id,
            'division_id' => $division->id, 'position_id' => $employee->position_id,
            'effective_from' => '2026-10-01', 'employment_started_on' => '2026-01-01',
            'employment_type' => 'permanent',
        ]);
        $profile->salaryComponents()->create(['payroll_component_id' => $base->id, 'amount' => '2500000', 'basis' => 'monthly']);
        $period = PayrollPeriod::create($this->payload($hrd->company_id));

        $this->actingAs($hrd)->post(route('hrd.periode-gaji.hitung', $period));

        $this->actingAs($hrd)
            ->delete(route('hrd.periode-gaji.destroy', $period))
            ->assertSessionHasErrors('period');

        $this->assertModelExists($period);
    }

    public function test_a_calculated_period_is_archived_only_after_manager_approval_and_its_run_is_preserved(): void
    {
        $hrd = User::factory()->create(['role' => 'hrd']);
        $manager = User::factory()->create(['company_id' => $hrd->company_id, 'role' => 'admin']);
        $period = $this->createCalculatedPeriod($hrd);
        $run = $period->runs()->firstOrFail();

        $this->actingAs($hrd)
            ->post(route('hrd.periode-gaji.minta-hapus', $period), ['reason' => 'Periode dibuat untuk simulasi.'])
            ->assertSessionDoesntHaveErrors();

        $request = $period->deletionRequests()->firstOrFail();
        $this->assertSame('pending', $request->status);
        $this->assertNull($period->fresh()->archived_at);

        $this->actingAs($manager)
            ->patch(route('manager.payroll.hapus-periode.approve', $request))
            ->assertSessionDoesntHaveErrors();

        $this->assertSame('approved', $request->fresh()->status);
        $this->assertNotNull($period->fresh()->archived_at);
        $this->assertModelExists($run);

        $response = $this->actingAs($hrd)->get(route('hrd.periode-gaji.index'))->assertOk();
        $this->assertCount(0, $response->viewData('page')['props']['periods']);
    }

    public function test_manager_can_reject_a_period_deletion_request_with_a_reason(): void
    {
        $hrd = User::factory()->create(['role' => 'hrd']);
        $manager = User::factory()->create(['company_id' => $hrd->company_id, 'role' => 'admin']);
        $period = $this->createCalculatedPeriod($hrd);

        $this->actingAs($hrd)
            ->post(route('hrd.periode-gaji.minta-hapus', $period), ['reason' => 'Periode duplikat.'])
            ->assertSessionDoesntHaveErrors();
        $request = $period->deletionRequests()->firstOrFail();

        $this->actingAs($manager)
            ->patch(route('manager.payroll.hapus-periode.reject', $request), [
                'decision_note' => 'Periode masih dibutuhkan untuk proses payroll.',
            ])
            ->assertSessionDoesntHaveErrors();

        $this->assertSame('rejected', $request->fresh()->status);
        $this->assertNull($period->fresh()->archived_at);
    }

    public function test_an_uncalculated_period_cannot_request_manager_approved_deletion(): void
    {
        $hrd = User::factory()->create(['role' => 'hrd']);
        $period = PayrollPeriod::create($this->payload($hrd->company_id));

        $this->actingAs($hrd)
            ->post(route('hrd.periode-gaji.minta-hapus', $period), ['reason' => 'Tidak diperlukan.'])
            ->assertSessionHasErrors('period');

        $this->assertDatabaseMissing('payroll_period_deletion_requests', [
            'payroll_period_id' => $period->id,
        ]);
    }

    public function test_a_period_cannot_be_archived_after_payroll_is_approved(): void
    {
        $hrd = User::factory()->create(['role' => 'hrd']);
        $period = $this->createCalculatedPeriod($hrd);
        $period->runs()->firstOrFail()->update(['status' => 'approved']);

        $this->actingAs($hrd)
            ->post(route('hrd.periode-gaji.minta-hapus', $period), ['reason' => 'Periode duplikat.'])
            ->assertSessionHasErrors('period');

        $this->assertDatabaseMissing('payroll_period_deletion_requests', [
            'payroll_period_id' => $period->id,
        ]);
    }

    public function test_hrd_can_edit_dates_of_a_never_calculated_period(): void
    {
        $hrd = User::factory()->create(['role' => 'hrd']);
        $period = PayrollPeriod::create($this->payload($hrd->company_id));

        $this->actingAs($hrd)
            ->patch(route('hrd.periode-gaji.update', $period), $this->payload($hrd->company_id, [
                'attendance_starts_on' => '2026-09-28', 'attendance_ends_on' => '2026-10-27',
            ]))
            ->assertSessionDoesntHaveErrors();

        $fresh = $period->fresh();
        $this->assertSame('2026-09-28', $fresh->attendance_starts_on->toDateString());
        $this->assertSame('2026-10-27', $fresh->attendance_ends_on->toDateString());
    }

    public function test_hrd_can_edit_dates_while_the_only_run_is_still_a_draft(): void
    {
        $hrd = User::factory()->create(['role' => 'hrd']);
        $period = $this->createCalculatedPeriod($hrd);

        $this->actingAs($hrd)
            ->patch(route('hrd.periode-gaji.update', $period), $this->payload($hrd->company_id, [
                'attendance_ends_on' => '2026-10-26',
            ]))
            ->assertSessionDoesntHaveErrors();

        $this->assertSame('2026-10-26', $period->fresh()->attendance_ends_on->toDateString());
    }

    public function test_dates_cannot_be_edited_once_the_run_has_been_submitted(): void
    {
        $hrd = User::factory()->create(['role' => 'hrd']);
        $period = $this->createCalculatedPeriod($hrd);
        $period->runs()->firstOrFail()->update(['status' => 'submitted']);

        $this->actingAs($hrd)
            ->patch(route('hrd.periode-gaji.update', $period), $this->payload($hrd->company_id))
            ->assertSessionHasErrors('period');

        $this->assertSame('2026-10-25', $period->fresh()->attendance_ends_on->toDateString());
    }

    public function test_hrd_cannot_edit_another_companys_period_dates(): void
    {
        $hrd = User::factory()->create(['role' => 'hrd']);
        $otherHrd = User::factory()->create(['role' => 'hrd']);
        $period = PayrollPeriod::create($this->payload($otherHrd->company_id));

        $this->actingAs($hrd)
            ->patch(route('hrd.periode-gaji.update', $period), $this->payload($otherHrd->company_id))
            ->assertForbidden();
    }

    public function test_hrd_cannot_delete_another_companys_period(): void
    {
        $hrd = User::factory()->create(['role' => 'hrd']);
        $otherHrd = User::factory()->create(['role' => 'hrd']);
        $period = PayrollPeriod::create($this->payload($otherHrd->company_id));

        $this->actingAs($hrd)
            ->delete(route('hrd.periode-gaji.destroy', $period))
            ->assertForbidden();

        $this->assertModelExists($period);
    }
}
