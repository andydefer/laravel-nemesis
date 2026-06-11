<?php

// src/Http/Middleware/NemesisTokenMiddleware.php

declare(strict_types=1);

namespace Kani\Nemesis\Http\Middleware;

use AndyDefer\Actions\Http\ResponseFactory;
use AndyDefer\DomainStructures\Services\HydrationService;
use Closure;
use Illuminate\Http\Request;
use Kani\Nemesis\Contracts\Configs\NemesisConfigInterface;
use Kani\Nemesis\Contracts\MustNemesis;
use Kani\Nemesis\Data\ErrorResponseData;
use Kani\Nemesis\Enums\ErrorCode;
use Kani\Nemesis\Services\HttpHeaderService;
use Kani\Nemesis\Services\NemesisAuthenticationService;

final class NemesisTokenMiddleware
{
    private HydrationService $hydration;

    public function __construct(
        private readonly NemesisConfigInterface $config,
        private readonly NemesisAuthenticationService $authService,
        private readonly HttpHeaderService $headerService,
    ) {
        $this->hydration = new HydrationService();
    }

    public function handle(Request $request, Closure $next, ?string $ability = null): mixed
    {
        // Récupérer le token depuis le header Bearer
        $bearerToken = $request->bearerToken();

        // Vérifier le header personnalisé si configuré
        $customHeaderName = $this->config->middlewareConfig()->token_header;
        $token = $bearerToken;

        if ($customHeaderName !== 'Authorization') {
            $customToken = $request->header($customHeaderName);
            if ($customToken !== null) {
                $token = $customToken;
            }
        }

        // Appel au service d'authentification
        $result = $this->authService->authenticate($request, $ability);

        if (!$result->isSuccess()) {
            $errorCode = $result->getErrorCode();
            $statusInt = $errorCode->getHttpStatusCode()->value;

            $errorResponse = $this->hydration->hydrate(ErrorResponseData::class, [
                'errorCode' => $errorCode,
                'message' => $errorCode->message(),
                'status' => $statusInt,
                'details' => $result->getAdditionalData(),
            ]);

            $response = ResponseFactory::json($errorResponse, $statusInt)->toResponse();

            return $this->headerService->addCorsToErrorResponse($response, $request);
        }

        // Succès - récupération des données
        $resultRecord = $result->getValue();
        $tokenRecord = $resultRecord->token_record;

        // Récupérer l'authenticatable
        $tokenableType = $tokenRecord->tokenable_type;
        $tokenableId = $tokenRecord->tokenable_id;

        if ($tokenableType === null || $tokenableId === null || !class_exists($tokenableType)) {
            $statusInt = ErrorCode::INVALID_TOKEN->getHttpStatusCode()->value;

            $errorResponse = $this->hydration->hydrate(ErrorResponseData::class, [
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

            $errorResponse = $this->hydration->hydrate(ErrorResponseData::class, [
                'errorCode' => ErrorCode::INVALID_TOKEN,
                'message' => ErrorCode::INVALID_TOKEN->message(),
                'status' => $statusInt,
                'details' => null,
            ]);

            $response = ResponseFactory::json($errorResponse, $statusInt)->toResponse();

            return $this->headerService->addCorsToErrorResponse($response, $request);
        }

        // Formatage si l'interface est implémentée
        $formattedAuthenticatable = null;
        if ($authenticatable instanceof MustNemesis) {
            $formattedAuthenticatable = $authenticatable->nemesisFormat();
        }

        // Attacher les données à la requête
        $parameterName = $this->config->middlewareConfig()->parameter_name;

        $request->merge([
            $parameterName => $authenticatable,
            'currentNemesisToken' => $tokenRecord,
        ]);

        if ($formattedAuthenticatable !== null) {
            $formatKey = $parameterName . 'Format';
            $request->merge([
                $formatKey => $formattedAuthenticatable,
            ]);
        }

        // Traiter la requête
        $response = $next($request);

        // Appliquer les headers de sécurité
        $response = $this->headerService->applySecurityHeaders($response);

        // Appliquer les headers CORS
        $response = $this->headerService->applyCorsHeaders($response, $request);

        return $response;
    }
}
