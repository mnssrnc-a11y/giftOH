<?php

namespace App\Services;

use App\Repositories\FirebasePasswordResetRepository;
use App\Repositories\FirebaseVerificationRepository;
use Illuminate\Support\Facades\Hash;

class VerificationService
{
    public function __construct(
        private FirebaseVerificationRepository $firebaseCodes,
        private FirebasePasswordResetRepository $firebaseResets
    ) {
    }

    public function store(string $email, string $code, string $tableName): void
    {
        if ($tableName === 'password_reset_tokens') {
            $this->firebaseResets->put($email, $code);

            return;
        }

        $this->firebaseCodes->put($this->typeFor($tableName), $email, $code);
    }

    public function forget(string $email, string $tableName): void
    {
        if ($tableName === 'password_reset_tokens') {
            $this->firebaseResets->forget($email);

            return;
        }

        $this->firebaseCodes->forget($this->typeFor($tableName), $email);
    }

    public function rotate(string $email, string $tableName, string $token): void
    {
        if ($tableName === 'password_reset_tokens') {
            $this->firebaseResets->put($email, $token);

            return;
        }

        $this->firebaseCodes->put($this->typeFor($tableName), $email, $token);
    }

    public function find(string $email, string $tableName): ?array
    {
        $record = $tableName === 'password_reset_tokens'
            ? $this->firebaseResets->find($email)
            : $this->firebaseCodes->find($this->typeFor($tableName), $email);

        return $record ? (array) $record : null;
    }

    public function verify(string $email, string $code, string $tableName, int $expirationMinutes = 5): array
    {
        $record = $tableName === 'password_reset_tokens'
            ? $this->firebaseResets->find($email)
            : $this->firebaseCodes->find($this->typeFor($tableName), $email);

        if (! $record) {
            return [
                'success' => false,
                'error' => 'No code found. Please request a new one.',
            ];
        }

        if (now()->diffInMinutes($record['created_at']) > $expirationMinutes) {
            $this->forget($email, $tableName);

            return [
                'success' => false,
                'error' => 'This code has expired. Please request a new one.',
            ];
        }

        $valid = Hash::check($code, $record['token'] ?? '');
        if (! $valid) {
            return [
                'success' => false,
                'error' => 'Invalid code. Please try again.',
            ];
        }

        return [
            'success' => true,
            'record' => $record,
        ];
    }

    private function typeFor(string $tableName): string
    {
        return match ($tableName) {
            'login_auth_codes' => 'login',
            'register_verification_codes' => 'register',
            'transaction_verification_codes' => 'transaction',
            'approval_verification_codes' => 'approval',
            default => throw new \InvalidArgumentException("Unsupported verification table: {$tableName}"),
        };
    }
}
