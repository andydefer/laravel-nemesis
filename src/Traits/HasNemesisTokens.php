<?php

declare(strict_types=1);

namespace Kani\Nemesis\Traits;

use Illuminate\Database\Eloquent\SoftDeletes;
use DateTimeInterface;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Support\Str;
use Kani\Nemesis\Models\NemesisToken;
use Kani\Nemesis\Services\TokenMetadataService;

/**
 * Trait for models that can own Nemesis tokens.
 *
 * Provides full token management capabilities for any Eloquent model.
 * The using class should implement MustNemesis interface.
 *
 * @mixin Model
 */
trait HasNemesisTokens
{
    /**
     * Get all tokens for this model.
     */
    public function nemesisTokens(): MorphMany
    {
        return $this->morphMany(NemesisToken::class, 'tokenable');
    }

    /**
     * Create a new token.
     */
    public function createNemesisToken(
        ?string $name = null,
        ?string $source = null,
        ?array $abilities = null,
        ?array $metadata = null
    ): string {
        $plainToken = Str::random(config('nemesis.token_length', 64));
        $hashedToken = hash(config('nemesis.hash_algorithm', 'sha256'), $plainToken);

        if ($metadata !== null) {
            TokenMetadataService::validate($metadata);
            $metadata = TokenMetadataService::sanitize($metadata);
        }

        $this->nemesisTokens()->create([
            'token_hash' => $hashedToken,
            'name' => $name,
            'source' => $source,
            'abilities' => $abilities,
            'metadata' => $metadata,
            'expires_at' => $this->getTokenExpiration(),
        ]);

        return $plainToken;
    }

    /**
     * Get token expiration date.
     */
    protected function getTokenExpiration(): ?DateTimeInterface
    {
        $expiration = config('nemesis.expiration');

        if ($expiration === null) {
            return null;
        }

        return now()->addMinutes($expiration);
    }

    /**
     * Delete all tokens permanently (force delete).
     *
     * @return int Number of tokens permanently deleted
     */
    public function deleteNemesisTokens(): int
    {
        return $this->nemesisTokens()->forceDelete();
    }

    /**
     * Revoke (soft delete) all tokens.
     *
     * @return int Number of tokens revoked
     */
    public function revokeNemesisTokens(): int
    {
        return $this->nemesisTokens()->delete();
    }

    /**
     * Delete current token permanently.
     */
    public function deleteCurrentNemesisToken(): void
    {
        if ($token = $this->currentNemesisToken()) {
            $token->forceDelete();
        }
    }

    /**
     * Revoke (soft delete) current token.
     */
    public function revokeCurrentNemesisToken(): void
    {
        if ($token = $this->currentNemesisToken()) {
            $token->delete();
        }
    }

    /**
     * Get current access token.
     */
    public function currentNemesisToken(): ?NemesisToken
    {
        $bearerToken = request()->bearerToken();

        if (! $bearerToken) {
            return null;
        }

        $hashedToken = hash(config('nemesis.hash_algorithm', 'sha256'), $bearerToken);

        return $this->nemesisTokens()
            ->where('token_hash', $hashedToken)
            ->latest('id')
            ->first();
    }

    /**
     * Check if model has tokens (including soft deleted).
     */
    public function hasNemesisTokens(bool $withTrashed = false): bool
    {
        $query = $this->nemesisTokens();

        if ($withTrashed && $this->isUsingSoftDeletes()) {
            $query = $query->withTrashed();
        }

        return $query->exists();
    }

    /**
     * Get token by plain text token.
     *
     * @param string $plainToken The plain text token to search for
     * @param bool $withTrashed Include soft deleted tokens
     * @return NemesisToken|null The token model or null if not found
     */
    public function getNemesisToken(string $plainToken, bool $withTrashed = false): ?NemesisToken
    {
        $hashedToken = hash(config('nemesis.hash_algorithm', 'sha256'), $plainToken);

        $query = $this->nemesisTokens()
            ->where('token_hash', $hashedToken)
            ->latest('id');

        if ($withTrashed && $this->isUsingSoftDeletes()) {
            $query = $query->withTrashed();
        }

        return $query->first();
    }

    /**
     * Validate a token.
     */
    public function validateNemesisToken(string $token, bool $includeRevoked = false): bool
    {
        $tokenModel = $this->getNemesisToken($token, $includeRevoked);

        if (! $tokenModel) {
            return false;
        }

        if ($includeRevoked) {
            return !$tokenModel->isExpired();
        }

        return $tokenModel->isValid();
    }

    /**
     * Update last used timestamp.
     */
    public function touchNemesisToken(string $token): void
    {
        if ($tokenModel = $this->getNemesisToken($token)) {
            $tokenModel->updateLastUsed();
        }
    }

    /**
     * Get tokens by source.
     */
    public function getNemesisTokensBySource(string $source, bool $withTrashed = false): iterable
    {
        $query = $this->nemesisTokens()
            ->where('source', $source);

        if ($withTrashed && $this->isUsingSoftDeletes()) {
            $query = $query->withTrashed();
        }

        return $query->get();
    }

    /**
     * Revoke (soft delete) all expired tokens.
     *
     * @return int Number of expired tokens revoked
     */
    public function revokeExpiredNemesisTokens(): int
    {
        return $this->nemesisTokens()
            ->where('expires_at', '<', now())
            ->delete();
    }

    /**
     * Permanently delete all expired tokens.
     *
     * @return int Number of expired tokens permanently deleted
     */
    public function forceDeleteExpiredNemesisTokens(): int
    {
        return $this->nemesisTokens()
            ->where('expires_at', '<', now())
            ->forceDelete();
    }

    /**
     * Restore all revoked tokens.
     *
     * @return int Number of tokens restored
     */
    public function restoreNemesisTokens(): int
    {
        if (!$this->isUsingSoftDeletes()) {
            return 0;
        }

        // Get only soft-deleted tokens and restore them
        $query = $this->nemesisTokens()->onlyTrashed();
        $count = $query->count();

        if ($count > 0) {
            $query->restore();
        }

        return $count;
    }

    /**
     * Check if the model uses SoftDeletes trait.
     */
    private function isUsingSoftDeletes(): bool
    {
        return in_array(SoftDeletes::class, class_uses_recursive($this));
    }
}
