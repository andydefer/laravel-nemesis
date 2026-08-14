<?php

// src/Records/WebConfigRecord.php

declare(strict_types=1);

namespace AndyDefer\Nemesis\Records;

use AndyDefer\DomainStructures\Abstracts\AbstractRecord;

/**
 * Configuration record for web authentication settings.
 *
 * This record holds all configuration options for web route protection
 * using cookie-based token authentication.
 */
final class WebConfigRecord extends AbstractRecord
{
    /**
     * Create a new WebConfigRecord instance.
     *
     * @param  string  $login_route  The route to redirect unauthenticated users to
     * @param  string  $dashboard_route  The route to redirect authenticated users to
     * @param  string  $cookie_name  The name of the cookie storing the token
     * @param  bool  $cookie_secure  Whether the cookie should only be sent over HTTPS
     * @param  bool  $cookie_httponly  Whether the cookie should be inaccessible to JavaScript
     * @param  string  $cookie_samesite  The SameSite attribute for the cookie (lax, strict, none)
     */
    public function __construct(
        public readonly string $login_route = '/login',
        public readonly string $dashboard_route = '/dashboard',
        public readonly string $cookie_name = 'nemesis_token',
        public readonly bool $cookie_secure = true,
        public readonly bool $cookie_httponly = true,
        public readonly string $cookie_samesite = 'lax',
    ) {}
}
