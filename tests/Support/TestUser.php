<?php

declare(strict_types=1);

namespace Kani\Nemesis\Tests\Support;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Kani\Nemesis\Contracts\MustNemesis;
use Kani\Nemesis\Traits\HasNemesisTokens;

/**
 * Test model for users that can authenticate with Nemesis tokens.
 *
 * This model represents a typical User model in a Laravel application
 * and demonstrates the correct implementation of the MustNemesis interface.
 * Used for testing token authentication in a realistic context.
 *
 * @package Kani\Nemesis\Tests\Support
 */
final class TestUser extends Model implements MustNemesis
{
    use HasNemesisTokens;
    use SoftDeletes;

    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'test_users';

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'name',
        'email',
    ];

    /**
     * The attributes that should be cast to native types.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'email_verified_at' => 'datetime',
        'deleted_at' => 'datetime',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var array<int, string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * Indicates if the model should be timestamped.
     *
     * @var bool
     */
    public $timestamps = true;
}
