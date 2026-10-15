<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\Division;
use App\Models\Position;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DivisionBackfillMigrationTest extends TestCase
{
    use RefreshDatabase;

    private function runMigration(): void
    {
        $migration = require database_path('migrations/2026_10_15_130040_add_division_id_to_users_and_backfill_from_positions.php');
        $migration->down();
        $migration->up();
    }

    public function test_reuses_an_existing_division_with_the_same_name_instead_of_duplicating(): void
    {
        $company = Company::factory()->create();
        $position = Position::factory()->create(['name' => 'Finance']);
        $company->positions()->attach($position);
        $division = Division::create(['company_id' => $company->id, 'name' => 'Finance']);
        $user = User::factory()->create(['company_id' => $company->id, 'position_id' => $position->id]);

        $this->runMigration();

        $this->assertSame($division->id, $user->fresh()->division_id);
        $this->assertSame(1, Division::where('company_id', $company->id)->where('name', 'Finance')->count());
    }

    public function test_creates_a_division_for_a_position_without_an_existing_match(): void
    {
        $company = Company::factory()->create();
        $position = Position::factory()->create(['name' => 'Operasional']);
        $company->positions()->attach($position);
        $user = User::factory()->create(['company_id' => $company->id, 'position_id' => $position->id]);

        $this->runMigration();

        $fresh = $user->fresh();
        $this->assertNotNull($fresh->division_id);
        $division = Division::find($fresh->division_id);
        $this->assertSame('Operasional', $division->name);
        $this->assertSame($company->id, $division->company_id);
    }

    public function test_creates_a_separate_division_for_each_distinctly_named_position(): void
    {
        $company = Company::factory()->create();
        $first = Position::factory()->create(['name' => 'IT']);
        $second = Position::factory()->create(['name' => 'I.T.']);
        $company->positions()->attach([$first->id, $second->id]);

        $this->runMigration();

        $names = Division::where('company_id', $company->id)->pluck('name')->sort()->values();
        $this->assertSame(['I.T.', 'IT'], $names->all());
    }

    public function test_user_keeps_a_null_division_id_when_their_position_is_not_attached_to_their_company(): void
    {
        $company = Company::factory()->create();
        // Deliberately not attached to $company — simulates a data
        // inconsistency rather than the normal seeded setup.
        $position = Position::factory()->create();
        $user = User::factory()->create(['company_id' => $company->id, 'position_id' => $position->id]);

        $this->runMigration();

        $this->assertNull($user->fresh()->division_id);
    }
}
