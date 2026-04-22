<?php

declare(strict_types=1);

namespace Kani\Nemesis\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Kani\Nemesis\Models\NemesisToken;
use Kani\Nemesis\Enums\ErrorCode;
use Kani\Nemesis\Data\ErrorResponseData;

class NemesisAuth
{
    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next, string $ability = null)
    {
        $token = $request->bearerToken();

        if (! $token) {
            $errorResponse = ErrorResponseData::fromErrorCode(ErrorCode::MISSING_TOKEN);
            return response()->json($errorResponse->toArray(), $errorResponse->status);
        }

        // Hasher le token pour la recherche
        $hashedToken = hash(config('nemesis.hash_algorithm', 'sha256'), $token);

        // Chercher le token avec son modèle associé
        $tokenModel = NemesisToken::where('token', $hashedToken)
            ->with('tokenable')
            ->first();

        if (! $tokenModel) {
            $errorResponse = ErrorResponseData::fromErrorCode(ErrorCode::INVALID_TOKEN);
            return response()->json($errorResponse->toArray(), $errorResponse->status);
        }

        // Vérifier l'expiration
        if ($tokenModel->isExpired()) {
            $errorResponse = ErrorResponseData::fromErrorCode(ErrorCode::TOKEN_EXPIRED);
            return response()->json($errorResponse->toArray(), $errorResponse->status);
        }

        // Vérifier l'ability si spécifiée
        if ($ability && !$tokenModel->can($ability)) {
            $errorResponse = ErrorResponseData::fromErrorCode(
                ErrorCode::INSUFFICIENT_PERMISSIONS,
                ['required_ability' => $ability]
            );
            return response()->json($errorResponse->toArray(), $errorResponse->status);
        }

        // Mettre à jour la date de dernière utilisation
        $tokenModel->update(['last_used_at' => now()]);

        // Récupérer le modèle authentifiable
        $authenticatable = $tokenModel->tokenable;

        if (! $authenticatable) {
            $errorResponse = ErrorResponseData::fromErrorCode(ErrorCode::INVALID_TOKEN);
            return response()->json($errorResponse->toArray(), $errorResponse->status);
        }

        // Stocker le modèle et le token dans la requête
        $parameterName = config('nemesis.middleware.parameter_name', 'nemesisAuth');
        $request->merge([
            $parameterName => $authenticatable,
            'currentNemesisToken' => $tokenModel,
        ]);

        // Partager avec le route binding
        if ($request->route()) {
            $request->route()->setParameter($parameterName, $authenticatable);
        }

        return $next($request);
    }
}
