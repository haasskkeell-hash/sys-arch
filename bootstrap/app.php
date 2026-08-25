<?php

use App\Http\Middleware\EnsureUserHasRole;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->trustProxies(at: '*');

        $middleware->alias([
            'role' => EnsureUserHasRole::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );

        $exceptions->reportable(function (Throwable $exception): void {
            if (! $exception instanceof QueryException && ! $exception instanceof PDOException) {
                return;
            }

            Log::error('Database operation failed.', [
                'event' => 'database_operation_failed',
                'exception' => $exception::class,
                'code' => (string) $exception->getCode(),
                'connection' => $exception instanceof QueryException
                    ? $exception->getConnectionName()
                    : config('database.default'),
            ]);
        });
    })->create();
