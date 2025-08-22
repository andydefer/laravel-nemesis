<?php

namespace Kani\Nemesis\Console;

use Illuminate\Console\Command;
use Kani\Nemesis\Models\NemesisToken;
use Illuminate\Support\Str;

class CreateNemesisToken extends Command
{
    protected $signature = 'nemesis:create {--origins=*} {--max=1000}';
    protected $description = 'Create a new Nemesis API token';

    public function handle(): int
    {
        $token = NemesisToken::create([
            'token' => Str::random(40),
            'allowed_origins' => $this->option('origins') ?: ['*'],
            'max_requests' => $this->option('max'),
            'requests_count' => 0,
        ]);

        $this->info('Nemesis token created: ' . $token->token);
        return self::SUCCESS;
    }
}
