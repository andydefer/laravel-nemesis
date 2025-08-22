<?php

namespace Kani\Nemesis\Console;

use Illuminate\Console\Command;
use Kani\Nemesis\Models\NemesisToken;

class ResetNemesisQuota extends Command
{
    protected $signature = 'nemesis:reset';
    protected $description = 'Reset requests_count for all Nemesis tokens';

    public function handle(): int
    {
        NemesisToken::query()->update([
            'requests_count' => 0,
            'last_request_at' => null,
        ]);

        $this->info('✅ All Nemesis token quotas have been reset.');
        return self::SUCCESS;
    }
}
