<?php

declare(strict_types=1);

namespace Kani\Nemesis\Tests\Integration\Directives;

use AndyDefer\Directive\Enums\ExitCode;
use AndyDefer\Directive\Services\DirectiveTestingService;
use AndyDefer\DomainStructures\Services\HydrationService;
use Kani\Nemesis\Directives\NemesisCleanDirective;
use Kani\Nemesis\Models\NemesisToken;
use Kani\Nemesis\Records\NemesisTokenRecord;
use Kani\Nemesis\Services\NemesisService;
use Kani\Nemesis\Tests\Fixtures\Models\TestUser;
use Kani\Nemesis\Tests\IntegrationTestCase;

final class NemesisCleanDirectiveTest extends IntegrationTestCase
{
    private DirectiveTestingService $service;
    private NemesisService $nemesisService;
    private HydrationService $hydration;

    protected function setUp(): void
    {
        parent::setUp();

        $this->service = new DirectiveTestingService($this->app);
        $this->nemesisService = $this->app->make(NemesisService::class);
        $this->hydration = new HydrationService();
    }

    protected function tearDown(): void
    {
        $this->service->destroy();
        parent::tearDown();
    }

    private function getDirectiveFromContainer(): NemesisCleanDirective
    {
        return $this->app->make(NemesisCleanDirective::class);
    }

    private function createUser(): TestUser
    {
        return TestUser::create([
            'name' => 'Test User',
            'email' => 'test@example.com',
        ]);
    }

    private function createExpiredToken(TestUser $user): NemesisToken
    {
        $record = $this->hydration->hydrate(NemesisTokenRecord::class, [
            'name' => 'expired-token',
            'source' => 'test',
            'abilities' => ['test'],
        ]);

        [$token, $plainToken] = $this->nemesisService->createWithPlainToken($record, $user);

        // Modifier la date d'expiration
        $token->expires_at = now()->subDays(1);
        $token->save();

        return $token;
    }

    private function createValidToken(TestUser $user): NemesisToken
    {
        $record = $this->hydration->hydrate(NemesisTokenRecord::class, [
            'name' => 'valid-token',
            'source' => 'test',
            'abilities' => ['test'],
        ]);

        [$token, $plainToken] = $this->nemesisService->createWithPlainToken($record, $user);

        $token->expires_at = now()->addDays(30);
        $token->save();

        return $token;
    }

    private function createOldToken(TestUser $user): NemesisToken
    {
        $record = $this->hydration->hydrate(NemesisTokenRecord::class, [
            'name' => 'old-token',
            'source' => 'test',
            'abilities' => ['test'],
        ]);

        [$token, $plainToken] = $this->nemesisService->createWithPlainToken($record, $user);

        $token->expires_at = now()->addDays(30);
        $token->created_at = now()->subDays(15);
        $token->save();

        return $token;
    }

    // ============================================================================
    // Signature and Description Tests
    // ============================================================================

    public function test_get_signature_returns_nemesis_clean(): void
    {
        $directive = $this->getDirectiveFromContainer();

        $signature = $directive->getSignature();

        $this->assertSame('nemesis-clean {--days= : Delete tokens older than X days} {--force : Force execution} {--keep-expired : Keep expired tokens}', $signature);
    }

    public function test_get_description_returns_description(): void
    {
        $directive = $this->getDirectiveFromContainer();

        $description = $directive->getDescription();

        $this->assertSame('Clean expired and old tokens based on configuration', $description);
    }

    public function test_get_aliases_returns_aliases(): void
    {
        $directive = $this->getDirectiveFromContainer();

        $aliases = $directive->getAliases();

        $this->assertTrue($aliases->contains('token-clean'));
        $this->assertTrue($aliases->contains('tokens-clean'));
        $this->assertSame(2, $aliases->count());
    }

    // ============================================================================
    // Cleanup Tests
    // ============================================================================

    public function test_clean_deletes_expired_tokens(): void
    {
        // Arrange
        $user = $this->createUser();
        $this->createExpiredToken($user);
        $this->createValidToken($user);

        // Act
        $response = $this->service->run(NemesisCleanDirective::class, ['--force']);

        // Assert
        $this->assertSame(ExitCode::SUCCESS, $response->exit_code);
        $this->assertStringContainsString('Deleted 1 expired tokens', $response->output);
        $this->assertSame(1, NemesisToken::count());
    }

    public function test_clean_deletes_old_tokens(): void
    {
        // Arrange
        $user = $this->createUser();
        $this->createOldToken($user);
        $this->createValidToken($user);

        // Act
        $response = $this->service->run(NemesisCleanDirective::class, ['--force', '--days=1']);

        // Assert
        $this->assertSame(ExitCode::SUCCESS, $response->exit_code);
        $this->assertStringContainsString('Deleted 1 old tokens', $response->output);
        $this->assertSame(1, NemesisToken::count());
    }

    public function test_clean_keeps_expired_tokens_when_flag_present(): void
    {
        // Arrange
        $user = $this->createUser();
        $this->createExpiredToken($user);

        // Act
        $response = $this->service->run(NemesisCleanDirective::class, ['--force', '--keep-expired']);

        // Assert
        $this->assertSame(ExitCode::SUCCESS, $response->exit_code);
        $this->assertStringContainsString('Keeping expired tokens', $response->output);
        $this->assertSame(1, NemesisToken::count());
    }

    public function test_clean_does_nothing_when_no_tokens(): void
    {
        // Act
        $response = $this->service->run(NemesisCleanDirective::class, ['--force']);

        // Assert
        $this->assertSame(ExitCode::SUCCESS, $response->exit_code);
        $this->assertStringContainsString('No tokens needed cleaning', $response->output);
        $this->assertSame(0, NemesisToken::count());
    }

    public function test_clean_skips_when_retention_days_zero(): void
    {
        // Arrange
        $user = $this->createUser();
        $this->createOldToken($user);

        // Act
        $response = $this->service->run(NemesisCleanDirective::class, ['--force', '--days=0']);

        // Assert
        $this->assertSame(ExitCode::SUCCESS, $response->exit_code);
        $this->assertStringContainsString('skipping old token cleanup', $response->output);
    }
}
