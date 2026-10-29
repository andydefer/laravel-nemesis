<?php

declare(strict_types=1);

namespace AndyDefer\Nemesis\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Database\Eloquent\SoftDeletes;

final class NemesisToken extends Model
{
    use HasUuids;
    use SoftDeletes;

    public $incrementing = false;

    protected $keyType = 'string';

    protected $table = 'nemesis_tokens';

    protected $fillable = [
        'token_hash',
        'tokenable_type',
        'tokenable_id',
        'name',
        'source',
        'abilities',
        'metadata',
        'allowed_origins',
        'last_used_at',
        'expires_at',
    ];

    protected $casts = [
        'abilities' => 'array',
        'metadata' => 'array',
        'allowed_origins' => 'array',
        'last_used_at' => 'datetime',
        'expires_at' => 'datetime',
        'deleted_at' => 'datetime',
    ];

    protected $hidden = ['token_hash'];

    public function tokenable(): MorphTo
    {
        return $this->morphTo();
    }

    public function isExpired(): bool
    {
        if ($this->expires_at === null) {
            return false;
        }

        return $this->expires_at->isPast();
    }

    public function isRevoked(): bool
    {
        return $this->trashed();
    }

    public function isValid(): bool
    {
        if ($this->isExpired()) {
            return false;
        }

        return ! $this->trashed();
    }
}
