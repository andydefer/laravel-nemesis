<?php

// tests/Integration/Http/Middleware/NemesisWebVerifiedMiddlewareTest.php

declare(strict_types=1);

namespace AndyDefer\Nemesis\Tests\Integration\Http\Middleware;

use AndyDefer\DomainStructures\Services\HydrationService;
use AndyDefer\Nemesis\Contracts\Configs\NemesisConfigInterface;
use AndyDefer\Nemesis\Contracts\Services\CookieTokenStorageInterface;
use AndyDefer\Nemesis\Records\NemesisTokenRecord;
use AndyDefer\Nemesis\Services\NemesisService;
use AndyDefer\Nemesis\Tests\Fixtures\Models\TestCheckPoint;
use AndyDefer\Nemesis\Tests\Fixtures\Models\TestUser;
use AndyDefer\Nemesis\Tests\IntegrationTestCase;
use AndyDefer\PhpVo\ValueObjects\DateTimeVO;
use Carbon\Carbon;
use Illuminate\Support\Facades\Route;

final class NemesisWebVerifiedMiddlewareTest extends IntegrationTestCase
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
            'email_verified_at' => null,
        ]);

        // Register test routes with the web verified middleware
        Route::middleware('nemesis.web.verified')->get('/web-verified-protected', function () {
            return response()->json(['message' => 'OK']);
        });

        Route::get('/login', function () {
            return response()->json(['message' => 'Login page']);
        })->name('login.show');

        Route::get('/verify-email', function () {
            return response()->json(['message' => 'Verify email page']);
        })->name('verify.email');
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

    private function createTokenForCheckPoint(TestCheckPoint $checkpoint): array
    {
        $record = $this->hydration->hydrate(NemesisTokenRecord::class, [
            'name' => 'CheckPoint Token',
            'source' => 'web',
        ]);

        return $this->service->createWithPlainToken($record, $checkpoint);
    }

    // ============================================================================
    // Authentication Success Tests
    // ============================================================================

    public function test_middleware_allows_request_with_verified_email(): void
    {
        // Arrange: Create a user with verified email
        $this->user->email_verified_at = Carbon::getTestNow();
        $this->user->save();

        [$token, $plainToken] = $this->createTokenForUser();

        // Act: Make a request with the token in the cookie
        $response = $this->withUnencryptedCookie('nemesis_token', $plainToken)
            ->get('/web-verified-protected');

        // Assert: The request should be successful
        $response->assertStatus(200);
        $response->assertJson(['message' => 'OK']);
    }

    public function test_middleware_redirects_to_verification_when_email_not_verified(): void
    {
        // Arrange: Create a user with unverified email
        $this->user->email_verified_at = null;
        $this->user->save();

        [$token, $plainToken] = $this->createTokenForUser();

        // Act: Make a request with the token in the cookie
        $response = $this->withUnencryptedCookie('nemesis_token', $plainToken)
            ->get('/web-verified-protected');

        // Assert: The request should be redirected to verification
        $response->assertStatus(302);
        $response->assertRedirect('/verify-email');
    }

    // ============================================================================
    // Authentication Failure Tests
    // ============================================================================

    public function test_middleware_redirects_to_login_when_no_token_in_cookie(): void
    {
        // Act: Make a request without a token cookie
        $response = $this->get('/web-verified-protected');

        // Assert: The request should be redirected to login
        $response->assertStatus(302);
        $response->assertRedirect('/login');
    }

    public function test_middleware_redirects_to_login_when_invalid_token_in_cookie(): void
    {
        // Act: Make a request with an invalid token in the cookie
        $response = $this->withUnencryptedCookie('nemesis_token', 'invalid-token')
            ->get('/web-verified-protected');

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
            ->get('/web-verified-protected');

        // Assert: The request should be redirected to login
        $response->assertStatus(302);
        $response->assertRedirect('/login');
    }

    public function test_middleware_redirects_to_login_when_token_revoked(): void
    {
        // Arrange: Create a token and revoke it
        [$token, $plainToken] = $this->createTokenForUser();
        $this->service->revoke($token);

        // Act: Make a request with the revoked token
        $response = $this->withUnencryptedCookie('nemesis_token', $plainToken)
            ->get('/web-verified-protected');

        // Assert: The request should be redirected to login
        $response->assertStatus(302);
        $response->assertRedirect('/login');
    }

    /**
     * Test that the middleware returns 500 when model is missing email_verified_at field.
     * The middleware uses Schema::hasColumn() and aborts with 500 if column doesn't exist.
     */
    public function test_middleware_returns_500_when_model_missing_email_verified_at(): void
    {
        // Arrange: Create a CheckPoint (model without email_verified_at column)
        $checkpoint = TestCheckPoint::create([
            'name' => 'Portique A',
            'location' => 'Entrée principale',
            'is_active' => true,
        ]);

        [$token, $plainToken] = $this->createTokenForCheckPoint($checkpoint);

        // Act: Make a request with the token
        $response = $this->withUnencryptedCookie('nemesis_token', $plainToken)
            ->get('/web-verified-protected');

        // Assert: The request should return 500

        $response->assertStatus(500);
    }

    public function test_middleware_redirects_to_login_when_tokenable_not_found(): void
    {
        // Arrange: Create a user, create a token, then delete the user
        $user = TestUser::create(['name' => 'Temp', 'email' => 'temp@example.com']);

        $record = $this->hydration->hydrate(NemesisTokenRecord::class, ['name' => 'Temp Token']);
        [$token, $plainToken] = $this->service->createWithPlainToken($record, $user);

        $user->delete();

        // Act: Make a request with the token
        $response = $this->withUnencryptedCookie('nemesis_token', $plainToken)
            ->get('/web-verified-protected');

        // Assert: The request should be redirected to login
        $response->assertStatus(302);
        $response->assertRedirect('/login');
    }

    public function test_middleware_redirects_to_configured_verification_route(): void
    {
        // Arrange: Change the verification route configuration
        config()->set('nemesis.web.verification_route', '/custom-verify');

        $this->app->forgetInstance(NemesisConfigInterface::class);
        $this->app->make(NemesisConfigInterface::class);

        Route::get('/custom-verify', function () {
            return response()->json(['message' => 'Custom Verify']);
        })->name('custom.verify');

        $this->user->email_verified_at = null;
        $this->user->save();

        [$token, $plainToken] = $this->createTokenForUser();

        // Act: Make a request with the token
        $response = $this->withUnencryptedCookie('nemesis_token', $plainToken)
            ->get('/web-verified-protected');

        // Assert: The request should be redirected to the custom verification route
        $response->assertStatus(302);
        $response->assertRedirect('/custom-verify');

        // Cleanup: Restore the original configuration
        config()->set('nemesis.web.verification_route', '/verify-email');
        $this->app->forgetInstance(NemesisConfigInterface::class);
        $this->app->make(NemesisConfigInterface::class);
    }

    public function test_middleware_updates_last_used_on_successful_authentication(): void
    {
        // Arrange: Create a user with verified email
        $this->user->email_verified_at = Carbon::getTestNow();
        $this->user->save();

        [$token, $plainToken] = $this->createTokenForUser();
        $this->assertNull($token->last_used_at);

        // Act: Make a request with the token
        $response = $this->withUnencryptedCookie('nemesis_token', $plainToken)
            ->get('/web-verified-protected');

        // Assert: The token should be updated with a last_used_at timestamp
        $token->refresh();
        $response->assertStatus(200);
        $this->assertNotNull($token->last_used_at);
    }

    public function test_middleware_handles_multiple_requests_with_same_token(): void
    {
        // Arrange: Create a user with verified email
        $this->user->email_verified_at = Carbon::getTestNow();
        $this->user->save();

        [$token, $plainToken] = $this->createTokenForUser();

        // Act & Assert: Make multiple requests with the same token
        $response1 = $this->withUnencryptedCookie('nemesis_token', $plainToken)
            ->get('/web-verified-protected');
        $response1->assertStatus(200);

        $response2 = $this->withUnencryptedCookie('nemesis_token', $plainToken)
            ->get('/web-verified-protected');
        $response2->assertStatus(200);

        $response3 = $this->withUnencryptedCookie('nemesis_token', $plainToken)
            ->get('/web-verified-protected');
        $response3->assertStatus(200);
    }
}
