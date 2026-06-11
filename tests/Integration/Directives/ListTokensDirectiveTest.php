<?php

// tests/Integration/Directives/ListTokensDirectiveTest.php

declare(strict_types=1);

namespace AndyDefer\Nemesis\Tests\Integration\Directives;

use AndyDefer\Directive\Enums\ExitCode;
use AndyDefer\Directive\Services\DirectiveTestingService;
use AndyDefer\DomainStructures\Services\HydrationService;
use Carbon\Carbon;
use AndyDefer\Nemesis\Directives\ListTokensDirective;
use AndyDefer\Nemesis\Models\NemesisToken;
use AndyDefer\Nemesis\Records\NemesisTokenRecord;
use AndyDefer\Nemesis\Services\NemesisService;
use AndyDefer\Nemesis\Tests\Fixtures\Models\TestApiClient;
use AndyDefer\Nemesis\Tests\Fixtures\Models\TestUser;
use AndyDefer\Nemesis\Tests\IntegrationTestCase;

final class ListTokensDirectiveTest extends IntegrationTestCase
{
    private DirectiveTestingService $service;
    private TestUser $user;
    private TestApiClient $apiClient;
    private NemesisService $nemesisService;
    private HydrationService $hydration;

    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow(Carbon::create(2024, 1, 1, 12, 0, 0));

        $this->service = new DirectiveTestingService($this->app);
        $this->nemesisService = $this->app->make(NemesisService::class);
        $this->hydration = new HydrationService();

        $this->user = TestUser::create([
            'name' => 'John Doe',
            'email' => 'john@example.com',
        ]);

        $this->apiClient = TestApiClient::create([
            'name' => 'API Client 1',
            'api_key' => 'api-key-123',
        ]);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        $this->service->destroy();
        parent::tearDown();
    }

    private function createTokenForUser(TestUser $user, array $overrides = []): NemesisToken
    {
        $record = $this->hydration->hydrate(NemesisTokenRecord::class, array_merge([
            'token_hash' => hash('sha256', uniqid('token-', true)),
            'name' => 'User Token',
            'source' => 'web',
        ], $overrides));

        return $this->nemesisService->create($record, $user);
    }

    private function createTokenForApiClient(TestApiClient $apiClient, array $overrides = []): NemesisToken
    {
        $record = $this->hydration->hydrate(NemesisTokenRecord::class, array_merge([
            'token_hash' => hash('sha256', uniqid('token-', true)),
            'name' => 'API Token',
            'source' => 'api',
        ], $overrides));

        return $this->nemesisService->create($record, $apiClient);
    }

    private function createToken(array $overrides = []): NemesisToken
    {
        return $this->createTokenForUser($this->user, $overrides);
    }

    // ============================================================================
    // Signature and Description Tests
    // ============================================================================

    public function test_get_signature_returns_list_tokens(): void
    {
        $directive = $this->getDirectiveFromContainer();

        $signature = $directive->getSignature();

        $this->assertStringContainsString('list-tokens', $signature);
        $this->assertStringContainsString('--model=', $signature);
    }

    public function test_get_description_returns_description(): void
    {
        $directive = $this->getDirectiveFromContainer();

        $description = $directive->getDescription();

        $this->assertSame('List all tokens in the system', $description);
    }

    public function test_get_aliases_returns_aliases(): void
    {
        $directive = $this->getDirectiveFromContainer();

        $aliases = $directive->getAliases();

        $this->assertTrue($aliases->contains('tokens-list'));
        $this->assertTrue($aliases->contains('nemesis-tokens'));
        $this->assertSame(2, $aliases->count());
    }

    public function test_should_boot_laravel_returns_true(): void
    {
        $directive = $this->getDirectiveFromContainer();

        $this->assertTrue($directive->shouldBootLaravel());
    }

    // ============================================================================
    // Listing Tests
    // ============================================================================

    public function test_execute_shows_warning_when_no_tokens(): void
    {
        $response = $this->service->run(ListTokensDirective::class, []);

        $this->assertSame(ExitCode::SUCCESS, $response->exit_code);
        $this->assertStringContainsString('No tokens found', $response->output);
    }

    public function test_execute_lists_all_tokens(): void
    {
        // Arrange
        $this->createToken(['name' => 'Token 1', 'source' => 'web']);
        $this->createToken(['name' => 'Token 2', 'source' => 'mobile']);

        // Act
        $response = $this->service->run(ListTokensDirective::class, []);

        // Assert
        $this->assertSame(ExitCode::SUCCESS, $response->exit_code);
        $this->assertStringContainsString('Token 1', $response->output);
        $this->assertStringContainsString('Token 2', $response->output);
        $this->assertStringContainsString('Total tokens: 2', $response->output);
    }

    public function test_execute_filters_by_model_option(): void
    {
        // Arrange
        // Créer un token pour TestUser
        $this->createToken(['name' => 'User Token']);

        // Créer un token pour TestApiClient (type différent)
        $this->createTokenForApiClient($this->apiClient, ['name' => 'API Token']);

        // Act: Filtrer par TestApiClient
        $response = $this->service->run(ListTokensDirective::class, ['--model=' . TestApiClient::class]);

        // Assert
        $this->assertSame(ExitCode::SUCCESS, $response->exit_code);
        // Le token API doit être présent
        $this->assertStringContainsString('API Token', $response->output);
        // Le token User NE DOIT PAS être présent
        $this->assertStringNotContainsString('User Token', $response->output);
        $this->assertStringContainsString('Filtering by model', $response->output);
        // Vérifier qu'un seul token est affiché
        $this->assertStringContainsString('Total tokens: 1', $response->output);
    }

    public function test_execute_displays_correct_table_headers(): void
    {
        // Arrange
        $this->createToken();

        // Act
        $response = $this->service->run(ListTokensDirective::class, []);

        // Assert
        $this->assertStringContainsString('ID', $response->output);
        $this->assertStringContainsString('Tokenable Type', $response->output);
        $this->assertStringContainsString('Tokenable ID', $response->output);
        $this->assertStringContainsString('Name', $response->output);
        $this->assertStringContainsString('Source', $response->output);
        $this->assertStringContainsString('Last Used', $response->output);
        $this->assertStringContainsString('Expires At', $response->output);
    }

    public function test_execute_formats_last_used_correctly(): void
    {
        // Arrange
        $token = $this->createToken();
        $token->last_used_at = Carbon::getTestNow();
        $token->save();

        // Act
        $response = $this->service->run(ListTokensDirective::class, []);

        // Assert
        $this->assertStringContainsString('0 seconds', $response->output);
    }

    public function test_execute_shows_never_for_unused_tokens(): void
    {
        // Arrange
        $this->createToken();

        // Act
        $response = $this->service->run(ListTokensDirective::class, []);

        // Assert
        $this->assertStringContainsString('Never', $response->output);
    }

    public function test_execute_formats_expiration_correctly(): void
    {
        // Arrange
        $futureDate = Carbon::getTestNow()->addDays(5);
        $token = $this->createToken();
        $token->expires_at = $futureDate;
        $token->save();

        // Act
        $response = $this->service->run(ListTokensDirective::class, []);

        // Assert
        $this->assertStringContainsString('5 days', $response->output);
    }

    public function test_execute_shows_never_for_non_expiring_tokens(): void
    {
        // Arrange
        $token = $this->createToken();
        $token->expires_at = null;
        $token->save();

        // Act
        $response = $this->service->run(ListTokensDirective::class, []);

        // Assert
        $this->assertStringContainsString('Never', $response->output);
    }

    public function test_execute_shows_expired_for_expired_tokens(): void
    {
        // Arrange
        $pastDate = Carbon::getTestNow()->subDays(5);
        $token = $this->createToken();
        $token->expires_at = $pastDate;
        $token->save();

        // Act
        $response = $this->service->run(ListTokensDirective::class, []);

        // Assert
        $this->assertStringContainsString('Expired', $response->output);
    }

    public function test_execute_displays_total_count(): void
    {
        // Arrange
        $this->createToken();
        $this->createToken();
        $this->createToken();

        // Act
        $response = $this->service->run(ListTokensDirective::class, []);

        // Assert
        $this->assertStringContainsString('Total tokens: 3', $response->output);
    }

    public function test_execute_handles_null_values_gracefully(): void
    {
        // Arrange
        $token = $this->createToken();
        $token->name = null;
        $token->source = null;
        $token->save();

        // Act
        $response = $this->service->run(ListTokensDirective::class, []);

        // Assert
        $this->assertStringContainsString('N/A', $response->output);
    }

    // ============================================================================
    // Private Helper Methods
    // ============================================================================

    private function getDirectiveFromContainer(): ListTokensDirective
    {
        return $this->app->make(ListTokensDirective::class);
    }
}
