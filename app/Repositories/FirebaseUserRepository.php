<?php

namespace App\Repositories;

use App\Auth\FirebaseUserProvider;
use Illuminate\Support\Facades\Cache;
use Kreait\Firebase\Exception\Database\UnsupportedQuery;

class FirebaseUserRepository extends FirebaseRepository
{
    public function findByEmail(string $email): ?array
    {
        $normalizedEmail = strtolower(trim($email));

        try {
            $users = $this->queryBy('email', $normalizedEmail);

            if (isset($users[0])) {
                return $users[0];
            }
        } catch (UnsupportedQuery) {
        }

        foreach ($this->all() as $user) {
            if (strtolower(trim((string) ($user['email'] ?? ''))) === $normalizedEmail) {
                return $user;
            }
        }

        return null;
    }

    public function findRole(string|int $id): ?string
    {
        $user = $this->find($id);
        $role = $user['role'];
        if ($role === "admin") {
            return "admin";
        } elseif (in_array($role, ["user", "normaluser"], true)) {
            return "user";
        } elseif (in_array($role, ["super_admin", "supper_admin"], true)) {
            return "super_admin";
        } else {
            return null;
        }
    }

    public function update(string|int $id, array $data): ?array
    {
        if (isset($data['email'])) {
            $data['email'] = strtolower(trim((string) $data['email']));
        }
        $updated = parent::update($id, $data);
        Cache::forget(FirebaseUserProvider::cacheKey($id));

        return $updated;
    }

    public function delete(string|int $id): bool
    {
        $deleted = parent::delete($id);
        Cache::forget(FirebaseUserProvider::cacheKey($id));

        return $deleted;
    }

    protected function nodeKey(): string
    {
        return 'users';
    }
}
