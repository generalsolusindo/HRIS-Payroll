<?php

namespace Tests\Feature;

use App\Mail\LeaveRequestDecisionMail;
use App\Models\LeaveRequest;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Request as HttpRequest;
use Illuminate\Mail\PendingMail;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Mockery;
use Tests\TestCase;

class LeaveRequestNotificationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'services.telegram.bot_token' => 'test-bot-token',
            'services.telegram.chat_id' => '-100123456',
        ]);
    }

    private function makeLeaveRequest(array $overrides = []): LeaveRequest
    {
        $employee = User::factory()->create(['phone' => '081234567890']);

        return LeaveRequest::create(array_merge([
            'request_number' => 'IZN-2026-000001',
            'user_id' => $employee->id,
            'type' => 'temporary_exit',
            'leave_date' => today(),
            'start_time' => '10:00',
            'estimated_arrival' => '11:00',
            'status' => 'pending',
        ], $overrides));
    }

    public function test_submitting_a_leave_request_sends_a_telegram_notification()
    {
        Http::fake(['api.telegram.org/*' => Http::response(['ok' => true], 200)]);

        $employee = User::factory()->create();
        $this->actingAs($employee)->post(route('izin.store'), [
            'type' => 'temporary_exit',
            'leave_date' => today()->toDateString(),
            'start_time' => '10:00',
            'estimated_arrival' => '11:00',
        ]);

        $leaveRequest = LeaveRequest::first();

        Http::assertSent(function (HttpRequest $request) use ($leaveRequest) {
            return str_contains($request->url(), 'api.telegram.org/bottest-bot-token/sendMessage')
                && $request['chat_id'] === '-100123456'
                && str_contains($request['text'], 'PENGAJUAN IZIN BARU')
                && str_contains($request['text'], $leaveRequest->request_number)
                && str_contains($request['text'], 'MENUNGGU PERSETUJUAN HRD');
        });

        $this->assertSame('sent', $leaveRequest->fresh()->telegram_status);
    }

    public function test_hrd_approve_sends_pending_manager_telegram_notification()
    {
        Http::fake(['api.telegram.org/*' => Http::response(['ok' => true], 200)]);

        $hrd = User::factory()->create(['role' => 'hrd']);
        $leaveRequest = $this->makeLeaveRequest();

        $this->actingAs($hrd)->patch(route('hrd.izin.approve', $leaveRequest), []);

        Http::assertSent(fn (HttpRequest $request) => str_contains($request['text'], 'MENUNGGU PERSETUJUAN MANAGER'));
        $this->assertSame('sent', $leaveRequest->fresh()->telegram_status);
    }

    public function test_hrd_reject_sends_telegram_and_email_with_hrd_as_rejector()
    {
        Http::fake(['api.telegram.org/*' => Http::response(['ok' => true], 200)]);
        Mail::fake();

        $hrd = User::factory()->create(['role' => 'hrd', 'name' => 'Tim HRD']);
        $leaveRequest = $this->makeLeaveRequest();

        $this->actingAs($hrd)->patch(route('hrd.izin.reject', $leaveRequest), ['note' => 'Dokumen kurang.']);

        Http::assertSent(function (HttpRequest $request) {
            return str_contains($request['text'], 'DITOLAK')
                && str_contains($request['text'], 'Tim HRD (HRD)')
                && str_contains($request['text'], 'Dokumen kurang.');
        });

        Mail::assertSent(LeaveRequestDecisionMail::class);
        $this->assertSame('sent', $leaveRequest->fresh()->telegram_status);
        $this->assertSame('sent', $leaveRequest->fresh()->email_status);
    }

    public function test_manager_final_approval_sends_telegram_with_whatsapp_button_and_email()
    {
        Http::fake(['api.telegram.org/*' => Http::response(['ok' => true], 200)]);
        Mail::fake();

        $manager = User::factory()->create(['role' => 'admin']);
        $leaveRequest = $this->makeLeaveRequest([
            'status' => 'pending_manager',
            'hrd_processed_by' => User::factory()->create(['role' => 'hrd'])->id,
        ]);

        $this->actingAs($manager)->patch(route('manager.izin.approve', $leaveRequest), []);

        Http::assertSent(function (HttpRequest $request) {
            $replyMarkup = json_decode((string) $request['reply_markup'], true);
            $url = $replyMarkup['inline_keyboard'][0][0]['url'] ?? '';

            return str_contains($request['text'], 'DISETUJUI')
                && str_starts_with($url, 'https://wa.me/6281234567890?text=');
        });

        Mail::assertSent(LeaveRequestDecisionMail::class);
        $this->assertSame('sent', $leaveRequest->fresh()->email_status);
    }

    public function test_manager_reject_shows_manager_as_rejector()
    {
        Http::fake(['api.telegram.org/*' => Http::response(['ok' => true], 200)]);
        Mail::fake();

        $manager = User::factory()->create(['role' => 'admin', 'name' => 'Pak Manager']);
        $leaveRequest = $this->makeLeaveRequest([
            'status' => 'pending_manager',
            'hrd_processed_by' => User::factory()->create(['role' => 'hrd'])->id,
        ]);

        $this->actingAs($manager)->patch(route('manager.izin.reject', $leaveRequest), ['note' => 'Tidak disetujui.']);

        Http::assertSent(fn (HttpRequest $request) => str_contains($request['text'], 'Pak Manager (Manager)'));
    }

    public function test_telegram_failure_does_not_break_the_approval_or_the_database_write()
    {
        Http::fake(['api.telegram.org/*' => Http::response('Bad Request', 400)]);

        $hrd = User::factory()->create(['role' => 'hrd']);
        $leaveRequest = $this->makeLeaveRequest();

        $response = $this->actingAs($hrd)->patch(route('hrd.izin.approve', $leaveRequest), []);

        $response->assertSessionDoesntHaveErrors()->assertRedirect();

        $fresh = $leaveRequest->fresh();
        $this->assertSame('pending_manager', $fresh->status);
        $this->assertSame('failed', $fresh->telegram_status);
        $this->assertNotNull($fresh->telegram_error);
    }

    public function test_telegram_connection_exception_does_not_break_the_approval()
    {
        Http::fake(function () {
            throw new ConnectionException('Could not resolve host');
        });

        $hrd = User::factory()->create(['role' => 'hrd']);
        $leaveRequest = $this->makeLeaveRequest();

        $response = $this->actingAs($hrd)->patch(route('hrd.izin.approve', $leaveRequest), []);

        $response->assertSessionDoesntHaveErrors();

        $fresh = $leaveRequest->fresh();
        $this->assertSame('pending_manager', $fresh->status);
        $this->assertSame('failed', $fresh->telegram_status);
    }

    public function test_missing_telegram_config_fails_gracefully_without_an_http_call()
    {
        config(['services.telegram.bot_token' => null, 'services.telegram.chat_id' => null]);
        Http::fake();

        $hrd = User::factory()->create(['role' => 'hrd']);
        $leaveRequest = $this->makeLeaveRequest();

        $response = $this->actingAs($hrd)->patch(route('hrd.izin.approve', $leaveRequest), []);

        $response->assertSessionDoesntHaveErrors();
        Http::assertNothingSent();

        $fresh = $leaveRequest->fresh();
        $this->assertSame('pending_manager', $fresh->status);
        $this->assertSame('failed', $fresh->telegram_status);
    }

    public function test_email_failure_does_not_break_the_final_decision()
    {
        Http::fake(['api.telegram.org/*' => Http::response(['ok' => true], 200)]);

        $pendingMail = Mockery::mock(PendingMail::class);
        $pendingMail->shouldReceive('send')->andThrow(new \RuntimeException('SMTP tidak tersedia.'));
        Mail::shouldReceive('to')->andReturn($pendingMail);

        $manager = User::factory()->create(['role' => 'admin']);
        $leaveRequest = $this->makeLeaveRequest([
            'status' => 'pending_manager',
            'hrd_processed_by' => User::factory()->create(['role' => 'hrd'])->id,
        ]);

        $response = $this->actingAs($manager)->patch(route('manager.izin.approve', $leaveRequest), []);

        $response->assertSessionDoesntHaveErrors();

        $fresh = $leaveRequest->fresh();
        $this->assertSame('approved', $fresh->status);
        $this->assertSame('failed', $fresh->email_status);
        $this->assertSame('SMTP tidak tersedia.', $fresh->email_error);
    }

    public function test_malicious_input_is_escaped_in_the_telegram_message()
    {
        Http::fake(['api.telegram.org/*' => Http::response(['ok' => true], 200)]);
        Mail::fake();

        $hrd = User::factory()->create(['role' => 'hrd']);
        $leaveRequest = $this->makeLeaveRequest();

        $this->actingAs($hrd)->patch(route('hrd.izin.reject', $leaveRequest), [
            'note' => '<script>alert(1)</script>',
        ]);

        Http::assertSent(function (HttpRequest $request) {
            return ! str_contains($request['text'], '<script>')
                && str_contains($request['text'], '&lt;script&gt;');
        });
    }
}
