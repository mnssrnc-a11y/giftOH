<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use App\Services\FirebaseService;
use App\Repositories\FirebaseUserRepository;
use App\Repositories\FirebaseFundingRepository;
use App\Repositories\FirebaseDonationRepository;
use App\Repositories\FirebaseApprovalRepository;
use App\Repositories\FirebaseAppealRepository;
use App\Repositories\FirebaseVerificationRepository;
use App\Repositories\FirebaseLogRepository;
use App\Repositories\FirebasePasswordResetRepository;
use App\Services\VerificationService;

Artisan::command('firebase:repositories', function (
    FirebaseUserRepository $users,
    FirebaseFundingRepository $fundings,
    FirebaseDonationRepository $donations,
    FirebaseApprovalRepository $approvals,
    FirebaseAppealRepository $appeals,
    FirebaseVerificationRepository $verificationCodes,
    FirebaseLogRepository $logs,
    FirebasePasswordResetRepository $passwordResets,
) {
    $this->info('Firebase repositories loaded successfully:');
    $this->line(' - users: ' . count($users->all()));
    $this->line(' - funding_requests: ' . count($fundings->all()));
    $this->line(' - donations: ' . count($donations->all()));
    $this->line(' - funding_approvals: ' . count($approvals->all()));
    $this->line(' - funding_appeals: ' . count($appeals->all()));
    $this->line(' - activity_logs: ' . count($logs->all()));
    $this->line(' - verification and password-reset repositories: ready');
})->purpose('Read Firebase repository roots without changing data');

Artisan::command('firebase:verification-smoke', function () {
    config(['auth.providers.users.driver' => 'firebase']);
    $verification = app(VerificationService::class);
    $email = 'phase5-smoke@example.invalid';
    $table = 'login_auth_codes';
    $code = '123456';

    $verification->store($email, $code, $table);
    $result = $verification->verify($email, $code, $table);
    $verification->forget($email, $table);

    if (! $result['success']) {
        $this->error('Firebase verification smoke test failed.');

        return self::FAILURE;
    }

    $this->info('Firebase verification storage, hashing, verification, and cleanup work.');
})->purpose('Test Firebase verification code lifecycle with a temporary namespaced record');
