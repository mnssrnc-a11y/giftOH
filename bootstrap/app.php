<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use App\Http\Middleware\EnsureActiveAccount;
use App\Http\Middleware\EnsureRole;
use App\Http\Middleware\KeepFlashForBackgroundRequests;
use App\Http\Middleware\ScanUploadedFiles;
use App\Http\Middleware\SecurityHeaders;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->alias([
            'role' => EnsureRole::class,
        ]);
        // Hosts like Render end HTTPS at their load balancer and forward plain HTTP; trusting the
        // forwarded headers keeps generated links, assets and secure cookies on https://.
        $middleware->trustProxies(at: '*');
        // Disabled accounts are signed out on their next request; background fetches keep flash messages;
        // every upload is scanned for malware; every response gets browser security headers.
        $middleware->web(append: [EnsureActiveAccount::class, KeepFlashForBackgroundRequests::class, ScanUploadedFiles::class, SecurityHeaders::class]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        // Secrets typed into a form are never kept in the session when validation fails.
        $exceptions->dontFlash(['current_password', 'password', 'password_confirmation', 'api_key']);
    })->create();
