<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class TransactionVerificationCode extends Mailable
{
    use Queueable, SerializesModels;

    public string $code;
    public string $fName;

    /**
     * Create a new message instance.
     */
    public function __construct(string $code, string $fName)
    {
        $this->code = $code;
        $this->fName = $fName;
    }

    /**
     * Get the message envelope.
     */
    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Gift of Hope - Fund Transaction Verification Code',
        );
    }

    /**
     * Get the message content definition.
     */
    public function content(): Content
    {
        return new Content(
            view: 'emails.transaction_verification',
        );
    }
}
