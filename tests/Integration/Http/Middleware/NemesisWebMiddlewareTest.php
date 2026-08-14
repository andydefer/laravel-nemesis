<?php

// tests/Integration/Http/Middleware/NemesisWebMiddlewareTest.php

declare(strict_types=1);

namespace AndyDefer\Nemesis\Tests\Integration\Http\Middleware;

use AndyDefer\DomainStructures\Services\HydrationService;
use AndyDefer\Nemesis\Contracts\Configs\NemesisConfigInterface;
use AndyDefer\Nemesis\Contracts\Services\CookieTokenStorageInterface;
use AndyDefer\Nemesis\Models\NemesisToken;
use AndyDefer\Nemesis\Records\NemesisTokenRecord;
use AndyDefer\Nemesis\Services\NemesisService;
use AndyDefer\Nemesis\Tests\Fixtures\Models\TestUser;
use AndyDefer\Nemesis\Tests\IntegrationTestCase;
use AndyDefer\PhpVo\ValueObjects\DateTimeVO;
use Carbon\Carbon;
use Illuminate\Support\Facades\Route;

final class NemesisWebMiddlewareTest extends IntegrationTestCase
{
    private TestUser $user;

    private NemesisConfigInterface $config;

    private NemesisService $service;

    private CookieTokenStorageInterface $cookieTokenStorage;

    private HydrationService $hydration;

    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow(Carbon::create(2024, 1, 1, 12, 0, 0));

        $this->hydration = new HydrationService;
        $this->config = $this->app->make(NemesisConfigInterface::class);
        $this->service = $this->app->make(NemesisService::class);
        $this->cookieTokenStorage = $this->app->make(CookieTokenStorageInterface::class);

        $this->user = TestUser::create([
            'name' => 'John Doe',
            'email' => 'john@example.com',
        ]);

        // Register test routes with the web middleware
        Route::middleware('nemesis.web')->get('/web-protected', function () {
            return response()->json(['message' => 'OK']);
        });

        Route::middleware('nemesis.web:read')->get('/web-ability', function () {
            return response()->json(['message' => 'OK']);
        });

        Route::middleware('nemesis.web:admin')->get('/web-admin', function () {
            return response()->json(['message' => 'OK']);
        });

        Route::get('/login', function () {
            return response()->json(['message' => 'Login page']);
        })->name('login.show');
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
            'name' => $name ?? 'Web Token',
            'source' => $source ?? 'web',
            'expires_at' => $expiresAt,
        ]);

        return $this->service->createWithPlainToken($record, $this->user);
    }

    private function createTokenWithAbilitiesForUser(array $abilities): array
    {
        $record = $this->hydration->hydrate(NemesisTokenRecord::class, [
            'name' => 'Web Token',
            'source' => 'web',
            'abilities' => $abilities,
        ]);

        return $this->service->createWithPlainToken($record, $this->user);
    }

    // ============================================================================
    // Authentication Success Tests
    // ============================================================================

    public function test_middleware_allows_request_with_valid_token_in_cookie(): void
    {
        // Arrange: Create a valid token and store it in a cookie
        [$token, $plainToken] = $this->createTokenForUser();

        // Act: Make a request with the token in the cookie
        $response = $this->withUnencryptedCookie('nemesis_token', $plainToken)
            ->get('/web-protected');

        // Assert: The request should be successful
        $response->assertStatus(200);
        $response->assertJson(['message' => 'OK']);
    }

    public function test_middleware_allows_request_without_ability(): void
    {
        // Arrange: Create a valid token
        [$token, $plainToken] = $this->createTokenForUser();

        // Act: Make a request with no ability requirement
        $response = $this->withUnencryptedCookie('nemesis_token', $plainToken)
            ->get('/web-protected');

        // Assert: The request should be successful
        $response->assertStatus(200);
        $response->assertJson(['message' => 'OK']);
    }

    public function test_middleware_allows_request_with_abilities_but_no_requirement(): void
    {
        // Arrange: Create a token with abilities
        [$token, $plainToken] = $this->createTokenWithAbilitiesForUser(['read']);

        // Act: Make a request with no ability requirement
        $response = $this->withUnencryptedCookie('nemesis_token', $plainToken)
            ->get('/web-protected');

        // Assert: The request should be successful
        $response->assertStatus(200);
        $response->assertJson(['message' => 'OK']);
    }

    // ============================================================================
    // Authentication Failure Tests
    // ============================================================================

    public function test_middleware_redirects_to_login_when_no_token_in_cookie(): void
    {
        // Act: Make a request without a token cookie
        $response = $this->get('/web-protected');

        // Assert: The request should be redirected to login
        $response->assertStatus(302);
        $response->assertRedirect('/login');
    }

    public function test_middleware_redirects_to_login_when_invalid_token_in_cookie(): void
    {
        // Act: Make a request with an invalid token in the cookie
        $response = $this->withUnencryptedCookie('nemesis_token', 'invalid-token')
            ->get('/web-protected');

        // Assert: The request should be redirected to login
        $response->assertStatus(302);
        $response->assertRedirect('/login');
    }

    public function test_middleware_redirects_to_login_when_cookie_token_not_in_database(): void
    {
        // Act: Make a request with a token not in the database
        $response = $this->withUnencryptedCookie('nemesis_token', 'token_not_in_database')
            ->get('/web-protected');

        // Assert: The request should be redirected to login
        $response->assertStatus(302);
        $response->assertRedirect('/login');
    }

    public function test_middleware_redirects_to_login_when_token_expired(): void
    {
        // Arrange: Create an expired token
        $expiredDate = new DateTimeVO(Carbon::getTestNow()->subDay()->format('Y-m-d\TH:i:sP'));
        [$token, $plainToken] = $this->createTokenForUser(expiresAt: $expiredDate);

        // Act: Make a request with the expired token
        $response = $this->withUnencryptedCookie('nemesis_token', $plainToken)
            ->get('/web-protected');

        // Assert: The request should be redirected to login
        $response->assertStatus(302);
        $response->assertRedirect('/login');
    }

    public function test_middleware_redirects_to_configured_login_route(): void
    {
        // Arrange: Change the login route configuration
        config()->set('nemesis.web.login_route', '/custom-login');

        $this->app->forgetInstance(NemesisConfigInterface::class);
        $this->app->make(NemesisConfigInterface::class);

        Route::get('/custom-login', function () {
            return response()->json(['message' => 'Custom Login']);
        })->name('custom.login');

        // Act: Make a request without a token
        $response = $this->get('/web-protected');

        // Assert: The request should be redirected to the custom login route
        $response->assertStatus(302);
        $response->assertRedirect('/custom-login');

        // Cleanup: Restore the original configuration
        config()->set('nemesis.web.login_route', '/login');
        $this->app->forgetInstance(NemesisConfigInterface::class);
        $this->app->make(NemesisConfigInterface::class);
    }

    // ============================================================================
    // Ability Check Tests
    // ============================================================================

    public function test_middleware_allows_request_with_required_ability(): void
    {
        // Arrange: Create a token with the required abilities
        [$token, $plainToken] = $this->createTokenWithAbilitiesForUser(['read', 'write']);

        // Act: Make a request requiring the 'read' ability
        $response = $this->withUnencryptedCookie('nemesis_token', $plainToken)
            ->get('/web-ability');

        // Assert: The request should be successful
        $response->assertStatus(200);
        $response->assertJson(['message' => 'OK']);
    }

    public function test_middleware_allows_request_with_multiple_abilities(): void
    {
        // Arrange: Create a token with multiple abilities including 'admin'
        [$token, $plainToken] = $this->createTokenWithAbilitiesForUser(['read', 'write', 'admin']);

        // Act: Make a request requiring the 'admin' ability
        $response = $this->withUnencryptedCookie('nemesis_token', $plainToken)
            ->get('/web-admin');

        // Assert: The request should be successful
        $response->assertStatus(200);
        $response->assertJson(['message' => 'OK']);
    }

    public function test_middleware_returns_403_when_token_lacks_required_ability(): void
    {
        // Arrange: Create a token without the required ability
        [$token, $plainToken] = $this->createTokenWithAbilitiesForUser(['read', 'write']);

        // Act: Make a request requiring the 'admin' ability
        $response = $this->withUnencryptedCookie('nemesis_token', $plainToken)
            ->get('/web-admin');

        // Assert: The request should be forbidden
        $response->assertStatus(403);
    }

    public function test_middleware_returns_403_when_token_has_no_abilities_and_ability_required(): void
    {
        // Arrange: Create a token without abilities
        [$token, $plainToken] = $this->createTokenForUser();

        // Act: Make a request requiring the 'admin' ability
        $response = $this->withUnencryptedCookie('nemesis_token', $plainToken)
            ->get('/web-admin');

        // Assert: The request should be forbidden
        $response->assertStatus(403);
    }

    // ============================================================================
    // Cookie Configuration Tests
    // ============================================================================

    public function test_middleware_uses_configured_cookie_name(): void
    {
        // Arrange: Change the cookie name configuration
        config()->set('nemesis.web.cookie_name', 'custom_auth_token');

        $this->app->forgetInstance(NemesisConfigInterface::class);
        $this->app->make(NemesisConfigInterface::class);

        [$token, $plainToken] = $this->createTokenForUser();

        // Act: Make a request with the token in the custom cookie
        $response = $this->withUnencryptedCookie('custom_auth_token', $plainToken)
            ->get('/web-protected');

        // Assert: The request should be successful
        $response->assertStatus(200);
        $response->assertJson(['message' => 'OK']);

        // Cleanup: Restore the original configuration
        config()->set('nemesis.web.cookie_name', 'nemesis_token');
        $this->app->forgetInstance(NemesisConfigInterface::class);
        $this->app->make(NemesisConfigInterface::class);
    }

    public function test_middleware_uses_configured_cookie_name_with_ability(): void
    {
        // Arrange: Change the cookie name configuration
        config()->set('nemesis.web.cookie_name', 'custom_auth_token');

        $this->app->forgetInstance(NemesisConfigInterface::class);
        $this->app->make(NemesisConfigInterface::class);

        [$token, $plainToken] = $this->createTokenWithAbilitiesForUser(['read']);

        // Act: Make a request with the token in the custom cookie
        $response = $this->withUnencryptedCookie('custom_auth_token', $plainToken)
            ->get('/web-ability');

        // Assert: The request should be successful
        $response->assertStatus(200);
        $response->assertJson(['message' => 'OK']);

        // Cleanup: Restore the original configuration
        config()->set('nemesis.web.cookie_name', 'nemesis_token');
        $this->app->forgetInstance(NemesisConfigInterface::class);
        $this->app->make(NemesisConfigInterface::class);
    }

    // ============================================================================
    // Multiple Requests Tests
    // ============================================================================

    public function test_middleware_handles_multiple_requests_with_same_token(): void
    {
        // Arrange: Create a valid token
        [$token, $plainToken] = $this->createTokenForUser();

        // Act & Assert: Make multiple requests with the same token
        $response1 = $this->withUnencryptedCookie('nemesis_token', $plainToken)
            ->get('/web-protected');
        $response1->assertStatus(200);

        $response2 = $this->withUnencryptedCookie('nemesis_token', $plainToken)
            ->get('/web-protected');
        $response2->assertStatus(200);

        $response3 = $this->withUnencryptedCookie('nemesis_token', $plainToken)
            ->get('/web-protected');
        $response3->assertStatus(200);
    }

    // ============================================================================
    // Edge Cases Tests
    // ============================================================================

    public function test_middleware_handles_token_with_nonexistent_tokenable_type(): void
    {
        // Arrange: Create a token with a non-existent tokenable type
        NemesisToken::create([
            'token_hash' => hash('sha256', 'bad-token'),
            'tokenable_type' => 'NonExistent\\Model\\Class',
            'tokenable_id' => 999,
            'name' => 'Bad Token',
            'source' => 'web',
        ]);

        // Act: Make a request with the invalid token
        $response = $this->withUnencryptedCookie('nemesis_token', 'bad-token')
            ->get('/web-protected');

        // Assert: The request should be redirected to login
        $response->assertStatus(302);
        $response->assertRedirect('/login');
    }

    public function test_middleware_handles_token_with_deleted_tokenable(): void
    {
        // Arrange: Create a user, create a token, then delete the user
        $user = TestUser::create(['name' => 'Temp', 'email' => 'temp@example.com']);

        $record = $this->hydration->hydrate(NemesisTokenRecord::class, ['name' => 'Temp Token']);
        [$token, $plainToken] = $this->service->createWithPlainToken($record, $user);

        $user->delete();

        // Act: Make a request with the token
        $response = $this->withUnencryptedCookie('nemesis_token', $plainToken)
            ->get('/web-protected');

        // Assert: The request should be redirected to login
        $response->assertStatus(302);
        $response->assertRedirect('/login');
    }

    public function test_middleware_redirects_to_login_when_deleted_tokenable_with_ability(): void
    {
        // Arrange: Create a user with abilities, create a token, then delete the user
        $user = TestUser::create(['name' => 'Temp', 'email' => 'temp@example.com']);

        $record = $this->hydration->hydrate(NemesisTokenRecord::class, [
            'name' => 'Temp Token',
            'abilities' => ['read'],
        ]);
        [$token, $plainToken] = $this->service->createWithPlainToken($record, $user);

        $user->delete();

        // Act: Make a request requiring the 'read' ability
        $response = $this->withUnencryptedCookie('nemesis_token', $plainToken)
            ->get('/web-ability');

        // Assert: The request should be redirected to login
        $response->assertStatus(302);
        $response->assertRedirect('/login');
    }

    public function test_middleware_redirects_to_login_when_expired_token_with_ability(): void
    {
        // Arrange: Create an expired token with abilities
        $expiredDate = new DateTimeVO(Carbon::getTestNow()->subDay()->format('Y-m-d\TH:i:sP'));

        $record = $this->hydration->hydrate(NemesisTokenRecord::class, [
            'name' => 'Expired Token',
            'abilities' => ['read'],
            'expires_at' => $expiredDate,
        ]);
        [$token, $plainToken] = $this->service->createWithPlainToken($record, $this->user);

        // Act: Make a request requiring the 'read' ability
        $response = $this->withUnencryptedCookie('nemesis_token', $plainToken)
            ->get('/web-ability');

        // Assert: The request should be redirected to login
        $response->assertStatus(302);
        $response->assertRedirect('/login');
    }

    public function test_middleware_redirects_to_login_when_revoked_token_with_ability(): void
    {
        // Arrange: Create a token with abilities and revoke it
        [$token, $plainToken] = $this->createTokenWithAbilitiesForUser(['read']);
        $this->service->revoke($token);

        // Act: Make a request requiring the 'read' ability
        $response = $this->withUnencryptedCookie('nemesis_token', $plainToken)
            ->get('/web-ability');

        // Assert: The request should be redirected to login
        $response->assertStatus(302);
        $response->assertRedirect('/login');
    }

    public function test_middleware_redirects_to_configured_login_route_with_ability(): void
    {
        // Arrange: Change the login route configuration
        config()->set('nemesis.web.login_route', '/custom-login');

        $this->app->forgetInstance(NemesisConfigInterface::class);
        $this->app->make(NemesisConfigInterface::class);

        Route::get('/custom-login', function () {
            return response()->json(['message' => 'Custom Login']);
        })->name('custom.login');

        // Act: Make a request with an invalid token
        $response = $this->withUnencryptedCookie('nemesis_token', 'invalid-token')
            ->get('/web-ability');

        // Assert: The request should be redirected to the custom login route
        $response->assertStatus(302);
        $response->assertRedirect('/custom-login');

        // Cleanup: Restore the original configuration
        config()->set('nemesis.web.login_route', '/login');
        $this->app->forgetInstance(NemesisConfigInterface::class);
        $this->app->make(NemesisConfigInterface::class);
    }

    // ============================================================================
    // Last Used Update Tests
    // ============================================================================

    public function test_middleware_updates_last_used_on_successful_authentication(): void
    {
        // Arrange: Create a token and verify last_used_at is null
        [$token, $plainToken] = $this->createTokenForUser();
        $this->assertNull($token->last_used_at);

        // Act: Make a request with the token
        $response = $this->withUnencryptedCookie('nemesis_token', $plainToken)
            ->get('/web-protected');

        // Assert: The token should be updated with a last_used_at timestamp
        $token->refresh();
        $response->assertStatus(200);
        $this->assertNotNull($token->last_used_at);
    }

    public function test_middleware_updates_last_used_with_ability(): void
    {
        // Arrange: Create a token with abilities and verify last_used_at is null
        [$token, $plainToken] = $this->createTokenWithAbilitiesForUser(['read']);
        $this->assertNull($token->last_used_at);

        // Act: Make a request requiring the 'read' ability
        $response = $this->withUnencryptedCookie('nemesis_token', $plainToken)
            ->get('/web-ability');

        // Assert: The token should be updated with a last_used_at timestamp
        $token->refresh();
        $response->assertStatus(200);
        $this->assertNotNull($token->last_used_at);
    }

    // ============================================================================
    // Revoked Token Tests
    // ============================================================================

    public function test_middleware_redirects_to_login_when_token_revoked(): void
    {
        // Arrange: Create a token and revoke it
        [$token, $plainToken] = $this->createTokenForUser();
        $this->service->revoke($token);

        // Act: Make a request with the revoked token
        $response = $this->withUnencryptedCookie('nemesis_token', $plainToken)
            ->get('/web-protected');

        // Assert: The request should be redirected to login
        $response->assertStatus(302);
        $response->assertRedirect('/login');
    }

    // ============================================================================
    // Token Without Abilities Tests
    // ============================================================================

    public function test_middleware_allows_request_when_token_has_no_abilities_and_no_requirement(): void
    {
        // Arrange: Create a token without abilities
        [$token, $plainToken] = $this->createTokenForUser();

        // Act: Make a request with no ability requirement
        $response = $this->withUnencryptedCookie('nemesis_token', $plainToken)
            ->get('/web-protected');

        // Assert: The request should be successful
        $response->assertStatus(200);
        $response->assertJson(['message' => 'OK']);
    }
}
