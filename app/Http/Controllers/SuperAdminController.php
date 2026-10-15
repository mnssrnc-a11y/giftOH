<?php

namespace App\Http\Controllers;

use App\Http\Middleware\EnsureRole;
use App\Repositories\FirebaseAuditLogRepository;
use App\Repositories\FirebaseUserRepository;
use App\Services\AdminDashboardService;
use App\Services\AuditLogger;
use App\Services\FundingRequestPresenter;
use App\Services\FundingRules;
use App\Services\FundingService;
use App\Services\FundQueue;
use App\Services\IotService;
use App\Services\MailSettingsService;
use App\Services\NotificationService;
use App\Services\RequestMessageService;
use App\Services\SystemSettings;
use App\Services\TransactionLog;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;

class SuperAdminController extends Controller
{
    public function __construct(
        private FundingService $fundingService,
        private AdminDashboardService $dashboard,
        private IotService $iotService,
        private FirebaseUserRepository $users,
        private SystemSettings $settings,
        private AuditLogger $audit
    ) {
    }

    public function index(FirebaseAuditLogRepository $auditLogs, MailSettingsService $mail, FundingRules $rules, FundQueue $queue, TransactionLog $ledger, \App\Services\Payouts\PayoutService $payouts)
    {
        // New donations come in from the boxes: see if the next request in line is now covered.
        $queue->refreshSoon();

        // Every request, the ones awaiting the final decision first; each opens the full request.
        $fundRequests = $this->dashboard->fundingRows();
        usort($fundRequests, static fn (array $a, array $b): int => ($b['status'] === 'awaiting') <=> ($a['status'] === 'awaiting'));

        $accounts = $this->accounts();
        $outcomes = $this->outcomes();
        $devices = $this->devices();

        return view('supperAdminPage.spAd_dashB', [
            'fundRequests' => $fundRequests,
            'releaseLine' => $queue->state(),
            'transactions' => $ledger->latest(),
            'ledgerCheck' => $ledger->verify(),
            'availableFunds' => $this->iotService->getAvailableFunds(),
            'accounts' => $accounts,
            'accountSummary' => [
                'total' => count($accounts),
                'admins' => count(array_filter($accounts, fn ($a) => $a['role'] === 'admin')),
                'super_admins' => count(array_filter($accounts, fn ($a) => $a['role'] === 'super_admin')),
                'disabled' => count(array_filter($accounts, fn ($a) => ! $a['active'])),
            ],
            'devices' => $devices,
            'outcomes' => $outcomes,
            'scores' => $this->settings->categoryPriorities(),
            'categoryLabels' => array_map(fn (array $c) => $c['label'], $rules->categories()),
            'intervalDays' => $this->settings->requestIntervalDays(),
            'minIntervalDays' => SystemSettings::MIN_REQUEST_INTERVAL_DAYS,
            'mail' => $mail->current(),
            'payoutSettings' => $this->payoutSettings($payouts),
            'boxAccounts' => $this->boxAccounts(),
            'mailPending' => $mail->pending(),
            'logs' => $this->logs($auditLogs),
            'selfId' => (string) Auth::id(),
        ]);
    }

    /**
     * Everything about one request for the super admin's final check: the requester's account
     * and earlier requests, the submitted details and documents, the social worker's assessment,
     * the amount needed, the admin's review and verification notes, the AI assessment, the conversation and
     * the history. Read only, except for the final decision.
     */
    public function showRequest(string $id, FundingRequestPresenter $presenter, RequestMessageService $messages, \App\Services\Payouts\PayoutService $payouts, FundQueue $queue, TransactionLog $ledger)
    {
        $request = $this->fundingService->getRequestById($id);
        abort_if($request === null, 404);

        // A transfer in progress: ask the provider how it ended (at most every 15 seconds).
        if (($request['disbursement']['payout']['status'] ?? null) === 'processing' && \Illuminate\Support\Facades\Cache::add("payout-sync:{$id}", true, 15)) {
            try {
                $payouts->sync($id);
                $request = $this->fundingService->getRequestById($id) ?? $request;
            } catch (\App\Services\Payouts\PayoutException $exception) {
                report($exception);
            }
        }

        $view = $presenter->present($request, forAdmin: true);
        $requester = ! empty($request['user_id']) ? $this->users->findById($request['user_id']) : null;
        $earlier = array_values(array_filter(
            $requester ? $this->fundingService->getRequestsByUser($requester['id']) : [],
            fn (array $other): bool => (string) $other['id'] !== $id
        ));

        return view('supperAdminPage.request-show', [
            'view' => $view,
            'requester' => $requester,
            'earlier' => array_map(fn (array $other): array => [
                'id' => (string) $other['id'],
                'organization' => $other['org_name'] ?? '—',
                'status' => $this->fundingService->statusOf($other),
                'granted' => $this->fundingService->grantedAmountOf($other),
                'created_at' => $other['created_at'] ?? null,
            ], $earlier),
            'thread' => $messages->thread($id, 'staff'),
            'availableFunds' => $this->iotService->getAvailableFunds(),
            'line' => $queue->rowFor($id),
            'transactions' => $ledger->latest($id),
            'outcomes' => $this->outcomes(),
            'transfer' => in_array($view['stage']['key'], ['awaiting_recipients', 'to_release', 'released'], true) || ! empty($view['disbursement']['payout'])
                ? $payouts->readiness($request) + ['enabled' => $payouts->enabled(), 'code_sent' => session('pending_payout') === $id,
                    'mode' => $payouts->mode(), 'mode_label' => $payouts->modeLabel($payouts->mode()), 'balance' => $payouts->balance()]
                : null,
        ]);
    }

    public function finalize(Request $request, string $id, NotificationService $notifications, TransactionLog $ledger, FundQueue $queue)
    {
        $validated = $request->validate([
            'decision' => 'required|in:approved,rejected',
            'amount' => 'required_if:decision,approved|nullable|numeric|min:1',
            'notes' => 'nullable|string|max:1000',
        ]);

        $fundingRequest = $this->fundingService->getRequestById($id);
        abort_if($fundingRequest === null, 404);
        $back = fn () => $request->input('from') === 'detail'
            ? redirect()->route('superadmin.fund-request.show', $id)
            : $this->toSection('requests');
        $requested = (float) ($fundingRequest['amount_requested'] ?? $fundingRequest['amount'] ?? 0);
        if ($validated['decision'] === 'approved' && (float) $validated['amount'] > $requested) {
            return $back()->with('alert_error', 'The approved amount cannot be more than the amount requested.');
        }

        $updated = $this->fundingService->finalizeRequest(
            $id,
            $validated['decision'],
            Auth::id(),
            $validated['decision'] === 'approved' ? (float) $validated['amount'] : null,
            $validated['notes'] ?? null
        );

        if ($updated === null) {
            return $back()->with('alert_error', 'This request is not awaiting finalization.');
        }

        $approved = $validated['decision'] === 'approved';
        $organization = $fundingRequest['org_name'] ?? 'a request';
        $this->audit->record('funding', 'Final decision: ' . $validated['decision']
            . ($approved ? ' · ₱' . number_format((float) $validated['amount'], 2) : ''), $id,
            ['organization' => $fundingRequest['org_name'] ?? null]);
        $notifications->notifyAdmins($id, 'final_decision', $approved ? 'Request approved · in the release line' : 'Request rejected',
            $approved
                ? "The super admin approved ₱" . number_format((float) $validated['amount'], 2) . " for {$organization}. It joins the release line (first come, first served); you are told when the donations on hand cover it."
                : "The super admin rejected the request from {$organization}." . (! empty($validated['notes']) ? " Note: {$validated['notes']}" : ''));
        if ($approved) {
            $ledger->record('approved', $fundingRequest, (float) $validated['amount'], ['note' => $validated['notes'] ?? null]);
            // The donations on hand may already cover it.
            $queue->refresh();
        }

        return $back()
            ->with('status', 'Request ' . ($approved ? 'approved' : 'rejected') . '. The requester and the admins have been notified.');
    }

    /**
     * Change an account's role (user/admin) or enable/disable it. Super admin accounts and your
     * own account cannot be changed here.
     */
    public function updateAccount(Request $request, string $id)
    {
        $validated = $request->validate([
            'role' => ['required', Rule::in(['user', 'admin'])],
            'active' => 'required|boolean',
        ]);

        $account = $this->users->findById($id);
        abort_if($account === null, 404);
        if ((string) $id === (string) Auth::id()) {
            return $this->toSection('accounts')->with('alert_error', 'You cannot change your own account here.');
        }
        if (EnsureRole::resolveRole((object) $account) === 'super_admin') {
            return $this->toSection('accounts')->with('alert_error', 'Super admin accounts cannot be changed from this page.');
        }

        $active = (bool) $validated['active'];
        $this->users->update($id, ['role' => $validated['role'], 'is_active' => $active]);

        $name = trim(($account['fname'] ?? '') . ' ' . ($account['lname'] ?? '')) ?: ($account['email'] ?? $id);
        $this->audit->record('account', "Account updated: {$name} → " . ucfirst($validated['role']) . ', ' . ($active ? 'enabled' : 'disabled'), $id);

        return $this->toSection('accounts')->with('status', "{$name} is now " . ($validated['role'] === 'admin' ? 'an administrator' : 'a user') . ' with ' . ($active ? 'enabled' : 'disabled') . ' access.');
    }

    public function updateScores(Request $request, FundingRules $rules)
    {
        // Form keys are slugs ("hygiene-cleaning-supplies"); category names contain spaces and "&".
        $slugs = collect(array_keys($rules->categories()))->mapWithKeys(fn ($name) => [\Illuminate\Support\Str::slug($name) => $name]);
        $validated = $request->validate(array_merge(['scores' => 'required|array'],
            $slugs->keys()->mapWithKeys(fn ($slug) => ["scores.{$slug}" => 'required|integer|min:0|max:100'])->all()),
            ['scores.*.required' => 'Enter a score for every category.']);

        $scores = $slugs->mapWithKeys(fn ($name, $slug) => [$name => (int) $validated['scores'][$slug]])->all();
        $this->settings->setCategoryPriorities($scores);
        $this->audit->record('settings', 'AI scoring priorities updated', null, $scores);

        return $this->toSection('settings')->with('status', 'Category priority scores saved. The AI recommendations now use them.');
    }

    public function updateInterval(Request $request)
    {
        $validated = $request->validate(['days' => 'required|integer|min:' . SystemSettings::MIN_REQUEST_INTERVAL_DAYS . '|max:1000'],
            ['days.min' => 'The interval must be greater than 92 days.']);

        $before = $this->settings->requestIntervalDays();
        $this->settings->setRequestIntervalDays((int) $validated['days']);
        $this->audit->record('settings', "Request interval changed from {$before} to {$validated['days']} days");

        return $this->toSection('settings')->with('status', "Requests must now be at least {$validated['days']} days apart.");
    }

    /**
     * Step 1 of changing the sender email: send a code using the new credentials.
     */
    public function startMailChange(Request $request, MailSettingsService $mail)
    {
        $brevo = $mail->usesBrevo();
        $validated = $request->validate($brevo ? [
            'email' => 'required|email|max:255',
            'app_name' => 'required|string|max:80',
            // A new key is optional once one is saved; Brevo API keys start with "xkeysib-".
            'api_key' => [$mail->brevoKey() ? 'nullable' : 'required', 'string', 'starts_with:xkeysib-', 'max:255'],
        ] : [
            'email' => 'required|email|max:255',
            'app_name' => 'required|string|max:80',
            'password' => 'required|string|min:8|max:255',
        ], [
            'api_key.required' => 'Enter the Brevo API key.',
            'api_key.starts_with' => 'That is not a Brevo API key. API keys start with "xkeysib-" (an SMTP key, "xsmtpsib-", does not work here). Create one in Brevo under SMTP & API → API keys.',
        ]);

        try {
            $mail->startChange($validated['email'], $validated['app_name'], $brevo ? ($validated['api_key'] ?? null) : $validated['password']);
        } catch (\Throwable $exception) {
            return $this->toSection('settings-mail')->withInput($request->except('api_key', 'password'))
                ->with('alert_error', $this->mailErrorHint($exception, $brevo));
        }

        return $this->toSection('settings-mail')->with('status', "A 6-digit code was sent to {$validated['email']} using the new settings. Enter it to save them.");
    }

    /**
     * Send a test email to the super admin with the settings in use now.
     */
    public function testMail(MailSettingsService $mail)
    {
        $user = Auth::user();
        if (! $mail->canSend()) {
            return $this->toSection('settings-mail')->with('alert_error', 'Email is not set up yet. Save the Brevo API key and sender first.');
        }

        try {
            \Illuminate\Support\Facades\Mail::to($user->email)->send(new \App\Mail\MailTestMessage($user->fname ?: 'there'));
        } catch (\Throwable $exception) {
            report($exception);

            return $this->toSection('settings-mail')->with('alert_error', $this->mailErrorHint($exception, $mail->usesBrevo()));
        }

        return $this->toSection('settings-mail')->with('status', "Test email sent to {$user->email}. If it is not in the inbox within a minute, check the spam folder.");
    }

    private function mailErrorHint(\Throwable $exception, bool $brevo): string
    {
        $message = $exception->getMessage();
        $hint = match (true) {
            ! $brevo => 'The mail server rejected these settings. Check the email and app password.',
            str_contains($message, '(401)') || stripos($message, 'key not found') !== false => 'Brevo did not accept the API key. Copy it again from Brevo (SMTP & API → API keys).',
            stripos($message, 'sender') !== false => 'Brevo refused this sender. Add and verify the address in Brevo (Senders, domains & IPs) first.',
            default => 'The email could not be sent.',
        };

        return $hint . ' (' . mb_strimwidth($message, 0, 160, '…') . ')';
    }

    /**
     * Step 2: confirm the code, then save the sender (MAIL_* keys in .env, or Firebase with Brevo).
     */
    public function confirmMailChange(Request $request, MailSettingsService $mail)
    {
        if ($request->boolean('cancel')) {
            $mail->cancel();

            return $this->toSection('settings-mail')->with('status', 'Email settings change cancelled.');
        }

        $validated = $request->validate(['code' => 'required|digits:6']);
        $pending = $mail->pending();
        if ($error = $mail->confirm($validated['code'])) {
            return $this->toSection('settings-mail')->with('alert_error', $error);
        }

        $this->audit->record('settings', 'Verification email sender changed to ' . ($pending['email'] ?? 'a new address')
            . (($pending['new_key'] ?? false) ? ' (new Brevo API key saved)' : ''));

        return $this->toSection('settings-mail')->with('status', 'Email settings saved. Verification codes are now sent from ' . ($pending['email'] ?? 'the new address') . '.');
    }

    /**
     * System monitor → Box sign-ins: create a donation box's Firebase sign-in, or give it a new
     * password. The password is shown once, to be put in the box.
     */
    public function issueBoxSignIn(string $box, \App\Services\BoxAccountService $boxes)
    {
        $known = array_column($this->iotService->getBoxes() ?? [], 'id');
        abort_unless(in_array($box, array_map('strval', $known), true), 404);

        try {
            $credentials = $boxes->issue($box);
        } catch (\Throwable $exception) {
            report($exception);

            return $this->toSection('monitor')->with('alert_error', 'The box sign-in could not be created: ' . $exception->getMessage());
        }
        $this->audit->record('settings', "Sign-in created for donation box {$box}", null, ['email' => $credentials['email']]);

        return $this->toSection('monitor')->with('box_credentials', ['box' => $box, 'email' => $credentials['email'], 'password' => $credentials['password']]);
    }

    /**
     * System settings → Bank transfers: how transfers are connected, and the payout channel of each
     * bank / e-wallet (the provider's list is read at most once an hour).
     */
    private function payoutSettings(\App\Services\Payouts\PayoutService $payouts): array
    {
        $available = [];
        $error = null;
        if ($payouts->enabled()) {
            try {
                $available = $payouts->channels();
            } catch (\App\Services\Payouts\PayoutException $exception) {
                $error = $exception->getMessage();
            }
        }
        $providers = collect(config('funding.transfer_modes'))->map(fn (array $mode) => $mode['providers'])->all();

        return [
            'driver' => config('payouts.driver'),
            'enabled' => $payouts->enabled(),
            'mode' => $payouts->mode(),
            'mode_label' => $payouts->modeLabel($payouts->mode()),
            'key_set' => filled(config('payouts.xendit.secret_key')),
            'webhook_set' => filled(config('payouts.xendit.webhook_token')),
            'webhook_url' => route('webhooks.payouts'),
            'balance' => $payouts->balance(),
            'providers' => $providers,
            'codes' => collect($providers)->flatten()->mapWithKeys(fn ($provider) => [$provider => $payouts->channelFor($provider)])->all(),
            'saved' => $this->settings->payoutChannels(),
            'available' => $available,
            'error' => $error,
        ];
    }

    private function accounts(): array
    {
        $accounts = array_map(function (array $user): array {
            $role = EnsureRole::resolveRole((object) $user) ?? 'user';

            return [
                'id' => (string) $user['id'],
                'name' => trim(($user['fname'] ?? '') . ' ' . ($user['lname'] ?? '')) ?: ($user['name'] ?? 'No name'),
                'email' => $user['email'] ?? '—',
                'phone' => $user['phone'] ?? null,
                'role' => $role,
                'active' => filter_var($user['is_active'] ?? true, FILTER_VALIDATE_BOOLEAN),
                'last_login' => $user['last_login_at'] ?? null,
                'created_at' => $user['created_at'] ?? null,
            ];
        }, $this->users->all());

        usort($accounts, fn (array $a, array $b): int => [$this->roleRank($a['role']), $a['name']] <=> [$this->roleRank($b['role']), $b['name']]);

        return $accounts;
    }

    private function roleRank(string $role): int
    {
        return ['super_admin' => 0, 'admin' => 1, 'user' => 2][$role] ?? 3;
    }

    private function devices(): array
    {
        $now = now()->getTimestampMs();

        return array_map(function (array $box) use ($now): array {
            $lastSeen = (int) ($box['lastSeen'] ?? 0);
            $lastSeen = $lastSeen > 0 && $lastSeen < 1_000_000_000_000 ? $lastSeen * 1000 : $lastSeen;

            return [
                'id' => $box['id'],
                'location' => $box['location'] ?? '—',
                'online' => $lastSeen > 0 && ($now - $lastSeen) <= 20_000,
                'last_seen' => $lastSeen ? Carbon::createFromTimestampMs($lastSeen)->toIso8601String() : null,
                'total' => (float) ($box['total'] ?? 0),
            ];
        }, $this->iotService->getBoxes() ?? []);
    }

    private function outcomes(): array
    {
        $approved = 0; $rejected = 0; $awaiting = 0; $amount = 0.0;
        foreach ($this->fundingService->getAllRequests() as $request) {
            $status = $this->fundingService->statusOf($request);
            if (in_array($status, ['approved', 'completed'], true)) {
                $approved++;
                $amount += $this->fundingService->grantedAmountOf($request);
            } elseif ($status === 'rejected') {
                $rejected++;
            } elseif ($status === FundingService::STATUS_UNDER_REVIEW) {
                $awaiting++;
            }
        }
        $decided = $approved + $rejected;

        return ['approved' => $approved, 'rejected' => $rejected, 'awaiting' => $awaiting, 'amount' => $amount,
            'rate' => $decided ? (int) round($approved / $decided * 100) : 0];
    }

    private function logs(FirebaseAuditLogRepository $auditLogs): array
    {
        try {
            return array_map(fn (array $log): array => [
                'action' => $log['action'] ?? '',
                'category' => $log['category'] ?? 'other',
                'actor' => $log['actor'] ?? 'System',
                'at' => $log['created_at'] ?? null,
            ], $auditLogs->latest(300));
        } catch (\Throwable) {
            return [];
        }
    }

    /** Box id => whether it has its own sign-in (null when it cannot be read). */
    private function boxAccounts(): ?array
    {
        try {
            return app(\App\Services\BoxAccountService::class)->accounts();
        } catch (\Throwable $exception) {
            report($exception);

            return null;
        }
    }

    private function toSection(string $section)
    {
        return redirect()->to(route('superadmin') . '#' . $section);
    }
}
