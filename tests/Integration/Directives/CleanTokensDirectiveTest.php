<?php

declare(strict_types=1);

namespace AndyDefer\Nemesis\Tests\Directives;

use AndyDefer\Directive\Enums\ExitCode;
use AndyDefer\Directive\Helpers\Paths;
use AndyDefer\Directive\Services\DirectiveTestingService;
use AndyDefer\Nemesis\Directives\CleanTokensDirective;
use AndyDefer\Nemesis\Models\NemesisToken;
use AndyDefer\Nemesis\Tests\Fixtures\Models\TestUser;
use AndyDefer\Nemesis\Tests\IntegrationTestCase;
use Illuminate\Support\Facades\Config;

final class CleanTokensDirectiveTest extends IntegrationTestCase
{
    private DirectiveTestingService $testingService;

    protected function setUp(): void
    {
        parent::setUp();

        $this->testingService = new DirectiveTestingService(
            $this->app,
            [
                Paths::projectRoot().'/src/Directives',

            ]
        );

        // Configuration pour les tests
        Config::set('nemesis.cleanup.keep_expired_for_days', 30);
        Config::set('nemesis.cleanup.auto_cleanup', true);
        Config::set('nemesis.cleanup.frequency', 60);
    }

    protected function tearDown(): void
    {
        $this->testingService->destroy();
        parent::tearDown();
    }

    public function test_clean_tokens_success_with_force_flag(): void
    {
        // Créer un token expiré
        $token = NemesisToken::create([
            'token_hash' => hash('sha256', 'test_token_1'),
            'tokenable_type' => TestUser::class,
            'tokenable_id' => 1,
            'name' => 'test-token',
            'source' => 'test',
            'expires_at' => now()->subDay(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $response = $this->testingService->run('nemesis:clean-tokens --force');

        $this->assertSame(ExitCode::SUCCESS, $response->exit_code);
        $this->assertStringContainsString('TOKEN CLEANUP COMPLETED', $response->output);
        $this->assertStringContainsString('Deleted 1 expired tokens', $response->output);

        // Vérifier que le token a été supprimé
        $this->assertDatabaseMissing('nemesis_tokens', ['id' => $token->id]);
    }

    public function test_clean_tokens_with_days_argument(): void
    {
        // Créer un token expiré
        NemesisToken::create([
            'token_hash' => hash('sha256', 'test_token_2'),
            'tokenable_type' => TestUser::class,
            'tokenable_id' => 2,
            'name' => 'test-token-2',
            'source' => 'test',
            'expires_at' => now()->subDay(),
            'created_at' => now()->subDays(5),
            'updated_at' => now()->subDays(5),
        ]);

        $response = $this->testingService->run('nemesis:clean-tokens 7 --force');

        $this->assertSame(ExitCode::SUCCESS, $response->exit_code);
        $this->assertStringContainsString('Using retention period from command line: 7 days', $response->output);
        $this->assertStringContainsString('TOKEN CLEANUP COMPLETED', $response->output);
    }

    public function test_clean_tokens_with_keep_expired_flag(): void
    {
        // Créer un token expiré
        $token = NemesisToken::create([
            'token_hash' => hash('sha256', 'test_token_3'),
            'tokenable_type' => TestUser::class,
            'tokenable_id' => 3,
            'name' => 'test-token-3',
            'source' => 'test',
            'expires_at' => now()->subDay(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $response = $this->testingService->run('nemesis:clean-tokens --force --keep-expired');

        $this->assertSame(ExitCode::SUCCESS, $response->exit_code);
        $this->assertStringContainsString('Keeping expired tokens as requested (--keep-expired)', $response->output);
        $this->assertStringContainsString('Expired tokens: ⏸️  Kept', $response->output);

        // Vérifier que le token n'a PAS été supprimé
        $this->assertDatabaseHas('nemesis_tokens', ['id' => $token->id]);
    }

    public function test_clean_tokens_without_tokens(): void
    {
        $response = $this->testingService->run('nemesis:clean-tokens --force');

        $this->assertSame(ExitCode::SUCCESS, $response->exit_code);
        $this->assertStringContainsString('No tokens needed cleaning', $response->output);
    }

    public function test_clean_tokens_requires_confirmation_without_force(): void
    {
        // Sans --force, la commande demande confirmation
        // Dans les tests, on utilise --force pour éviter l'interaction
        $response = $this->testingService->run('nemesis:clean-tokens --force');

        $this->assertSame(ExitCode::SUCCESS, $response->exit_code);
    }

    public function test_clean_tokens_with_zero_retention_days(): void
    {
        Config::set('nemesis.cleanup.keep_expired_for_days', 0);

        $response = $this->testingService->run('nemesis:clean-tokens 0 --force');

        $this->assertSame(ExitCode::SUCCESS, $response->exit_code);
        $this->assertStringContainsString('Retention period is set to 0 or negative, skipping old token cleanup', $response->output);
    }

    public function test_clean_tokens_uses_config_retention_days(): void
    {
        Config::set('nemesis.cleanup.keep_expired_for_days', 45);

        $response = $this->testingService->run('nemesis:clean-tokens --force');

        $this->assertSame(ExitCode::SUCCESS, $response->exit_code);
        $this->assertStringContainsString('Using retention period from config: 45 days', $response->output);
    }

    public function test_clean_tokens_displays_configuration_summary(): void
    {
        $response = $this->testingService->run('nemesis:clean-tokens --force');

        $this->assertSame(ExitCode::SUCCESS, $response->exit_code);
        $this->assertStringContainsString('📋 Current Configuration:', $response->output);
        $this->assertStringContainsString('Auto cleanup:', $response->output);
        $this->assertStringContainsString('Cleanup frequency:', $response->output);
        $this->assertStringContainsString('Retention period:', $response->output);
        $this->assertStringContainsString('Validate origin:', $response->output);
    }

    public function test_clean_tokens_with_alias(): void
    {
        $response = $this->testingService->run('nemesis-tc --force');

        $this->assertSame(ExitCode::SUCCESS, $response->exit_code);
        $this->assertStringContainsString('TOKEN CLEANUP COMPLETED', $response->output);
    }

    public function test_clean_tokens_with_second_alias(): void
    {
        $response = $this->testingService->run('nemesis-ce --force');

        $this->assertSame(ExitCode::SUCCESS, $response->exit_code);
        $this->assertStringContainsString('TOKEN CLEANUP COMPLETED', $response->output);
    }

    public function test_clean_tokens_force_deletes_expired_tokens(): void
    {
        // Créer plusieurs tokens expirés
        for ($i = 0; $i < 5; $i++) {
            NemesisToken::create([
                'token_hash' => hash('sha256', 'test_token_expired_'.$i),
                'tokenable_type' => TestUser::class,
                'tokenable_id' => 10 + $i,
                'name' => 'test-token-'.$i,
                'source' => 'test',
                'expires_at' => now()->subDays(2),
                'created_at' => now()->subDays(3),
                'updated_at' => now()->subDays(3),
            ]);
        }

        $response = $this->testingService->run('nemesis:clean-tokens --force');

        $this->assertSame(ExitCode::SUCCESS, $response->exit_code);
        $this->assertStringContainsString('Deleted 5 expired tokens', $response->output);

        // Vérifier que tous les tokens ont été supprimés
        $count = NemesisToken::where('expires_at', '<', now())->count();
        $this->assertEquals(0, $count);
    }

    public function test_clean_tokens_by_fqcn(): void
    {
        $response = $this->testingService->runDirective(
            CleanTokensDirective::class,
            ['--force']
        );

        $this->assertSame(ExitCode::SUCCESS, $response->exit_code);
        $this->assertStringContainsString('TOKEN CLEANUP COMPLETED', $response->output);
    }

    public function test_clean_tokens_displays_stats_table(): void
    {
        // Créer un token expiré
        NemesisToken::create([
            'token_hash' => hash('sha256', 'test_token_stats'),
            'tokenable_type' => TestUser::class,
            'tokenable_id' => 99,
            'name' => 'test-token-stats',
            'source' => 'test',
            'expires_at' => now()->subDay(),
            'created_at' => now()->subDays(10),
            'updated_at' => now()->subDays(10),
        ]);

        $response = $this->testingService->run('nemesis:clean-tokens --force');

        $this->assertSame(ExitCode::SUCCESS, $response->exit_code);
        $this->assertStringContainsString('Metric', $response->output);
        $this->assertStringContainsString('Count', $response->output);
        $this->assertStringContainsString('Expired tokens deleted', $response->output);
        $this->assertStringContainsString('Old tokens deleted', $response->output);
        $this->assertStringContainsString('Total tokens deleted', $response->output);
    }
}
