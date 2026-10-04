<?php

namespace Tests\Feature;

use App\Models\Attendance;
use App\Models\Company;
use App\Models\OfficeLocation;
use App\Models\User;
use App\Models\WorkSchedule;
use App\Services\FaceDescriptorExtractor;
use App\Services\FaceDescriptorResult;
use Carbon\CarbonImmutable;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class AttendanceTest extends TestCase
{
    use RefreshDatabase;

    private const OFFICE_LATITUDE = -6.2000000;

    private const OFFICE_LONGITUDE = 106.8160000;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('attendance_photos');
        $extractor = \Mockery::mock(FaceDescriptorExtractor::class)->makePartial();
        $extractor->shouldReceive('extract')->andReturn(FaceDescriptorResult::success(array_fill(0, 128, 0.2)));
        $this->app->instance(FaceDescriptorExtractor::class, $extractor);
    }

    private function makeUser(): User
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

        return User::factory()->withEnrolledFace()->create(['company_id' => $company->id]);
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

    public function test_employee_can_clock_in_within_office_radius()
    {
        $user = $this->makeUser();
        $this->travelTo(today()->setTime(7, 50));

        $this->actingAs($user)
            ->post(route('absen.masuk'), $this->payload())
            ->assertSessionDoesntHaveErrors()
            ->assertRedirect();

        $attendance = Attendance::first();

        $this->assertSame('tepat_waktu', $attendance->clock_in_status);
        $this->assertNotNull($attendance->clock_in_time);
        Storage::disk('attendance_photos')->assertExists($attendance->clock_in_photo_path);
    }

    public function test_employee_clocking_in_late_is_marked_telat()
    {
        $user = $this->makeUser();
        $this->travelTo(today()->setTime(8, 30));

        $this->actingAs($user)->post(route('absen.masuk'), $this->payload());

        $attendance = Attendance::first();

        $this->assertSame('telat', $attendance->clock_in_status);
        $this->assertSame(30, $attendance->clock_in_late_minutes);
    }

    public function test_clock_in_at_0834_wib_is_four_minutes_late_even_with_old_grace_period(): void
    {
        $user = $this->makeUser();
        $user->company->workSchedules()->update(['jam_masuk' => '08:30:00']);
        $this->travelTo(CarbonImmutable::parse('2026-10-03 01:34:00', 'UTC'));

        $this->actingAs($user)->post(route('absen.masuk'), $this->payload())
            ->assertSessionDoesntHaveErrors();

        $this->assertDatabaseHas('attendances', [
            'user_id' => $user->id,
            'clock_in_status' => 'telat',
            'clock_in_late_minutes' => 4,
            'clock_in_time' => '2026-10-03 08:34:00',
        ]);
    }

    public function test_clock_in_at_exactly_0830_wib_is_on_time(): void
    {
        $user = $this->makeUser();
        $user->company->workSchedules()->update(['jam_masuk' => '08:30:00']);
        $this->travelTo(today()->setTime(8, 30));

        $this->actingAs($user)->post(route('absen.masuk'), $this->payload())
            ->assertSessionDoesntHaveErrors();

        $this->assertDatabaseHas('attendances', [
            'user_id' => $user->id, 'clock_in_status' => 'tepat_waktu', 'clock_in_late_minutes' => null,
        ]);
    }

    public function test_employee_clocking_in_on_time_has_no_late_minutes()
    {
        $user = $this->makeUser();
        $this->travelTo(today()->setTime(7, 50));

        $this->actingAs($user)->post(route('absen.masuk'), $this->payload());

        $this->assertNull(Attendance::first()->clock_in_late_minutes);
    }

    public function test_radius_is_capped_at_twenty_meters_even_with_larger_database_radius(): void
    {
        $user = $this->makeUser(); // Legacy radius is 100 m.
        $this->actingAs($user)->post(route('absen.masuk'), $this->payload([
            'latitude' => self::OFFICE_LATITUDE + rad2deg(21 / 6371000),
        ]))->assertSessionHasErrors('latitude');
        $this->assertDatabaseCount('attendances', 0);
    }

    public function test_nineteen_meters_is_allowed_but_clock_out_at_twenty_one_is_rejected(): void
    {
        $user = $this->makeUser();
        $this->actingAs($user)->post(route('absen.masuk'), $this->payload([
            'latitude' => self::OFFICE_LATITUDE + rad2deg(19 / 6371000),
        ]))->assertSessionDoesntHaveErrors();
        $this->assertDatabaseCount('attendances', 1);
        $this->actingAs($user)->post(route('absen.pulang'), $this->payload([
            'latitude' => self::OFFICE_LATITUDE + rad2deg(21 / 6371000),
        ]))->assertSessionHasErrors('latitude');
        $this->assertNull(Attendance::first()->clock_out_time);
    }

    public function test_employee_outside_office_radius_is_rejected()
    {
        $user = $this->makeUser();
        $this->travelTo(today()->setTime(7, 50));

        $response = $this->actingAs($user)->post(route('absen.masuk'), $this->payload([
            'latitude' => -6.2500000,
            'longitude' => 106.8500000,
        ]));

        $response->assertSessionHasErrors('latitude');
        $this->assertDatabaseCount('attendances', 0);
    }

    public function test_employee_inside_another_valid_office_is_not_rejected_by_nearest_small_office(): void
    {
        $user = $this->makeUser();
        $user->company->officeLocations()->update(['radius_meter' => 5]);
        OfficeLocation::create([
            'company_id' => $user->company_id, 'name' => 'Area Kantor',
            'latitude' => self::OFFICE_LATITUDE + 0.0002,
            'longitude' => self::OFFICE_LONGITUDE, 'radius_meter' => 200,
        ]);
        $this->actingAs($user)->post(route('absen.masuk'), $this->payload([
            'latitude' => self::OFFICE_LATITUDE + 0.0001,
        ]))->assertSessionDoesntHaveErrors();
        $this->assertDatabaseCount('attendances', 1);
    }

    public function test_office_from_another_company_does_not_allow_attendance(): void
    {
        $user = $this->makeUser();
        OfficeLocation::create([
            'company_id' => Company::factory()->create()->id, 'name' => 'Kantor Lain',
            'latitude' => -6.25, 'longitude' => 106.85, 'radius_meter' => 200,
        ]);
        $this->actingAs($user)->post(route('absen.masuk'), $this->payload([
            'latitude' => -6.25, 'longitude' => 106.85,
        ]))->assertSessionHasErrors('latitude');
        $this->assertDatabaseCount('attendances', 0);
    }

    public function test_poor_accuracy_also_rejects_clock_out_without_writing_it(): void
    {
        $user = $this->makeUser();
        $this->actingAs($user)->post(route('absen.masuk'), $this->payload());
        $this->actingAs($user)->post(route('absen.pulang'), $this->payload(['accuracy' => 80]))
            ->assertSessionHasErrors('accuracy');
        $this->assertNull(Attendance::first()->clock_out_time);
    }

    public function test_employee_with_poor_gps_accuracy_is_rejected()
    {
        $user = $this->makeUser();
        $this->travelTo(today()->setTime(7, 50));

        $response = $this->actingAs($user)->post(route('absen.masuk'), $this->payload([
            'accuracy' => 80,
        ]));

        $response->assertSessionHasErrors('accuracy');
        $this->assertDatabaseCount('attendances', 0);
    }

    public function test_employee_cannot_clock_in_twice_in_one_day()
    {
        $user = $this->makeUser();
        $this->travelTo(today()->setTime(7, 50));

        $this->actingAs($user)->post(route('absen.masuk'), $this->payload());
        $response = $this->actingAs($user)->post(route('absen.masuk'), $this->payload());

        $response->assertSessionHasErrors('photo');
        $this->assertDatabaseCount('attendances', 1);
    }

    public function test_employee_cannot_clock_out_before_clocking_in()
    {
        $user = $this->makeUser();
        $this->travelTo(today()->setTime(17, 0));

        $response = $this->actingAs($user)->post(route('absen.pulang'), $this->payload());

        $response->assertSessionHasErrors('photo');
        $this->assertDatabaseCount('attendances', 0);
    }

    public function test_employee_can_clock_out_after_clocking_in()
    {
        $user = $this->makeUser();

        $this->travelTo(today()->setTime(7, 50));
        $this->actingAs($user)->post(route('absen.masuk'), $this->payload());

        $this->travelTo(today()->setTime(17, 30));
        $this->actingAs($user)
            ->post(route('absen.pulang'), $this->payload())
            ->assertSessionDoesntHaveErrors();

        $attendance = Attendance::first();
        $this->assertSame('tepat_waktu', $attendance->clock_out_status);
        $this->assertNotNull($attendance->clock_out_time);
    }

    public function test_employee_leaving_early_is_marked_pulang_cepat()
    {
        $user = $this->makeUser();

        $this->travelTo(today()->setTime(7, 50));
        $this->actingAs($user)->post(route('absen.masuk'), $this->payload());

        $this->travelTo(today()->setTime(15, 0));
        $this->actingAs($user)->post(route('absen.pulang'), $this->payload());

        $attendance = Attendance::first();

        $this->assertSame('pulang_cepat', $attendance->clock_out_status);
        $this->assertSame(120, $attendance->clock_out_early_minutes);
    }

    public function test_early_departure_saves_whole_minutes_without_fractional_seconds(): void
    {
        $user = $this->makeUser();
        $this->travelTo(today()->setTime(7, 50));
        $this->actingAs($user)->post(route('absen.masuk'), $this->payload());

        $this->travelTo(today()->setTime(13, 40, 33));
        $this->actingAs($user)->post(route('absen.pulang'), $this->payload())
            ->assertSessionDoesntHaveErrors();

        $attendance = Attendance::first();
        $this->assertSame('pulang_cepat', $attendance->clock_out_status);
        $this->assertSame(199, $attendance->clock_out_early_minutes);
        $this->assertDatabaseHas('attendances', ['id' => $attendance->id, 'clock_out_early_minutes' => 199]);
        $this->assertSame(199, $attendance->toArray()['clock_out_early_minutes']);
    }

    public function test_early_departure_of_less_than_a_minute_has_minimum_one_minute(): void
    {
        $user = $this->makeUser();
        $this->travelTo(today()->setTime(7, 50));
        $this->actingAs($user)->post(route('absen.masuk'), $this->payload());
        $this->travelTo(today()->setTime(16, 59, 59));
        $this->actingAs($user)->post(route('absen.pulang'), $this->payload());
        $this->assertSame(1, Attendance::first()->clock_out_early_minutes);
    }

    public function test_legacy_fractional_minutes_are_serialized_as_integers(): void
    {
        $attendance = new Attendance;
        $attendance->setRawAttributes([
            'clock_out_early_minutes' => 199.45022413333,
            'clock_in_late_minutes' => 4.75,
        ]);
        $this->assertSame(199, $attendance->toArray()['clock_out_early_minutes']);
        $this->assertSame(4, $attendance->toArray()['clock_in_late_minutes']);
    }

    public function test_employee_leaving_on_time_has_no_early_minutes()
    {
        $user = $this->makeUser();

        $this->travelTo(today()->setTime(7, 50));
        $this->actingAs($user)->post(route('absen.masuk'), $this->payload());

        $this->travelTo(today()->setTime(17, 30));
        $this->actingAs($user)->post(route('absen.pulang'), $this->payload());

        $this->assertNull(Attendance::first()->clock_out_early_minutes);
    }

    public function test_employee_cannot_clock_out_twice_in_one_day()
    {
        $user = $this->makeUser();

        $this->travelTo(today()->setTime(7, 50));
        $this->actingAs($user)->post(route('absen.masuk'), $this->payload());

        $this->travelTo(today()->setTime(17, 0));
        $this->actingAs($user)->post(route('absen.pulang'), $this->payload());
        $response = $this->actingAs($user)->post(route('absen.pulang'), $this->payload());

        $response->assertSessionHasErrors('photo');
    }

    public function test_svg_photo_is_rejected()
    {
        $user = $this->makeUser();
        $this->travelTo(today()->setTime(7, 50));

        $response = $this->actingAs($user)->post(route('absen.masuk'), $this->payload([
            'photo' => UploadedFile::fake()->create('selfie.svg', 10, 'image/svg+xml'),
        ]));

        $response->assertSessionHasErrors('photo');
        $this->assertDatabaseCount('attendances', 0);
    }

    public function test_duplicate_attendance_row_is_rejected_at_the_database_level()
    {
        $user = $this->makeUser();

        Attendance::create([
            'user_id' => $user->id,
            'tanggal' => today(),
            'clock_in_time' => now(),
            'clock_in_status' => 'tepat_waktu',
        ]);

        $this->expectException(UniqueConstraintViolationException::class);

        Attendance::create([
            'user_id' => $user->id,
            'tanggal' => today(),
            'clock_in_time' => now(),
            'clock_in_status' => 'tepat_waktu',
        ]);
    }
}
