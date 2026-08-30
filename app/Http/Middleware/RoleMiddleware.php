<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class RoleMiddleware
{
    public function handle(
        Request $request,
        Closure $next,
        string $role
    ): Response {
        $user = $request->user();

        if (!$user) {
            return response()->json([
                'status' => 401,
                'message' => 'Unauthenticated.',
            ], 401);
        }

        if ($user->role !== $role) {
            return response()->json([
                'status' => 403,
                'message' => 'You do not have permission to access this resource.',
            ], 403);
        }

        return $next($request);
    }
}