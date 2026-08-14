<?php

// src/Http/Middleware/NemesisGuestMiddleware.php

declare(strict_types=1);

namespace AndyDefer\Nemesis\Http\Middleware;

use AndyDefer\Nemesis\Contracts\Configs\NemesisConfigInterface;
use AndyDefer\Nemesis\Contracts\Services\CookieTokenStorageInterface;
use AndyDefer\Nemesis\Contracts\Services\NemesisAuthenticationInterface;
use AndyDefer\Nemesis\Contracts\Services\NemesisInterface;
use AndyDefer\Nemesis\Models\NemesisToken;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Middleware for guest routes that redirects authenticated users away.
 *
 * This middleware checks if a valid token exists in the cookie.
 * If the user is authenticated, they are redirected to the dashboard.
 * This is useful for login and registration pages that should not be accessible
 * to authenticated users.
 *
 * Optionally, it can check if the user has a specific ability and redirect them
 * if they possess it.
 */
final class NemesisGuestMiddleware
{
    /**
     * Create a new NemesisGuestMiddleware instance.
     *
     * @param  CookieTokenStorageInterface  $cookieTokenStorage  Service for cookie token operations
     * @param  NemesisAuthenticationInterface  $authService  Service for authentication
     * @param  NemesisConfigInterface  $config  Configuration for the Nemesis system
     */
    public function __construct(
        private readonly CookieTokenStorageInterface $cookieTokenStorage,
        private readonly NemesisAuthenticationInterface $authService,
        private readonly NemesisConfigInterface $config,
    ) {}

    /**
     * Handle an incoming request.
     *
     * @param  Request  $request  The incoming HTTP request
     * @param  Closure  $next  The next middleware in the pipeline
     * @param  string|null  $ability  Optional ability to check (if user has it, redirect)
     * @return Response The HTTP response
     */
    public function handle(Request $request, Closure $next, ?string $ability = null): Response
    {
        $plainToken = $this->cookieTokenStorage->get($request);

        if ($plainToken === null) {
            return $next($request);
        }

        $tokenHeader = $this->config->middlewareConfig()->token_header;
        $request->headers->set($tokenHeader, 'Bearer '.$plainToken);

        $authResult = $this->authService->authenticate($request);

        if (! $authResult->isSuccess()) {
            return $next($request);
        }

        $tokenRecord = $authResult->getTokenRecord();
        $nemesisService = app(NemesisInterface::class);

        $tokenModel = null;
        if ($tokenRecord !== null && $tokenRecord->token_hash !== null) {
            $tokenModel = $nemesisService->findByHash($tokenRecord->token_hash);
        }

        if ($ability !== null && $tokenModel instanceof NemesisToken) {
            $hasAbility = $nemesisService->can($tokenModel, $ability);

            if ($hasAbility) {
                $dashboardRoute = $this->config->webConfig()->dashboard_route;

                return redirect()->to($dashboardRoute);
            }

            return $next($request);
        }

        $dashboardRoute = $this->config->webConfig()->dashboard_route;

        return redirect()->to($dashboardRoute);
    }
}
