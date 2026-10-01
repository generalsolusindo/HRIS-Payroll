<?php

namespace App\Mail;

use App\Models\LeaveRequest;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class LeaveRequestDecisionMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public LeaveRequest $leaveRequest) {}

    public function envelope(): Envelope
    {
        $subject = $this->leaveRequest->status === 'approved'
            ? "Pengajuan Izin {$this->leaveRequest->request_number} Disetujui"
            : "Pengajuan Izin {$this->leaveRequest->request_number} Ditolak";

        return new Envelope(subject: $subject);
    }

    public function content(): Content
    {
        return new Content(markdown: 'mail.leave-request-decision');
    }
}
