<?php

namespace Kani\Nemesis\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Kani\Nemesis\Models\NemesisToken;

class NemesisMiddleware
{
    public function handle(Request $request, Closure $next): Response
    {
        $tokenValue = $request->bearerToken();
        if (! $tokenValue) {
            return response()->json(['message' => 'Missing API token'], 401);
        }

        $token = NemesisToken::where('token', $tokenValue)->first();
        if (! $token) {
            return response()->json(['message' => 'Invalid API token'], 403);
        }

        $origin = $request->headers->get('Origin');
        if ($origin && ! $this->originAllowed($origin, $token->allowed_origins)) {
            return response()->json(['message' => 'Origin not allowed'], 403);
        }

        if ($token->requests_count >= $token->max_requests) {
            return response()->json(['message' => 'Request limit exceeded'], 429);
        }

        $token->increment('requests_count', 1, ['last_request_at' => now()]);

        $response = $next($request);
        $response->headers->set('Access-Control-Allow-Origin', $origin ?? '*');
        $response->headers->set('Access-Control-Allow-Methods', 'GET, POST, PUT, DELETE, OPTIONS');
        $response->headers->set('Access-Control-Allow-Headers', 'Authorization, Content-Type');

        return $response;
    }

    private function originAllowed(string $origin, array $allowed): bool
    {
        foreach ($allowed as $pattern) {
            $regex = '/^'.str_replace(['*', '.'], ['.*', '\.'], $pattern).'$/i';
            if (preg_match($regex, $origin)) {
                return true;
            }
        }
        return false;
    }
}
