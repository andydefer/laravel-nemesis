<?php

declare(strict_types=1);

namespace Kani\Nemesis\Tests\Integration\Directives;

use AndyDefer\Directive\Enums\ExitCode;
use AndyDefer\Directive\Records\DirectiveResponseRecord;
use AndyDefer\Directive\Testing\InteractsWithDirectives;
use Kani\Nemesis\Directives\NemesisCleanDirective;
use Kani\Nemesis\Models\NemesisToken;
use Kani\Nemesis\Tests\IntegrationTestCase;
use Kani\Nemesis\Tests\FIxtures\Models\TestUser;

final class NemesisCleanDirectiveTest extends IntegrationTestCase
{
    use InteractsWithDirectives;

    protected function setUp(): void
    {
        // Initialiser d'abord l'environnement de test des directives
        $this->initDirectiveTesting(bootLaravel: true);

        // Puis appeler parent::setUp() qui va exécuter les migrations
        parent::setUp();
    }

    protected function tearDown(): void
    {
        $this->destroyDirectiveTesting();
        parent::tearDown();
    }

    private function getDirective(): NemesisCleanDirective
    {
        return new NemesisCleanDirective($this->interaction);
    }

    private function registerAndRun(string $signature, array $arguments = []): DirectiveResponseRecord
    {
        $directive = $this->getDirective();
        $this->registerDirective($directive);

        return $this->runDirective($signature, $arguments);
    }

    private function createUser(): TestUser
    {
        return TestUser::create([
            'name' => 'Test User',
            'email' => 'test@example.com',
        ]);
    }

    private function createExpiredToken(TestUser $user): void
    {
        $plainToken = $user->createNemesisToken(
            name: 'expired-token',
            source: 'test',
            abilities: ['test']
        );

        $hashedToken = hash('sha256', $plainToken);

        NemesisToken::where('token_hash', $hashedToken)->update([
            'expires_at' => now()->subDays(1)
        ]);
    }

    private function createValidToken(TestUser $user): void
    {
        $plainToken = $user->createNemesisToken(
            name: 'valid-token',
            source: 'test',
            abilities: ['test']
        );

        $hashedToken = hash('sha256', $plainToken);

        NemesisToken::where('token_hash', $hashedToken)->update([
            'expires_at' => now()->addDays(30)
        ]);
    }

    private function createOldToken(TestUser $user): void
    {
        $plainToken = $user->createNemesisToken(
            name: 'old-token',
            source: 'test',
            abilities: ['test']
        );

        $hashedToken = hash('sha256', $plainToken);

        NemesisToken::where('token_hash', $hashedToken)->update([
            'expires_at' => now()->addDays(30),
            'created_at' => now()->subDays(15)
        ]);
    }

    public function test_get_signature_returns_nemesis_clean(): void
    {
        $directive = $this->getDirective();

        $signature = $directive->getSignature();

        $this->assertSame('nemesis-clean {--days= : Delete tokens older than X days} {--force : Force execution} {--keep-expired : Keep expired tokens}', $signature);
    }

    public function test_get_description_returns_description(): void
    {
        $directive = $this->getDirective();

        $description = $directive->getDescription();

        $this->assertSame('Clean expired and old tokens based on configuration', $description);
    }

    public function test_get_aliases_returns_aliases(): void
    {
        $directive = $this->getDirective();

        $aliases = $directive->getAliases();

        $this->assertTrue($aliases->contains('token-clean'));
        $this->assertTrue($aliases->contains('tokens-clean'));
        $this->assertSame(2, $aliases->count());
    }

    public function test_clean_deletes_expired_tokens(): void
    {
        // Arrange
        $user = $this->createUser();
        $this->createExpiredToken($user);
        $this->createValidToken($user);

        // Act
        $response = $this->registerAndRun('nemesis-clean', ['--force']);

        // Assert
        $this->assertSame(ExitCode::SUCCESS, $response->exitCode);
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
        $response = $this->registerAndRun('nemesis-clean', ['--force', '--days=1']);

        // Assert
        $this->assertSame(ExitCode::SUCCESS, $response->exitCode);
        $this->assertStringContainsString('Deleted 1 old tokens', $response->output);
        $this->assertSame(1, NemesisToken::count());
    }

    public function test_clean_keeps_expired_tokens_when_flag_present(): void
    {
        // Arrange
        $user = $this->createUser();
        $this->createExpiredToken($user);

        // Act
        $response = $this->registerAndRun('nemesis-clean', ['--force', '--keep-expired']);

        // Assert
        $this->assertSame(ExitCode::SUCCESS, $response->exitCode);
        $this->assertStringContainsString('Keeping expired tokens', $response->output);
        $this->assertSame(1, NemesisToken::count());
    }

    public function test_clean_does_nothing_when_no_tokens(): void
    {
        // Act
        $response = $this->registerAndRun('nemesis-clean', ['--force']);

        // Assert
        $this->assertSame(ExitCode::SUCCESS, $response->exitCode);
        $this->assertStringContainsString('No tokens needed cleaning', $response->output);
        $this->assertSame(0, NemesisToken::count());
    }

    public function test_clean_skips_when_retention_days_zero(): void
    {
        // Arrange
        $user = $this->createUser();
        $this->createOldToken($user);

        // Act
        $response = $this->registerAndRun('nemesis-clean', ['--force', '--days=0']);

        // Assert
        $this->assertSame(ExitCode::SUCCESS, $response->exitCode);
        $this->assertStringContainsString('skipping old token cleanup', $response->output);
    }
}
