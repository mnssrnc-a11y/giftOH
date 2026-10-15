<?php

namespace App\Services;

use App\Repositories\FirebasePasswordResetRepository;
use App\Repositories\FirebaseVerificationRepository;
use Carbon\Carbon;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;

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

    /**
     * Whether a code (or reset session) created at $createdAt is more than $minutes old. A missing
     * or unreadable time counts as expired.
     *
     * Compare with the expiry time, never with now()->diffInMinutes($createdAt): since Carbon 3 that
     * difference is negative for a past time, so "> 5" was never true and codes never expired.
     */
    public static function isExpired(mixed $createdAt, int $minutes): bool
    {
        if (blank($createdAt)) {
            return true;
        }

        try {
            return Carbon::parse($createdAt)->addMinutes($minutes)->isPast();
        } catch (\Throwable) {
            return true;
        }
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

        if (self::isExpired($record['created_at'] ?? null, $expirationMinutes)) {
            $this->forget($email, $tableName);

            return [
                'success' => false,
                'error' => 'This code has expired. Please request a new one.',
            ];
        }

        // Five wrong tries and the code is thrown away: guessing a 6-digit code is not possible.
        $attempts = 'code-attempts:' . $tableName . ':' . sha1(strtolower(trim($email)));
        $valid = Hash::check($code, $record['token'] ?? '');
        if (! $valid) {
            if (RateLimiter::hit($attempts, $expirationMinutes * 60) >= self::MAX_WRONG_CODES) {
                RateLimiter::clear($attempts);
                $this->forget($email, $tableName);

                return [
                    'success' => false,
                    'error' => 'Too many wrong codes. Please request a new code.',
                ];
            }

            return [
                'success' => false,
                'error' => 'Invalid code. Please try again.',
            ];
        }
        RateLimiter::clear($attempts);

        return [
            'success' => true,
            'record' => $record,
        ];
    }

    public const MAX_WRONG_CODES = 5;

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
