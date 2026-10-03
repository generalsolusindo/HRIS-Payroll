<?php

namespace Tests\Feature;

use App\Mail\LeaveRequestDecisionMail;
use App\Models\LeaveRequest;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

/**
 * Walks the real HTTP endpoints through the full lifecycle in sequence
 * (submit -> HRD -> Manager), unlike every other Izin test file, which
 * sets up a LeaveRequest directly in whatever state a single stage
 * needs. This is the one place that proves all 8 tahap actually
 * compose correctly as a whole, not just individually.
 */
class LeaveRequestLifecycleTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Http::fake(['api.telegram.org/*' => Http::response(['ok' => true], 200)]);
        Mail::fake();
        config([
            'services.telegram.bot_token' => 'test-bot-token',
            'services.telegram.chat_id' => '-100123456',
        ]);
    }

    public function test_full_lifecycle_submit_through_hrd_and_manager_approval()
    {
        $employee = User::factory()->create(['name' => 'Budi Karyawan']);
        $hrd = User::factory()->create(['role' => 'hrd', 'name' => 'Tim HRD']);
        $manager = User::factory()->create(['role' => 'admin', 'name' => 'Pak Manager']);

        $this->actingAs($employee)->post(route('izin.store'), [
            'type' => 'temporary_exit',
            'leave_date' => today()->toDateString(),
            'start_time' => '10:00',
            'estimated_arrival' => '11:00',
            'reason' => 'Urusan bank.',
        ])->assertSessionDoesntHaveErrors();

        $leaveRequest = LeaveRequest::firstOrFail();
        $this->assertSame('pending', $leaveRequest->status);
        $this->assertMatchesRegularExpression('/^IZN-\d{4}-\d{6}$/', $leaveRequest->request_number);

        $this->actingAs($employee)->get(route('izin'))->assertOk();
        $this->actingAs($employee)->get(route('izin.show', $leaveRequest))->assertOk();

        $hrdQueue = fn () => collect(
            $this->actingAs($hrd)->get(route('hrd.izin.index'))->viewData('page')['props']['leaveRequests']
        )->pluck('id');
        $this->assertContains($leaveRequest->id, $hrdQueue());

        $this->actingAs($hrd)
            ->patch(route('hrd.izin.approve', $leaveRequest), ['note' => 'Oke, silakan.'])
            ->assertSessionDoesntHaveErrors();

        $leaveRequest->refresh();
        $this->assertSame('pending_manager', $leaveRequest->status);
        $this->assertSame($hrd->id, $leaveRequest->hrd_processed_by);
        $this->assertSame('approved', $leaveRequest->hrd_decision);
        $this->assertNull($leaveRequest->processed_by);
        $this->assertNotContains($leaveRequest->id, $hrdQueue());

        $managerQueue = fn () => collect(
            $this->actingAs($manager)->get(route('manager.izin.index'))->viewData('page')['props']['leaveRequests']
        )->pluck('id');
        $this->assertContains($leaveRequest->id, $managerQueue());

        $this->actingAs($manager)
            ->patch(route('manager.izin.approve', $leaveRequest), ['note' => 'Disetujui.'])
            ->assertSessionDoesntHaveErrors();

        $leaveRequest->refresh();
        $this->assertSame('approved', $leaveRequest->status);
        $this->assertSame($manager->id, $leaveRequest->processed_by);
        $this->assertSame('approved', $leaveRequest->hrd_decision);
        $this->assertNotNull($leaveRequest->hrd_processed_at);
        $this->assertNotNull($leaveRequest->processed_at);
        $this->assertNotContains($leaveRequest->id, $managerQueue());

        // 3 Telegram sends across the lifecycle: submitted, HRD-approved, final-approved.
        $this->assertCount(3, Http::recorded(
            fn ($request) => str_starts_with($request->url(), 'https://api.telegram.org/'),
        ));
        Mail::assertSent(LeaveRequestDecisionMail::class, 1);
        $this->assertSame('sent', $leaveRequest->email_status);
    }

    public function test_full_lifecycle_hrd_rejection_is_final_and_never_reaches_manager()
    {
        $employee = User::factory()->create();
        $hrd = User::factory()->create(['role' => 'hrd']);
        $manager = User::factory()->create(['role' => 'admin']);

        $this->actingAs($employee)->post(route('izin.store'), [
            'type' => 'leave',
            'leave_date' => today()->addDays(7)->toDateString(),
            'duration' => 3,
        ]);

        $leaveRequest = LeaveRequest::firstOrFail();

        $this->actingAs($hrd)
            ->patch(route('hrd.izin.reject', $leaveRequest), ['note' => 'Dokumen tidak lengkap.'])
            ->assertSessionDoesntHaveErrors();

        $leaveRequest->refresh();
        $this->assertSame('rejected', $leaveRequest->status);
        $this->assertSame($hrd->id, $leaveRequest->processed_by);
        $this->assertSame('Dokumen tidak lengkap.', $leaveRequest->rejection_reason);

        $managerQueue = collect(
            $this->actingAs($manager)->get(route('manager.izin.index'))->viewData('page')['props']['leaveRequests']
        )->pluck('id');
        $this->assertNotContains($leaveRequest->id, $managerQueue);

        // Manager still can't act on it even if they try directly.
        $this->actingAs($manager)
            ->patch(route('manager.izin.approve', $leaveRequest), [])
            ->assertSessionHasErrors('status');

        $this->assertSame('rejected', $leaveRequest->fresh()->status);
        Mail::assertSent(LeaveRequestDecisionMail::class, 1);
    }

    public function test_full_lifecycle_manager_rejection_after_hrd_approval()
    {
        $employee = User::factory()->create();
        $hrd = User::factory()->create(['role' => 'hrd']);
        $manager = User::factory()->create(['role' => 'admin']);

        $this->actingAs($employee)->post(route('izin.store'), [
            'type' => 'temporary_exit',
            'leave_date' => today()->toDateString(),
            'start_time' => '10:00',
            'estimated_arrival' => '11:00',
        ]);

        $leaveRequest = LeaveRequest::firstOrFail();

        $this->actingAs($hrd)->patch(route('hrd.izin.approve', $leaveRequest), []);
        $this->actingAs($manager)
            ->patch(route('manager.izin.reject', $leaveRequest), ['note' => 'Beban kerja tinggi minggu ini.'])
            ->assertSessionDoesntHaveErrors();

        $leaveRequest->refresh();
        $this->assertSame('rejected', $leaveRequest->status);
        $this->assertSame($manager->id, $leaveRequest->processed_by);
        // HRD's own decision stays intact as a separate audit trail.
        $this->assertSame('approved', $leaveRequest->hrd_decision);
        $this->assertSame($hrd->id, $leaveRequest->hrd_processed_by);

        Mail::assertSent(LeaveRequestDecisionMail::class, 1);
    }
}
