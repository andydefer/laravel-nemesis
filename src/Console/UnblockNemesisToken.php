<?php

namespace Kani\Nemesis\Console;

use Illuminate\Console\Command;
use Kani\Nemesis\Models\NemesisToken;

class UnblockNemesisToken extends Command
{
    protected $signature = 'nemesis:unblock
                            {token : The token to unblock}
                            {--max= : New maximum requests limit}
                            {--reason= : Reason for unblocking}';

    protected $description = 'Unblock a Nemesis token and optionally reset max_requests';

    public function handle(): int
    {
        $tokenValue = $this->argument('token');
        $max = $this->option('max') ?? config('nemesis.default_max_requests', 1000);
        $reason = $this->option('reason');

        $token = NemesisToken::where('token', $tokenValue)->first();

        if (!$token) {
            $this->error("❌ Token not found: $tokenValue");
            return self::FAILURE;
        }

        $token->update([
            'max_requests' => (int) $max,
            'block_reason' => null,
            'unblock_reason' => $reason
        ]);

        $this->info("✅ Token $tokenValue has been unblocked successfully.");
        $this->line("New max requests: {$token->max_requests}");
        if ($reason) {
            $this->line("Reason: $reason");
        }

        return self::SUCCESS;
    }
}
