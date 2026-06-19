<?php

// tests/Integration/Http/Middleware/NemesisTokenMiddlewareTest.php

declare(strict_types=1);

namespace AndyDefer\Nemesis\Tests\Integration\Http\Middleware;

use AndyDefer\DomainStructures\Services\HydrationService;
use AndyDefer\Nemesis\Contracts\Configs\NemesisConfigInterface;
use AndyDefer\Nemesis\Models\NemesisToken;
use AndyDefer\Nemesis\Records\NemesisTokenRecord;
use AndyDefer\Nemesis\Services\NemesisAuthenticationService;
use AndyDefer\Nemesis\Services\NemesisService;
use AndyDefer\Nemesis\Tests\Fixtures\Models\TestUser;
use AndyDefer\Nemesis\Tests\IntegrationTestCase;
use AndyDefer\PhpVo\ValueObjects\DateTimeVO;
use Carbon\Carbon;
use Illuminate\Support\Facades\Route;

final class NemesisTokenMiddlewareTest extends IntegrationTestCase
{
    private TestUser $user;

    private NemesisConfigInterface $config;

    private NemesisService $service;

    private NemesisAuthenticationService $authService;

    private HydrationService $hydration;

    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow(Carbon::create(2024, 1, 1, 12, 0, 0));

        $this->hydration = new HydrationService;
        $this->config = $this->app->make(NemesisConfigInterface::class);
        $this->service = $this->app->make(NemesisService::class);
        $this->authService = $this->app->make(NemesisAuthenticationService::class);

        $this->user = TestUser::create([
            'name' => 'John Doe',
            'email' => 'john@example.com',
        ]);

        // Register test routes with the middleware STRING (not instance)
        Route::middleware('nemesis.token')->get('/test-protected', function () {
            return response()->json(['message' => 'OK']);
        });

        Route::middleware('nemesis.token:read')->get('/test-ability', function () {
            return response()->json(['message' => 'OK']);
        });

        Route::middleware('nemesis.token:admin')->get('/test-admin', function () {
            return response()->json(['message' => 'OK']);
        });
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function createTokenForUser(
        ?string $name = null,
        ?string $source = null,
        ?DateTimeVO $expiresAt = null
    ): array {
        $record = $this->hydration->hydrate(NemesisTokenRecord::class, [
            'name' => $name ?? 'Test Token',
            'source' => $source ?? 'web',
            'expires_at' => $expiresAt,
        ]);

        return $this->service->createWithPlainToken($record, $this->user);
    }

    private function createTokenWithAbilitiesForUser(array $abilities): array
    {
        $record = $this->hydration->hydrate(NemesisTokenRecord::class, [
            'name' => 'API Token',
            'source' => 'api',
            'abilities' => $abilities,
        ]);

        return $this->service->createWithPlainToken($record, $this->user);
    }

    private function createTokenWithAllowedOriginsForUser(array $origins): array
    {
        $record = $this->hydration->hydrate(NemesisTokenRecord::class, [
            'name' => 'API Token',
            'source' => 'api',
            'allowed_origins' => $origins,
        ]);

        return $this->service->createWithPlainToken($record, $this->user);
    }

    // ============================================================================
    // Authentication Success Tests
    // ============================================================================

    public function test_middleware_allows_request_with_valid_token(): void
    {
        [$token, $plainToken] = $this->createTokenForUser();

        $response = $this->get('/test-protected', [
            'Authorization' => 'Bearer '.$plainToken,
        ]);

        $response->assertStatus(200);
        $response->assertJson(['message' => 'OK']);
    }

    public function test_middleware_attaches_token_to_request(): void
    {
        [$token, $plainToken] = $this->createTokenForUser();

        $response = $this->get('/test-protected', [
            'Authorization' => 'Bearer '.$plainToken,
        ]);

        $response->assertStatus(200);
    }

    public function test_middleware_attaches_formatted_authenticatable_when_implemented(): void
    {
        [$token, $plainToken] = $this->createTokenForUser();

        $response = $this->get('/test-protected', [
            'Authorization' => 'Bearer '.$plainToken,
        ]);

        $response->assertStatus(200);
    }

    // ============================================================================
    // Authentication Failure Tests
    // ============================================================================

    public function test_middleware_returns_401_when_no_token_provided(): void
    {
        $response = $this->get('/test-protected');

        $response->assertStatus(401);
        $response->assertJsonStructure(['errorCode', 'message', 'status']);
    }

    public function test_middleware_returns_401_when_invalid_token_provided(): void
    {
        $response = $this->get('/test-protected', [
            'Authorization' => 'Bearer invalid-token',
        ]);

        $response->assertStatus(401);
    }

    public function test_middleware_returns_401_when_token_expired(): void
    {
        $expiredDate = new DateTimeVO(Carbon::getTestNow()->subDay()->format('Y-m-d\TH:i:sP'));

        [$token, $plainToken] = $this->createTokenForUser(expiresAt: $expiredDate);

        $response = $this->get('/test-protected', [
            'Authorization' => 'Bearer '.$plainToken,
        ]);

        $response->assertStatus(401);
    }

    // ============================================================================
    // Ability Check Tests
    // ============================================================================

    public function test_middleware_allows_request_with_required_ability(): void
    {
        [$token, $plainToken] = $this->createTokenWithAbilitiesForUser(['read', 'write']);

        $response = $this->get('/test-ability', [
            'Authorization' => 'Bearer '.$plainToken,
        ]);

        $response->assertStatus(200);
        $response->assertJson(['message' => 'OK']);
    }

    public function test_middleware_returns_403_when_token_lacks_required_ability(): void
    {
        [$token, $plainToken] = $this->createTokenWithAbilitiesForUser(['read', 'write']);

        $response = $this->get('/test-admin', [
            'Authorization' => 'Bearer '.$plainToken,
        ]);

        $response->assertStatus(403);
    }

    // ============================================================================
    // CORS Origin Tests
    // ============================================================================

    public function test_middleware_allows_request_when_origin_allowed(): void
    {
        [$token, $plainToken] = $this->createTokenWithAllowedOriginsForUser(['https://allowed.com']);

        $response = $this->get('/test-protected', [
            'Authorization' => 'Bearer '.$plainToken,
            'Origin' => 'https://allowed.com',
        ]);

        $response->assertStatus(200);
    }

    public function test_middleware_returns_403_when_origin_not_allowed(): void
    {
        [$token, $plainToken] = $this->createTokenWithAllowedOriginsForUser(['https://allowed.com']);

        $response = $this->get('/test-protected', [
            'Authorization' => 'Bearer '.$plainToken,
            'Origin' => 'https://evil.com',
        ]);

        $response->assertStatus(403);
    }

    // ============================================================================
    // Custom Header Tests
    // ============================================================================

    public function test_middleware_accepts_token_in_custom_header(): void
    {
        $originalTokenHeader = config('nemesis.middleware.token_header');
        $originalValidateOrigin = config('nemesis.middleware.validate_origin');

        config()->set('nemesis.middleware.token_header', 'X-API-Key');
        config()->set('nemesis.middleware.validate_origin', false);

        // Rafraîchir la config dans le conteneur
        $this->app->forgetInstance(NemesisConfigInterface::class);
        $this->app->make(NemesisConfigInterface::class);

        [$token, $plainToken] = $this->createTokenForUser();

        Route::get('/test-custom-header', function () {
            return response()->json(['message' => 'OK']);
        });

        $response = $this->get('/test-custom-header', [], [
            'X-API-Key' => $plainToken,
        ]);

        $response->assertStatus(200);
        $response->assertJson(['message' => 'OK']);

        // Restaurer la config
        config()->set('nemesis.middleware.token_header', $originalTokenHeader);
        config()->set('nemesis.middleware.validate_origin', $originalValidateOrigin);

        // Recréer l'instance de config dans le conteneur
        $this->app->forgetInstance(NemesisConfigInterface::class);
        $this->app->make(NemesisConfigInterface::class);
    }

    // ============================================================================
    // Security Headers Tests
    // ============================================================================

    public function test_middleware_applies_security_headers(): void
    {
        [$token, $plainToken] = $this->createTokenForUser();

        $response = $this->get('/test-protected', [
            'Authorization' => 'Bearer '.$plainToken,
        ]);

        $response->assertStatus(200);
    }

    // ============================================================================
    // CORS Headers Tests
    // ============================================================================

    public function test_middleware_applies_cors_headers_when_origin_validated(): void
    {
        [$token, $plainToken] = $this->createTokenForUser();

        $response = $this->get('/test-protected', [
            'Authorization' => 'Bearer '.$plainToken,
            'Origin' => 'https://example.com',
        ]);

        $response->assertStatus(200);
    }

    // ============================================================================
    // Preflight Request Tests
    // ============================================================================

    public function test_middleware_handles_preflight_request(): void
    {
        [$token, $plainToken] = $this->createTokenForUser();

        $response = $this->call('OPTIONS', '/test-protected', [], [], [], [
            'HTTP_ORIGIN' => 'https://example.com',
            'HTTP_ACCESS_CONTROL_REQUEST_METHOD' => 'GET',
            'HTTP_AUTHORIZATION' => 'Bearer '.$plainToken,
        ]);

        $response->assertStatus(200);
    }

    // ============================================================================
    // Edge Cases Tests
    // ============================================================================

    public function test_middleware_handles_token_with_nonexistent_tokenable_type(): void
    {
        $token = NemesisToken::create([
            'token_hash' => hash('sha256', 'bad-token'),
            'tokenable_type' => 'NonExistent\\Model\\Class',
            'tokenable_id' => $this->user->id,
            'name' => 'Bad Token',
            'source' => 'web',
        ]);

        $response = $this->get('/test-protected', [
            'Authorization' => 'Bearer bad-token',
        ]);

        $response->assertStatus(401);
    }

    public function test_middleware_handles_token_with_deleted_tokenable(): void
    {
        $user = TestUser::create(['name' => 'Temp', 'email' => 'temp@example.com']);

        $record = $this->hydration->hydrate(NemesisTokenRecord::class, ['name' => 'Temp Token']);
        [$token, $plainToken] = $this->service->createWithPlainToken($record, $user);

        $user->delete();

        $response = $this->get('/test-protected', [
            'Authorization' => 'Bearer '.$plainToken,
        ]);

        $response->assertStatus(401);
    }

    public function test_middleware_updates_last_used_on_successful_authentication(): void
    {
        [$token, $plainToken] = $this->createTokenForUser();

        $this->assertNull($token->last_used_at);

        $response = $this->get('/test-protected', [
            'Authorization' => 'Bearer '.$plainToken,
        ]);

        $token->refresh();

        $response->assertStatus(200);
        $this->assertNotNull($token->last_used_at);
    }
}
