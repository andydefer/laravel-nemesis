<?php

declare(strict_types=1);

namespace AndyDefer\Nemesis\Contracts\Services;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Interface for HTTP header manipulation service.
 *
 * Provides methods for applying security headers, CORS headers,
 * and handling CORS for error responses.
 */
interface HttpHeaderInterface
{
    /**
     * Apply security headers to a response.
     *
     * Adds security headers like X-Frame-Options, X-XSS-Protection,
     * X-Content-Type-Options, Referrer-Policy, and HSTS (in production).
     *
     * @param  mixed  $response  The response to modify
     * @return mixed The modified response with security headers
     */
    public function applySecurityHeaders(mixed $response): mixed;

    /**
     * Apply CORS headers to the response.
     *
     * Adds CORS headers based on the request origin and configuration.
     * Handles preflight OPTIONS requests.
     *
     * @param  mixed  $response  The response to modify
     * @param  Request  $request  The HTTP request containing the origin
     * @return mixed The modified response with CORS headers
     */
    public function applyCorsHeaders(mixed $response, Request $request): mixed;

    /**
     * Add CORS headers to an error response.
     *
     * Ensures that error responses also include CORS headers
     * so they can be consumed by cross-origin clients.
     *
     * @param  JsonResponse  $response  The JSON error response to modify
     * @param  Request  $request  The HTTP request containing the origin
     * @return JsonResponse The modified response with CORS headers
     */
    public function addCorsToErrorResponse(JsonResponse $response, Request $request): JsonResponse;
}
