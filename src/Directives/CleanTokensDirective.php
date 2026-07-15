<?php

// src/Directives/CleanTokensDirective.php

declare(strict_types=1);

namespace AndyDefer\Nemesis\Directives;

use AndyDefer\Directive\AbstractDirective;
use AndyDefer\Directive\Enums\ExitCode;
use AndyDefer\DomainStructures\Collections\Utility\StringTypedCollection;
use AndyDefer\DomainStructures\Utils\ListCollection;
use AndyDefer\Nemesis\Contracts\Configs\NemesisConfigInterface;
use AndyDefer\Nemesis\Records\CleanupStatisticsRecord;
use AndyDefer\Nemesis\Records\NemesisTokenFilterRecord;
use AndyDefer\Nemesis\Services\NemesisService;
use AndyDefer\PhpVo\ValueObjects\DateTimeVO;

final class CleanTokensDirective extends AbstractDirective
{
    public function getSignature(): string
    {
        return 'nemesis:clean-tokens 
                    {days=?}#"Number of days to keep tokens" 
                    {--force}#"Force without confirmation" 
                    {--keep-expired}#"Keep expired tokens"';
    }

    public function getDescription(): string
    {
        return 'Clean expired and old tokens based on configuration';
    }

    public function getAliases(): StringTypedCollection
    {
        return StringTypedCollection::from(['nemesis-tc', 'nemesis-ce']);
    }

    public function execute(): ExitCode
    {
        $config = $this->getApplication()->make(NemesisConfigInterface::class);
        $service = $this->getApplication()->make(NemesisService::class);

        if (! $this->shouldProceed()) {
            return ExitCode::SUCCESS;
        }

        $statistics = $this->performCleanup($config, $service);

        $this->displayResults($statistics, $config);

        return ExitCode::SUCCESS;
    }

    private function shouldProceed(): bool
    {
        if ($this->isFlagActive('force')) {
            return true;
        }

        return $this->confirm(
            'This will permanently delete expired and old tokens. Do you wish to continue?'
        );
    }

    private function performCleanup(
        NemesisConfigInterface $config,
        NemesisService $service,
    ): CleanupStatisticsRecord {
        $expiredCount = $this->cleanExpiredTokens($service);
        $oldCount = $this->cleanOldTokens($config, $service);

        return CleanupStatisticsRecord::from([
            'expired' => $expiredCount,
            'old' => $oldCount,
            'total' => $expiredCount + $oldCount,
        ]);
    }

    private function cleanExpiredTokens(
        NemesisService $service,
    ): int {
        if ($this->isFlagActive('keep-expired')) {
            $this->getConsole()->alertWarning('Keeping expired tokens as requested (--keep-expired)');

            return 0;
        }

        $filter = NemesisTokenFilterRecord::from([
            'is_expired' => true,
        ]);
        $count = $service->count($filter);

        if ($count > 0) {
            $service->forceDeleteBulk($filter);
            $this->getConsole()->info(sprintf('Deleted %d expired tokens', $count));
        }

        return $count;
    }

    private function cleanOldTokens(
        NemesisConfigInterface $config,
        NemesisService $service,
    ): int {
        $retentionDays = $this->getRetentionDays($config);

        if ($retentionDays <= 0) {
            $this->getConsole()->info('Retention period is set to 0 or negative, skipping old token cleanup');

            return 0;
        }

        $cutoffDate = $this->getCutoffDate($retentionDays);
        $filter = NemesisTokenFilterRecord::from([
            'created_before' => $cutoffDate,
        ]);

        $count = $service->count($filter);

        if ($count > 0) {
            $service->forceDeleteBulk($filter);
            $this->getConsole()->info(sprintf('Deleted %d old tokens (older than %d days)', $count, $retentionDays));
        }

        return $count;
    }

    private function getCutoffDate(int $retentionDays): DateTimeVO
    {
        return new DateTimeVO(now()->subDays($retentionDays)->toIso8601String());
    }

    private function getRetentionDays(NemesisConfigInterface $config): int
    {
        $daysOption = $this->getArgument('days');

        if ($daysOption !== null && $daysOption !== '') {
            $days = (int) $daysOption;
            $this->getConsole()->info(sprintf('Using retention period from command line: %d days', $days));

            return $days;
        }

        $cleanupConfig = $config->cleanupConfig();
        $configDays = $cleanupConfig->keep_expired_for_days;
        $this->getConsole()->info(sprintf('Using retention period from config: %d days', $configDays));

        return $configDays;
    }

    private function displayResults(
        CleanupStatisticsRecord $statistics,
        NemesisConfigInterface $config,
    ): void {
        $this->getConsole()->newLine();
        $this->displayHeader();
        $this->displayStatisticsTable($statistics);
        $this->displayStatusMessage($statistics);
        $this->displayConfigurationSummary($config);
    }

    private function displayHeader(): void
    {
        $this->getConsole()->line('═══════════════════════════════════════════════════════');
        $this->getConsole()->info('🧹 TOKEN CLEANUP COMPLETED');
        $this->getConsole()->line('═══════════════════════════════════════════════════════');
    }

    private function displayStatisticsTable(CleanupStatisticsRecord $statistics): void
    {
        $headers = ListCollection::from(['Metric', 'Count']);

        $rows = ListCollection::from([
            ListCollection::from(['Expired tokens deleted', (string) $statistics->expired]),
            ListCollection::from(['Old tokens deleted', (string) $statistics->old]),
            ListCollection::from(['━━━━━━━━━━━━━━━━━━━━━', '━━━━━━━━━']),
            ListCollection::from(['Total tokens deleted', (string) $statistics->total]),
        ]);

        $this->getConsole()->table($headers, $rows);
        $this->getConsole()->newLine();
    }

    private function displayStatusMessage(CleanupStatisticsRecord $statistics): void
    {
        if ($statistics->total === 0) {
            $this->getConsole()->info('✨ No tokens needed cleaning. Database is clean!');
        } else {
            $this->getConsole()->success('✅ Cleanup completed successfully!');
        }
    }

    private function displayConfigurationSummary(NemesisConfigInterface $config): void
    {
        $cleanupConfig = $config->cleanupConfig();
        $middlewareConfig = $config->middlewareConfig();

        $this->getConsole()->newLine();
        $this->getConsole()->line('📋 Current Configuration:');
        $this->getConsole()->line(sprintf(
            '   • Auto cleanup: %s',
            $cleanupConfig->auto_cleanup ? '✅ Enabled' : '❌ Disabled'
        ));
        $this->getConsole()->line(sprintf(
            '   • Cleanup frequency: %d minutes',
            $cleanupConfig->frequency
        ));
        $this->getConsole()->line(sprintf(
            '   • Retention period: %d days',
            $this->getRetentionDays($config)
        ));
        $this->getConsole()->line(sprintf(
            '   • Validate origin: %s',
            $middlewareConfig->validate_origin ? '✅ Enabled' : '❌ Disabled'
        ));

        $this->displayExpiredTokensStatus();
    }

    private function displayExpiredTokensStatus(): void
    {
        if (! $this->isFlagActive('keep-expired')) {
            $this->getConsole()->line('   • Expired tokens: ✅ Removed');
        } else {
            $this->getConsole()->line('   • Expired tokens: ⏸️  Kept (--keep-expired flag used)');
        }
    }
}
