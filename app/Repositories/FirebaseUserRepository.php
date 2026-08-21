<?php

namespace App\Repositories;

use Kreait\Firebase\Exception\Database\UnsupportedQuery;

class FirebaseUserRepository extends FirebaseRepository
{
    public function findByEmail(string $email): ?array
    {
        $normalizedEmail = strtolower(trim($email));

        try {
            $users = $this->queryBy('email', $normalizedEmail);

            return $users[0] ?? null;
        } catch (UnsupportedQuery) {
            foreach ($this->all() as $user) {
                if (strtolower(trim((string) ($user['email'] ?? ''))) === $normalizedEmail) {
                    return $user;
                }
            }
        }

        return null;
    }

    public function update(string|int $id, array $data): ?array
    {
        if (isset($data['email'])) {
            $data['email'] = strtolower(trim((string) $data['email']));
        }

        return parent::update($id, $data);
    }

    protected function nodeKey(): string
    {
        return 'users';
    }
}
