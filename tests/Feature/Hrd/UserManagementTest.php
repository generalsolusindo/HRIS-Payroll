<?php

namespace Tests\Feature\Hrd;

use App\Models\Company;
use App\Models\Position;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class UserManagementTest extends TestCase
{
    use RefreshDatabase;

    private function makeCompanyWithPosition(): array
    {
        $company = Company::factory()->create();
        $position = Position::factory()->create();
        $company->positions()->attach($position);

        return [$company, $position];
    }

    private function payload(Company $company, Position $position, array $overrides = []): array
    {
        return array_merge([
            'name' => 'Karyawan Baru',
            'email' => 'baru@example.com',
            'phone' => '08123456789',
            'employee_number' => null,
            'company_id' => $company->id,
            'position_id' => $position->id,
            'role' => 'karyawan',
            'status' => 'active',
            'password' => 'Password123!',
            'password_confirmation' => 'Password123!',
        ], $overrides);
    }

    public function test_hrd_can_view_the_user_list(): void
    {
        $hrd = User::factory()->create(['role' => 'hrd']);
        [$company, $position] = $this->makeCompanyWithPosition();
        $other = User::factory()->create(['company_id' => $company->id, 'position_id' => $position->id]);

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
        $hrd = User::factory()->create(['role' => 'hrd']);
        [$company, $position] = $this->makeCompanyWithPosition();
        $match = User::factory()->create([
            'company_id' => $company->id, 'position_id' => $position->id,
            'name' => 'Budi Santoso', 'employee_number' => 'EMP-001',
        ]);
        $noMatch = User::factory()->create([
            'company_id' => $company->id, 'position_id' => $position->id,
            'name' => 'Siti Aminah', 'employee_number' => 'EMP-002',
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
        $hrd = User::factory()->create(['role' => 'hrd']);
        [$company, $position] = $this->makeCompanyWithPosition();

        $this->actingAs($hrd)
            ->post(route('hrd.users.store'), $this->payload($company, $position, [
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

    public function test_creating_a_user_rejects_a_position_that_does_not_belong_to_the_chosen_company(): void
    {
        $hrd = User::factory()->create(['role' => 'hrd']);
        [$company] = $this->makeCompanyWithPosition();
        $unrelatedPosition = Position::factory()->create();

        $this->actingAs($hrd)
            ->post(route('hrd.users.store'), $this->payload($company, $unrelatedPosition))
            ->assertSessionHasErrors('position_id');

        $this->assertSame(0, User::where('email', 'baru@example.com')->count());
    }

    public function test_hrd_can_edit_another_users_role_status_and_assignment(): void
    {
        $hrd = User::factory()->create(['role' => 'hrd']);
        [$company, $position] = $this->makeCompanyWithPosition();
        [$newCompany, $newPosition] = $this->makeCompanyWithPosition();
        $target = User::factory()->create([
            'company_id' => $company->id, 'position_id' => $position->id,
            'role' => 'karyawan', 'status' => 'pending',
        ]);

        $this->actingAs($hrd)
            ->put(route('hrd.users.update', $target), [
                'name' => $target->name,
                'email' => $target->email,
                'phone' => $target->phone,
                'employee_number' => null,
                'company_id' => $newCompany->id,
                'position_id' => $newPosition->id,
                'role' => 'hrd',
                'status' => 'active',
                'password' => '',
                'password_confirmation' => '',
            ])
            ->assertSessionDoesntHaveErrors();

        $fresh = $target->fresh();
        $this->assertSame('hrd', $fresh->role);
        $this->assertSame('active', $fresh->status);
        $this->assertSame($newCompany->id, $fresh->company_id);
        $this->assertSame($newPosition->id, $fresh->position_id);
    }

    public function test_leaving_password_blank_on_edit_keeps_the_existing_password(): void
    {
        $hrd = User::factory()->create(['role' => 'hrd']);
        [$company, $position] = $this->makeCompanyWithPosition();
        $target = User::factory()->create([
            'company_id' => $company->id, 'position_id' => $position->id,
            'password' => 'original-password',
        ]);
        $originalHash = $target->password;

        $this->actingAs($hrd)
            ->put(route('hrd.users.update', $target), [
                'name' => $target->name,
                'email' => $target->email,
                'phone' => $target->phone,
                'employee_number' => null,
                'company_id' => $company->id,
                'position_id' => $position->id,
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
        [$company, $position] = $this->makeCompanyWithPosition();
        $hrd = User::factory()->create([
            'role' => 'hrd', 'status' => 'active',
            'company_id' => $company->id, 'position_id' => $position->id,
        ]);

        $this->actingAs($hrd)
            ->put(route('hrd.users.update', $hrd), [
                'name' => $hrd->name,
                'email' => $hrd->email,
                'phone' => $hrd->phone,
                'employee_number' => null,
                'company_id' => $company->id,
                'position_id' => $position->id,
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

    public function test_hrd_can_delete_another_user(): void
    {
        $hrd = User::factory()->create(['role' => 'hrd']);
        $target = User::factory()->create();

        $this->actingAs($hrd)
            ->delete(route('hrd.users.destroy', $target))
            ->assertRedirect();

        $this->assertModelMissing($target);
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
