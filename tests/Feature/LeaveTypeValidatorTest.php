<?php

namespace Tests\Feature;

use App\Services\LeaveTypeValidator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LeaveTypeValidatorTest extends TestCase
{
    use RefreshDatabase;

    private LeaveTypeValidator $validator;

    protected function setUp(): void
    {
        parent::setUp();

        $this->validator = new LeaveTypeValidator;
    }

    // --- Izin Terlambat (late) ---

    public function test_late_valid_before_deadline_passes()
    {
        $this->travelTo(today()->setTime(6, 30));

        $errors = $this->validator->validate('late', [
            'leave_date' => today()->toDateString(),
            'estimated_arrival' => '08:00',
        ]);

        $this->assertSame([], $errors);
    }

    public function test_late_rejects_other_dates()
    {
        $this->travelTo(today()->setTime(6, 30));

        $errors = $this->validator->validate('late', [
            'leave_date' => today()->addDay()->toDateString(),
            'estimated_arrival' => '08:00',
        ]);

        $this->assertArrayHasKey('leave_date', $errors);
    }

    public function test_late_after_0700_requires_emergency_flag()
    {
        $this->travelTo(today()->setTime(7, 30));

        $errors = $this->validator->validate('late', [
            'leave_date' => today()->toDateString(),
            'estimated_arrival' => '08:00',
            'emergency' => false,
        ]);

        $this->assertArrayHasKey('emergency', $errors);
    }

    public function test_late_after_0700_passes_with_emergency_flag()
    {
        $this->travelTo(today()->setTime(7, 30));

        $errors = $this->validator->validate('late', [
            'leave_date' => today()->toDateString(),
            'estimated_arrival' => '08:00',
            'emergency' => true,
        ]);

        $this->assertSame([], $errors);
    }

    public function test_late_requires_estimated_arrival()
    {
        $this->travelTo(today()->setTime(6, 30));

        $errors = $this->validator->validate('late', [
            'leave_date' => today()->toDateString(),
        ]);

        $this->assertArrayHasKey('estimated_arrival', $errors);
    }

    public function test_late_rejects_arrival_after_0930()
    {
        $this->travelTo(today()->setTime(6, 30));

        $errors = $this->validator->validate('late', [
            'leave_date' => today()->toDateString(),
            'estimated_arrival' => '09:31',
        ]);

        $this->assertArrayHasKey('estimated_arrival', $errors);
    }

    // --- Izin Setengah Hari (half_day) ---

    public function test_half_day_valid_tomorrow_passes()
    {
        $errors = $this->validator->validate('half_day', [
            'leave_date' => today()->addDay()->toDateString(),
            'start_time' => '13:00',
        ]);

        $this->assertSame([], $errors);
    }

    public function test_half_day_rejects_today()
    {
        $errors = $this->validator->validate('half_day', [
            'leave_date' => today()->toDateString(),
            'start_time' => '13:00',
        ]);

        $this->assertArrayHasKey('leave_date', $errors);
    }

    public function test_half_day_rejects_start_time_before_1230()
    {
        $errors = $this->validator->validate('half_day', [
            'leave_date' => today()->addDay()->toDateString(),
            'start_time' => '12:00',
        ]);

        $this->assertArrayHasKey('start_time', $errors);
    }

    // --- Izin Keluar Kantor Sebentar (temporary_exit) ---

    public function test_temporary_exit_valid_passes()
    {
        $errors = $this->validator->validate('temporary_exit', [
            'leave_date' => today()->toDateString(),
            'start_time' => '10:00',
            'estimated_arrival' => '11:00',
        ]);

        $this->assertSame([], $errors);
    }

    public function test_temporary_exit_rejects_past_date()
    {
        $errors = $this->validator->validate('temporary_exit', [
            'leave_date' => today()->subDay()->toDateString(),
            'start_time' => '10:00',
            'estimated_arrival' => '11:00',
        ]);

        $this->assertArrayHasKey('leave_date', $errors);
    }

    public function test_temporary_exit_requires_both_times()
    {
        $errors = $this->validator->validate('temporary_exit', [
            'leave_date' => today()->toDateString(),
        ]);

        $this->assertArrayHasKey('start_time', $errors);
        $this->assertArrayHasKey('estimated_arrival', $errors);
    }

    // --- Cuti (leave) ---

    public function test_leave_valid_h7_passes()
    {
        $errors = $this->validator->validate('leave', [
            'leave_date' => today()->addDays(7)->toDateString(),
            'duration' => 3,
        ]);

        $this->assertSame([], $errors);
    }

    public function test_leave_rejects_less_than_h7()
    {
        $errors = $this->validator->validate('leave', [
            'leave_date' => today()->addDays(6)->toDateString(),
            'duration' => 3,
        ]);

        $this->assertArrayHasKey('leave_date', $errors);
    }

    public function test_leave_requires_duration()
    {
        $errors = $this->validator->validate('leave', [
            'leave_date' => today()->addDays(7)->toDateString(),
        ]);

        $this->assertArrayHasKey('duration', $errors);
    }

    // --- Izin Darurat (emergency) ---

    public function test_emergency_valid_before_deadline_passes()
    {
        $this->travelTo(today()->setTime(8, 0));

        $errors = $this->validator->validate('emergency', [
            'leave_date' => today()->toDateString(),
            'contactable' => true,
        ]);

        $this->assertSame([], $errors);
    }

    public function test_emergency_rejects_other_dates()
    {
        $this->travelTo(today()->setTime(8, 0));

        $errors = $this->validator->validate('emergency', [
            'leave_date' => today()->addDay()->toDateString(),
            'contactable' => true,
        ]);

        $this->assertArrayHasKey('leave_date', $errors);
    }

    public function test_emergency_after_0830_is_rejected_outright()
    {
        $this->travelTo(today()->setTime(8, 31));

        $errors = $this->validator->validate('emergency', [
            'leave_date' => today()->toDateString(),
            'contactable' => true,
        ]);

        $this->assertArrayHasKey('type', $errors);
    }

    public function test_emergency_requires_contactable()
    {
        $this->travelTo(today()->setTime(8, 0));

        $errors = $this->validator->validate('emergency', [
            'leave_date' => today()->toDateString(),
        ]);

        $this->assertArrayHasKey('contactable', $errors);
    }

    public function test_emergency_accepts_explicit_false_contactable()
    {
        $this->travelTo(today()->setTime(8, 0));

        $errors = $this->validator->validate('emergency', [
            'leave_date' => today()->toDateString(),
            'contactable' => false,
        ]);

        $this->assertArrayNotHasKey('contactable', $errors);
    }

    // --- Izin Sakit (sick) ---

    public function test_sick_valid_before_deadline_passes()
    {
        $this->travelTo(today()->setTime(8, 0));

        $errors = $this->validator->validate('sick', [
            'leave_date' => today()->toDateString(),
            'contactable' => true,
        ]);

        $this->assertSame([], $errors);
    }

    public function test_sick_rejects_past_date()
    {
        $errors = $this->validator->validate('sick', [
            'leave_date' => today()->subDay()->toDateString(),
            'contactable' => true,
        ]);

        $this->assertArrayHasKey('leave_date', $errors);
    }

    public function test_sick_after_0830_requires_emergency_flag()
    {
        $this->travelTo(today()->setTime(9, 0));

        $errors = $this->validator->validate('sick', [
            'leave_date' => today()->toDateString(),
            'contactable' => true,
            'emergency' => false,
        ]);

        $this->assertArrayHasKey('emergency', $errors);
    }

    public function test_sick_after_0830_passes_with_emergency_flag()
    {
        $this->travelTo(today()->setTime(9, 0));

        $errors = $this->validator->validate('sick', [
            'leave_date' => today()->toDateString(),
            'contactable' => true,
            'emergency' => true,
        ]);

        $this->assertSame([], $errors);
    }

    public function test_sick_requires_contactable()
    {
        $this->travelTo(today()->setTime(8, 0));

        $errors = $this->validator->validate('sick', [
            'leave_date' => today()->toDateString(),
        ]);

        $this->assertArrayHasKey('contactable', $errors);
    }
}
