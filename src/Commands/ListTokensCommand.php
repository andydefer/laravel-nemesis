<?php

declare(strict_types=1);

namespace Kani\Nemesis\Commands;

use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Kani\Nemesis\Models\NemesisToken;

/**
 * Command to list all tokens in the system.
 *
 * Displays a formatted table of all tokens with optional filtering by model type.
 * Useful for debugging, auditing, and system administration.
 *
 * @package Kani\Nemesis\Commands
 */
final class ListTokensCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'nemesis:list {--model= : Filter by tokenable type (e.g., App\\Models\\User)}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'List all tokens in the system';

    /**
     * Table headers for the token listing.
     *
     * @var array<int, string>
     */
    private const TABLE_HEADERS = [
        'ID',
        'Tokenable Type',
        'Tokenable ID',
        'Name',
        'Source',
        'Last Used',
        'Expires At',
    ];

    /**
     * Execute the console command.
     *
     * @return int Exit code (0 for success)
     */
    public function handle(): int
    {
        $tokens = $this->getFilteredTokens();

        if ($this->shouldShowNoTokensWarning($tokens)) {
            return $this->displayNoTokensWarning();
        }

        $this->displayTokensTable($tokens);

        return self::SUCCESS;
    }

    /**
     * Get the filtered tokens based on the --model option.
     *
     * @return Collection<int, NemesisToken>
     */
    private function getFilteredTokens(): Collection
    {
        $query = $this->buildBaseQuery();

        $this->applyModelFilter($query);

        return $query->get();
    }

    /**
     * Build the base query with eager loading and ordering.
     *
     * @return Builder<NemesisToken>
     */
    private function buildBaseQuery(): Builder
    {
        return NemesisToken::with('tokenable')->latest();
    }

    /**
     * Apply the model filter to the query if provided.
     *
     * @param Builder<NemesisToken> $query The query builder instance
     */
    private function applyModelFilter(Builder $query): void
    {
        $modelFilter = $this->option('model');

        if ($modelFilter !== null) {
            $query->where('tokenable_type', $modelFilter);
        }
    }

    /**
     * Determine if the no tokens warning should be displayed.
     *
     * @param Collection<int, NemesisToken> $tokens
     * @return bool
     */
    private function shouldShowNoTokensWarning(Collection $tokens): bool
    {
        return $tokens->isEmpty();
    }

    /**
     * Display the no tokens found warning.
     *
     * @return int Exit code
     */
    private function displayNoTokensWarning(): int
    {
        $this->warn('No tokens found.');
        return self::SUCCESS;
    }

    /**
     * Display the tokens in a formatted table.
     *
     * @param Collection<int, NemesisToken> $tokens
     */
    private function displayTokensTable(Collection $tokens): void
    {
        $this->table(
            self::TABLE_HEADERS,
            $this->formatTokensForTable($tokens)
        );
    }

    /**
     * Format tokens for table display.
     *
     * @param Collection<int, NemesisToken> $tokens
     * @return array<int, array<int, string>>
     */
    private function formatTokensForTable(Collection $tokens): array
    {
        return $tokens->map(fn(NemesisToken $token): array => [
            $token->id,
            $this->formatTokenableType($token),
            $token->tokenable_id,
            $this->formatName($token),
            $this->formatSource($token),
            $this->formatLastUsed($token),
            $this->formatExpiration($token),
        ])->toArray();
    }

    /**
     * Format the tokenable type for display.
     *
     * @param NemesisToken $token
     * @return string
     */
    private function formatTokenableType(NemesisToken $token): string
    {
        return class_basename($token->tokenable_type);
    }

    /**
     * Format the token name for display.
     *
     * @param NemesisToken $token
     * @return string
     */
    private function formatName(NemesisToken $token): string
    {
        return $token->name ?? 'N/A';
    }

    /**
     * Format the token source for display.
     *
     * @param NemesisToken $token
     * @return string
     */
    private function formatSource(NemesisToken $token): string
    {
        return $token->source ?? 'N/A';
    }

    /**
     * Format the last used timestamp for display.
     *
     * @param NemesisToken $token
     * @return string
     */
    private function formatLastUsed(NemesisToken $token): string
    {
        return $token->last_used_at?->diffForHumans() ?? 'Never';
    }

    /**
     * Format the expiration timestamp for display.
     *
     * @param NemesisToken $token
     * @return string
     */
    private function formatExpiration(NemesisToken $token): string
    {
        return $token->expires_at?->diffForHumans() ?? 'Never';
    }
}
