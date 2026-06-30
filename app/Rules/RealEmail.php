<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

class RealEmail implements ValidationRule
{
    /**
     * Verify that the email address has a real, reachable mailbox
     * by connecting to the recipient's mail server and checking
     * RCPT TO acceptance.
     */
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        $email = strtolower(trim($value));

        // Extract domain
        $parts = explode('@', $email);
        if (count($parts) !== 2) {
            $fail('The email address is not eligible. Please provide a valid email to continue.');
            return;
        }

        $domain = $parts[1];

        // Look up MX records for the domain
        $mxHosts = [];
        $mxWeights = [];
        if (!getmxrr($domain, $mxHosts, $mxWeights)) {
            $fail('The email address is not eligible. Please provide a valid email to continue.');
            return;
        }

        // Sort MX hosts by priority (weight)
        array_multisort($mxWeights, $mxHosts);

        // Try to verify via SMTP RCPT TO
        $verified = false;
        $allFailedToConnect = true;

        foreach ($mxHosts as $mxHost) {
            $connected = false;
            $result = $this->checkMailbox($mxHost, $email, $connected);
            if ($connected) {
                $allFailedToConnect = false;
            }

            if ($result === true) {
                $verified = true;
                break;
            }
            if ($result === false) {
                // Server explicitly rejected the recipient
                $fail('The email address is not eligible. Please provide a valid email to continue.');
                return;
            }
        }

        // If we connected successfully to at least one mail server, but none of them confirmed the mailbox,
        // and we did not get an explicit reject (false), then fail.
        // But if ALL connections failed (e.g. port 25 is blocked), we fall back gracefully and let the email pass.
        if (!$verified && !$allFailedToConnect) {
            $fail('The email address is not eligible. Please provide a valid email to continue.');
            return;
        }
    }

    /**
     * Connect to the MX server and verify the mailbox via RCPT TO.
     *
     * @return bool|null true = accepted, false = rejected, null = inconclusive/error
     */
    private function checkMailbox(string $mxHost, string $email, &$connected = false): ?bool
    {
        $timeout = 5; // seconds
        $socket = @fsockopen($mxHost, 25, $errno, $errstr, $timeout);

        if (!$socket) {
            $connected = false;
            return null; // Could not connect, try next MX
        }

        $connected = true;
        stream_set_timeout($socket, $timeout);

        // Read greeting
        $response = $this->readResponse($socket);
        if (!$response || !str_starts_with($response, '220')) {
            fclose($socket);
            return null;
        }

        // Send EHLO
        $this->sendCommand($socket, "EHLO giftofhope.local\r\n");
        $response = $this->readResponse($socket);
        if (!$response || !str_starts_with($response, '250')) {
            // Try HELO as fallback
            $this->sendCommand($socket, "HELO giftofhope.local\r\n");
            $response = $this->readResponse($socket);
            if (!$response || !str_starts_with($response, '250')) {
                fclose($socket);
                return null;
            }
        }

        // MAIL FROM with empty sender (standard for verification)
        $this->sendCommand($socket, "MAIL FROM:<>\r\n");
        $response = $this->readResponse($socket);
        if (!$response || !str_starts_with($response, '250')) {
            fclose($socket);
            return null;
        }

        // RCPT TO — this is the key check
        $this->sendCommand($socket, "RCPT TO:<{$email}>\r\n");
        $response = $this->readResponse($socket);

        // Send QUIT to cleanly close
        $this->sendCommand($socket, "QUIT\r\n");
        fclose($socket);

        if (!$response) {
            return null;
        }

        $code = (int) substr($response, 0, 3);

        // 250, 251 = accepted
        if ($code === 250 || $code === 251) {
            return true;
        }

        // 550, 551, 552, 553 = rejected (mailbox does not exist)
        if ($code >= 550 && $code <= 553) {
            return false;
        }

        // 450, 451, 452 = temporary failure (greylisting, etc.) — treat as inconclusive
        return null;
    }

    private function sendCommand($socket, string $command): void
    {
        fwrite($socket, $command);
    }

    private function readResponse($socket): ?string
    {
        $response = '';
        while ($line = @fgets($socket, 512)) {
            $response .= $line;
            // Multi-line responses have a dash after the code (e.g., "250-")
            // The final line has a space (e.g., "250 ")
            if (isset($line[3]) && $line[3] === ' ') {
                break;
            }
            if (isset($line[3]) && $line[3] !== '-') {
                break;
            }
        }
        return $response ?: null;
    }
}
