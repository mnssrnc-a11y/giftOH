<?php

namespace App\Support\Cache;

use Psr\Cache\CacheItemInterface;

/**
 * One entry of LaravelCachePool.
 */
class LaravelCacheItem implements CacheItemInterface
{
    private ?int $expiresAt = null;

    public function __construct(
        private string $key,
        private mixed $value = null,
        private bool $hit = false
    ) {
    }

    public function getKey(): string
    {
        return $this->key;
    }

    public function get(): mixed
    {
        return $this->hit ? $this->value : null;
    }

    public function isHit(): bool
    {
        return $this->hit;
    }

    public function set(mixed $value): static
    {
        $this->value = $value;
        $this->hit = true;

        return $this;
    }

    public function expiresAt(?\DateTimeInterface $expiration): static
    {
        $this->expiresAt = $expiration?->getTimestamp();

        return $this;
    }

    public function expiresAfter(int|\DateInterval|null $time): static
    {
        $this->expiresAt = match (true) {
            $time === null => null,
            $time instanceof \DateInterval => (new \DateTimeImmutable())->add($time)->getTimestamp(),
            default => time() + $time,
        };

        return $this;
    }

    /**
     * Seconds until expiry, or null when the item never expires.
     */
    public function secondsToLive(): ?int
    {
        return $this->expiresAt === null ? null : $this->expiresAt - time();
    }
}
