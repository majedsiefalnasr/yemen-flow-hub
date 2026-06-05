<?php

namespace App\Mail;

use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class TestEmailMail extends Mailable
{
    use SerializesModels;

    public function envelope(): Envelope
    {
        return new Envelope(subject: 'Yemen Flow Hub — بريد اختباري');
    }

    public function content(): Content
    {
        return new Content(view: 'emails.test-email');
    }

    public function attachments(): array
    {
        return [];
    }
}
