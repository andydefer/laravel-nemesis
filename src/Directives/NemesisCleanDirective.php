<?php

// src/Directives/NemesisCleanDirective.php

declare(strict_types=1);

namespace AndyDefer\Nemesis\Directives;

use AndyDefer\Directive\AbstractDirective;
use AndyDefer\Directive\Collections\RowCollection;
use AndyDefer\Directive\Enums\ExitCode;
use AndyDefer\DomainStructures\Collections\Utility\StringTypedCollection;
use AndyDefer\DomainStructures\Services\HydrationService;
use AndyDefer\Nemesis\Contracts\Configs\NemesisConfigInterface;
use AndyDefer\Nemesis\Records\NemesisTokenFilterRecord;
use AndyDefer\Nemesis\Repositories\NemesisTokenRepository;
use AndyDefer\PhpVo\ValueObjects\DateTimeVO;

final class NemesisCleanDirective extends AbstractDirective
{
    public function getSignature(): string
    {
        return 'nemesis-clean {--days=} {--force} {--keep-expired}';
    }

    public function getDescription(): string
    {
        return 'Clean expired and old tokens based on configuration';
    }

    public function getAliases(): StringTypedCollection
    {
        $aliases = new StringTypedCollection;
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
        $laravel = $this->getLaravel();

        $config = $laravel->make(NemesisConfigInterface::class);
        $repository = $laravel->make(NemesisTokenRepository::class);
        $hydration = new HydrationService;

        if (! $this->shouldProceed()) {
            return ExitCode::SUCCESS;
        }

        $statistics = $this->performCleanup($config, $repository, $hydration);

        $this->displayResults($statistics, $config, $repository, $hydration);

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

    private function performCleanup(
        NemesisConfigInterface $config,
        NemesisTokenRepository $repository,
        HydrationService $hydration,
    ): array {
        $statistics = [
            'expired' => 0,
            'old' => 0,
            'total' => 0,
        ];

        $statistics['expired'] = $this->cleanExpiredTokens($repository, $hydration);
        $statistics['old'] = $this->cleanOldTokens($config, $repository, $hydration);
        $statistics['total'] = $statistics['expired'] + $statistics['old'];

        return $statistics;
    }

    private function cleanExpiredTokens(
        NemesisTokenRepository $repository,
        HydrationService $hydration,
    ): int {
        if ($this->option('keep-expired')) {
            $this->warn('Keeping expired tokens as requested (--keep-expired)');

            return 0;
        }

        $filter = $hydration->hydrate(NemesisTokenFilterRecord::class, [
            'is_expired' => true,
        ]);

        $count = $repository->count($filter);

        if ($count > 0) {
            $repository->forceDeleteBulk($filter);
            $this->info(sprintf('Deleted %d expired tokens', $count));
        }

        return $count;
    }

    private function cleanOldTokens(
        NemesisConfigInterface $config,
        NemesisTokenRepository $repository,
        HydrationService $hydration,
    ): int {
        $retentionDays = $this->getRetentionDays($config);

        if ($retentionDays <= 0) {
            $this->info('Retention period is set to 0 or negative, skipping old token cleanup');

            return 0;
        }

        $cutoffDate = $this->getCutoffDate($retentionDays);

        $filter = $hydration->hydrate(NemesisTokenFilterRecord::class, [
            'created_before' => $cutoffDate,
        ]);

        $count = $repository->count($filter);

        if ($count > 0) {
            $repository->deleteBulk($filter);
            $this->info(sprintf('Deleted %d old tokens (older than %d days)', $count, $retentionDays));
        }

        return $count;
    }

    private function getCutoffDate(int $retentionDays): DateTimeVO
    {
        return new DateTimeVO(now()->subDays($retentionDays)->toIso8601String());
    }

    private function getRetentionDays(NemesisConfigInterface $config): int
    {
        $daysOption = $this->option('days');

        if ($daysOption !== null) {
            $days = (int) $daysOption;
            $this->info(sprintf('Using retention period from command line: %d days', $days));

            return $days;
        }

        $cleanupConfig = $config->cleanupConfig();
        $configDays = $cleanupConfig->keep_expired_for_days;
        $this->info(sprintf('Using retention period from config: %d days', $configDays));

        return $configDays;
    }

    private function displayResults(
        array $statistics,
        NemesisConfigInterface $config,
        NemesisTokenRepository $repository,
        HydrationService $hydration,
    ): void {
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

        $this->displayConfigurationSummary($config);
    }

    private function displayStatisticsTable(array $statistics): void
    {
        $headers = new StringTypedCollection;
        $headers->add('Metric', 'Count');

        $rows = new RowCollection;

        $row1 = new RowCollection;
        $row1->add('Expired tokens deleted', (string) $statistics['expired']);
        $rows->add($row1);

        $row2 = new RowCollection;
        $row2->add('Old tokens deleted', (string) $statistics['old']);
        $rows->add($row2);

        $row3 = new RowCollection;
        $row3->add('━━━━━━━━━━━━━━━━━━━━━', '━━━━━━━━━');
        $rows->add($row3);

        $row4 = new RowCollection;
        $row4->add('Total tokens deleted', (string) $statistics['total']);
        $rows->add($row4);

        $this->table($headers, $rows);
        $this->newLine();
    }

    private function displayConfigurationSummary(NemesisConfigInterface $config): void
    {
        $cleanupConfig = $config->cleanupConfig();

        $this->newLine();
        $this->line('📋 Current Configuration:');
        $this->line(sprintf(
            '   • Auto cleanup: %s',
            $cleanupConfig->auto_cleanup ? '✅ Enabled' : '❌ Disabled'
        ));
        $this->line(sprintf(
            '   • Cleanup frequency: %d minutes',
            $cleanupConfig->frequency
        ));
        $this->line(sprintf(
            '   • Retention period: %d days',
            $this->getRetentionDays($config)
        ));

        if (! $this->option('keep-expired')) {
            $this->line('   • Expired tokens: ✅ Removed');
        } else {
            $this->line('   • Expired tokens: ⏸️  Kept (--keep-expired flag used)');
        }
    }
}
