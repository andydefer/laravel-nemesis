<?php

namespace Kani\Nemesis\Console;

use Illuminate\Console\Command;
use Kani\Nemesis\Models\NemesisToken;
use Illuminate\Support\Carbon;

class ListNemesisTokens extends Command
{
    protected $signature = 'nemesis:list
                            {--status= : Filter by status: active, blocked, all}
                            {--limit=10 : Number of tokens to display}';

    protected $description = 'List all Nemesis tokens with their status';

    public function handle(): int
    {
        $status = $this->option('status') ?? 'all';
        $limit = (int) $this->option('limit');

        $query = NemesisToken::query();

        switch ($status) {
            case 'active':
                $query->where('max_requests', '>', 0);
                break;
            case 'blocked':
                $query->where('max_requests', 0);
                break;
            case 'all':
            default:
                // No filter
                break;
        }

        $tokens = $query->orderBy('created_at', 'desc')
            ->limit($limit)
            ->get();

        $totalCount = NemesisToken::count();
        $activeCount = NemesisToken::where('max_requests', '>', 0)->count();
        $blockedCount = NemesisToken::where('max_requests', 0)->count();

        $this->info("📋 Nemesis Tokens List (Showing {$tokens->count()} of {$totalCount} tokens)");
        $this->line("✅ Active: {$activeCount} | 🚫 Blocked: {$blockedCount}");
        $this->line('');

        if ($tokens->isEmpty()) {
            $this->info('No tokens found.');
            return self::SUCCESS;
        }

        $headers = ['Name', 'Token (truncated)', 'Status', 'Usage', 'Last Used', 'Created'];
        $rows = [];

        foreach ($tokens as $token) {
            $truncatedToken = substr($token->token, 0, 12) . '...';
            $status = $token->max_requests > 0 ? '✅ Active' : '🚫 Blocked';
            $usage = "{$token->requests_count}/" . ($token->max_requests > 0 ? $token->max_requests : '0');

            $lastUsed = $token->last_request_at
                ? Carbon::parse($token->last_request_at)->diffForHumans()
                : 'Never';

            $created = Carbon::parse($token->created_at)->format('Y-m-d');

            $rows[] = [
                $token->name ?? 'N/A',
                $truncatedToken,
                $status,
                $usage,
                $lastUsed,
                $created
            ];
        }

        $this->table($headers, $rows);

        return self::SUCCESS;
    }
}
