<?php

use App\Exceptions\DomainException;
use App\Http\Middleware\HandleInertiaRequests;
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
        $middleware->throttleApi();
        $middleware->web(append: [HandleInertiaRequests::class]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );

        // Domain (business-rule) exceptions render as a stable JSON envelope
        // for API/JSON callers: {"message": "...", "error_code": "..."}.
        // Inertia requests receive a validation-style error bag so the browser
        // can reconcile optimistic state with the canonical response.
        $exceptions->render(function (DomainException $e, Request $request) {
            if ($request->is('api/*') || $request->expectsJson()) {
                return response()->json([
                    'message' => $e->getMessage(),
                    'error_code' => $e->errorCode(),
                ], $e->httpStatus());
            }

            if ($request->header('X-Inertia')) {
                return back()->withErrors(['domain' => $e->getMessage()]);
            }
        });
    })->create();
