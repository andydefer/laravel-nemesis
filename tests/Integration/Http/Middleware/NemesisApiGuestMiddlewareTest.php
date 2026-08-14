<?php

// tests/Integration/Http/Middleware/NemesisApiGuestMiddlewareTest.php

declare(strict_types=1);

namespace AndyDefer\Nemesis\Tests\Integration\Http\Middleware;

use AndyDefer\DomainStructures\Services\HydrationService;
use AndyDefer\Nemesis\Contracts\Configs\NemesisConfigInterface;
use AndyDefer\Nemesis\Models\NemesisToken;
use AndyDefer\Nemesis\Records\NemesisTokenRecord;
use AndyDefer\Nemesis\Services\NemesisService;
use AndyDefer\Nemesis\Tests\Fixtures\Models\TestUser;
use AndyDefer\Nemesis\Tests\IntegrationTestCase;
use AndyDefer\PhpVo\ValueObjects\DateTimeVO;
use Carbon\Carbon;
use Illuminate\Support\Facades\Route;

final class NemesisApiGuestMiddlewareTest extends IntegrationTestCase
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
        ]);

        // Register test routes with the API guest middleware
        Route::middleware('nemesis.api.guest')->get('/api-guest', function () {
            return response()->json(['message' => 'Guest Access']);
        });

        Route::middleware('nemesis.api.guest:admin')->get('/api-guest-ability', function () {
            return response()->json(['message' => 'Guest Access']);
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

    private function createTokenWithAbilitiesForUser(array $abilities): array
    {
        $record = $this->hydration->hydrate(NemesisTokenRecord::class, [
            'name' => 'API Token',
            'source' => 'api',
            'abilities' => $abilities,
        ]);

        return $this->service->createWithPlainToken($record, $this->user);
    }

    // ============================================================================
    // Guest Access Tests
    // ============================================================================

    public function test_middleware_allows_guest_access_without_token(): void
    {
        $response = $this->getJson('/api-guest');

        $response->assertStatus(200);
        $response->assertJson(['message' => 'Guest Access']);
    }

    public function test_middleware_returns_400_with_valid_token(): void
    {
        [$token, $plainToken] = $this->createTokenForUser();

        $response = $this->getJson('/api-guest', [
            'Authorization' => 'Bearer '.$plainToken,
        ]);

        $response->assertStatus(400);
        $response->assertJson([
            'errorCode' => 'ALREADY_AUTHENTICATED',
            'message' => 'Already authenticated',
            'status' => 400,
        ]);
    }

    public function test_middleware_allows_guest_access_with_invalid_token(): void
    {
        $response = $this->getJson('/api-guest', [
            'Authorization' => 'Bearer invalid-token',
        ]);

        $response->assertStatus(200);
        $response->assertJson(['message' => 'Guest Access']);
    }

    public function test_middleware_allows_guest_access_with_expired_token(): void
    {
        $expiredDate = new DateTimeVO(Carbon::getTestNow()->subDay()->format('Y-m-d\TH:i:sP'));
        [$token, $plainToken] = $this->createTokenForUser(expiresAt: $expiredDate);

        $response = $this->getJson('/api-guest', [
            'Authorization' => 'Bearer '.$plainToken,
        ]);

        $response->assertStatus(200);
        $response->assertJson(['message' => 'Guest Access']);
    }

    public function test_middleware_allows_guest_access_with_revoked_token(): void
    {
        [$token, $plainToken] = $this->createTokenForUser();
        $this->service->revoke($token);

        $response = $this->getJson('/api-guest', [
            'Authorization' => 'Bearer '.$plainToken,
        ]);

        $response->assertStatus(200);
        $response->assertJson(['message' => 'Guest Access']);
    }

    // ============================================================================
    // Ability Check Tests
    // ============================================================================

    public function test_middleware_returns_400_with_token_and_ability(): void
    {
        [$token, $plainToken] = $this->createTokenWithAbilitiesForUser(['admin']);

        $response = $this->getJson('/api-guest-ability', [
            'Authorization' => 'Bearer '.$plainToken,
        ]);

        $response->assertStatus(400);
        $response->assertJson([
            'errorCode' => 'ALREADY_AUTHENTICATED',
            'message' => 'Already authenticated',
            'status' => 400,
        ]);
    }

    public function test_middleware_allows_guest_access_with_token_without_ability(): void
    {
        [$token, $plainToken] = $this->createTokenWithAbilitiesForUser(['read']);

        $response = $this->getJson('/api-guest-ability', [
            'Authorization' => 'Bearer '.$plainToken,
        ]);

        $response->assertStatus(200);
        $response->assertJson(['message' => 'Guest Access']);
    }

    public function test_middleware_returns_400_with_token_and_no_abilities_when_ability_required(): void
    {
        [$token, $plainToken] = $this->createTokenForUser();

        $response = $this->getJson('/api-guest-ability', [
            'Authorization' => 'Bearer '.$plainToken,
        ]);

        $response->assertStatus(400);
        $response->assertJson([
            'errorCode' => 'ALREADY_AUTHENTICATED',
            'message' => 'Already authenticated',
            'status' => 400,
        ]);
    }

    // ============================================================================
    // Edge Cases Tests
    // ============================================================================

    public function test_middleware_handles_token_with_deleted_tokenable(): void
    {
        $user = TestUser::create(['name' => 'Temp', 'email' => 'temp@example.com']);

        $record = $this->hydration->hydrate(NemesisTokenRecord::class, ['name' => 'Temp Token']);
        [$token, $plainToken] = $this->service->createWithPlainToken($record, $user);

        $user->delete();

        $response = $this->getJson('/api-guest', [
            'Authorization' => 'Bearer '.$plainToken,
        ]);

        $response->assertStatus(200);
        $response->assertJson(['message' => 'Guest Access']);
    }

    public function test_middleware_handles_token_with_nonexistent_tokenable_type(): void
    {
        NemesisToken::create([
            'token_hash' => hash('sha256', 'bad-token'),
            'tokenable_type' => 'NonExistent\\Model\\Class',
            'tokenable_id' => 999,
            'name' => 'Bad Token',
            'source' => 'api',
        ]);

        $response = $this->getJson('/api-guest', [
            'Authorization' => 'Bearer bad-token',
        ]);

        $response->assertStatus(200);
        $response->assertJson(['message' => 'Guest Access']);
    }

    public function test_middleware_handles_multiple_guest_requests_without_token(): void
    {
        $response1 = $this->getJson('/api-guest');
        $response1->assertStatus(200);

        $response2 = $this->getJson('/api-guest');
        $response2->assertStatus(200);

        $response3 = $this->getJson('/api-guest');
        $response3->assertStatus(200);
    }

    public function test_middleware_handles_multiple_guest_requests_with_valid_token(): void
    {
        [$token, $plainToken] = $this->createTokenForUser();

        $response1 = $this->getJson('/api-guest', [
            'Authorization' => 'Bearer '.$plainToken,
        ]);
        $response1->assertStatus(400);

        $response2 = $this->getJson('/api-guest', [
            'Authorization' => 'Bearer '.$plainToken,
        ]);
        $response2->assertStatus(400);

        $response3 = $this->getJson('/api-guest', [
            'Authorization' => 'Bearer '.$plainToken,
        ]);
        $response3->assertStatus(400);
    }

    public function test_middleware_returns_400_response_structure(): void
    {
        [$token, $plainToken] = $this->createTokenForUser();

        $response = $this->getJson('/api-guest', [
            'Authorization' => 'Bearer '.$plainToken,
        ]);

        $response->assertStatus(400);
        $response->assertJsonStructure([
            'errorCode',
            'message',
            'status',
            'details',
        ]);
    }
}
