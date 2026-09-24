<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Symfony\Component\HttpKernel\Exception\MethodNotAllowedHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use App\Http\Middleware\RoleMiddleware;
use App\Http\Middleware\SecurityHeaders;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        channels: __DIR__.'/../routes/channels.php',
        health: '/up',
    )

    ->withMiddleware(function (Middleware $middleware): void {
            $middleware->trustProxies(
            at: [
                '103.21.244.0/22',
                '103.22.200.0/22',
                '103.31.4.0/22',
                '104.16.0.0/13',
                '104.24.0.0/14',
                '108.162.192.0/18',
                '131.0.72.0/22',
                '141.101.64.0/18',
                '162.158.0.0/15',
                '172.64.0.0/13',
                '173.245.48.0/20',
                '188.114.96.0/20',
                '190.93.240.0/20',
                '197.234.240.0/22',
                '198.41.128.0/17',
                '2400:cb00::/32',
                '2606:4700::/32',
                '2803:f800::/32',
                '2405:b500::/32',
                '2405:8100::/32',
                '2a06:98c0::/29',
                '2c0f:f248::/32',
            ],
            headers: Request::HEADER_X_FORWARDED_FOR
                | Request::HEADER_X_FORWARDED_HOST
                | Request::HEADER_X_FORWARDED_PORT
                | Request::HEADER_X_FORWARDED_PROTO
        );
        $middleware->api(append: [
                SecurityHeaders::class,
            ]);
        $middleware->alias([
            'role' => RoleMiddleware::class,
        ]);
    })

    ->withExceptions(function (Exceptions $exceptions): void {

        /*
        |--------------------------------------------------------------------------
        | API errors ziwe JSON
        |--------------------------------------------------------------------------
        */
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) =>
                $request->is('api/*') || $request->expectsJson(),
        );

        /*
        |--------------------------------------------------------------------------
        | 404 - API route haipo
        |--------------------------------------------------------------------------
        */
        $exceptions->render(
            function (
                NotFoundHttpException $e,
                Request $request
            ) {

                if ($request->is('api/*')) {
                    return response()->json([
                        'status' => 404,
                        'message' => 'API endpoint not found.',
                    ], 404);
                }

                return null;
            }
        );

        /*
        |--------------------------------------------------------------------------
        | 405 - HTTP method hairuhusiwi
        |--------------------------------------------------------------------------
        */
        $exceptions->render(
            function (
                MethodNotAllowedHttpException $e,
                Request $request
            ) {

                if ($request->is('api/*')) {
                    return response()->json([
                        'status' => 405,
                        'message' => 'HTTP method not allowed.',
                    ], 405);
                }

                return null;
            }
        );
    })

    ->create();
