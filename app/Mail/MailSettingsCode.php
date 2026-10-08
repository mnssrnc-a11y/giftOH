<?php

namespace App\Mail;

use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Address;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

/**
 * Sent with the NEW sender credentials to prove they work before they are saved.
 */
class MailSettingsCode extends Mailable
{
    public function __construct(
        public string $code,
        public string $fromAddress,
        public string $appName
    ) {
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            from: new Address($this->fromAddress, $this->appName),
            subject: "{$this->appName} — confirm the new sender email",
        );
    }

    public function content(): Content
    {
        return new Content(htmlString: '<div style="font-family:Arial,sans-serif;font-size:15px;color:#172033">'
            . '<p>This address is being set as the sender of verification emails for <strong>' . e($this->appName) . '</strong>.</p>'
            . '<p>Enter this code in System settings to confirm:</p>'
            . '<p style="font-size:28px;font-weight:700;letter-spacing:6px">' . e($this->code) . '</p>'
            . '<p style="color:#64748b;font-size:13px">The code expires in 10 minutes. If you did not request this, ignore this email.</p></div>');
    }
}
