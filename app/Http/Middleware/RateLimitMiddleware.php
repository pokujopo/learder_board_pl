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
        // Get client identifier (IP or user ID if authenticated)
        $identifier = $this->getIdentifier($request);

        // Different limits for different endpoints
        $limit = $this->getLimit($request);
        $decay = 60; // seconds

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
            ->header('X-RateLimit-Remaining', $this->limiter->remaining($identifier, $limit))
            ->header('X-RateLimit-Reset', now()->addSeconds($decay)->timestamp);
    }

    /**
     * Get the rate limit for the request.
     */
    private function getLimit(Request $request): int
    {
        // Auth endpoints: strict rate limiting (10 per minute)
        if ($request->is('api/register') || $request->is('api/login')) {
            return 10;
        }

        // Game endpoints: moderate rate limiting (60 per minute)
        if ($request->is('api/games') || $request->is('api/games/*')) {
            return 60;
        }

        // Profile endpoints: moderate rate limiting (60 per minute)
        if ($request->is('api/profile/*')) {
            return 60;
        }

        // Ranking endpoints: generous rate limiting (100 per minute)
        if ($request->is('api/ranking/*')) {
            return 100;
        }

        // Default: moderate rate limiting (60 per minute)
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
