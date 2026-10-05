<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Laravel\Fortify\Contracts\TwoFactorLoginResponse;
use Laravel\Passkeys\Contracts\PasskeyLoginResponse;
use Tests\TestCase;

class FaceEnrollmentGateTest extends TestCase
{
    use RefreshDatabase;

    public function test_dashboard_requires_enrollment_but_confirmation_and_logout_remain_available(): void
    {
        $this->actingAs(User::factory()->create());
        $this->get(route('dashboard'))->assertRedirect(route('face.edit'));
        $this->get(route('face.edit'))->assertRedirect(route('password.confirm'));
        $this->get(route('password.confirm'))->assertOk();
        $this->withSession(['auth.password_confirmed_at' => time()])->get(route('face.edit'))->assertOk();
        $this->post(route('logout'))->assertRedirect(route('home'));
        $this->assertGuest();
    }

    public function test_login_overrides_intended_url_until_enrollment_is_complete(): void
    {
        $user = User::factory()->create();
        $this->withSession(['url.intended' => route('izin')])->post(route('login.store'), [
            'email' => $user->email, 'password' => 'password',
        ])->assertRedirect(route('face.edit'))->assertSessionMissing('url.intended');
    }

    public function test_enrolled_login_preserves_intended_destination(): void
    {
        $user = User::factory()->withEnrolledFace()->create();
        $this->withSession(['url.intended' => route('izin')])->post(route('login.store'), [
            'email' => $user->email, 'password' => 'password',
        ])->assertRedirect(route('izin'));
    }

    public function test_guest_json_is_unauthenticated_and_unenrolled_json_is_forbidden_for_both_actions(): void
    {
        $this->postJson(route('absen.masuk'))->assertUnauthorized();
        $this->actingAs(User::factory()->create());
        foreach (['absen.masuk', 'absen.pulang'] as $route) {
            $this->postJson(route($route))->assertForbidden()->assertJsonPath('code', 'enrollment_required');
            $this->post(route($route))->assertRedirect(route('face.edit'));
        }
        $this->assertDatabaseCount('attendances', 0);
    }

    public function test_nonactive_users_cannot_enroll_or_attend(): void
    {
        foreach (['pending', 'rejected'] as $status) {
            $this->actingAs(User::factory()->create(['status' => $status]));
            $this->get(route('dashboard'))->assertRedirect(route('pending-approval'));
            $this->get(route('face.edit'))->assertRedirect(route('pending-approval'));
            $this->postJson(route('face.store'))->assertForbidden()->assertJsonPath('code', 'account_inactive');
            $this->postJson(route('absen.masuk'))->assertForbidden()->assertJsonPath('code', 'account_inactive');
        }
    }

    public function test_hrd_has_no_attendance_bypass_but_can_still_manage_employees(): void
    {
        $this->actingAs(User::factory()->create(['role' => 'hrd']));
        $this->get(route('hrd.users.index'))->assertOk();
        $this->postJson(route('absen.masuk'))->assertForbidden();
    }

    public function test_invalid_descriptors_and_bad_ciphertext_do_not_pass_enrollment_guard(): void
    {
        foreach ([[], [0.2], array_fill(0, 128, 'not-a-number')] as $descriptor) {
            $user = User::factory()->create(['face_descriptor' => $descriptor]);
            $this->assertFalse($user->hasFaceEnrolled());
            $this->actingAs($user)->get(route('dashboard'))->assertRedirect(route('face.edit'));
        }
        $user = User::factory()->create();
        DB::table('users')->where('id', $user->id)->update(['face_descriptor' => 'broken-ciphertext']);
        $this->assertFalse($user->fresh()->hasFaceEnrolled());
        $this->actingAs($user->fresh())->get(route('dashboard'))->assertRedirect(route('face.edit'));
    }

    public function test_two_factor_recovery_login_requires_enrollment(): void
    {
        $user = User::factory()->withTwoFactor()->create();
        $this->post(route('login.store'), ['email' => $user->email, 'password' => 'password'])
            ->assertRedirect(route('two-factor.login'));
        $this->post(route('two-factor.login.store'), ['recovery_code' => 'recovery-code-1'])
            ->assertRedirect(route('face.edit'));
        $this->assertAuthenticatedAs($user);
    }

    public function test_two_factor_and_passkey_response_contracts_return_enrollment_destination(): void
    {
        // Response wiring after authentication; not a simulated WebAuthn signature test.
        $user = User::factory()->create();
        $request = Request::create('/login', 'POST', server: ['HTTP_ACCEPT' => 'application/json']);
        $request->setUserResolver(fn () => $user);
        $request->setLaravelSession(app('session.store'));
        foreach ([TwoFactorLoginResponse::class, PasskeyLoginResponse::class] as $contract) {
            $response = app($contract)->toResponse($request);
            $this->assertSame(200, $response->getStatusCode());
            $this->assertSame(route('face.edit'), json_decode($response->getContent(), true)['redirect']);
        }
    }
}
