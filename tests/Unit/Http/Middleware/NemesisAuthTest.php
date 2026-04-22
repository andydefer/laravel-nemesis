<?php

declare(strict_types=1);

namespace Kani\Nemesis\Tests\Unit\Http\Middleware;

use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Route;
use Illuminate\Support\Facades\DB;
use Kani\Nemesis\Enums\ErrorCode;
use Kani\Nemesis\Http\Middleware\NemesisAuth;
use Kani\Nemesis\Models\NemesisToken;
use Kani\Nemesis\Tests\Support\TestUser;
use Kani\Nemesis\Tests\TestCase;

/**
 * Test suite for NemesisAuth middleware.
 *
 * Verifies that the token authentication middleware correctly validates
 * bearer tokens, checks expiration, verifies abilities, and attaches
 * the authenticated model to the request.
 */
final class NemesisAuthTest extends TestCase
{
    private NemesisAuth $middleware;
    private Request $request;
    private bool $nextCalled;

    protected function setUp(): void
    {
        parent::setUp();

        $this->middleware = new NemesisAuth();
        $this->request = Request::create('/test', 'GET');
        $this->nextCalled = false;

        $this->setupMockRoute();
    }

    /**
     * Set up a mock route for the request.
     */
    private function setupMockRoute(): void
    {
        $route = new Route('GET', '/test', function (): void {});
        $this->request->setRouteResolver(function () use ($route) {
            return $route;
        });
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    // ============================================================================
    // Tests for missing token
    // ============================================================================

    /**
     * Test that request without bearer token returns MISSING_TOKEN error.
     */
    public function test_returns_missing_token_error_when_no_bearer_token_provided(): void
    {
        // Arrange: Request has no Authorization header
        $next = function ($req): JsonResponse {
            $this->nextCalled = true;
            return response()->json(['success' => true]);
        };

        // Act: Process request through middleware
        $response = $this->middleware->handle($this->request, $next);

        // Assert: Error response is returned and next middleware was not called
        $this->assertInstanceOf(JsonResponse::class, $response);
        $this->assertEquals(401, $response->getStatusCode());

        $data = $response->getData(true);
        $this->assertEquals(ErrorCode::MISSING_TOKEN->value, $data['errorCode']);
        $this->assertEquals('Token not provided', $data['message']);
        $this->assertEquals(401, $data['status']);
        $this->assertFalse($this->nextCalled);
    }

    /**
     * Test that empty bearer token string is treated as missing token.
     */
    public function test_empty_bearer_token_string_returns_missing_token_error(): void
    {
        // Arrange: Request with empty bearer token
        $this->request->headers->set('Authorization', 'Bearer ');
        $next = function ($req): JsonResponse {
            $this->nextCalled = true;
            return response()->json(['success' => true]);
        };

        // Act: Process request through middleware
        $response = $this->middleware->handle($this->request, $next);

        // Assert: Error response is returned and next middleware was not called
        $this->assertInstanceOf(JsonResponse::class, $response);
        $this->assertEquals(401, $response->getStatusCode());

        $data = $response->getData(true);
        $this->assertEquals(ErrorCode::MISSING_TOKEN->value, $data['errorCode']);
        $this->assertFalse($this->nextCalled);
    }

    /**
     * Test that malformed Authorization header returns missing token error.
     */
    public function test_malformed_authorization_header_returns_missing_token_error(): void
    {
        // Arrange: Request with malformed Authorization header (no 'Bearer' prefix)
        $this->request->headers->set('Authorization', 'InvalidToken123');
        $next = function ($req): JsonResponse {
            $this->nextCalled = true;
            return response()->json(['success' => true]);
        };

        // Act: Process request through middleware
        $response = $this->middleware->handle($this->request, $next);

        // Assert: Error response is returned and next middleware was not called
        $this->assertInstanceOf(JsonResponse::class, $response);
        $this->assertEquals(401, $response->getStatusCode());

        $data = $response->getData(true);
        $this->assertEquals(ErrorCode::MISSING_TOKEN->value, $data['errorCode']);
        $this->assertFalse($this->nextCalled);
    }

    // ============================================================================
    // Tests for invalid token
    // ============================================================================

    /**
     * Test that request with invalid token returns INVALID_TOKEN error.
     */
    public function test_returns_invalid_token_error_when_token_not_found_in_database(): void
    {
        // Arrange: Request with bearer token that doesn't exist in database
        $this->request->headers->set('Authorization', 'Bearer invalid-token-12345');
        $next = function ($req): JsonResponse {
            $this->nextCalled = true;
            return response()->json(['success' => true]);
        };

        // Act: Process request through middleware
        $response = $this->middleware->handle($this->request, $next);

        // Assert: Error response is returned and next middleware was not called
        $this->assertInstanceOf(JsonResponse::class, $response);
        $this->assertEquals(401, $response->getStatusCode());

        $data = $response->getData(true);
        $this->assertEquals(ErrorCode::INVALID_TOKEN->value, $data['errorCode']);
        $this->assertEquals('Invalid token', $data['message']);
        $this->assertEquals(401, $data['status']);
        $this->assertFalse($this->nextCalled);
    }

    // ============================================================================
    // Tests for expired token
    // ============================================================================

    /**
     * Test that request with expired token returns TOKEN_EXPIRED error.
     */
    public function test_returns_token_expired_error_when_token_is_expired(): void
    {
        // Arrange: Create a user and a token, then expire it directly in database
        $user = TestUser::create(['name' => 'Test User', 'email' => 'test@example.com']);
        $plainToken = $user->createNemesisToken('Test Token', 'test');

        $this->expireTokenInDatabase($user->getNemesisToken($plainToken));

        $this->request->headers->set('Authorization', 'Bearer ' . $plainToken);
        $next = function ($req): JsonResponse {
            $this->nextCalled = true;
            return response()->json(['success' => true]);
        };

        // Act: Process request through middleware
        $response = $this->middleware->handle($this->request, $next);

        // Assert: Error response is returned and next middleware was not called
        $this->assertInstanceOf(JsonResponse::class, $response);
        $this->assertEquals(401, $response->getStatusCode());

        $data = $response->getData(true);
        $this->assertEquals(ErrorCode::TOKEN_EXPIRED->value, $data['errorCode']);
        $this->assertEquals('Token has expired', $data['message']);
        $this->assertFalse($this->nextCalled);
    }

    /**
     * Test that token with future expiration date passes through.
     */
    public function test_token_with_future_expiration_passes_through(): void
    {
        // Arrange: Create a user and a token with future expiration
        $user = TestUser::create(['name' => 'Test User', 'email' => 'test@example.com']);
        $plainToken = $user->createNemesisToken('Test Token', 'test');

        $tokenModel = $user->getNemesisToken($plainToken);
        $tokenModel->expires_at = now()->addDays(30);
        $tokenModel->save();

        $this->request->headers->set('Authorization', 'Bearer ' . $plainToken);
        $next = function ($req): JsonResponse {
            $this->nextCalled = true;
            return response()->json(['success' => true]);
        };

        // Act: Process request through middleware
        $response = $this->middleware->handle($this->request, $next);

        // Assert: Next middleware was called
        $this->assertTrue($this->nextCalled);
    }

    /**
     * Test that token with null expiration (never expires) passes through.
     */
    public function test_token_with_null_expiration_passes_through(): void
    {
        // Arrange: Create a user and a token with null expiration
        $user = TestUser::create(['name' => 'Test User', 'email' => 'test@example.com']);
        $plainToken = $user->createNemesisToken('Test Token', 'test');

        $tokenModel = $user->getNemesisToken($plainToken);
        $tokenModel->expires_at = null;
        $tokenModel->save();

        $this->request->headers->set('Authorization', 'Bearer ' . $plainToken);
        $next = function ($req): JsonResponse {
            $this->nextCalled = true;
            return response()->json(['success' => true]);
        };

        // Act: Process request through middleware
        $response = $this->middleware->handle($this->request, $next);

        // Assert: Next middleware was called
        $this->assertTrue($this->nextCalled);
    }

    // ============================================================================
    // Tests for ability/permissions
    // ============================================================================

    /**
     * Test that request with valid token but insufficient ability returns INSUFFICIENT_PERMISSIONS error.
     */
    public function test_returns_insufficient_permissions_error_when_token_lacks_required_ability(): void
    {
        // Arrange: Create a user and a token with limited abilities
        $user = TestUser::create(['name' => 'Test User', 'email' => 'test@example.com']);
        $plainToken = $user->createNemesisToken('Test Token', 'test', ['read']);

        $this->request->headers->set('Authorization', 'Bearer ' . $plainToken);
        $next = function ($req): JsonResponse {
            $this->nextCalled = true;
            return response()->json(['success' => true]);
        };

        // Act: Process request with 'write' ability requirement
        $response = $this->middleware->handle($this->request, $next, 'write');

        // Assert: Error response is returned and next middleware was not called
        $this->assertInstanceOf(JsonResponse::class, $response);
        $this->assertEquals(403, $response->getStatusCode());

        $data = $response->getData(true);
        $this->assertEquals(ErrorCode::INSUFFICIENT_PERMISSIONS->value, $data['errorCode']);
        $this->assertEquals('Insufficient permissions', $data['message']);
        $this->assertEquals(403, $data['status']);
        $this->assertArrayHasKey('details', $data);
        $this->assertEquals('write', $data['details']['required_ability']);
        $this->assertFalse($this->nextCalled);
    }

    /**
     * Test that request with valid token and matching ability passes through.
     */
    public function test_passes_through_when_token_has_required_ability(): void
    {
        // Arrange: Create a user and a token with 'write' ability
        $user = TestUser::create(['name' => 'Test User', 'email' => 'test@example.com']);
        $plainToken = $user->createNemesisToken('Test Token', 'test', ['read', 'write']);

        $this->request->headers->set('Authorization', 'Bearer ' . $plainToken);
        $next = function ($req): JsonResponse {
            $this->nextCalled = true;
            return response()->json(['success' => true]);
        };

        // Act: Process request with 'write' ability requirement
        $response = $this->middleware->handle($this->request, $next, 'write');

        // Assert: Next middleware was called and returns success response
        $this->assertTrue($this->nextCalled);
        $this->assertInstanceOf(JsonResponse::class, $response);
        $this->assertEquals(200, $response->getStatusCode());

        $data = $response->getData(true);
        $this->assertTrue($data['success']);
    }

    /**
     * Test that request with token having null abilities (unrestricted) passes any ability check.
     */
    public function test_passes_through_when_token_has_null_abilities_unrestricted(): void
    {
        // Arrange: Create a user and a token with null abilities (unrestricted)
        $user = TestUser::create(['name' => 'Test User', 'email' => 'test@example.com']);
        $plainToken = $user->createNemesisToken('Test Token', 'test', null);

        $this->request->headers->set('Authorization', 'Bearer ' . $plainToken);
        $next = function ($req): JsonResponse {
            $this->nextCalled = true;
            return response()->json(['success' => true]);
        };

        // Act: Process request with any ability requirement
        $response = $this->middleware->handle($this->request, $next, 'super-admin');

        // Assert: Next middleware was called (null abilities means no restrictions)
        $this->assertTrue($this->nextCalled);
        $this->assertInstanceOf(JsonResponse::class, $response);
        $this->assertEquals(200, $response->getStatusCode());
    }

    /**
     * Test that ability parameter is optional and not required.
     */
    public function test_passes_through_when_no_ability_required(): void
    {
        // Arrange: Create a user and a token
        $user = TestUser::create(['name' => 'Test User', 'email' => 'test@example.com']);
        $plainToken = $user->createNemesisToken('Test Token', 'test');

        $this->request->headers->set('Authorization', 'Bearer ' . $plainToken);
        $next = function ($req): JsonResponse {
            $this->nextCalled = true;
            return response()->json(['success' => true]);
        };

        // Act: Process request without ability parameter
        $response = $this->middleware->handle($this->request, $next);

        // Assert: Next middleware was called
        $this->assertTrue($this->nextCalled);
        $this->assertInstanceOf(JsonResponse::class, $response);
        $this->assertEquals(200, $response->getStatusCode());
    }

    // ============================================================================
    // Tests for successful authentication
    // ============================================================================

    /**
     * Test that successful authentication attaches authenticatable model to request.
     */
    public function test_attaches_authenticatable_model_to_request_on_success(): void
    {
        // Arrange: Create a user and a token
        $user = TestUser::create(['name' => 'Test User', 'email' => 'test@example.com']);
        $plainToken = $user->createNemesisToken('Test Token', 'test');

        $this->request->headers->set('Authorization', 'Bearer ' . $plainToken);
        $parameterName = config('nemesis.middleware.parameter_name', 'nemesisAuth');
        $next = function ($req) use ($parameterName): JsonResponse {
            $this->nextCalled = true;
            return response()->json(['success' => true]);
        };

        // Act: Process request through middleware
        $this->middleware->handle($this->request, $next);

        // Assert: Request has the authenticatable model and token model merged
        $this->assertTrue($this->request->has($parameterName));
        $this->assertEquals($user->id, $this->request->get($parameterName)->id);
        $this->assertEquals($user->getMorphClass(), $this->request->get($parameterName)->getMorphClass());
        $this->assertTrue($this->request->has('currentNemesisToken'));
        $this->assertInstanceOf(NemesisToken::class, $this->request->get('currentNemesisToken'));
    }

    /**
     * Test that last_used_at timestamp is updated on successful authentication.
     */
    public function test_updates_last_used_at_timestamp_on_success(): void
    {
        // Arrange: Create a user and a token
        $user = TestUser::create(['name' => 'Test User', 'email' => 'test@example.com']);
        $plainToken = $user->createNemesisToken('Test Token', 'test');

        $tokenModel = $user->getNemesisToken($plainToken);
        $originalLastUsed = $tokenModel->last_used_at;

        $this->request->headers->set('Authorization', 'Bearer ' . $plainToken);
        $next = function ($req): JsonResponse {
            $this->nextCalled = true;
            return response()->json(['success' => true]);
        };

        // Act: Process request through middleware
        $this->middleware->handle($this->request, $next);

        // Assert: last_used_at was updated
        $tokenModel->refresh();
        $this->assertNotNull($tokenModel->last_used_at);
    }

    // ============================================================================
    // Tests for tokenable model not found
    // ============================================================================

    /**
     * Test that request returns INVALID_TOKEN error when tokenable model is null.
     */
    public function test_returns_invalid_token_error_when_tokenable_model_is_null(): void
    {
        // Arrange: Create a user and a token, then delete the user
        $user = TestUser::create(['name' => 'Test User', 'email' => 'test@example.com']);
        $plainToken = $user->createNemesisToken('Test Token', 'test');

        $user->delete();

        $this->request->headers->set('Authorization', 'Bearer ' . $plainToken);
        $next = function ($req): JsonResponse {
            $this->nextCalled = true;
            return response()->json(['success' => true]);
        };

        // Act: Process request through middleware
        $response = $this->middleware->handle($this->request, $next);

        // Assert: Error response is returned and next middleware was not called
        $this->assertInstanceOf(JsonResponse::class, $response);
        $this->assertEquals(401, $response->getStatusCode());

        $data = $response->getData(true);
        $this->assertEquals(ErrorCode::INVALID_TOKEN->value, $data['errorCode']);
        $this->assertEquals('Invalid token', $data['message']);
        $this->assertFalse($this->nextCalled);
    }

    // ============================================================================
    // Tests for multiple tokens from different sources
    // ============================================================================

    /**
     * Test that different token sources are handled correctly.
     */
    public function test_token_with_different_sources_are_handled_correctly(): void
    {
        // Arrange: Create a user with multiple tokens from different sources
        $user = TestUser::create(['name' => 'Test User', 'email' => 'test@example.com']);
        $phoneToken = $user->createNemesisToken('Phone App', 'phone');
        $webToken = $user->createNemesisToken('Web App', 'web');

        $this->request->headers->set('Authorization', 'Bearer ' . $phoneToken);
        $next = function ($req): JsonResponse {
            $this->nextCalled = true;
            return response()->json(['success' => true]);
        };

        // Act: Process request with phone token
        $this->middleware->handle($this->request, $next);

        // Assert: Phone token works
        $this->assertTrue($this->nextCalled);

        // Arrange: Reset for web token test
        $this->resetRequest();
        $this->request->headers->set('Authorization', 'Bearer ' . $webToken);
        $this->nextCalled = false;

        // Act: Process request with web token
        $this->middleware->handle($this->request, $next);

        // Assert: Web token works
        $this->assertTrue($this->nextCalled);
    }

        // ============================================================================
    // Tests for origin validation (CORS)
    // ============================================================================

    /**
     * Test that request without origin header passes through.
     */
    public function test_passes_through_when_no_origin_header_present(): void
    {
        // Arrange: Create a user and a token
        $user = TestUser::create(['name' => 'Test User', 'email' => 'test@example.com']);
        $plainToken = $user->createNemesisToken('Test Token', 'test');

        // Ensure no origin header is set
        $this->request->headers->remove('Origin');
        $this->request->headers->set('Authorization', 'Bearer ' . $plainToken);

        $next = function ($req): JsonResponse {
            $this->nextCalled = true;
            return response()->json(['success' => true]);
        };

        // Act: Process request through middleware
        $response = $this->middleware->handle($this->request, $next);

        // Assert: Next middleware was called (non-browser requests are allowed)
        $this->assertTrue($this->nextCalled);
        $this->assertInstanceOf(JsonResponse::class, $response);
        $this->assertEquals(200, $response->getStatusCode());
    }

    /**
     * Test that request with allowed origin passes through.
     */
    public function test_passes_through_when_origin_is_allowed(): void
    {
        // Arrange: Create a user and a token with specific allowed origins
        $user = TestUser::create(['name' => 'Test User', 'email' => 'test@example.com']);
        $plainToken = $user->createNemesisToken('Test Token', 'test');

        $tokenModel = $user->getNemesisToken($plainToken);
        $tokenModel->setAllowedOrigins(['https://example.com', 'https://app.example.com']);

        $this->request->headers->set('Origin', 'https://example.com');
        $this->request->headers->set('Authorization', 'Bearer ' . $plainToken);

        $next = function ($req): JsonResponse {
            $this->nextCalled = true;
            return response()->json(['success' => true]);
        };

        // Act: Process request through middleware
        $response = $this->middleware->handle($this->request, $next);

        // Assert: Next middleware was called
        $this->assertTrue($this->nextCalled);
        $this->assertInstanceOf(JsonResponse::class, $response);
        $this->assertEquals(200, $response->getStatusCode());
    }

    /**
     * Test that request with origin not allowed returns ORIGIN_NOT_ALLOWED error.
     */
    public function test_returns_origin_not_allowed_error_when_origin_is_not_allowed(): void
    {
        // Arrange: Create a user and a token with specific allowed origins
        $user = TestUser::create(['name' => 'Test User', 'email' => 'test@example.com']);
        $plainToken = $user->createNemesisToken('Test Token', 'test');

        $tokenModel = $user->getNemesisToken($plainToken);
        $tokenModel->setAllowedOrigins(['https://example.com']);

        $this->request->headers->set('Origin', 'https://malicious.com');
        $this->request->headers->set('Authorization', 'Bearer ' . $plainToken);

        $next = function ($req): JsonResponse {
            $this->nextCalled = true;
            return response()->json(['success' => true]);
        };

        // Act: Process request through middleware
        $response = $this->middleware->handle($this->request, $next);

        // Assert: Error response is returned and next middleware was not called
        $this->assertInstanceOf(JsonResponse::class, $response);
        $this->assertEquals(403, $response->getStatusCode());

        $data = $response->getData(true);
        $this->assertEquals(ErrorCode::ORIGIN_NOT_ALLOWED->value, $data['errorCode']);
        $this->assertEquals('This origin is not allowed', $data['message']);
        $this->assertEquals(403, $data['status']);
        $this->assertArrayHasKey('details', $data);
        $this->assertEquals('https://malicious.com', $data['details']['origin']);
        $this->assertFalse($this->nextCalled);
    }

    /**
     * Test that token with empty allowed_origins (allow all) passes any origin.
     */
    public function test_passes_through_when_allowed_origins_is_empty_allow_all(): void
    {
        // Arrange: Create a user and a token with empty allowed_origins (allow all)
        $user = TestUser::create(['name' => 'Test User', 'email' => 'test@example.com']);
        $plainToken = $user->createNemesisToken('Test Token', 'test');

        $tokenModel = $user->getNemesisToken($plainToken);
        $tokenModel->setAllowedOrigins([]); // Empty array means allow all

        $this->request->headers->set('Origin', 'https://any-domain.com');
        $this->request->headers->set('Authorization', 'Bearer ' . $plainToken);

        $next = function ($req): JsonResponse {
            $this->nextCalled = true;
            return response()->json(['success' => true]);
        };

        // Act: Process request through middleware
        $response = $this->middleware->handle($this->request, $next);

        // Assert: Next middleware was called (empty allowed_origins = allow all)
        $this->assertTrue($this->nextCalled);
        $this->assertInstanceOf(JsonResponse::class, $response);
        $this->assertEquals(200, $response->getStatusCode());
    }

    /**
     * Test that token with null allowed_origins (allow all) passes any origin.
     */
    public function test_passes_through_when_allowed_origins_is_null_allow_all(): void
    {
        // Arrange: Create a user and a token with null allowed_origins (allow all)
        $user = TestUser::create(['name' => 'Test User', 'email' => 'test@example.com']);
        $plainToken = $user->createNemesisToken('Test Token', 'test');

        $tokenModel = $user->getNemesisToken($plainToken);
        $tokenModel->setAllowedOrigins(null); // Null means allow all

        $this->request->headers->set('Origin', 'https://another-domain.com');
        $this->request->headers->set('Authorization', 'Bearer ' . $plainToken);

        $next = function ($req): JsonResponse {
            $this->nextCalled = true;
            return response()->json(['success' => true]);
        };

        // Act: Process request through middleware
        $response = $this->middleware->handle($this->request, $next);

        // Assert: Next middleware was called (null allowed_origins = allow all)
        $this->assertTrue($this->nextCalled);
        $this->assertInstanceOf(JsonResponse::class, $response);
        $this->assertEquals(200, $response->getStatusCode());
    }

    /**
     * Test that wildcard subdomain origin matching works.
     */
    public function test_passes_through_when_origin_matches_wildcard_subdomain(): void
    {
        // Arrange: Create a user and a token with wildcard origin
        $user = TestUser::create(['name' => 'Test User', 'email' => 'test@example.com']);
        $plainToken = $user->createNemesisToken('Test Token', 'test');

        $tokenModel = $user->getNemesisToken($plainToken);
        $tokenModel->setAllowedOrigins(['https://*.example.com']);

        // Test multiple subdomains
        $subdomains = ['https://api.example.com', 'https://app.example.com', 'https://admin.example.com'];

        foreach ($subdomains as $subdomain) {
            // Arrange: Reset for each subdomain
            $this->resetRequest();
            $this->request->headers->set('Origin', $subdomain);
            $this->request->headers->set('Authorization', 'Bearer ' . $plainToken);
            $this->nextCalled = false;

            $next = function ($req): JsonResponse {
                $this->nextCalled = true;
                return response()->json(['success' => true]);
            };

            // Act: Process request through middleware
            $response = $this->middleware->handle($this->request, $next);

            // Assert: Next middleware was called for each subdomain
            $this->assertTrue($this->nextCalled, "Failed for subdomain: {$subdomain}");
            $this->assertInstanceOf(JsonResponse::class, $response);
            $this->assertEquals(200, $response->getStatusCode());
        }
    }

    /**
     * Test that exact origin matching is case-sensitive? (should be case-insensitive for domains)
     */
    public function test_origin_matching_is_case_insensitive_for_domains(): void
    {
        // Arrange: Create a user and a token with specific allowed origin
        $user = TestUser::create(['name' => 'Test User', 'email' => 'test@example.com']);
        $plainToken = $user->createNemesisToken('Test Token', 'test');

        $tokenModel = $user->getNemesisToken($plainToken);
        $tokenModel->setAllowedOrigins(['https://Example.com']);

        // Test with different case
        $this->request->headers->set('Origin', 'https://example.com');
        $this->request->headers->set('Authorization', 'Bearer ' . $plainToken);

        $next = function ($req): JsonResponse {
            $this->nextCalled = true;
            return response()->json(['success' => true]);
        };

        // Act: Process request through middleware
        $response = $this->middleware->handle($this->request, $next);

        // Assert: Next middleware was called (domains are case-insensitive)
        $this->assertTrue($this->nextCalled);
        $this->assertInstanceOf(JsonResponse::class, $response);
        $this->assertEquals(200, $response->getStatusCode());
    }

    /**
     * Test that trailing slashes in origins are handled correctly.
     */
    public function test_origin_matching_handles_trailing_slashes(): void
    {
        // Arrange: Create a user and a token with origin without trailing slash
        $user = TestUser::create(['name' => 'Test User', 'email' => 'test@example.com']);
        $plainToken = $user->createNemesisToken('Test Token', 'test');

        $tokenModel = $user->getNemesisToken($plainToken);
        $tokenModel->setAllowedOrigins(['https://example.com']);

        // Test with trailing slash in request
        $this->request->headers->set('Origin', 'https://example.com/');
        $this->request->headers->set('Authorization', 'Bearer ' . $plainToken);

        $next = function ($req): JsonResponse {
            $this->nextCalled = true;
            return response()->json(['success' => true]);
        };

        // Act: Process request through middleware
        $response = $this->middleware->handle($this->request, $next);

        // Assert: Next middleware was called (trailing slashes are normalized)
        $this->assertTrue($this->nextCalled);
        $this->assertInstanceOf(JsonResponse::class, $response);
        $this->assertEquals(200, $response->getStatusCode());
    }

    // ============================================================================
    // Tests for security headers
    // ============================================================================

    /**
     * Test that security headers are added to successful responses.
     */
    public function test_security_headers_are_added_to_successful_response(): void
    {
        // Arrange: Create a user and a token
        $user = TestUser::create(['name' => 'Test User', 'email' => 'test@example.com']);
        $plainToken = $user->createNemesisToken('Test Token', 'test');

        $this->request->headers->set('Authorization', 'Bearer ' . $plainToken);

        $next = function ($req): JsonResponse {
            return response()->json(['success' => true]);
        };

        // Act: Process request through middleware
        $response = $this->middleware->handle($this->request, $next);

        // Assert: Security headers are present
        $this->assertInstanceOf(JsonResponse::class, $response);
        $this->assertEquals('DENY', $response->headers->get('X-Frame-Options'));
        $this->assertEquals('1; mode=block', $response->headers->get('X-XSS-Protection'));
        $this->assertEquals('nosniff', $response->headers->get('X-Content-Type-Options'));
        $this->assertEquals('strict-origin-when-cross-origin', $response->headers->get('Referrer-Policy'));
    }

    /**
     * Test that security headers are not added to error responses.
     */
    public function test_security_headers_are_not_added_to_error_responses(): void
    {
        // Arrange: Request with no token
        $next = function ($req): JsonResponse {
            $this->nextCalled = true;
            return response()->json(['success' => true]);
        };

        // Act: Process request through middleware
        $response = $this->middleware->handle($this->request, $next);

        // Assert: Error response should not have security headers
        $this->assertInstanceOf(JsonResponse::class, $response);
        $this->assertNull($response->headers->get('X-Frame-Options'));
        $this->assertNull($response->headers->get('X-XSS-Protection'));
        $this->assertNull($response->headers->get('X-Content-Type-Options'));
        $this->assertNull($response->headers->get('Referrer-Policy'));
    }

    /**
     * Test that multiple origins can be allowed for a token.
     */
    public function test_multiple_origins_can_be_allowed_for_token(): void
    {
        // Arrange: Create a user and a token with multiple allowed origins
        $user = TestUser::create(['name' => 'Test User', 'email' => 'test@example.com']);
        $plainToken = $user->createNemesisToken('Test Token', 'test');

        $tokenModel = $user->getNemesisToken($plainToken);
        $allowedOrigins = [
            'https://example.com',
            'https://app.example.com',
            'https://api.example.com',
            'https://admin.example.com',
        ];
        $tokenModel->setAllowedOrigins($allowedOrigins);

        foreach ($allowedOrigins as $origin) {
            // Arrange: Reset for each origin
            $this->resetRequest();
            $this->request->headers->set('Origin', $origin);
            $this->request->headers->set('Authorization', 'Bearer ' . $plainToken);
            $this->nextCalled = false;

            $next = function ($req): JsonResponse {
                $this->nextCalled = true;
                return response()->json(['success' => true]);
            };

            // Act: Process request through middleware
            $response = $this->middleware->handle($this->request, $next);

            // Assert: Next middleware was called for each allowed origin
            $this->assertTrue($this->nextCalled, "Failed for origin: {$origin}");
            $this->assertInstanceOf(JsonResponse::class, $response);
            $this->assertEquals(200, $response->getStatusCode());
        }
    }

    /**
     * Test that adding and removing origins works correctly.
     */
    public function test_dynamic_origin_management_works_correctly(): void
    {
        // Arrange: Create a user and a token
        $user = TestUser::create(['name' => 'Test User', 'email' => 'test@example.com']);
        $plainToken = $user->createNemesisToken('Test Token', 'test');

        $tokenModel = $user->getNemesisToken($plainToken);

        // Add origins dynamically
        $tokenModel->addAllowedOrigin('https://example.com');
        $tokenModel->addAllowedOrigin('https://app.example.com');

        // Test first origin
        $this->resetRequest();
        $this->request->headers->set('Origin', 'https://example.com');
        $this->request->headers->set('Authorization', 'Bearer ' . $plainToken);

        $next = function ($req): JsonResponse {
            $this->nextCalled = true;
            return response()->json(['success' => true]);
        };

        // Act: Process request through middleware
        $response = $this->middleware->handle($this->request, $next);

        // Assert: First origin works
        $this->assertTrue($this->nextCalled);

        // Remove first origin
        $tokenModel->removeAllowedOrigin('https://example.com');

        // Test removed origin
        $this->resetRequest();
        $this->request->headers->set('Origin', 'https://example.com');
        $this->request->headers->set('Authorization', 'Bearer ' . $plainToken);
        $this->nextCalled = false;

        // Act: Process request through middleware
        $response = $this->middleware->handle($this->request, $next);

        // Assert: Removed origin is now rejected
        $this->assertFalse($this->nextCalled);
        $this->assertEquals(403, $response->getStatusCode());

        $data = $response->getData(true);
        $this->assertEquals(ErrorCode::ORIGIN_NOT_ALLOWED->value, $data['errorCode']);
    }

    // ============================================================================
    // Helper methods
    // ============================================================================

    /**
     * Expire a token by setting its expiration date to the past.
     *
     * @param NemesisToken $tokenModel The token to expire
     */
    private function expireTokenInDatabase(NemesisToken $tokenModel): void
    {
        DB::table('nemesis_tokens')
            ->where('id', $tokenModel->id)
            ->update(['expires_at' => '2023-01-01 00:00:00']);
    }

    /**
     * Reset the request for a new test case.
     */
    private function resetRequest(): void
    {
        $this->request = Request::create('/test', 'GET');
        $this->setupMockRoute();
    }
}
