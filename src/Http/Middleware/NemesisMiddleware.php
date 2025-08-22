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
        $origin = $request->headers->get('Origin');

        // --- Cas "same domain" ou Origin manquant
        if (!$origin || $this->isSameDomain($origin)) {
            if ($request->getMethod() === 'OPTIONS') {
                return $this->corsResponse($origin);
            }

            $response = $next($request);
            return $this->withCors($response, $origin);
        }

        // --- Vérification du token pour appels cross-domain
        $tokenValue = $request->bearerToken() ?? $request->query('token');
        if (!$tokenValue) {
            return $this->blockedResponse('Missing API token');
        }

        $token = NemesisToken::where('token', $tokenValue)->first();
        if (!$token) {
            return $this->blockedResponse('Invalid API token');
        }

        if (!$this->originAllowed($origin, $token->allowed_origins)) {
            return $this->blockedResponse('Origin not allowed');
        }

        $maxRequests = $token->max_requests ?? config('nemesis.default_max_requests');
        if ($token->requests_count >= $maxRequests) {
            return $this->blockedResponse('Request limit exceeded');
        }

        // Incrémenter compteur
        $token->increment('requests_count', 1, ['last_request_at' => now()]);

        // Preflight CORS
        if ($request->getMethod() === 'OPTIONS') {
            return $this->corsResponse($origin);
        }

        $response = $next($request);
        return $this->withCors($response, $origin);
    }

    private function isSameDomain(string $origin): bool
    {
        $app = parse_url(config('app.url'));
        $req = parse_url($origin);

        $appPort = $app['port'] ?? ($app['scheme'] === 'https' ? 443 : 80);
        $reqPort = $req['port'] ?? ($req['scheme'] === 'https' ? 443 : 80);

        return ($app['host'] ?? null) === ($req['host'] ?? null)
            && $appPort === $reqPort;
    }

    private function originAllowed(string $origin, array|string $allowed): bool
    {
        if (empty($allowed)) return true;

        // Cast JSON string en tableau si nécessaire
        if (is_string($allowed)) {
            $allowed = json_decode($allowed, true) ?: [];
        }

        foreach ($allowed as $pattern) {
            $regex = '/^' . str_replace(['*', '.'], ['.*', '\.'], $pattern) . '$/i';
            if (preg_match($regex, $origin)) {
                return true;
            }
        }

        return false;
    }

    private function blockedResponse(string $message): Response
    {
        return response()->json([
            'message' => $message,
        ], config('nemesis.block_response.status', 429));
    }

    private function corsResponse(?string $origin): Response
    {
        return response()->noContent(204)
            ->header('Access-Control-Allow-Origin', $origin ?? '*')
            ->header('Access-Control-Allow-Methods', 'GET, POST, PUT, DELETE, OPTIONS')
            ->header('Access-Control-Allow-Headers', 'Authorization, Content-Type');
    }

    private function withCors(Response $response, ?string $origin): Response
    {
        $response->headers->set('Access-Control-Allow-Origin', $origin ?? '*');
        $response->headers->set('Access-Control-Allow-Methods', 'GET, POST, PUT, DELETE, OPTIONS');
        $response->headers->set('Access-Control-Allow-Headers', 'Authorization, Content-Type');
        return $response;
    }
}
