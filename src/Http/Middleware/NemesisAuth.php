<?php

declare(strict_types=1);

namespace Kani\Nemesis\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Kani\Nemesis\Models\NemesisToken;
use Kani\Nemesis\Enums\ErrorCode;
use Kani\Nemesis\Data\ErrorResponseData;

/**
 * Middleware for authenticating requests using Nemesis tokens.
 *
 * Validates bearer tokens, checks expiration, abilities, and origin restrictions,
 * then attaches the authenticated model to the request.
 */
final class NemesisAuth
{
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
        // Extract and validate the bearer token
        $token = $this->extractBearerToken($request);

        if ($token === null) {
            return $this->sendErrorResponse(ErrorCode::MISSING_TOKEN);
        }

        // Hash the token for database lookup
        $hashedToken = $this->hashToken($token);

        // Find the token model with its associated authenticatable model
        $tokenModel = $this->findTokenModel($hashedToken);

        if ($tokenModel === null) {
            return $this->sendErrorResponse(ErrorCode::INVALID_TOKEN);
        }

        // Check if the token has expired
        if ($tokenModel->isExpired()) {
            return $this->sendErrorResponse(ErrorCode::TOKEN_EXPIRED);
        }

        // Check origin restrictions (CORS security) if enabled
        if ($this->shouldValidateOrigin() && !$this->isOriginAllowed($tokenModel, $request)) {
            return $this->sendErrorResponse(
                ErrorCode::ORIGIN_NOT_ALLOWED,
                ['origin' => $request->headers->get('Origin')]
            );
        }

        // Check ability/permissions if specified
        if (!$this->hasRequiredAbility($tokenModel, $ability)) {
            return $this->sendErrorResponse(
                ErrorCode::INSUFFICIENT_PERMISSIONS,
                ['required_ability' => $ability]
            );
        }

        // Verify the authenticatable model exists
        $authenticatable = $tokenModel->tokenable;

        if ($authenticatable === null) {
            return $this->sendErrorResponse(ErrorCode::INVALID_TOKEN);
        }

        // Update token usage timestamp
        $this->updateTokenUsage($tokenModel);

        // Attach the authenticated model and token to the request
        $this->attachToRequest($request, $authenticatable, $tokenModel);

        // Get the response from the next middleware
        $response = $next($request);

        // Add security headers if enabled
        if ($this->shouldAddSecurityHeaders()) {
            $response = $this->addSecurityHeaders($response);
        }

        // Add CORS headers if needed
        if ($this->shouldAddCorsHeaders()) {
            $response = $this->addCorsHeaders($response, $request);
        }

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
        $tokenHeader = config('nemesis.middleware.token_header', 'Authorization');

        // If using custom header, extract token from there
        if ($tokenHeader !== 'Authorization') {
            $token = $request->header($tokenHeader);
            if ($token !== null && trim($token) !== '') {
                return $token;
            }
        }

        // Fall back to standard bearer token extraction
        $token = $request->bearerToken();

        // Validate that token is not empty
        if ($token === null || trim($token) === '') {
            return null;
        }

        return $token;
    }

    /**
     * Hash the token using the configured hash algorithm.
     *
     * @param string $token The raw token to hash
     * @return string The hashed token
     */
    private function hashToken(string $token): string
    {
        $algorithm = config('nemesis.hash_algorithm', 'sha256');

        return hash($algorithm, $token);
    }

    /**
     * Find the token model by its hashed value.
     *
     * @param string $hashedToken The hashed token to search for
     * @return NemesisToken|null The token model or null if not found
     */
    private function findTokenModel(string $hashedToken): ?NemesisToken
    {

        return NemesisToken::where('token', $hashedToken)
            ->with('tokenable')
            ->first();
    }

    /**
     * Check if origin validation is enabled.
     *
     * @return bool True if origin validation should be performed
     */
    private function shouldValidateOrigin(): bool
    {
        return config('nemesis.middleware.validate_origin', true);
    }

    /**
     * Check if the token's origin is allowed.
     *
     * @param NemesisToken $tokenModel The token model
     * @param Request $request The HTTP request
     * @return bool True if origin is allowed, false otherwise
     */
    private function isOriginAllowed(NemesisToken $tokenModel, Request $request): bool
    {
        $origin = $request->headers->get('Origin');

        // If no origin header, allow by default (non-browser requests)
        // For browser requests, origin is always sent
        if ($origin === null) {
            return true;
        }

        return $tokenModel->canUseFromOrigin($origin);
    }

    /**
     * Check if the token has the required ability.
     *
     * @param NemesisToken $tokenModel The token model
     * @param string|null $ability The required ability
     * @return bool True if token has the ability, false otherwise
     */
    private function hasRequiredAbility(NemesisToken $tokenModel, ?string $ability): bool
    {
        // If no ability is required, allow access
        if ($ability === null) {
            return true;
        }

        return $tokenModel->can($ability);
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
        $parameterName = config('nemesis.middleware.parameter_name', 'nemesisAuth');

        $request->merge([
            $parameterName => $authenticatable,
            'currentNemesisToken' => $tokenModel,
        ]);
    }

    /**
     * Check if security headers should be added.
     *
     * @return bool True if security headers should be added
     */
    private function shouldAddSecurityHeaders(): bool
    {
        return config('nemesis.middleware.security_headers', true);
    }

    /**
     * Add security headers to the response.
     *
     * @param mixed $response The response object
     * @return mixed The response with security headers
     */
    private function addSecurityHeaders(mixed $response): mixed
    {
        // Add security headers to prevent common attacks
        if (method_exists($response, 'header')) {
            // Prevent clickjacking
            $response->header('X-Frame-Options', 'DENY');

            // Enable XSS protection
            $response->header('X-XSS-Protection', '1; mode=block');

            // Prevent MIME type sniffing
            $response->header('X-Content-Type-Options', 'nosniff');

            // Referrer policy
            $response->header('Referrer-Policy', 'strict-origin-when-cross-origin');

            // HSTS (HTTP Strict Transport Security) - only for HTTPS
            if (app()->environment('production')) {
                $response->header('Strict-Transport-Security', 'max-age=31536000; includeSubDomains');
            }
        }

        return $response;
    }

    /**
     * Check if CORS headers should be added.
     *
     * @return bool True if CORS headers should be added
     */
    private function shouldAddCorsHeaders(): bool
    {
        return config('nemesis.middleware.validate_origin', true);
    }

    /**
     * Add CORS headers to the response.
     *
     * @param mixed $response The response object
     * @param Request $request The HTTP request
     * @return mixed The response with CORS headers
     */
    private function addCorsHeaders(mixed $response, Request $request): mixed
    {
        if (!method_exists($response, 'header')) {
            return $response;
        }

        $origin = $request->headers->get('Origin');

        // Only add CORS headers if origin is present
        if ($origin !== null) {
            $allowCredentials = config('nemesis.cors.allow_credentials', true);
            $maxAge = config('nemesis.cors.max_age', 86400);
            $exposeTokenInfo = config('nemesis.cors.expose_token_info', false);

            // Set Access-Control-Allow-Origin to the requesting origin
            $response->header('Access-Control-Allow-Origin', $origin);

            // Allow credentials if configured
            if ($allowCredentials) {
                $response->header('Access-Control-Allow-Credentials', 'true');
            }

            // For preflight requests, add additional headers
            if ($request->isMethod('OPTIONS')) {
                $response->header('Access-Control-Allow-Methods', 'GET, POST, PUT, PATCH, DELETE, OPTIONS');
                $response->header('Access-Control-Allow-Headers', 'Content-Type, Authorization, X-Requested-With');
                $response->header('Access-Control-Max-Age', (string) $maxAge);
            }

            // Expose token information if configured
            if ($exposeTokenInfo) {
                $response->header('Access-Control-Expose-Headers', 'X-Token-Expires-At, X-Token-Abilities');
            }
        }

        return $response;
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

        // Add CORS headers to error responses if needed
        if ($this->shouldAddCorsHeaders() && request()->headers->has('Origin')) {
            $origin = request()->headers->get('Origin');
            $response->header('Access-Control-Allow-Origin', $origin);

            if (config('nemesis.cors.allow_credentials', true)) {
                $response->header('Access-Control-Allow-Credentials', 'true');
            }
        }

        return $response;
    }
}
