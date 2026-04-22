<?php
// tests/Support/TestApiClient.php

namespace Kani\Nemesis\Tests\Support;

use Illuminate\Database\Eloquent\Model;
use Kani\Nemesis\Contracts\MustNemesis;
use Kani\Nemesis\Traits\HasNemesisTokens;

/**
 * Test model for API clients that can authenticate with tokens.
 */
class TestApiClient extends Model implements MustNemesis
{
    use HasNemesisTokens;

    /**
     * The table associated with the model.
     */
    protected $table = 'test_api_clients';

    /**
     * The attributes that are mass assignable.
     */
    protected $fillable = [
        'name',
        'api_key',
    ];
}
