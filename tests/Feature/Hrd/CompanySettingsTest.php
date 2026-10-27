<?php

namespace Tests\Feature\Hrd;

use App\Models\Company;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class CompanySettingsTest extends TestCase
{
    use RefreshDatabase;

    public function test_hrd_sees_settings_for_every_company(): void
    {
        $hrd = User::factory()->create(['role' => 'hrd']);
        Company::whereKey($hrd->company_id)->update(['telegram_chat_id' => '-100111222']);
        $otherCompany = Company::factory()->create(['telegram_chat_id' => '-100999888']);

        $this->actingAs($hrd)
            ->get(route('hrd.pengaturan-perusahaan.index'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('hrd/company-settings')
                ->where('companies', fn ($companies) => collect($companies)->contains(
                    fn ($company) => $company['id'] === $hrd->company_id && $company['telegram_chat_id'] === '-100111222'
                ) && collect($companies)->contains(
                    fn ($company) => $company['id'] === $otherCompany->id && $company['telegram_chat_id'] === '-100999888'
                ))
            );
    }

    public function test_hrd_can_set_the_telegram_chat_id_for_their_own_company(): void
    {
        $hrd = User::factory()->create(['role' => 'hrd']);

        $this->actingAs($hrd)
            ->put(route('hrd.pengaturan-perusahaan.update', $hrd->company_id), [
                'telegram_chat_id' => '-100333444',
            ])
            ->assertSessionDoesntHaveErrors()
            ->assertRedirect();

        $this->assertSame('-100333444', Company::find($hrd->company_id)->telegram_chat_id);
    }

    public function test_hrd_can_set_the_telegram_chat_id_for_another_company(): void
    {
        // HRD manages every instansi in the system (General Solusindo and
        // Tabinaco share this one HRD role) — deliberately not tenant
        // isolated here, unlike most other HRD-scoped resources.
        $hrd = User::factory()->create(['role' => 'hrd']);
        $otherCompany = Company::factory()->create();

        $this->actingAs($hrd)
            ->put(route('hrd.pengaturan-perusahaan.update', $otherCompany), [
                'telegram_chat_id' => '-100777666',
            ])
            ->assertSessionDoesntHaveErrors();

        $this->assertSame('-100777666', $otherCompany->fresh()->telegram_chat_id);
    }

    public function test_hrd_can_clear_the_telegram_chat_id(): void
    {
        $hrd = User::factory()->create(['role' => 'hrd']);
        Company::whereKey($hrd->company_id)->update(['telegram_chat_id' => '-100333444']);

        $this->actingAs($hrd)
            ->put(route('hrd.pengaturan-perusahaan.update', $hrd->company_id), [
                'telegram_chat_id' => null,
            ])
            ->assertSessionDoesntHaveErrors();

        $this->assertNull(Company::find($hrd->company_id)->telegram_chat_id);
    }

    public function test_hrd_can_set_the_payslip_letterhead_fields(): void
    {
        $hrd = User::factory()->create(['role' => 'hrd']);

        $this->actingAs($hrd)
            ->put(route('hrd.pengaturan-perusahaan.update', $hrd->company_id), [
                'legal_name' => 'CV General Solusindo',
                'code' => 'GS',
                'address' => 'Pondok Jati Blok AS-31, Pagerwojo, Buduran, Sidoarjo',
                'city' => 'Sidoarjo',
                'director_name' => 'M Syarif Ridho P, S.Kom',
            ])
            ->assertSessionDoesntHaveErrors();

        $company = Company::find($hrd->company_id);
        $this->assertSame('CV General Solusindo', $company->legal_name);
        $this->assertSame('GS', $company->code);
        $this->assertSame('Pondok Jati Blok AS-31, Pagerwojo, Buduran, Sidoarjo', $company->address);
        $this->assertSame('Sidoarjo', $company->city);
        $this->assertSame('M Syarif Ridho P, S.Kom', $company->director_name);
    }

    public function test_hrd_can_upload_a_director_signature(): void
    {
        Storage::fake('company_signatures');

        $hrd = User::factory()->create(['role' => 'hrd']);
        $file = UploadedFile::fake()->image('ttd.png', 300, 100);

        $this->actingAs($hrd)
            ->put(route('hrd.pengaturan-perusahaan.update', $hrd->company_id), [
                'director_signature' => $file,
            ])
            ->assertSessionDoesntHaveErrors();

        $company = Company::find($hrd->company_id);
        $this->assertNotNull($company->director_signature_path);
        Storage::disk('company_signatures')->assertExists($company->director_signature_path);
    }

    public function test_uploading_a_new_signature_replaces_the_old_one(): void
    {
        Storage::fake('company_signatures');

        $hrd = User::factory()->create(['role' => 'hrd']);

        $this->actingAs($hrd)->put(route('hrd.pengaturan-perusahaan.update', $hrd->company_id), [
            'director_signature' => UploadedFile::fake()->image('ttd-lama.png'),
        ]);
        $oldPath = Company::find($hrd->company_id)->director_signature_path;

        $this->actingAs($hrd)->put(route('hrd.pengaturan-perusahaan.update', $hrd->company_id), [
            'director_signature' => UploadedFile::fake()->image('ttd-baru.png'),
        ]);
        $newPath = Company::find($hrd->company_id)->director_signature_path;

        $this->assertNotSame($oldPath, $newPath);
        Storage::disk('company_signatures')->assertMissing($oldPath);
        Storage::disk('company_signatures')->assertExists($newPath);
    }

    public function test_karyawan_cannot_view_or_edit_company_settings(): void
    {
        $karyawan = User::factory()->create(['role' => 'karyawan']);

        $this->actingAs($karyawan)
            ->get(route('hrd.pengaturan-perusahaan.index'))
            ->assertForbidden();

        $this->actingAs($karyawan)
            ->put(route('hrd.pengaturan-perusahaan.update', $karyawan->company_id), ['telegram_chat_id' => '-1'])
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
