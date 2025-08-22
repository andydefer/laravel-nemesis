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

        // 1. Gestion des requêtes OPTIONS (preflight) - TOUJOURS autoriser
        if ($request->isMethod('OPTIONS')) {
            return $this->handlePreflightRequest($origin);
        }

        // 2. Préparer la réponse avec les headers CORS
        $response = $next($request);
        $response = $this->withCorsHeaders($response, $origin);

        // 3. Si pas d'origine ou même domaine → AUTORISER sans token
        if (!$origin || $this->isSameDomain($origin)) {
            return $response;
        }

        // 4. Vérification cross-domain avec token
        $tokenValue = $this->extractToken($request);

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

        // 5. Incrémenter le compteur
        $token->update([
            'requests_count' => $token->requests_count + 1,
            'last_request_at' => now()
        ]);

        return $response;
    }

    /**
     * Extrait le token de la requête (Bearer ou query param)
     */
    private function extractToken(Request $request): ?string
    {
        $tokenSources = config('nemesis.token_sources', [
            'bearer',
            'query:token',
            'query:api_token',
        ]);

        foreach ($tokenSources as $source) {
            if (strpos($source, 'query:') === 0) {
                $param = substr($source, 6);
                if ($request->has($param)) {
                    return $request->query($param);
                }
            } elseif ($source === 'bearer') {
                if ($token = $request->bearerToken()) {
                    return $token;
                }
            }
        }

        return null;
    }

    /**
     * Gère les requêtes OPTIONS (preflight CORS)
     */
    private function handlePreflightRequest(?string $origin): Response
    {
        return response()->noContent(204)
            ->header('Access-Control-Allow-Origin', $origin ?? '*')
            ->header('Access-Control-Allow-Methods', 'GET, POST, PUT, DELETE, OPTIONS')
            ->header('Access-Control-Allow-Headers', 'Authorization, Content-Type, X-Requested-With, X-CSRF-TOKEN')
            ->header('Access-Control-Allow-Credentials', 'true')
            ->header('Access-Control-Max-Age', '86400'); // Cache pour 24h
    }

    /**
     * Vérifie si l'origine est la même que l'application
     */
    private function isSameDomain(string $origin): bool
    {
        $appUrl = config('app.url');

        if (!$appUrl) {
            return false;
        }

        $app = parse_url($appUrl);
        $req = parse_url($origin);

        // Comparer les hosts
        $appHost = $app['host'] ?? null;
        $reqHost = $req['host'] ?? null;

        if ($appHost !== $reqHost) {
            return false;
        }

        // Comparer les ports (avec valeurs par défaut)
        $appPort = $app['port'] ?? (($app['scheme'] ?? 'http') === 'https' ? 443 : 80);
        $reqPort = $req['port'] ?? (($req['scheme'] ?? 'http') === 'https' ? 443 : 80);

        return $appPort === $reqPort;
    }

    /**
     * Vérifie si l'origine est autorisée pour le token
     */
    private function originAllowed(string $origin, $allowed): bool
    {
        if (empty($allowed)) {
            return false;
        }

        // Convertir en tableau si c'est une chaîne JSON
        if (is_string($allowed)) {
            $allowed = json_decode($allowed, true) ?: [];
        }

        foreach ($allowed as $pattern) {
            $pattern = trim($pattern);

            // Wildcard global
            if ($pattern === '*') {
                return true;
            }

            // Comparaison exacte
            if ($pattern === $origin) {
                return true;
            }

            // Pattern avec wildcard
            if (strpos($pattern, '*') !== false) {
                $regex = $this->patternToRegex($pattern);
                if (preg_match($regex, $origin)) {
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * Convertit un pattern avec wildcards en regex
     */
    private function patternToRegex(string $pattern): string
    {
        $regex = preg_quote($pattern, '/');
        $regex = str_replace('\\*', '.*', $regex);
        return '/^' . $regex . '$/i';
    }

    /**
     * Réponse de blocage avec headers CORS
     */
    private function blockedResponse(string $message, ?string $origin): Response
    {
        $status = config('nemesis.block_response.status', 429);
        $defaultMessage = config('nemesis.block_response.message', 'Accès refusé');

        $response = response()->json([
            'message' => $defaultMessage . ' (' . $message . ')',
            'error_code' => 'NEMESIS_BLOCKED',
        ], $status);

        return $this->withCorsHeaders($response, $origin);
    }

    /**
     * Ajoute les headers CORS à une réponse
     */
    private function withCorsHeaders(Response $response, ?string $origin): Response
    {
        $corsConfig = config('nemesis.cors', []);

        $headers = [
            'Access-Control-Allow-Origin' => $origin ?? '*',
            'Access-Control-Allow-Methods' => $corsConfig['allow_methods'] ?? 'GET, POST, PUT, DELETE, OPTIONS, PATCH, HEAD',
            'Access-Control-Allow-Headers' => $corsConfig['allow_headers'] ?? 'Authorization, Content-Type, X-Requested-With, X-CSRF-TOKEN, Accept',
            'Access-Control-Allow-Credentials' => $corsConfig['allow_credentials'] ?? 'true' ? 'true' : 'false',
            'Access-Control-Expose-Headers' => implode(', ', $corsConfig['expose_headers'] ?? ['X-RateLimit-Limit', 'X-RateLimit-Remaining', 'X-RateLimit-Reset']),
        ];

        if (isset($corsConfig['max_age'])) {
            $headers['Access-Control-Max-Age'] = $corsConfig['max_age'];
        }

        foreach ($headers as $key => $value) {
            $response->headers->set($key, $value);
        }

        return $response;
    }
}
