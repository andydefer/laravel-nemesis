<?php

// src/Http/Middleware/NemesisWebMiddleware.php

declare(strict_types=1);

namespace AndyDefer\Nemesis\Http\Middleware;

use AndyDefer\Nemesis\Contracts\Configs\NemesisConfigInterface;
use AndyDefer\Nemesis\Contracts\MustNemesis;
use AndyDefer\Nemesis\Contracts\Services\CookieTokenStorageInterface;
use AndyDefer\Nemesis\Contracts\Services\NemesisAuthenticationInterface;
use AndyDefer\Nemesis\Enums\ErrorCode;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Middleware for protecting web routes with Nemesis token authentication.
 *
 * Validates that a valid Nemesis token exists in the cookie and that the
 * user is authenticated. When the ability is `optional`, authentication is
 * skipped on failure and the request proceeds with a null authenticatable,
 * enabling routes that render differently for guests and authenticated users.
 */
final class NemesisWebMiddleware
{
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
     * @param  string|null  $ability  Optional ability to check, or `optional` to allow guests
     * @return Response The HTTP response
     */
    public function handle(Request $request, Closure $next, ?string $ability = null): Response
    {
        $isOptional = $ability === 'optional';

        $plainToken = $this->cookieTokenStorage->get($request);

        if ($plainToken === null) {
            if ($isOptional) {
                return $this->proceedWithoutAuth($request, $next);
            }

            return redirect()->to($this->config->webConfig()->login_route);
        }

        $tokenHeader = $this->config->middlewareConfig()->token_header;
        $request->headers->set($tokenHeader, 'Bearer '.$plainToken);

        $authResult = $this->authService->authenticate($request, $isOptional ? null : $ability);

        if (! $authResult->isSuccess()) {
            if ($isOptional) {
                return $this->proceedWithoutAuth($request, $next);
            }

            if ($authResult->getErrorCode() === ErrorCode::INSUFFICIENT_PERMISSIONS) {
                abort(403, 'Unauthorized action.');
            }

            return redirect()->to($this->config->webConfig()->login_route);
        }

        $resultRecord = $authResult->getValue();
        $tokenRecord = $resultRecord->token_record;

        $tokenableType = $tokenRecord->tokenable_type;
        $tokenableId = $tokenRecord->tokenable_id;

        if ($tokenableType === null || $tokenableId === null || ! class_exists($tokenableType)) {
            if ($isOptional) {
                return $this->proceedWithoutAuth($request, $next);
            }

            return redirect()->to($this->config->webConfig()->login_route);
        }

        $authenticatable = $tokenableType::find($tokenableId);

        if ($authenticatable === null) {
            if ($isOptional) {
                return $this->proceedWithoutAuth($request, $next);
            }

            return redirect()->to($this->config->webConfig()->login_route);
        }

        if (! $isOptional && $ability !== null) {
            $abilities = $tokenRecord->abilities;
            if ($abilities === null || ! in_array($ability, $abilities->toArray(), true)) {
                abort(403, 'Unauthorized action.');
            }
        }

        $parameterName = $this->config->middlewareConfig()->parameter_name;

        $request->merge([
            $parameterName => $authenticatable,
            'current_nemesis_token' => $tokenRecord,
        ]);

        if ($authenticatable instanceof MustNemesis) {
            $request->merge([
                $parameterName.'_format' => $authenticatable->nemesisFormat(),
            ]);
        }

        return $next($request);
    }

    /**
     * Continue the request without authentication, injecting null values
     * for the configured parameter name, the token record and the format.
     *
     * @param  Request  $request  The incoming HTTP request
     * @param  Closure  $next  The next middleware in the pipeline
     * @return Response The HTTP response
     */
    private function proceedWithoutAuth(Request $request, Closure $next): Response
    {
        $parameterName = $this->config->middlewareConfig()->parameter_name;

        $request->merge([
            $parameterName => null,
            'current_nemesis_token' => null,
            $parameterName.'_format' => null,
        ]);

        return $next($request);
    }
}
