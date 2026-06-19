<?php

declare(strict_types=1);

namespace AndyDefer\Nemesis\Contracts;

use AndyDefer\DomainStructures\Abstracts\AbstractData;

/**
 * Contract for models that can be authenticated with Nemesis tokens.
 *
 * Models implementing this interface must define how they should be formatted
 * for API responses when authenticated via Nemesis tokens.
 */
interface MustNemesis
{
    /**
     * Define the format for authenticated API responses.
     *
     * This method MUST return a Record of data to expose.
     * It gives developers full control over what information
     * is sent to the client.
     *
     * @return AbstractData The formatted data record
     */
    public function nemesisFormat(): AbstractData;
}
