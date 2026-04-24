<?php

declare(strict_types=1);

namespace Kani\Nemesis\Contracts;

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
     * This method MUST return an array of data to expose.
     * It gives developers full control over what information
     * is sent to the client.
     *
     * @return array<string, mixed> The formatted data array
     *
     * @example
     * // In your User model:
     * public function nemesisFormat(): array
     * {
     *     return [
     *         'id' => $this->id,
     *         'name' => $this->name,
     *         'email' => $this->email,
     *     ];
     * }
     */
    public function nemesisFormat(): array;
}
