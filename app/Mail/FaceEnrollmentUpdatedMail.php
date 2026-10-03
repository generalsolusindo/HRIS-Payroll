<?php

namespace App\Mail;

use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class FaceEnrollmentUpdatedMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public User $user,
        public bool $reEnrolled,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: $this->reEnrolled
                ? 'Verifikasi Wajah Anda Diperbarui'
                : 'Verifikasi Wajah Berhasil Didaftarkan',
        );
    }

    public function content(): Content
    {
        return new Content(markdown: 'mail.face-enrollment-updated');
    }
}
