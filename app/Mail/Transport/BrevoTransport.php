<?php

namespace App\Mail\Transport;

use Illuminate\Support\Facades\Http;
use Symfony\Component\Mailer\Exception\TransportException;
use Symfony\Component\Mailer\SentMessage;
use Symfony\Component\Mailer\Transport\AbstractTransport;
use Symfony\Component\Mime\Address;
use Symfony\Component\Mime\Email;
use Symfony\Component\Mime\MessageConverter;
use Throwable;

/**
 * Sends mail through Brevo's HTTPS API (https://api.brevo.com/v3/smtp/email) instead of SMTP.
 *
 * Needed on hosts that block outgoing SMTP ports 25/465/587, such as Render's free plan.
 * The sender address must be verified in Brevo (Senders, domains & IPs). Errors are raised as
 * Symfony TransportExceptions, so the app's existing "email could not be sent" handling still works.
 */
class BrevoTransport extends AbstractTransport
{
    private const ENDPOINT = 'https://api.brevo.com/v3/smtp/email';

    public function __construct(
        private string $apiKey,
        private int $timeout = 20
    ) {
        parent::__construct();
    }

    protected function doSend(SentMessage $message): void
    {
        if ($this->apiKey === '') {
            throw new TransportException('No Brevo API key is saved. The super admin adds it in System settings → Verification email settings.');
        }

        $email = MessageConverter::toEmail($message->getOriginalMessage());
        $envelope = $message->getEnvelope();
        $from = $email->getFrom()[0] ?? $envelope->getSender();

        $payload = array_filter([
            'sender' => $this->address($from),
            'to' => $this->addresses($email->getTo() ?: $envelope->getRecipients()),
            'cc' => $this->addresses($email->getCc()),
            'bcc' => $this->addresses($email->getBcc()),
            'replyTo' => isset($email->getReplyTo()[0]) ? $this->address($email->getReplyTo()[0]) : null,
            'subject' => (string) $email->getSubject(),
            'htmlContent' => $this->body($email->getHtmlBody()),
            'textContent' => $this->body($email->getTextBody()),
            'attachment' => $this->attachments($email),
        ], static fn ($value) => $value !== null && $value !== [] && $value !== '');

        try {
            $response = Http::timeout($this->timeout)
                ->acceptJson()
                ->withHeaders(['api-key' => $this->apiKey])
                ->post(self::ENDPOINT, $payload);
        } catch (Throwable $exception) {
            throw new TransportException('Could not reach Brevo: ' . $exception->getMessage(), 0, $exception);
        }

        if ($response->failed()) {
            $reason = $response->json('message') ?? $response->body();
            throw new TransportException("Brevo rejected the email ({$response->status()}): {$reason}");
        }

        if ($id = $response->json('messageId')) {
            $message->setMessageId((string) $id);
        }
    }

    public function __toString(): string
    {
        return 'brevo+api://api.brevo.com';
    }

    private function address(Address $address): array
    {
        return array_filter(['email' => $address->getAddress(), 'name' => $address->getName()]);
    }

    /** @param Address[] $addresses */
    private function addresses(array $addresses): array
    {
        return array_map(fn (Address $address) => $this->address($address), $addresses);
    }

    private function body(mixed $body): ?string
    {
        if (is_resource($body)) {
            $body = stream_get_contents($body);
        }

        return is_string($body) && $body !== '' ? $body : null;
    }

    private function attachments(Email $email): array
    {
        $files = [];
        foreach ($email->getAttachments() as $attachment) {
            $files[] = [
                'name' => $attachment->getFilename() ?? 'attachment',
                'content' => base64_encode($attachment->getBody()),
            ];
        }

        return $files;
    }
}
