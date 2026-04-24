<?php

declare(strict_types=1);

// src/Facades/Nemesis.php
namespace Kani\Nemesis\Facades;

use Kani\Nemesis\Models\NemesisToken;
use Illuminate\Support\Facades\Facade;

/**
 * @method static string createToken($model, string $name = null, string $source = null, array $abilities = null, array $metadata = null)
 * @method static bool validateToken($model, string $token)
 * @method static mixed getTokenableModel(string $token)
 * @method static bool deleteToken($model, string $token)
 * @method static void deleteAllTokens($model)
 * @method static int revokeExpiredTokens()
 * @method static NemesisToken|null getTokenModel(string $token)
 */
class Nemesis extends Facade
{
    protected static function getFacadeAccessor()
    {
        return 'nemesis';
    }
}
