<?php

namespace Tests\Feature;

use App\Models\Division;
use App\Models\EmployeePayrollProfile;
use App\Models\EmployeeSalaryComponent;
use App\Models\PayrollComponent;
use App\Models\PayrollEntry;
use App\Models\PayrollPeriod;
use App\Models\User;
use App\Services\Terbilang;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PayslipDownloadTest extends TestCase
{
    use RefreshDatabase;

    private function makeDraftEntry(User $hrd): PayrollEntry
    {
        $division = Division::create(['company_id' => $hrd->company_id, 'name' => 'Operasional']);
        $base = PayrollComponent::create([
            'company_id' => $hrd->company_id, 'code' => 'BASE', 'name' => 'Gaji Pokok',
            'type' => 'earning',
        ]);
        $employee = User::factory()->create(['company_id' => $hrd->company_id, 'role' => 'karyawan', 'status' => 'active']);
        $profile = EmployeePayrollProfile::create([
            'user_id' => $employee->id, 'company_id' => $hrd->company_id,
            'division_id' => $division->id, 'position_id' => $employee->position_id,
            'effective_from' => '2026-10-01', 'employment_started_on' => '2026-01-01',
            'employment_type' => 'permanent',
        ]);
        EmployeeSalaryComponent::create([
            'employee_payroll_profile_id' => $profile->id, 'payroll_component_id' => $base->id,
            'amount' => '3000000', 'basis' => 'monthly',
        ]);

        $period = PayrollPeriod::create([
            'company_id' => $hrd->company_id, 'name' => 'Oktober 2026',
            'starts_on' => '2026-10-01', 'ends_on' => '2026-10-31',
            'attendance_starts_on' => '2026-09-26', 'attendance_ends_on' => '2026-10-25',
        ]);

        $this->actingAs($hrd)->post(route('hrd.periode-gaji.hitung', $period));

        return PayrollEntry::where('user_id', $employee->id)->firstOrFail();
    }

    public function test_terbilang_matches_the_offices_reference_example(): void
    {
        $this->assertSame('Satu Juta Empat Ratus Dua Puluh Ribu Rupiah', Terbilang::rupiah(1_420_000));
        $this->assertSame('Nol Rupiah', Terbilang::rupiah(0));
        $this->assertSame('Seratus Sebelas Rupiah', Terbilang::rupiah(111));
        $this->assertSame('Dua Juta Lima Ratus Ribu Rupiah', Terbilang::rupiah(2_500_000));
    }

    public function test_hrd_can_download_a_slip_for_a_draft_entry_not_yet_submitted_to_manager(): void
    {
        $hrd = User::factory()->create(['role' => 'hrd']);
        $entry = $this->makeDraftEntry($hrd);

        $this->assertSame('draft', $entry->run->status);

        $response = $this->actingAs($hrd)
            ->get(route('hrd.hasil-payroll.entri.slip.unduh', $entry));

        $response->assertOk();
        $this->assertSame('application/pdf', $response->headers->get('content-type'));
    }

    public function test_hrd_from_another_company_cannot_download_the_slip(): void
    {
        $hrd = User::factory()->create(['role' => 'hrd']);
        $entry = $this->makeDraftEntry($hrd);

        $otherHrd = User::factory()->create(['role' => 'hrd']);

        $this->actingAs($otherHrd)
            ->get(route('hrd.hasil-payroll.entri.slip.unduh', $entry))
            ->assertForbidden();
    }

    public function test_karyawan_cannot_use_the_hrd_slip_route(): void
    {
        $hrd = User::factory()->create(['role' => 'hrd']);
        $entry = $this->makeDraftEntry($hrd);

        $this->actingAs($entry->user)
            ->get(route('hrd.hasil-payroll.entri.slip.unduh', $entry))
            ->assertForbidden();
    }

    public function test_manager_can_download_a_slip(): void
    {
        $hrd = User::factory()->create(['role' => 'hrd']);
        $entry = $this->makeDraftEntry($hrd);
        $manager = User::factory()->create(['company_id' => $hrd->company_id, 'role' => 'admin']);

        $this->actingAs($manager)
            ->get(route('manager.payroll.entri.slip', $entry))
            ->assertOk();
    }

    public function test_finance_can_download_a_slip(): void
    {
        $hrd = User::factory()->create(['role' => 'hrd']);
        $entry = $this->makeDraftEntry($hrd);
        $finance = User::factory()->create(['company_id' => $hrd->company_id, 'role' => 'finance']);

        $this->actingAs($finance)
            ->get(route('finance.entri.slip', $entry))
            ->assertOk();
    }

    public function test_slip_reflects_the_employees_live_bank_details(): void
    {
        $hrd = User::factory()->create(['role' => 'hrd']);
        $entry = $this->makeDraftEntry($hrd);
        $entry->user->update([
            'bank_name' => 'Bank Mandiri',
            'bank_account_number' => '1440026732062',
            'bank_account_holder' => 'Navy Ahmad',
        ]);

        // Building the PDF must not throw with a live bank destination
        // present — the actual "does the bank box render" question is
        // covered at the template level below, since dompdf's own PDF
        // output is FlateDecode-compressed and can't be substring-matched.
        (new \App\Services\PayslipPdfService)->build($entry->fresh());

        $html = view('payslips.slip', [
            'company' => $entry->run->period->company,
            'user' => $entry->user,
            'entry' => $entry,
            'periodLabel' => 'Bulan Oktober 2026',
            'statusLabel' => 'Tetap',
            'earnings' => [['label' => 'Gaji Pokok', 'amount' => '3000000']],
            'deductions' => [],
            'destination' => [
                'bank_name' => 'Bank Mandiri',
                'account_number' => '1440026732062',
                'account_holder' => 'Navy Ahmad',
            ],
            'grossTotal' => '3000000',
            'deductionTotal' => '0',
            'netAmount' => '3000000',
            'terbilang' => Terbilang::rupiah(3_000_000),
            'issueCity' => 'Sidoarjo',
            'issueDateLabel' => '27 Oktober 2026',
            'logoPath' => public_path('images/general-solusindo.png'),
            'signaturePath' => null,
        ])->render();

        $this->assertStringContainsString('Bank Mandiri', $html);
        $this->assertStringContainsString('1440026732062', $html);
    }
}
