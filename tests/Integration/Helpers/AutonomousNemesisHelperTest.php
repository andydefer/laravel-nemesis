<?php

// tests/Integration/Helpers/AutonomousNemesisHelperTest.php

declare(strict_types=1);

namespace AndyDefer\Nemesis\Tests\Integration\Helpers;

use AndyDefer\DomainStructures\Abstracts\AbstractData;
use AndyDefer\DomainStructures\Collections\Utility\StringTypedCollection;
use AndyDefer\DomainStructures\Services\HydrationService;
use AndyDefer\DomainStructures\Utils\StrictDataObject;
use AndyDefer\Nemesis\Contracts\Configs\NemesisConfigInterface;
use AndyDefer\Nemesis\Contracts\Services\CookieTokenStorageInterface;
use AndyDefer\Nemesis\Contracts\Services\NemesisInterface;
use AndyDefer\Nemesis\Helpers\AutonomousNemesisHelper;
use AndyDefer\Nemesis\Models\NemesisToken;
use AndyDefer\Nemesis\Records\NemesisTokenRecord;
use AndyDefer\Nemesis\Tests\Fixtures\Models\TestInvalidModel;
use AndyDefer\Nemesis\Tests\Fixtures\Models\TestUser;
use AndyDefer\Nemesis\Tests\IntegrationTestCase;
use AndyDefer\PhpVo\ValueObjects\DateTimeVO;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cookie;

final class AutonomousNemesisHelperTest extends IntegrationTestCase
{
    private TestUser $user;

    private NemesisConfigInterface $config;

    private CookieTokenStorageInterface $cookieStorage;

    private HydrationService $hydration;

    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow(Carbon::create(2024, 1, 1, 12, 0, 0));

        $this->hydration = new HydrationService;
        $this->config = $this->app->make(NemesisConfigInterface::class);
        $this->cookieStorage = $this->app->make(CookieTokenStorageInterface::class);

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

    private function getAutonomousHelper(): AutonomousNemesisHelper
    {
        return $this->app->make(AutonomousNemesisHelper::class);
    }

    private function createTokenRecord(array $overrides = []): NemesisTokenRecord
    {
        $token = NemesisToken::create([
            'token_hash' => hash('sha256', 'test-token-123'),
            'tokenable_type' => $this->user->getMorphClass(),
            'tokenable_id' => $this->user->id,
            'name' => 'Test Token',
            'source' => 'web',
            'abilities' => ['read', 'write', 'delete'],
            'metadata' => ['ip' => '127.0.0.1', 'user_agent' => 'test'],
            'allowed_origins' => ['http://localhost', 'https://example.com'],
            'expires_at' => Carbon::now()->addDays(7),
            ...$overrides,
        ]);

        return $this->hydration->hydrate(NemesisTokenRecord::class, [
            'id' => $token->id,
            'token_hash' => $token->token_hash,
            'tokenable_type' => $token->tokenable_type,
            'tokenable_id' => $token->tokenable_id,
            'name' => $token->name,
            'source' => $token->source,
            'abilities' => $token->abilities ? StringTypedCollection::from($token->abilities) : null,
            'metadata' => $token->metadata ? StrictDataObject::from($token->metadata) : null,
            'allowed_origins' => $token->allowed_origins ? StringTypedCollection::from($token->allowed_origins) : null,
            'last_used_at' => $token->last_used_at ? DateTimeVO::from($token->last_used_at->toIso8601String()) : null,
            'expires_at' => $token->expires_at ? DateTimeVO::from($token->expires_at->toIso8601String()) : null,
            'created_at' => $token->created_at ? DateTimeVO::from($token->created_at->toIso8601String()) : null,
            'updated_at' => $token->updated_at ? DateTimeVO::from($token->updated_at->toIso8601String()) : null,
            'deleted_at' => null,
        ]);
    }

    private function storeTokenInCookie(string $plainToken): void
    {
        $this->cookieStorage->store($plainToken);
    }

    private function getCookieValueFromRequest(Request $request, string $cookieName): ?string
    {
        return $request->cookies->get($cookieName);
    }

    private function simulateAuthenticatedRequest(?NemesisTokenRecord $tokenRecord = null, ?TestUser $user = null): void
    {
        $data = [];

        if ($tokenRecord !== null) {
            $data['current_nemesis_token'] = $tokenRecord;
        }

        if ($user !== null) {
            $parameterName = $this->config->middlewareConfig()->parameter_name;
            $data[$parameterName] = $user;
            $data[$parameterName.'_format'] = $user->nemesisFormat();
        }

        $this->app['request']->merge($data);
    }

    private function createRequestWithCookie(string $cookieName, string $cookieValue): Request
    {
        $request = Request::create('/', 'GET');
        $request->cookies->set($cookieName, $cookieValue);

        return $request;
    }

    // ============================================================================
    // getCurrentToken Tests
    // ============================================================================

    public function test_get_current_token_from_middleware(): void
    {
        // Arrange
        $tokenRecord = $this->createTokenRecord();
        $this->simulateAuthenticatedRequest(tokenRecord: $tokenRecord);
        $helper = $this->getAutonomousHelper();

        // Act
        $result = $helper->getCurrentToken();

        // Assert
        $this->assertNotNull($result);
        $this->assertSame($tokenRecord->id, $result->id);
        $this->assertSame($tokenRecord->name, $result->name);
    }

    public function test_get_current_token_from_cookie_when_no_middleware(): void
    {
        // Arrange
        $plainToken = 'cookie-token-456';
        $token = NemesisToken::create([
            'token_hash' => hash('sha256', $plainToken),
            'tokenable_type' => $this->user->getMorphClass(),
            'tokenable_id' => $this->user->id,
            'name' => 'Cookie Token',
            'source' => 'web',
            'expires_at' => Carbon::now()->addDays(7),
        ]);

        // Stocker le token dans le cookie
        $this->cookieStorage->store($plainToken);

        // Créer une requête avec le cookie
        $request = $this->createRequestWithCookie('nemesis_token', $plainToken);
        $this->app->instance(Request::class, $request);

        $helper = $this->getAutonomousHelper();

        // Act
        $result = $helper->getCurrentToken();

        // Assert
        $this->assertNotNull($result);
        $this->assertSame($token->id, $result->id);
        $this->assertSame($token->name, $result->name);
    }

    public function test_get_current_token_returns_null_when_no_token(): void
    {
        // Arrange
        $helper = $this->getAutonomousHelper();

        // Act
        $result = $helper->getCurrentToken();

        // Assert
        $this->assertNull($result);
    }

    // ============================================================================
    // getCurrentAuthenticatable Tests
    // ============================================================================

    public function test_get_current_authenticatable_from_middleware(): void
    {
        // Arrange
        $this->simulateAuthenticatedRequest(user: $this->user);
        $helper = $this->getAutonomousHelper();

        // Act
        $result = $helper->getCurrentAuthenticatable();

        // Assert
        $this->assertNotNull($result);
        $this->assertSame($this->user->id, $result->id);
        $this->assertSame($this->user->name, $result->name);
    }

    public function test_get_current_authenticatable_from_cookie_when_no_middleware(): void
    {
        // Arrange
        $plainToken = 'cookie-token-456';
        NemesisToken::create([
            'token_hash' => hash('sha256', $plainToken),
            'tokenable_type' => $this->user->getMorphClass(),
            'tokenable_id' => $this->user->id,
            'name' => 'Cookie Token',
            'source' => 'web',
            'expires_at' => Carbon::now()->addDays(7),
        ]);

        $this->cookieStorage->store($plainToken);

        $request = $this->createRequestWithCookie('nemesis_token', $plainToken);
        $this->app->instance(Request::class, $request);

        $helper = $this->getAutonomousHelper();

        // Act
        $result = $helper->getCurrentAuthenticatable();

        // Assert
        $this->assertNotNull($result);
        $this->assertSame($this->user->id, $result->id);
        $this->assertSame($this->user->name, $result->name);
    }

    public function test_get_current_authenticatable_returns_null_when_no_token(): void
    {
        // Arrange
        $helper = $this->getAutonomousHelper();

        // Act
        $result = $helper->getCurrentAuthenticatable();

        // Assert
        $this->assertNull($result);
    }

    // ============================================================================
    // getCurrentAuthenticatableFormat Tests
    // ============================================================================

    public function test_get_current_authenticatable_format_from_middleware(): void
    {
        // Arrange
        $parameterName = $this->config->middlewareConfig()->parameter_name;
        $formatKey = $parameterName.'_format';
        $formattedRecord = $this->user->nemesisFormat();

        $this->app['request']->merge([$formatKey => $formattedRecord]);
        $helper = $this->getAutonomousHelper();

        // Act
        $result = $helper->getCurrentAuthenticatableFormat();

        // Assert
        $this->assertNotNull($result);
        $this->assertInstanceOf(AbstractData::class, $result);
        $this->assertArrayHasKey('id', $result->toArray());
        $this->assertArrayHasKey('name', $result->toArray());
        $this->assertArrayHasKey('email', $result->toArray());
    }

    public function test_get_current_authenticatable_format_from_user_when_no_middleware(): void
    {
        // Arrange
        $plainToken = 'cookie-token-456';
        NemesisToken::create([
            'token_hash' => hash('sha256', $plainToken),
            'tokenable_type' => $this->user->getMorphClass(),
            'tokenable_id' => $this->user->id,
            'name' => 'Cookie Token',
            'source' => 'web',
            'expires_at' => Carbon::now()->addDays(7),
        ]);

        $this->cookieStorage->store($plainToken);

        $request = $this->createRequestWithCookie('nemesis_token', $plainToken);
        $this->app->instance(Request::class, $request);

        $helper = $this->getAutonomousHelper();

        // Act
        $result = $helper->getCurrentAuthenticatableFormat();

        // Assert
        $this->assertNotNull($result);
        $this->assertInstanceOf(AbstractData::class, $result);
        $this->assertArrayHasKey('id', $result->toArray());
        $this->assertArrayHasKey('name', $result->toArray());
        $this->assertArrayHasKey('email', $result->toArray());
    }

    public function test_get_current_authenticatable_format_returns_null_when_no_token(): void
    {
        // Arrange
        $helper = $this->getAutonomousHelper();

        // Act
        $result = $helper->getCurrentAuthenticatableFormat();

        // Assert
        $this->assertNull($result);
    }

    // ============================================================================
    // Priorité Tests (middleware > cookie)
    // ============================================================================

    public function test_prioritizes_middleware_over_cookie_for_token(): void
    {
        // Arrange
        // Cookie token
        $plainTokenCookie = 'cookie-token';
        NemesisToken::create([
            'token_hash' => hash('sha256', $plainTokenCookie),
            'tokenable_type' => $this->user->getMorphClass(),
            'tokenable_id' => $this->user->id,
            'name' => 'Cookie Token',
            'source' => 'web',
            'expires_at' => Carbon::now()->addDays(7),
        ]);
        $this->cookieStorage->store($plainTokenCookie);

        // Middleware token
        $tokenRecord = $this->createTokenRecord(['name' => 'Middleware Token']);
        $this->simulateAuthenticatedRequest(tokenRecord: $tokenRecord);

        $helper = $this->getAutonomousHelper();

        // Act
        $result = $helper->getCurrentToken();

        // Assert
        $this->assertNotNull($result);
        $this->assertSame('Middleware Token', $result->name);
    }

    public function test_prioritizes_middleware_over_cookie_for_user(): void
    {
        // Arrange
        // Cookie user
        $plainToken = 'cookie-token';
        NemesisToken::create([
            'token_hash' => hash('sha256', $plainToken),
            'tokenable_type' => $this->user->getMorphClass(),
            'tokenable_id' => $this->user->id,
            'name' => 'Cookie Token',
            'source' => 'web',
            'expires_at' => Carbon::now()->addDays(7),
        ]);
        $this->cookieStorage->store($plainToken);

        // Middleware user
        $otherUser = TestUser::create([
            'name' => 'Jane Doe',
            'email' => 'jane@example.com',
        ]);
        $this->simulateAuthenticatedRequest(user: $otherUser);

        $helper = $this->getAutonomousHelper();

        // Act
        $result = $helper->getCurrentAuthenticatable();

        // Assert
        $this->assertNotNull($result);
        $this->assertSame($otherUser->id, $result->id);
        $this->assertSame($otherUser->name, $result->name);
    }

    // ============================================================================
    // hasCurrentToken Tests
    // ============================================================================

    public function test_has_current_token_with_middleware(): void
    {
        // Arrange
        $tokenRecord = $this->createTokenRecord();
        $this->simulateAuthenticatedRequest(tokenRecord: $tokenRecord);
        $helper = $this->getAutonomousHelper();

        // Act & Assert
        $this->assertTrue($helper->hasCurrentToken());
    }

    public function test_has_current_token_with_cookie(): void
    {
        // Arrange
        $plainToken = 'cookie-token';
        NemesisToken::create([
            'token_hash' => hash('sha256', $plainToken),
            'tokenable_type' => $this->user->getMorphClass(),
            'tokenable_id' => $this->user->id,
            'name' => 'Cookie Token',
            'source' => 'web',
            'expires_at' => Carbon::now()->addDays(7),
        ]);

        $this->cookieStorage->store($plainToken);

        $request = $this->createRequestWithCookie('nemesis_token', $plainToken);
        $this->app->instance(Request::class, $request);

        $helper = $this->getAutonomousHelper();

        // Act & Assert
        $this->assertTrue($helper->hasCurrentToken());
    }

    public function test_has_current_token_returns_false_when_no_token(): void
    {
        // Arrange
        $helper = $this->getAutonomousHelper();

        // Act & Assert
        $this->assertFalse($helper->hasCurrentToken());
    }

    // ============================================================================
    // hasCurrentAuthenticatable Tests
    // ============================================================================

    public function test_has_current_authenticatable_with_middleware(): void
    {
        // Arrange
        $this->simulateAuthenticatedRequest(user: $this->user);
        $helper = $this->getAutonomousHelper();

        // Act & Assert
        $this->assertTrue($helper->hasCurrentAuthenticatable());
    }

    public function test_has_current_authenticatable_with_cookie(): void
    {
        // Arrange
        $plainToken = 'cookie-token';
        NemesisToken::create([
            'token_hash' => hash('sha256', $plainToken),
            'tokenable_type' => $this->user->getMorphClass(),
            'tokenable_id' => $this->user->id,
            'name' => 'Cookie Token',
            'source' => 'web',
            'expires_at' => Carbon::now()->addDays(7),
        ]);

        $this->cookieStorage->store($plainToken);

        $request = $this->createRequestWithCookie('nemesis_token', $plainToken);
        $this->app->instance(Request::class, $request);

        $helper = $this->getAutonomousHelper();

        // Act & Assert
        $this->assertTrue($helper->hasCurrentAuthenticatable());
    }

    public function test_has_current_authenticatable_returns_false_when_no_token(): void
    {
        // Arrange
        $helper = $this->getAutonomousHelper();

        // Act & Assert
        $this->assertFalse($helper->hasCurrentAuthenticatable());
    }

    // ============================================================================
    // Token Information Tests
    // ============================================================================

    public function test_get_token_properties_with_cookie(): void
    {
        // Arrange
        $plainToken = 'cookie-token';
        $token = NemesisToken::create([
            'token_hash' => hash('sha256', $plainToken),
            'tokenable_type' => $this->user->getMorphClass(),
            'tokenable_id' => $this->user->id,
            'name' => 'Cookie Token',
            'source' => 'web',
            'abilities' => ['read', 'write'],
            'metadata' => ['key' => 'value'],
            'allowed_origins' => ['http://localhost'],
            'expires_at' => Carbon::now()->addDays(7),
        ]);

        $this->cookieStorage->store($plainToken);

        $request = $this->createRequestWithCookie('nemesis_token', $plainToken);
        $this->app->instance(Request::class, $request);

        $helper = $this->getAutonomousHelper();

        // Act & Assert
        $this->assertSame($token->id, $helper->getTokenId());
        $this->assertSame($token->tokenable_id, $helper->getTokenableId());
        $this->assertSame($token->tokenable_type, $helper->getTokenableType());
        $this->assertSame($token->name, $helper->getTokenName());
        $this->assertSame($token->source, $helper->getTokenSource());
        $this->assertTrue($helper->tokenHasAbility('read'));
        $this->assertFalse($helper->tokenHasAbility('admin'));
        $this->assertNotNull($helper->getTokenMetadata());
        $this->assertTrue($helper->isOriginAllowed('http://localhost'));
        $this->assertFalse($helper->isOriginAllowed('https://malicious.com'));
    }

    // ============================================================================
    // Token Expiration Tests
    // ============================================================================

    public function test_is_token_expired_false_when_valid(): void
    {
        // Arrange
        $plainToken = 'valid-token';
        NemesisToken::create([
            'token_hash' => hash('sha256', $plainToken),
            'tokenable_type' => $this->user->getMorphClass(),
            'tokenable_id' => $this->user->id,
            'name' => 'Valid Token',
            'source' => 'web',
            'expires_at' => Carbon::now()->addDays(7),
        ]);

        $this->cookieStorage->store($plainToken);

        $request = $this->createRequestWithCookie('nemesis_token', $plainToken);
        $this->app->instance(Request::class, $request);

        $helper = $this->getAutonomousHelper();

        // Act & Assert
        $this->assertFalse($helper->isTokenExpired());
        $this->assertTrue($helper->isTokenValid());
    }

    public function test_is_token_expired_true_when_expired(): void
    {
        // Arrange
        $plainToken = 'expired-token';
        NemesisToken::create([
            'token_hash' => hash('sha256', $plainToken),
            'tokenable_type' => $this->user->getMorphClass(),
            'tokenable_id' => $this->user->id,
            'name' => 'Expired Token',
            'source' => 'web',
            'expires_at' => Carbon::now()->subDays(1),
        ]);

        $this->cookieStorage->store($plainToken);

        $request = $this->createRequestWithCookie('nemesis_token', $plainToken);
        $this->app->instance(Request::class, $request);

        $helper = $this->getAutonomousHelper();

        // Act & Assert
        $this->assertTrue($helper->isTokenExpired());
        $this->assertFalse($helper->isTokenValid());
    }

    public function test_is_token_expired_true_when_no_token(): void
    {
        // Arrange
        $helper = $this->getAutonomousHelper();

        // Act & Assert
        $this->assertTrue($helper->isTokenExpired());
        $this->assertFalse($helper->isTokenValid());
    }

    // ============================================================================
    // Authentication Status Tests
    // ============================================================================

    public function test_is_authenticated_with_middleware(): void
    {
        // Arrange
        $tokenRecord = $this->createTokenRecord();
        $this->simulateAuthenticatedRequest(tokenRecord: $tokenRecord, user: $this->user);
        $helper = $this->getAutonomousHelper();

        // Act & Assert
        $this->assertTrue($helper->isAuthenticated());
        $this->assertFalse($helper->isGuest());
    }

    public function test_is_authenticated_with_cookie(): void
    {
        // Arrange
        $plainToken = 'valid-token';
        NemesisToken::create([
            'token_hash' => hash('sha256', $plainToken),
            'tokenable_type' => $this->user->getMorphClass(),
            'tokenable_id' => $this->user->id,
            'name' => 'Valid Token',
            'source' => 'web',
            'expires_at' => Carbon::now()->addDays(7),
        ]);

        $this->cookieStorage->store($plainToken);

        $request = $this->createRequestWithCookie('nemesis_token', $plainToken);
        $this->app->instance(Request::class, $request);

        $helper = $this->getAutonomousHelper();

        // Act & Assert
        $this->assertTrue($helper->isAuthenticated());
        $this->assertFalse($helper->isGuest());
    }

    public function test_is_authenticated_returns_false_when_no_token(): void
    {
        // Arrange
        $helper = $this->getAutonomousHelper();

        // Act & Assert
        $this->assertFalse($helper->isAuthenticated());
        $this->assertTrue($helper->isGuest());
    }

    public function test_is_authenticated_returns_false_when_token_expired(): void
    {
        // Arrange
        $plainToken = 'expired-token';
        NemesisToken::create([
            'token_hash' => hash('sha256', $plainToken),
            'tokenable_type' => $this->user->getMorphClass(),
            'tokenable_id' => $this->user->id,
            'name' => 'Expired Token',
            'source' => 'web',
            'expires_at' => Carbon::now()->subDays(1),
        ]);

        $this->cookieStorage->store($plainToken);

        $request = $this->createRequestWithCookie('nemesis_token', $plainToken);
        $this->app->instance(Request::class, $request);

        $helper = $this->getAutonomousHelper();

        // Act & Assert
        $this->assertFalse($helper->isAuthenticated());
        $this->assertTrue($helper->isGuest());
    }

    // ============================================================================
    // Clear Cache Tests
    // ============================================================================

    public function test_clear_resets_cached_values(): void
    {
        // Arrange
        $plainToken = 'cookie-token';
        $token = NemesisToken::create([
            'token_hash' => hash('sha256', $plainToken),
            'tokenable_type' => $this->user->getMorphClass(),
            'tokenable_id' => $this->user->id,
            'name' => 'Cookie Token',
            'source' => 'web',
            'expires_at' => Carbon::now()->addDays(7),
        ]);

        $this->cookieStorage->store($plainToken);

        $request = $this->createRequestWithCookie('nemesis_token', $plainToken);
        $this->app->instance(Request::class, $request);

        $helper = $this->getAutonomousHelper();

        // Act - Remplir le cache
        $helper->getCurrentToken();
        $helper->getCurrentAuthenticatable();

        // Vérifier que le cache est rempli
        $this->assertNotNull($helper->cachedToken);
        $this->assertNotNull($helper->cachedAuthenticatable);

        // Act - Clear cache
        $helper->clear();

        // Assert - Le cache est vidé sur le MÊME helper
        $this->assertNull($helper->cachedToken);
        $this->assertNull($helper->cachedAuthenticatable);

        // Vérifier que les méthodes retournent null car le cache est vidé
        // et que la requête contient toujours le cookie, mais le cache est prioritaire
        // (si cache null, il relit la requête, donc ça retombera sur le cookie)
        // Donc pour vraiment tester clear(), on vérifie seulement le cache
    }

    // ============================================================================
    // Edge Cases Tests
    // ============================================================================

    public function test_handles_token_without_abilities_gracefully(): void
    {
        // Arrange
        $plainToken = 'no-abilities-token';
        NemesisToken::create([
            'token_hash' => hash('sha256', $plainToken),
            'tokenable_type' => $this->user->getMorphClass(),
            'tokenable_id' => $this->user->id,
            'name' => 'No Abilities Token',
            'source' => 'web',
            'abilities' => null,
            'expires_at' => Carbon::now()->addDays(7),
        ]);

        $this->cookieStorage->store($plainToken);

        $request = $this->createRequestWithCookie('nemesis_token', $plainToken);
        $this->app->instance(Request::class, $request);

        $helper = $this->getAutonomousHelper();

        // Act & Assert
        $this->assertNull($helper->getTokenAbilities());
        $this->assertFalse($helper->tokenHasAbility('read'));
        $this->assertFalse($helper->tokenHasAllAbilities(['read', 'write']));
    }

    public function test_handles_token_without_metadata_gracefully(): void
    {
        // Arrange
        $plainToken = 'no-metadata-token';
        NemesisToken::create([
            'token_hash' => hash('sha256', $plainToken),
            'tokenable_type' => $this->user->getMorphClass(),
            'tokenable_id' => $this->user->id,
            'name' => 'No Metadata Token',
            'source' => 'web',
            'metadata' => null,
            'expires_at' => Carbon::now()->addDays(7),
        ]);

        $this->cookieStorage->store($plainToken);

        $request = $this->createRequestWithCookie('nemesis_token', $plainToken);
        $this->app->instance(Request::class, $request);

        $helper = $this->getAutonomousHelper();

        // Act & Assert
        $this->assertNull($helper->getTokenMetadata());
    }

    public function test_handles_token_without_allowed_origins_gracefully(): void
    {
        // Arrange
        $plainToken = 'no-origins-token';
        NemesisToken::create([
            'token_hash' => hash('sha256', $plainToken),
            'tokenable_type' => $this->user->getMorphClass(),
            'tokenable_id' => $this->user->id,
            'name' => 'No Origins Token',
            'source' => 'web',
            'allowed_origins' => null,
            'expires_at' => Carbon::now()->addDays(7),
        ]);

        $this->cookieStorage->store($plainToken);

        $request = $this->createRequestWithCookie('nemesis_token', $plainToken);
        $this->app->instance(Request::class, $request);

        $helper = $this->getAutonomousHelper();

        // Act & Assert
        $this->assertNull($helper->getTokenAllowedOrigins());
        $this->assertFalse($helper->isOriginAllowed('http://localhost'));
    }

    public function test_handles_model_without_must_nemesis_interface(): void
    {
        // Arrange
        // Utiliser TestInvalidModel qui N'IMPLEMENTE PAS MustNemesis
        $userWithoutNemesis = TestInvalidModel::create([
            'name' => 'No Nemesis',
        ]);

        $plainToken = 'no-nemesis-token';

        // Créer un token pour cet utilisateur
        $token = NemesisToken::create([
            'token_hash' => hash('sha256', $plainToken),
            'tokenable_type' => $userWithoutNemesis->getMorphClass(),
            'tokenable_id' => $userWithoutNemesis->id,
            'name' => 'No Nemesis Token',
            'source' => 'web',
            'expires_at' => Carbon::now()->addDays(7),
        ]);

        $this->cookieStorage->store($plainToken);

        $request = $this->createRequestWithCookie('nemesis_token', $plainToken);
        $this->app->instance(Request::class, $request);

        $helper = $this->getAutonomousHelper();

        // Act
        $result = $helper->getCurrentAuthenticatableFormat();

        // Assert
        $this->assertNull($result);
    }

    // ============================================================================
    // Cookie Name Configuration Tests
    // ============================================================================

    public function test_handles_cookie_with_custom_name(): void
    {
        // Arrange
        $customCookieName = 'custom_auth_token';
        config()->set('nemesis.web.cookie_name', $customCookieName);

        $this->app->forgetInstance(NemesisConfigInterface::class);
        $this->app->make(NemesisConfigInterface::class);
        $this->app->forgetInstance(CookieTokenStorageInterface::class);
        $this->app->make(CookieTokenStorageInterface::class);

        $plainToken = 'custom-cookie-token';
        NemesisToken::create([
            'token_hash' => hash('sha256', $plainToken),
            'tokenable_type' => $this->user->getMorphClass(),
            'tokenable_id' => $this->user->id,
            'name' => 'Custom Cookie Token',
            'source' => 'web',
            'expires_at' => Carbon::now()->addDays(7),
        ]);

        $this->cookieStorage->store($plainToken);

        $request = $this->createRequestWithCookie($customCookieName, $plainToken);
        $this->app->instance(Request::class, $request);

        $cookieStorage = $this->app->make(CookieTokenStorageInterface::class);
        $helper = new AutonomousNemesisHelper(
            $cookieStorage,
            $this->app->make(NemesisInterface::class)
        );

        // Act
        $result = $helper->getCurrentToken();

        // Assert
        $this->assertNotNull($result);
        $this->assertSame('Custom Cookie Token', $result->name);

        // Cleanup
        config()->set('nemesis.web.cookie_name', 'nemesis_token');
        $this->app->forgetInstance(NemesisConfigInterface::class);
        $this->app->make(NemesisConfigInterface::class);
        $this->app->forgetInstance(CookieTokenStorageInterface::class);
        $this->app->make(CookieTokenStorageInterface::class);
    }
}
