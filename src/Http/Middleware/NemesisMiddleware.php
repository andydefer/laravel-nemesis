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
        dd('heell');
        $origin = $request->headers->get('Origin');

        // Gestion des requêtes OPTIONS (preflight)
        if ($request->getMethod() === 'OPTIONS') {
            return $this->handlePreflightRequest($origin);
        }

        // --- Cas "same domain" ou Origin manquant
        if (!$origin || $this->isSameDomain($origin)) {
            $response = $next($request);
            return $this->withCorsHeaders($response, $origin);
        }

        // --- Vérification du token pour appels cross-domain
        $tokenValue = $request->bearerToken() ?? $request->query('token');

        if (!$tokenValue) {
            return $this->blockedResponse('Missing API token', $origin);
        }

        $token = NemesisToken::where('token', $tokenValue)->first();

        if (!$token) {
            return $this->blockedResponse('Invalid API token', $origin);
        }

        if (!$this->originAllowed($origin, $token->allowed_origins)) {
            return $this->blockedResponse('Origin not allowed', $origin);
        }

        $maxRequests = $token->max_requests ?? config('nemesis.default_max_requests');

        if ($token->requests_count >= $maxRequests) {
            return $this->blockedResponse('Request limit exceeded', $origin);
        }

        // Incrémenter compteur
        $token->increment('requests_count', 1, ['last_request_at' => now()]);

        $response = $next($request);
        return $this->withCorsHeaders($response, $origin);
    }

    private function handlePreflightRequest(?string $origin): Response
    {
        return response()->noContent(204)
            ->header('Access-Control-Allow-Origin', $origin ?? '*')
            ->header('Access-Control-Allow-Methods', 'GET, POST, PUT, DELETE, OPTIONS')
            ->header('Access-Control-Allow-Headers', 'Authorization, Content-Type, X-Requested-With')
            ->header('Access-Control-Max-Age', '86400'); // 24 heures
    }

    private function isSameDomain(string $origin): bool
    {
        $appUrl = config('app.url');
        $app = parse_url($appUrl);
        $req = parse_url($origin);

        $appHost = $app['host'] ?? null;
        $reqHost = $req['host'] ?? null;

        // Si les hôtes sont différents, ce n'est pas le même domaine
        if ($appHost !== $reqHost) {
            return false;
        }

        // Comparer les ports (avec valeurs par défaut)
        $appPort = $app['port'] ?? ($app['scheme'] === 'https' ? 443 : 80);
        $reqPort = $req['port'] ?? ($req['scheme'] === 'https' ? 443 : 80);

        return $appPort === $reqPort;
    }

    private function originAllowed(string $origin, $allowed): bool
    {
        if (empty($allowed)) {
            return false; // Par défaut, refuser si aucune origine n'est configurée
        }

        // Cast JSON string en tableau si nécessaire
        if (is_string($allowed)) {
            $allowed = json_decode($allowed, true) ?: [];
        }

        foreach ($allowed as $pattern) {
            // Échapper les caractères spéciaux de regex sauf *
            $regexPattern = preg_quote($pattern, '/');
            // Remplacer les étoiles par .* pour le pattern matching
            $regexPattern = str_replace('\\*', '.*', $regexPattern);

            if (preg_match('/^' . $regexPattern . '$/i', $origin)) {
                return true;
            }
        }

        return false;
    }

    private function blockedResponse(string $message, ?string $origin): Response
    {
        $status = config('nemesis.block_response.status', 429);

        return response()->json([
            'message' => config('nemesis.block_response.message', 'Accès refusé') . ' (' . $message . ')',
        ], $status)
            ->withHeaders($this->getCorsHeaders($origin));
    }

    private function withCorsHeaders(Response $response, ?string $origin): Response
    {
        foreach ($this->getCorsHeaders($origin) as $key => $value) {
            $response->headers->set($key, $value);
        }

        return $response;
    }

    private function getCorsHeaders(?string $origin): array
    {
        return [
            'Access-Control-Allow-Origin' => $origin ?? '*',
            'Access-Control-Allow-Methods' => 'GET, POST, PUT, DELETE, OPTIONS',
            'Access-Control-Allow-Headers' => 'Authorization, Content-Type, X-Requested-With',
            'Access-Control-Allow-Credentials' => 'true',
        ];
    }
}
