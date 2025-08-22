<?php

namespace Kani\Nemesis\Console;

use Illuminate\Console\Command;
use Kani\Nemesis\Models\NemesisToken;

class UnblockNemesisToken extends Command
{
    protected $signature = 'nemesis:unblock {token} {--max=1000}';
    protected $description = 'Unblock a Nemesis token and optionally reset max_requests';

    public function handle(): int
    {
        $tokenValue = $this->argument('token');
        $max = $this->option('max');

        $token = NemesisToken::where('token', $tokenValue)->first();

        if (! $token) {
            $this->error("❌ Token not found: $tokenValue");
            return self::FAILURE;
        }

        $token->max_requests = $max;
        $token->save();

        $this->info("✅ Token $tokenValue has been unblocked with max_requests=$max.");
        return self::SUCCESS;
    }
}
