<?php

namespace Kani\Nemesis\Console;

use Illuminate\Console\Command;
use Kani\Nemesis\Models\NemesisToken;

class BlockNemesisToken extends Command
{
    protected $signature = 'nemesis:block
                            {token : The token to block}
                            {--reason= : Reason for blocking}';

    protected $description = 'Block a Nemesis token by its value';

    public function handle(): int
    {
        $tokenValue = $this->argument('token');
        $reason = $this->option('reason');

        $token = NemesisToken::where('token', $tokenValue)->first();

        if (!$token) {
            $this->error("❌ Token not found: $tokenValue");
            return self::FAILURE;
        }

        // Bloquer le token en mettant max_requests à 0
        $token->update([
            'max_requests' => 0,
            'block_reason' => $reason
        ]);

        $this->info("✅ Token $tokenValue has been blocked successfully.");
        if ($reason) {
            $this->line("Reason: $reason");
        }

        return self::SUCCESS;
    }
}
