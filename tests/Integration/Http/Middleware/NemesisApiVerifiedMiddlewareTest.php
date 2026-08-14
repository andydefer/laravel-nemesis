<?php

// tests/Integration/Http/Middleware/NemesisApiVerifiedMiddlewareTest.php

declare(strict_types=1);

namespace AndyDefer\Nemesis\Tests\Integration\Http\Middleware;

use AndyDefer\DomainStructures\Services\HydrationService;
use AndyDefer\Nemesis\Contracts\Configs\NemesisConfigInterface;
use AndyDefer\Nemesis\Models\NemesisToken;
use AndyDefer\Nemesis\Records\NemesisTokenRecord;
use AndyDefer\Nemesis\Services\NemesisService;
use AndyDefer\Nemesis\Tests\Fixtures\Models\TestCheckPoint;
use AndyDefer\Nemesis\Tests\Fixtures\Models\TestUser;
use AndyDefer\Nemesis\Tests\IntegrationTestCase;
use AndyDefer\PhpVo\ValueObjects\DateTimeVO;
use Carbon\Carbon;
use Illuminate\Support\Facades\Route;

final class NemesisApiVerifiedMiddlewareTest extends IntegrationTestCase
{
    private TestUser $user;

    private NemesisConfigInterface $config;

    private NemesisService $service;

    private HydrationService $hydration;

    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow(Carbon::create(2024, 1, 1, 12, 0, 0));

        $this->hydration = new HydrationService;
        $this->config = $this->app->make(NemesisConfigInterface::class);
        $this->service = $this->app->make(NemesisService::class);

        $this->user = TestUser::create([
            'name' => 'John Doe',
            'email' => 'john@example.com',
            'email_verified_at' => null,
        ]);

        // Register test routes with the API verified middleware
        Route::middleware('nemesis.api.verified')->get('/api-verified-protected', function () {
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
            'name' => $name ?? 'API Token',
            'source' => $source ?? 'api',
            'expires_at' => $expiresAt,
        ]);

        return $this->service->createWithPlainToken($record, $this->user);
    }

    private function createTokenForCheckPoint(TestCheckPoint $checkpoint): array
    {
        $record = $this->hydration->hydrate(NemesisTokenRecord::class, [
            'name' => 'CheckPoint Token',
            'source' => 'api',
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

        // Act: Make a request with the token
        $response = $this->getJson('/api-verified-protected', [
            'Authorization' => 'Bearer '.$plainToken,
        ]);

        // Assert: The request should be successful
        $response->assertStatus(200);
        $response->assertJson(['message' => 'OK']);
    }

    // ============================================================================
    // Email Verification Failure Tests
    // ============================================================================

    public function test_middleware_returns_403_when_email_not_verified(): void
    {
        // Arrange: Create a user with unverified email
        $this->user->email_verified_at = null;
        $this->user->save();

        [$token, $plainToken] = $this->createTokenForUser();

        // Act: Make a request with the token
        $response = $this->getJson('/api-verified-protected', [
            'Authorization' => 'Bearer '.$plainToken,
        ]);

        // Assert: The request should return 403
        $response->assertStatus(403);
        $response->assertJson([
            'errorCode' => 'EMAIL_NOT_VERIFIED',
            'message' => 'Email not verified. Please verify your email address.',
            'status' => 403,
        ]);
    }

    // ============================================================================
    // Authentication Failure Tests
    // ============================================================================

    public function test_middleware_returns_401_when_no_token(): void
    {
        // Act: Make a request without a token
        $response = $this->getJson('/api-verified-protected');

        // Assert: The request should return 401
        $response->assertStatus(401);
        $response->assertJson([
            'errorCode' => 'MISSING_TOKEN',
            'message' => 'Token not provided',
        ]);
    }

    public function test_middleware_returns_401_when_invalid_token(): void
    {
        // Act: Make a request with an invalid token
        $response = $this->getJson('/api-verified-protected', [
            'Authorization' => 'Bearer invalid-token',
        ]);

        // Assert: The request should return 401
        $response->assertStatus(401);
        $response->assertJson([
            'errorCode' => 'INVALID_TOKEN',
            'message' => 'Invalid token',
        ]);
    }

    public function test_middleware_returns_401_when_token_expired(): void
    {
        // Arrange: Create an expired token
        $expiredDate = new DateTimeVO(Carbon::getTestNow()->subDay()->format('Y-m-d\TH:i:sP'));
        [$token, $plainToken] = $this->createTokenForUser(expiresAt: $expiredDate);

        // Act: Make a request with the expired token
        $response = $this->getJson('/api-verified-protected', [
            'Authorization' => 'Bearer '.$plainToken,
        ]);

        // Assert: The request should return 401
        $response->assertStatus(401);
        $response->assertJson([
            'errorCode' => 'TOKEN_EXPIRED',
            'message' => 'Token has expired',
            'status' => 401,
        ]);
    }

    public function test_middleware_returns_401_when_token_revoked(): void
    {
        // Arrange: Create a token and revoke it
        [$token, $plainToken] = $this->createTokenForUser();
        $this->service->revoke($token);

        // Act: Make a request with the revoked token
        $response = $this->getJson('/api-verified-protected', [
            'Authorization' => 'Bearer '.$plainToken,
        ]);

        // Assert: The request should return 401
        $response->assertStatus(401);
        $response->assertJson([
            'errorCode' => 'INVALID_TOKEN',
            'message' => 'Invalid token',
        ]);
    }

    // ============================================================================
    // Edge Cases Tests
    // ============================================================================

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
        $response = $this->getJson('/api-verified-protected', [
            'Authorization' => 'Bearer '.$plainToken,
        ]);

        // Assert: The request should return 500
        $response->assertStatus(500);
        $response->assertJson([
            'errorCode' => 'MODEL_MISSING_EMAIL_VERIFIED_AT',
            'message' => 'Model must have email_verified_at field',
            'status' => 500,
        ]);
    }

    public function test_middleware_returns_401_when_tokenable_not_found(): void
    {
        // Arrange: Create a user, create a token, then delete the user
        $user = TestUser::create(['name' => 'Temp', 'email' => 'temp@example.com']);

        $record = $this->hydration->hydrate(NemesisTokenRecord::class, ['name' => 'Temp Token']);
        [$token, $plainToken] = $this->service->createWithPlainToken($record, $user);

        $user->delete();

        // Act: Make a request with the token
        $response = $this->getJson('/api-verified-protected', [
            'Authorization' => 'Bearer '.$plainToken,
        ]);

        // Assert: The request should return 401
        $response->assertStatus(401);
        $response->assertJson([
            'errorCode' => 'INVALID_TOKEN',
            'message' => 'Invalid token',
        ]);
    }

    public function test_middleware_handles_token_with_nonexistent_tokenable_type(): void
    {
        // Arrange: Create a token with a non-existent tokenable type
        NemesisToken::create([
            'token_hash' => hash('sha256', 'bad-token'),
            'tokenable_type' => 'NonExistent\\Model\\Class',
            'tokenable_id' => 999,
            'name' => 'Bad Token',
            'source' => 'api',
        ]);

        // Act: Make a request with the invalid token
        $response = $this->getJson('/api-verified-protected', [
            'Authorization' => 'Bearer bad-token',
        ]);

        // Assert: The request should return 401
        $response->assertStatus(401);
        $response->assertJson([
            'errorCode' => 'INVALID_TOKEN',
            'message' => 'Invalid token',
        ]);
    }

    public function test_middleware_updates_last_used_on_successful_authentication(): void
    {
        // Arrange: Create a user with verified email
        $this->user->email_verified_at = Carbon::getTestNow();
        $this->user->save();

        [$token, $plainToken] = $this->createTokenForUser();
        $this->assertNull($token->last_used_at);

        // Act: Make a request with the token
        $response = $this->getJson('/api-verified-protected', [
            'Authorization' => 'Bearer '.$plainToken,
        ]);

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
        $response1 = $this->getJson('/api-verified-protected', [
            'Authorization' => 'Bearer '.$plainToken,
        ]);
        $response1->assertStatus(200);

        $response2 = $this->getJson('/api-verified-protected', [
            'Authorization' => 'Bearer '.$plainToken,
        ]);
        $response2->assertStatus(200);

        $response3 = $this->getJson('/api-verified-protected', [
            'Authorization' => 'Bearer '.$plainToken,
        ]);
        $response3->assertStatus(200);
    }

    public function test_middleware_returns_401_response_structure(): void
    {
        // Act: Make a request without a token
        $response = $this->getJson('/api-verified-protected');

        // Assert: The request should return 401 with proper structure
        $response->assertStatus(401);
        $response->assertJsonStructure([
            'errorCode',
            'message',
            'status',
            'details',
        ]);
    }

    public function test_middleware_returns_403_response_structure(): void
    {
        // Arrange: Create a user with unverified email
        $this->user->email_verified_at = null;
        $this->user->save();

        [$token, $plainToken] = $this->createTokenForUser();

        // Act: Make a request with the token
        $response = $this->getJson('/api-verified-protected', [
            'Authorization' => 'Bearer '.$plainToken,
        ]);

        // Assert: The request should return 403 with proper structure
        $response->assertStatus(403);
        $response->assertJsonStructure([
            'errorCode',
            'message',
            'status',
            'details',
        ]);
    }
}
