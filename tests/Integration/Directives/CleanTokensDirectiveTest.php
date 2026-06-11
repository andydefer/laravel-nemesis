<?php

// tests/Integration/Directives/CleanTokensDirectiveTest.php

declare(strict_types=1);

namespace Kani\Nemesis\Tests\Integration\Directives;

use AndyDefer\Directive\Enums\ExitCode;
use AndyDefer\Directive\Services\DirectiveTestingService;
use Carbon\Carbon;
use Kani\Nemesis\Directives\CleanTokensDirective;
use Kani\Nemesis\Models\NemesisToken;
use Kani\Nemesis\Tests\Fixtures\Models\TestUser;
use Kani\Nemesis\Tests\IntegrationTestCase;

final class CleanTokensDirectiveTest extends IntegrationTestCase
{
    private DirectiveTestingService $service;
    private TestUser $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->service = new DirectiveTestingService($this->app);

        $this->user = TestUser::create([
            'name' => 'John Doe',
            'email' => 'john@example.com',
        ]);
    }

    protected function tearDown(): void
    {
        $this->service->destroy();
        parent::tearDown();
    }

    private function getDirectiveFromContainer(): CleanTokensDirective
    {
        return $this->app->make(CleanTokensDirective::class);
    }

    private function createToken(array $overrides = []): NemesisToken
    {
        $data = array_merge([
            'token_hash' => hash('sha256', uniqid('token-', true)),
            'tokenable_type' => $this->user->getMorphClass(),
            'tokenable_id' => $this->user->id,
            'name' => 'Test Token',
            'source' => 'web',
        ], $overrides);

        $token = NemesisToken::create($data);

        // Permettre de surcharger created_at
        if (isset($overrides['created_at'])) {
            $token->setCreatedAt($overrides['created_at']);
            $token->saveQuietly(); // Sauvegarde sans déclencher d'events
        }

        // Permettre de surcharger expires_at
        if (isset($overrides['expires_at']) && $overrides['expires_at'] === null) {
            $token->expires_at = null;
            $token->saveQuietly();
        }

        return $token;
    }

    // ============================================================================
    // Signature and Description Tests
    // ============================================================================

    public function test_get_signature_returns_clean_tokens(): void
    {
        $directive = $this->getDirectiveFromContainer();

        $signature = $directive->getSignature();

        $this->assertStringContainsString('clean-tokens', $signature);
        $this->assertStringContainsString('--days=', $signature);
        $this->assertStringContainsString('--force', $signature);
        $this->assertStringContainsString('--keep-expired', $signature);
    }

    public function test_get_description_returns_description(): void
    {
        $directive = $this->getDirectiveFromContainer();

        $this->assertSame('Clean expired and old tokens based on configuration', $directive->getDescription());
    }

    public function test_get_aliases_returns_aliases(): void
    {
        $directive = $this->getDirectiveFromContainer();

        $aliases = $directive->getAliases();

        $this->assertTrue($aliases->contains('tokens-clean'));
        $this->assertTrue($aliases->contains('token-clean'));
        $this->assertTrue($aliases->contains('clean-expired'));
        $this->assertSame(3, $aliases->count());
    }

    public function test_should_boot_laravel_returns_true(): void
    {
        $directive = $this->getDirectiveFromContainer();

        $this->assertTrue($directive->shouldBootLaravel());
    }

    // ============================================================================
    // Cleanup Tests
    // ============================================================================

    public function test_execute_cleans_expired_tokens(): void
    {
        $this->createToken(['name' => 'Active Token']);
        $this->createToken([
            'name' => 'Expired Token',
            'expires_at' => Carbon::getTestNow()->subDay(),
        ]);

        $response = $this->service->run(CleanTokensDirective::class, ['--force']);

        $this->assertSame(ExitCode::SUCCESS, $response->exit_code);
        $this->assertStringContainsString('Deleted 1 expired tokens', $response->output);
    }

    public function test_execute_cleans_old_tokens_based_on_config(): void
    {
        $oldDate = Carbon::getTestNow()->copy()->subDays(60);
        $this->createToken([
            'name' => 'Old Token',
            'created_at' => $oldDate,
            'expires_at' => null,
        ]);
        $this->createToken(['name' => 'Recent Token']);

        $response = $this->service->run(CleanTokensDirective::class, ['--force']);

        $this->assertSame(ExitCode::SUCCESS, $response->exit_code);
        $this->assertStringContainsString('Deleted 1 old tokens', $response->output);
    }

    public function test_execute_cleans_old_tokens_with_custom_days_option(): void
    {
        $oldDate = Carbon::getTestNow()->copy()->subDays(60);
        $this->createToken([
            'name' => 'Old Token',
            'created_at' => $oldDate,
            'expires_at' => null,
        ]);

        $response = $this->service->run(CleanTokensDirective::class, ['--force', '--days=5']);

        $this->assertSame(ExitCode::SUCCESS, $response->exit_code);
        $this->assertStringContainsString('Using retention period from command line: 5 days', $response->output);
        $this->assertStringContainsString('Deleted 1 old tokens', $response->output);
    }

    public function test_execute_keeps_expired_tokens_when_keep_expired_option_used(): void
    {
        $this->createToken([
            'name' => 'Expired Token',
            'expires_at' => Carbon::getTestNow()->subDay(),
        ]);

        $response = $this->service->run(CleanTokensDirective::class, ['--force', '--keep-expired']);

        $this->assertSame(ExitCode::SUCCESS, $response->exit_code);
        $this->assertStringContainsString('Keeping expired tokens as requested', $response->output);
        $this->assertStringNotContainsString('Deleted 1 expired tokens', $response->output);
    }

    public function test_execute_shows_no_tokens_message_when_nothing_to_clean(): void
    {
        $this->createToken(['name' => 'Active Token']);

        $response = $this->service->run(CleanTokensDirective::class, ['--force']);

        $this->assertSame(ExitCode::SUCCESS, $response->exit_code);
        $this->assertStringContainsString('✨ No tokens needed cleaning', $response->output);
    }

    public function test_execute_force_option_skips_confirmation(): void
    {
        $this->createToken([
            'name' => 'Expired Token',
            'expires_at' => Carbon::getTestNow()->subDay(),
        ]);

        $response = $this->service->run(CleanTokensDirective::class, ['--force']);

        $this->assertSame(ExitCode::SUCCESS, $response->exit_code);
        $this->assertStringContainsString('Deleted 1 expired tokens', $response->output);
    }

    public function test_execute_displays_statistics_table(): void
    {
        $this->createToken([
            'name' => 'Expired Token',
            'expires_at' => Carbon::getTestNow()->subDay(),
        ]);

        $response = $this->service->run(CleanTokensDirective::class, ['--force']);

        $this->assertStringContainsString('Expired tokens deleted', $response->output);
        $this->assertStringContainsString('Old tokens deleted', $response->output);
        $this->assertStringContainsString('Total tokens deleted', $response->output);
    }

    public function test_execute_displays_configuration_summary(): void
    {
        $response = $this->service->run(CleanTokensDirective::class, ['--force']);

        $this->assertStringContainsString('📋 Current Configuration', $response->output);
        $this->assertStringContainsString('Auto cleanup:', $response->output);
        $this->assertStringContainsString('Cleanup frequency:', $response->output);
        $this->assertStringContainsString('Retention period:', $response->output);
    }

    public function test_execute_shows_expired_tokens_status_when_removed(): void
    {
        $this->createToken([
            'name' => 'Expired Token',
            'expires_at' => Carbon::getTestNow()->subDay(),
        ]);

        $response = $this->service->run(CleanTokensDirective::class, ['--force']);

        $this->assertStringContainsString('Expired tokens: ✅ Removed', $response->output);
    }

    public function test_execute_shows_expired_tokens_status_when_kept(): void
    {
        $this->createToken([
            'name' => 'Expired Token',
            'expires_at' => Carbon::getTestNow()->subDay(),
        ]);

        $response = $this->service->run(CleanTokensDirective::class, ['--force', '--keep-expired']);

        $this->assertStringContainsString('Expired tokens: ⏸️  Kept (--keep-expired flag used)', $response->output);
    }

    public function test_execute_skips_old_token_cleanup_when_retention_days_is_zero(): void
    {
        $oldDate = Carbon::getTestNow()->copy()->subDays(60);
        $this->createToken([
            'name' => 'Old Token',
            'created_at' => $oldDate,
            'expires_at' => null,
        ]);

        $response = $this->service->run(CleanTokensDirective::class, ['--force', '--days=0']);

        $this->assertStringContainsString('Retention period is set to 0 or negative, skipping old token cleanup', $response->output);
    }

    public function test_execute_handles_multiple_tokens_cleanup(): void
    {
        $expiredDate = Carbon::getTestNow()->subDay();

        $this->createToken([
            'name' => 'Expired Token 1',
            'expires_at' => $expiredDate,
        ]);
        $this->createToken([
            'name' => 'Expired Token 2',
            'expires_at' => $expiredDate,
        ]);
        $this->createToken([
            'name' => 'Expired Token 3',
            'expires_at' => $expiredDate,
        ]);
        $this->createToken(['name' => 'Active Token']);

        $response = $this->service->run(CleanTokensDirective::class, ['--force']);

        $this->assertSame(ExitCode::SUCCESS, $response->exit_code);
        $this->assertStringContainsString('Deleted 3 expired tokens', $response->output);
    }

    public function test_execute_handles_both_expired_and_old_tokens(): void
    {
        $expiredDate = Carbon::getTestNow()->subDay();
        $oldDate = Carbon::getTestNow()->copy()->subDays(60);

        $this->createToken([
            'name' => 'Expired Token',
            'expires_at' => $expiredDate,
        ]);
        $this->createToken([
            'name' => 'Old Token',
            'created_at' => $oldDate,
            'expires_at' => null,
        ]);
        $this->createToken(['name' => 'Active Token']);

        $response = $this->service->run(CleanTokensDirective::class, ['--force']);

        $this->assertSame(ExitCode::SUCCESS, $response->exit_code);
        $this->assertStringContainsString('Deleted 1 expired tokens', $response->output);
        $this->assertStringContainsString('Deleted 1 old tokens', $response->output);
    }

    public function test_execute_shows_correct_headers_and_formatting(): void
    {
        $this->createToken([
            'name' => 'Expired Token',
            'expires_at' => Carbon::getTestNow()->subDay(),
        ]);

        $response = $this->service->run(CleanTokensDirective::class, ['--force']);

        $this->assertStringContainsString('═══════════════════════════════════════════════════════', $response->output);
        $this->assertStringContainsString('🧹 TOKEN CLEANUP COMPLETED', $response->output);
        $this->assertStringContainsString('✅ Cleanup completed successfully!', $response->output);
    }
}
