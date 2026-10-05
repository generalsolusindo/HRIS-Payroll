<?php

namespace Tests\Feature;

use App\Models\Attendance;
use App\Models\Company;
use App\Models\OfficeLocation;
use App\Models\User;
use App\Models\WorkSchedule;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Covers config('face.verification_enabled') = false — the FACE_VERIFICATION_ENABLED
 * kill switch used to turn the whole feature off in production without a
 * code change, while the worker/threshold/etc. are unreliable or down.
 */
class FaceVerificationKillSwitchTest extends TestCase
{
    use RefreshDatabase;

    private const OFFICE_LATITUDE = -6.2000000;

    private const OFFICE_LONGITUDE = 106.8160000;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('attendance_photos');
        config(['face.verification_enabled' => false]);
    }

    private function makeUnenrolledUser(): User
    {
        $company = Company::factory()->create();

        OfficeLocation::create([
            'company_id' => $company->id,
            'name' => 'Kantor Pusat',
            'latitude' => self::OFFICE_LATITUDE,
            'longitude' => self::OFFICE_LONGITUDE,
            'radius_meter' => 100,
        ]);

        WorkSchedule::create([
            'company_id' => $company->id,
            'name' => 'Reguler',
            'jam_masuk' => '08:00:00',
            'jam_pulang' => '17:00:00',
            'toleransi_telat_menit' => 15,
        ]);

        return User::factory()->create([
            'company_id' => $company->id,
            'face_descriptor' => null,
            'face_enrolled_at' => null,
        ]);
    }

    public function test_an_unenrolled_user_can_reach_the_dashboard(): void
    {
        $this->actingAs($this->makeUnenrolledUser())
            ->get(route('dashboard'))
            ->assertOk();
    }

    public function test_login_does_not_redirect_an_unenrolled_user_to_enrollment(): void
    {
        $user = $this->makeUnenrolledUser();

        $this->post(route('login.store'), [
            'email' => $user->email, 'password' => 'password',
        ])->assertRedirect(route('dashboard'));
    }

    public function test_an_unenrolled_user_can_clock_in_without_any_face_check(): void
    {
        $user = $this->makeUnenrolledUser();

        $response = $this->actingAs($user)->post(route('absen.masuk'), [
            'latitude' => self::OFFICE_LATITUDE,
            'longitude' => self::OFFICE_LONGITUDE,
            'accuracy' => 15,
            'photo' => UploadedFile::fake()->image('selfie.jpg'),
        ]);

        $response->assertSessionDoesntHaveErrors();
        $attendance = Attendance::first();
        $this->assertNotNull($attendance);
        $this->assertNull($attendance->clock_in_face_match);
        $this->assertNull($attendance->clock_in_face_distance);
    }
}
