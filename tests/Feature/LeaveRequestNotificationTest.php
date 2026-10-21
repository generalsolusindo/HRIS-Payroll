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

    private function makeLeaveRequest(array $overrides = [], ?int $companyId = null): LeaveRequest
    {
        $employee = User::factory()->create(array_filter([
            'phone' => '081234567890',
            'company_id' => $companyId,
        ], fn ($value) => $value !== null));

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

    public function test_each_companys_notifications_route_to_its_own_telegram_chat()
    {
        Http::fake(['api.telegram.org/*' => Http::response(['ok' => true], 200)]);

        $company = \App\Models\Company::factory()->create(['telegram_chat_id' => '-100999888']);
        $leaveRequest = $this->makeLeaveRequest(companyId: $company->id);
        $this->app->make(\App\Services\LeaveRequestNotifier::class)
            ->notifySubmitted($leaveRequest->load(['user.company', 'user.division']));

        Http::assertSent(fn (HttpRequest $request) => $request['chat_id'] === '-100999888');
    }

    public function test_a_company_without_its_own_chat_id_falls_back_to_the_global_default()
    {
        Http::fake(['api.telegram.org/*' => Http::response(['ok' => true], 200)]);

        $company = \App\Models\Company::factory()->create(['telegram_chat_id' => null]);
        $leaveRequest = $this->makeLeaveRequest(companyId: $company->id);
        $this->app->make(\App\Services\LeaveRequestNotifier::class)
            ->notifySubmitted($leaveRequest->load(['user.company', 'user.division']));

        Http::assertSent(fn (HttpRequest $request) => $request['chat_id'] === '-100123456');
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
            'reason' => 'Urusan bank.',
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

    /**
     * Locked to TEMPLATE-PESAN-TELEGRAM.md's "Kondisi 1" template exactly
     * — field order, wording, and the "Login Web: ..." text line (not
     * just the button) all matter here, not just a substring match.
     */
    public function test_submitted_notification_text_matches_template_exactly()
    {
        config(['services.telegram.login_url' => 'https://izin.example.com/login']);
        Http::fake(['api.telegram.org/*' => Http::response(['ok' => true], 200)]);

        $employee = User::factory()->create(['name' => 'Budi Santoso']);
        $employee->load('company', 'division');

        $this->actingAs($employee)->post(route('izin.store'), [
            'type' => 'temporary_exit',
            'leave_date' => today()->toDateString(),
            'start_time' => '10:00',
            'estimated_arrival' => '11:00',
            'reason' => 'Urusan bank.',
        ]);

        $leaveRequest = LeaveRequest::first();

        $expected = implode("\n", [
            '🔔 PENGAJUAN IZIN BARU',
            '',
            'No. Pengajuan: '.$leaveRequest->request_number,
            'Nama: Budi Santoso',
            'Departemen: '.$employee->company->name,
            'Jabatan: '.$employee->division->name,
            'Jenis Izin: Izin Keluar Kantor Sebentar',
            'Tanggal Izin: '.today()->format('d/m/Y'),
            'Alasan: Urusan bank.',
            'Status: 🔵 MENUNGGU PERSETUJUAN HRD',
            '',
            'HRD meninjau terlebih dahulu. Setelah disetujui HRD, Manager memberikan keputusan final.',
            'Login Web: https://izin.example.com/login',
        ]);

        Http::assertSent(fn (HttpRequest $request) => $request['text'] === $expected);
    }

    public function test_submitted_notification_includes_a_login_button_when_https_login_url_is_set()
    {
        config(['services.telegram.login_url' => 'https://izin.example.com/login']);
        Http::fake(['api.telegram.org/*' => Http::response(['ok' => true], 200)]);

        $employee = User::factory()->create();
        $this->actingAs($employee)->post(route('izin.store'), [
            'type' => 'temporary_exit',
            'leave_date' => today()->toDateString(),
            'start_time' => '10:00',
            'estimated_arrival' => '11:00',
            'reason' => 'Urusan bank.',
        ]);

        Http::assertSent(function (HttpRequest $request) {
            $replyMarkup = json_decode((string) $request['reply_markup'], true);
            $button = $replyMarkup['inline_keyboard'][0][0] ?? [];

            return ($button['text'] ?? null) === 'Login Web Izin'
                && ($button['url'] ?? null) === 'https://izin.example.com/login';
        });
    }

    public function test_submitted_notification_has_no_button_when_login_url_is_not_https()
    {
        config(['services.telegram.login_url' => 'http://izin.example.com/login']);
        Http::fake(['api.telegram.org/*' => Http::response(['ok' => true], 200)]);

        $employee = User::factory()->create();
        $this->actingAs($employee)->post(route('izin.store'), [
            'type' => 'temporary_exit',
            'leave_date' => today()->toDateString(),
            'start_time' => '10:00',
            'estimated_arrival' => '11:00',
            'reason' => 'Urusan bank.',
        ]);

        Http::assertSent(fn (HttpRequest $request) => ! array_key_exists('reply_markup', $request->data()));
        $this->assertSame('sent', LeaveRequest::first()->telegram_status);
    }

    public function test_notification_includes_the_original_submission_reason()
    {
        Http::fake(['api.telegram.org/*' => Http::response(['ok' => true], 200)]);

        $employee = User::factory()->create();
        $this->actingAs($employee)->post(route('izin.store'), [
            'type' => 'temporary_exit',
            'leave_date' => today()->toDateString(),
            'start_time' => '10:00',
            'estimated_arrival' => '11:00',
            'reason' => 'Urusan bank penting.',
        ]);

        Http::assertSent(fn (HttpRequest $request) => str_contains($request['text'], 'Alasan: Urusan bank penting.'));
    }

    public function test_hrd_approve_sends_pending_manager_telegram_notification()
    {
        Http::fake(['api.telegram.org/*' => Http::response(['ok' => true], 200)]);

        $hrd = User::factory()->create(['role' => 'hrd']);
        $leaveRequest = $this->makeLeaveRequest([], $hrd->company_id);

        $this->actingAs($hrd)->patch(route('hrd.izin.approve', $leaveRequest), []);

        Http::assertSent(fn (HttpRequest $request) => str_contains($request['text'], 'MENUNGGU PERSETUJUAN MANAGER'));
        $this->assertSame('sent', $leaveRequest->fresh()->telegram_status);
    }

    public function test_hrd_approve_note_is_included_in_the_telegram_message()
    {
        Http::fake(['api.telegram.org/*' => Http::response(['ok' => true], 200)]);

        $hrd = User::factory()->create(['role' => 'hrd']);
        $leaveRequest = $this->makeLeaveRequest([], $hrd->company_id);

        $this->actingAs($hrd)->patch(route('hrd.izin.approve', $leaveRequest), [
            'note' => 'Sudah dikonfirmasi lewat telepon.',
        ]);

        Http::assertSent(fn (HttpRequest $request) => str_contains(
            $request['text'],
            'Catatan HRD: Sudah dikonfirmasi lewat telepon.',
        ));
    }

    public function test_hrd_approve_without_a_note_omits_the_catatan_line_entirely()
    {
        Http::fake(['api.telegram.org/*' => Http::response(['ok' => true], 200)]);

        $hrd = User::factory()->create(['role' => 'hrd']);
        $leaveRequest = $this->makeLeaveRequest([], $hrd->company_id);

        $this->actingAs($hrd)->patch(route('hrd.izin.approve', $leaveRequest), []);

        Http::assertSent(fn (HttpRequest $request) => ! str_contains($request['text'], 'Catatan HRD'));
    }

    public function test_hrd_approve_note_carries_through_to_the_final_approval_message()
    {
        Http::fake(['api.telegram.org/*' => Http::response(['ok' => true], 200)]);
        Mail::fake();

        $manager = User::factory()->create(['role' => 'admin']);
        $hrd = User::factory()->create(['role' => 'hrd', 'company_id' => $manager->company_id]);
        $leaveRequest = $this->makeLeaveRequest([
            'status' => 'pending_manager',
            'hrd_processed_by' => $hrd->id,
            'hrd_note' => 'Sudah dikonfirmasi lewat telepon.',
        ], $manager->company_id);

        $this->actingAs($manager)->patch(route('manager.izin.approve', $leaveRequest), []);

        Http::assertSent(fn (HttpRequest $request) => str_contains(
            $request['text'],
            'Catatan HRD: Sudah dikonfirmasi lewat telepon.',
        ));
    }

    public function test_hrd_approve_note_is_escaped_in_the_telegram_message()
    {
        Http::fake(['api.telegram.org/*' => Http::response(['ok' => true], 200)]);

        $hrd = User::factory()->create(['role' => 'hrd']);
        $leaveRequest = $this->makeLeaveRequest([], $hrd->company_id);

        $this->actingAs($hrd)->patch(route('hrd.izin.approve', $leaveRequest), [
            'note' => '<script>alert(1)</script>',
        ]);

        Http::assertSent(function (HttpRequest $request) {
            return ! str_contains($request['text'], '<script>')
                && str_contains($request['text'], 'Catatan HRD: &lt;script&gt;alert(1)&lt;/script&gt;');
        });
    }

    public function test_hrd_reject_sends_telegram_and_email_with_hrd_as_rejector()
    {
        Http::fake(['api.telegram.org/*' => Http::response(['ok' => true], 200)]);
        Mail::fake();

        $hrd = User::factory()->create(['role' => 'hrd', 'name' => 'Tim HRD']);
        $leaveRequest = $this->makeLeaveRequest([], $hrd->company_id);

        $this->actingAs($hrd)->patch(route('hrd.izin.reject', $leaveRequest), ['note' => 'Dokumen kurang.']);

        Http::assertSent(function (HttpRequest $request) {
            return str_contains($request['text'], 'DITOLAK')
                && str_contains($request['text'], 'Ditolak oleh: HRD (Tim HRD)')
                && str_contains($request['text'], 'Alasan Penolakan: Dokumen kurang.');
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
            'hrd_processed_by' => User::factory()->create(['role' => 'hrd', 'company_id' => $manager->company_id])->id,
        ], $manager->company_id);

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

    /**
     * Locked to TEMPLATE-PESAN-TELEGRAM.md's "Bonus" WhatsApp-approved
     * template — the detailed multi-line body, not the old one-liner.
     */
    public function test_approval_whatsapp_message_matches_template_structure()
    {
        Http::fake(['api.telegram.org/*' => Http::response(['ok' => true], 200)]);
        Mail::fake();

        $this->travelTo(today()->setTime(14, 30));

        $manager = User::factory()->create(['role' => 'admin']);
        $hrd = User::factory()->create(['role' => 'hrd', 'company_id' => $manager->company_id]);
        $employee = User::factory()->create([
            'company_id' => $manager->company_id,
            'name' => 'Budi Santoso',
            'phone' => '081234567890',
        ]);
        $leaveRequest = LeaveRequest::create([
            'request_number' => 'IZN-2026-000099',
            'user_id' => $employee->id,
            'type' => 'leave',
            'leave_date' => today()->addDays(7),
            'duration' => 3,
            'status' => 'pending_manager',
            'hrd_processed_by' => $hrd->id,
        ]);

        $this->actingAs($manager)->patch(route('manager.izin.approve', $leaveRequest), []);

        $expected = implode("\n", [
            'Halo Budi Santoso,',
            '',
            'Pengajuan izin Anda telah DISETUJUI.',
            '',
            'Nomor Pengajuan: IZN-2026-000099',
            'Jenis Izin: Cuti',
            'Tanggal: '.today()->addDays(7)->format('d/m/Y').', 3 hari',
            'Status: DISETUJUI',
            'Diproses oleh: MANAGER',
            'Waktu proses: '.now()->format('d/m/Y H:i'),
            '',
            'Silakan simpan pesan ini sebagai informasi pengajuan izin Anda.',
        ]);

        Http::assertSent(function (HttpRequest $request) use ($expected) {
            $replyMarkup = json_decode((string) $request['reply_markup'], true);
            $url = $replyMarkup['inline_keyboard'][0][0]['url'] ?? '';
            $prefix = 'https://wa.me/6281234567890?text=';

            if (! str_starts_with($url, $prefix)) {
                return false;
            }

            return urldecode(substr($url, strlen($prefix))) === $expected;
        });
    }

    /**
     * Locked to TEMPLATE-PESAN-TELEGRAM.md's "Bonus" WhatsApp-rejected
     * template.
     */
    public function test_rejection_whatsapp_message_matches_template_structure()
    {
        Http::fake(['api.telegram.org/*' => Http::response(['ok' => true], 200)]);
        Mail::fake();

        $this->travelTo(today()->setTime(9, 15));

        $hrd = User::factory()->create(['role' => 'hrd', 'name' => 'Tim HRD']);
        $employee = User::factory()->create([
            'company_id' => $hrd->company_id,
            'name' => 'Siti Aminah',
            'phone' => '081234567890',
        ]);
        $leaveRequest = LeaveRequest::create([
            'request_number' => 'IZN-2026-000098',
            'user_id' => $employee->id,
            'type' => 'temporary_exit',
            'leave_date' => today(),
            'start_time' => '10:00',
            'estimated_arrival' => '11:00',
            'status' => 'pending',
        ]);

        $this->actingAs($hrd)->patch(route('hrd.izin.reject', $leaveRequest), ['note' => 'Kuota cuti tim sedang penuh.']);

        $expected = implode("\n", [
            'Halo Siti Aminah,',
            '',
            'Pengajuan izin Anda telah DITOLAK.',
            '',
            'Nomor Pengajuan: IZN-2026-000098',
            'Jenis Izin: Izin Keluar Kantor Sebentar',
            'Tanggal: '.today()->format('d/m/Y').', keluar 10:00, estimasi kembali 11:00',
            'Status: DITOLAK',
            '',
            'Alasan penolakan:',
            'Kuota cuti tim sedang penuh.',
            '',
            'Diproses oleh: HRD',
            'Waktu proses: '.now()->format('d/m/Y H:i'),
            '',
            'Silakan hubungi HRD apabila memerlukan informasi lebih lanjut.',
        ]);

        Http::assertSent(function (HttpRequest $request) use ($expected) {
            $replyMarkup = json_decode((string) $request['reply_markup'], true);
            $url = $replyMarkup['inline_keyboard'][0][0]['url'] ?? '';
            $prefix = 'https://wa.me/6281234567890?text=';

            if (! str_starts_with($url, $prefix)) {
                return false;
            }

            return urldecode(substr($url, strlen($prefix))) === $expected;
        });
    }

    /**
     * TEMPLATE-PESAN-TELEGRAM.md puts "Ditolak oleh" / "Alasan Penolakan"
     * as fields before "Status:", not as a trailing note after it.
     */
    public function test_rejected_telegram_message_field_order_matches_template()
    {
        Http::fake(['api.telegram.org/*' => Http::response(['ok' => true], 200)]);
        Mail::fake();

        $hrd = User::factory()->create(['role' => 'hrd', 'name' => 'Tim HRD']);
        $leaveRequest = $this->makeLeaveRequest([], $hrd->company_id);

        $this->actingAs($hrd)->patch(route('hrd.izin.reject', $leaveRequest), ['note' => 'Dokumen kurang.']);

        Http::assertSent(function (HttpRequest $request) {
            $alasanPos = strpos($request['text'], 'Alasan: ');
            $ditolakPos = strpos($request['text'], 'Ditolak oleh:');
            $alasanPenolakanPos = strpos($request['text'], 'Alasan Penolakan:');
            $statusPos = strpos($request['text'], 'Status: 🔴 DITOLAK');

            return $alasanPos !== false && $ditolakPos !== false
                && $alasanPenolakanPos !== false && $statusPos !== false
                && $alasanPos < $ditolakPos
                && $ditolakPos < $alasanPenolakanPos
                && $alasanPenolakanPos < $statusPos;
        });
    }

    public function test_reject_notification_title_has_no_final_suffix()
    {
        Http::fake(['api.telegram.org/*' => Http::response(['ok' => true], 200)]);
        Mail::fake();

        $hrd = User::factory()->create(['role' => 'hrd']);
        $leaveRequest = $this->makeLeaveRequest([], $hrd->company_id);

        $this->actingAs($hrd)->patch(route('hrd.izin.reject', $leaveRequest), ['note' => 'Dokumen kurang.']);

        Http::assertSent(function (HttpRequest $request) {
            return str_contains($request['text'], 'PENGAJUAN IZIN DITOLAK')
                && ! str_contains($request['text'], 'PENGAJUAN IZIN DITOLAK (FINAL)')
                && str_contains($request['text'], 'Alasan Penolakan: Dokumen kurang.');
        });
    }

    public function test_manager_reject_shows_manager_as_rejector()
    {
        Http::fake(['api.telegram.org/*' => Http::response(['ok' => true], 200)]);
        Mail::fake();

        $manager = User::factory()->create(['role' => 'admin', 'name' => 'Pak Manager']);
        $leaveRequest = $this->makeLeaveRequest([
            'status' => 'pending_manager',
            'hrd_processed_by' => User::factory()->create(['role' => 'hrd', 'company_id' => $manager->company_id])->id,
        ], $manager->company_id);

        $this->actingAs($manager)->patch(route('manager.izin.reject', $leaveRequest), ['note' => 'Tidak disetujui.']);

        Http::assertSent(fn (HttpRequest $request) => str_contains($request['text'], 'Ditolak oleh: MANAGER (Pak Manager)'));
    }

    public function test_telegram_failure_does_not_break_the_approval_or_the_database_write()
    {
        Http::fake(['api.telegram.org/*' => Http::response('Bad Request', 400)]);

        $hrd = User::factory()->create(['role' => 'hrd']);
        $leaveRequest = $this->makeLeaveRequest([], $hrd->company_id);

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
        $leaveRequest = $this->makeLeaveRequest([], $hrd->company_id);

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
        $leaveRequest = $this->makeLeaveRequest([], $hrd->company_id);

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
            'hrd_processed_by' => User::factory()->create(['role' => 'hrd', 'company_id' => $manager->company_id])->id,
        ], $manager->company_id);

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
        $leaveRequest = $this->makeLeaveRequest([], $hrd->company_id);

        $this->actingAs($hrd)->patch(route('hrd.izin.reject', $leaveRequest), [
            'note' => '<script>alert(1)</script>',
        ]);

        Http::assertSent(function (HttpRequest $request) {
            return ! str_contains($request['text'], '<script>')
                && str_contains($request['text'], '&lt;script&gt;');
        });
    }
}
