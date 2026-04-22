<?php

declare(strict_types=1);

namespace Kani\Nemesis\Commands;

use Illuminate\Console\Command;
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
     * @return int Exit code (0 for success, 1 for error)
     */
    public function handle(): int
    {
        // Display warning and ask for confirmation unless forced
        if (!$this->option('force') && !$this->confirm('This will permanently delete expired and old tokens. Do you wish to continue?')) {
            $this->info('Operation cancelled.');
            return self::SUCCESS;
        }

        $stats = $this->performCleanup();

        $this->displayResults($stats);

        return self::SUCCESS;
    }

    /**
     * Perform the token cleanup operations.
     *
     * @return array<string, int> Statistics about the cleanup
     */
    private function performCleanup(): array
    {
        $stats = [
            'expired' => 0,
            'old' => 0,
            'total' => 0,
        ];

        // Clean expired tokens unless --keep-expired flag is used
        if (!$this->option('keep-expired')) {
            $stats['expired'] = $this->deleteExpiredTokens();
            $this->info("Deleted {$stats['expired']} expired tokens");
        } else {
            $this->warn('Keeping expired tokens as requested (--keep-expired)');
        }

        // Clean old tokens based on retention period
        $stats['old'] = $this->deleteOldTokens();
        $stats['total'] = $stats['expired'] + $stats['old'];

        return $stats;
    }

    /**
     * Delete tokens that have expired.
     *
     * @return int Number of deleted tokens
     */
    private function deleteExpiredTokens(): int
    {
        $query = NemesisToken::where('expires_at', '<', now());

        $count = $query->count();

        if ($count > 0) {
            $query->delete();
        }

        return $count;
    }

    /**
     * Delete old tokens based on retention period.
     *
     * @return int Number of deleted tokens
     */
    private function deleteOldTokens(): int
    {
        $retentionDays = $this->getRetentionDays();

        // If retention is set to 0 or negative, don't delete old tokens
        if ($retentionDays <= 0) {
            $this->info('Retention period is set to 0 or negative, skipping old token cleanup');
            return 0;
        }

        $cutoffDate = now()->subDays($retentionDays);

        $query = NemesisToken::where('created_at', '<', $cutoffDate);

        // Don't delete tokens that are already expired if --keep-expired is not set
        // They will be handled by the expired tokens cleanup
        if (!$this->option('keep-expired')) {
            $query->where(function ($q) {
                $q->whereNull('expires_at')
                    ->orWhere('expires_at', '>=', now());
            });
        }

        $count = $query->count();

        if ($count > 0) {
            $query->delete();
        }

        return $count;
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
        // Check command line option first
        if ($this->option('days') !== null) {
            $days = (int) $this->option('days');
            $this->info("Using retention period from command line: {$days} days");
            return $days;
        }

        // Check configuration
        $configDays = config('nemesis.cleanup.keep_expired_for_days', 30);

        $this->info("Using retention period from config: {$configDays} days");

        return (int) $configDays;
    }

    /**
     * Display the cleanup results.
     *
     * @param array<string, int> $stats Cleanup statistics
     */
    private function displayResults(array $stats): void
    {
        $this->newLine();
        $this->line('═══════════════════════════════════════════════════════');
        $this->info('🧹 TOKEN CLEANUP COMPLETED');
        $this->line('═══════════════════════════════════════════════════════');

        $this->table(
            ['Metric', 'Count'],
            [
                ['Expired tokens deleted', $stats['expired']],
                ['Old tokens deleted', $stats['old']],
                ['━━━━━━━━━━━━━━━━━━━━━', '━━━━━━━━━'],
                ['Total tokens deleted', $stats['total']],
            ]
        );

        $this->newLine();

        if ($stats['total'] === 0) {
            $this->info('✨ No tokens needed cleaning. Database is clean!');
        } else {
            $this->info('✅ Cleanup completed successfully!');
        }

        // Display configuration summary
        $this->newLine();
        $this->line('📋 Current Configuration:');
        $this->line("   • Auto cleanup: " . (config('nemesis.cleanup.auto_cleanup', true) ? '✅ Enabled' : '❌ Disabled'));
        $this->line("   • Cleanup frequency: " . config('nemesis.cleanup.frequency', 60) . " minutes");
        $this->line("   • Retention period: " . $this->getRetentionDays() . " days");

        if (!$this->option('keep-expired')) {
            $this->line("   • Expired tokens: ✅ Removed");
        } else {
            $this->line("   • Expired tokens: ⏸️  Kept (--keep-expired flag used)");
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
