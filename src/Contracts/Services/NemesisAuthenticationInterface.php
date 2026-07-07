<?php

declare(strict_types=1);

namespace AndyDefer\Nemesis\Contracts\Services;

use AndyDefer\DomainStructures\Abstracts\AbstractData;
use AndyDefer\Nemesis\Records\AuthenticationResultRecord;
use AndyDefer\Nemesis\ValueObjects\AuthenticationResultVO;
use Illuminate\Http\Request;

/**
 * Interface for the Nemesis authentication service.
 *
 * Provides authentication capabilities using Nemesis tokens.
 * Handles token extraction, validation, expiration checking,
 * origin restrictions, and permission verification.
 */
interface NemesisAuthenticationInterface
{
    /**
     * Authenticate a request using a Nemesis token.
     *
     * This method extracts the token from the request, validates it,
     * checks expiration and origin restrictions, and verifies the
     * required ability if provided.
     *
     * @param  Request  $request  The HTTP request containing the token
     * @param  string|null  $requiredAbility  Optional ability that the token must have
     * @return AuthenticationResultVO The authentication result
     */
    public function authenticate(Request $request, ?string $requiredAbility = null): AuthenticationResultVO;

    /**
     * Authenticate a request and return the result as a record.
     *
     * This is a convenience method that wraps authenticate() and returns
     * the result as an AuthenticationResultRecord.
     *
     * @param  Request  $request  The HTTP request containing the token
     * @param  string|null  $requiredAbility  Optional ability that the token must have
     * @return AuthenticationResultRecord The authentication result as a record
     */
    public function authenticateToRecord(Request $request, ?string $requiredAbility = null): AuthenticationResultRecord;

    /**
     * Get the formatted authenticatable model data.
     *
     * Returns the nemesisFormat() of the authenticatable model if it implements
     * the MustNemesis interface, otherwise returns null.
     *
     * @param  mixed  $authenticatable  The authenticatable model
     * @return AbstractData|null The formatted data or null
     */
    public function getFormattedAuthenticatable(mixed $authenticatable): ?AbstractData;
}
