<?php

declare(strict_types=1);

namespace Kani\Nemesis;

use DateTimeInterface;
use Illuminate\Database\Eloquent\Model;
use Kani\Nemesis\Contracts\MustNemesis;
use Kani\Nemesis\Models\NemesisToken;

/**
 * Manager class for Nemesis token operations.
 *
 * Provides a convenient facade for common token operations including
 * creation, validation, deletion, and management of tokens across
 * different authenticatable models.
 *
 * @package Kani\Nemesis
 */
final class NemesisManager
{
    /**
     * Create a new token for an authenticatable model.
     *
     * @param MustNemesis&Model $model The authenticatable model
     * @param string|null $name Descriptive name for the token
     * @param string|null $source Source/origin (web, mobile, api, etc.)
     * @param array<int, string>|null $abilities Array of allowed abilities
     * @param array<string, mixed>|null $metadata Additional metadata
     * @return string The plain text token (store securely, show only once)
     *
     * @example
     * $user = User::find(1);
     * $token = $manager->createToken($user, 'Mobile App', 'mobile', ['read', 'write']);
     */
    public function createToken(
        MustNemesis&Model $model,
        ?string $name = null,
        ?string $source = null,
        ?array $abilities = null,
        ?array $metadata = null
    ): string {
        return $model->createNemesisToken($name, $source, $abilities, $metadata);
    }

    /**
     * Validate a token for an authenticatable model.
     *
     * @param MustNemesis&Model $model The authenticatable model
     * @param string $token The plain text token to validate
     * @return bool True if the token is valid, false otherwise
     *
     * @example
     * if ($manager->validateToken($user, $token)) {
     *     // Token is valid
     * }
     */
    public function validateToken(MustNemesis&Model $model, string $token): bool
    {
        return $model->validateNemesisToken($token);
    }

    /**
     * Get the token model from a plain text token.
     *
     * @param string $token The plain text token
     * @return NemesisToken|null The token model or null if not found
     *
     * @example
     * $tokenModel = $manager->getTokenModel($token);
     * if ($tokenModel && !$tokenModel->isExpired()) {
     *     // Token is valid and not expired
     * }
     */
    public function getTokenModel(string $token): ?NemesisToken
    {
        $hashedToken = $this->hashToken($token);

        return NemesisToken::where('token_hash', $hashedToken)
            ->with('tokenable')
            ->first();
    }

    /**
     * Get the authenticatable model from a plain text token.
     *
     * @param string $token The plain text token
     * @return Model|null The authenticatable model or null if invalid/expired
     *
     * @example
     * $user = $manager->getTokenableModel($token);
     * if ($user) {
     *     // Token is valid and not expired
     *     $userId = $user->id;
     * }
     */
    public function getTokenableModel(string $token): ?Model
    {
        $tokenModel = $this->getTokenModel($token);

        if ($tokenModel === null || $tokenModel->isExpired()) {
            return null;
        }

        $authenticatable = $tokenModel->tokenable;

        return $authenticatable instanceof Model ? $authenticatable : null;
    }

    /**
     * Delete a specific token for an authenticatable model.
     *
     * @param MustNemesis&Model $model The authenticatable model
     * @param string $token The plain text token to delete
     * @return bool True if the token was deleted, false otherwise
     *
     * @example
     * if ($manager->deleteToken($user, $token)) {
     *     // Token was deleted successfully
     * }
     */
    public function deleteToken(MustNemesis&Model $model, string $token): bool
    {
        $tokenModel = $model->getNemesisToken($token);

        if ($tokenModel === null) {
            return false;
        }

        return (bool) $tokenModel->delete();
    }

    /**
     * Delete all tokens for an authenticatable model.
     *
     * @param MustNemesis&Model $model The authenticatable model
     * @return int Number of tokens deleted
     *
     * @example
     * $deletedCount = $manager->deleteAllTokens($user);
     * // Logout from all devices
     */
    public function deleteAllTokens(MustNemesis&Model $model): int
    {
        return $model->deleteNemesisTokens();
    }

    /**
     * Revoke (soft delete) all tokens for an authenticatable model by source.
     *
     * @param MustNemesis&Model $model The authenticatable model
     * @param string $source The source to filter by
     * @param bool $force Whether to force delete instead of soft delete
     * @return int Number of tokens revoked
     *
     * @example
     * // Logout from all web sessions
     * $revokedCount = $manager->revokeTokensBySource($user, 'web');
     */
    public function revokeTokensBySource(MustNemesis&Model $model, string $source, bool $force = false): int
    {
        return $model->revokeNemesisTokensBySource($source, $force);
    }

    /**
     * Revoke (soft delete) all expired tokens across all models.
     *
     * @return int Number of expired tokens revoked
     *
     * @example
     * $revokedCount = $manager->revokeExpiredTokens();
     * echo "Revoked {$revokedCount} expired tokens";
     */
    public function revokeExpiredTokens(): int
    {
        return NemesisToken::where('expires_at', '<', now())->delete();
    }

    /**
     * Revoke (soft delete) tokens older than a specific date.
     *
     * @param DateTimeInterface $date Cutoff date (tokens created before this date)
     * @return int Number of tokens revoked
     *
     * @example
     * $oneMonthAgo = now()->subMonth();
     * $revokedCount = $manager->revokeTokensOlderThan($oneMonthAgo);
     */
    public function revokeTokensOlderThan(DateTimeInterface $date): int
    {
        return NemesisToken::where('created_at', '<', $date)->delete();
    }

    /**
     * Get all tokens for an authenticatable model filtered by source.
     *
     * @param MustNemesis&Model $model The authenticatable model
     * @param string $source The source to filter by
     * @return iterable<NemesisToken> Collection of tokens
     *
     * @example
     * $mobileTokens = $manager->getTokensBySource($user, 'mobile');
     * foreach ($mobileTokens as $token) {
     *     echo $token->name;
     * }
     */
    public function getTokensBySource(MustNemesis&Model $model, string $source): iterable
    {
        return $model->getNemesisTokensBySource($source);
    }

    /**
     * Check if a token is valid (not expired and not revoked).
     *
     * @param string $token The plain text token
     * @return bool True if the token is valid, false otherwise
     *
     * @example
     * if ($manager->isTokenValid($token)) {
     *     // Token can be used
     * }
     */
    public function isTokenValid(string $token): bool
    {
        $tokenModel = $this->getTokenModel($token);

        if ($tokenModel === null) {
            return false;
        }

        return $tokenModel->isValid();
    }

    /**
     * Check if a token has a specific ability.
     *
     * @param string $token The plain text token
     * @param string $ability The ability to check
     * @return bool True if the token has the ability, false otherwise
     *
     * @example
     * if ($manager->tokenHasAbility($token, 'admin')) {
     *     // Token has admin privileges
     * }
     */
    public function tokenHasAbility(string $token, string $ability): bool
    {
        $tokenModel = $this->getTokenModel($token);

        if ($tokenModel === null) {
            return false;
        }

        return $tokenModel->can($ability);
    }

    /**
     * Get the expiration timestamp of a token.
     *
     * @param string $token The plain text token
     * @return DateTimeInterface|null Expiration timestamp or null if never expires
     *
     * @example
     * $expiresAt = $manager->getTokenExpiration($token);
     * if ($expiresAt && $expiresAt->isPast()) {
     *     // Token is expired
     * }
     */
    public function getTokenExpiration(string $token): ?DateTimeInterface
    {
        $tokenModel = $this->getTokenModel($token);

        if ($tokenModel === null) {
            return null;
        }

        return $tokenModel->expires_at;
    }

    /**
     * Update the last used timestamp for a token.
     *
     * @param string $token The plain text token
     * @return bool True if the token was updated, false otherwise
     *
     * @example
     * $manager->touchToken($token);
     */
    public function touchToken(string $token): bool
    {
        $tokenModel = $this->getTokenModel($token);

        if ($tokenModel === null) {
            return false;
        }

        $tokenModel->updateLastUsed();

        return true;
    }

    /**
     * Hash a plain text token using the configured algorithm.
     *
     * @param string $token The plain text token
     * @return string The hashed token
     */
    private function hashToken(string $token): string
    {
        return hash(config('nemesis.hash_algorithm', 'sha256'), $token);
    }
}
