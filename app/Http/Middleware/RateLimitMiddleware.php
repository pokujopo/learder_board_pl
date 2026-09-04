<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Cache\RateLimiter;
use Symfony\Component\HttpFoundation\Response;

class RateLimitMiddleware
{
    /**
     * RateLimiter instance
     */
    protected RateLimiter $limiter;

    public function __construct(RateLimiter $limiter)
    {
        $this->limiter = $limiter;
    }

    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */

public function handle(Request $request, Closure $next): Response
{
    /*
    |--------------------------------------------------------------------------
    | Disable rate limiting on local environment
    |--------------------------------------------------------------------------
    |
    | This allows load testing with k6 without receiving 429 responses.
    | Rate limiting remains fully active in production.
    |
    */

    if (app()->environment('local')) {
        return $next($request);
    }

    // Get client identifier (IP or user ID if authenticated)
    $identifier = $this->getIdentifier($request);

    // Different limits for different endpoints
    $limit = $this->getLimit($request);
    $decay = 60;

    // Check if rate limit exceeded
    if ($this->limiter->tooManyAttempts($identifier, $limit, $decay)) {

        $retryAfter = $this->limiter->availableIn($identifier);

        return response()->json([
            'status' => 429,
            'message' => 'Too many requests. Please try again in ' . $retryAfter . ' seconds.',
            'retry_after' => $retryAfter,
        ], 429)->header('Retry-After', $retryAfter);
    }

    // Increment attempt counter
    $this->limiter->hit($identifier, $decay);

    // Add rate limit info to response headers
    $response = $next($request);

    return $response
        ->header('X-RateLimit-Limit', $limit)
        ->header(
            'X-RateLimit-Remaining',
            $this->limiter->remaining($identifier, $limit)
        )
        ->header(
            'X-RateLimit-Reset',
            now()->addSeconds($decay)->timestamp
        );
}


    /**
     * Get the rate limit for the request.
     */
    private function getLimit(Request $request): int
    {
        if ($request->is('api/v1/auth/login') || $request->is('api/v1/auth/register') || $request->is('api/v1/auth/forgot-password') || $request->is('api/v1/auth/reset-password')) return 10;
        if ($request->is('api/v1/auth/refresh')) return 20;
        if ($request->is('api/v1/competitions/*/join')) return 10;
        if ($request->is('api/v1/admin/*')) return 120;
        if ($request->is('api/v1/competitions/*/leaderboard*')) return 100;
        return 60;
    }

    /**
     * Get the identifier for rate limiting.
     */
    private function getIdentifier(Request $request): string
    {
        // If user is authenticated, use user ID (allows higher limits per authenticated user)
        if ($request->user()) {
            return 'user:' . $request->user()->id;
        }

        // Use IP address for unauthenticated requests
        return 'ip:' . ($request->ip() ?? '0.0.0.0');
    }
}
