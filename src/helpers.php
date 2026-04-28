<?php

declare(strict_types=1);

use Kani\Nemesis\Models\NemesisToken;
use Kani\Nemesis\NemesisManager;

if (!function_exists('nemesis')) {
    /**
     * Get the Nemesis manager instance from the container.
     *
     * Provides access to the main Nemesis manager for token operations
     * such as token validation, ability checking, and metadata management.
     *
     * @return NemesisManager The Nemesis manager instance
     *
     * @example
     * $manager = nemesis();
     * $isValid = $manager->validateToken($token);
     */
    function nemesis(): NemesisManager
    {
        return app('nemesis');
    }
}

if (!function_exists('current_token')) {
    /**
     * Get the current token model from the request.
     *
     * Retrieves the token model that was authenticated by the middleware
     * for the current request. Useful for checking token properties like
     * abilities, expiration, or metadata.
     *
     * The middleware attaches the token to the request via $request->merge(),
     * making it accessible through request()->input().
     *
     * @return NemesisToken|null The current token model or null if not available
     *
     * @example
     * $token = current_token();
     * if ($token && $token->can('admin')) {
     *     // Token has admin privileges
     * }
     */
    function current_token(): ?NemesisToken
    {
        // The middleware uses $request->merge(), so data is accessible via input()
        $token = request()->input('currentNemesisToken');

        if ($token instanceof NemesisToken) {
            return $token;
        }

        // Fallback for backward compatibility with older versions
        return request()->route('currentNemesisToken');
    }
}

if (!function_exists('current_authenticatable')) {
    /**
     * Get the current authenticated model from the request.
     *
     * Retrieves the authenticatable model (User, ApiClient, etc.) that owns
     * the current token. This is the model that was authenticated via the token.
     *
     * The middleware attaches the authenticatable model to the request via
     * $request->merge(), making it accessible through request()->input().
     *
     * @return \Illuminate\Database\Eloquent\Model|null The authenticated model or null
     *
     * @example
     * $user = current_authenticatable();
     * if ($user) {
     *     $name = $user->name;
     * }
     */
    function current_authenticatable(): ?\Illuminate\Database\Eloquent\Model
    {
        $parameterName = config('nemesis.middleware.parameter_name', 'nemesisAuth');

        // The middleware uses $request->merge(), so data is accessible via input()
        $authenticatable = request()->input($parameterName);

        if ($authenticatable instanceof \Illuminate\Database\Eloquent\Model) {
            return $authenticatable;
        }

        // Fallback for backward compatibility with older versions
        return request()->route($parameterName);
    }
}

if (!function_exists('current_authenticatable_format')) {
    /**
     * Get the formatted version of the current authenticated model.
     *
     * Returns the array defined by nemesisFormat() method on the model.
     * This forces developers to explicitly control what data is exposed.
     *
     * The middleware attaches the formatted data to the request via
     * $request->merge(), making it accessible through request()->input().
     *
     * @return array<string, mixed>|null The formatted data or null if not authenticated
     *
     * @example
     * // In your controller
     * return response()->json([
     *     'user' => current_authenticatable_format(),
     *     'token' => current_token()
     * ]);
     */
    function current_authenticatable_format(): ?array
    {
        $parameterName = config('nemesis.middleware.parameter_name', 'nemesisAuth');
        $formatKey = $parameterName . 'Format';

        // The middleware uses $request->merge(), so data is accessible via input()
        $formatted = request()->input($formatKey);

        if (is_array($formatted)) {
            return $formatted;
        }

        // Fallback: generate from the model if available (backward compatibility)
        $user = current_authenticatable();
        if ($user && method_exists($user, 'nemesisFormat')) {
            return $user->nemesisFormat();
        }

        return null;
    }
}
