<?php

namespace Kani\Nemesis\Models;

use Illuminate\Database\Eloquent\Model;

class NemesisToken extends Model
{
    protected $fillable = [
        'token',
        'allowed_origins',
        'max_requests',
        'requests_count',
        'last_request_at',
    ];

    protected $casts = [
        'allowed_origins' => 'array',
        'last_request_at' => 'datetime',
    ];
}
