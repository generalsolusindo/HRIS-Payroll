<?php

namespace Tests\Feature\Auth;

use App\Models\Company;
use App\Models\Position;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\RateLimiter;
use Laravel\Fortify\Features;
use Tests\TestCase;

class RegistrationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->skipUnlessFortifyHas(Features::registration());
    }

    public function test_registration_screen_can_be_rendered()
    {
        $response = $this->get(route('register'));

        $response->assertOk();
    }

    public function test_new_users_register_with_pending_status_and_cannot_reach_dashboard()
    {
        $company = Company::create(['name' => 'General Solusindo', 'code' => 'GS']);
        $position = Position::create(['name' => 'Sales']);
        $company->positions()->attach($position);

        $response = $this->post(route('register.store'), [
            'name' => 'Test User',
            'email' => 'test@example.com',
            'phone' => '081234567890',
            'company_id' => $company->id,
            'position_id' => $position->id,
            'password' => 'password',
            'password_confirmation' => 'password',
        ]);

        $this->assertAuthenticated();
        $response->assertRedirect(route('dashboard', absolute: false));

        $this->assertDatabaseHas('users', [
            'email' => 'test@example.com',
            'status' => 'pending',
            'role' => 'karyawan',
        ]);

        $this->get(route('dashboard'))->assertRedirect(route('pending-approval', absolute: false));
    }

    public function test_registration_rejects_position_not_available_for_company()
    {
        $company = Company::create(['name' => 'Tabinaco', 'code' => 'TBC']);
        $otherCompanysPosition = Position::create(['name' => 'Teknisi']);

        $response = $this->post(route('register.store'), [
            'name' => 'Test User',
            'email' => 'test@example.com',
            'phone' => '081234567890',
            'company_id' => $company->id,
            'position_id' => $otherCompanysPosition->id,
            'password' => 'password',
            'password_confirmation' => 'password',
        ]);

        $response->assertSessionHasErrors('position_id');
        $this->assertGuest();
    }

    public function test_registration_is_rate_limited_per_ip()
    {
        $company = Company::create(['name' => 'General Solusindo', 'code' => 'GS']);
        $position = Position::create(['name' => 'Sales']);
        $company->positions()->attach($position);

        RateLimiter::clear('register:127.0.0.1');

        for ($i = 0; $i < 10; $i++) {
            $this->post(route('register.store'), [
                'name' => 'Test User',
                'email' => "test{$i}@example.com",
                'phone' => '081234567890',
                'company_id' => $company->id,
                'position_id' => $position->id,
                'password' => 'password',
                'password_confirmation' => 'password',
            ]);

            $this->app['auth']->guard()->logout();
        }

        $response = $this->post(route('register.store'), [
            'name' => 'Test User',
            'email' => 'test11@example.com',
            'phone' => '081234567890',
            'company_id' => $company->id,
            'position_id' => $position->id,
            'password' => 'password',
            'password_confirmation' => 'password',
        ]);

        $response->assertSessionHasErrors('email');
        $this->assertGuest();
        $this->assertDatabaseMissing('users', ['email' => 'test11@example.com']);
    }
}
