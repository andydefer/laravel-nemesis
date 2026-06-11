<?php

declare(strict_types=1);

namespace Kani\Nemesis\Services;

use Illuminate\Contracts\Foundation\Application;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Kani\Nemesis\Contracts\Configs\NemesisConfigInterface;

/**
 * Service for applying HTTP headers to responses.
 * Pure service with no business logic - only HTTP header manipulation.
 */
class HttpHeaderService
{
    public function __construct(
        private readonly NemesisConfigInterface $config,
        private readonly Application $app,
    ) {}

    /**
     * Apply security headers to a response.
     */
    public function applySecurityHeaders(mixed $response): mixed
    {
        // ✅ Utilisation de la nouvelle API avec middlewareConfig()
        if (! $this->config->middlewareConfig()->security_headers) {
            return $response;
        }

        if (! method_exists($response, 'header')) {
            return $response;
        }

        $response->header('X-Frame-Options', 'DENY');
        $response->header('X-XSS-Protection', '1; mode=block');
        $response->header('X-Content-Type-Options', 'nosniff');
        $response->header('Referrer-Policy', 'strict-origin-when-cross-origin');

        $this->applyHstsHeader($response);

        return $response;
    }

    /**
     * Apply HSTS (Strict-Transport-Security) header in production.
     */
    private function applyHstsHeader(Response|JsonResponse $response): void
    {
        if ($this->app->environment('production')) {
            $response->header('Strict-Transport-Security', 'max-age=31536000; includeSubDomains');
        }
    }

    /**
     * Apply CORS headers to the response.
     */
    public function applyCorsHeaders(mixed $response, Request $request): mixed
    {
        // ✅ Utilisation de la nouvelle API avec middlewareConfig()
        if (! $this->config->middlewareConfig()->validate_origin) {
            return $response;
        }

        if (! method_exists($response, 'header')) {
            return $response;
        }

        $origin = $request->headers->get('Origin');

        if ($origin === null) {
            return $response;
        }

        $response->header('Access-Control-Allow-Origin', $origin);

        // ✅ Utilisation de la nouvelle API avec corsConfig()
        if ($this->config->corsConfig()->allow_credentials) {
            $response->header('Access-Control-Allow-Credentials', 'true');
        }

        if ($request->isMethod('OPTIONS')) {
            $response->header('Access-Control-Allow-Methods', 'GET, POST, PUT, PATCH, DELETE, OPTIONS');
            $response->header('Access-Control-Allow-Headers', 'Content-Type, Authorization, X-Requested-With');
            $response->header('Access-Control-Max-Age', (string) $this->config->corsConfig()->max_age);
        }

        // ✅ Utilisation de la nouvelle API avec corsConfig()
        if ($this->config->corsConfig()->expose_token_info) {
            $response->header('Access-Control-Expose-Headers', 'X-Token-Expires-At, X-Token-Abilities');
        }

        return $response;
    }

    /**
     * Add CORS headers to error response if needed.
     *
     * @return JsonResponse The modified response
     */
    public function addCorsToErrorResponse(JsonResponse $response, Request $request): JsonResponse
    {
        // ✅ Utilisation de la nouvelle API avec middlewareConfig()
        if (! $this->config->middlewareConfig()->validate_origin) {
            return $response;
        }

        $origin = $request->headers->get('Origin');

        if ($origin === null) {
            return $response;
        }

        $response->header('Access-Control-Allow-Origin', $origin);

        // ✅ Utilisation de la nouvelle API avec corsConfig()
        if ($this->config->corsConfig()->allow_credentials) {
            $response->header('Access-Control-Allow-Credentials', 'true');
        }

        return $response;
    }
}
