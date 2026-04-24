<?php

declare(strict_types=1);

namespace Kani\Nemesis\Commands;

use Carbon\CarbonInterface;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Builder;
use Kani\Nemesis\Models\NemesisToken;

/**
 * Command to clean expired and old tokens from the database.
 *
 * This command removes:
 * - Tokens that have passed their expiration date
 * - Tokens that are older than the configured retention period
 *
 * @package Kani\Nemesis\Commands
 */
final class CleanTokensCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'nemesis:clean 
                            {--days= : Delete tokens older than X days (overrides config)} 
                            {--force : Force execution without confirmation}
                            {--keep-expired : Keep expired tokens, only clean old tokens}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Clean expired and old tokens based on configuration';

    /**
     * Execute the console command.
     *
     * @return int Exit code (0 for success)
     */
    public function handle(): int
    {
        if (!$this->shouldProceed()) {
            return self::SUCCESS;
        }

        $statistics = $this->performCleanup();

        $this->displayResults($statistics);

        return self::SUCCESS;
    }

    /**
     * Determine if the cleanup operation should proceed.
     *
     * @return bool True if should proceed, false otherwise
     */
    private function shouldProceed(): bool
    {
        if ($this->option('force')) {
            return true;
        }

        return $this->confirm(
            'This will permanently delete expired and old tokens. Do you wish to continue?'
        );
    }

    /**
     * Perform the token cleanup operations.
     *
     * @return array<string, int> Statistics about the cleanup
     */
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

    /**
     * Clean expired tokens from the database.
     *
     * @return int Number of expired tokens deleted
     */
    private function cleanExpiredTokens(): int
    {
        if ($this->option('keep-expired')) {
            $this->warn('Keeping expired tokens as requested (--keep-expired)');
            return 0;
        }

        $query = $this->getExpiredTokensQuery();
        $count = $query->count();

        if ($count > 0) {
            $query->delete();
            $this->info(sprintf('Deleted %d expired tokens', $count));
        }

        return $count;
    }

    /**
     * Get the query builder for expired tokens.
     *
     * @return Builder<NemesisToken>
     */
    private function getExpiredTokensQuery(): Builder
    {
        return NemesisToken::where('expires_at', '<', now());
    }

    /**
     * Clean old tokens based on retention period.
     *
     * @return int Number of old tokens deleted
     */
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

    /**
     * Get the cutoff date based on retention days.
     *
     * @param int $retentionDays Number of days to keep tokens
     * @return CarbonInterface The cutoff date
     */
    private function getCutoffDate(int $retentionDays): CarbonInterface
    {
        return now()->subDays($retentionDays);
    }

    /**
     * Get the query builder for old tokens.
     *
     * @param CarbonInterface $cutoffDate Tokens created before this date are considered old
     * @return Builder<NemesisToken>
     */
    private function getOldTokensQuery(CarbonInterface $cutoffDate): Builder
    {
        $query = NemesisToken::where('created_at', '<', $cutoffDate);

        if (!$this->option('keep-expired')) {
            $query->where(function (Builder $subQuery): void {
                $subQuery->whereNull('expires_at')
                    ->orWhere('expires_at', '>=', now());
            });
        }

        return $query;
    }

    /**
     * Get the retention period in days.
     *
     * Priority:
     * 1. Command line option --days
     * 2. Configuration 'cleanup.keep_expired_for_days'
     * 3. Default value (30 days)
     *
     * @return int Number of days to keep tokens
     */
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

    /**
     * Display the cleanup results in a formatted table.
     *
     * @param array<string, int> $statistics Cleanup statistics
     */
    private function displayResults(array $statistics): void
    {
        $this->newLine();
        $this->displayHeader();
        $this->displayStatisticsTable($statistics);
        $this->displayStatusMessage($statistics);
        $this->displayConfigurationSummary();
    }

    /**
     * Display the cleanup header.
     */
    private function displayHeader(): void
    {
        $this->line('═══════════════════════════════════════════════════════');
        $this->info('🧹 TOKEN CLEANUP COMPLETED');
        $this->line('═══════════════════════════════════════════════════════');
    }

    /**
     * Display the statistics table.
     *
     * @param array<string, int> $statistics Cleanup statistics
     */
    private function displayStatisticsTable(array $statistics): void
    {
        $this->table(
            ['Metric', 'Count'],
            [
                ['Expired tokens deleted', $statistics['expired']],
                ['Old tokens deleted', $statistics['old']],
                ['━━━━━━━━━━━━━━━━━━━━━', '━━━━━━━━━'],
                ['Total tokens deleted', $statistics['total']],
            ]
        );

        $this->newLine();
    }

    /**
     * Display the status message based on cleanup results.
     *
     * @param array<string, int> $statistics Cleanup statistics
     */
    private function displayStatusMessage(array $statistics): void
    {
        if ($statistics['total'] === 0) {
            $this->info('✨ No tokens needed cleaning. Database is clean!');
        } else {
            $this->info('✅ Cleanup completed successfully!');
        }
    }

    /**
     * Display the current configuration summary.
     */
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

        $this->displayExpiredTokensStatus();
    }

    /**
     * Display the status of expired token handling.
     */
    private function displayExpiredTokensStatus(): void
    {
        if (!$this->option('keep-expired')) {
            $this->line('   • Expired tokens: ✅ Removed');
        } else {
            $this->line('   • Expired tokens: ⏸️  Kept (--keep-expired flag used)');
        }
    }

    /**
     * Get the console command options.
     *
     * @return array<int, array<int, mixed>>
     */
    protected function getOptions(): array
    {
        return [
            ['days', 'd', 'input', 'Number of days to keep tokens (overrides config)'],
            ['force', 'f', 'none', 'Force execution without confirmation'],
            ['keep-expired', 'k', 'none', 'Keep expired tokens, only clean old tokens'],
        ];
    }
}
