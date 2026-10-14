<?php

namespace Tests\Feature\Hrd;

use App\Models\Company;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CompanySettingsTest extends TestCase
{
    use RefreshDatabase;

    public function test_hrd_sees_their_own_companys_settings(): void
    {
        $hrd = User::factory()->create(['role' => 'hrd']);
        Company::whereKey($hrd->company_id)->update(['telegram_chat_id' => '-100111222']);

        $this->actingAs($hrd)
            ->get(route('hrd.pengaturan-perusahaan.index'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('hrd/company-settings')
                ->where('company.id', $hrd->company_id)
                ->where('company.telegram_chat_id', '-100111222')
            );
    }

    public function test_hrd_can_set_the_telegram_chat_id(): void
    {
        $hrd = User::factory()->create(['role' => 'hrd']);

        $this->actingAs($hrd)
            ->put(route('hrd.pengaturan-perusahaan.update'), [
                'telegram_chat_id' => '-100333444',
            ])
            ->assertSessionDoesntHaveErrors()
            ->assertRedirect();

        $this->assertSame('-100333444', Company::find($hrd->company_id)->telegram_chat_id);
    }

    public function test_hrd_can_clear_the_telegram_chat_id(): void
    {
        $hrd = User::factory()->create(['role' => 'hrd']);
        Company::whereKey($hrd->company_id)->update(['telegram_chat_id' => '-100333444']);

        $this->actingAs($hrd)
            ->put(route('hrd.pengaturan-perusahaan.update'), [
                'telegram_chat_id' => null,
            ])
            ->assertSessionDoesntHaveErrors();

        $this->assertNull(Company::find($hrd->company_id)->telegram_chat_id);
    }

    public function test_updating_never_touches_another_companys_settings(): void
    {
        $hrd = User::factory()->create(['role' => 'hrd']);
        $otherCompany = Company::factory()->create(['telegram_chat_id' => '-999']);

        $this->actingAs($hrd)->put(route('hrd.pengaturan-perusahaan.update'), [
            'telegram_chat_id' => '-100555666',
        ]);

        $this->assertSame('-999', $otherCompany->fresh()->telegram_chat_id);
    }

    public function test_karyawan_cannot_view_or_edit_company_settings(): void
    {
        $karyawan = User::factory()->create(['role' => 'karyawan']);

        $this->actingAs($karyawan)
            ->get(route('hrd.pengaturan-perusahaan.index'))
            ->assertForbidden();

        $this->actingAs($karyawan)
            ->put(route('hrd.pengaturan-perusahaan.update'), ['telegram_chat_id' => '-1'])
            ->assertForbidden();
    }

    public function test_manager_cannot_view_or_edit_company_settings(): void
    {
        $manager = User::factory()->create(['role' => 'admin']);

        $this->actingAs($manager)
            ->get(route('hrd.pengaturan-perusahaan.index'))
            ->assertForbidden();
    }
}
