<?php

namespace Kani\Nemesis\Console;

use Illuminate\Console\Command;
use Kani\Nemesis\Models\NemesisToken;
use Illuminate\Support\Str;

class CreateNemesisToken extends Command
{
    protected $signature = 'nemesis:create
                            {--origins=* : Allowed domains (multiple)}
                            {--max= : Maximum requests limit}
                            {--name= : Descriptive name for the token}';

    protected $description = 'Create a new Nemesis API token';

    public function handle(): int
    {
        $maxRequests = $this->option('max') ?: config('nemesis.default_max_requests');
        $origins = $this->option('origins') ?: ['*'];
        $name = $this->option('name');

        $token = NemesisToken::create([
            'token' => Str::random(40),
            'allowed_origins' => $origins,
            'max_requests' => $maxRequests,
            'requests_count' => 0,
            'name' => $name,
        ]);

        $this->info('✅ Nemesis token created successfully!');
        $this->line('');
        $this->line('Token: ' . $token->token);
        $this->line('Max Requests: ' . $token->max_requests);
        $this->line('Allowed Origins: ' . json_encode($token->allowed_origins));
        if ($name) {
            $this->line('Name: ' . $token->name);
        }
        $this->line('');
        $this->warn('⚠️  Important: Save this token securely as it cannot be retrieved later!');

        return self::SUCCESS;
    }
}
