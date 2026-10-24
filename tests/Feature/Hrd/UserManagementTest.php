<?php

namespace Tests\Feature\Hrd;

use App\Models\Company;
use App\Models\Division;
use App\Models\EmployeePayrollProfile;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class UserManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_employee_numbers_are_sequential_and_never_reused_after_deletion(): void
    {
        $first = User::factory()->create();
        $second = User::factory()->create();
        $this->assertSame('0001', $first->employee_number);
        $this->assertSame('0002', $second->employee_number);
        $second->delete();
        $third = User::factory()->create();
        $this->assertSame('0003', $third->employee_number);
        $first->update(['name' => 'Changed']);
        $this->assertSame('0001', $first->fresh()->employee_number);
    }

    public function test_hrd_cannot_override_generated_number_and_can_choose_all_employee_types(): void
    {
        [$company, $division] = $this->makeCompanyWithDivision();
        $hrd = User::factory()->create(['role' => 'hrd', 'company_id' => $company->id]);
        foreach (User::EMPLOYMENT_TYPES as $index => $type) {
            $this->actingAs($hrd)->post(route('hrd.users.store'), $this->payload($company, $division, [
                'email' => "employee{$index}@example.com", 'employment_type' => $type, 'employee_number' => 'CUSTOM',
            ]))->assertSessionHasNoErrors()->assertRedirect();
            $employee = User::where('email', "employee{$index}@example.com")->firstOrFail();
            $this->assertSame(str_pad((string) ($index + 1), 4, '0', STR_PAD_LEFT), $employee->employee_number);
            $this->assertSame($type, $employee->employment_type);
        }
    }

    public function test_user_type_updates_current_payroll_profile(): void
    {
        $profile = $this->makePayrollProfile(User::factory()->create(), 'permanent');
        $user = $profile->user;
        $user->update(['employment_type' => 'part_time']);
        $this->assertSame('part_time', $profile->fresh()->employment_type);
    }

    public function test_employment_started_on_updates_current_payroll_profile(): void
    {
        $profile = $this->makePayrollProfile(User::factory()->create(), 'permanent');
        $user = $profile->user;
        $user->update(['employment_started_on' => '2020-05-01']);
        $this->assertSame('2020-05-01', $profile->fresh()->employment_started_on->toDateString());
    }

    public function test_migration_preserves_numbers_backfills_missing_and_keeps_known_types(): void
    {
        $existing = User::factory()->create(['employee_number' => '0042']);
        $missing = User::factory()->create();
        $profile = $this->makePayrollProfile($missing, 'intern');
        DB::table('users')->where('id', $missing->id)->update(['employee_number' => null]);
        $migration = require database_path('migrations/2026_10_09_120000_add_employee_identity_and_sequence.php');
        $migration->down();
        $migration->up();
        $this->assertSame('0042', $existing->fresh()->employee_number);
        $this->assertSame('0043', $missing->fresh()->employee_number);
        $this->assertSame('intern', $missing->fresh()->employment_type);
        $this->assertSame('0044', User::factory()->create()->employee_number);
    }

    private function makePayrollProfile(User $user, string $type): EmployeePayrollProfile
    {
        $division = Division::create(['company_id' => $user->company_id, 'name' => 'Operasional']);

        return EmployeePayrollProfile::create([
            'user_id' => $user->id, 'company_id' => $user->company_id,
            'position_id' => $user->position_id, 'division_id' => $division->id,
            'effective_from' => '2026-10-01', 'employment_started_on' => '2026-10-01', 'employment_type' => $type,
        ]);
    }

    private function makeCompanyWithDivision(): array
    {
        $company = Company::factory()->create();
        $division = Division::factory()->create(['company_id' => $company->id]);

        return [$company, $division];
    }

    private function payload(Company $company, Division $division, array $overrides = []): array
    {
        return array_merge([
            'name' => 'Karyawan Baru',
            'email' => 'baru@example.com',
            'phone' => '08123456789',
            'employee_number' => null,
            'employment_started_on' => '2026-01-01',
            'employment_type' => 'permanent',
            'is_payroll_eligible' => true,
            'company_id' => $company->id,
            'division_id' => $division->id,
            'role' => 'karyawan',
            'status' => 'active',
            'password' => 'Password123!',
            'password_confirmation' => 'Password123!',
        ], $overrides);
    }

    public function test_hrd_can_view_the_user_list(): void
    {
        $hrd = User::factory()->create(['role' => 'hrd']);
        [$company, $division] = $this->makeCompanyWithDivision();
        $other = User::factory()->create(['company_id' => $company->id, 'division_id' => $division->id]);

        $this->actingAs($hrd)
            ->get(route('hrd.users.index'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('hrd/user-management')
                ->has('users.data')
                ->has('companies')
            );

        $this->assertNotNull($other);
    }

    public function test_karyawan_cannot_access_user_management(): void
    {
        $karyawan = User::factory()->create(['role' => 'karyawan']);

        $this->actingAs($karyawan)
            ->get(route('hrd.users.index'))
            ->assertForbidden();
    }

    public function test_search_filters_by_name_email_or_employee_number(): void
    {
        // Explicit, non-random name/email on every user here — the id_ID
        // Faker locale can plausibly generate "Budi" on its own for an
        // unconstrained factory user, which would flakily match the
        // search term below and break the exact-count assertion.
        [$company, $division] = $this->makeCompanyWithDivision();
        $hrd = User::factory()->create(['role' => 'hrd', 'company_id' => $company->id, 'name' => 'Test Hrd Account', 'email' => 'test-hrd-account@example.com']);
        $match = User::factory()->create([
            'company_id' => $company->id, 'division_id' => $division->id,
            'name' => 'Budi Santoso', 'email' => 'budi-santoso@example.com', 'employee_number' => 'EMP-001',
        ]);
        $noMatch = User::factory()->create([
            'company_id' => $company->id, 'division_id' => $division->id,
            'name' => 'Rahmat Hidayat', 'email' => 'rahmat-hidayat@example.com', 'employee_number' => 'EMP-002',
        ]);

        $this->actingAs($hrd)
            ->get(route('hrd.users.index', ['search' => 'Budi']))
            ->assertInertia(fn ($page) => $page
                ->has('users.data', 1)
                ->where('users.data.0.id', $match->id)
            );

        $this->assertNotNull($noMatch);
    }

    public function test_hrd_can_create_a_user_with_an_explicit_role_and_status(): void
    {
        [$company, $division] = $this->makeCompanyWithDivision();
        $hrd = User::factory()->create(['role' => 'hrd', 'company_id' => $company->id]);

        $this->actingAs($hrd)
            ->post(route('hrd.users.store'), $this->payload($company, $division, [
                'role' => 'hrd', 'status' => 'active',
            ]))
            ->assertSessionDoesntHaveErrors()
            ->assertRedirect();

        $created = User::where('email', 'baru@example.com')->first();
        $this->assertNotNull($created);
        $this->assertSame('hrd', $created->role);
        $this->assertSame('active', $created->status);
        $this->assertTrue(Hash::check('Password123!', $created->password));
    }

    public function test_bank_details_can_be_set_on_creation_and_are_encrypted_at_rest(): void
    {
        [$company, $division] = $this->makeCompanyWithDivision();
        $hrd = User::factory()->create(['role' => 'hrd', 'company_id' => $company->id]);

        $this->actingAs($hrd)
            ->post(route('hrd.users.store'), $this->payload($company, $division, [
                'bank_name' => 'BCA',
                'bank_account_number' => '9876543210',
                'bank_account_holder' => 'Karyawan Baru',
            ]))
            ->assertSessionDoesntHaveErrors();

        $created = User::where('email', 'baru@example.com')->firstOrFail();
        $raw = DB::table('users')->where('id', $created->id)->value('bank_account_number');
        $this->assertNotSame('9876543210', $raw);
        $this->assertSame('9876543210', $created->bank_account_number);
        $this->assertSame('BCA', $created->bank_name);
    }

    public function test_leaving_bank_account_number_blank_on_edit_keeps_the_existing_value(): void
    {
        [$company, $division] = $this->makeCompanyWithDivision();
        $hrd = User::factory()->create(['role' => 'hrd', 'company_id' => $company->id]);
        $target = User::factory()->create([
            'company_id' => $company->id, 'division_id' => $division->id,
            'bank_name' => 'BCA', 'bank_account_number' => '1111111111', 'bank_account_holder' => 'Lama',
        ]);

        $this->actingAs($hrd)
            ->put(route('hrd.users.update', $target), [
                'name' => $target->name, 'email' => $target->email, 'phone' => $target->phone,
                'employee_number' => null, 'employment_started_on' => '2026-01-01', 'employment_type' => 'permanent', 'is_payroll_eligible' => true, 'company_id' => $company->id, 'division_id' => $division->id,
                'role' => $target->role, 'status' => $target->status,
                'bank_name' => 'Mandiri', 'bank_account_number' => '', 'bank_account_holder' => 'Baru',
                'password' => '', 'password_confirmation' => '',
            ])
            ->assertSessionDoesntHaveErrors();

        $fresh = $target->fresh();
        $this->assertSame('1111111111', $fresh->bank_account_number, 'Blank bank_account_number must not overwrite the existing one.');
        $this->assertSame('Mandiri', $fresh->bank_name);
        $this->assertSame('Baru', $fresh->bank_account_holder);
    }

    public function test_creating_a_user_rejects_a_division_that_does_not_belong_to_the_chosen_company(): void
    {
        [$company] = $this->makeCompanyWithDivision();
        $hrd = User::factory()->create(['role' => 'hrd', 'company_id' => $company->id]);
        $unrelatedDivision = Division::factory()->create();

        $this->actingAs($hrd)
            ->post(route('hrd.users.store'), $this->payload($company, $unrelatedDivision))
            ->assertSessionHasErrors('division_id');

        $this->assertSame(0, User::where('email', 'baru@example.com')->count());
    }

    public function test_hrd_can_edit_another_users_role_status_and_assignment(): void
    {
        [$company, $division] = $this->makeCompanyWithDivision();
        $otherDivision = Division::factory()->create(['company_id' => $company->id]);
        $hrd = User::factory()->create(['role' => 'hrd', 'company_id' => $company->id]);
        $target = User::factory()->create([
            'company_id' => $company->id, 'division_id' => $division->id,
            'role' => 'karyawan', 'status' => 'pending',
        ]);

        $this->actingAs($hrd)
            ->put(route('hrd.users.update', $target), [
                'name' => $target->name,
                'email' => $target->email,
                'phone' => $target->phone,
                'employee_number' => null, 'employment_started_on' => '2026-01-01', 'employment_type' => 'permanent', 'is_payroll_eligible' => true,
                'company_id' => $company->id,
                'division_id' => $otherDivision->id,
                'role' => 'hrd',
                'status' => 'active',
                'password' => '',
                'password_confirmation' => '',
            ])
            ->assertSessionDoesntHaveErrors();

        $fresh = $target->fresh();
        $this->assertSame('hrd', $fresh->role);
        $this->assertSame('active', $fresh->status);
        $this->assertSame($company->id, $fresh->company_id);
        $this->assertSame($otherDivision->id, $fresh->division_id);
    }

    public function test_hrd_can_move_a_user_into_another_company(): void
    {
        // HRD manages every instansi in the system (General Solusindo and
        // Tabinaco share this one HRD role) — deliberately not tenant
        // isolated, unlike every other HRD-scoped resource in the app.
        [$company, $division] = $this->makeCompanyWithDivision();
        [$otherCompany, $otherDivision] = $this->makeCompanyWithDivision();
        $hrd = User::factory()->create(['role' => 'hrd', 'company_id' => $company->id]);
        $target = User::factory()->create(['company_id' => $company->id, 'division_id' => $division->id]);

        $this->actingAs($hrd)
            ->put(route('hrd.users.update', $target), [
                'name' => $target->name,
                'email' => $target->email,
                'phone' => $target->phone,
                'employee_number' => null, 'employment_started_on' => '2026-01-01', 'employment_type' => 'permanent', 'is_payroll_eligible' => true,
                'company_id' => $otherCompany->id,
                'division_id' => $otherDivision->id,
                'role' => $target->role,
                'status' => $target->status,
                'password' => '',
                'password_confirmation' => '',
            ])
            ->assertSessionDoesntHaveErrors();

        $this->assertSame($otherCompany->id, $target->fresh()->company_id);
    }

    public function test_hrd_can_view_users_from_another_company(): void
    {
        [$company] = $this->makeCompanyWithDivision();
        $hrd = User::factory()->create(['role' => 'hrd', 'company_id' => $company->id]);
        $outsider = User::factory()->create(['name' => 'Outsider Employee']);

        $this->actingAs($hrd)
            ->get(route('hrd.users.index'))
            ->assertInertia(fn ($page) => $page
                ->where('users.data', fn ($data) => collect($data)->contains('id', $outsider->id))
            );
    }

    public function test_hrd_can_edit_a_user_from_another_company(): void
    {
        [$company, $division] = $this->makeCompanyWithDivision();
        $hrd = User::factory()->create(['role' => 'hrd', 'company_id' => $company->id]);
        $outsider = User::factory()->create();

        $this->actingAs($hrd)
            ->put(route('hrd.users.update', $outsider), [
                'name' => 'Diubah',
                'email' => $outsider->email,
                'phone' => $outsider->phone,
                'employee_number' => null, 'employment_started_on' => '2026-01-01', 'employment_type' => 'permanent', 'is_payroll_eligible' => true,
                'company_id' => $company->id,
                'division_id' => $division->id,
                'role' => 'admin',
                'status' => 'active',
                'password' => '',
                'password_confirmation' => '',
            ])
            ->assertSessionDoesntHaveErrors();

        $this->assertSame('Diubah', $outsider->fresh()->name);
        $this->assertSame('admin', $outsider->fresh()->role);
    }

    public function test_hrd_can_create_a_user_in_another_company(): void
    {
        [$company] = $this->makeCompanyWithDivision();
        $hrd = User::factory()->create(['role' => 'hrd', 'company_id' => $company->id]);
        [$otherCompany, $otherDivision] = $this->makeCompanyWithDivision();

        $this->actingAs($hrd)
            ->post(route('hrd.users.store'), $this->payload($otherCompany, $otherDivision))
            ->assertSessionDoesntHaveErrors();

        $this->assertSame(1, User::where('email', 'baru@example.com')->where('company_id', $otherCompany->id)->count());
    }

    public function test_leaving_password_blank_on_edit_keeps_the_existing_password(): void
    {
        [$company, $division] = $this->makeCompanyWithDivision();
        $hrd = User::factory()->create(['role' => 'hrd', 'company_id' => $company->id]);
        $target = User::factory()->create([
            'company_id' => $company->id, 'division_id' => $division->id,
            'password' => 'original-password',
        ]);
        $originalHash = $target->password;

        $this->actingAs($hrd)
            ->put(route('hrd.users.update', $target), [
                'name' => $target->name,
                'email' => $target->email,
                'phone' => $target->phone,
                'employee_number' => null, 'employment_started_on' => '2026-01-01', 'employment_type' => 'permanent', 'is_payroll_eligible' => true,
                'company_id' => $company->id,
                'division_id' => $division->id,
                'role' => $target->role,
                'status' => $target->status,
                'password' => '',
                'password_confirmation' => '',
            ])
            ->assertSessionDoesntHaveErrors();

        $this->assertSame($originalHash, $target->fresh()->password);
    }

    public function test_an_hrd_cannot_change_their_own_role_or_status_through_this_screen(): void
    {
        [$company, $division] = $this->makeCompanyWithDivision();
        $hrd = User::factory()->create([
            'role' => 'hrd', 'status' => 'active',
            'company_id' => $company->id, 'division_id' => $division->id,
        ]);

        $this->actingAs($hrd)
            ->put(route('hrd.users.update', $hrd), [
                'name' => $hrd->name,
                'email' => $hrd->email,
                'phone' => $hrd->phone,
                'employee_number' => null, 'employment_started_on' => '2026-01-01', 'employment_type' => 'permanent', 'is_payroll_eligible' => true,
                'company_id' => $company->id,
                'division_id' => $division->id,
                'role' => 'karyawan',
                'status' => 'rejected',
                'password' => '',
                'password_confirmation' => '',
            ])
            ->assertSessionDoesntHaveErrors();

        $fresh = $hrd->fresh();
        $this->assertSame('hrd', $fresh->role, 'Role must not change on a self-edit, regardless of what was submitted.');
        $this->assertSame('active', $fresh->status, 'Status must not change on a self-edit, regardless of what was submitted.');
    }

    public function test_a_new_user_is_payroll_eligible_by_default(): void
    {
        [$company, $division] = $this->makeCompanyWithDivision();
        $hrd = User::factory()->create(['role' => 'hrd', 'company_id' => $company->id]);

        $this->actingAs($hrd)
            ->post(route('hrd.users.store'), $this->payload($company, $division))
            ->assertSessionDoesntHaveErrors();

        $created = User::where('email', 'baru@example.com')->firstOrFail();
        $this->assertTrue($created->is_payroll_eligible);
    }

    public function test_hrd_can_create_a_user_marked_as_not_payroll_eligible(): void
    {
        [$company, $division] = $this->makeCompanyWithDivision();
        $hrd = User::factory()->create(['role' => 'hrd', 'company_id' => $company->id]);

        $this->actingAs($hrd)
            ->post(route('hrd.users.store'), $this->payload($company, $division, [
                'is_payroll_eligible' => false,
            ]))
            ->assertSessionDoesntHaveErrors();

        $created = User::where('email', 'baru@example.com')->firstOrFail();
        $this->assertFalse($created->is_payroll_eligible);
    }

    public function test_hrd_can_toggle_an_existing_users_payroll_eligibility(): void
    {
        [$company, $division] = $this->makeCompanyWithDivision();
        $hrd = User::factory()->create(['role' => 'hrd', 'company_id' => $company->id]);
        $target = User::factory()->create([
            'company_id' => $company->id, 'division_id' => $division->id,
            'is_payroll_eligible' => true,
        ]);

        $this->actingAs($hrd)
            ->put(route('hrd.users.update', $target), [
                'name' => $target->name,
                'email' => $target->email,
                'phone' => $target->phone,
                'employee_number' => null, 'employment_started_on' => '2026-01-01', 'employment_type' => 'permanent',
                'is_payroll_eligible' => false,
                'company_id' => $company->id,
                'division_id' => $division->id,
                'role' => $target->role,
                'status' => $target->status,
                'password' => '',
                'password_confirmation' => '',
            ])
            ->assertSessionDoesntHaveErrors();

        $this->assertFalse($target->fresh()->is_payroll_eligible);
    }

    public function test_hrd_can_delete_another_user(): void
    {
        [$company] = $this->makeCompanyWithDivision();
        $hrd = User::factory()->create(['role' => 'hrd', 'company_id' => $company->id]);
        $target = User::factory()->create(['company_id' => $company->id]);

        $this->actingAs($hrd)
            ->delete(route('hrd.users.destroy', $target))
            ->assertRedirect();

        $this->assertModelMissing($target);
    }

    public function test_hrd_can_delete_a_user_from_another_company(): void
    {
        [$company] = $this->makeCompanyWithDivision();
        $hrd = User::factory()->create(['role' => 'hrd', 'company_id' => $company->id]);
        $outsider = User::factory()->create();

        $this->actingAs($hrd)
            ->delete(route('hrd.users.destroy', $outsider))
            ->assertRedirect();

        $this->assertModelMissing($outsider);
    }

    public function test_an_hrd_cannot_delete_their_own_account(): void
    {
        $hrd = User::factory()->create(['role' => 'hrd']);

        $this->actingAs($hrd)
            ->delete(route('hrd.users.destroy', $hrd))
            ->assertForbidden();

        $this->assertModelExists($hrd);
    }
}
