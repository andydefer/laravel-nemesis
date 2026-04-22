<?php
// src/helpers.php

declare(strict_types=1);

if (!function_exists('nemesis')) {
    /**
     * Get the nemesis token manager instance.
     */
    function nemesis(): \Kani\Nemesis\NemesisManager
    {
        return app('nemesis');
    }
}

if (!function_exists('current_token')) {
    /**
     * Get the current authenticated token from the request.
     */
    function current_token(): ?\Kani\Nemesis\Models\NemesisToken
    {
        return request()->route('currentNemesisToken');
    }
}

if (!function_exists('current_authenticatable')) {
    /**
     * Get the current authenticated model from the request.
     */
    function current_authenticatable()
    {
        $parameterName = config('nemesis.middleware.parameter_name', 'nemesisAuth');
        return request()->route($parameterName);
    }
}
