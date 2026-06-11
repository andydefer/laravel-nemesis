<?php

// src/Directives/ListTokensDirective.php

declare(strict_types=1);

namespace AndyDefer\Nemesis\Directives;

use AndyDefer\Directive\AbstractDirective;
use AndyDefer\Directive\Collections\RowCollection;
use AndyDefer\Directive\Contexts\DirectiveContext;
use AndyDefer\Directive\Enums\ExitCode;
use AndyDefer\Directive\Services\DirectiveInteractionService;
use AndyDefer\DomainStructures\Collections\Utility\StringTypedCollection;
use Illuminate\Support\Collection;
use AndyDefer\Nemesis\Models\NemesisToken;
use AndyDefer\Nemesis\Records\NemesisTokenFilterRecord;
use AndyDefer\Nemesis\Services\NemesisService;

final class ListTokensDirective extends AbstractDirective
{
    public function __construct(
        DirectiveContext $context,
        DirectiveInteractionService $interaction,
        private readonly NemesisService $service,
    ) {
        parent::__construct($context, $interaction);
    }

    public function getSignature(): string
    {
        return 'list-tokens {--model=}';
    }

    public function getDescription(): string
    {
        return 'List all tokens in the system';
    }

    public function getAliases(): StringTypedCollection
    {
        $aliases = new StringTypedCollection;
        $aliases->add('tokens-list');
        $aliases->add('nemesis-tokens');

        return $aliases;
    }

    public function shouldBootLaravel(): bool
    {
        return true;
    }

    public function execute(): ExitCode
    {
        $modelFilter = $this->option('model');


        if (is_string($modelFilter)) {
            $this->info(sprintf('Filtering by model: %s', $modelFilter));
            $filter = new NemesisTokenFilterRecord(tokenable_type: $modelFilter);
            $tokens = $this->service->findByFilters($filter);
        } else {
            $tokens = $this->service->findByFilters(new NemesisTokenFilterRecord);
        }

        if ($tokens->isEmpty()) {
            $this->warn('No tokens found.');

            return ExitCode::SUCCESS;
        }

        $this->displayTokensTable($tokens);

        return ExitCode::SUCCESS;
    }

    private function displayTokensTable(Collection $tokens): void
    {
        $headers = new StringTypedCollection;
        $headers->add('ID', 'Tokenable Type', 'Tokenable ID', 'Name', 'Source', 'Last Used', 'Expires At');

        $rows = new RowCollection;

        foreach ($tokens as $token) {
            $row = new RowCollection;
            $row->add(
                (string) $token->id,
                $this->formatTokenableType($token),
                (string) ($token->tokenable_id ?? 'N/A'),
                $token->name ?? 'N/A',
                $token->source ?? 'N/A',
                $this->formatLastUsed($token),
                $this->formatExpiration($token),
            );
            $rows->add($row);
        }

        $this->table($headers, $rows);
        $this->info(sprintf('Total tokens: %d', $tokens->count()));
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
            ? 'Expired ' . $token->expires_at->diffForHumans()
            : $token->expires_at->diffForHumans();
    }
}
