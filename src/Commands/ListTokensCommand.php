<?php
// src/Commands/ListTokensCommand.php

declare(strict_types=1);

namespace Kani\Nemesis\Commands;

use Illuminate\Console\Command;
use Kani\Nemesis\Models\NemesisToken;

final class ListTokensCommand extends Command
{
    protected $signature = 'nemesis:list {--model= : Filter by tokenable type}';
    protected $description = 'List all tokens in the system';

    public function handle(): int
    {
        $query = NemesisToken::with('tokenable');

        if ($model = $this->option('model')) {
            $query->where('tokenable_type', $model);
        }

        $tokens = $query->latest()->get();

        if ($tokens->isEmpty()) {
            $this->warn('No tokens found.');
            return self::SUCCESS;
        }

        $this->table(
            ['ID', 'Tokenable Type', 'Tokenable ID', 'Name', 'Source', 'Last Used', 'Expires At'],
            $tokens->map(fn($token) => [
                $token->id,
                class_basename($token->tokenable_type),
                $token->tokenable_id,
                $token->name ?? 'N/A',
                $token->source ?? 'N/A',
                $token->last_used_at?->diffForHumans() ?? 'Never',
                $token->expires_at?->diffForHumans() ?? 'Never',
            ])
        );

        return self::SUCCESS;
    }
}
