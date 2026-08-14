<?php

// src/Http/Middleware/NemesisWebMiddleware.php

declare(strict_types=1);

namespace AndyDefer\Nemesis\Http\Middleware;

use AndyDefer\Nemesis\Contracts\Configs\NemesisConfigInterface;
use AndyDefer\Nemesis\Contracts\Services\CookieTokenStorageInterface;
use AndyDefer\Nemesis\Contracts\Services\NemesisAuthenticationInterface;
use AndyDefer\Nemesis\Enums\ErrorCode;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Middleware for protecting web routes with Nemesis token authentication.
 *
 * This middleware validates that a valid Nemesis token exists in the cookie
 * and that the user is authenticated. If the token is invalid or missing,
 * the user is redirected to the login route defined in the configuration.
 * Optionally, it can check for specific abilities on the token.
 */
final class NemesisWebMiddleware
{
    /**
     * Create a new NemesisWebMiddleware instance.
     *
     * @param  CookieTokenStorageInterface  $cookieTokenStorage  Service for cookie token operations
     * @param  NemesisConfigInterface  $config  Configuration for the Nemesis system
     * @param  NemesisAuthenticationInterface  $authService  Service for authentication
     */
    public function __construct(
        private readonly CookieTokenStorageInterface $cookieTokenStorage,
        private readonly NemesisConfigInterface $config,
        private readonly NemesisAuthenticationInterface $authService,
    ) {}

    /**
     * Handle an incoming request.
     *
     * @param  Request  $request  The incoming HTTP request
     * @param  Closure  $next  The next middleware in the pipeline
     * @param  string|null  $ability  Optional ability to check on the token
     * @return Response The HTTP response
     */
    public function handle(Request $request, Closure $next, ?string $ability = null): Response
    {
        $plainToken = $this->cookieTokenStorage->get($request);

        if ($plainToken === null) {
            $loginRoute = $this->config->webConfig()->login_route;

            return redirect()->to($loginRoute);
        }

        $tokenHeader = $this->config->middlewareConfig()->token_header;
        $request->headers->set($tokenHeader, 'Bearer '.$plainToken);

        $authResult = $this->authService->authenticate($request, $ability);

        if (! $authResult->isSuccess()) {
            if ($authResult->getErrorCode() === ErrorCode::INSUFFICIENT_PERMISSIONS) {
                abort(403, 'Unauthorized action.');
            }

            $loginRoute = $this->config->webConfig()->login_route;

            return redirect()->to($loginRoute);
        }

        if ($ability !== null) {
            $token = $authResult->getTokenRecord();
            if ($token !== null) {
                $abilities = $token->abilities;
                if ($abilities === null || ! in_array($ability, $abilities->toArray(), true)) {
                    abort(403, 'Unauthorized action.');
                }
            }
        }

        return $next($request);
    }
}
