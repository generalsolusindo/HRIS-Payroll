<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class PayrollSchemaTest extends TestCase
{
    use RefreshDatabase;

    private function payroll(): array
    {
        $user = User::factory()->create();
        $division = DB::table('divisions')->insertGetId([
            'company_id' => $user->company_id, 'name' => 'Operasional',
        ]);
        $profile = DB::table('employee_payroll_profiles')->insertGetId([
            'user_id' => $user->id, 'company_id' => $user->company_id,
            'division_id' => $division, 'position_id' => $user->position_id,
            'effective_from' => '2026-10-01', 'employment_started_on' => '2026-10-01',
            'employment_type' => 'permanent',
        ]);
        $policy = DB::table('payroll_policies')->insertGetId([
            'company_id' => $user->company_id, 'code' => 'REGULAR', 'version' => 1,
            'effective_from' => '2026-10-01', 'rules' => '{}',
        ]);
        $period = DB::table('payroll_periods')->insertGetId([
            'company_id' => $user->company_id, 'name' => 'Oktober 2026',
            'starts_on' => '2026-10-01', 'ends_on' => '2026-10-31',
            'attendance_starts_on' => '2026-09-26', 'attendance_ends_on' => '2026-10-25',
        ]);
        $run = DB::table('payroll_runs')->insertGetId([
            'payroll_period_id' => $period, 'version' => 1, 'payroll_policy_id' => $policy,
            'policy_snapshot' => '{}', 'company_snapshot' => '{}',
        ]);
        $entry = DB::table('payroll_entries')->insertGetId([
            'payroll_run_id' => $run, 'user_id' => $user->id,
            'employee_payroll_profile_id' => $profile, 'division_id' => $division,
            'employee_snapshot' => json_encode(['name' => $user->name]),
            'division_name' => 'Operasional', 'position_name' => 'Teknisi',
            'attendance_snapshot' => '{"late_minutes":15}', 'compensation_snapshot' => '{}',
            'gross_amount' => '3000000.00', 'deduction_amount' => '100000.00', 'net_amount' => '2900000.00',
        ]);

        return compact('user', 'division', 'profile', 'policy', 'period', 'run', 'entry');
    }

    public function test_payroll_history_survives_employee_and_division_changes(): void
    {
        $data = $this->payroll();
        $original = DB::table('payroll_entries')->find($data['entry']);
        $data['user']->update(['name' => 'Nama Baru']);
        DB::table('divisions')->where('id', $data['division'])->update(['name' => 'Divisi Baru']);

        $saved = DB::table('payroll_entries')->find($data['entry']);
        $this->assertSame($original->employee_snapshot, $saved->employee_snapshot);
        $this->assertSame('Operasional', $saved->division_name);
        $this->assertEquals('2900000.00', $saved->net_amount);
    }

    public function test_same_employee_cannot_be_added_twice_to_a_run(): void
    {
        $data = $this->payroll();
        $entry = (array) DB::table('payroll_entries')->find($data['entry']);
        unset($entry['id']);
        $this->expectException(QueryException::class);
        DB::table('payroll_entries')->insert($entry);
    }

    public function test_employee_with_payroll_history_cannot_be_deleted(): void
    {
        $data = $this->payroll();
        $this->expectException(QueryException::class);
        $data['user']->delete();
    }

    public function test_payment_retry_cannot_duplicate_a_payment(): void
    {
        $data = $this->payroll();
        $payment = [
            'payroll_entry_id' => $data['entry'],
            'idempotency_key' => 'bb5ab116-f980-452d-969b-2b58ab186214',
            'amount' => '2900000.00', 'method' => 'bank_transfer',
        ];
        DB::table('payroll_payments')->insert($payment);
        $this->expectException(QueryException::class);
        DB::table('payroll_payments')->insert($payment);
    }

    public function test_revision_retains_previous_run_and_approval_actor_snapshot(): void
    {
        $data = $this->payroll();
        $manager = User::factory()->create();
        DB::table('payroll_approvals')->insert([
            'payroll_run_id' => $data['run'], 'action' => 'revision_requested',
            'from_status' => 'submitted', 'to_status' => 'revision_requested',
            'actor_id' => $manager->id, 'actor_snapshot' => '{"name":"Manager"}',
            'note' => 'Periksa reward.',
        ]);
        $run = (array) DB::table('payroll_runs')->find($data['run']);
        unset($run['id']);
        $run['version'] = 2;
        $run['supersedes_run_id'] = $data['run'];
        DB::table('payroll_runs')->insert($run);
        $manager->delete();

        $this->assertDatabaseCount('payroll_runs', 2);
        $this->assertDatabaseHas('payroll_approvals', [
            'payroll_run_id' => $data['run'], 'actor_id' => null,
            'actor_snapshot' => '{"name":"Manager"}',
        ]);
    }

    public function test_missing_attendance_can_be_reviewed_without_creating_a_fake_punch(): void
    {
        $user = User::factory()->create();
        DB::table('attendance_reviews')->insert([
            'user_id' => $user->id, 'company_id' => $user->company_id,
            'date' => '2026-10-01', 'source_snapshot' => '{}', 'schedule_snapshot' => '{}',
        ]);
        $this->assertDatabaseHas('attendance_reviews', [
            'user_id' => $user->id, 'attendance_id' => null,
            'classification' => 'unresolved', 'status' => 'draft',
        ]);
        $this->assertDatabaseCount('attendances', 0);
    }

    public function test_new_migrations_can_roll_back_and_reapply_without_dropping_existing_tables(): void
    {
        $files = glob(database_path('migrations/2026_10_03_100*.php'));
        foreach (array_reverse($files) as $file) {
            (require $file)->down();
        }
        $this->assertFalse(Schema::hasTable('payroll_runs'));
        $this->assertFalse(Schema::hasTable('divisions'));
        $this->assertTrue(Schema::hasTable('attendances'));
        $this->assertTrue(Schema::hasTable('users'));
        foreach ($files as $file) {
            (require $file)->up();
        }
        $this->assertTrue(Schema::hasTable('payroll_payslips'));
    }
}
