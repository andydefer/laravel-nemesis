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

        // Si origine same-domain ou absente, on laisse passer sans token
        if (!$origin || $this->isSameDomain($origin)) {
            return $this->handlePreflight($request) ?? $next($request);
        }

        // Récupérer le token depuis Authorization Bearer ou query string
        $tokenValue = $request->bearerToken() ?? $request->query('token');

        if (!$tokenValue) {
            return $this->blockedResponse('Missing API token');
        }

        // Vérifier que le token existe
        $token = NemesisToken::where('token', $tokenValue)->first();
        if (!$token) {
            return $this->blockedResponse('Invalid API token');
        }

        // Vérifier que l'origine est autorisée pour ce token
        if (! $this->originAllowed($origin, $token->allowed_origins)) {
            return $this->blockedResponse('Origin not allowed');
        }

        // Vérifier le quota
        $maxRequests = $token->max_requests ?? config('nemesis.default_max_requests');
        if ($token->requests_count >= $maxRequests) {
            return $this->blockedResponse('Request limit exceeded');
        }

        // Incrémenter le compteur
        $token->increment('requests_count', 1, ['last_request_at' => now()]);

        // Traiter preflight OPTIONS si nécessaire
        if ($request->getMethod() === 'OPTIONS') {
            return response()->noContent(204)
                ->header('Access-Control-Allow-Origin', $origin)
                ->header('Access-Control-Allow-Methods', 'GET, POST, PUT, DELETE, OPTIONS')
                ->header('Access-Control-Allow-Headers', 'Authorization, Content-Type');
        }

        $response = $next($request);

        // Ajouter les headers CORS pour la réponse
        $response->headers->set('Access-Control-Allow-Origin', $origin);
        $response->headers->set('Access-Control-Allow-Methods', 'GET, POST, PUT, DELETE, OPTIONS');
        $response->headers->set('Access-Control-Allow-Headers', 'Authorization, Content-Type');

        return $response;
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

    private function originAllowed(string $origin, array $allowed): bool
    {
        if (!$allowed) return true;

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

    private function handlePreflight(Request $request): ?Response
    {
        if ($request->getMethod() !== 'OPTIONS') {
            return null;
        }

        $origin = $request->headers->get('Origin') ?? '*';

        return response()->noContent(204)
            ->header('Access-Control-Allow-Origin', $origin)
            ->header('Access-Control-Allow-Methods', 'GET, POST, PUT, DELETE, OPTIONS')
            ->header('Access-Control-Allow-Headers', 'Authorization, Content-Type');
    }
}
