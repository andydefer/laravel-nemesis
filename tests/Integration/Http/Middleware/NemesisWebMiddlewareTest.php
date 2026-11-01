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

        // Optional route: returns whether the request is authenticated
        Route::middleware('nemesis.web:optional')->get('/web-optional', function () {
            $parameterName = config('nemesis.middleware.parameter_name', 'nemesis_auth');
            $user = request()->input($parameterName);
            $token = request()->input('current_nemesis_token');
            $format = request()->input($parameterName.'_format');

            return response()->json([
                'message' => 'OK',
                'authenticated' => $user !== null,
                'has_user' => $user !== null,
                'has_token' => $token !== null,
                'has_format' => $format !== null,
            ]);
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
        [$token, $plainToken] = $this->createTokenForUser();

        $response = $this->withUnencryptedCookie('nemesis_token', $plainToken)
            ->get('/web-protected');

        $response->assertStatus(200);
        $response->assertJson(['message' => 'OK']);
    }

    public function test_middleware_allows_request_without_ability(): void
    {
        [$token, $plainToken] = $this->createTokenForUser();

        $response = $this->withUnencryptedCookie('nemesis_token', $plainToken)
            ->get('/web-protected');

        $response->assertStatus(200);
        $response->assertJson(['message' => 'OK']);
    }

    public function test_middleware_allows_request_with_abilities_but_no_requirement(): void
    {
        [$token, $plainToken] = $this->createTokenWithAbilitiesForUser(['read']);

        $response = $this->withUnencryptedCookie('nemesis_token', $plainToken)
            ->get('/web-protected');

        $response->assertStatus(200);
        $response->assertJson(['message' => 'OK']);
    }

    // ============================================================================
    // Authentication Failure Tests
    // ============================================================================

    public function test_middleware_redirects_to_login_when_no_token_in_cookie(): void
    {
        $response = $this->get('/web-protected');

        $response->assertStatus(302);
        $response->assertRedirect('/login');
    }

    public function test_middleware_redirects_to_login_when_invalid_token_in_cookie(): void
    {
        $response = $this->withUnencryptedCookie('nemesis_token', 'invalid-token')
            ->get('/web-protected');

        $response->assertStatus(302);
        $response->assertRedirect('/login');
    }

    public function test_middleware_redirects_to_login_when_cookie_token_not_in_database(): void
    {
        $response = $this->withUnencryptedCookie('nemesis_token', 'token_not_in_database')
            ->get('/web-protected');

        $response->assertStatus(302);
        $response->assertRedirect('/login');
    }

    public function test_middleware_redirects_to_login_when_token_expired(): void
    {
        $expiredDate = new DateTimeVO(Carbon::getTestNow()->subDay()->format('Y-m-d\TH:i:sP'));
        [$token, $plainToken] = $this->createTokenForUser(expiresAt: $expiredDate);

        $response = $this->withUnencryptedCookie('nemesis_token', $plainToken)
            ->get('/web-protected');

        $response->assertStatus(302);
        $response->assertRedirect('/login');
    }

    public function test_middleware_redirects_to_configured_login_route(): void
    {
        config()->set('nemesis.web.login_route', '/custom-login');

        $this->app->forgetInstance(NemesisConfigInterface::class);
        $this->app->make(NemesisConfigInterface::class);

        Route::get('/custom-login', function () {
            return response()->json(['message' => 'Custom Login']);
        })->name('custom.login');

        $response = $this->get('/web-protected');

        $response->assertStatus(302);
        $response->assertRedirect('/custom-login');

        config()->set('nemesis.web.login_route', '/login');
        $this->app->forgetInstance(NemesisConfigInterface::class);
        $this->app->make(NemesisConfigInterface::class);
    }

    // ============================================================================
    // Ability Check Tests
    // ============================================================================

    public function test_middleware_allows_request_with_required_ability(): void
    {
        [$token, $plainToken] = $this->createTokenWithAbilitiesForUser(['read', 'write']);

        $response = $this->withUnencryptedCookie('nemesis_token', $plainToken)
            ->get('/web-ability');

        $response->assertStatus(200);
        $response->assertJson(['message' => 'OK']);
    }

    public function test_middleware_allows_request_with_multiple_abilities(): void
    {
        [$token, $plainToken] = $this->createTokenWithAbilitiesForUser(['read', 'write', 'admin']);

        $response = $this->withUnencryptedCookie('nemesis_token', $plainToken)
            ->get('/web-admin');

        $response->assertStatus(200);
        $response->assertJson(['message' => 'OK']);
    }

    public function test_middleware_returns_403_when_token_lacks_required_ability(): void
    {
        [$token, $plainToken] = $this->createTokenWithAbilitiesForUser(['read', 'write']);

        $response = $this->withUnencryptedCookie('nemesis_token', $plainToken)
            ->get('/web-admin');

        $response->assertStatus(403);
    }

    public function test_middleware_returns_403_when_token_has_no_abilities_and_ability_required(): void
    {
        [$token, $plainToken] = $this->createTokenForUser();

        $response = $this->withUnencryptedCookie('nemesis_token', $plainToken)
            ->get('/web-admin');

        $response->assertStatus(403);
    }

    // ============================================================================
    // Cookie Configuration Tests
    // ============================================================================

    public function test_middleware_uses_configured_cookie_name(): void
    {
        config()->set('nemesis.web.cookie_name', 'custom_auth_token');

        $this->app->forgetInstance(NemesisConfigInterface::class);
        $this->app->make(NemesisConfigInterface::class);

        [$token, $plainToken] = $this->createTokenForUser();

        $response = $this->withUnencryptedCookie('custom_auth_token', $plainToken)
            ->get('/web-protected');

        $response->assertStatus(200);
        $response->assertJson(['message' => 'OK']);

        config()->set('nemesis.web.cookie_name', 'nemesis_token');
        $this->app->forgetInstance(NemesisConfigInterface::class);
        $this->app->make(NemesisConfigInterface::class);
    }

    public function test_middleware_uses_configured_cookie_name_with_ability(): void
    {
        config()->set('nemesis.web.cookie_name', 'custom_auth_token');

        $this->app->forgetInstance(NemesisConfigInterface::class);
        $this->app->make(NemesisConfigInterface::class);

        [$token, $plainToken] = $this->createTokenWithAbilitiesForUser(['read']);

        $response = $this->withUnencryptedCookie('custom_auth_token', $plainToken)
            ->get('/web-ability');

        $response->assertStatus(200);
        $response->assertJson(['message' => 'OK']);

        config()->set('nemesis.web.cookie_name', 'nemesis_token');
        $this->app->forgetInstance(NemesisConfigInterface::class);
        $this->app->make(NemesisConfigInterface::class);
    }

    // ============================================================================
    // Multiple Requests Tests
    // ============================================================================

    public function test_middleware_handles_multiple_requests_with_same_token(): void
    {
        [$token, $plainToken] = $this->createTokenForUser();

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
        NemesisToken::create([
            'token_hash' => hash('sha256', 'bad-token'),
            'tokenable_type' => 'NonExistent\\Model\\Class',
            'tokenable_id' => 999,
            'name' => 'Bad Token',
            'source' => 'web',
        ]);

        $response = $this->withUnencryptedCookie('nemesis_token', 'bad-token')
            ->get('/web-protected');

        $response->assertStatus(302);
        $response->assertRedirect('/login');
    }

    public function test_middleware_handles_token_with_deleted_tokenable(): void
    {
        $user = TestUser::create(['name' => 'Temp', 'email' => 'temp@example.com']);

        $record = $this->hydration->hydrate(NemesisTokenRecord::class, ['name' => 'Temp Token']);
        [$token, $plainToken] = $this->service->createWithPlainToken($record, $user);

        $user->delete();

        $response = $this->withUnencryptedCookie('nemesis_token', $plainToken)
            ->get('/web-protected');

        $response->assertStatus(302);
        $response->assertRedirect('/login');
    }

    public function test_middleware_redirects_to_login_when_deleted_tokenable_with_ability(): void
    {
        $user = TestUser::create(['name' => 'Temp', 'email' => 'temp@example.com']);

        $record = $this->hydration->hydrate(NemesisTokenRecord::class, [
            'name' => 'Temp Token',
            'abilities' => ['read'],
        ]);
        [$token, $plainToken] = $this->service->createWithPlainToken($record, $user);

        $user->delete();

        $response = $this->withUnencryptedCookie('nemesis_token', $plainToken)
            ->get('/web-ability');

        $response->assertStatus(302);
        $response->assertRedirect('/login');
    }

    public function test_middleware_redirects_to_login_when_expired_token_with_ability(): void
    {
        $expiredDate = new DateTimeVO(Carbon::getTestNow()->subDay()->format('Y-m-d\TH:i:sP'));

        $record = $this->hydration->hydrate(NemesisTokenRecord::class, [
            'name' => 'Expired Token',
            'abilities' => ['read'],
            'expires_at' => $expiredDate,
        ]);
        [$token, $plainToken] = $this->service->createWithPlainToken($record, $this->user);

        $response = $this->withUnencryptedCookie('nemesis_token', $plainToken)
            ->get('/web-ability');

        $response->assertStatus(302);
        $response->assertRedirect('/login');
    }

    public function test_middleware_redirects_to_login_when_revoked_token_with_ability(): void
    {
        [$token, $plainToken] = $this->createTokenWithAbilitiesForUser(['read']);
        $this->service->revoke($token);

        $response = $this->withUnencryptedCookie('nemesis_token', $plainToken)
            ->get('/web-ability');

        $response->assertStatus(302);
        $response->assertRedirect('/login');
    }

    public function test_middleware_redirects_to_configured_login_route_with_ability(): void
    {
        config()->set('nemesis.web.login_route', '/custom-login');

        $this->app->forgetInstance(NemesisConfigInterface::class);
        $this->app->make(NemesisConfigInterface::class);

        Route::get('/custom-login', function () {
            return response()->json(['message' => 'Custom Login']);
        })->name('custom.login');

        $response = $this->withUnencryptedCookie('nemesis_token', 'invalid-token')
            ->get('/web-ability');

        $response->assertStatus(302);
        $response->assertRedirect('/custom-login');

        config()->set('nemesis.web.login_route', '/login');
        $this->app->forgetInstance(NemesisConfigInterface::class);
        $this->app->make(NemesisConfigInterface::class);
    }

    // ============================================================================
    // Last Used Update Tests
    // ============================================================================

    public function test_middleware_updates_last_used_on_successful_authentication(): void
    {
        [$token, $plainToken] = $this->createTokenForUser();
        $this->assertNull($token->last_used_at);

        $response = $this->withUnencryptedCookie('nemesis_token', $plainToken)
            ->get('/web-protected');

        $token->refresh();
        $response->assertStatus(200);
        $this->assertNotNull($token->last_used_at);
    }

    public function test_middleware_updates_last_used_with_ability(): void
    {
        [$token, $plainToken] = $this->createTokenWithAbilitiesForUser(['read']);
        $this->assertNull($token->last_used_at);

        $response = $this->withUnencryptedCookie('nemesis_token', $plainToken)
            ->get('/web-ability');

        $token->refresh();
        $response->assertStatus(200);
        $this->assertNotNull($token->last_used_at);
    }

    // ============================================================================
    // Revoked Token Tests
    // ============================================================================

    public function test_middleware_redirects_to_login_when_token_revoked(): void
    {
        [$token, $plainToken] = $this->createTokenForUser();
        $this->service->revoke($token);

        $response = $this->withUnencryptedCookie('nemesis_token', $plainToken)
            ->get('/web-protected');

        $response->assertStatus(302);
        $response->assertRedirect('/login');
    }

    // ============================================================================
    // Token Without Abilities Tests
    // ============================================================================

    public function test_middleware_allows_request_when_token_has_no_abilities_and_no_requirement(): void
    {
        [$token, $plainToken] = $this->createTokenForUser();

        $response = $this->withUnencryptedCookie('nemesis_token', $plainToken)
            ->get('/web-protected');

        $response->assertStatus(200);
        $response->assertJson(['message' => 'OK']);
    }

    // ============================================================================
    // Optional Middleware Tests
    // ============================================================================

    public function test_optional_middleware_allows_request_without_token(): void
    {
        $response = $this->get('/web-optional');

        $response->assertStatus(200);
        $response->assertJson([
            'message' => 'OK',
            'authenticated' => false,
            'has_user' => false,
            'has_token' => false,
            'has_format' => false,
        ]);
    }

    public function test_optional_middleware_allows_request_with_valid_token(): void
    {
        [$token, $plainToken] = $this->createTokenForUser();

        $response = $this->withUnencryptedCookie('nemesis_token', $plainToken)
            ->get('/web-optional');

        $response->assertStatus(200);
        $response->assertJson([
            'message' => 'OK',
            'authenticated' => true,
            'has_user' => true,
            'has_token' => true,
            'has_format' => true,
        ]);
    }

    public function test_optional_middleware_allows_request_with_invalid_token(): void
    {
        $response = $this->withUnencryptedCookie('nemesis_token', 'invalid-token')
            ->get('/web-optional');

        $response->assertStatus(200);
        $response->assertJson([
            'message' => 'OK',
            'authenticated' => false,
            'has_user' => false,
            'has_token' => false,
            'has_format' => false,
        ]);
    }

    public function test_optional_middleware_allows_request_with_expired_token(): void
    {
        $expiredDate = new DateTimeVO(Carbon::getTestNow()->subDay()->format('Y-m-d\TH:i:sP'));
        [$token, $plainToken] = $this->createTokenForUser(expiresAt: $expiredDate);

        $response = $this->withUnencryptedCookie('nemesis_token', $plainToken)
            ->get('/web-optional');

        $response->assertStatus(200);
        $response->assertJson([
            'message' => 'OK',
            'authenticated' => false,
            'has_user' => false,
            'has_token' => false,
            'has_format' => false,
        ]);
    }

    public function test_optional_middleware_allows_request_with_revoked_token(): void
    {
        [$token, $plainToken] = $this->createTokenForUser();
        $this->service->revoke($token);

        $response = $this->withUnencryptedCookie('nemesis_token', $plainToken)
            ->get('/web-optional');

        $response->assertStatus(200);
        $response->assertJson([
            'message' => 'OK',
            'authenticated' => false,
            'has_user' => false,
            'has_token' => false,
            'has_format' => false,
        ]);
    }

    public function test_optional_middleware_ignores_ability_check_when_optional(): void
    {
        Route::middleware('nemesis.web:optional')->get('/web-optional-ability', function () {
            $parameterName = config('nemesis.middleware.parameter_name', 'nemesis_auth');
            $user = request()->input($parameterName);

            return response()->json([
                'message' => 'OK',
                'authenticated' => $user !== null,
            ]);
        });

        [$token, $plainToken] = $this->createTokenForUser();

        $response = $this->withUnencryptedCookie('nemesis_token', $plainToken)
            ->get('/web-optional-ability');

        $response->assertStatus(200);
        $response->assertJson([
            'message' => 'OK',
            'authenticated' => true,
        ]);
    }
}
