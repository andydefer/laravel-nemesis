<?php

// src/Directives/CleanTokensDirective.php

declare(strict_types=1);

namespace AndyDefer\Nemesis\Directives;

use AndyDefer\Directive\AbstractDirective;
use AndyDefer\Directive\Collections\RowCollection;
use AndyDefer\Directive\Enums\ExitCode;
use AndyDefer\DomainStructures\Collections\Utility\StringTypedCollection;
use AndyDefer\DomainStructures\Services\HydrationService;
use AndyDefer\PhpVo\ValueObjects\DateTimeVO;
use AndyDefer\Nemesis\Contracts\Configs\NemesisConfigInterface;
use AndyDefer\Nemesis\Records\CleanupStatisticsRecord;
use AndyDefer\Nemesis\Records\NemesisTokenFilterRecord;
use AndyDefer\Nemesis\Services\NemesisService;

final class CleanTokensDirective extends AbstractDirective
{

    public function getSignature(): string
    {
        return 'clean-tokens {--days=} {--force} {--keep-expired}';
    }

    public function getDescription(): string
    {
        return 'Clean expired and old tokens based on configuration';
    }

    public function getAliases(): StringTypedCollection
    {
        $aliases = new StringTypedCollection;
        $aliases->add('tokens-clean');
        $aliases->add('token-clean');
        $aliases->add('clean-expired');

        return $aliases;
    }

    public function shouldBootLaravel(): bool
    {
        return true;
    }

    public function execute(): ExitCode
    {
        // Récupération des dépendances via le container Laravel
        $config = $this->getLaravel()->make(NemesisConfigInterface::class);
        $service = $this->getLaravel()->make(NemesisService::class);
        $hydration = new HydrationService();

        if (! $this->shouldProceed()) {
            return ExitCode::SUCCESS;
        }

        $statistics = $this->performCleanup($config, $service, $hydration);

        $this->displayResults($statistics, $config);

        return ExitCode::SUCCESS;
    }

    private function shouldProceed(): bool
    {
        if ($this->hasOption('force')) {
            return true;
        }

        return $this->confirm(
            'This will permanently delete expired and old tokens. Do you wish to continue?'
        );
    }

    private function performCleanup(
        NemesisConfigInterface $config,
        NemesisService $service,
        HydrationService $hydration,
    ): CleanupStatisticsRecord {
        $expiredCount = $this->cleanExpiredTokens($service, $hydration);
        $oldCount = $this->cleanOldTokens($config, $service, $hydration);

        return $hydration->hydrate(CleanupStatisticsRecord::class, [
            'expired' => $expiredCount,
            'old' => $oldCount,
            'total' => $expiredCount + $oldCount,
        ]);
    }

    private function cleanExpiredTokens(
        NemesisService $service,
        HydrationService $hydration,
    ): int {
        if ($this->hasOption('keep-expired')) {
            $this->warn('Keeping expired tokens as requested (--keep-expired)');

            return 0;
        }

        $filter = $hydration->hydrate(NemesisTokenFilterRecord::class, [
            'is_expired' => true,
        ]);
        $count = $service->count($filter);

        if ($count > 0) {
            $service->forceDeleteBulk($filter);
            $this->info(sprintf('Deleted %d expired tokens', $count));
        }

        return $count;
    }

    private function cleanOldTokens(
        NemesisConfigInterface $config,
        NemesisService $service,
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

        $count = $service->count($filter);

        if ($count > 0) {
            $service->forceDeleteBulk($filter);
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

        if ($daysOption !== null && $daysOption !== '') {
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
        CleanupStatisticsRecord $statistics,
        NemesisConfigInterface $config,
    ): void {
        $this->newLine();
        $this->displayHeader();
        $this->displayStatisticsTable($statistics);
        $this->displayStatusMessage($statistics);
        $this->displayConfigurationSummary($config);
    }

    private function displayHeader(): void
    {
        $this->line('═══════════════════════════════════════════════════════');
        $this->info('🧹 TOKEN CLEANUP COMPLETED');
        $this->line('═══════════════════════════════════════════════════════');
    }

    private function displayStatisticsTable(CleanupStatisticsRecord $statistics): void
    {
        $headers = new StringTypedCollection;
        $headers->add('Metric', 'Count');

        $rows = new RowCollection;

        $expiredRow = new RowCollection;
        $expiredRow->add('Expired tokens deleted', (string) $statistics->expired);
        $rows->add($expiredRow);

        $oldRow = new RowCollection;
        $oldRow->add('Old tokens deleted', (string) $statistics->old);
        $rows->add($oldRow);

        $separatorRow = new RowCollection;
        $separatorRow->add('━━━━━━━━━━━━━━━━━━━━━', '━━━━━━━━━');
        $rows->add($separatorRow);

        $totalRow = new RowCollection;
        $totalRow->add('Total tokens deleted', (string) $statistics->total);
        $rows->add($totalRow);

        $this->table($headers, $rows);
        $this->newLine();
    }

    private function displayStatusMessage(CleanupStatisticsRecord $statistics): void
    {
        if ($statistics->total === 0) {
            $this->info('✨ No tokens needed cleaning. Database is clean!');
        } else {
            $this->info('✅ Cleanup completed successfully!');
        }
    }

    private function displayConfigurationSummary(NemesisConfigInterface $config): void
    {
        $cleanupConfig = $config->cleanupConfig();
        $middlewareConfig = $config->middlewareConfig();

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
        $this->line(sprintf(
            '   • Validate origin: %s',
            $middlewareConfig->validate_origin ? '✅ Enabled' : '❌ Disabled'
        ));

        $this->displayExpiredTokensStatus();
    }

    private function displayExpiredTokensStatus(): void
    {
        if (! $this->hasOption('keep-expired')) {
            $this->line('   • Expired tokens: ✅ Removed');
        } else {
            $this->line('   • Expired tokens: ⏸️  Kept (--keep-expired flag used)');
        }
    }
}
