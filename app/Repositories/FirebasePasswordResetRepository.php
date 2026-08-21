<?php

namespace App\Repositories;

use Illuminate\Support\Facades\Hash;

class FirebasePasswordResetRepository extends FirebaseRepository
{
    public function put(string $email, string $code): array
    {
        $record = [
            'email' => strtolower(trim($email)),
            'token' => Hash::make($code),
            'created_at' => now()->toIso8601String(),
        ];

        $this->reference($this->key($email))->set($record);

        return $record;
    }

    public function find(string $email): ?array
    {
        $value = $this->reference($this->key($email))->getValue();

        return is_array($value) ? $value : null;
    }

    public function forget(string $email): void
    {
        $this->reference($this->key($email))->remove();
    }

    protected function nodeKey(): string
    {
        return 'password_reset_tokens';
    }

    private function key(string $email): string
    {
        return hash('sha256', strtolower(trim($email)));
    }
}
