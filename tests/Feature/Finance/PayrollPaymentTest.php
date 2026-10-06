<?php

namespace Tests\Feature\Finance;

use App\Models\Division;
use App\Models\EmployeePayrollProfile;
use App\Models\EmployeeSalaryComponent;
use App\Models\PayrollComponent;
use App\Models\PayrollEntry;
use App\Models\PayrollPayment;
use App\Models\PayrollPeriod;
use App\Models\PayrollRun;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class PayrollPaymentTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('payment_proofs');
    }

    /**
     * Builds an "approved" run with one employee entry (net 3,000,000,
     * bank destination set) ready for Finance to act on.
     */
    private function makeApprovedRun(): PayrollRun
    {
        $hrd = User::factory()->create(['role' => 'hrd']);
        $division = Division::create(['company_id' => $hrd->company_id, 'code' => 'OPS', 'name' => 'Operasional']);
        $base = PayrollComponent::create([
            'company_id' => $hrd->company_id, 'code' => 'BASE', 'name' => 'Gaji Pokok',
            'type' => 'earning', 'category' => 'base_salary',
        ]);
        $employee = User::factory()->create(['company_id' => $hrd->company_id, 'role' => 'karyawan', 'status' => 'active']);
        $profile = EmployeePayrollProfile::create([
            'user_id' => $employee->id, 'company_id' => $hrd->company_id,
            'division_id' => $division->id, 'position_id' => $employee->position_id,
            'effective_from' => '2026-09-01', 'employment_started_on' => '2026-01-01',
            'employment_type' => 'permanent',
            'bank_name' => 'BCA', 'bank_account_number' => '111222333', 'bank_account_holder' => $employee->name,
        ]);
        EmployeeSalaryComponent::create([
            'employee_payroll_profile_id' => $profile->id, 'payroll_component_id' => $base->id,
            'amount' => '3000000', 'basis' => 'monthly',
        ]);

        $period = PayrollPeriod::create([
            'company_id' => $hrd->company_id, 'code' => '2026-09', 'name' => 'September 2026',
            'starts_on' => '2026-09-01', 'ends_on' => '2026-09-30',
            'attendance_starts_on' => '2026-09-28', 'attendance_ends_on' => '2026-09-28',
        ]);
        $this->actingAs($hrd)->post(route('hrd.periode-gaji.hitung', $period));

        $run = PayrollRun::where('payroll_period_id', $period->id)->firstOrFail();
        $this->actingAs($hrd)->patch(route('hrd.hasil-payroll.ajukan', $run));

        $manager = User::factory()->create(['role' => 'admin']);
        $this->actingAs($manager)->patch(route('manager.payroll.approve', $run), ['note' => null]);

        return $run->fresh();
    }

    public function test_hrd_and_karyawan_cannot_access_finance_payroll(): void
    {
        $run = $this->makeApprovedRun();

        $hrd = User::factory()->create(['role' => 'hrd']);
        $this->actingAs($hrd)->get(route('finance.payroll.index'))->assertForbidden();

        $karyawan = User::factory()->create(['role' => 'karyawan']);
        $this->actingAs($karyawan)->get(route('finance.payroll.show', $run))->assertForbidden();
    }

    public function test_finance_sees_only_approved_and_later_statuses(): void
    {
        $finance = User::factory()->create(['role' => 'finance']);
        $approvedRun = $this->makeApprovedRun();

        $response = $this->actingAs($finance)
            ->get(route('finance.payroll.index'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page->component('finance/payroll-payments'));

        $ids = collect($response->viewData('page')['props']['runs'])->pluck('id');
        $this->assertTrue($ids->contains($approvedRun->id));
    }

    public function test_finance_can_lock_an_approved_run(): void
    {
        $finance = User::factory()->create(['role' => 'finance']);
        $run = $this->makeApprovedRun();

        $this->actingAs($finance)
            ->patch(route('finance.payroll.kunci', $run))
            ->assertSessionDoesntHaveErrors()
            ->assertRedirect();

        $this->assertSame('locked', $run->fresh()->status);
        $this->assertDatabaseHas('payroll_approvals', [
            'payroll_run_id' => $run->id, 'action' => 'locked',
            'from_status' => 'approved', 'to_status' => 'locked',
        ]);
    }

    public function test_locking_a_non_approved_run_fails(): void
    {
        $finance = User::factory()->create(['role' => 'finance']);
        $run = $this->makeApprovedRun();
        $run->update(['status' => 'locked']);

        $this->actingAs($finance)
            ->patch(route('finance.payroll.kunci', $run))
            ->assertSessionHasErrors('status');
    }

    public function test_payment_cannot_be_recorded_before_locking(): void
    {
        $finance = User::factory()->create(['role' => 'finance']);
        $run = $this->makeApprovedRun();
        $entry = PayrollEntry::where('payroll_run_id', $run->id)->firstOrFail();

        $this->actingAs($finance)
            ->post(route('finance.entri.bayar', $entry), ['amount' => '3000000', 'method' => 'bank_transfer'])
            ->assertStatus(422);

        $this->assertSame(0, PayrollPayment::count());
    }

    public function test_a_full_payment_marks_the_run_as_paid_and_copies_the_destination(): void
    {
        $finance = User::factory()->create(['role' => 'finance']);
        $run = $this->makeApprovedRun();
        $run->update(['status' => 'locked']);
        $entry = PayrollEntry::where('payroll_run_id', $run->id)->firstOrFail();

        $proof = UploadedFile::fake()->create('bukti.pdf', 100, 'application/pdf');

        $this->actingAs($finance)
            ->post(route('finance.entri.bayar', $entry), [
                'amount' => '3000000', 'method' => 'bank_transfer', 'reference' => 'TRX001', 'proof' => $proof,
            ])
            ->assertSessionDoesntHaveErrors()
            ->assertRedirect();

        $payment = PayrollPayment::where('payroll_entry_id', $entry->id)->firstOrFail();
        $this->assertSame('confirmed', $payment->status);
        $this->assertSame($finance->id, $payment->recorded_by);
        $this->assertSame($finance->id, $payment->confirmed_by);
        $this->assertNotNull($payment->proof_path);
        Storage::disk('payment_proofs')->assertExists($payment->proof_path);
        $this->assertSame('111222333', $payment->destination_snapshot['account_number']);

        $raw = DB::table('payroll_payments')->where('id', $payment->id)->value('destination_snapshot');
        $this->assertStringNotContainsString('111222333', (string) $raw);

        $this->assertSame('paid', $run->fresh()->status);
    }

    public function test_proof_must_be_an_image_or_pdf(): void
    {
        $finance = User::factory()->create(['role' => 'finance']);
        $run = $this->makeApprovedRun();
        $run->update(['status' => 'locked']);
        $entry = PayrollEntry::where('payroll_run_id', $run->id)->firstOrFail();

        $proof = UploadedFile::fake()->create('script.exe', 100, 'application/x-msdownload');

        $this->actingAs($finance)
            ->post(route('finance.entri.bayar', $entry), [
                'amount' => '3000000', 'method' => 'cash', 'proof' => $proof,
            ])
            ->assertSessionHasErrors('proof');

        $this->assertSame(0, PayrollPayment::count());
    }

    public function test_partial_payments_accumulate_until_the_entry_is_fully_paid(): void
    {
        $finance = User::factory()->create(['role' => 'finance']);
        $run = $this->makeApprovedRun();
        $run->update(['status' => 'locked']);
        $entry = PayrollEntry::where('payroll_run_id', $run->id)->firstOrFail();

        $this->actingAs($finance)->post(route('finance.entri.bayar', $entry), [
            'amount' => '2000000', 'method' => 'cash',
        ]);
        $this->assertSame('partially_paid', $run->fresh()->status);

        $this->actingAs($finance)->post(route('finance.entri.bayar', $entry), [
            'amount' => '1000000', 'method' => 'cash',
        ]);
        $this->assertSame('paid', $run->fresh()->status);
        $this->assertSame(2, PayrollPayment::where('payroll_entry_id', $entry->id)->count());
    }

    public function test_payment_amount_cannot_exceed_the_remaining_balance(): void
    {
        $finance = User::factory()->create(['role' => 'finance']);
        $run = $this->makeApprovedRun();
        $run->update(['status' => 'locked']);
        $entry = PayrollEntry::where('payroll_run_id', $run->id)->firstOrFail();

        $this->actingAs($finance)
            ->post(route('finance.entri.bayar', $entry), ['amount' => '9999999', 'method' => 'cash'])
            ->assertSessionHasErrors('amount');

        $this->assertSame(0, PayrollPayment::count());
    }

    public function test_voiding_a_payment_reverts_the_run_status(): void
    {
        $finance = User::factory()->create(['role' => 'finance']);
        $run = $this->makeApprovedRun();
        $run->update(['status' => 'locked']);
        $entry = PayrollEntry::where('payroll_run_id', $run->id)->firstOrFail();

        $this->actingAs($finance)->post(route('finance.entri.bayar', $entry), [
            'amount' => '3000000', 'method' => 'cash',
        ]);
        $this->assertSame('paid', $run->fresh()->status);

        $payment = PayrollPayment::where('payroll_entry_id', $entry->id)->firstOrFail();

        $this->actingAs($finance)
            ->patch(route('finance.pembayaran.batalkan', $payment), ['note' => 'Salah input nominal.'])
            ->assertSessionDoesntHaveErrors();

        $this->assertSame('voided', $payment->fresh()->status);
        $this->assertSame('locked', $run->fresh()->status);
    }

    public function test_proof_file_is_only_viewable_by_finance(): void
    {
        $finance = User::factory()->create(['role' => 'finance']);
        $run = $this->makeApprovedRun();
        $run->update(['status' => 'locked']);
        $entry = PayrollEntry::where('payroll_run_id', $run->id)->firstOrFail();

        $proof = UploadedFile::fake()->create('bukti.pdf', 50, 'application/pdf');
        $this->actingAs($finance)->post(route('finance.entri.bayar', $entry), [
            'amount' => '3000000', 'method' => 'cash', 'proof' => $proof,
        ]);
        $payment = PayrollPayment::where('payroll_entry_id', $entry->id)->firstOrFail();

        $this->actingAs($finance)
            ->get(route('finance.pembayaran.bukti', $payment))
            ->assertOk();

        $hrd = User::factory()->create(['role' => 'hrd']);
        $this->actingAs($hrd)
            ->get(route('finance.pembayaran.bukti', $payment))
            ->assertForbidden();
    }
}
