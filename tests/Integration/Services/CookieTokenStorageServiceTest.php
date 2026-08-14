<?php

// tests/Integration/Services/CookieTokenStorageServiceTest.php

declare(strict_types=1);

namespace AndyDefer\Nemesis\Tests\Integration\Services;

use AndyDefer\DomainStructures\Services\HydrationService;
use AndyDefer\Nemesis\Contracts\Configs\NemesisConfigInterface;
use AndyDefer\Nemesis\Contracts\Services\CookieTokenStorageInterface;
use AndyDefer\Nemesis\Records\NemesisTokenRecord;
use AndyDefer\Nemesis\Services\NemesisService;
use AndyDefer\Nemesis\Tests\Fixtures\Models\TestUser;
use AndyDefer\Nemesis\Tests\IntegrationTestCase;
use AndyDefer\PhpVo\ValueObjects\DateTimeVO;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cookie;

final class CookieTokenStorageServiceTest extends IntegrationTestCase
{
    private CookieTokenStorageInterface $cookieTokenStorage;

    private NemesisService $nemesisService;

    private TestUser $user;

    private HydrationService $hydration;

    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow(Carbon::create(2024, 1, 1, 12, 0, 0));

        $this->hydration = new HydrationService;
        $this->cookieTokenStorage = $this->app->make(CookieTokenStorageInterface::class);
        $this->nemesisService = $this->app->make(NemesisService::class);

        $this->user = TestUser::create([
            'name' => 'John Doe',
            'email' => 'john@example.com',
        ]);
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

        return $this->nemesisService->createWithPlainToken($record, $this->user);
    }

    private function getQueuedCookieValue(string $cookieName): ?string
    {
        $queuedCookies = Cookie::getQueuedCookies();

        foreach ($queuedCookies as $queuedCookie) {
            if ($queuedCookie->getName() === $cookieName) {
                return $queuedCookie->getValue();
            }
        }

        return null;
    }

    private function getQueuedCookieExpiration(string $cookieName): ?int
    {
        $queuedCookies = Cookie::getQueuedCookies();

        foreach ($queuedCookies as $queuedCookie) {
            if ($queuedCookie->getName() === $cookieName) {
                return $queuedCookie->getExpiresTime();
            }
        }

        return null;
    }

    private function isQueuedCookieForgotten(string $cookieName): bool
    {
        $queuedCookies = Cookie::getQueuedCookies();

        foreach ($queuedCookies as $queuedCookie) {
            if ($queuedCookie->getName() === $cookieName) {
                return $queuedCookie->getExpiresTime() < time();
            }
        }

        return false;
    }

    // ============================================================================
    // Store Tests
    // ============================================================================

    public function test_store_creates_cookie_with_token(): void
    {
        // Arrange: Create a valid token
        [$token, $plainToken] = $this->createTokenForUser();

        // Act: Store the token in the cookie
        $this->cookieTokenStorage->store($plainToken);

        // Assert: The cookie should contain the token
        $cookieValue = $this->getQueuedCookieValue('nemesis_token');
        $this->assertEquals($plainToken, $cookieValue);
    }

    public function test_store_uses_configured_cookie_name(): void
    {
        // Arrange: Change the cookie name configuration
        config()->set('nemesis.web.cookie_name', 'custom_token');

        // Refresh the service container
        $this->app->forgetInstance(CookieTokenStorageInterface::class);
        $this->app->forgetInstance(NemesisConfigInterface::class);
        $cookieTokenStorage = $this->app->make(CookieTokenStorageInterface::class);

        [$token, $plainToken] = $this->createTokenForUser();

        // Act: Store the token using the custom cookie name
        $cookieTokenStorage->store($plainToken);

        // Assert: The cookie should use the custom name
        $cookieValue = $this->getQueuedCookieValue('custom_token');
        $this->assertEquals($plainToken, $cookieValue);

        // Cleanup: Restore the original configuration
        config()->set('nemesis.web.cookie_name', 'nemesis_token');
        $this->app->forgetInstance(CookieTokenStorageInterface::class);
        $this->app->forgetInstance(NemesisConfigInterface::class);
        $this->app->make(CookieTokenStorageInterface::class);
    }

    public function test_store_sets_secure_flag_from_config(): void
    {
        // Arrange: Get the web configuration
        $webConfig = $this->app->make(NemesisConfigInterface::class)->webConfig();

        // Assert: The secure flags should be set correctly
        $this->assertTrue($webConfig->cookie_secure);
        $this->assertTrue($webConfig->cookie_httponly);
        $this->assertEquals('lax', $webConfig->cookie_samesite);
    }

    // ============================================================================
    // Get Tests
    // ============================================================================

    public function test_get_returns_token_from_cookie(): void
    {
        // Arrange: Store a token in the cookie
        [$token, $plainToken] = $this->createTokenForUser();
        $this->cookieTokenStorage->store($plainToken);

        $request = Request::create('/', 'GET');
        $request->cookies->set('nemesis_token', $plainToken);

        // Act: Retrieve the token from the cookie
        $retrievedToken = $this->cookieTokenStorage->get($request);

        // Assert: The retrieved token should match the stored token
        $this->assertEquals($plainToken, $retrievedToken);
    }

    public function test_get_returns_null_when_cookie_not_present(): void
    {
        // Arrange: Create a request without a token cookie
        $request = Request::create('/', 'GET');

        // Act: Attempt to retrieve the token
        $retrievedToken = $this->cookieTokenStorage->get($request);

        // Assert: The result should be null
        $this->assertNull($retrievedToken);
    }

    // ============================================================================
    // Has Tests
    // ============================================================================

    public function test_has_returns_true_when_cookie_exists(): void
    {
        // Arrange: Store a token in the cookie
        [$token, $plainToken] = $this->createTokenForUser();
        $this->cookieTokenStorage->store($plainToken);

        $request = Request::create('/', 'GET');
        $request->cookies->set('nemesis_token', $plainToken);

        // Act: Check if the cookie exists
        $hasCookie = $this->cookieTokenStorage->has($request);

        // Assert: The cookie should exist
        $this->assertTrue($hasCookie);
    }

    public function test_has_returns_false_when_cookie_not_present(): void
    {
        // Arrange: Create a request without a token cookie
        $request = Request::create('/', 'GET');

        // Act: Check if the cookie exists
        $hasCookie = $this->cookieTokenStorage->has($request);

        // Assert: The cookie should not exist
        $this->assertFalse($hasCookie);
    }

    // ============================================================================
    // Forget Tests
    // ============================================================================

    public function test_forget_removes_cookie(): void
    {
        // Arrange: Store a token in the cookie
        [$token, $plainToken] = $this->createTokenForUser();
        $this->cookieTokenStorage->store($plainToken);

        // Act: Forget the cookie
        $this->cookieTokenStorage->forget();

        // Assert: The cookie should be forgotten (expired)
        $isForgotten = $this->isQueuedCookieForgotten('nemesis_token');
        $this->assertTrue($isForgotten);
    }

    public function test_forget_uses_configured_cookie_name(): void
    {
        // Arrange: Change the cookie name configuration
        config()->set('nemesis.web.cookie_name', 'custom_token');

        // Refresh the service container
        $this->app->forgetInstance(CookieTokenStorageInterface::class);
        $this->app->forgetInstance(NemesisConfigInterface::class);
        $cookieTokenStorage = $this->app->make(CookieTokenStorageInterface::class);

        [$token, $plainToken] = $this->createTokenForUser();

        // Act: Store and then forget the cookie
        $cookieTokenStorage->store($plainToken);
        $cookieTokenStorage->forget();

        // Assert: The cookie with the custom name should be forgotten
        $isForgotten = $this->isQueuedCookieForgotten('custom_token');
        $this->assertTrue($isForgotten);

        // Cleanup: Restore the original configuration
        config()->set('nemesis.web.cookie_name', 'nemesis_token');
        $this->app->forgetInstance(CookieTokenStorageInterface::class);
        $this->app->forgetInstance(NemesisConfigInterface::class);
        $this->app->make(CookieTokenStorageInterface::class);
    }

    // ============================================================================
    // GetValidatedToken Tests
    // ============================================================================

    public function test_get_validated_token_returns_valid_token(): void
    {
        // Arrange: Create and store a valid token
        [$token, $plainToken] = $this->createTokenForUser();
        $this->cookieTokenStorage->store($plainToken);

        $request = Request::create('/', 'GET');
        $request->cookies->set('nemesis_token', $plainToken);

        // Act: Validate the token
        $validatedToken = $this->cookieTokenStorage->getValidatedToken($request);

        // Assert: The validated token should be the same as the stored token
        $this->assertNotNull($validatedToken);
        $this->assertEquals($token->id, $validatedToken->id);
    }

    public function test_get_validated_token_returns_null_when_token_invalid(): void
    {
        // Arrange: Store an invalid token
        $this->cookieTokenStorage->store('invalid-token');

        $request = Request::create('/', 'GET');
        $request->cookies->set('nemesis_token', 'invalid-token');

        // Act: Validate the token
        $validatedToken = $this->cookieTokenStorage->getValidatedToken($request);

        // Assert: The validation should fail
        $this->assertNull($validatedToken);
    }

    public function test_get_validated_token_returns_null_when_token_expired(): void
    {
        // Arrange: Create and store an expired token
        $expiredDate = new DateTimeVO(Carbon::getTestNow()->subDay()->format('Y-m-d\TH:i:sP'));
        [$token, $plainToken] = $this->createTokenForUser(expiresAt: $expiredDate);
        $this->cookieTokenStorage->store($plainToken);

        $request = Request::create('/', 'GET');
        $request->cookies->set('nemesis_token', $plainToken);

        // Act: Validate the expired token
        $validatedToken = $this->cookieTokenStorage->getValidatedToken($request);

        // Assert: The validation should fail
        $this->assertNull($validatedToken);
    }

    public function test_get_validated_token_returns_null_when_token_revoked(): void
    {
        // Arrange: Create, store, and revoke a token
        [$token, $plainToken] = $this->createTokenForUser();
        $this->cookieTokenStorage->store($plainToken);

        // Revoke the token
        $this->nemesisService->revoke($token);

        $request = Request::create('/', 'GET');
        $request->cookies->set('nemesis_token', $plainToken);

        // Act: Validate the revoked token
        $validatedToken = $this->cookieTokenStorage->getValidatedToken($request);

        // Assert: The validation should fail
        $this->assertNull($validatedToken);
    }

    public function test_get_validated_token_returns_null_when_cookie_not_present(): void
    {
        // Arrange: Create a request without a token cookie
        $request = Request::create('/', 'GET');

        // Act: Validate the token
        $validatedToken = $this->cookieTokenStorage->getValidatedToken($request);

        // Assert: The validation should fail
        $this->assertNull($validatedToken);
    }

    // ============================================================================
    // GetAuthenticatable Tests
    // ============================================================================

    public function test_get_authenticatable_returns_user_from_token(): void
    {
        // Arrange: Create and store a valid token
        [$token, $plainToken] = $this->createTokenForUser();
        $this->cookieTokenStorage->store($plainToken);

        $request = Request::create('/', 'GET');
        $request->cookies->set('nemesis_token', $plainToken);

        // Act: Get the authenticatable model
        $authenticatable = $this->cookieTokenStorage->getAuthenticatable($request);

        // Assert: The authenticatable should be the user
        $this->assertNotNull($authenticatable);
        $this->assertEquals($this->user->id, $authenticatable->id);
        $this->assertEquals($this->user->email, $authenticatable->email);
    }

    public function test_get_authenticatable_returns_null_when_token_invalid(): void
    {
        // Arrange: Store an invalid token
        $this->cookieTokenStorage->store('invalid-token');

        $request = Request::create('/', 'GET');
        $request->cookies->set('nemesis_token', 'invalid-token');

        // Act: Get the authenticatable model
        $authenticatable = $this->cookieTokenStorage->getAuthenticatable($request);

        // Assert: The authenticatable should be null
        $this->assertNull($authenticatable);
    }

    public function test_get_authenticatable_returns_null_when_cookie_not_present(): void
    {
        // Arrange: Create a request without a token cookie
        $request = Request::create('/', 'GET');

        // Act: Get the authenticatable model
        $authenticatable = $this->cookieTokenStorage->getAuthenticatable($request);

        // Assert: The authenticatable should be null
        $this->assertNull($authenticatable);
    }

    // ============================================================================
    // IsValid Tests
    // ============================================================================

    public function test_is_valid_returns_true_with_valid_token(): void
    {
        // Arrange: Create and store a valid token
        [$token, $plainToken] = $this->createTokenForUser();
        $this->cookieTokenStorage->store($plainToken);

        $request = Request::create('/', 'GET');
        $request->cookies->set('nemesis_token', $plainToken);

        // Act: Check if the token is valid
        $isValid = $this->cookieTokenStorage->isValid($request);

        // Assert: The token should be valid
        $this->assertTrue($isValid);
    }

    public function test_is_valid_returns_false_with_invalid_token(): void
    {
        // Arrange: Store an invalid token
        $this->cookieTokenStorage->store('invalid-token');

        $request = Request::create('/', 'GET');
        $request->cookies->set('nemesis_token', 'invalid-token');

        // Act: Check if the token is valid
        $isValid = $this->cookieTokenStorage->isValid($request);

        // Assert: The token should be invalid
        $this->assertFalse($isValid);
    }

    public function test_is_valid_returns_false_when_cookie_not_present(): void
    {
        // Arrange: Create a request without a token cookie
        $request = Request::create('/', 'GET');

        // Act: Check if the token is valid
        $isValid = $this->cookieTokenStorage->isValid($request);

        // Assert: The token should be invalid
        $this->assertFalse($isValid);
    }

    public function test_is_valid_returns_false_with_expired_token(): void
    {
        // Arrange: Create and store an expired token
        $expiredDate = new DateTimeVO(Carbon::getTestNow()->subDay()->format('Y-m-d\TH:i:sP'));
        [$token, $plainToken] = $this->createTokenForUser(expiresAt: $expiredDate);
        $this->cookieTokenStorage->store($plainToken);

        $request = Request::create('/', 'GET');
        $request->cookies->set('nemesis_token', $plainToken);

        // Act: Check if the token is valid
        $isValid = $this->cookieTokenStorage->isValid($request);

        // Assert: The token should be invalid
        $this->assertFalse($isValid);
    }

    public function test_is_valid_returns_false_with_revoked_token(): void
    {
        // Arrange: Create, store, and revoke a token
        [$token, $plainToken] = $this->createTokenForUser();
        $this->cookieTokenStorage->store($plainToken);

        // Revoke the token
        $this->nemesisService->revoke($token);

        $request = Request::create('/', 'GET');
        $request->cookies->set('nemesis_token', $plainToken);

        // Act: Check if the token is valid
        $isValid = $this->cookieTokenStorage->isValid($request);

        // Assert: The token should be invalid
        $this->assertFalse($isValid);
    }
}
