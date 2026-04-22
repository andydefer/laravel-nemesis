<?php
// src/Contracts/MustNemesis.php

namespace Kani\Nemesis\Contracts;

use Illuminate\Database\Eloquent\Relations\MorphMany;

interface MustNemesis
{
    /**
     * Get all tokens for the authenticatable model
     */
    public function nemesisTokens(): MorphMany;

    /**
     * Create a new token for the model
     */
    public function createNemesisToken(
        string $name = null,
        string $source = null,
        array $abilities = null,
        array $metadata = null
    ): string;

    /**
     * Delete all tokens for the model
     */
    public function deleteNemesisTokens(): void;

    /**
     * Delete current token
     */
    public function deleteCurrentNemesisToken(): void;

    /**
     * Get the current access token
     */
    public function currentNemesisToken();

    /**
     * Check if model has valid tokens
     */
    public function hasNemesisTokens(): bool;

    /**
     * Get token by plain text token
     */
    public function getNemesisToken(string $plainToken);

    /**
     * Validate a token
     */
    public function validateNemesisToken(string $token): bool;
}
