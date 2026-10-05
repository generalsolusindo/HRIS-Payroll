<?php

namespace Tests\Feature\Hrd;

use App\Models\CompanyCalendarDay;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CompanyCalendarDayManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_hrd_can_list_and_create_calendar_days(): void
    {
        $hrd = User::factory()->create(['role' => 'hrd']);

        $this->actingAs($hrd)
            ->get(route('hrd.kalender-kerja.index'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page->component('hrd/calendar-days')->has('days', 0));

        $this->actingAs($hrd)
            ->post(route('hrd.kalender-kerja.store'), [
                'company_id' => $hrd->company_id,
                'date' => '2026-08-17',
                'type' => 'holiday',
                'description' => 'Hari Kemerdekaan',
            ])
            ->assertSessionDoesntHaveErrors()
            ->assertRedirect();

        $this->assertDatabaseHas('company_calendar_days', [
            'company_id' => $hrd->company_id,
            'date' => '2026-08-17',
            'type' => 'holiday',
            'description' => 'Hari Kemerdekaan',
        ]);
    }

    public function test_the_same_date_cannot_be_added_twice_for_one_company(): void
    {
        $hrd = User::factory()->create(['role' => 'hrd']);
        CompanyCalendarDay::create([
            'company_id' => $hrd->company_id, 'date' => '2026-08-17', 'type' => 'holiday',
        ]);

        $this->actingAs($hrd)
            ->post(route('hrd.kalender-kerja.store'), [
                'company_id' => $hrd->company_id,
                'date' => '2026-08-17',
                'type' => 'holiday',
            ])
            ->assertSessionHasErrors('date');
    }

    public function test_hrd_can_delete_a_calendar_day(): void
    {
        $hrd = User::factory()->create(['role' => 'hrd']);
        $day = CompanyCalendarDay::create([
            'company_id' => $hrd->company_id, 'date' => '2026-08-17', 'type' => 'holiday',
        ]);

        $this->actingAs($hrd)
            ->delete(route('hrd.kalender-kerja.destroy', $day))
            ->assertRedirect();

        $this->assertDatabaseMissing('company_calendar_days', ['id' => $day->id]);
    }

    public function test_karyawan_cannot_manage_calendar_days(): void
    {
        $karyawan = User::factory()->create(['role' => 'karyawan']);

        $this->actingAs($karyawan)
            ->get(route('hrd.kalender-kerja.index'))
            ->assertForbidden();

        $this->actingAs($karyawan)
            ->post(route('hrd.kalender-kerja.store'), [
                'company_id' => $karyawan->company_id,
                'date' => '2026-08-17',
                'type' => 'holiday',
            ])
            ->assertForbidden();
    }
}
