<?php

namespace App\Auth;

use App\Models\FirebaseUser;
use App\Repositories\FirebaseUserRepository;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Contracts\Auth\UserProvider;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Hash;

class FirebaseUserProvider implements UserProvider
{
    public function __construct(
        private FirebaseUserRepository $users
    ) {
    }

    public function retrieveById($identifier): ?Authenticatable
    {
        // Every page needs the signed-in account; reading it from Firebase each time costs ~0.3 s.
        // FirebaseUserRepository clears this entry whenever the account is updated or deleted.
        $user = Cache::remember(self::cacheKey($identifier), 60, fn () => $this->users->findById($identifier) ?: null);

        return $user ? new FirebaseUser($user) : null;
    }

    public static function cacheKey(string|int $identifier): string
    {
        return 'auth-user:' . $identifier;
    }

    public function retrieveByToken($identifier, $token): ?Authenticatable
    {
        $user = $this->retrieveById($identifier);

        if (! $user || ! $user->getRememberToken()) {
            return null;
        }

        return hash_equals($user->getRememberToken(), $token) ? $user : null;
    }

    public function updateRememberToken(Authenticatable $user, $token): void
    {
        $this->users->update($user->getAuthIdentifier(), [
            'remember_token' => $token,
        ]);
    }

    public function retrieveByCredentials(array $credentials): ?Authenticatable
    {
        $email = $credentials['email'] ?? null;

        if (! is_string($email) || $email === '') {
            return null;
        }

        $user = $this->users->findByEmail($email);

        return $user ? new FirebaseUser($user) : null;
    }

    public function validateCredentials(Authenticatable $user, array $credentials): bool
    {
        $password = $credentials['password'] ?? null;

        return is_string($password)
            && Hash::check($password, $user->getAuthPassword());
    }

    public function rehashPasswordIfRequired(
        Authenticatable $user,
        array $credentials,
        bool $force = false
    ): void {
        $password = $credentials['password'] ?? null;

        if (! is_string($password) || $password === '') {
            return;
        }

        if ($force || Hash::needsRehash($user->getAuthPassword())) {
            $this->users->update($user->getAuthIdentifier(), [
                'password' => Hash::make($password),
            ]);
        }
    }
}
