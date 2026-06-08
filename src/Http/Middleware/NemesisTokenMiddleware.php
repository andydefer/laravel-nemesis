<?php

declare(strict_types=1);

namespace Kani\Nemesis\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Kani\Nemesis\Config\NemesisConfig;
use Kani\Nemesis\Contracts\CanBeFormatted;
use Kani\Nemesis\Data\ErrorResponseData;
use Kani\Nemesis\Enums\ErrorCode;
use Kani\Nemesis\Services\HttpHeaderService;
use Kani\Nemesis\Services\NemesisAuthenticationService;

/**
 * Middleware for authenticating requests using Nemesis tokens.
 *
 * This middleware handles Bearer token authentication, validates token
 * expiration, checks required abilities, enforces CORS origin restrictions,
 * and attaches the authenticated model to the request for downstream use.
 * 
 * All business logic has been extracted to services.
 *
 * @package Kani\Nemesis\Http\Middleware
 */
final class NemesisTokenMiddleware
{
    public function __construct(
        private readonly NemesisConfig $config,
        private readonly NemesisAuthenticationService $authService,
        private readonly HttpHeaderService $headerService,
    ) {}

    /**
     * Handle an incoming request and authenticate via bearer token.
     */
    public function handle(Request $request, Closure $next, ?string $ability = null): mixed
    {
        // Authenticate using the pure service (returns VO)
        $result = $this->authService->authenticate($request, $ability);

        if (!$result->isSuccess()) {
            $errorResponse = ErrorResponseData::from([
                'errorCode' => $result->getErrorCode(),
                'message' => $result->getErrorCode()->message(),
                'status' => $result->getErrorCode()->httpStatusCode(),
                'details' => $result->getAdditionalData(),
            ]);
            $response = response()->json(
                data: $errorResponse->toArray(),
                status: $errorResponse->status->value
            );
            return $this->headerService->addCorsToErrorResponse($response);
        }

        // Get the record from VO
        $resultRecord = $result->getValue();
        $tokenRecord = $resultRecord->token_record;

        // Get authenticatable from token record
        $tokenableType = $tokenRecord->tokenable_type;
        $tokenableId = $tokenRecord->tokenable_id;

        if ($tokenableType === null || $tokenableId === null || !class_exists($tokenableType)) {
            $errorResponse = ErrorResponseData::from([
                'errorCode' => ErrorCode::INVALID_TOKEN,
                'message' => ErrorCode::INVALID_TOKEN->message(),
                'status' => ErrorCode::INVALID_TOKEN->httpStatusCode(),
                'details' => null,
            ]);
            $response = response()->json(
                data: $errorResponse->toArray(),
                status: $errorResponse->status->value
            );
            return $this->headerService->addCorsToErrorResponse($response);
        }

        $authenticatable = $tokenableType::find($tokenableId);

        if ($authenticatable === null) {
            $errorResponse = ErrorResponseData::from([
                'errorCode' => ErrorCode::INVALID_TOKEN,
                'message' => ErrorCode::INVALID_TOKEN->message(),
                'status' => ErrorCode::INVALID_TOKEN->httpStatusCode(),
                'details' => null,
            ]);
            $response = response()->json(
                data: $errorResponse->toArray(),
                status: $errorResponse->status->value
            );
            return $this->headerService->addCorsToErrorResponse($response);
        }

        // Get formatted authenticatable if it implements the interface
        $formattedAuthenticatable = null;
        if ($authenticatable instanceof CanBeFormatted) {
            $formattedAuthenticatable = $authenticatable->nemesisFormat();
        }

        // Attach data to request
        $request->merge([
            $this->config->getParameterName() => $authenticatable,
            'currentNemesisToken' => $tokenRecord,
        ]);

        if ($formattedAuthenticatable !== null) {
            $request->merge([
                $this->config->getParameterName() . 'Format' => $formattedAuthenticatable,
            ]);
        }

        // Process the request
        $response = $next($request);

        // Apply headers
        $response = $this->headerService->applySecurityHeaders($response);
        $response = $this->headerService->applyCorsHeaders($response, $request);

        return $response;
    }
}
