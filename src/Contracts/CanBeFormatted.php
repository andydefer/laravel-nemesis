<?php

declare(strict_types=1);

namespace Kani\Nemesis\Contracts;

use AndyDefer\DomainStructures\Abstracts\AbstractRecord;

/**
 * Contract for models that can format themselves for API responses.
 *
 * This interface forces models to define how they should be exposed
 * when authenticated. No default implementation - the developer MUST
 * explicitly define what data is returned.
 *
 * @package Kani\Nemesis\Contracts
 */
interface CanBeFormatted
{
    /**
     * Define the format for authenticated API responses.
     *
     * This method MUST return a Record of data to expose.
     * It gives developers full control over what information
     * is sent to the client.
     *
     * @return AbstractRecord The formatted data record
     */
    public function nemesisFormat(): AbstractRecord;
}
