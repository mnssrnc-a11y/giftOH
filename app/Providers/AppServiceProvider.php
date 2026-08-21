<?php

namespace App\Providers;

use App\Auth\FirebaseUserProvider;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\ServiceProvider;

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
    }
}
