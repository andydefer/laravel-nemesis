<?php

declare(strict_types=1);

namespace AndyDefer\Nemesis\Directives;

use AndyDefer\ConsoleWriter\Console\Components\AdaptiveTable;
use AndyDefer\Directive\AbstractDirective;
use AndyDefer\Directive\Enums\ExitCode;
use AndyDefer\DomainStructures\Collections\Utility\StringTypedCollection;
use AndyDefer\DomainStructures\Utils\ListCollection;
use AndyDefer\Nemesis\Models\NemesisToken;
use AndyDefer\Nemesis\Records\NemesisTokenFilterRecord;
use AndyDefer\Nemesis\Services\NemesisService;
use Illuminate\Support\Collection;

final class ListTokensDirective extends AbstractDirective
{
    public function getSignature(): string
    {
        return 'nemesis:list-tokens 
                    {limit=50}#"Maximum number of tokens to display" 
                    {model=?}#"Filter by model name (partial match allowed)"';
    }

    public function getDescription(): string
    {
        return 'List all tokens in the system';
    }

    public function getAliases(): StringTypedCollection
    {
        return StringTypedCollection::from(['tokens-list', 'nemesis-tokens']);
    }

    public function execute(): ExitCode
    {
        $console = $this->getConsole();
        $service = $this->getApplication()->make(NemesisService::class);

        $limit = (int) $this->getArgument('limit');
        $modelFilter = $this->getArgument('model');

        if ($modelFilter !== null && $modelFilter !== '') {
            $console->info(sprintf('Filtering by model: %s', $modelFilter));
            $tokens = NemesisToken::where('tokenable_type', 'LIKE', "%{$modelFilter}%")
                ->limit($limit)
                ->get();
        } else {
            $tokens = $service->findByFilters(
                new NemesisTokenFilterRecord,
                $limit
            );
        }

        if ($tokens->isEmpty()) {
            $console->alertWarning('No tokens found.');

            return ExitCode::SUCCESS;
        }

        $this->displayTokensTable($tokens);

        $console->info(sprintf('Total tokens: %d', $tokens->count()));

        return ExitCode::SUCCESS;
    }

    private function displayTokensTable(Collection $tokens): void
    {
        $headers = ListCollection::from([
            'ID',
            'Tokenable Type',
            'Tokenable ID',
            'Name',
            'Source',
            'Last Used',
            'Expires At',
        ]);

        $rows = ListCollection::from([]);

        foreach ($tokens as $token) {
            $row = ListCollection::from([
                (string) $token->id,
                $this->formatTokenableType($token),
                (string) ($token->tokenable_id ?? 'N/A'),
                $token->name ?? 'N/A',
                $token->source ?? 'N/A',
                $this->formatLastUsed($token),
                $this->formatExpiration($token),
            ]);
            $rows = $rows->add($row);
        }

        AdaptiveTable::render($headers, $rows);
    }

    private function formatTokenableType(NemesisToken $token): string
    {
        return class_basename($token->tokenable_type ?? 'Unknown');
    }

    private function formatLastUsed(NemesisToken $token): string
    {
        return $token->last_used_at?->diffForHumans() ?? 'Never';
    }

    private function formatExpiration(NemesisToken $token): string
    {
        if ($token->expires_at === null) {
            return 'Never';
        }

        return $token->expires_at->isPast()
            ? 'Expired '.$token->expires_at->diffForHumans()
            : $token->expires_at->diffForHumans();
    }
}
