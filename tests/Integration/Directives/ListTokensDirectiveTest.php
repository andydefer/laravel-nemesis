<?php

declare(strict_types=1);

namespace AndyDefer\Nemesis\Tests\Integration\Directives;

use AndyDefer\Directive\Enums\ExitCode;
use AndyDefer\Directive\Services\DirectiveTestingService;
use AndyDefer\Nemesis\Directives\ListTokensDirective;
use AndyDefer\Nemesis\Models\NemesisToken;
use AndyDefer\Nemesis\Tests\Fixtures\Models\TestUser;
use AndyDefer\Nemesis\Tests\IntegrationTestCase;
use Illuminate\Support\Str;

final class ListTokensDirectiveTest extends IntegrationTestCase
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

    public function test_get_signature_returns_correct_string(): void
    {
        $directive = $this->app->make(ListTokensDirective::class);
        $signature = $directive->getSignature();

        $this->assertStringContainsString('list-tokens', $signature);
        $this->assertStringContainsString('--model=', $signature);
    }

    public function test_get_description_returns_string(): void
    {
        $directive = $this->app->make(ListTokensDirective::class);
        $description = $directive->getDescription();

        $this->assertIsString($description);
        $this->assertNotEmpty($description);
    }

    public function test_get_aliases_returns_aliases(): void
    {
        $directive = $this->app->make(ListTokensDirective::class);
        $aliases = $directive->getAliases();

        $this->assertTrue($aliases->contains('tokens-list'));
        $this->assertTrue($aliases->contains('nemesis-tokens'));
        $this->assertSame(2, $aliases->count());
    }

    public function test_execute_returns_success_when_no_tokens(): void
    {
        $response = $this->service->run(ListTokensDirective::class, []);

        $this->assertSame(ExitCode::SUCCESS, $response->exit_code);
        $this->assertStringContainsString('No tokens found', $response->output);
    }

    public function test_execute_displays_table_when_tokens_exist(): void
    {
        $this->createTestToken(['name' => 'Test Token 1', 'source' => 'api']);
        $this->createTestToken(['name' => 'Test Token 2', 'source' => 'cli']);

        $response = $this->service->run(ListTokensDirective::class, []);

        $this->assertSame(ExitCode::SUCCESS, $response->exit_code);
        $this->assertStringContainsString('Test Token 1', $response->output);
        $this->assertStringContainsString('Test Token 2', $response->output);
        $this->assertStringContainsString('Total tokens: 2', $response->output);
    }

    public function test_execute_filters_by_model_with_basename(): void
    {
        // Créer un token avec le namespace complet
        $this->createTestToken([
            'name' => 'User Token',
            'tokenable_type' => TestUser::class,
        ]);
        $this->createTestToken([
            'name' => 'Api Token',
            'tokenable_type' => 'App\Models\ApiClient',
        ]);

        // Filtrer avec le basename (fonctionne grâce à LIKE)
        $response = $this->service->run(ListTokensDirective::class, ['--model=TestUser']);

        $this->assertSame(ExitCode::SUCCESS, $response->exit_code);
        $this->assertStringContainsString('Filtering by model: TestUser', $response->output);
        $this->assertStringContainsString('User Token', $response->output);
        $this->assertStringNotContainsString('Api Token', $response->output);
    }

    public function test_execute_filters_by_model_with_partial_namespace(): void
    {
        $this->createTestToken([
            'name' => 'User Token',
            'tokenable_type' => TestUser::class,
        ]);

        // Filtrer avec une partie du namespace
        $response = $this->service->run(ListTokensDirective::class, ['--model=Fixtures']);

        $this->assertSame(ExitCode::SUCCESS, $response->exit_code);
        $this->assertStringContainsString('User Token', $response->output);
    }

    public function test_execute_shows_warning_when_no_tokens_match_filter(): void
    {
        $response = $this->service->run(ListTokensDirective::class, ['--model=NonexistentModel']);

        $this->assertSame(ExitCode::SUCCESS, $response->exit_code);
        $this->assertStringContainsString('No tokens found', $response->output);
    }

    public function test_execute_displays_correct_table_headers(): void
    {
        $this->createTestToken();

        $response = $this->service->run(ListTokensDirective::class, []);

        $this->assertStringContainsString('ID', $response->output);
        $this->assertStringContainsString('Tokenable Type', $response->output);
        $this->assertStringContainsString('Tokenable ID', $response->output);
        $this->assertStringContainsString('Name', $response->output);
        $this->assertStringContainsString('Source', $response->output);
        $this->assertStringContainsString('Last Used', $response->output);
        $this->assertStringContainsString('Expires At', $response->output);
    }

    public function test_execute_formats_never_used_correctly(): void
    {
        $this->createTestToken([
            'name' => 'Never Used Token',
            'last_used_at' => null,
        ]);

        $response = $this->service->run(ListTokensDirective::class, []);

        $this->assertStringContainsString('Never Used Token', $response->output);
        $this->assertStringContainsString('Never', $response->output);
    }

    public function test_execute_formats_never_expires_correctly(): void
    {
        $this->createTestToken([
            'name' => 'Never Expires Token',
            'expires_at' => null,
        ]);

        $response = $this->service->run(ListTokensDirective::class, []);

        $this->assertStringContainsString('Never Expires Token', $response->output);
        $this->assertStringContainsString('Never', $response->output);
    }

    // ==================== Helper Methods ====================

    private function createTestToken(array $overrides = []): NemesisToken
    {
        $defaults = [
            'token_hash' => hash('sha256', Str::random(40)),
            'name' => 'Test Token',
            'source' => 'test',
            'tokenable_type' => TestUser::class,
            'tokenable_id' => 1,
            'last_used_at' => now(),
            'expires_at' => now()->addDays(30),
        ];

        $data = array_merge($defaults, $overrides);

        return NemesisToken::create($data);
    }
}
