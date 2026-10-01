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
 */
class LeaveRequestNotifier
{
    public function notifySubmitted(LeaveRequest $leaveRequest): void
    {
        $this->sendTelegram($leaveRequest, $this->renderMessage(
            $leaveRequest,
            '🔔 PENGAJUAN IZIN BARU',
            '🔵 MENUNGGU PERSETUJUAN HRD',
            'HRD meninjau terlebih dahulu. Setelah disetujui HRD, Manager memberikan keputusan final.',
        ));
    }

    public function notifyHrdApproved(LeaveRequest $leaveRequest): void
    {
        $this->sendTelegram($leaveRequest, $this->renderMessage(
            $leaveRequest,
            '🟡 DISETUJUI HRD — MENUNGGU MANAGER',
            '🟡 MENUNGGU PERSETUJUAN MANAGER',
            'Mohon Manager untuk meninjau pengajuan ini.',
        ));
    }

    public function notifyFinalApproved(LeaveRequest $leaveRequest): void
    {
        $this->sendTelegram(
            $leaveRequest,
            $this->renderMessage(
                $leaveRequest,
                '🟢 PENGAJUAN IZIN DISETUJUI (FINAL)',
                '🟢 DISETUJUI',
                'Silakan teruskan konfirmasi ke karyawan lewat tombol WhatsApp di bawah ini.',
            ),
            $this->whatsAppButton($leaveRequest, 'Disetujui'),
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

        $note = sprintf(
            "Ditolak oleh: %s (%s)\nAlasan: %s\n\nSilakan teruskan konfirmasi ke karyawan lewat tombol WhatsApp di bawah ini.",
            $this->escape($rejector->name),
            $rejectedByStage === 'hrd' ? 'HRD' : 'Manager',
            $this->escape($leaveRequest->rejection_reason ?? '-'),
        );

        $this->sendTelegram(
            $leaveRequest,
            $this->renderMessage($leaveRequest, '🔴 PENGAJUAN IZIN DITOLAK (FINAL)', '🔴 DITOLAK', $note),
            $this->whatsAppButton($leaveRequest, 'Ditolak'),
        );

        $this->sendEmail($leaveRequest);
    }

    private function renderMessage(LeaveRequest $leaveRequest, string $title, string $statusLine, string $note): string
    {
        $user = $leaveRequest->user;

        $lines = [
            '<b>'.$this->escape($title).'</b>',
            '',
            'No. Pengajuan: '.$this->escape($leaveRequest->request_number),
            'Nama: '.$this->escape($user->name),
            'Departemen: '.$this->escape($user->company->name ?? '-'),
            'Jabatan: '.$this->escape($user->position->name ?? '-'),
            'Jenis Izin: '.$this->escape($leaveRequest->typeLabel()),
            'Tanggal Izin: '.$leaveRequest->leave_date->format('d/m/Y'),
        ];

        if ($leaveRequest->hrd_processed_by) {
            $lines[] = 'Disetujui HRD: '.$this->escape($leaveRequest->hrdProcessedBy->name);
        }

        if ($leaveRequest->status === 'approved' && $leaveRequest->processed_by) {
            $lines[] = 'Disetujui Manager: '.$this->escape($leaveRequest->processedBy->name);
        }

        $lines[] = 'Status: '.$this->escape($statusLine);
        $lines[] = '';
        $lines[] = $note;

        return implode("\n", $lines);
    }

    /**
     * @return array<string, mixed>
     */
    private function whatsAppButton(LeaveRequest $leaveRequest, string $decisionLabel): array
    {
        $phone = $this->normalizePhoneForWhatsApp($leaveRequest->user->phone);

        $text = urlencode(sprintf(
            'Halo %s, pengajuan izin Anda (%s) sudah diputuskan: %s.',
            $leaveRequest->user->name,
            $leaveRequest->request_number,
            $decisionLabel,
        ));

        return [
            'inline_keyboard' => [[
                ['text' => 'Kirim ke WhatsApp Karyawan', 'url' => "https://wa.me/{$phone}?text={$text}"],
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
        $chatId = config('services.telegram.chat_id');

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
