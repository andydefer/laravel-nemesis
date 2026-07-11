<?php

// tests/Integration/Helpers/NemesisHelperTest.php

declare(strict_types=1);

namespace AndyDefer\Nemesis\Tests\Integration\Helpers;

use AndyDefer\DomainStructures\Collections\Utility\StringTypedCollection;
use AndyDefer\DomainStructures\Services\HydrationService;
use AndyDefer\DomainStructures\Utils\StrictDataObject;
use AndyDefer\Nemesis\Contracts\Configs\NemesisConfigInterface;
use AndyDefer\Nemesis\Helpers\NemesisHelper;
use AndyDefer\Nemesis\Models\NemesisToken;
use AndyDefer\Nemesis\Records\MiddlewareConfigRecord;
use AndyDefer\Nemesis\Records\NemesisTokenRecord;
use AndyDefer\Nemesis\Tests\Fixtures\Models\TestUser;
use AndyDefer\Nemesis\Tests\IntegrationTestCase;
use AndyDefer\PhpVo\ValueObjects\DateTimeVO;
use Carbon\Carbon;
use Illuminate\Http\Request;

final class NemesisHelperTest extends IntegrationTestCase
{
    private TestUser $user;

    private NemesisConfigInterface $config;

    private HydrationService $hydration;

    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow(Carbon::create(2024, 1, 1, 12, 0, 0));

        $this->hydration = new HydrationService;
        $this->config = $this->app->make(NemesisConfigInterface::class);

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

    private function getHelper(): NemesisHelper
    {
        return $this->app->make(NemesisHelper::class);
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

    // ============================================================================
    // getCurrentToken Tests
    // ============================================================================

    public function test_get_current_token_returns_token_when_present(): void
    {
        // Arrange
        $tokenRecord = $this->createTokenRecord();
        $this->simulateAuthenticatedRequest(tokenRecord: $tokenRecord);
        $helper = $this->getHelper();

        // Act
        $result = $helper->getCurrentToken();

        // Assert
        $this->assertNotNull($result);
        $this->assertSame($tokenRecord->id, $result->id);
        $this->assertSame($tokenRecord->name, $result->name);
    }

    public function test_get_current_token_returns_null_when_no_token(): void
    {
        // Arrange
        $this->simulateAuthenticatedRequest();
        $helper = $this->getHelper();

        // Act
        $result = $helper->getCurrentToken();

        // Assert
        $this->assertNull($result);
    }

    public function test_get_current_token_returns_null_when_token_is_not_token_record(): void
    {
        // Arrange
        $this->app['request']->merge(['current_nemesis_token' => 'not-a-token-record']);
        $helper = $this->getHelper();

        // Act
        $result = $helper->getCurrentToken();

        // Assert
        $this->assertNull($result);
    }

    // ============================================================================
    // getCurrentAuthenticatable Tests
    // ============================================================================

    public function test_get_current_authenticatable_returns_model_when_present(): void
    {
        // Arrange
        $this->simulateAuthenticatedRequest(user: $this->user);
        $helper = $this->getHelper();

        // Act
        $result = $helper->getCurrentAuthenticatable();

        // Assert
        $this->assertNotNull($result);
        $this->assertSame($this->user->id, $result->id);
        $this->assertSame($this->user->name, $result->name);
    }

    public function test_get_current_authenticatable_returns_null_when_no_authenticatable(): void
    {
        // Arrange
        $this->simulateAuthenticatedRequest();
        $helper = $this->getHelper();

        // Act
        $result = $helper->getCurrentAuthenticatable();

        // Assert
        $this->assertNull($result);
    }

    public function test_get_current_authenticatable_uses_custom_parameter_name_from_config(): void
    {
        // Arrange
        $customParameterName = 'customAuth';

        $mockConfig = $this->createStub(NemesisConfigInterface::class);
        $middlewareConfig = $this->hydration->hydrate(MiddlewareConfigRecord::class, [
            'parameter_name' => $customParameterName,
            'token_header' => 'Authorization',
            'security_headers' => true,
            'validate_origin' => true,
        ]);
        $mockConfig->method('middlewareConfig')->willReturn($middlewareConfig);

        $this->app->instance(NemesisConfigInterface::class, $mockConfig);

        $request = $this->app->make(Request::class);
        $request->merge([$customParameterName => $this->user]);
        $this->app->instance(Request::class, $request);

        $helper = $this->getHelper();

        // Act
        $result = $helper->getCurrentAuthenticatable();

        // Assert
        $this->assertNotNull($result);
        $this->assertSame($this->user->id, $result->id);
    }

    // ============================================================================
    // getCurrentAuthenticatableFormat Tests
    // ============================================================================

    public function test_get_current_authenticatable_format_returns_record_when_present(): void
    {
        // Arrange
        $parameterName = $this->config->middlewareConfig()->parameter_name;
        $formatKey = $parameterName.'_format';
        $formattedRecord = $this->user->nemesisFormat();

        $this->app['request']->merge([$formatKey => $formattedRecord]);
        $helper = $this->getHelper();

        // Act
        $result = $helper->getCurrentAuthenticatableFormat();

        // Assert
        $this->assertNotNull($result);
        $this->assertArrayHasKey('id', $result->toArray());
        $this->assertArrayHasKey('name', $result->toArray());
        $this->assertArrayHasKey('email', $result->toArray());
    }

    public function test_get_current_authenticatable_format_returns_null_when_no_format(): void
    {
        // Arrange
        $this->simulateAuthenticatedRequest();
        $helper = $this->getHelper();

        // Act
        $result = $helper->getCurrentAuthenticatableFormat();

        // Assert
        $this->assertNull($result);
    }

    public function test_get_current_authenticatable_format_returns_null_when_format_is_not_record(): void
    {
        // Arrange
        $parameterName = $this->config->middlewareConfig()->parameter_name;
        $formatKey = $parameterName.'_format';
        $this->app['request']->merge([$formatKey => ['not' => 'a record']]);
        $helper = $this->getHelper();

        // Act
        $result = $helper->getCurrentAuthenticatableFormat();

        // Assert
        $this->assertNull($result);
    }

    // ============================================================================
    // hasCurrentToken Tests
    // ============================================================================

    public function test_has_current_token_returns_true_when_token_present(): void
    {
        // Arrange
        $tokenRecord = $this->createTokenRecord();
        $this->simulateAuthenticatedRequest(tokenRecord: $tokenRecord);
        $helper = $this->getHelper();

        // Act
        $result = $helper->hasCurrentToken();

        // Assert
        $this->assertTrue($result);
    }

    public function test_has_current_token_returns_false_when_no_token(): void
    {
        // Arrange
        $this->simulateAuthenticatedRequest();
        $helper = $this->getHelper();

        // Act
        $result = $helper->hasCurrentToken();

        // Assert
        $this->assertFalse($result);
    }

    // ============================================================================
    // hasCurrentAuthenticatable Tests
    // ============================================================================

    public function test_has_current_authenticatable_returns_true_when_authenticatable_present(): void
    {
        // Arrange
        $this->simulateAuthenticatedRequest(user: $this->user);
        $helper = $this->getHelper();

        // Act
        $result = $helper->hasCurrentAuthenticatable();

        // Assert
        $this->assertTrue($result);
    }

    public function test_has_current_authenticatable_returns_false_when_no_authenticatable(): void
    {
        // Arrange
        $this->simulateAuthenticatedRequest();
        $helper = $this->getHelper();

        // Act
        $result = $helper->hasCurrentAuthenticatable();

        // Assert
        $this->assertFalse($result);
    }

    // ============================================================================
    // New Methods Tests - Token Information
    // ============================================================================

    public function test_get_token_id_returns_token_id_when_token_present(): void
    {
        // Arrange
        $tokenRecord = $this->createTokenRecord();
        $this->simulateAuthenticatedRequest(tokenRecord: $tokenRecord);
        $helper = $this->getHelper();

        // Act
        $result = $helper->getTokenId();

        // Assert
        $this->assertSame($tokenRecord->id, $result);
    }

    public function test_get_token_id_returns_null_when_no_token(): void
    {
        // Arrange
        $this->simulateAuthenticatedRequest();
        $helper = $this->getHelper();

        // Act
        $result = $helper->getTokenId();

        // Assert
        $this->assertNull($result);
    }

    public function test_get_tokenable_id_returns_tokenable_id_when_token_present(): void
    {
        // Arrange
        $tokenRecord = $this->createTokenRecord();
        $this->simulateAuthenticatedRequest(tokenRecord: $tokenRecord);
        $helper = $this->getHelper();

        // Act
        $result = $helper->getTokenableId();

        // Assert
        $this->assertSame($tokenRecord->tokenable_id, $result);
    }

    public function test_get_tokenable_id_returns_null_when_no_token(): void
    {
        // Arrange
        $this->simulateAuthenticatedRequest();
        $helper = $this->getHelper();

        // Act
        $result = $helper->getTokenableId();

        // Assert
        $this->assertNull($result);
    }

    public function test_get_tokenable_type_returns_tokenable_type_when_token_present(): void
    {
        // Arrange
        $tokenRecord = $this->createTokenRecord();
        $this->simulateAuthenticatedRequest(tokenRecord: $tokenRecord);
        $helper = $this->getHelper();

        // Act
        $result = $helper->getTokenableType();

        // Assert
        $this->assertSame($tokenRecord->tokenable_type, $result);
    }

    public function test_get_tokenable_type_returns_null_when_no_token(): void
    {
        // Arrange
        $this->simulateAuthenticatedRequest();
        $helper = $this->getHelper();

        // Act
        $result = $helper->getTokenableType();

        // Assert
        $this->assertNull($result);
    }

    public function test_get_token_name_returns_token_name_when_token_present(): void
    {
        // Arrange
        $tokenRecord = $this->createTokenRecord();
        $this->simulateAuthenticatedRequest(tokenRecord: $tokenRecord);
        $helper = $this->getHelper();

        // Act
        $result = $helper->getTokenName();

        // Assert
        $this->assertSame($tokenRecord->name, $result);
    }

    public function test_get_token_name_returns_null_when_no_token(): void
    {
        // Arrange
        $this->simulateAuthenticatedRequest();
        $helper = $this->getHelper();

        // Act
        $result = $helper->getTokenName();

        // Assert
        $this->assertNull($result);
    }

    public function test_get_token_source_returns_token_source_when_token_present(): void
    {
        // Arrange
        $tokenRecord = $this->createTokenRecord();
        $this->simulateAuthenticatedRequest(tokenRecord: $tokenRecord);
        $helper = $this->getHelper();

        // Act
        $result = $helper->getTokenSource();

        // Assert
        $this->assertSame($tokenRecord->source, $result);
    }

    public function test_get_token_source_returns_null_when_no_token(): void
    {
        // Arrange
        $this->simulateAuthenticatedRequest();
        $helper = $this->getHelper();

        // Act
        $result = $helper->getTokenSource();

        // Assert
        $this->assertNull($result);
    }

    // ============================================================================
    // Token Abilities Tests
    // ============================================================================

    public function test_get_token_abilities_returns_abilities_when_token_present(): void
    {
        // Arrange
        $tokenRecord = $this->createTokenRecord();
        $this->simulateAuthenticatedRequest(tokenRecord: $tokenRecord);
        $helper = $this->getHelper();

        // Act
        $result = $helper->getTokenAbilities();

        // Assert
        $this->assertNotNull($result);
        $this->assertInstanceOf(StringTypedCollection::class, $result);
        $this->assertTrue($result->contains('read'));
        $this->assertTrue($result->contains('write'));
        $this->assertTrue($result->contains('delete'));
    }

    public function test_get_token_abilities_returns_null_when_no_token(): void
    {
        // Arrange
        $this->simulateAuthenticatedRequest();
        $helper = $this->getHelper();

        // Act
        $result = $helper->getTokenAbilities();

        // Assert
        $this->assertNull($result);
    }

    public function test_token_has_ability_returns_true_when_ability_exists(): void
    {
        // Arrange
        $tokenRecord = $this->createTokenRecord();
        $this->simulateAuthenticatedRequest(tokenRecord: $tokenRecord);
        $helper = $this->getHelper();

        // Act & Assert
        $this->assertTrue($helper->tokenHasAbility('read'));
        $this->assertTrue($helper->tokenHasAbility('write'));
        $this->assertTrue($helper->tokenHasAbility('delete'));
    }

    public function test_token_has_ability_returns_false_when_ability_does_not_exist(): void
    {
        // Arrange
        $tokenRecord = $this->createTokenRecord();
        $this->simulateAuthenticatedRequest(tokenRecord: $tokenRecord);
        $helper = $this->getHelper();

        // Act & Assert
        $this->assertFalse($helper->tokenHasAbility('admin'));
        $this->assertFalse($helper->tokenHasAbility('superuser'));
    }

    public function test_token_has_ability_returns_false_when_no_token(): void
    {
        // Arrange
        $this->simulateAuthenticatedRequest();
        $helper = $this->getHelper();

        // Act & Assert
        $this->assertFalse($helper->tokenHasAbility('read'));
    }

    public function test_token_has_all_abilities_returns_true_when_all_exist(): void
    {
        // Arrange
        $tokenRecord = $this->createTokenRecord();
        $this->simulateAuthenticatedRequest(tokenRecord: $tokenRecord);
        $helper = $this->getHelper();

        // Act & Assert
        $this->assertTrue($helper->tokenHasAllAbilities(['read', 'write']));
        $this->assertTrue($helper->tokenHasAllAbilities(['read', 'write', 'delete']));
    }

    public function test_token_has_all_abilities_returns_false_when_some_abilities_missing(): void
    {
        // Arrange
        $tokenRecord = $this->createTokenRecord();
        $this->simulateAuthenticatedRequest(tokenRecord: $tokenRecord);
        $helper = $this->getHelper();

        // Act & Assert
        $this->assertFalse($helper->tokenHasAllAbilities(['read', 'admin']));
        $this->assertFalse($helper->tokenHasAllAbilities(['write', 'superuser']));
    }

    public function test_token_has_all_abilities_returns_false_when_no_token(): void
    {
        // Arrange
        $this->simulateAuthenticatedRequest();
        $helper = $this->getHelper();

        // Act & Assert
        $this->assertFalse($helper->tokenHasAllAbilities(['read', 'write']));
    }

    // ============================================================================
    // Token Expiration Tests
    // ============================================================================

    public function test_is_token_expired_returns_false_when_token_not_expired(): void
    {
        // Arrange
        $tokenRecord = $this->createTokenRecord(['expires_at' => Carbon::now()->addDays(7)]);
        $this->simulateAuthenticatedRequest(tokenRecord: $tokenRecord);
        $helper = $this->getHelper();

        // Act
        $result = $helper->isTokenExpired();

        // Assert
        $this->assertFalse($result);
    }

    public function test_is_token_expired_returns_true_when_token_expired(): void
    {
        // Arrange
        $tokenRecord = $this->createTokenRecord(['expires_at' => Carbon::now()->subDays(1)]);
        $this->simulateAuthenticatedRequest(tokenRecord: $tokenRecord);
        $helper = $this->getHelper();

        // Act
        $result = $helper->isTokenExpired();

        // Assert
        $this->assertTrue($result);
    }

    public function test_is_token_expired_returns_true_when_no_expiration_date(): void
    {
        // Arrange
        $tokenRecord = $this->createTokenRecord(['expires_at' => null]);
        $this->simulateAuthenticatedRequest(tokenRecord: $tokenRecord);
        $helper = $this->getHelper();

        // Act
        $result = $helper->isTokenExpired();

        // Assert
        $this->assertTrue($result);
    }

    public function test_is_token_expired_returns_true_when_no_token(): void
    {
        // Arrange
        $this->simulateAuthenticatedRequest();
        $helper = $this->getHelper();

        // Act
        $result = $helper->isTokenExpired();

        // Assert
        $this->assertTrue($result);
    }

    public function test_is_token_valid_returns_true_when_token_valid(): void
    {
        // Arrange
        $tokenRecord = $this->createTokenRecord(['expires_at' => Carbon::now()->addDays(7)]);
        $this->simulateAuthenticatedRequest(tokenRecord: $tokenRecord);
        $helper = $this->getHelper();

        // Act
        $result = $helper->isTokenValid();

        // Assert
        $this->assertTrue($result);
    }

    public function test_is_token_valid_returns_false_when_token_expired(): void
    {
        // Arrange
        $tokenRecord = $this->createTokenRecord(['expires_at' => Carbon::now()->subDays(1)]);
        $this->simulateAuthenticatedRequest(tokenRecord: $tokenRecord);
        $helper = $this->getHelper();

        // Act
        $result = $helper->isTokenValid();

        // Assert
        $this->assertFalse($result);
    }

    // ============================================================================
    // Token Metadata Tests
    // ============================================================================

    public function test_get_token_metadata_returns_metadata_when_token_present(): void
    {
        // Arrange
        $tokenRecord = $this->createTokenRecord();
        $this->simulateAuthenticatedRequest(tokenRecord: $tokenRecord);
        $helper = $this->getHelper();

        // Act
        $result = $helper->getTokenMetadata();

        // Assert
        $this->assertNotNull($result);
        $this->assertInstanceOf(StrictDataObject::class, $result);
        $this->assertArrayHasKey('ip', $result->toArray());
        $this->assertArrayHasKey('user_agent', $result->toArray());
    }

    public function test_get_token_metadata_returns_null_when_no_token(): void
    {
        // Arrange
        $this->simulateAuthenticatedRequest();
        $helper = $this->getHelper();

        // Act
        $result = $helper->getTokenMetadata();

        // Assert
        $this->assertNull($result);
    }

    // ============================================================================
    // Token Allowed Origins Tests
    // ============================================================================

    public function test_get_token_allowed_origins_returns_origins_when_token_present(): void
    {
        // Arrange
        $tokenRecord = $this->createTokenRecord();
        $this->simulateAuthenticatedRequest(tokenRecord: $tokenRecord);
        $helper = $this->getHelper();

        // Act
        $result = $helper->getTokenAllowedOrigins();

        // Assert
        $this->assertNotNull($result);
        $this->assertInstanceOf(StringTypedCollection::class, $result);
        $this->assertTrue($result->contains('http://localhost'));
        $this->assertTrue($result->contains('https://example.com'));
    }

    public function test_get_token_allowed_origins_returns_null_when_no_token(): void
    {
        // Arrange
        $this->simulateAuthenticatedRequest();
        $helper = $this->getHelper();

        // Act
        $result = $helper->getTokenAllowedOrigins();

        // Assert
        $this->assertNull($result);
    }

    public function test_is_origin_allowed_returns_true_when_origin_allowed(): void
    {
        // Arrange
        $tokenRecord = $this->createTokenRecord();
        $this->simulateAuthenticatedRequest(tokenRecord: $tokenRecord);
        $helper = $this->getHelper();

        // Act & Assert
        $this->assertTrue($helper->isOriginAllowed('http://localhost'));
        $this->assertTrue($helper->isOriginAllowed('https://example.com'));
    }

    public function test_is_origin_allowed_returns_false_when_origin_not_allowed(): void
    {
        // Arrange
        $tokenRecord = $this->createTokenRecord();
        $this->simulateAuthenticatedRequest(tokenRecord: $tokenRecord);
        $helper = $this->getHelper();

        // Act & Assert
        $this->assertFalse($helper->isOriginAllowed('https://malicious.com'));
        $this->assertFalse($helper->isOriginAllowed('http://localhost:8080'));
    }

    public function test_is_origin_allowed_returns_false_when_no_token(): void
    {
        // Arrange
        $this->simulateAuthenticatedRequest();
        $helper = $this->getHelper();

        // Act & Assert
        $this->assertFalse($helper->isOriginAllowed('http://localhost'));
    }

    // ============================================================================
    // Token Dates Tests
    // ============================================================================

    public function test_get_token_expiration_date_returns_date_when_token_present(): void
    {
        // Arrange
        $expiresAt = Carbon::now()->addDays(7);
        $tokenRecord = $this->createTokenRecord(['expires_at' => $expiresAt]);
        $this->simulateAuthenticatedRequest(tokenRecord: $tokenRecord);
        $helper = $this->getHelper();

        // Act
        $result = $helper->getTokenExpirationDate();

        // Assert
        $this->assertNotNull($result);
        $this->assertInstanceOf(DateTimeVO::class, $result);
        $this->assertSame($expiresAt->toIso8601String(), $result->format('Y-m-d\TH:i:sP'));
    }

    public function test_get_token_expiration_date_returns_null_when_no_token(): void
    {
        // Arrange
        $this->simulateAuthenticatedRequest();
        $helper = $this->getHelper();

        // Act
        $result = $helper->getTokenExpirationDate();

        // Assert
        $this->assertNull($result);
    }

    public function test_get_token_last_used_at_returns_date_when_token_present(): void
    {
        // Arrange
        $lastUsedAt = Carbon::now()->subHours(2);
        $tokenRecord = $this->createTokenRecord(['last_used_at' => $lastUsedAt]);
        $this->simulateAuthenticatedRequest(tokenRecord: $tokenRecord);
        $helper = $this->getHelper();

        // Act
        $result = $helper->getTokenLastUsedAt();

        // Assert
        $this->assertNotNull($result);
        $this->assertInstanceOf(DateTimeVO::class, $result);
        $this->assertSame($lastUsedAt->toIso8601String(), $result->format('Y-m-d\TH:i:sP'));
    }

    public function test_get_token_last_used_at_returns_null_when_no_token(): void
    {
        // Arrange
        $this->simulateAuthenticatedRequest();
        $helper = $this->getHelper();

        // Act
        $result = $helper->getTokenLastUsedAt();

        // Assert
        $this->assertNull($result);
    }

    // ============================================================================
    // Authentication Status Tests
    // ============================================================================

    public function test_is_authenticated_returns_true_when_token_and_user_present(): void
    {
        // Arrange
        $tokenRecord = $this->createTokenRecord();
        $this->simulateAuthenticatedRequest(tokenRecord: $tokenRecord, user: $this->user);
        $helper = $this->getHelper();

        // Act
        $result = $helper->isAuthenticated();

        // Assert
        $this->assertTrue($result);
    }

    public function test_is_authenticated_returns_false_when_no_token(): void
    {
        // Arrange
        $this->simulateAuthenticatedRequest(user: $this->user);
        $helper = $this->getHelper();

        // Act
        $result = $helper->isAuthenticated();

        // Assert
        $this->assertFalse($result);
    }

    public function test_is_authenticated_returns_false_when_no_user(): void
    {
        // Arrange
        $tokenRecord = $this->createTokenRecord();
        $this->simulateAuthenticatedRequest(tokenRecord: $tokenRecord);
        $helper = $this->getHelper();

        // Act
        $result = $helper->isAuthenticated();

        // Assert
        $this->assertFalse($result);
    }

    public function test_is_authenticated_returns_false_when_token_expired(): void
    {
        // Arrange
        $tokenRecord = $this->createTokenRecord(['expires_at' => Carbon::now()->subDays(1)]);
        $this->simulateAuthenticatedRequest(tokenRecord: $tokenRecord, user: $this->user);
        $helper = $this->getHelper();

        // Act
        $result = $helper->isAuthenticated();

        // Assert
        $this->assertFalse($result);
    }

    public function test_is_guest_returns_true_when_not_authenticated(): void
    {
        // Arrange
        $this->simulateAuthenticatedRequest();
        $helper = $this->getHelper();

        // Act
        $result = $helper->isGuest();

        // Assert
        $this->assertTrue($result);
    }

    public function test_is_guest_returns_false_when_authenticated(): void
    {
        // Arrange
        $tokenRecord = $this->createTokenRecord();
        $this->simulateAuthenticatedRequest(tokenRecord: $tokenRecord, user: $this->user);
        $helper = $this->getHelper();

        // Act
        $result = $helper->isGuest();

        // Assert
        $this->assertFalse($result);
    }

    // ============================================================================
    // Clear Cache Tests
    // ============================================================================

    public function test_clear_resets_cached_values(): void
    {
        // Arrange
        $tokenRecord = $this->createTokenRecord();
        $this->simulateAuthenticatedRequest(tokenRecord: $tokenRecord, user: $this->user);
        $helper = $this->getHelper();

        // Act - First call caches values
        $helper->getCurrentToken();
        $helper->getCurrentAuthenticatable();

        // Act - Clear cache
        $helper->clear();

        // ✅ Recréer le helper avec une requête vide
        $app = $this->app;
        $app->instance(Request::class, Request::create('/'));
        $helper = $app->make(NemesisHelper::class);

        // Assert - Values should be null after clear
        $this->assertNull($helper->getTokenId());
        $this->assertNull($helper->getTokenName());
        $this->assertNull($helper->getTokenableId());
        $this->assertFalse($helper->hasCurrentToken());
        $this->assertFalse($helper->hasCurrentAuthenticatable());
    }

    // ============================================================================
    // Integration Tests with Real Middleware
    // ============================================================================

    public function test_helper_works_with_real_middleware_authentication(): void
    {
        // Arrange
        $parameterName = $this->config->middlewareConfig()->parameter_name;
        $tokenRecord = $this->createTokenRecord();

        $this->app['request']->merge([
            'current_nemesis_token' => $tokenRecord,
            $parameterName => $this->user,
            $parameterName.'_format' => $this->user->nemesisFormat(),
        ]);

        $helper = $this->getHelper();

        // Act & Assert
        $this->assertNotNull($helper->getCurrentToken());
        $this->assertNotNull($helper->getCurrentAuthenticatable());
        $this->assertNotNull($helper->getCurrentAuthenticatableFormat());
        $this->assertTrue($helper->hasCurrentToken());
        $this->assertTrue($helper->hasCurrentAuthenticatable());
        $this->assertSame($tokenRecord->id, $helper->getTokenId());
        $this->assertSame($tokenRecord->tokenable_id, $helper->getTokenableId());
        $this->assertSame($tokenRecord->tokenable_type, $helper->getTokenableType());
        $this->assertTrue($helper->isAuthenticated());
    }

    public function test_helper_returns_null_when_middleware_not_executed(): void
    {
        // Arrange
        $helper = $this->getHelper();

        // Act & Assert
        $this->assertNull($helper->getCurrentToken());
        $this->assertNull($helper->getCurrentAuthenticatable());
        $this->assertNull($helper->getCurrentAuthenticatableFormat());
        $this->assertFalse($helper->hasCurrentToken());
        $this->assertFalse($helper->hasCurrentAuthenticatable());
        $this->assertNull($helper->getTokenId());
        $this->assertNull($helper->getTokenableId());
        $this->assertNull($helper->getTokenName());
        $this->assertNull($helper->getTokenSource());
        $this->assertFalse($helper->isAuthenticated());
        $this->assertTrue($helper->isGuest());
    }

    // ============================================================================
    // Edge Cases Tests
    // ============================================================================

    public function test_helper_handles_token_without_abilities_gracefully(): void
    {
        // Arrange
        $tokenRecord = $this->createTokenRecord(['abilities' => null]);
        $this->simulateAuthenticatedRequest(tokenRecord: $tokenRecord);
        $helper = $this->getHelper();

        // Act & Assert
        $this->assertNull($helper->getTokenAbilities());
        $this->assertFalse($helper->tokenHasAbility('read'));
        $this->assertFalse($helper->tokenHasAllAbilities(['read', 'write']));
    }

    public function test_helper_handles_token_without_metadata_gracefully(): void
    {
        // Arrange
        $tokenRecord = $this->createTokenRecord(['metadata' => null]);
        $this->simulateAuthenticatedRequest(tokenRecord: $tokenRecord);
        $helper = $this->getHelper();

        // Act & Assert
        $this->assertNull($helper->getTokenMetadata());
    }

    public function test_helper_handles_token_without_allowed_origins_gracefully(): void
    {
        // Arrange
        $tokenRecord = $this->createTokenRecord(['allowed_origins' => null]);
        $this->simulateAuthenticatedRequest(tokenRecord: $tokenRecord);
        $helper = $this->getHelper();

        // Act & Assert
        $this->assertNull($helper->getTokenAllowedOrigins());
        $this->assertFalse($helper->isOriginAllowed('http://localhost'));
    }
}
