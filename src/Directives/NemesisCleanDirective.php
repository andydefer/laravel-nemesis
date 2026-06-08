<?php

declare(strict_types=1);

namespace Kani\Nemesis\Directives;

use AndyDefer\Directive\AbstractDirective;
use AndyDefer\Directive\Collections\RowCollection;
use AndyDefer\Directive\Enums\ExitCode;
use AndyDefer\Directive\Services\DirectiveInteractionService;
use AndyDefer\DomainStructures\Collections\Utility\StringTypedCollection;
use Carbon\CarbonInterface;
use Kani\Nemesis\Models\NemesisToken;

final class NemesisCleanDirective extends AbstractDirective
{
    public function __construct(DirectiveInteractionService $interaction)
    {
        parent::__construct($interaction);
    }

    public function getSignature(): string
    {
        return 'nemesis-clean {--days= : Delete tokens older than X days} {--force : Force execution} {--keep-expired : Keep expired tokens}';
    }

    public function getDescription(): string
    {
        return 'Clean expired and old tokens based on configuration';
    }

    public function getAliases(): StringTypedCollection
    {
        $aliases = new StringTypedCollection();
        $aliases->add('token-clean');
        $aliases->add('tokens-clean');

        return $aliases;
    }

    public function shouldBootLaravel(): bool
    {
        return true;
    }

    public function execute(): ExitCode
    {
        if (!$this->shouldProceed()) {
            return ExitCode::SUCCESS;
        }

        $statistics = $this->performCleanup();

        $this->displayResults($statistics);

        return ExitCode::SUCCESS;
    }

    private function shouldProceed(): bool
    {
        if ($this->option('force')) {
            return true;
        }

        return $this->confirm(
            'This will permanently delete expired and old tokens. Do you wish to continue?'
        );
    }

    private function performCleanup(): array
    {
        $statistics = [
            'expired' => 0,
            'old' => 0,
            'total' => 0,
        ];

        $statistics['expired'] = $this->cleanExpiredTokens();
        $statistics['old'] = $this->cleanOldTokens();
        $statistics['total'] = $statistics['expired'] + $statistics['old'];

        return $statistics;
    }

    private function cleanExpiredTokens(): int
    {
        if ($this->option('keep-expired')) {
            $this->warn('Keeping expired tokens as requested (--keep-expired)');
            return 0;
        }

        $query = NemesisToken::where('expires_at', '<', now());
        $count = $query->count();

        if ($count > 0) {
            $query->delete();
            $this->info(sprintf('Deleted %d expired tokens', $count));
        }

        return $count;
    }

    private function cleanOldTokens(): int
    {
        $retentionDays = $this->getRetentionDays();

        if ($retentionDays <= 0) {
            $this->info('Retention period is set to 0 or negative, skipping old token cleanup');
            return 0;
        }

        $cutoffDate = $this->getCutoffDate($retentionDays);
        $query = $this->getOldTokensQuery($cutoffDate);

        $count = $query->count();

        if ($count > 0) {
            $query->delete();
            $this->info(sprintf('Deleted %d old tokens (older than %d days)', $count, $retentionDays));
        }

        return $count;
    }

    private function getCutoffDate(int $retentionDays): CarbonInterface
    {
        return now()->subDays($retentionDays);
    }

    private function getOldTokensQuery(CarbonInterface $cutoffDate)
    {
        $query = NemesisToken::where('created_at', '<', $cutoffDate);

        if (!$this->option('keep-expired')) {
            $query->where(function ($subQuery): void {
                $subQuery->whereNull('expires_at')
                    ->orWhere('expires_at', '>=', now());
            });
        }

        return $query;
    }

    private function getRetentionDays(): int
    {
        $daysOption = $this->option('days');

        if ($daysOption !== null) {
            $days = (int) $daysOption;
            $this->info(sprintf('Using retention period from command line: %d days', $days));
            return $days;
        }

        $configDays = config('nemesis.cleanup.keep_expired_for_days', 30);
        $this->info(sprintf('Using retention period from config: %d days', (int) $configDays));

        return (int) $configDays;
    }

    private function displayResults(array $statistics): void
    {
        $this->newLine();
        $this->line('═══════════════════════════════════════════════════════');
        $this->info('🧹 TOKEN CLEANUP COMPLETED');
        $this->line('═══════════════════════════════════════════════════════');

        $this->displayStatisticsTable($statistics);

        if ($statistics['total'] === 0) {
            $this->info('✨ No tokens needed cleaning. Database is clean!');
        } else {
            $this->info('✅ Cleanup completed successfully!');
        }

        $this->displayConfigurationSummary();
    }

    private function displayStatisticsTable(array $statistics): void
    {
        $headers = new StringTypedCollection();
        $headers->add('Metric', 'Count');

        $rows = new RowCollection();

        $row1 = new RowCollection();
        $row1->add('Expired tokens deleted', (string) $statistics['expired']);
        $rows->add($row1);

        $row2 = new RowCollection();
        $row2->add('Old tokens deleted', (string) $statistics['old']);
        $rows->add($row2);

        $row3 = new RowCollection();
        $row3->add('━━━━━━━━━━━━━━━━━━━━━', '━━━━━━━━━');
        $rows->add($row3);

        $row4 = new RowCollection();
        $row4->add('Total tokens deleted', (string) $statistics['total']);
        $rows->add($row4);

        $this->table($headers, $rows);
        $this->newLine();
    }

    private function displayConfigurationSummary(): void
    {
        $this->newLine();
        $this->line('📋 Current Configuration:');
        $this->line(sprintf(
            '   • Auto cleanup: %s',
            config('nemesis.cleanup.auto_cleanup', true) ? '✅ Enabled' : '❌ Disabled'
        ));
        $this->line(sprintf(
            '   • Cleanup frequency: %d minutes',
            config('nemesis.cleanup.frequency', 60)
        ));
        $this->line(sprintf(
            '   • Retention period: %d days',
            $this->getRetentionDays()
        ));

        if (!$this->option('keep-expired')) {
            $this->line('   • Expired tokens: ✅ Removed');
        } else {
            $this->line('   • Expired tokens: ⏸️  Kept (--keep-expired flag used)');
        }
    }
}
