<?php

namespace Tests\Feature;

use App\Models\Attendance;
use App\Models\Company;
use App\Models\FaceVerificationReceipt;
use App\Models\OfficeLocation;
use App\Models\User;
use App\Models\WorkSchedule;
use App\Services\FaceDescriptorExtractor;
use App\Services\FaceDescriptorResult;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Mockery;
use Tests\TestCase;

class AttendanceReceiptConsumptionTest extends TestCase
{
    use RefreshDatabase;

    private const OFFICE_LATITUDE = -6.2000000;

    private const OFFICE_LONGITUDE = 106.8160000;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('attendance_photos');
    }

    private function makeUser(array $attributes = []): User
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

        // A real enrollment always sets both columns together (see
        // FaceProfileController::store), so default face_enrolled_at
        // alongside face_descriptor unless a test overrides it on
        // purpose (e.g. to simulate a stale receipt).
        if (isset($attributes['face_descriptor']) && ! array_key_exists('face_enrolled_at', $attributes)) {
            $attributes['face_enrolled_at'] = now();
        }

        return User::factory()->create(array_merge(['company_id' => $company->id], $attributes));
    }

    private function payload(array $overrides = []): array
    {
        return array_merge([
            'latitude' => self::OFFICE_LATITUDE,
            'longitude' => self::OFFICE_LONGITUDE,
            'accuracy' => 15,
            'photo' => UploadedFile::fake()->image('selfie.jpg'),
        ], $overrides);
    }

    private function fakeExtractor(FaceDescriptorResult $result): void
    {
        $extractor = Mockery::mock(FaceDescriptorExtractor::class);
        $extractor->shouldReceive('extract')->once()->andReturn($result);
        $extractor->shouldReceive('distance')->andReturnUsing(
            fn (array $a, array $b) => array_sum(array_map(fn ($x, $y) => ($x - $y) ** 2, $a, $b)) ** 0.5,
        );
        $extractor->shouldReceive('matches')->andReturnUsing(
            fn (array $a, array $b) => $a === $b,
        );
        $this->app->instance(FaceDescriptorExtractor::class, $extractor);
    }

    /**
     * Laravel's test client starts a brand new session (new id) on every
     * separate call by default — it doesn't replay the previous
     * response's cookie automatically. Pinning a chosen id onto the
     * request's session cookie (encrypted the same way a real browser's
     * would be) is the documented way to make a later call resolve to
     * a session we already know the id of, so a receipt issued directly
     * against that id is actually bound to the session the real request
     * will use.
     */
    private function pinSessionId(string $sessionId): static
    {
        return $this->withCookie(config('session.cookie'), $sessionId);
    }

    public function test_a_valid_receipt_is_consumed_on_successful_clock_in(): void
    {
        $descriptor = array_fill(0, 128, 0.2);
        $user = $this->makeUser(['face_descriptor' => $descriptor]);
        $this->fakeExtractor(FaceDescriptorResult::success($descriptor));

        $sessionId = Str::random(40);
        $receipt = FaceVerificationReceipt::issue($user, $sessionId, 'masuk', 'attempt-1', null, 10);

        $this->pinSessionId($sessionId)
            ->actingAs($user)
            ->post(route('absen.masuk'), $this->payload(['receipt' => $receipt->token]))
            ->assertSessionDoesntHaveErrors();

        $this->assertNotNull($receipt->fresh()->consumed_at);
    }

    public function test_attendance_still_succeeds_without_any_receipt(): void
    {
        $descriptor = array_fill(0, 128, 0.2);
        $user = $this->makeUser(['face_descriptor' => $descriptor]);
        $this->fakeExtractor(FaceDescriptorResult::success($descriptor));

        $this->actingAs($user)
            ->post(route('absen.masuk'), $this->payload())
            ->assertSessionDoesntHaveErrors();

        $this->assertSame(1, Attendance::count());
    }

    public function test_an_already_consumed_receipt_is_not_reused_but_does_not_block_attendance(): void
    {
        $descriptor = array_fill(0, 128, 0.2);
        $user = $this->makeUser(['face_descriptor' => $descriptor]);
        $this->fakeExtractor(FaceDescriptorResult::success($descriptor));

        $sessionId = Str::random(40);
        $receipt = FaceVerificationReceipt::issue($user, $sessionId, 'masuk', 'attempt-1', null, 10);
        $receipt->forceFill(['consumed_at' => now()])->save();
        $originalConsumedAt = $receipt->consumed_at;

        $this->pinSessionId($sessionId)
            ->actingAs($user)
            ->post(route('absen.masuk'), $this->payload(['receipt' => $receipt->token]))
            ->assertSessionDoesntHaveErrors();

        $this->assertSame(1, Attendance::count());
        $this->assertEquals($originalConsumedAt, $receipt->fresh()->consumed_at);
    }

    public function test_a_receipt_issued_for_a_different_action_is_ignored(): void
    {
        $descriptor = array_fill(0, 128, 0.2);
        $user = $this->makeUser(['face_descriptor' => $descriptor]);
        $this->fakeExtractor(FaceDescriptorResult::success($descriptor));

        $sessionId = Str::random(40);
        $receipt = FaceVerificationReceipt::issue($user, $sessionId, 'pulang', 'attempt-1', null, 10);

        $this->pinSessionId($sessionId)
            ->actingAs($user)
            ->post(route('absen.masuk'), $this->payload(['receipt' => $receipt->token]))
            ->assertSessionDoesntHaveErrors();

        $this->assertSame(1, Attendance::count());
        $this->assertNull($receipt->fresh()->consumed_at);
    }

    public function test_a_receipt_belonging_to_a_different_user_is_ignored(): void
    {
        $descriptor = array_fill(0, 128, 0.2);
        $user = $this->makeUser(['face_descriptor' => $descriptor]);
        $otherUser = $this->makeUser(['face_descriptor' => array_fill(0, 128, 0.9)]);
        $this->fakeExtractor(FaceDescriptorResult::success($descriptor));

        $sessionId = Str::random(40);
        $receipt = FaceVerificationReceipt::issue($otherUser, $sessionId, 'masuk', 'attempt-1', null, 10);

        $this->pinSessionId($sessionId)
            ->actingAs($user)
            ->post(route('absen.masuk'), $this->payload(['receipt' => $receipt->token]))
            ->assertSessionDoesntHaveErrors();

        $this->assertNull($receipt->fresh()->consumed_at);
    }

    public function test_an_expired_receipt_is_ignored(): void
    {
        $descriptor = array_fill(0, 128, 0.2);
        $user = $this->makeUser(['face_descriptor' => $descriptor]);
        $this->fakeExtractor(FaceDescriptorResult::success($descriptor));

        $sessionId = Str::random(40);
        $receipt = FaceVerificationReceipt::issue($user, $sessionId, 'masuk', 'attempt-1', null, 10);
        $receipt->forceFill(['expires_at' => now()->subSecond()])->save();

        $this->pinSessionId($sessionId)
            ->actingAs($user)
            ->post(route('absen.masuk'), $this->payload(['receipt' => $receipt->token]))
            ->assertSessionDoesntHaveErrors();

        $this->assertNull($receipt->fresh()->consumed_at);
    }

    public function test_an_unknown_receipt_token_does_not_crash_the_request(): void
    {
        $descriptor = array_fill(0, 128, 0.2);
        $user = $this->makeUser(['face_descriptor' => $descriptor]);
        $this->fakeExtractor(FaceDescriptorResult::success($descriptor));

        $this->actingAs($user)
            ->post(route('absen.masuk'), $this->payload(['receipt' => 'this-token-does-not-exist']))
            ->assertSessionDoesntHaveErrors();

        $this->assertSame(1, Attendance::count());
    }

    public function test_a_stale_receipt_from_before_re_enrollment_is_ignored(): void
    {
        $descriptor = array_fill(0, 128, 0.2);
        $user = $this->makeUser(['face_descriptor' => $descriptor, 'face_enrolled_at' => now()->subDay()]);
        $this->fakeExtractor(FaceDescriptorResult::success($descriptor));

        $sessionId = Str::random(40);
        $receipt = FaceVerificationReceipt::issue($user, $sessionId, 'masuk', 'attempt-1', null, 10);
        // Simulate the user re-enrolling their face after the preview but
        // before the final submit.
        $user->forceFill(['face_enrolled_at' => now()])->save();

        $this->pinSessionId($sessionId)
            ->actingAs($user)
            ->post(route('absen.masuk'), $this->payload(['receipt' => $receipt->token]))
            ->assertSessionDoesntHaveErrors();

        $this->assertNull($receipt->fresh()->consumed_at);
    }

    public function test_a_mismatched_face_still_blocks_attendance_even_with_a_valid_receipt(): void
    {
        $user = $this->makeUser(['face_descriptor' => array_fill(0, 128, 0.2)]);
        // The receipt itself proves nothing about the FINAL submitted
        // photo — assertFaceMatches() always re-checks independently.
        $this->fakeExtractor(FaceDescriptorResult::success(array_fill(0, 128, 0.9)));

        $sessionId = Str::random(40);
        $receipt = FaceVerificationReceipt::issue($user, $sessionId, 'masuk', 'attempt-1', null, 10);

        $response = $this->pinSessionId($sessionId)
            ->actingAs($user)
            ->post(route('absen.masuk'), $this->payload(['receipt' => $receipt->token]));

        $response->assertSessionHasErrors('photo');
        $this->assertSame(0, Attendance::count());
        // A rejected final check must not consume the receipt either —
        // nothing about this attempt actually succeeded.
        $this->assertNull($receipt->fresh()->consumed_at);
    }
}
