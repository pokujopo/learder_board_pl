<?php
namespace App\Http\Middleware;

use App\Models\User;
use App\Services\Auth\JwtService;
use Closure;
use Illuminate\Http\Request;
use Throwable;

class JwtAuthMiddleware
{
    public function __construct(private JwtService $jwt) {}
    public function handle(Request $request, Closure $next)
    {
        $header = $request->header('Authorization', '');
        if (!preg_match('/^Bearer\s+(.+)$/i', $header, $m)) return response()->json(['status'=>401,'message'=>'Unauthenticated.'],401);
        try {
            $claims = $this->jwt->decode($m[1]);
            $user = User::find((int) $claims['sub']);
            if (!$user) return response()->json(['status'=>401,'message'=>'Unauthenticated.'],401);
            $request->setUserResolver(fn() => $user);
            $request->attributes->set('jwt_claims', $claims);
            return $next($request);
        } catch (Throwable) { return response()->json(['status'=>401,'message'=>'Invalid or expired access token.'],401); }
    }
}
