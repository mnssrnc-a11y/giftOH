<?php

namespace App\Models;

use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Support\Facades\Storage;

class FirebaseUser implements Authenticatable
{
    public function __construct(
        private array $attributes
    ) {
    }

    public function getAuthIdentifierName(): string
    {
        return 'id';
    }

    public function getAuthIdentifier(): mixed
    {
        return $this->attributes['id'] ?? null;
    }

    public function getAuthPasswordName(): string
    {
        return 'password';
    }

    public function getAuthPassword(): string
    {
        return (string) ($this->attributes['password'] ?? '');
    }

    public function getRememberToken(): ?string
    {
        return $this->attributes['remember_token'] ?? null;
    }

    public function setRememberToken($value): void
    {
        $this->attributes['remember_token'] = $value;
    }

    public function getRememberTokenName(): string
    {
        return 'remember_token';
    }

    public function __get(string $key): mixed
    {
        return $this->attributes[$key] ?? null;
    }

    public function __isset(string $key): bool
    {
        return isset($this->attributes[$key]);
    }

    public function toArray(): array
    {
        return $this->attributes;
    }

    public function isAdmin(): bool
    {
        return strtolower(trim((string) ($this->attributes['role'] ?? ''))) === 'admin';
    }

    public function isUser(): bool
    {
        return in_array(strtolower(trim((string) ($this->attributes['role'] ?? ''))), [
            'normaluser',
            'user',
            'normal user',
            'normal_user',
            'regular user',
            'regular_user',
            'member',
        ], true);
    }

    public function isSuperAdmin(): bool
    {
        return in_array(strtolower(trim((string) ($this->attributes['role'] ?? ''))), [
            'supper_admin',
            'super_admin',
            'superadmin',
            'super admin',
            'super-admin',
        ], true);
    }

    /**
     * Public URL of the photo stored in the profile_picture field, or null when there is none.
     * The field normally holds a path on the public disk, but full URLs are accepted too.
     */
    public function profilePhotoUrl(): ?string
    {
        $picture = trim((string) ($this->attributes['profile_picture'] ?? ''));

        if ($picture === '') {
            return null;
        }

        if (preg_match('#^(https?://|data:image/)#i', $picture)) {
            return $picture;
        }

        $path = ltrim($picture, '/');

        return Storage::disk('public')->exists($path) ? asset('storage/' . $path) : null;
    }

    /**
     * Name of the route each role lands on after signing in.
     */
    public function homeRoute(): string
    {
        return match (true) {
            $this->isSuperAdmin() => 'superadmin',
            $this->isAdmin() => 'admin',
            default => 'dashboarduser',
        };
    }

    public function isActive(): bool
    {
        return ($this->attributes['is_active'] ?? false) === true;
    }
}
