<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        channels: __DIR__.'/../routes/channels.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware) {
        $middleware->web(append: [
            \App\Http\Middleware\HandleInertiaRequests::class,
            \Illuminate\Http\Middleware\AddLinkHeadersForPreloadedAssets::class,
        ]);

        $middleware->trustProxies([
            '172.16.0.0/16',
            '172.17.0.0/16',
            '172.18.0.0/16',
            '172.19.0.0/16',
            '172.20.0.0/16',
            '172.21.0.0/16',
            '172.22.0.0/16',
            '172.23.0.0/16',
            '172.24.0.0/16',
            '172.25.0.0/16',
            '172.26.0.0/16',
            '172.27.0.0/16',
            '172.28.0.0/16',
            '172.29.0.0/16',
            '172.30.0.0/16',
            '172.31.0.0/16',
            '172.32.0.0/16',
            '172.33.0.0/16',
            '172.34.0.0/16',
            '172.35.0.0/16',
            '172.24.0.0/16',
        ]);
        //
    })
    
    ->withExceptions(function (Exceptions $exceptions) {
        //
    })->create();
