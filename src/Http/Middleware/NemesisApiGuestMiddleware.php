<?php

// src/Http/Middleware/NemesisApiGuestMiddleware.php

declare(strict_types=1);

namespace AndyDefer\Nemesis\Http\Middleware;

use AndyDefer\Actions\Http\ResponseFactory;
use AndyDefer\Nemesis\Contracts\Configs\NemesisConfigInterface;
use AndyDefer\Nemesis\Contracts\Services\NemesisAuthenticationInterface;
use AndyDefer\Nemesis\Contracts\Services\NemesisInterface;
use AndyDefer\Nemesis\Data\ErrorResponseData;
use AndyDefer\Nemesis\Enums\ErrorCode;
use AndyDefer\Nemesis\Models\NemesisToken;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Middleware for API guest routes that blocks authenticated users.
 *
 * This middleware checks if a valid token exists in the Authorization header.
 * If the user is authenticated, they are blocked with a 400 error.
 * This is useful for login and registration API endpoints that should not be
 * accessible to authenticated users.
 *
 * Optionally, it can check if the user has a specific ability and block them
 * if they possess it.
 */
final class NemesisApiGuestMiddleware
{
    /**
     * Create a new NemesisApiGuestMiddleware instance.
     *
     * @param  NemesisAuthenticationInterface  $authService  Service for authentication
     * @param  NemesisConfigInterface  $config  Configuration for the Nemesis system
     */
    public function __construct(
        private readonly NemesisAuthenticationInterface $authService,
        private readonly NemesisConfigInterface $config,
    ) {}

    /**
     * Handle an incoming request.
     *
     * @param  Request  $request  The incoming HTTP request
     * @param  Closure  $next  The next middleware in the pipeline
     * @param  string|null  $ability  Optional ability to check (if user has it, block)
     * @return Response The HTTP response
     */
    public function handle(Request $request, Closure $next, ?string $ability = null): Response
    {
        $bearerToken = $request->bearerToken();

        if ($bearerToken === null) {
            return $next($request);
        }

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
                $errorResponse = ErrorResponseData::from([
                    'errorCode' => ErrorCode::ALREADY_AUTHENTICATED,
                    'message' => 'Already authenticated',
                    'status' => 400,
                    'details' => null,
                ]);

                return ResponseFactory::json($errorResponse, 400)->toResponse();
            }

            return $next($request);
        }

        $errorResponse = ErrorResponseData::from([
            'errorCode' => ErrorCode::ALREADY_AUTHENTICATED,
            'message' => 'Already authenticated',
            'status' => 400,
            'details' => null,
        ]);

        return ResponseFactory::json($errorResponse, 400)->toResponse();
    }
}
