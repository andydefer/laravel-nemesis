<?php
// src/Commands/CleanTokensCommand.php

declare(strict_types=1);

namespace Kani\Nemesis\Commands;

use Illuminate\Console\Command;
use Kani\Nemesis\Models\NemesisToken;

final class CleanTokensCommand extends Command
{
    protected $signature = 'nemesis:clean {--days=30 : Delete tokens older than X days}';
    protected $description = 'Clean expired and old tokens';

    public function handle(): int
    {
        $expiredCount = NemesisToken::where('expires_at', '<', now())->delete();

        $days = (int) $this->option('days');
        $oldCount = NemesisToken::where('created_at', '<', now()->subDays($days))->delete();

        $this->info("✅ Cleaned {$expiredCount} expired tokens");
        $this->info("✅ Cleaned {$oldCount} tokens older than {$days} days");

        return self::SUCCESS;
    }
}
