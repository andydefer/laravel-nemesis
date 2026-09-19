<?php

declare(strict_types=1);

namespace AndyDefer\Nemesis\Http\Middleware;

use AndyDefer\Nemesis\Contracts\Configs\NemesisConfigInterface;
use AndyDefer\Nemesis\Contracts\MustNemesis;
use AndyDefer\Nemesis\Contracts\Services\HttpHeaderInterface;
use AndyDefer\Nemesis\Contracts\Services\NemesisAuthenticationInterface;
use AndyDefer\Nemesis\Enums\ErrorCode;
use Closure;
use Illuminate\Http\Request;

final class NemesisTokenMiddleware
{
    public function __construct(
        private readonly NemesisConfigInterface $config,
        private readonly NemesisAuthenticationInterface $authService,
        private readonly HttpHeaderInterface $headerService,
    ) {}

    public function handle(Request $request, Closure $next, ?string $ability = null): mixed
    {
        $isOptional = $ability === 'optional';

        $result = $this->authService->authenticate($request, $isOptional ? null : $ability);

        if (! $result->isSuccess()) {
            if ($isOptional) {
                return $this->proceedWithoutAuth($request, $next);
            }

            $response = $result->getErrorCode()
                ->toJsonResponseFactory(errors: $result->getAdditionalData())
                ->toResponse();

            return $this->headerService->addCorsToErrorResponse($response, $request);
        }

        $resultRecord = $result->getValue();
        $tokenRecord = $resultRecord->token_record;

        $tokenableType = $tokenRecord->tokenable_type;
        $tokenableId = $tokenRecord->tokenable_id;

        if ($tokenableType === null || $tokenableId === null || ! class_exists($tokenableType)) {
            if ($isOptional) {
                return $this->proceedWithoutAuth($request, $next);
            }

            $response = ErrorCode::INVALID_TOKEN->toJsonResponseFactory()->toResponse();

            return $this->headerService->addCorsToErrorResponse($response, $request);
        }

        $authenticatable = $tokenableType::find($tokenableId);

        if ($authenticatable === null) {
            if ($isOptional) {
                return $this->proceedWithoutAuth($request, $next);
            }

            $response = ErrorCode::INVALID_TOKEN->toJsonResponseFactory()->toResponse();

            return $this->headerService->addCorsToErrorResponse($response, $request);
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

        $response = $next($request);

        $response = $this->headerService->applySecurityHeaders($response);
        $response = $this->headerService->applyCorsHeaders($response, $request);

        return $response;
    }

    private function proceedWithoutAuth(Request $request, Closure $next): mixed
    {
        $parameterName = $this->config->middlewareConfig()->parameter_name;

        $request->merge([
            $parameterName => null,
            'current_nemesis_token' => null,
            $parameterName.'_format' => null,
        ]);

        $response = $next($request);

        $response = $this->headerService->applySecurityHeaders($response);
        $response = $this->headerService->applyCorsHeaders($response, $request);

        return $response;
    }
}
