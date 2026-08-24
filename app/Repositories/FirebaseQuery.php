<?php

namespace App\Repositories;

use App\Services\FirebaseService;

class FirebaseQuery
{
    private string $node = '';
    private array $filters = [];

    public function __construct(private FirebaseService $firebase)
    {
    }

    public function from(string $node): self
    {
        $this->node = (string) config("firebase.nodes.{$node}", $node);

        return $this;
    }

    public function where(string $field, string|int|bool $value): self
    {
        $this->filters[$field] = $value;

        return $this;
    }

    public function get(): array
    {
        if ($this->node === '') {
            return [];
        }

        $reference = $this->firebase->getDatabase()->getReference($this->node);
        foreach ($this->filters as $field => $value) {
            $reference = $reference->orderByChild($field)->equalTo($value);
        }

        $records = $reference->getValue();
        if (!is_array($records)) {
            return [];
        }

        $result = [];
        foreach ($records as $id => $record) {
            if (is_array($record)) {
                $record['id'] ??= (string) $id;
                $result[] = $record;
            }
        }

        return $result;
    }
}