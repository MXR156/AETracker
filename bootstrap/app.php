<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        // The bookmarklet posts from the Atlas Earth site, so it can't send a CSRF token.
        // This route only stages the data; saving it still needs a CSRF-protected form.
        $middleware->validateCsrfTokens(except: ['import/atlas-earth']);

        // Behind a reverse proxy that adds HTTPS (e.g. when the tracker is public), set TRUSTED_PROXIES
        // to its IP (or "*") so Laravel sees the real https:// address and the visitor's IP.
        if ($proxies = env('TRUSTED_PROXIES')) {
            $middleware->trustProxies(at: $proxies === '*' ? '*' : array_map('trim', explode(',', $proxies)));
        }
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );
    })->create();
