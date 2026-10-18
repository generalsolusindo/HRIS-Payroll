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

    public function test_re_adding_the_same_date_updates_it_in_place_instead_of_erroring(): void
    {
        $hrd = User::factory()->create(['role' => 'hrd']);
        $day = CompanyCalendarDay::create([
            'company_id' => $hrd->company_id, 'date' => '2026-08-17', 'type' => 'holiday', 'description' => 'Lama',
        ]);

        $this->actingAs($hrd)
            ->post(route('hrd.kalender-kerja.store'), [
                'company_id' => $hrd->company_id,
                'date' => '2026-08-17',
                'type' => 'holiday',
                'description' => 'Baru',
            ])
            ->assertSessionDoesntHaveErrors();

        $this->assertSame(1, CompanyCalendarDay::where('company_id', $hrd->company_id)->where('date', '2026-08-17')->count());
        $this->assertSame('Baru', $day->fresh()->description);
    }

    public function test_a_date_range_creates_one_row_per_day(): void
    {
        $hrd = User::factory()->create(['role' => 'hrd']);

        $this->actingAs($hrd)
            ->post(route('hrd.kalender-kerja.store'), [
                'company_id' => $hrd->company_id,
                'date' => '2026-03-31',
                'ends_on' => '2026-04-02',
                'type' => 'holiday',
                'description' => 'Idul Fitri',
            ])
            ->assertSessionDoesntHaveErrors();

        $this->assertSame(
            ['2026-03-31', '2026-04-01', '2026-04-02'],
            CompanyCalendarDay::where('company_id', $hrd->company_id)->orderBy('date')->pluck('date')
                ->map(fn ($date) => $date->toDateString())->all(),
        );
    }

    public function test_a_range_longer_than_60_days_is_rejected(): void
    {
        $hrd = User::factory()->create(['role' => 'hrd']);

        $this->actingAs($hrd)
            ->post(route('hrd.kalender-kerja.store'), [
                'company_id' => $hrd->company_id,
                'date' => '2026-01-01',
                'ends_on' => '2026-04-01',
                'type' => 'holiday',
            ])
            ->assertSessionHasErrors('ends_on');
    }

    public function test_a_workday_override_can_be_restricted_to_specific_employees(): void
    {
        $hrd = User::factory()->create(['role' => 'hrd']);
        $allowed = User::factory()->create(['company_id' => $hrd->company_id, 'role' => 'karyawan']);
        User::factory()->create(['company_id' => $hrd->company_id, 'role' => 'karyawan']);

        $this->actingAs($hrd)
            ->post(route('hrd.kalender-kerja.store'), [
                'company_id' => $hrd->company_id,
                'date' => '2026-08-16',
                'type' => 'workday',
                'allowed_user_ids' => [$allowed->id],
            ])
            ->assertSessionDoesntHaveErrors();

        $this->assertDatabaseHas('company_calendar_days', [
            'company_id' => $hrd->company_id, 'date' => '2026-08-16', 'type' => 'workday',
        ]);
        $day = CompanyCalendarDay::where('company_id', $hrd->company_id)->where('date', '2026-08-16')->firstOrFail();
        $this->assertSame([$allowed->id], $day->allowed_user_ids);
    }

    public function test_a_holiday_can_exempt_specific_employees_who_still_need_to_work(): void
    {
        $hrd = User::factory()->create(['role' => 'hrd']);
        $employee = User::factory()->create(['company_id' => $hrd->company_id, 'role' => 'karyawan']);

        $this->actingAs($hrd)
            ->post(route('hrd.kalender-kerja.store'), [
                'company_id' => $hrd->company_id,
                'date' => '2026-08-17',
                'type' => 'holiday',
                'allowed_user_ids' => [$employee->id],
            ])
            ->assertSessionDoesntHaveErrors();

        $day = CompanyCalendarDay::where('company_id', $hrd->company_id)->where('date', '2026-08-17')->firstOrFail();
        $this->assertSame([$employee->id], $day->allowed_user_ids);
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

    public function test_hrd_can_edit_a_calendar_day(): void
    {
        $hrd = User::factory()->create(['role' => 'hrd']);
        $employee = User::factory()->create(['company_id' => $hrd->company_id, 'role' => 'karyawan']);
        $day = CompanyCalendarDay::create([
            'company_id' => $hrd->company_id, 'date' => '2026-08-17', 'type' => 'holiday', 'description' => 'Lama',
        ]);

        $this->actingAs($hrd)
            ->patch(route('hrd.kalender-kerja.update', $day), [
                'date' => '2026-08-18',
                'type' => 'holiday',
                'allowed_user_ids' => [$employee->id],
                'description' => 'Baru',
            ])
            ->assertSessionDoesntHaveErrors();

        $fresh = $day->fresh();
        $this->assertSame('2026-08-18', $fresh->date->toDateString());
        $this->assertSame('Baru', $fresh->description);
        $this->assertSame([$employee->id], $fresh->allowed_user_ids);
    }

    public function test_editing_a_calendar_day_to_a_date_already_taken_is_rejected(): void
    {
        $hrd = User::factory()->create(['role' => 'hrd']);
        CompanyCalendarDay::create(['company_id' => $hrd->company_id, 'date' => '2026-08-17', 'type' => 'holiday']);
        $day = CompanyCalendarDay::create(['company_id' => $hrd->company_id, 'date' => '2026-08-18', 'type' => 'holiday']);

        $this->actingAs($hrd)
            ->patch(route('hrd.kalender-kerja.update', $day), [
                'date' => '2026-08-17',
                'type' => 'holiday',
            ])
            ->assertSessionHasErrors('date');
    }

    public function test_hrd_cannot_edit_another_companys_calendar_day(): void
    {
        $hrd = User::factory()->create(['role' => 'hrd']);
        $otherHrd = User::factory()->create(['role' => 'hrd']);
        $day = CompanyCalendarDay::create(['company_id' => $otherHrd->company_id, 'date' => '2026-08-17', 'type' => 'holiday']);

        $this->actingAs($hrd)
            ->patch(route('hrd.kalender-kerja.update', $day), [
                'date' => '2026-08-17',
                'type' => 'holiday',
            ])
            ->assertForbidden();
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
