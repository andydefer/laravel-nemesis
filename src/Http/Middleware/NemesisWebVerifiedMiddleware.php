<?php

// src/Http/Middleware/NemesisWebVerifiedMiddleware.php

declare(strict_types=1);

namespace AndyDefer\Nemesis\Http\Middleware;

use AndyDefer\Nemesis\Contracts\Configs\NemesisConfigInterface;
use AndyDefer\Nemesis\Contracts\Services\CookieTokenStorageInterface;
use AndyDefer\Nemesis\Contracts\Services\NemesisAuthenticationInterface;
use AndyDefer\Nemesis\Contracts\Services\NemesisInterface;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;
use Symfony\Component\HttpFoundation\Response;

/**
 * Middleware that checks if the authenticated user has verified their email.
 *
 * This middleware extends NemesisWebMiddleware by adding email verification check.
 * If the user is authenticated but not verified, they are redirected to the verification page.
 */
final class NemesisWebVerifiedMiddleware
{
    public function __construct(
        private readonly CookieTokenStorageInterface $cookieTokenStorage,
        private readonly NemesisAuthenticationInterface $authService,
        private readonly NemesisConfigInterface $config,
    ) {}

    public function handle(Request $request, Closure $next): Response
    {
        // 1. Récupérer le token du cookie
        $plainToken = $this->cookieTokenStorage->get($request);

        if ($plainToken === null) {
            return redirect()->to($this->config->webConfig()->login_route);
        }

        // 2. Ajouter le token au header pour l'authentification
        $tokenHeader = $this->config->middlewareConfig()->token_header;
        $request->headers->set($tokenHeader, 'Bearer '.$plainToken);

        // 3. Authentifier l'utilisateur
        $authResult = $this->authService->authenticate($request);

        if (! $authResult->isSuccess()) {
            return redirect()->to($this->config->webConfig()->login_route);
        }

        // 4. Récupérer le token model
        $tokenRecord = $authResult->getTokenRecord();
        $nemesisService = app(NemesisInterface::class);

        $tokenModel = null;
        if ($tokenRecord !== null && $tokenRecord->token_hash !== null) {
            $tokenModel = $nemesisService->findByHash($tokenRecord->token_hash);
        }

        // 5. Vérifier que le token est valide
        if ($tokenModel === null || ! $tokenModel->isValid()) {
            return redirect()->to($this->config->webConfig()->login_route);
        }

        // 6. Récupérer l'utilisateur via tokenable
        $authenticatable = $tokenModel->tokenable;

        if ($authenticatable === null) {
            return redirect()->to($this->config->webConfig()->login_route);
        }

        // 7. Vérifier que le modèle a le champ email_verified_at via Schema
        $table = $authenticatable->getTable();
        if (! Schema::hasColumn($table, 'email_verified_at')) {
            abort(500, 'Model must have email_verified_at field');
        }

        // 8. Vérifier si l'email est vérifié
        if ($authenticatable->email_verified_at === null) {
            return redirect()->to($this->config->webConfig()->verification_route);
        }

        // 9. Ajouter l'utilisateur et le token à la requête
        $request->merge([
            $this->config->middlewareConfig()->parameter_name => $authenticatable,
            'current_nemesis_token' => $tokenModel,
            $this->config->middlewareConfig()->parameter_name.'_format' => $this->authService->getFormattedAuthenticatable($authenticatable),
        ]);

        return $next($request);
    }
}
