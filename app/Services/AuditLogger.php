<?php

namespace App\Services;

use App\Repositories\FirebaseAuditLogRepository;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

/**
 * Records who did what (role changes, settings, funding decisions, releases, price updates)
 * for the super admin's activity log. Never stores secrets.
 */
class AuditLogger
{
    public function __construct(
        private FirebaseAuditLogRepository $logs
    ) {
    }

    /**
     * @param  string  $category  account | settings | funding | prices | content
     */
    public function record(string $category, string $action, ?string $subjectId = null, array $details = []): void
    {
        $user = Auth::user();

        try {
            $this->logs->create([
                'category' => $category,
                'action' => $action,
                'subject_id' => $subjectId,
                'details' => $details ?: null,
                'user_id' => $user ? (string) $user->getAuthIdentifier() : 'system',
                'actor' => $user ? ($user->fullName() ?: $user->email) : 'System',
                'actor_role' => $user ? (string) ($user->role ?? 'user') : 'system',
            ]);
        } catch (\Throwable $exception) {
            // Logging must never break the action being logged.
            Log::warning('Audit log write failed', ['action' => $action, 'error' => $exception->getMessage()]);
        }
    }
}
