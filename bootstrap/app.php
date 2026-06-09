<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->append(\App\Http\Middleware\SecurityHeaders::class);
        $middleware->alias([
            'auth.api' => \App\Http\Middleware\AuthenticateApiKey::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        // When PHP drops the entire POST body because it exceeds post_max_size,
        // the CSRF token is lost and Laravel throws a TokenMismatchException (419).
        // Detect this case and redirect back with a friendly error instead.
        $exceptions->render(function (
            \Illuminate\Session\TokenMismatchException $e,
            \Illuminate\Http\Request $request
        ) {
            $contentLength = (int) $request->server('CONTENT_LENGTH', 0);
            $postMaxBytes = \App\Http\Middleware\HandleOversizedUpload::iniToBytes(ini_get('post_max_size'));

            if ($contentLength > 0 && $postMaxBytes > 0 && $contentLength > $postMaxBytes) {
                return redirect()->back()
                    ->withErrors(['attachment' => 'The uploaded file is too large. The maximum total upload size is ' . ini_get('post_max_size') . '.']);
            }
        });
    })->create();
