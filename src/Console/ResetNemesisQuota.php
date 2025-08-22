<?php

namespace Kani\Nemesis\Console;

use Illuminate\Console\Command;
use Kani\Nemesis\Models\NemesisToken;

class ResetNemesisQuota extends Command
{
    protected $signature = 'nemesis:reset
                            {--token= : Reset specific token only}
                            {--force : Force reset without confirmation}';

    protected $description = 'Reset requests_count for Nemesis tokens';

    public function handle(): int
    {
        $tokenValue = $this->option('token');

        if ($tokenValue) {
            // Reset specific token
            $token = NemesisToken::where('token', $tokenValue)->first();

            if (!$token) {
                $this->error("❌ Token not found: $tokenValue");
                return self::FAILURE;
            }

            $token->update([
                'requests_count' => 0,
                'last_request_at' => null,
            ]);

            $this->info("✅ Token quota reset successfully for: $tokenValue");
            return self::SUCCESS;
        }

        // Reset all tokens
        if (!$this->option('force') && !$this->confirm('Are you sure you want to reset all token quotas?')) {
            $this->info('Reset cancelled.');
            return self::SUCCESS;
        }

        $affected = NemesisToken::query()->update([
            'requests_count' => 0,
            'last_request_at' => null,
        ]);

        $this->info("✅ Successfully reset quotas for $affected tokens.");
        return self::SUCCESS;
    }
}
