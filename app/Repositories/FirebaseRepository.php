<?php

namespace App\Repositories;

use App\Services\FirebaseService;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

abstract class FirebaseRepository
{
    protected string $node;

    /**
     * Whole-node reads made during this request (node => records). Several services read the same
     * node on one page (e.g. every funding request for the dashboard, the totals and the queue);
     * each Firebase round trip costs about 0.1 s. Any write through a repository clears the node.
     */
    private static array $snapshots = [];

    /**
     * Seconds a read stays cached between requests. Each Firebase round trip costs 0.1-0.4 s, so
     * moving between pages re-reads the same data; any write through a repository gives the node a
     * new version at once, so changes made in the app are never served stale. Nodes written by
     * devices or outside the repositories set this to 0.
     */
    protected int $cacheSeconds = 45;

    public static function flushSnapshots(): void
    {
        self::$snapshots = [];
        self::$requestMemo = [];
    }

    /** Values read once per request by custom repository methods (e.g. the live IoT boxes). */
    private static array $requestMemo = [];

    protected function oncePerRequest(string $key, \Closure $read): mixed
    {
        $memoKey = "{$this->node}:{$key}";
        if (! array_key_exists($memoKey, self::$requestMemo)) {
            self::$requestMemo[$memoKey] = $read();
        }

        return self::$requestMemo[$memoKey];
    }

    protected function forgetSnapshot(): void
    {
        unset(self::$snapshots[$this->node]);
    }

    /**
     * Call after every write to the node: clears this request's snapshot and the shared read cache.
     */
    protected function nodeChanged(): void
    {
        $this->forgetSnapshot();
        if ($this->cacheSeconds > 0) {
            Cache::forever($this->versionKey(), Str::random(12));
        }
    }

    protected function remember(string $what, \Closure $read): mixed
    {
        if ($this->cacheSeconds <= 0) {
            return $read();
        }
        $version = Cache::get($this->versionKey(), '0');

        return Cache::remember("firebase-node:{$this->node}:{$version}:" . sha1($what), $this->cacheSeconds, $read);
    }

    private function versionKey(): string
    {
        return "firebase-node-version:{$this->node}";
    }

    public function __construct(
        protected FirebaseService $firebase
    ) {
        $this->node = (string) config("firebase.nodes.{$this->nodeKey()}", $this->nodeKey());
    }

    public function create(array $data): array
    {
        $id = (string) ($data['id'] ?? Str::uuid());
        $now = now()->toIso8601String();

        $record = array_merge($data, [
            'id' => $id,
            'created_at' => $data['created_at'] ?? $now,
            'updated_at' => $data['updated_at'] ?? $now,
        ]);

        $this->reference($id)->set($record);
        $this->nodeChanged();

        return $record;
    }

    public function findById(string|int $id): ?array
    {
        if (isset(self::$snapshots[$this->node])) {
            foreach (self::$snapshots[$this->node] as $record) {
                if ((string) $record['id'] === (string) $id) {
                    return $record;
                }
            }

            return null;
        }

        $value = $this->remember("id:{$id}", fn () => $this->reference((string) $id)->getValue());

        return is_array($value) ? array_merge(['id' => (string) $id], $value) : null;
    }

    public function all(): array
    {
        if (isset(self::$snapshots[$this->node])) {
            return self::$snapshots[$this->node];
        }

        $value = $this->remember('all', fn () => $this->root()->getValue());

        if (! is_array($value)) {
            return self::$snapshots[$this->node] = [];
        }

        $records = [];
        foreach ($value as $id => $record) {
            if (is_array($record)) {
                $record['id'] ??= (string) $id;
                $records[] = $record;
            }
        }

        return self::$snapshots[$this->node] = $records;
    }

    public function update(string|int $id, array $data): ?array
    {
        $existing = $this->findById($id);
        if ($existing === null) {
            return null;
        }

        unset($data['id'], $data['created_at']);
        $data['updated_at'] = now()->toIso8601String();
        $this->reference((string) $id)->update($data);
        $this->nodeChanged();

        return array_merge($existing, $data);
    }

    public function delete(string|int $id): bool
    {
        if ($this->findById($id) === null) {
            return false;
        }

        $this->reference((string) $id)->remove();
        $this->nodeChanged();
        return true;
    }

    protected function root(): mixed
    {
        return $this->firebase->getDatabase()->getReference($this->node);
    }

    protected function reference(string $id): mixed
    {
        return $this->firebase->getDatabase()->getReference("{$this->node}/{$id}");
    }

    protected function queryBy(string $field, string|int|bool $value): array
    {
        return $this->remember("where:{$field}=" . var_export($value, true), fn () => (new FirebaseQuery($this->firebase))
            ->from($this->nodeKey())
            ->where($field, $value)
            ->get());
    }

    abstract protected function nodeKey(): string;
}
