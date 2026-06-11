<?php

// src/Directives/NemesisCleanDirective.php

declare(strict_types=1);

namespace AndyDefer\Nemesis\Directives;

use AndyDefer\Directive\AbstractDirective;
use AndyDefer\Directive\Collections\RowCollection;
use AndyDefer\Directive\Contexts\DirectiveContext;
use AndyDefer\Directive\Enums\ExitCode;
use AndyDefer\Directive\Services\DirectiveInteractionService;
use AndyDefer\DomainStructures\Collections\Utility\StringTypedCollection;
use AndyDefer\DomainStructures\Services\HydrationService;
use AndyDefer\PhpVo\ValueObjects\DateTimeVO;
use AndyDefer\Nemesis\Contracts\Configs\NemesisConfigInterface;
use AndyDefer\Nemesis\Models\NemesisToken;
use AndyDefer\Nemesis\Records\NemesisTokenFilterRecord;
use AndyDefer\Nemesis\Repositories\NemesisTokenRepository;

final class NemesisCleanDirective extends AbstractDirective
{
    private HydrationService $hydration;

    public function __construct(
        DirectiveContext $context,
        DirectiveInteractionService $interaction,
        private readonly NemesisConfigInterface $config,
        private readonly NemesisTokenRepository $repository,
    ) {
        parent::__construct($context, $interaction);
        $this->hydration = new HydrationService();
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

        // ✅ Utilisation du repository avec filter
        $filter = $this->hydration->hydrate(NemesisTokenFilterRecord::class, [
            'is_expired' => true,
        ]);

        $count = $this->repository->count($filter);

        if ($count > 0) {
            $this->repository->forceDeleteBulk($filter);
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

        // ✅ Utilisation du repository avec filter created_before
        $filter = $this->hydration->hydrate(NemesisTokenFilterRecord::class, [
            'created_before' => $cutoffDate,
        ]);

        $count = $this->repository->count($filter);

        if ($count > 0) {
            $this->repository->deleteBulk($filter);
            $this->info(sprintf('Deleted %d old tokens (older than %d days)', $count, $retentionDays));
        }

        return $count;
    }

    private function getCutoffDate(int $retentionDays): DateTimeVO
    {
        return new DateTimeVO(now()->subDays($retentionDays)->toIso8601String());
    }

    private function getRetentionDays(): int
    {
        $daysOption = $this->option('days');

        if ($daysOption !== null) {
            $days = (int) $daysOption;
            $this->info(sprintf('Using retention period from command line: %d days', $days));
            return $days;
        }

        // ✅ Utilisation de la config injectée
        $cleanupConfig = $this->config->cleanupConfig();
        $configDays = $cleanupConfig->keep_expired_for_days;
        $this->info(sprintf('Using retention period from config: %d days', $configDays));

        return $configDays;
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

    private function displayConfigurationSummary(): void
    {
        // ✅ Utilisation de la config injectée
        $cleanupConfig = $this->config->cleanupConfig();

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
            $this->getRetentionDays()
        ));

        if (!$this->option('keep-expired')) {
            $this->line('   • Expired tokens: ✅ Removed');
        } else {
            $this->line('   • Expired tokens: ⏸️  Kept (--keep-expired flag used)');
        }
    }
}
