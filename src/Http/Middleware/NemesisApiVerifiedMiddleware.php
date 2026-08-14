<?php

// src/Http/Middleware/NemesisApiVerifiedMiddleware.php

declare(strict_types=1);

namespace AndyDefer\Nemesis\Http\Middleware;

use AndyDefer\Actions\Http\ResponseFactory;
use AndyDefer\Nemesis\Contracts\Configs\NemesisConfigInterface;
use AndyDefer\Nemesis\Contracts\Services\NemesisAuthenticationInterface;
use AndyDefer\Nemesis\Contracts\Services\NemesisInterface;
use AndyDefer\Nemesis\Data\ErrorResponseData;
use AndyDefer\Nemesis\Enums\ErrorCode;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;
use Symfony\Component\HttpFoundation\Response;

/**
 * Middleware that checks if the authenticated user has verified their email.
 *
 * This middleware extends NemesisTokenMiddleware by adding email verification check.
 * If the user is authenticated but not verified, they receive a 403 error.
 */
final class NemesisApiVerifiedMiddleware
{
    public function __construct(
        private readonly NemesisAuthenticationInterface $authService,
        private readonly NemesisConfigInterface $config,
    ) {}

    public function handle(Request $request, Closure $next): Response
    {
        // 1. Authentifier l'utilisateur via Bearer token
        $authResult = $this->authService->authenticate($request);

        if (! $authResult->isSuccess()) {
            $errorCode = $authResult->getErrorCode() ?? ErrorCode::INVALID_TOKEN;
            $errorResponse = ErrorResponseData::from([
                'errorCode' => $errorCode,
                'message' => $errorCode->message(),
                'status' => $errorCode->getHttpStatusCode()->value,
                'details' => null,
            ]);

            return ResponseFactory::json($errorResponse, $errorCode->getHttpStatusCode()->value)->toResponse();
        }

        // 2. Récupérer le token model
        $tokenRecord = $authResult->getTokenRecord();
        $nemesisService = app(NemesisInterface::class);

        $tokenModel = null;
        if ($tokenRecord !== null && $tokenRecord->token_hash !== null) {
            $tokenModel = $nemesisService->findByHash($tokenRecord->token_hash);
        }

        // 3. Vérifier que le token est valide
        if ($tokenModel === null || ! $tokenModel->isValid()) {
            $errorResponse = ErrorResponseData::from([
                'errorCode' => ErrorCode::INVALID_TOKEN,
                'message' => ErrorCode::INVALID_TOKEN->message(),
                'status' => ErrorCode::INVALID_TOKEN->getHttpStatusCode()->value,
                'details' => null,
            ]);

            return ResponseFactory::json($errorResponse, ErrorCode::INVALID_TOKEN->getHttpStatusCode()->value)->toResponse();
        }

        // 4. Récupérer l'utilisateur via tokenable
        $authenticatable = $tokenModel->tokenable;

        if ($authenticatable === null) {
            $errorResponse = ErrorResponseData::from([
                'errorCode' => ErrorCode::AUTHENTICATABLE_NOT_FOUND,
                'message' => ErrorCode::AUTHENTICATABLE_NOT_FOUND->message(),
                'status' => ErrorCode::AUTHENTICATABLE_NOT_FOUND->getHttpStatusCode()->value,
                'details' => null,
            ]);

            return ResponseFactory::json($errorResponse, ErrorCode::AUTHENTICATABLE_NOT_FOUND->getHttpStatusCode()->value)->toResponse();
        }

        // 5. Vérifier que le modèle a le champ email_verified_at via Schema
        $table = $authenticatable->getTable();
        if (! Schema::hasColumn($table, 'email_verified_at')) {
            $errorResponse = ErrorResponseData::from([
                'errorCode' => ErrorCode::MODEL_MISSING_EMAIL_VERIFIED_AT,
                'message' => ErrorCode::MODEL_MISSING_EMAIL_VERIFIED_AT->message(),
                'status' => ErrorCode::MODEL_MISSING_EMAIL_VERIFIED_AT->getHttpStatusCode()->value,
                'details' => null,
            ]);

            return ResponseFactory::json($errorResponse, ErrorCode::MODEL_MISSING_EMAIL_VERIFIED_AT->getHttpStatusCode()->value)->toResponse();
        }

        // 6. Vérifier si l'email est vérifié
        if ($authenticatable->email_verified_at === null) {
            $errorResponse = ErrorResponseData::from([
                'errorCode' => ErrorCode::EMAIL_NOT_VERIFIED,
                'message' => ErrorCode::EMAIL_NOT_VERIFIED->message(),
                'status' => ErrorCode::EMAIL_NOT_VERIFIED->getHttpStatusCode()->value,
                'details' => null,
            ]);

            return ResponseFactory::json($errorResponse, ErrorCode::EMAIL_NOT_VERIFIED->getHttpStatusCode()->value)->toResponse();
        }

        // 7. Ajouter l'utilisateur et le token à la requête
        $request->merge([
            $this->config->middlewareConfig()->parameter_name => $authenticatable,
            'current_nemesis_token' => $tokenModel,
            $this->config->middlewareConfig()->parameter_name.'_format' => $this->authService->getFormattedAuthenticatable($authenticatable),
        ]);

        return $next($request);
    }
}
