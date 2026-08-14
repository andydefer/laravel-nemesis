<?php

// src/Contracts/Services/CookieTokenStorageInterface.php

declare(strict_types=1);

namespace AndyDefer\Nemesis\Contracts\Services;

use AndyDefer\Nemesis\Models\NemesisToken;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;

/**
 * Interface for cookie-based token storage.
 *
 * This interface defines the contract for storing and retrieving
 * authentication tokens from HTTP cookies. It provides methods for
 * storing, retrieving, validating, and removing tokens.
 */
interface CookieTokenStorageInterface
{
    /**
     * Store a token in the cookie.
     *
     * @param  string  $plainToken  The plain text token to store
     */
    public function store(string $plainToken): void;

    /**
     * Get the plain token from the cookie.
     *
     * @param  Request  $request  The HTTP request
     * @return string|null The plain token or null if not found
     */
    public function get(Request $request): ?string;

    /**
     * Check if a token exists in the cookie.
     *
     * @param  Request  $request  The HTTP request
     * @return bool True if the cookie exists, false otherwise
     */
    public function has(Request $request): bool;

    /**
     * Remove the token from the cookie.
     */
    public function forget(): void;

    /**
     * Get the validated token from the cookie.
     *
     * Retrieves the token from the cookie, validates it against the database,
     * and returns the token model if it is valid (not expired and not revoked).
     *
     * @param  Request  $request  The HTTP request
     * @return NemesisToken|null The validated token or null if invalid
     */
    public function getValidatedToken(Request $request): ?NemesisToken;

    /**
     * Get the authenticatable model from the token.
     *
     * Retrieves the token from the cookie and returns the associated
     * authenticatable model (User, etc.) if the token is valid.
     *
     * @param  Request  $request  The HTTP request
     * @return Model|null The authenticatable model or null
     */
    public function getAuthenticatable(Request $request): ?Model;

    /**
     * Check if the current request has a valid token.
     *
     * @param  Request  $request  The HTTP request
     * @return bool True if a valid token exists, false otherwise
     */
    public function isValid(Request $request): bool;
}
