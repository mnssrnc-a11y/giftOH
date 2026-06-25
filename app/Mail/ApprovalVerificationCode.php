<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class ApprovalVerificationCode extends Mailable
{
    use Queueable, SerializesModels;

    public string $code;
    public string $fName;
    public string $actionType;

    /**
     * Create a new message instance.
     */
    public function __construct(string $code, string $fName, string $actionType)
    {
        $this->code = $code;
        $this->fName = $fName;
        $this->actionType = $actionType;
    }

    /**
     * Get the message envelope.
     */
    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Gift of Hope - Fund Approval Verification Code',
        );
    }

    /**
     * Get the message content definition.
     */
    public function content(): Content
    {
        return new Content(
            view: 'emails.approval_verification',
        );
    }
}
