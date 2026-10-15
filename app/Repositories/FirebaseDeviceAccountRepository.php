<?php

namespace App\Repositories;

/**
 * Firebase device_accounts: which Firebase sign-in (uid) belongs to which donation box. The
 * database rules let a box write only its own node in boxes/ (see firebase.database.rules.json).
 * No one can read or change this node from outside the app.
 */
class FirebaseDeviceAccountRepository extends FirebaseRepository
{
    /** Always read fresh: it decides who may write the donation totals. */
    protected int $cacheSeconds = 0;

    /** @return array<string, string> box id => uid */
    public function byBox(): array
    {
        $value = $this->root()->getValue();

        return is_array($value) ? array_flip(array_filter($value, 'is_string')) : [];
    }

    public function link(string $uid, string $boxId): void
    {
        $this->reference($uid)->set($boxId);
        $this->nodeChanged();
    }

    protected function nodeKey(): string
    {
        return 'device_accounts';
    }
}
