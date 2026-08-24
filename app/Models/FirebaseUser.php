<?php

namespace App\Models;

use Illuminate\Contracts\Auth\Authenticatable;

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
        return ($this->attributes['role'] ?? null) === 'admin';
    }

    public function isUser(): bool
    {
        return ($this->attributes['role'] ?? null) === 'user';
    }

    public function isSuperAdmin(): bool
    {
        return ($this->attributes['role'] ?? null) === 'super_admin';
    }

    public function isActive(): bool
    {
        return ($this->attributes['is_active'] ?? false) === true;
    }
}
