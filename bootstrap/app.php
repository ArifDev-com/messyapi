<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Support\Facades\Route;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
        then: function () {
            foreach([
                'web.php',
            ] as $f) {
                Route::middleware('web')
                    ->group(base_path('routes/' . $f));
            }
        }
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->validateCsrfTokens(except: [
            '*'
        ]);
    })
    ->withSchedule(function ($schedule) {

    })
    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })->create();
