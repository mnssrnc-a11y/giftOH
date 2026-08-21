<?php

namespace App\Services;

use App\Repositories\FirebasePasswordResetRepository;
use App\Repositories\FirebaseVerificationRepository;
use Illuminate\Support\Facades\DB;
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
        if ($this->firebaseEnabled()) {
            if ($tableName === 'password_reset_tokens') {
                $this->firebaseResets->put($email, $code);
            } else {
                $this->firebaseCodes->put($this->typeFor($tableName), $email, $code);
            }

            return;
        }

        DB::table($tableName)->updateOrInsert(
            ['email' => $email],
            ['token' => Hash::make($code), 'created_at' => now()]
        );
    }

    public function forget(string $email, string $tableName): void
    {
        if ($this->firebaseEnabled()) {
            if ($tableName === 'password_reset_tokens') {
                $this->firebaseResets->forget($email);
            } else {
                $this->firebaseCodes->forget($this->typeFor($tableName), $email);
            }

            return;
        }

        DB::table($tableName)->where('email', $email)->delete();
    }

    public function rotate(string $email, string $tableName, string $token): void
    {
        if ($this->firebaseEnabled()) {
            if ($tableName === 'password_reset_tokens') {
                $this->firebaseResets->put($email, $token);
            } else {
                $this->firebaseCodes->put($this->typeFor($tableName), $email, $token);
            }

            return;
        }

        DB::table($tableName)
            ->where('email', $email)
            ->update(['token' => Hash::make($token), 'created_at' => now()]);
    }

    public function find(string $email, string $tableName): ?array
    {
        $record = $this->firebaseEnabled()
            ? ($tableName === 'password_reset_tokens'
                ? $this->firebaseResets->find($email)
                : $this->firebaseCodes->find($this->typeFor($tableName), $email))
            : DB::table($tableName)->where('email', $email)->first();

        return $record ? (array) $record : null;
    }

    /**
     * Verify a generic code against a specific database table.
     *
     * @param string $email The email to check for.
     * @param string $code The code provided by the user.
     * @param string $tableName The table containing the tokens.
     * @param int $expirationMinutes The number of minutes before the code expires.
     * @return array An array containing success status, and error message if failed, or record if success.
     */
    public function verify(string $email, string $code, string $tableName, int $expirationMinutes = 5): array
    {
        if ($this->firebaseEnabled()) {
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

        $record = DB::table($tableName)
            ->where('email', $email)
            ->first();

        if (!$record) {
            return [
                'success' => false,
                'error' => 'No code found. Please request a new one.'
            ];
        }

        // Check if code has expired
        if (now()->diffInMinutes($record->created_at) > $expirationMinutes) {
            DB::table($tableName)->where('email', $email)->delete();
            return [
                'success' => false,
                'error' => 'This code has expired. Please request a new one.'
            ];
        }

        // Verify the code against the hash
        if (!Hash::check($code, $record->token)) {
            return [
                'success' => false,
                'error' => 'Invalid code. Please try again.'
            ];
        }

        return [
            'success' => true,
            'record' => $record
        ];
    }

    private function firebaseEnabled(): bool
    {
        return config('auth.providers.users.driver') === 'firebase';
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
