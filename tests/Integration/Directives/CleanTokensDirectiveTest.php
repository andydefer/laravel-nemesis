<?php

declare(strict_types=1);

namespace AndyDefer\Nemesis\Tests\Integration\Directives;

use AndyDefer\Directive\Enums\ExitCode;
use AndyDefer\Directive\Services\DirectiveTestingService;
use AndyDefer\Nemesis\Directives\CleanTokensDirective;
use AndyDefer\Nemesis\Tests\IntegrationTestCase;

final class CleanTokensDirectiveTest extends IntegrationTestCase
{
    private DirectiveTestingService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new DirectiveTestingService($this->app);
    }

    protected function tearDown(): void
    {
        $this->service->destroy();
        parent::tearDown();
    }

    // ==================== Tests: Signature, Description & Aliases ====================

    public function test_get_signature_returns_correct_string(): void
    {
        $directive = $this->app->make(CleanTokensDirective::class);
        $signature = $directive->getSignature();

        $this->assertStringContainsString('clean-tokens', $signature);
        $this->assertStringContainsString('--days=', $signature);
        $this->assertStringContainsString('--force', $signature);
        $this->assertStringContainsString('--keep-expired', $signature);
    }

    public function test_get_description_returns_string(): void
    {
        $directive = $this->app->make(CleanTokensDirective::class);
        $description = $directive->getDescription();

        $this->assertIsString($description);
        $this->assertNotEmpty($description);
    }

    public function test_get_aliases_returns_aliases(): void
    {
        $directive = $this->app->make(CleanTokensDirective::class);
        $aliases = $directive->getAliases();

        $this->assertTrue($aliases->contains('tokens-clean'));
        $this->assertTrue($aliases->contains('token-clean'));
        $this->assertTrue($aliases->contains('clean-expired'));
        $this->assertSame(3, $aliases->count());
    }

    // ==================== Tests: Cleanup Execution ====================

    public function test_execute_returns_success_even_when_no_tokens(): void
    {
        $response = $this->service->run(CleanTokensDirective::class, ['--force']);

        $this->assertSame(ExitCode::SUCCESS, $response->exit_code);
    }

    public function test_execute_with_force_flag_skips_confirmation(): void
    {
        $response = $this->service->run(CleanTokensDirective::class, ['--force']);

        $this->assertSame(ExitCode::SUCCESS, $response->exit_code);
        $this->assertStringContainsString('TOKEN CLEANUP COMPLETED', $response->output);
    }

    public function test_execute_with_keep_expired_flag(): void
    {
        $response = $this->service->run(CleanTokensDirective::class, ['--force', '--keep-expired']);

        $this->assertSame(ExitCode::SUCCESS, $response->exit_code);
        $this->assertStringContainsString('Keeping expired tokens', $response->output);
    }

    public function test_execute_with_custom_retention_days(): void
    {
        $response = $this->service->run(CleanTokensDirective::class, ['--force', '--days=7']);

        $this->assertSame(ExitCode::SUCCESS, $response->exit_code);
        $this->assertStringContainsString('Using retention period from command line: 7 days', $response->output);
    }

    public function test_execute_displays_configuration_summary(): void
    {
        $response = $this->service->run(CleanTokensDirective::class, ['--force']);

        $this->assertStringContainsString('📋 Current Configuration:', $response->output);
        $this->assertStringContainsString('Auto cleanup:', $response->output);
        $this->assertStringContainsString('Cleanup frequency:', $response->output);
        $this->assertStringContainsString('Retention period:', $response->output);
        $this->assertStringContainsString('Validate origin:', $response->output);
    }

    public function test_execute_displays_expired_tokens_kept_when_flag_used(): void
    {
        $response = $this->service->run(CleanTokensDirective::class, ['--force', '--keep-expired']);

        $this->assertStringContainsString('Expired tokens: ⏸️  Kept', $response->output);
    }

    public function test_execute_displays_expired_tokens_removed_by_default(): void
    {
        $response = $this->service->run(CleanTokensDirective::class, ['--force']);

        $this->assertStringContainsString('Expired tokens: ✅ Removed', $response->output);
    }

    // ==================== Tests: Display Messages ====================

    public function test_execute_displays_total_tokens_deleted(): void
    {
        $response = $this->service->run(CleanTokensDirective::class, ['--force']);

        $this->assertStringContainsString('Total tokens deleted', $response->output);
    }

    public function test_execute_displays_no_tokens_message_when_nothing_deleted(): void
    {
        $response = $this->service->run(CleanTokensDirective::class, ['--force']);

        $hasNoTokensMessage = str_contains($response->output, 'No tokens needed cleaning');
        $hasDeletedMessage = str_contains($response->output, 'Deleted');

        $this->assertTrue($hasNoTokensMessage || $hasDeletedMessage);
    }

    // ==================== Tests: Header Display ====================

    public function test_execute_displays_header(): void
    {
        $response = $this->service->run(CleanTokensDirective::class, ['--force']);

        $this->assertStringContainsString('TOKEN CLEANUP COMPLETED', $response->output);
    }
}
