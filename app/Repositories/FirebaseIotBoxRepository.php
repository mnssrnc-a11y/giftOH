<?php

namespace App\Repositories;

class FirebaseIotBoxRepository extends FirebaseRepository
{
    /** Not cached between requests: the smart boxes write this node directly. */
    protected int $cacheSeconds = 0;

    protected function nodeKey(): string
    {
        return 'boxes';
    }

    /**
     * Get all IoT boxes from Firebase.
     * Returns null if database read fails or node does not exist,
     * or an array of box records.
     */
    public function getBoxes(): ?array
    {
        try {
            // Several dashboard figures need the boxes; read them once per request.
            $value = $this->oncePerRequest('boxes', fn () => $this->root()->getValue());

            if ($value === null) {
                return null;
            }

            if (! is_array($value)) {
                return [];
            }

            $records = [];
            foreach ($value as $id => $record) {
                if (is_array($record)) {
                    $record['id'] = (string) ($record['id'] ?? $id);
                    $records[] = $record;
                }
            }

            return $records;
        } catch (\Throwable $e) {
            return null;
        }
    }
}
