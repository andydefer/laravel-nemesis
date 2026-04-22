<?php
// tests/Support/TestUser.php

namespace Kani\Nemesis\Tests\Support;

use Illuminate\Database\Eloquent\Model;
use Kani\Nemesis\Contracts\MustNemesis;
use Kani\Nemesis\Traits\HasNemesisTokens;

/**
 * Test model for users that can authenticate with tokens.
 */
class TestUser extends Model implements MustNemesis
{
    use HasNemesisTokens;

    /**
     * The table associated with the model.
     */
    protected $table = 'test_users';

    /**
     * The attributes that are mass assignable.
     */
    protected $fillable = [
        'name',
        'email',
    ];

    /**
     * The attributes that should be cast.
     */
    protected $casts = [
        'email_verified_at' => 'datetime',
    ];
}
