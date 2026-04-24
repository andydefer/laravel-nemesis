<?php

declare(strict_types=1);

namespace Kani\Nemesis\Http\Middleware;

use Closure;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Kani\Nemesis\Config\NemesisConfig;
use Kani\Nemesis\Contracts\MustNemesis;
use Kani\Nemesis\Data\ErrorResponseData;
use Kani\Nemesis\Enums\ErrorCode;
use Kani\Nemesis\Models\NemesisToken;

/**
 * Middleware for authenticating requests using Nemesis tokens.
 *
 * This middleware handles Bearer token authentication, validates token
 * expiration, checks required abilities, enforces CORS origin restrictions,
 * and attaches the authenticated model to the request for downstream use.
 *
 * @package Kani\Nemesis\Http\Middleware
 */
final class NemesisAuth
{
    /**
     * Create a new middleware instance.
     *
     * @param NemesisConfig $config Immutable configuration for the middleware
     */
    public function __construct(
        private readonly NemesisConfig $config
    ) {}

    /**
     * Handle an incoming request and authenticate via bearer token.
     *
     * @param Request $request The incoming HTTP request
     * @param Closure $next The next middleware in the pipeline
     * @param string|null $ability Optional ability to check against the token
     * @return mixed The response (either error JSON or the next middleware response)
     */
    public function handle(Request $request, Closure $next, ?string $ability = null): mixed
    {
        $token = $this->extractBearerToken($request);

        if ($token === null) {
            return $this->sendErrorResponse(ErrorCode::MISSING_TOKEN);
        }

        $tokenModel = $this->findValidToken($token);

        if ($tokenModel === null) {
            return $this->sendErrorResponse(ErrorCode::INVALID_TOKEN);
        }

        if ($this->isTokenExpired($tokenModel)) {
            return $this->sendErrorResponse(ErrorCode::TOKEN_EXPIRED);
        }

        if ($this->isOriginRestricted($tokenModel, $request)) {
            return $this->sendErrorResponse(
                ErrorCode::ORIGIN_NOT_ALLOWED,
                ['origin' => $request->headers->get('Origin')]
            );
        }

        if ($this->hasInsufficientAbility($tokenModel, $ability)) {
            return $this->sendErrorResponse(
                ErrorCode::INSUFFICIENT_PERMISSIONS,
                ['required_ability' => $ability]
            );
        }

        $authenticatable = $this->getAuthenticatableModel($tokenModel);

        if ($authenticatable === null) {
            return $this->sendErrorResponse(ErrorCode::INVALID_TOKEN);
        }

        if (!$this->isValidAuthenticatable($authenticatable)) {
            return $this->sendInvalidAuthenticatableResponse($authenticatable);
        }

        $this->updateTokenUsage($tokenModel);
        $this->attachToRequest($request, $authenticatable, $tokenModel);

        $response = $next($request);

        $response = $this->applySecurityHeaders($response);
        $response = $this->applyCorsHeaders($response, $request);

        return $response;
    }

    /**
     * Extract the bearer token from the request.
     *
     * @param Request $request The HTTP request
     * @return string|null The bearer token or null if not present
     */
    private function extractBearerToken(Request $request): ?string
    {
        if ($this->config->isUsingCustomHeader()) {
            $token = $request->header($this->config->tokenHeader);
            if ($token !== null && trim($token) !== '') {
                return $token;
            }
        }

        $token = $request->bearerToken();

        return $token !== null && trim($token) !== '' ? $token : null;
    }

    /**
     * Find a valid token model from the database.
     *
     * @param string $rawToken The raw token string
     * @return NemesisToken|null The token model or null if not found
     */
    private function findValidToken(string $rawToken): ?NemesisToken
    {
        $hashedToken = hash($this->config->hashAlgorithm, $rawToken);

        return NemesisToken::where('token_hash', $hashedToken)
            ->with('tokenable')
            ->first();
    }

    /**
     * Check if the token has expired.
     *
     * @param NemesisToken $tokenModel The token model
     * @return bool True if expired, false otherwise
     */
    private function isTokenExpired(NemesisToken $tokenModel): bool
    {
        return $tokenModel->isExpired();
    }

    /**
     * Check if the request origin is restricted for this token.
     *
     * @param NemesisToken $tokenModel The token model
     * @param Request $request The HTTP request
     * @return bool True if origin is NOT allowed, false otherwise
     */
    private function isOriginRestricted(NemesisToken $tokenModel, Request $request): bool
    {
        if (!$this->config->validateOrigin) {
            return false;
        }

        $origin = $request->headers->get('Origin');

        if ($origin === null) {
            return false;
        }

        return !$tokenModel->canUseFromOrigin($origin);
    }

    /**
     * Check if the token has insufficient ability for the request.
     *
     * @param NemesisToken $tokenModel The token model
     * @param string|null $requiredAbility The required ability
     * @return bool True if ability is insufficient, false otherwise
     */
    private function hasInsufficientAbility(NemesisToken $tokenModel, ?string $requiredAbility): bool
    {
        if ($requiredAbility === null) {
            return false;
        }

        return !$tokenModel->can($requiredAbility);
    }

    /**
     * Get the authenticatable model from the token.
     *
     * @param NemesisToken $tokenModel The token model
     * @return mixed The authenticatable model or null
     */
    private function getAuthenticatableModel(NemesisToken $tokenModel): mixed
    {
        return $tokenModel->tokenable;
    }

    /**
     * Check if the authenticatable model implements the required interface.
     *
     * @param mixed $authenticatable The authenticatable model
     * @return bool True if valid, false otherwise
     */
    private function isValidAuthenticatable(mixed $authenticatable): bool
    {
        return $authenticatable instanceof MustNemesis;
    }

    /**
     * Send error response for invalid authenticatable model.
     *
     * @param mixed $authenticatable The invalid model
     * @return JsonResponse The error response
     */
    private function sendInvalidAuthenticatableResponse(mixed $authenticatable): JsonResponse
    {
        return $this->sendErrorResponse(
            ErrorCode::INVALID_AUTHENTICATABLE_MODEL,
            [
                'message' => 'Authenticatable model must implement MustNemesis interface',
                'model_class' => get_class($authenticatable),
                'expected_interface' => MustNemesis::class,
            ]
        );
    }

    /**
     * Update the token's last used timestamp.
     *
     * @param NemesisToken $tokenModel The token model to update
     */
    private function updateTokenUsage(NemesisToken $tokenModel): void
    {
        $tokenModel->update(['last_used_at' => now()]);
    }

    /**
     * Attach the authenticated model and token to the request.
     *
     * @param Request $request The HTTP request
     * @param mixed $authenticatable The authenticated model
     * @param NemesisToken $tokenModel The token model
     */
    private function attachToRequest(Request $request, mixed $authenticatable, NemesisToken $tokenModel): void
    {
        $request->merge([
            $this->config->parameterName => $authenticatable,
            'currentNemesisToken' => $tokenModel,
        ]);
    }

    /**
     * Apply security headers to the response.
     *
     * @param mixed $response The response object
     * @return mixed The response with security headers
     */
    private function applySecurityHeaders(mixed $response): mixed
    {
        if (!$this->config->securityHeaders) {
            return $response;
        }

        if (!method_exists($response, 'header')) {
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
     *
     * @param Response|JsonResponse $response The response object
     */
    private function applyHstsHeader(Response|JsonResponse $response): void
    {
        if (app()->environment('production')) {
            $response->header('Strict-Transport-Security', 'max-age=31536000; includeSubDomains');
        }
    }

    /**
     * Apply CORS headers to the response.
     *
     * @param mixed $response The response object
     * @param Request $request The HTTP request
     * @return mixed The response with CORS headers
     */
    private function applyCorsHeaders(mixed $response, Request $request): mixed
    {
        if (!$this->config->validateOrigin) {
            return $response;
        }

        if (!method_exists($response, 'header')) {
            return $response;
        }

        $origin = $request->headers->get('Origin');

        if ($origin === null) {
            return $response;
        }

        $response->header('Access-Control-Allow-Origin', $origin);

        $this->applyCorsCredentialsHeader($response);
        $this->applyPreflightHeaders($response, $request);
        $this->applyExposeHeaders($response);

        return $response;
    }

    /**
     * Apply CORS credentials header if configured.
     *
     * @param Response|JsonResponse $response The response object
     */
    private function applyCorsCredentialsHeader(Response|JsonResponse $response): void
    {
        if ($this->config->allowCredentials) {
            $response->header('Access-Control-Allow-Credentials', 'true');
        }
    }

    /**
     * Apply preflight request headers for OPTIONS method.
     *
     * @param Response|JsonResponse $response The response object
     * @param Request $request The HTTP request
     */
    private function applyPreflightHeaders(Response|JsonResponse $response, Request $request): void
    {
        if ($request->isMethod('OPTIONS')) {
            $response->header('Access-Control-Allow-Methods', 'GET, POST, PUT, PATCH, DELETE, OPTIONS');
            $response->header('Access-Control-Allow-Headers', 'Content-Type, Authorization, X-Requested-With');
            $response->header('Access-Control-Max-Age', (string) $this->config->maxAge);
        }
    }

    /**
     * Apply expose headers to expose token information.
     *
     * @param Response|JsonResponse $response The response object
     */
    private function applyExposeHeaders(Response|JsonResponse $response): void
    {
        if ($this->config->exposeTokenInfo) {
            $response->header('Access-Control-Expose-Headers', 'X-Token-Expires-At, X-Token-Abilities');
        }
    }

    /**
     * Send an error response for authentication failures.
     *
     * @param ErrorCode $errorCode The error code to send
     * @param array<string, mixed> $additionalData Additional data to include in the response
     * @return JsonResponse The JSON error response
     */
    private function sendErrorResponse(ErrorCode $errorCode, array $additionalData = []): JsonResponse
    {
        $errorResponse = ErrorResponseData::fromErrorCode(
            errorCode: $errorCode,
            details: $additionalData
        );

        $response = response()->json(
            data: $errorResponse->toArray(),
            status: $errorResponse->status
        );

        $this->addCorsToErrorResponse($response);

        return $response;
    }

    /**
     * Add CORS headers to error response if needed.
     *
     * 
     * @param JsonResponse $response The error response
     */
    private function addCorsToErrorResponse(JsonResponse $response): void
    {
        if (!$this->config->validateOrigin) {
            return;
        }

        $origin = request()->headers->get('Origin');

        if ($origin === null) {
            return;
        }

        $response->header('Access-Control-Allow-Origin', $origin);

        if ($this->config->allowCredentials) {
            $response->header('Access-Control-Allow-Credentials', 'true');
        }
    }
}
