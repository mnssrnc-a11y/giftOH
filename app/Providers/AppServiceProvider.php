<?php

namespace App\Providers;

use App\Auth\FirebaseUserProvider;
use Illuminate\Auth\Events\Login;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\View;
use App\Services\FundingService;
use App\Mail\Transport\BrevoTransport;
use App\Support\Storage\FirebaseDatabaseAdapter;
use Illuminate\Filesystem\FilesystemAdapter;
use Illuminate\Mail\Events\MessageSending;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use League\Flysystem\Filesystem;
use Symfony\Component\Mime\Address as SymfonyAddress;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        // One Firebase connection per request. Every repository and service shares it instead of
        // opening (and signing in to Google) on its own, which cost about 0.9 s each.
        $this->app->singleton(\App\Services\FirebaseService::class);
        // Snapshots of Firebase nodes are per request only.
        $this->app->terminating(fn () => \App\Repositories\FirebaseRepository::flushSnapshots());
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Storage driver that keeps uploads in the Firebase Realtime Database (FILES_DRIVER=firebase).
        Storage::extend('firebase-rtdb', function ($app, array $config) {
            $adapter = new FirebaseDatabaseAdapter(
                $app->make(\App\Services\FirebaseService::class),
                $config['root'] ?? 'file_store/local',
                $config['visibility'] ?? 'private'
            );

            return new FilesystemAdapter(new Filesystem($adapter, $config), $adapter, $config);
        });

        // Gmail SMTP without its app password cannot send anything; mail then goes through Brevo,
        // whose API key the super admin saves in System settings.
        if (config('mail.default') === 'smtp' && blank(config('mail.mailers.smtp.password'))) {
            config(['mail.default' => 'brevo']);
        }

        // Mail over Brevo's HTTPS API. The key is looked up only when a message is sent: the one
        // being tested ("api_key"), else the super admin's saved key, else BREVO_API_KEY.
        Mail::extend('brevo', fn (array $config) => new BrevoTransport(
            (string) ($config['api_key'] ?? app(\App\Services\MailSettingsService::class)->brevoKey() ?? ''),
            (int) ($config['timeout'] ?? 20)
        ));

        // With Brevo, the super admin's chosen sender lives in Firebase (system_settings/mail_sender).
        // Swap it in for the default From address just before a message leaves.
        Event::listen(MessageSending::class, function (MessageSending $event): void {
            if (config('mail.default') !== 'brevo') {
                return;
            }
            $from = $event->message->getFrom()[0] ?? null;
            if ($from !== null && strcasecmp($from->getAddress(), (string) config('mail.from.address')) !== 0) {
                return; // explicitly chosen sender (e.g. the "confirm new sender" email)
            }
            try {
                $sender = app(\App\Services\SystemSettings::class)->mailSender();
            } catch (\Throwable $e) {
                report($e);
                $sender = null;
            }
            if ($sender !== null) {
                $event->message->from(new SymfonyAddress($sender['address'], $sender['name'] ?: (string) config('mail.from.name')));
            }
        });

        Auth::provider('firebase', function ($app, array $config) {
            return new FirebaseUserProvider(
                $app->make(\App\Repositories\FirebaseUserRepository::class)
            );
        });

        // Any page that extends layouts.admin needs $pendingCount for the
        // sidebar badge (see admin.blade.php). Previously only
        // AdminController::admin()/adminApproval() supplied it, so every
        // other admin page (iot-monitor, reports, settings, ...) crashed
        // with "Undefined variable $pendingRequests" the moment it started
        // using this layout. A view composer means the layout is no longer
        // coupled to which controller happens to render it.
        View::composer('layouts.admin', function ($view) {
            try {
                $count = count(app(FundingService::class)->getPendingRequests());
            } catch (\Throwable $e) {
                // Don't let a Firebase hiccup take down every admin page;
                // the badge just shows 0 until the next successful load.
                report($e);
                $count = 0;
            }

            $view->with('pendingCount', $count);
        });

        // Every workspace topbar shows the signed-in account's unread notifications (bell).
        View::composer(['layouts.admin', 'layouts.spAdmin', 'layouts.dashboard'], function ($view) {
            $view->with('navNotifications', Auth::check() ? app(\App\Services\NotificationService::class)->getByUser(Auth::id()) : []);
        });

        // Remember each account's last sign-in for the super admin's account list.
        Event::listen(Login::class, function (Login $event): void {
            try {
                app(\App\Repositories\FirebaseUserRepository::class)->update($event->user->getAuthIdentifier(), [
                    'last_login_at' => now()->toIso8601String(),
                ]);
            } catch (\Throwable $e) {
                report($e);
            }
        });
    }

}
