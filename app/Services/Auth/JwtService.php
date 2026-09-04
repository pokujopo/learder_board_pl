<?php
namespace App\Services\Auth;

use App\Models\User;
use Illuminate\Support\Str;
use RuntimeException;

class JwtService
{
    public function issue(User $user, array $permissions = []): array
    {
        $now = now()->timestamp;
        $payload = [
            'iss' => config('app.url'),
            'aud' => config('app.api_audience', 'referrace-api'),
            'sub' => (string) $user->id,
            'iat' => $now,
            'nbf' => $now,
            'exp' => $now + (int) config('auth.access_token_ttl', 900),
            'jti' => (string) Str::uuid(),
            'scope' => implode(' ', $permissions),
        ];
        return ['access_token' => $this->encode($payload), 'expires_in' => $payload['exp'] - $now];
    }

    public function decode(string $token): array
    {
        $parts = explode('.', $token);
        if (count($parts) !== 3) throw new RuntimeException('Invalid token.');
        [$h, $p, $s] = $parts;
        $header = json_decode($this->base64UrlDecode($h), true);
        $payload = json_decode($this->base64UrlDecode($p), true);
        if (($header['alg'] ?? null) !== 'HS256' || !is_array($payload)) throw new RuntimeException('Invalid token.');
        $expected = $this->base64UrlEncode(hash_hmac('sha256', "$h.$p", $this->secret(), true));
        if (!hash_equals($expected, $s)) throw new RuntimeException('Invalid token.');
        $now = now()->timestamp;
        if (($payload['iss'] ?? null) !== config('app.url') || ($payload['aud'] ?? null) !== config('app.api_audience', 'referrace-api')) throw new RuntimeException('Invalid token claims.');
        if (($payload['nbf'] ?? 0) > $now || ($payload['exp'] ?? 0) <= $now) throw new RuntimeException('Token expired.');
        return $payload;
    }

    private function encode(array $payload): string
    {
        $header = ['typ' => 'JWT', 'alg' => 'HS256'];
        $h = $this->base64UrlEncode(json_encode($header, JSON_UNESCAPED_SLASHES));
        $p = $this->base64UrlEncode(json_encode($payload, JSON_UNESCAPED_SLASHES));
        $s = $this->base64UrlEncode(hash_hmac('sha256', "$h.$p", $this->secret(), true));
        return "$h.$p.$s";
    }
    private function secret(): string
    {
        $key = config('app.key');
        if (!$key) throw new RuntimeException('Application key is not configured.');
        return str_starts_with($key, 'base64:') ? base64_decode(substr($key, 7)) : $key;
    }
    private function base64UrlEncode(string $value): string { return rtrim(strtr(base64_encode($value), '+/', '-_'), '='); }
    private function base64UrlDecode(string $value): string { return base64_decode(strtr($value . str_repeat('=', (4 - strlen($value) % 4) % 4), '-_', '+/')) ?: ''; }
}
