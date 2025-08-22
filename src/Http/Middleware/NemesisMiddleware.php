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

        // Si l'origine est la même que l'APP_URL ou absente, on laisse passer sans token
        if (!$origin || $this->isSameDomain($origin)) {
            return $next($request);
        }

        // Récupérer le token depuis Authorization ou query string
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

        // Vérifier le nombre maximum de requêtes
        $maxRequests = $token->max_requests ?? config('nemesis.default_max_requests');
        if ($token->requests_count >= $maxRequests) {
            return $this->blockedResponse('Request limit exceeded');
        }

        // Incrémenter le compteur
        $token->increment('requests_count', 1, ['last_request_at' => now()]);

        $response = $next($request);

        // Ajouter les headers CORS
        $response->headers->set('Access-Control-Allow-Origin', $origin ?? '*');
        $response->headers->set('Access-Control-Allow-Methods', 'GET, POST, PUT, DELETE, OPTIONS');
        $response->headers->set('Access-Control-Allow-Headers', 'Authorization, Content-Type');

        return $response;
    }

    private function isSameDomain(string $origin): bool
    {
        $appUrl = config('app.url');
        return stripos($origin, $appUrl) === 0;
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
}
