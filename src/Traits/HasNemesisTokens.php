<?php
// src/Traits/HasNemesisTokens.php

namespace Kani\Nemesis\Traits;

use Kani\Nemesis\Models\NemesisToken;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Hash;

trait HasNemesisTokens
{
    /**
     * Get all tokens for this model
     */
    public function nemesisTokens()
    {
        return $this->morphMany(NemesisToken::class, 'tokenable');
    }

    /**
     * Create a new token
     */
    public function createNemesisToken(
        string $name = null,
        string $source = null,
        array $abilities = null,
        array $metadata = null
    ): string {
        $plainToken = Str::random(config('nemesis.token_length', 64));
        $hashedToken = hash(config('nemesis.hash_algorithm', 'sha256'), $plainToken);

        $this->nemesisTokens()->create([
            'token' => $hashedToken,
            'name' => $name,
            'source' => $source,
            'abilities' => $abilities,
            'metadata' => $metadata,
            'expires_at' => $this->getTokenExpiration(),
        ]);

        return $plainToken;
    }

    /**
     * Get token expiration date
     */
    protected function getTokenExpiration()
    {
        $expiration = config('nemesis.expiration');

        if ($expiration === null) {
            return null;
        }

        return now()->addMinutes($expiration);
    }

    /**
     * Delete all tokens
     */
    public function deleteNemesisTokens(): void
    {
        $this->nemesisTokens()->delete();
    }

    /**
     * Delete current token
     */
    public function deleteCurrentNemesisToken(): void
    {
        if ($token = $this->currentNemesisToken()) {
            $token->delete();
        }
    }

    /**
     * Get current access token
     */
    public function currentNemesisToken()
    {
        $bearerToken = request()->bearerToken();

        if (!$bearerToken) {
            return null;
        }

        $hashedToken = hash(config('nemesis.hash_algorithm', 'sha256'), $bearerToken);

        return $this->nemesisTokens()
            ->where('token', $hashedToken)
            ->first();
    }

    /**
     * Check if model has tokens
     */
    public function hasNemesisTokens(): bool
    {
        return $this->nemesisTokens()->exists();
    }

    /**
     * Get token by plain text token
     */
    public function getNemesisToken(string $plainToken)
    {
        $hashedToken = hash(config('nemesis.hash_algorithm', 'sha256'), $plainToken);

        return $this->nemesisTokens()
            ->where('token', $hashedToken)
            ->first();
    }

    /**
     * Validate a token
     */
    public function validateNemesisToken(string $token): bool
    {
        $tokenModel = $this->getNemesisToken($token);

        if (!$tokenModel) {
            return false;
        }

        if ($tokenModel->expires_at && $tokenModel->expires_at->isPast()) {
            return false;
        }

        return true;
    }

    /**
     * Update last used timestamp
     */
    public function touchNemesisToken(string $token): void
    {
        if ($tokenModel = $this->getNemesisToken($token)) {
            $tokenModel->update(['last_used_at' => now()]);
        }
    }

    /**
     * Get tokens by source
     */
    public function getNemesisTokensBySource(string $source)
    {
        return $this->nemesisTokens()
            ->where('source', $source)
            ->get();
    }

    /**
     * Revoke expired tokens
     */
    public function revokeExpiredNemesisTokens(): int
    {
        return $this->nemesisTokens()
            ->where('expires_at', '<', now())
            ->delete();
    }
}
