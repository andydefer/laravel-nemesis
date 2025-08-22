<?php

namespace Kani\Nemesis\Console;

use Illuminate\Console\Command;
use Kani\Nemesis\Models\NemesisToken;

class BlockNemesisToken extends Command
{
    protected $signature = 'nemesis:block {token}';
    protected $description = 'Block a Nemesis token by its value';

    public function handle(): int
    {
        $tokenValue = $this->argument('token');

        $token = NemesisToken::where('token', $tokenValue)->first();

        if (! $token) {
            $this->error("❌ Token not found: $tokenValue");
            return self::FAILURE;
        }

        $token->max_requests = 0;
        $token->save();

        $this->info("✅ Token $tokenValue has been blocked.");
        return self::SUCCESS;
    }
}
