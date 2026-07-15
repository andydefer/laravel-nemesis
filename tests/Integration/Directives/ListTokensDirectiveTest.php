<?php

declare(strict_types=1);

namespace AndyDefer\Nemesis\Tests\Directives;

use AndyDefer\Directive\Enums\ExitCode;
use AndyDefer\Directive\Helpers\Paths;
use AndyDefer\Directive\Services\DirectiveTestingService;
use AndyDefer\Nemesis\Directives\ListTokensDirective;
use AndyDefer\Nemesis\Models\NemesisToken;
use AndyDefer\Nemesis\Tests\Fixtures\Models\TestUser;
use AndyDefer\Nemesis\Tests\IntegrationTestCase;

final class ListTokensDirectiveTest extends IntegrationTestCase
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
    }

    protected function tearDown(): void
    {
        $this->testingService->destroy();
        parent::tearDown();
    }

    private function createToken(array $overrides = []): NemesisToken
    {
        $defaults = [
            'token_hash' => hash('sha256', 'token_'.uniqid()),
            'tokenable_type' => TestUser::class,
            'tokenable_id' => 1,
            'name' => 'test-token',
            'source' => 'test',
            'metadata' => json_encode([]),
            'allowed_origins' => null,
            'abilities' => null,
        ];

        $data = array_merge($defaults, $overrides);

        return NemesisToken::create($data);
    }

    public function test_list_tokens_shows_all_tokens(): void
    {
        $this->createToken(['name' => 'token-1', 'source' => 'web']);
        $this->createToken(['name' => 'token-2', 'source' => 'mobile']);
        $this->createToken(['name' => 'token-3', 'source' => 'api']);

        $response = $this->testingService->run('nemesis:list-tokens');

        $this->assertSame(ExitCode::SUCCESS, $response->exit_code);
        $this->assertStringContainsString('Total tokens: 3', $response->output);
        $this->assertStringContainsString('token-1', $response->output);
        $this->assertStringContainsString('token-2', $response->output);
        $this->assertStringContainsString('token-3', $response->output);
    }

    public function test_list_tokens_with_limit(): void
    {
        for ($i = 0; $i < 10; $i++) {
            $this->createToken(['name' => 'token-'.$i]);
        }

        $response = $this->testingService->run('nemesis:list-tokens 5');

        $this->assertSame(ExitCode::SUCCESS, $response->exit_code);
        $this->assertStringContainsString('Total tokens: 5', $response->output);
    }

    public function test_list_tokens_with_model_filter(): void
    {
        $this->createToken(['tokenable_type' => TestUser::class]);
        $this->createToken(['tokenable_type' => 'App\\Models\\Admin']);
        $this->createToken(['tokenable_type' => TestUser::class]);

        $response = $this->testingService->run('nemesis:list-tokens 50 User');

        $this->assertSame(ExitCode::SUCCESS, $response->exit_code);
        $this->assertStringContainsString('Filtering by model: User', $response->output);
        $this->assertStringContainsString('Total tokens: 2', $response->output);
        $this->assertStringContainsString('User', $response->output);
        $this->assertStringNotContainsString('Admin', $response->output);
    }

    public function test_list_tokens_with_partial_model_filter(): void
    {
        $this->createToken(['tokenable_type' => TestUser::class]);
        $this->createToken(['tokenable_type' => 'App\\Models\\AdminUser']);
        $this->createToken(['tokenable_type' => 'App\\Models\\Guest']);

        $response = $this->testingService->run('nemesis:list-tokens 50 User');

        $this->assertSame(ExitCode::SUCCESS, $response->exit_code);
        $this->assertStringContainsString('Filtering by model: User', $response->output);
        $this->assertStringContainsString('Total tokens: 2', $response->output);
        $this->assertStringContainsString('User', $response->output);
        $this->assertStringContainsString('AdminUser', $response->output);
        $this->assertStringNotContainsString('Guest', $response->output);
    }

    public function test_list_tokens_when_no_tokens(): void
    {
        $response = $this->testingService->run('nemesis:list-tokens');

        $this->assertSame(ExitCode::SUCCESS, $response->exit_code);
        $this->assertStringContainsString('No tokens found.', $response->output);
    }

    public function test_list_tokens_displays_headers(): void
    {
        $this->createToken();

        $response = $this->testingService->run('nemesis:list-tokens');

        $this->assertSame(ExitCode::SUCCESS, $response->exit_code);
        $this->assertStringContainsString('ID', $response->output);
        $this->assertStringContainsString('Tokenable Type', $response->output);
        $this->assertStringContainsString('Tokenable ID', $response->output);
        $this->assertStringContainsString('Name', $response->output);
        $this->assertStringContainsString('Source', $response->output);
        $this->assertStringContainsString('Last Used', $response->output);
        $this->assertStringContainsString('Expires At', $response->output);
    }

    public function test_list_tokens_formats_tokenable_type(): void
    {
        $this->createToken(['tokenable_type' => 'App\\Models\\CustomUser']);

        $response = $this->testingService->run('nemesis:list-tokens');

        $this->assertSame(ExitCode::SUCCESS, $response->exit_code);
        $this->assertStringContainsString('CustomUser', $response->output);
        $this->assertStringNotContainsString('App\\Models\\CustomUser', $response->output);
    }

    public function test_list_tokens_formats_last_used(): void
    {
        $token = $this->createToken();
        $token->last_used_at = now()->subHour();
        $token->save();

        $response = $this->testingService->run('nemesis:list-tokens');

        $this->assertSame(ExitCode::SUCCESS, $response->exit_code);
        $this->assertStringContainsString('1 hour ago', $response->output);
    }

    public function test_list_tokens_formats_never_used(): void
    {
        $this->createToken(['last_used_at' => null]);

        $response = $this->testingService->run('nemesis:list-tokens');

        $this->assertSame(ExitCode::SUCCESS, $response->exit_code);
        $this->assertStringContainsString('Never', $response->output);
    }

    public function test_list_tokens_formats_expiration_never(): void
    {
        $this->createToken(['expires_at' => null]);

        $response = $this->testingService->run('nemesis:list-tokens');

        $this->assertSame(ExitCode::SUCCESS, $response->exit_code);
        $this->assertStringContainsString('Never', $response->output);
    }

    public function test_list_tokens_formats_expired(): void
    {
        $this->createToken(['expires_at' => now()->subDay()]);

        $response = $this->testingService->run('nemesis:list-tokens');

        $this->assertSame(ExitCode::SUCCESS, $response->exit_code);
        $this->assertStringContainsString('Expired', $response->output);
        $this->assertStringContainsString('ago', $response->output);
    }

    public function test_list_tokens_formats_future_expiration(): void
    {
        $this->createToken(['expires_at' => now()->addDays(7)]);

        $response = $this->testingService->run('nemesis:list-tokens');

        $this->assertSame(ExitCode::SUCCESS, $response->exit_code);
        $this->assertStringContainsString('week', $response->output);
        $this->assertStringNotContainsString('Expired', $response->output);
    }

    public function test_list_tokens_shows_n_a_for_missing_values(): void
    {
        $this->createToken(['name' => null, 'source' => null]);

        $response = $this->testingService->run('nemesis:list-tokens');

        $this->assertSame(ExitCode::SUCCESS, $response->exit_code);
        $this->assertStringContainsString('N/A', $response->output);
    }

    public function test_list_tokens_with_alias(): void
    {
        $this->createToken();

        $response = $this->testingService->run('tokens-list');

        $this->assertSame(ExitCode::SUCCESS, $response->exit_code);
        $this->assertStringContainsString('Total tokens: 1', $response->output);
    }

    public function test_list_tokens_with_second_alias(): void
    {
        $this->createToken();

        $response = $this->testingService->run('nemesis-tokens');

        $this->assertSame(ExitCode::SUCCESS, $response->exit_code);
        $this->assertStringContainsString('Total tokens: 1', $response->output);
    }

    public function test_list_tokens_by_fqcn(): void
    {
        $this->createToken(['name' => 'fqcn-test']);

        $response = $this->testingService->runDirective(
            ListTokensDirective::class,
            []
        );

        $this->assertSame(ExitCode::SUCCESS, $response->exit_code);
        $this->assertStringContainsString('fqcn-test', $response->output);
        $this->assertStringContainsString('Total tokens: 1', $response->output);
    }

    public function test_list_tokens_default_limit_50(): void
    {
        for ($i = 0; $i < 60; $i++) {
            $this->createToken(['name' => 'token-'.$i]);
        }

        $response = $this->testingService->run('nemesis:list-tokens');

        $this->assertSame(ExitCode::SUCCESS, $response->exit_code);
        $this->assertStringContainsString('Total tokens: 50', $response->output);
    }

    public function test_list_tokens_with_custom_limit(): void
    {
        for ($i = 0; $i < 20; $i++) {
            $this->createToken(['name' => 'token-'.$i]);
        }

        $response = $this->testingService->run('nemesis:list-tokens 10');

        $this->assertSame(ExitCode::SUCCESS, $response->exit_code);
        $this->assertStringContainsString('Total tokens: 10', $response->output);
    }

    public function test_list_tokens_with_model_filter_and_limit(): void
    {
        for ($i = 0; $i < 10; $i++) {
            $this->createToken([
                'tokenable_type' => TestUser::class,
                'name' => 'user-token-'.$i,
            ]);
        }

        for ($i = 0; $i < 5; $i++) {
            $this->createToken([
                'tokenable_type' => 'App\\Models\\Admin',
                'name' => 'admin-token-'.$i,
            ]);
        }

        $response = $this->testingService->run('nemesis:list-tokens 3 User');

        $this->assertSame(ExitCode::SUCCESS, $response->exit_code);
        $this->assertStringContainsString('Filtering by model: User', $response->output);
        $this->assertStringContainsString('Total tokens: 3', $response->output);
        $this->assertStringContainsString('user-token', $response->output);
        $this->assertStringNotContainsString('admin-token', $response->output);
    }

    public function test_list_tokens_displays_multiple_tokens_in_table(): void
    {
        $this->createToken(['name' => 'Alpha', 'source' => 'web']);
        $this->createToken(['name' => 'Beta', 'source' => 'mobile']);
        $this->createToken(['name' => 'Gamma', 'source' => 'api']);

        $response = $this->testingService->run('nemesis:list-tokens');

        $this->assertSame(ExitCode::SUCCESS, $response->exit_code);
        $this->assertStringContainsString('Alpha', $response->output);
        $this->assertStringContainsString('Beta', $response->output);
        $this->assertStringContainsString('Gamma', $response->output);
        $this->assertStringContainsString('web', $response->output);
        $this->assertStringContainsString('mobile', $response->output);
        $this->assertStringContainsString('api', $response->output);
    }
}
