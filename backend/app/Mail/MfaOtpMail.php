<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeEncrypted;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class MfaOtpMail extends Mailable implements ShouldBeEncrypted, ShouldQueue
{
    use Queueable, SerializesModels;

    public int $tries = 3;

    public array $backoff = [60, 300];

    public function __construct(
        public readonly string $email,
        public readonly string $otp,
        public readonly int $ttlMinutes,
    ) {
        $this->onQueue('emails');
        $this->afterCommit();
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Yemen Flow Hub — رمز التحقق'
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.system.mfa-otp',
            with: [
                'user_name' => $this->email,
                'otp_code' => $this->otp,
                'ttl_minutes' => $this->ttlMinutes,
            ],
        );
    }

    public function attachments(): array
    {
        return [];
    }
}
