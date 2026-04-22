<?php
// src/NemesisManager.php

namespace Kani\Nemesis;

use Kani\Nemesis\Models\NemesisToken;
use Illuminate\Support\Str;

class NemesisManager
{
    /**
     * Create a token for a model
     */
    public function createToken(
        $model,
        string $name = null,
        string $source = null,
        array $abilities = null,
        array $metadata = null
    ): string {
        return $model->createNemesisToken($name, $source, $abilities, $metadata);
    }

    /**
     * Validate a token for a model
     */
    public function validateToken($model, string $token): bool
    {
        return $model->validateNemesisToken($token);
    }

    /**
     * Get token model from token
     */
    public function getTokenModel(string $token): ?NemesisToken
    {
        $hashedToken = hash(config('nemesis.hash_algorithm', 'sha256'), $token);

        return NemesisToken::where('token', $hashedToken)
            ->with('tokenable')
            ->first();
    }

    /**
     * Get tokenable model from token
     */
    public function getTokenableModel(string $token)
    {
        $tokenModel = $this->getTokenModel($token);

        if (!$tokenModel || $tokenModel->isExpired()) {
            return null;
        }

        return $tokenModel->tokenable;
    }

    /**
     * Delete a specific token
     */
    public function deleteToken($model, string $token): bool
    {
        $tokenModel = $model->getNemesisToken($token);

        if ($tokenModel) {
            return $tokenModel->delete();
        }

        return false;
    }

    /**
     * Delete all tokens for a model
     */
    public function deleteAllTokens($model): void
    {
        $model->deleteNemesisTokens();
    }

    /**
     * Revoke all expired tokens
     */
    public function revokeExpiredTokens(): int
    {
        return NemesisToken::where('expires_at', '<', now())->delete();
    }

    /**
     * Revoke tokens older than given date
     */
    public function revokeTokensOlderThan(\DateTimeInterface $date): int
    {
        return NemesisToken::where('created_at', '<', $date)->delete();
    }

    /**
     * Get all tokens for a model by source
     */
    public function getTokensBySource($model, string $source)
    {
        return $model->getNemesisTokensBySource($source);
    }
}
