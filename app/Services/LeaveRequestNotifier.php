<?php

namespace App\Services;

use App\Mail\LeaveRequestDecisionMail;
use App\Models\LeaveRequest;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Throwable;

/**
 * Sends the Telegram/email notifications for a leave request's
 * lifecycle events. Every send is wrapped so a failure here can never
 * break the approve/reject flow that triggered it — only the
 * telegram_status/email_status tracking columns reflect what happened.
 *
 * Message wording/structure is pinned to TEMPLATE-PESAN-TELEGRAM.md at
 * the repo root — match it exactly on any change, not just "close enough".
 */
class LeaveRequestNotifier
{
    public function notifySubmitted(LeaveRequest $leaveRequest): void
    {
        $this->sendTelegram(
            $leaveRequest,
            $this->renderMessage(
                $leaveRequest,
                '🔔 PENGAJUAN IZIN BARU',
                [],
                '🔵 MENUNGGU PERSETUJUAN HRD',
                [
                    'HRD meninjau terlebih dahulu. Setelah disetujui HRD, Manager memberikan keputusan final.',
                    'Login Web: '.$this->escape($this->loginUrl()),
                ],
            ),
            $this->loginButton(),
        );
    }

    public function notifyHrdApproved(LeaveRequest $leaveRequest): void
    {
        $this->sendTelegram(
            $leaveRequest,
            $this->renderMessage(
                $leaveRequest,
                '🟡 DISETUJUI HRD — MENUNGGU MANAGER',
                ['Disetujui HRD: '.$this->escape($leaveRequest->hrdProcessedBy->name)],
                '🟡 MENUNGGU PERSETUJUAN MANAGER',
                [
                    'Mohon Manager untuk meninjau dan memberikan keputusan final.',
                    'Login Web: '.$this->escape($this->loginUrl()),
                ],
            ),
            $this->loginButton(),
        );
    }

    public function notifyFinalApproved(LeaveRequest $leaveRequest): void
    {
        $this->sendTelegram(
            $leaveRequest,
            $this->renderMessage(
                $leaveRequest,
                '🟢 PENGAJUAN IZIN DISETUJUI (FINAL)',
                [
                    'Disetujui HRD: '.$this->escape($leaveRequest->hrdProcessedBy->name),
                    'Disetujui Manager: '.$this->escape($leaveRequest->processedBy->name),
                ],
                '🟢 DISETUJUI',
                ['Silakan teruskan konfirmasi ke karyawan lewat tombol WhatsApp di bawah ini.'],
            ),
            $this->whatsAppButton($leaveRequest, 'MANAGER', now()),
        );

        $this->sendEmail($leaveRequest);
    }

    public function notifyRejected(LeaveRequest $leaveRequest, string $rejectedByStage): void
    {
        // notifyRejected() is only ever called right after the relevant
        // FK (hrd_processed_by for an HRD rejection, processed_by for a
        // Manager one) was just written in the same request, so the
        // relation is guaranteed loaded and non-null here.
        $rejector = $rejectedByStage === 'hrd'
            ? $leaveRequest->hrdProcessedBy
            : $leaveRequest->processedBy;
        $rejectorRole = $rejectedByStage === 'hrd' ? 'HRD' : 'MANAGER';

        $this->sendTelegram(
            $leaveRequest,
            $this->renderMessage(
                $leaveRequest,
                '🔴 PENGAJUAN IZIN DITOLAK',
                [
                    'Ditolak oleh: '.$rejectorRole.' ('.$this->escape($rejector->name).')',
                    'Alasan Penolakan: '.$this->escape($leaveRequest->rejection_reason ?: 'Tidak memenuhi ketentuan kebijakan kantor.'),
                ],
                '🔴 DITOLAK',
                ['Silakan teruskan informasi penolakan ke karyawan lewat tombol WhatsApp di bawah ini.'],
            ),
            $this->whatsAppButton($leaveRequest, $rejectorRole, now()),
        );

        $this->sendEmail($leaveRequest);
    }

    /**
     * @param  array<int, string>  $extraLines  Inserted between "Alasan: ..." and "Status: ...".
     * @param  array<int, string>  $trailingLines  Inserted after the blank line following "Status: ...".
     */
    private function renderMessage(LeaveRequest $leaveRequest, string $title, array $extraLines, string $statusLine, array $trailingLines): string
    {
        $user = $leaveRequest->user;

        $lines = [
            $this->escape($title),
            '',
            'No. Pengajuan: '.$this->escape($leaveRequest->request_number),
            'Nama: '.$this->escape($user->name),
            'Departemen: '.$this->escape($user->company->name ?? '-'),
            'Jabatan: '.$this->escape($user->position->name ?? '-'),
            'Jenis Izin: '.$this->escape($leaveRequest->typeLabel()),
            'Tanggal Izin: '.$leaveRequest->leave_date->format('d/m/Y'),
            'Alasan: '.$this->escape($leaveRequest->reason),
            ...$extraLines,
            'Status: '.$this->escape($statusLine),
            '',
            ...$trailingLines,
        ];

        return implode("\n", $lines);
    }

    /**
     * A short line describing the relevant date/time detail for this
     * type — used only in the WhatsApp message, where there is no room
     * for per-field labels like the Telegram message has.
     */
    private function tanggalInfo(LeaveRequest $leaveRequest): string
    {
        $date = $leaveRequest->leave_date->format('d/m/Y');

        return match ($leaveRequest->type) {
            'late' => "{$date}, estimasi tiba ".$this->shortTime($leaveRequest->estimated_arrival),
            'half_day' => "{$date}, ".$this->shortTime($leaveRequest->start_time).'–'.$this->shortTime($leaveRequest->end_time),
            'temporary_exit' => "{$date}, keluar ".$this->shortTime($leaveRequest->start_time).', estimasi kembali '.$this->shortTime($leaveRequest->estimated_arrival),
            'leave', 'sick' => "{$date}, ".rtrim(rtrim((string) $leaveRequest->duration, '0'), '.').' hari',
            default => $date,
        };
    }

    private function shortTime(?string $time): string
    {
        return $time ? substr($time, 0, 5) : '-';
    }

    /**
     * @return array<string, mixed>
     */
    private function whatsAppButton(LeaveRequest $leaveRequest, string $processedByRole, \DateTimeInterface $processedAt): array
    {
        $phone = $this->normalizePhoneForWhatsApp($leaveRequest->user->phone);
        $decided = $leaveRequest->status === 'approved';

        $lines = [
            'Halo '.$leaveRequest->user->name.',',
            '',
            $decided
                ? 'Pengajuan izin Anda telah DISETUJUI.'
                : 'Pengajuan izin Anda telah DITOLAK.',
            '',
            'Nomor Pengajuan: '.$leaveRequest->request_number,
            'Jenis Izin: '.$leaveRequest->typeLabel(),
            'Tanggal: '.$this->tanggalInfo($leaveRequest),
            'Status: '.($decided ? 'DISETUJUI' : 'DITOLAK'),
        ];

        if ($decided) {
            $lines[] = 'Diproses oleh: '.$processedByRole;
            $lines[] = 'Waktu proses: '.$processedAt->format('d/m/Y H:i');
            $lines[] = '';
            $lines[] = 'Silakan simpan pesan ini sebagai informasi pengajuan izin Anda.';
        } else {
            $lines[] = '';
            $lines[] = 'Alasan penolakan:';
            $lines[] = $leaveRequest->rejection_reason ?: 'Tidak memenuhi ketentuan kebijakan kantor.';
            $lines[] = '';
            $lines[] = 'Diproses oleh: '.$processedByRole;
            $lines[] = 'Waktu proses: '.$processedAt->format('d/m/Y H:i');
            $lines[] = '';
            $lines[] = 'Silakan hubungi HRD apabila memerlukan informasi lebih lanjut.';
        }

        $text = urlencode(implode("\n", $lines));

        return [
            'inline_keyboard' => [[
                ['text' => 'Kirim ke WhatsApp Karyawan', 'url' => "https://wa.me/{$phone}?text={$text}"],
            ]],
        ];
    }

    private function loginUrl(): string
    {
        return config('services.telegram.login_url') ?: route('login');
    }

    /**
     * "Login Web Izin" button for the submitted/HRD-approved notifications.
     * Telegram rejects http:// button URLs outright, so a non-https login
     * URL just drops the button — the "Login Web: ..." text line (always
     * sent, see renderMessage() callers) still carries the URL either way.
     *
     * @return array<string, mixed>|null
     */
    private function loginButton(): ?array
    {
        $url = $this->loginUrl();

        if (! str_starts_with($url, 'https://')) {
            return null;
        }

        return [
            'inline_keyboard' => [[
                ['text' => 'Login Web Izin', 'url' => $url],
            ]],
        ];
    }

    private function normalizePhoneForWhatsApp(?string $phone): string
    {
        $digits = preg_replace('/\D+/', '', $phone ?? '') ?? '';

        if (str_starts_with($digits, '0')) {
            $digits = '62'.substr($digits, 1);
        }

        return $digits;
    }

    private function escape(?string $value): string
    {
        return htmlspecialchars($value ?? '-', ENT_QUOTES);
    }

    /**
     * @param  array<string, mixed>|null  $replyMarkup
     */
    private function sendTelegram(LeaveRequest $leaveRequest, string $text, ?array $replyMarkup = null): void
    {
        $botToken = config('services.telegram.bot_token');
        // Each company can route its notifications to its own Telegram
        // group (companies.telegram_chat_id) — falls back to the global
        // TELEGRAM_CHAT_ID env value for a company that hasn't set one yet.
        $chatId = $leaveRequest->user->company->telegram_chat_id
            ?? config('services.telegram.chat_id');

        if (! $botToken || ! $chatId) {
            $leaveRequest->forceFill([
                'telegram_status' => 'failed',
                'telegram_error' => 'Telegram belum dikonfigurasi (TELEGRAM_BOT_TOKEN/TELEGRAM_CHAT_ID kosong).',
            ])->save();

            return;
        }

        try {
            $payload = [
                'chat_id' => $chatId,
                'text' => $text,
                'parse_mode' => 'HTML',
            ];

            if ($replyMarkup !== null) {
                $payload['reply_markup'] = json_encode($replyMarkup);
            }

            $response = Http::timeout(5)
                ->asForm()
                ->post("https://api.telegram.org/bot{$botToken}/sendMessage", $payload);

            if ($response->successful()) {
                $leaveRequest->forceFill([
                    'telegram_status' => 'sent',
                    'telegram_sent_at' => now(),
                    'telegram_error' => null,
                ])->save();
            } else {
                $leaveRequest->forceFill([
                    'telegram_status' => 'failed',
                    'telegram_error' => $response->body(),
                ])->save();
            }
        } catch (Throwable $e) {
            Log::warning('Gagal mengirim notifikasi Telegram izin.', [
                'leave_request_id' => $leaveRequest->id,
                'error' => $e->getMessage(),
            ]);

            $leaveRequest->forceFill([
                'telegram_status' => 'failed',
                'telegram_error' => $e->getMessage(),
            ])->save();
        }
    }

    private function sendEmail(LeaveRequest $leaveRequest): void
    {
        try {
            Mail::to($leaveRequest->user->email)->send(new LeaveRequestDecisionMail($leaveRequest));

            $leaveRequest->forceFill([
                'email_status' => 'sent',
                'email_sent_at' => now(),
                'email_error' => null,
            ])->save();
        } catch (Throwable $e) {
            Log::warning('Gagal mengirim email keputusan izin.', [
                'leave_request_id' => $leaveRequest->id,
                'error' => $e->getMessage(),
            ]);

            $leaveRequest->forceFill([
                'email_status' => 'failed',
                'email_error' => $e->getMessage(),
            ])->save();
        }
    }
}
