<?php

declare(strict_types=1);

namespace AndyDefer\Nemesis\Http\Middleware;

use AndyDefer\Actions\Http\ResponseFactory;
use AndyDefer\Nemesis\Contracts\Configs\NemesisConfigInterface;
use AndyDefer\Nemesis\Contracts\MustNemesis;
use AndyDefer\Nemesis\Contracts\Services\HttpHeaderInterface;
use AndyDefer\Nemesis\Contracts\Services\NemesisAuthenticationInterface;
use AndyDefer\Nemesis\Data\ErrorResponseData;
use AndyDefer\Nemesis\Enums\ErrorCode;
use Closure;
use Illuminate\Http\Request;

/**
 * Middleware for authenticating requests using Nemesis tokens.
 *
 * Extracts the token from the request, validates it, and attaches
 * the authenticated model to the request for further processing.
 */
final class NemesisTokenMiddleware
{
    /**
     * Create a new NemesisTokenMiddleware instance.
     *
     * @param  NemesisConfigInterface  $config  Configuration for token and middleware settings
     * @param  NemesisAuthenticationInterface  $authService  Service for token authentication
     * @param  HttpHeaderInterface  $headerService  Service for HTTP header management
     */
    public function __construct(
        private readonly NemesisConfigInterface $config,
        private readonly NemesisAuthenticationInterface $authService,
        private readonly HttpHeaderInterface $headerService,
    ) {}

    /**
     * Handle an incoming request.
     *
     * @param  Request  $request  The HTTP request
     * @param  Closure  $next  The next middleware
     * @param  string|null  $ability  Optional ability required for the token
     * @return mixed The response
     */
    public function handle(Request $request, Closure $next, ?string $ability = null): mixed
    {
        $result = $this->authService->authenticate($request, $ability);

        if (! $result->isSuccess()) {
            $errorCode = $result->getErrorCode();
            $statusInt = $errorCode->getHttpStatusCode()->value;

            $errorResponse = ErrorResponseData::from([
                'errorCode' => $errorCode,
                'message' => $errorCode->message(),
                'status' => $statusInt,
                'details' => $result->getAdditionalData(),
            ]);

            $response = ResponseFactory::json($errorResponse, $statusInt)->toResponse();

            return $this->headerService->addCorsToErrorResponse($response, $request);
        }

        $resultRecord = $result->getValue();
        $tokenRecord = $resultRecord->token_record;

        $tokenableType = $tokenRecord->tokenable_type;
        $tokenableId = $tokenRecord->tokenable_id;

        if ($tokenableType === null || $tokenableId === null || ! class_exists($tokenableType)) {
            $statusInt = ErrorCode::INVALID_TOKEN->getHttpStatusCode()->value;

            $errorResponse = ErrorResponseData::from([
                'errorCode' => ErrorCode::INVALID_TOKEN,
                'message' => ErrorCode::INVALID_TOKEN->message(),
                'status' => $statusInt,
                'details' => null,
            ]);

            $response = ResponseFactory::json($errorResponse, $statusInt)->toResponse();

            return $this->headerService->addCorsToErrorResponse($response, $request);
        }

        $authenticatable = $tokenableType::find($tokenableId);

        if ($authenticatable === null) {
            $statusInt = ErrorCode::INVALID_TOKEN->getHttpStatusCode()->value;

            $errorResponse = ErrorResponseData::from([
                'errorCode' => ErrorCode::INVALID_TOKEN,
                'message' => ErrorCode::INVALID_TOKEN->message(),
                'status' => $statusInt,
                'details' => null,
            ]);

            $response = ResponseFactory::json($errorResponse, $statusInt)->toResponse();

            return $this->headerService->addCorsToErrorResponse($response, $request);
        }

        $formattedAuthenticatable = null;
        if ($authenticatable instanceof MustNemesis) {
            $formattedAuthenticatable = $authenticatable->nemesisFormat();
        }

        $parameterName = $this->config->middlewareConfig()->parameter_name;

        $request->merge([
            $parameterName => $authenticatable,
            'current_nemesis_token' => $tokenRecord,
        ]);

        if ($formattedAuthenticatable !== null) {
            $formatKey = $parameterName.'_format';
            $request->merge([
                $formatKey => $formattedAuthenticatable,
            ]);
        }

        $response = $next($request);

        $response = $this->headerService->applySecurityHeaders($response);
        $response = $this->headerService->applyCorsHeaders($response, $request);

        return $response;
    }
}
