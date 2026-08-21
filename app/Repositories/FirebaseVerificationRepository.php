<?php

namespace App\Repositories;

use Illuminate\Support\Facades\Hash;

class FirebaseVerificationRepository extends FirebaseRepository
{
    public function put(string $type, string $email, string $code): array
    {
        $record = [
            'email' => strtolower(trim($email)),
            'token' => Hash::make($code),
            'created_at' => now()->toIso8601String(),
        ];

        $this->reference($this->key($type, $email))->set($record);

        return $record;
    }

    public function find(string $type, string $email): ?array
    {
        $value = $this->reference($this->key($type, $email))->getValue();

        return is_array($value) ? $value : null;
    }

    public function verify(string $type, string $email, string $code, int $expirationMinutes = 5): bool
    {
        $record = $this->find($type, $email);

        if (! $record || ! isset($record['token'], $record['created_at'])) {
            return false;
        }

        if (now()->diffInMinutes($record['created_at']) > $expirationMinutes) {
            $this->forget($type, $email);
            return false;
        }

        return Hash::check($code, $record['token']);
    }

    public function forget(string $type, string $email): void
    {
        $this->reference($this->key($type, $email))->remove();
    }

    protected function nodeKey(): string
    {
        return 'verification_codes';
    }

    private function key(string $type, string $email): string
    {
        return $type . '/' . hash('sha256', strtolower(trim($email)));
    }
}
