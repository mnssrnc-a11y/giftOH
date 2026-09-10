<?php

namespace App\Repositories;

class FirebaseNotificationHistoryRepository extends FirebaseRepository
{
    protected function nodeKey(): string
    {
        return 'notification_history';
    }
}