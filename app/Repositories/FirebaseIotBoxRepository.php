<?php

namespace App\Repositories;

class FirebaseIotBoxRepository extends FirebaseRepository
{
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
            $value = $this->root()->getValue();

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
