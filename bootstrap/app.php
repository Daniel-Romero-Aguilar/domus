<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->redirectGuestsTo(
            static fn (Request $request): string => $request->is('admin/*')
                ? route('admin.login')
                : url('/login')
        );
        $middleware->redirectUsersTo(
            static fn (Request $request): string => $request->is('admin/*')
                ? route('admin.dashboard')
                : route('dashboard')
        );
        $middleware->append(\App\Http\Middleware\EnsureSpanishApiMessages::class);
        $middleware->append(\App\Http\Middleware\LogFailedUserActions::class);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })->create();
