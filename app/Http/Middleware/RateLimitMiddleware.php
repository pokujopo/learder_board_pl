<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Cache\RateLimiter;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class RateLimitMiddleware
{
    public function __construct(
        protected RateLimiter $limiter
    ) {}

    public function handle(Request $request, Closure $next): Response
    {
        if (app()->environment('local')) {
            return $next($request);
        }

        if ($request->is('ref/*')) {
            $key = $this->getKey($request);
            $limit = 1;
            $decay = 1800; // 30 minutes

            if ($this->limiter->tooManyAttempts($key, $limit, $decay)) {
                return redirect('https://pawacode.com');
            }

    $this->limiter->hit($key, $decay);

    return $next($request);
}

        $key = $this->getKey($request);
        $limit = $this->getLimit($request);
        $decay = 60;

        if ($this->limiter->tooManyAttempts($key, $limit, $decay)) {
            $retryAfter = $this->limiter->availableIn($key);

            return response()->json([
                'status' => 429,
                'message' => 'Too many requests. Please try again later.',
                'retry_after' => $retryAfter,
            ], 429)->header('Retry-After', $retryAfter);
        }

        $this->limiter->hit($key, $decay);

        $response = $next($request);

        return $response
            ->header('X-RateLimit-Limit', $limit)
            ->header(
                'X-RateLimit-Remaining',
                $this->limiter->remaining($key, $limit)
            )
            ->header(
                'X-RateLimit-Reset',
                now()->addSeconds($decay)->timestamp
            );
    }

    private function getLimit(Request $request): int
    {
        if ($request->is('api/v1/auth/login')) {
            return 5;
        }

        if ($request->is('api/v1/auth/login/verify-otp')) {
            return 5;
        }

        if ($request->is('api/v1/auth/register')) {
            return 5;
        }

        if ($request->is('api/v1/auth/forgot-password')) {
            return 5;
        }

        if ($request->is('api/v1/auth/reset-password')) {
            return 5;
        }

        if ($request->is('api/v1/auth/change-password')) {
            return 5;
        }

        if ($request->is('api/v1/auth/change-password/verify-otp')) {
            return 5;
        }

        if ($request->is('api/v1/competitions/*/verify-refercode')) {
            return 5;
        }

        if ($request->is('api/v1/competitions/user/registration')) {
            return 5;
        }

        if ($request->is('api/v1/competitions/*/join')) {
            return 10;
        }

        if ($request->is('api/v1/auth/refresh')) {
            return 20;
        }

        if ($request->is('api/v1/admin/*')) {
            return 120;
        }

        if ($request->is('api/v1/competitions/*/ranking')) {
            return 60;
        }

        if ($request->is('api/v1/competitions/*/ranking/me')) {
            return 60;
        }

        return 60;
    }

    private function getKey(Request $request): string
    {
        $route = $request->route();

        $routeName = $route
            ? $route->uri()
            : $request->path();

        return 'rate-limit:'
            . $request->ip()
            . ':'
            . $routeName;
    }
}
