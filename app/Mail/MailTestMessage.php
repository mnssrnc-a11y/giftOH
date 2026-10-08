<?php

namespace App\Mail;

use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

/**
 * The super admin's "Send test email": proves the saved Brevo key and sender deliver mail.
 */
class MailTestMessage extends Mailable
{
    public function __construct(
        public string $name
    ) {
    }

    public function envelope(): Envelope
    {
        return new Envelope(subject: config('app.name') . ' — test email');
    }

    public function content(): Content
    {
        return new Content(htmlString: '<div style="font-family:Arial,sans-serif;font-size:15px;color:#0f2a55">'
            . '<p>Hi ' . e($this->name) . ',</p>'
            . '<p>This is a test from <strong>' . e(config('app.name')) . '</strong>. Email is working: verification codes and notices will be delivered from this sender.</p>'
            . '<p style="color:#5b6b85;font-size:13px">Sent ' . e(now()->format('M d, Y g:i A')) . ' from System settings.</p></div>');
    }
}
