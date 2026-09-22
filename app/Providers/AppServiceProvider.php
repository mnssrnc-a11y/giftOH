<?php

namespace App\Providers;

use App\Auth\FirebaseUserProvider;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\View;
use App\Services\FundingService;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
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
    }

}
