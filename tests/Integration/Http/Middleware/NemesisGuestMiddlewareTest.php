<?php

// tests/Integration/Http/Middleware/NemesisGuestMiddlewareTest.php

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

final class NemesisGuestMiddlewareTest extends IntegrationTestCase
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

        // Register test routes with the guest middleware
        Route::middleware('nemesis.guest')->get('/guest-only', function () {
            return response()->json(['message' => 'Guest Access']);
        });

        Route::middleware('nemesis.guest:admin')->get('/guest-ability', function () {
            return response()->json(['message' => 'Guest Access']);
        });

        Route::get('/dashboard', function () {
            return response()->json(['message' => 'Dashboard']);
        })->name('dashboard');
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
    // Guest Access Tests
    // ============================================================================

    public function test_middleware_allows_guest_access_without_token(): void
    {
        // Act: Make a request without a token cookie
        $response = $this->get('/guest-only');

        // Assert: The request should be allowed
        $response->assertStatus(200);
        $response->assertJson(['message' => 'Guest Access']);
    }

    public function test_middleware_redirects_to_dashboard_with_valid_token(): void
    {
        // Arrange: Create a valid token and store it in a cookie
        [$token, $plainToken] = $this->createTokenForUser();

        // Act: Make a request with a valid token
        $response = $this->withUnencryptedCookie('nemesis_token', $plainToken)
            ->get('/guest-only');

        // Assert: The request should be redirected to dashboard
        $response->assertStatus(302);
        $response->assertRedirect('/dashboard');
    }

    public function test_middleware_allows_guest_access_with_invalid_token(): void
    {
        // Act: Make a request with an invalid token
        $response = $this->withUnencryptedCookie('nemesis_token', 'invalid-token')
            ->get('/guest-only');

        // Assert: The request should be allowed (invalid token = guest)
        $response->assertStatus(200);
        $response->assertJson(['message' => 'Guest Access']);
    }

    public function test_middleware_allows_guest_access_with_expired_token(): void
    {
        // Arrange: Create an expired token
        $expiredDate = new DateTimeVO(Carbon::getTestNow()->subDay()->format('Y-m-d\TH:i:sP'));
        [$token, $plainToken] = $this->createTokenForUser(expiresAt: $expiredDate);

        // Act: Make a request with an expired token
        $response = $this->withUnencryptedCookie('nemesis_token', $plainToken)
            ->get('/guest-only');

        // Assert: The request should be allowed (expired token = guest)
        $response->assertStatus(200);
        $response->assertJson(['message' => 'Guest Access']);
    }

    public function test_middleware_allows_guest_access_with_revoked_token(): void
    {
        // Arrange: Create a token and revoke it
        [$token, $plainToken] = $this->createTokenForUser();
        $this->service->revoke($token);

        // Act: Make a request with a revoked token
        $response = $this->withUnencryptedCookie('nemesis_token', $plainToken)
            ->get('/guest-only');

        // Assert: The request should be allowed (revoked token = guest)
        $response->assertStatus(200);
        $response->assertJson(['message' => 'Guest Access']);
    }

    public function test_middleware_redirects_to_dashboard_with_token_and_ability(): void
    {
        // Arrange: Create a token with the 'admin' ability
        [$token, $plainToken] = $this->createTokenWithAbilitiesForUser(['admin']);

        // Act: Make a request to a route that blocks users with 'admin' ability
        $response = $this->withUnencryptedCookie('nemesis_token', $plainToken)
            ->get('/guest-ability');

        // Assert: The request should be redirected to dashboard
        $response->assertStatus(302);
        $response->assertRedirect('/dashboard');
    }

    public function test_middleware_allows_guest_access_with_token_without_ability(): void
    {
        // Arrange: Create a token without the 'admin' ability
        [$token, $plainToken] = $this->createTokenWithAbilitiesForUser(['read']);

        // Act: Make a request to a route that blocks users with 'admin' ability
        $response = $this->withUnencryptedCookie('nemesis_token', $plainToken)
            ->get('/guest-ability');

        // Assert: The request should be allowed (user doesn't have 'admin' ability)
        $response->assertStatus(200);
        $response->assertJson(['message' => 'Guest Access']);
    }

    public function test_middleware_redirects_to_dashboard_with_token_and_no_abilities_when_ability_required(): void
    {
        // Arrange: Create a token without abilities
        [$token, $plainToken] = $this->createTokenForUser();

        // Act: Make a request to a route that blocks users with 'admin' ability
        $response = $this->withUnencryptedCookie('nemesis_token', $plainToken)
            ->get('/guest-ability');

        // Assert: The request should be redirected to dashboard
        // (User is authenticated, even without specific ability)
        $response->assertStatus(302);
        $response->assertRedirect('/dashboard');
    }

    // ============================================================================
    // Configured Dashboard Route Tests
    // ============================================================================

    public function test_middleware_redirects_to_configured_dashboard_route(): void
    {
        // Arrange: Change the dashboard route configuration
        config()->set('nemesis.web.dashboard_route', '/custom-dashboard');

        $this->app->forgetInstance(NemesisConfigInterface::class);
        $this->app->make(NemesisConfigInterface::class);

        Route::get('/custom-dashboard', function () {
            return response()->json(['message' => 'Custom Dashboard']);
        })->name('custom.dashboard');

        [$token, $plainToken] = $this->createTokenForUser();

        // Act: Make a request with a valid token
        $response = $this->withUnencryptedCookie('nemesis_token', $plainToken)
            ->get('/guest-only');

        // Assert: The request should be redirected to the custom dashboard route
        $response->assertStatus(302);
        $response->assertRedirect('/custom-dashboard');

        // Cleanup: Restore the original configuration
        config()->set('nemesis.web.dashboard_route', '/dashboard');
        $this->app->forgetInstance(NemesisConfigInterface::class);
        $this->app->make(NemesisConfigInterface::class);
    }

    // ============================================================================
    // Edge Cases Tests
    // ============================================================================

    public function test_middleware_handles_token_with_deleted_tokenable(): void
    {
        // Arrange: Create a user, create a token, then delete the user
        $user = TestUser::create(['name' => 'Temp', 'email' => 'temp@example.com']);

        $record = $this->hydration->hydrate(NemesisTokenRecord::class, ['name' => 'Temp Token']);
        [$token, $plainToken] = $this->service->createWithPlainToken($record, $user);

        $user->delete();

        // Act: Make a request with the token from the deleted user
        $response = $this->withUnencryptedCookie('nemesis_token', $plainToken)
            ->get('/guest-only');

        // Assert: The request should be allowed (invalid tokenable = guest)
        $response->assertStatus(200);
        $response->assertJson(['message' => 'Guest Access']);
    }

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
            ->get('/guest-only');

        // Assert: The request should be allowed (invalid tokenable = guest)
        $response->assertStatus(200);
        $response->assertJson(['message' => 'Guest Access']);
    }

    public function test_middleware_redirects_to_dashboard_with_valid_token_and_cookie_name(): void
    {
        // Arrange: Change the cookie name configuration
        config()->set('nemesis.web.cookie_name', 'custom_auth_token');

        $this->app->forgetInstance(NemesisConfigInterface::class);
        $this->app->make(NemesisConfigInterface::class);

        [$token, $plainToken] = $this->createTokenForUser();

        // Act: Make a request with the token in the custom cookie
        $response = $this->withUnencryptedCookie('custom_auth_token', $plainToken)
            ->get('/guest-only');

        // Assert: The request should be redirected to dashboard
        $response->assertStatus(302);
        $response->assertRedirect('/dashboard');

        // Cleanup: Restore the original configuration
        config()->set('nemesis.web.cookie_name', 'nemesis_token');
        $this->app->forgetInstance(NemesisConfigInterface::class);
        $this->app->make(NemesisConfigInterface::class);
    }

    // ============================================================================
    // Multiple Requests Tests
    // ============================================================================

    public function test_middleware_handles_multiple_guest_requests_without_token(): void
    {
        // Act & Assert: Make multiple requests without a token
        $response1 = $this->get('/guest-only');
        $response1->assertStatus(200);

        $response2 = $this->get('/guest-only');
        $response2->assertStatus(200);

        $response3 = $this->get('/guest-only');
        $response3->assertStatus(200);
    }

    public function test_middleware_handles_multiple_guest_requests_with_valid_token(): void
    {
        // Arrange: Create a valid token
        [$token, $plainToken] = $this->createTokenForUser();

        // Act & Assert: Multiple requests with the same token should redirect
        $response1 = $this->withUnencryptedCookie('nemesis_token', $plainToken)
            ->get('/guest-only');
        $response1->assertStatus(302);

        $response2 = $this->withUnencryptedCookie('nemesis_token', $plainToken)
            ->get('/guest-only');
        $response2->assertStatus(302);

        $response3 = $this->withUnencryptedCookie('nemesis_token', $plainToken)
            ->get('/guest-only');
        $response3->assertStatus(302);
    }

    // ============================================================================
    // Token Last Used Update Tests
    // ============================================================================

    public function test_middleware_updates_last_used_on_authenticated_redirect(): void
    {
        // Arrange: Create a token and verify last_used_at is null
        [$token, $plainToken] = $this->createTokenForUser();
        $this->assertNull($token->last_used_at);

        // Act: Make a request with the token (should redirect)
        $response = $this->withUnencryptedCookie('nemesis_token', $plainToken)
            ->get('/guest-only');

        // Assert: The token should be updated with a last_used_at timestamp
        $token->refresh();
        $response->assertStatus(302);
        $this->assertNotNull($token->last_used_at);
    }

    public function test_middleware_does_not_update_last_used_on_invalid_token(): void
    {
        // Act: Make a request with an invalid token
        $response = $this->withUnencryptedCookie('nemesis_token', 'invalid-token')
            ->get('/guest-only');

        // Assert: The request should be allowed
        $response->assertStatus(200);
        $response->assertJson(['message' => 'Guest Access']);
    }
}
