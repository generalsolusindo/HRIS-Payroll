<?php

namespace Tests\Feature\Middleware;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class EnsureUserHasRoleTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Route::middleware(['web', 'auth', 'role:hrd'])
            ->get('/_test/hrd-only', fn () => 'ok');
    }

    public function test_user_with_matching_role_can_access_the_route()
    {
        $hrd = User::factory()->create(['role' => 'hrd']);

        $this->actingAs($hrd)->get('/_test/hrd-only')->assertOk();
    }

    public function test_user_without_matching_role_is_forbidden()
    {
        $karyawan = User::factory()->create(['role' => 'karyawan']);

        $this->actingAs($karyawan)->get('/_test/hrd-only')->assertForbidden();
    }

    public function test_guest_is_redirected_to_login()
    {
        $this->get('/_test/hrd-only')->assertRedirect(route('login'));
    }
}
