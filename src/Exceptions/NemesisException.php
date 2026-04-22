<?php
// src/Exceptions/NemesisException.php

namespace Kani\Nemesis\Exceptions;

use Exception;

class NemesisException extends Exception
{
    /**
     * Create a new exception instance.
     */
    public static function invalidToken(): self
    {
        return new static('Invalid token provided.');
    }

    /**
     * Create a new exception instance for expired token.
     */
    public static function tokenExpired(): self
    {
        return new static('Token has expired.');
    }

    /**
     * Create a new exception instance for missing token.
     */
    public static function missingToken(): self
    {
        return new static('No token provided.');
    }

    /**
     * Create a new exception instance for insufficient permissions.
     */
    public static function insufficientPermissions(string $ability = null): self
    {
        $message = 'Insufficient permissions.';

        if ($ability) {
            $message .= " Required ability: {$ability}";
        }

        return new static($message);
    }
}
