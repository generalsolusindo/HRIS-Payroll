<?php

namespace Tests\Feature;

use App\Models\Attendance;
use App\Models\Company;
use App\Models\OfficeLocation;
use App\Models\User;
use App\Models\WorkSchedule;
use App\Services\FaceDescriptorExtractor;
use App\Services\FaceDescriptorResult;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Mockery;
use Tests\TestCase;

class AttendanceFaceMatchTest extends TestCase
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

    public function test_an_employee_without_enrolled_face_cannot_clock_in(): void
    {
        $user = $this->makeUser(['face_descriptor' => null]);

        $this->actingAs($user)
            ->post(route('absen.masuk'), $this->payload())
            ->assertRedirect(route('face.edit'));

        $this->assertDatabaseCount('attendances', 0);
    }

    public function test_a_matching_face_is_recorded_on_clock_in(): void
    {
        $descriptor = array_fill(0, 128, 0.2);
        $user = $this->makeUser(['face_descriptor' => $descriptor]);
        $this->fakeExtractor(FaceDescriptorResult::success($descriptor));

        $this->actingAs($user)
            ->post(route('absen.masuk'), $this->payload())
            ->assertSessionDoesntHaveErrors();

        $attendance = Attendance::first();
        $this->assertTrue($attendance->clock_in_face_match);
        $this->assertNotNull($attendance->clock_in_face_distance);
    }

    public function test_a_mismatched_face_blocks_clock_in_and_saves_nothing(): void
    {
        $user = $this->makeUser(['face_descriptor' => array_fill(0, 128, 0.2)]);
        $this->fakeExtractor(FaceDescriptorResult::success(array_fill(0, 128, 0.9)));

        $response = $this->actingAs($user)
            ->post(route('absen.masuk'), $this->payload());

        $response->assertSessionHasErrors('photo');
        $this->assertSame(0, Attendance::count(), 'A mismatch must not create an attendance row at all.');
        $this->assertEmpty(
            Storage::disk('attendance_photos')->allFiles($user->id.'/'.today()->toDateString()),
            'The rejected selfie must not be kept.',
        );
    }

    public function test_an_undetectable_face_in_the_selfie_blocks_clock_in(): void
    {
        $user = $this->makeUser(['face_descriptor' => array_fill(0, 128, 0.2)]);
        $this->fakeExtractor(FaceDescriptorResult::failure('no_face'));

        $response = $this->actingAs($user)
            ->post(route('absen.masuk'), $this->payload());

        $response->assertSessionHasErrors('photo');
        $this->assertSame(0, Attendance::count());
    }

    public function test_face_match_is_also_recorded_on_clock_out(): void
    {
        $descriptor = array_fill(0, 128, 0.3);
        $user = $this->makeUser(['face_descriptor' => $descriptor]);
        $this->fakeExtractor(FaceDescriptorResult::success($descriptor));

        $attendance = Attendance::create([
            'user_id' => $user->id,
            'tanggal' => today(),
            'clock_in_time' => now()->subHours(8),
        ]);

        $this->actingAs($user)
            ->post(route('absen.pulang'), $this->payload())
            ->assertSessionDoesntHaveErrors();

        $this->assertTrue($attendance->fresh()->clock_out_face_match);
    }

    public function test_a_mismatched_face_blocks_clock_out_and_leaves_clock_in_untouched(): void
    {
        $descriptor = array_fill(0, 128, 0.2);
        $user = $this->makeUser(['face_descriptor' => $descriptor]);
        $this->fakeExtractor(FaceDescriptorResult::success(array_fill(0, 128, 0.9)));

        $clockInTime = now()->subHours(8);
        $attendance = Attendance::create([
            'user_id' => $user->id,
            'tanggal' => today(),
            'clock_in_time' => $clockInTime,
        ]);

        $response = $this->actingAs($user)
            ->post(route('absen.pulang'), $this->payload());

        $response->assertSessionHasErrors('photo');

        $fresh = $attendance->fresh();
        $this->assertNull($fresh->clock_out_time);
        $this->assertEquals($clockInTime->toDateTimeString(), $fresh->clock_in_time->toDateTimeString());
    }
}
