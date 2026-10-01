<?php

namespace Tests\Feature;

use App\Models\LeaveRequestSequence;
use App\Services\LeaveRequestService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LeaveRequestServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_first_request_number_of_the_year_starts_at_one()
    {
        $this->travelTo(today()->setDate(2026, 1, 15));

        $number = (new LeaveRequestService)->generateRequestNumber();

        $this->assertSame('IZN-2026-000001', $number);
    }

    public function test_request_numbers_increment_sequentially()
    {
        $this->travelTo(today()->setDate(2026, 1, 15));
        $service = new LeaveRequestService;

        $this->assertSame('IZN-2026-000001', $service->generateRequestNumber());
        $this->assertSame('IZN-2026-000002', $service->generateRequestNumber());
        $this->assertSame('IZN-2026-000003', $service->generateRequestNumber());
    }

    public function test_sequence_resets_per_year()
    {
        $service = new LeaveRequestService;

        $this->travelTo(today()->setDate(2026, 12, 31));
        $this->assertSame('IZN-2026-000001', $service->generateRequestNumber());

        $this->travelTo(today()->setDate(2027, 1, 1));
        $this->assertSame('IZN-2027-000001', $service->generateRequestNumber());

        $this->assertSame(1, LeaveRequestSequence::find(2026)->last_sequence);
        $this->assertSame(1, LeaveRequestSequence::find(2027)->last_sequence);
    }
}
