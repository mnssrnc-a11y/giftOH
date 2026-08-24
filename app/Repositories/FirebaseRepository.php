<?php

namespace App\Repositories;

use App\Services\FirebaseService;
use Illuminate\Support\Str;

abstract class FirebaseRepository
{
    protected string $node;

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

        return $record;
    }

    public function findById(string|int $id): ?array
    {
        $value = $this->reference((string) $id)->getValue();

        return is_array($value) ? array_merge(['id' => (string) $id], $value) : null;
    }

    public function all(): array
    {
        $value = $this->root()->getValue();

        if (! is_array($value)) {
            return [];
        }

        $records = [];
        foreach ($value as $id => $record) {
            if (is_array($record)) {
                $record['id'] ??= (string) $id;
                $records[] = $record;
            }
        }

        return $records;
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

        return array_merge($existing, $data);
    }

    public function delete(string|int $id): bool
    {
        if ($this->findById($id) === null) {
            return false;
        }

        $this->reference((string) $id)->remove();
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
        return (new FirebaseQuery($this->firebase))
            ->from($this->nodeKey())
            ->where($field, $value)
            ->get();
    }

    abstract protected function nodeKey(): string;
}
