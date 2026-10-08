<?php

namespace App\Support\Cache;

use Illuminate\Contracts\Cache\Repository;
use Psr\Cache\CacheItemInterface;
use Psr\Cache\CacheItemPoolInterface;

/**
 * A PSR-6 cache pool on top of Laravel's cache, so the Firebase SDK can keep its Google access
 * token between requests (it is valid for an hour) instead of fetching a new one on every page.
 */
class LaravelCachePool implements CacheItemPoolInterface
{
    /** @var array<string, LaravelCacheItem> */
    private array $deferred = [];

    public function __construct(
        private Repository $cache,
        private string $prefix = 'psr6:'
    ) {
    }

    public function getItem(string $key): CacheItemInterface
    {
        if (isset($this->deferred[$key])) {
            return clone $this->deferred[$key];
        }
        $stored = $this->cache->get($this->prefix . $key);

        return new LaravelCacheItem($key, $stored['value'] ?? null, is_array($stored) && array_key_exists('value', $stored));
    }

    public function getItems(array $keys = []): iterable
    {
        $items = [];
        foreach ($keys as $key) {
            $items[$key] = $this->getItem($key);
        }

        return $items;
    }

    public function hasItem(string $key): bool
    {
        return $this->getItem($key)->isHit();
    }

    public function clear(): bool
    {
        // Only this pool's deferred items; the shared application cache is left alone.
        $this->deferred = [];

        return true;
    }

    public function deleteItem(string $key): bool
    {
        unset($this->deferred[$key]);

        return $this->cache->forget($this->prefix . $key) || true;
    }

    public function deleteItems(array $keys): bool
    {
        foreach ($keys as $key) {
            $this->deleteItem($key);
        }

        return true;
    }

    public function save(CacheItemInterface $item): bool
    {
        if (! $item instanceof LaravelCacheItem) {
            return false;
        }
        $seconds = $item->secondsToLive();
        if ($seconds !== null && $seconds <= 0) {
            return $this->deleteItem($item->getKey());
        }
        $payload = ['value' => $item->get()];

        return $seconds === null
            ? $this->cache->forever($this->prefix . $item->getKey(), $payload)
            : $this->cache->put($this->prefix . $item->getKey(), $payload, $seconds);
    }

    public function saveDeferred(CacheItemInterface $item): bool
    {
        if (! $item instanceof LaravelCacheItem) {
            return false;
        }
        $this->deferred[$item->getKey()] = $item;

        return true;
    }

    public function commit(): bool
    {
        $ok = true;
        foreach ($this->deferred as $item) {
            $ok = $this->save($item) && $ok;
        }
        $this->deferred = [];

        return $ok;
    }

    public function __destruct()
    {
        $this->commit();
    }
}
