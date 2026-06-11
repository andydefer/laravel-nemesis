<?php

// tests/Integration/Services/NemesisAuthenticationServiceTest.php

declare(strict_types=1);

namespace AndyDefer\Nemesis\Tests\Integration\Services;

use AndyDefer\DataValidator\Services\MetadataValidator;
use AndyDefer\DomainStructures\Abstracts\AbstractRecord;
use AndyDefer\DomainStructures\Collections\Utility\StringTypedCollection;
use AndyDefer\DomainStructures\Services\HydrationService;
use AndyDefer\PhpServices\Services\RecordTransformableService;
use AndyDefer\PhpVo\ValueObjects\DateTimeVO;
use Carbon\Carbon;
use AndyDefer\Nemesis\Contracts\Configs\NemesisConfigInterface;
use AndyDefer\Nemesis\Enums\ErrorCode;
use AndyDefer\Nemesis\Models\NemesisToken;
use AndyDefer\Nemesis\Records\AuthenticationResultRecord;
use AndyDefer\Nemesis\Records\NemesisTokenRecord;
use AndyDefer\Nemesis\Services\NemesisAuthenticationService;
use AndyDefer\Nemesis\Services\NemesisService;
use AndyDefer\Nemesis\Tests\Fixtures\Models\TestUser;
use AndyDefer\Nemesis\Tests\IntegrationTestCase;

final class NemesisAuthenticationServiceTest extends IntegrationTestCase
{
    private NemesisAuthenticationService $authService;
    private NemesisService $nemesisService;
    private TestUser $user;
    private string $plainToken;
    private NemesisToken $tokenModel;
    private HydrationService $hydration;

    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow(Carbon::create(2024, 1, 1, 12, 0, 0));

        $this->hydration = new HydrationService();
        $this->nemesisService = $this->app->make(NemesisService::class);

        // ✅ Correction : 6 arguments pour NemesisAuthenticationService
        $this->authService = new NemesisAuthenticationService(
            config: $this->app->make(NemesisConfigInterface::class),
            nemesisService: $this->nemesisService,
            recordTransformableService: $this->app->make(RecordTransformableService::class),
            db: $this->app->make('db'),
            metadataValidator: $this->app->make(MetadataValidator::class),
            hydration: $this->hydration,
        );

        $this->user = TestUser::create([
            'name' => 'John Doe',
            'email' => 'john@example.com',
        ]);

        // Create a valid token for testing
        $record = $this->hydration->hydrate(NemesisTokenRecord::class, [
            'name' => 'Test Token',
            'source' => 'web',
        ]);
        [$this->tokenModel, $this->plainToken] = $this->nemesisService->createWithPlainToken($record, $this->user);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function withBearerToken(string $token): void
    {
        $this->app['request']->headers->set('Authorization', 'Bearer ' . $token);
    }

    private function withCustomHeader(string $token, string $header = 'X-API-Key'): void
    {
        config()->set('nemesis.middleware.token_header', $header);
        $this->app['request']->headers->set($header, $token);
    }

    private function withOrigin(string $origin): void
    {
        $this->app['request']->headers->set('Origin', $origin);
    }

    // ============================================================================
    // Success Tests
    // ============================================================================

    public function test_authenticate_returns_success_with_valid_token(): void
    {
        // Arrange
        $this->withBearerToken($this->plainToken);

        // Act
        $result = $this->authService->authenticate($this->app['request']);

        // Assert
        $this->assertTrue($result->isSuccess());
        $this->assertNotNull($result->getTokenRecord());
        $this->assertNull($result->getErrorCode());
    }

    public function test_authenticate_returns_token_record(): void
    {
        // Arrange
        $this->withBearerToken($this->plainToken);

        // Act
        $result = $this->authService->authenticate($this->app['request']);

        // Assert
        $tokenRecord = $result->getTokenRecord();
        $this->assertInstanceOf(NemesisTokenRecord::class, $tokenRecord);
        $this->assertSame($this->tokenModel->id, $tokenRecord->id);
        $this->assertSame('Test Token', $tokenRecord->name);
    }

    public function test_authenticate_updates_last_used(): void
    {
        // Arrange
        $this->assertNull($this->tokenModel->last_used_at);
        $this->withBearerToken($this->plainToken);

        // Act
        $this->authService->authenticate($this->app['request']);

        // Assert
        $this->tokenModel->refresh();
        $this->assertNotNull($this->tokenModel->last_used_at);
    }

    public function test_authenticate_adds_tracking_metadata(): void
    {
        // Arrange
        $this->withBearerToken($this->plainToken);
        $this->app['request']->server->set('REMOTE_ADDR', '192.168.1.1');
        $this->app['request']->headers->set('User-Agent', 'Mozilla/5.0');

        // Act
        $this->authService->authenticate($this->app['request']);

        // Assert
        $this->tokenModel->refresh();
        $this->assertArrayHasKey('last_auth_ip', $this->tokenModel->metadata);
        $this->assertArrayHasKey('last_auth_ua', $this->tokenModel->metadata);
        $this->assertArrayHasKey('auth_count', $this->tokenModel->metadata);
        $this->assertEquals(1, $this->tokenModel->metadata['auth_count']);
    }

    public function test_authenticate_increments_auth_count(): void
    {
        // Arrange
        $this->nemesisService->mergeMetadata($this->tokenModel, ['auth_count' => 5]);
        $this->withBearerToken($this->plainToken);

        // Act
        $this->authService->authenticate($this->app['request']);

        // Assert
        $this->tokenModel->refresh();
        $this->assertEquals(6, $this->tokenModel->metadata['auth_count']);
    }

    // ============================================================================
    // Authentication Failure Tests
    // ============================================================================

    public function test_authenticate_returns_missing_token_error(): void
    {
        // Act (no token set)
        $result = $this->authService->authenticate($this->app['request']);

        // Assert
        $this->assertFalse($result->isSuccess());
        $this->assertEquals(ErrorCode::MISSING_TOKEN, $result->getErrorCode());
    }

    public function test_authenticate_returns_invalid_token_error(): void
    {
        // Arrange
        $this->withBearerToken('invalid-token');

        // Act
        $result = $this->authService->authenticate($this->app['request']);

        // Assert
        $this->assertFalse($result->isSuccess());
        $this->assertEquals(ErrorCode::INVALID_TOKEN, $result->getErrorCode());
    }

    public function test_authenticate_returns_expired_token_error(): void
    {
        // Arrange
        $expiredDate = new DateTimeVO(Carbon::getTestNow()->subDay()->format('Y-m-d\TH:i:sP'));
        $record = $this->hydration->hydrate(NemesisTokenRecord::class, [
            'name' => 'Expired Token',
            'expires_at' => $expiredDate,
        ]);
        [$expiredToken, $plainExpiredToken] = $this->nemesisService->createWithPlainToken($record, $this->user);
        $this->withBearerToken($plainExpiredToken);

        // Act
        $result = $this->authService->authenticate($this->app['request']);

        // Assert
        $this->assertFalse($result->isSuccess());
        $this->assertEquals(ErrorCode::TOKEN_EXPIRED, $result->getErrorCode());
    }

    // ============================================================================
    // Ability Check Tests
    // ============================================================================

    public function test_authenticate_accepts_token_with_required_ability(): void
    {
        // Arrange
        $abilities = new StringTypedCollection();
        $abilities->add('read');
        $abilities->add('write');

        $record = $this->hydration->hydrate(NemesisTokenRecord::class, [
            'name' => 'Ability Token',
            'abilities' => $abilities,
        ]);
        [$token, $plainToken] = $this->nemesisService->createWithPlainToken($record, $this->user);
        $this->withBearerToken($plainToken);

        // Act
        $result = $this->authService->authenticate($this->app['request'], 'read');

        // Assert
        $this->assertTrue($result->isSuccess());
    }

    public function test_authenticate_returns_insufficient_permissions_error(): void
    {
        // Arrange
        $abilities = new StringTypedCollection();
        $abilities->add('read');

        $record = $this->hydration->hydrate(NemesisTokenRecord::class, [
            'name' => 'Limited Token',
            'abilities' => $abilities,
        ]);
        [$token, $plainToken] = $this->nemesisService->createWithPlainToken($record, $this->user);
        $this->withBearerToken($plainToken);

        // Act
        $result = $this->authService->authenticate($this->app['request'], 'admin');

        // Assert
        $this->assertFalse($result->isSuccess());
        $this->assertEquals(ErrorCode::INSUFFICIENT_PERMISSIONS, $result->getErrorCode());

        $additionalData = $result->getAdditionalData();
        $this->assertEquals('admin', $additionalData->toArray()['required_ability']);
    }

    // ============================================================================
    // Origin Restriction Tests
    // ============================================================================

    public function test_authenticate_accepts_request_from_allowed_origin(): void
    {
        // Arrange
        $origins = new StringTypedCollection();
        $origins->add('https://allowed.com');

        $record = $this->hydration->hydrate(NemesisTokenRecord::class, [
            'name' => 'Origin Token',
            'allowed_origins' => $origins,
        ]);
        [$token, $plainToken] = $this->nemesisService->createWithPlainToken($record, $this->user);
        $this->withBearerToken($plainToken);
        $this->withOrigin('https://allowed.com');

        // Act
        $result = $this->authService->authenticate($this->app['request']);

        // Assert
        $this->assertTrue($result->isSuccess());
    }

    public function test_authenticate_returns_origin_not_allowed_error(): void
    {
        // Arrange
        $origins = new StringTypedCollection();
        $origins->add('https://allowed.com');

        $record = $this->hydration->hydrate(NemesisTokenRecord::class, [
            'name' => 'Restricted Token',
            'allowed_origins' => $origins,
        ]);
        [$token, $plainToken] = $this->nemesisService->createWithPlainToken($record, $this->user);
        $this->withBearerToken($plainToken);
        $this->withOrigin('https://evil.com');

        // Act
        $result = $this->authService->authenticate($this->app['request']);

        // Assert
        $this->assertFalse($result->isSuccess());
        $this->assertEquals(ErrorCode::ORIGIN_NOT_ALLOWED, $result->getErrorCode());

        $additionalData = $result->getAdditionalData();
        $this->assertEquals('https://evil.com', $additionalData->toArray()['origin']);
    }

    public function test_authenticate_ignores_origin_when_validation_disabled(): void
    {
        // Arrange
        config()->set('nemesis.middleware.validate_origin', false);

        $origins = new StringTypedCollection();
        $origins->add('https://allowed.com');

        $record = $this->hydration->hydrate(NemesisTokenRecord::class, [
            'name' => 'No Origin Check Token',
            'allowed_origins' => $origins,
        ]);
        [$token, $plainToken] = $this->nemesisService->createWithPlainToken($record, $this->user);
        $this->withBearerToken($plainToken);
        $this->withOrigin('https://evil.com');

        // Act
        $result = $this->authService->authenticate($this->app['request']);

        // Assert
        $this->assertTrue($result->isSuccess());
    }

    // ============================================================================
    // Custom Header Tests
    // ============================================================================

    public function test_authenticate_accepts_token_in_custom_header(): void
    {
        // Arrange
        $this->withCustomHeader($this->plainToken, 'X-Custom-Auth');

        // Act
        $result = $this->authService->authenticate($this->app['request']);

        // Assert
        $this->assertTrue($result->isSuccess());
    }

    // ============================================================================
    // Edge Cases Tests
    // ============================================================================

    public function test_authenticate_handles_token_with_deleted_tokenable(): void
    {
        // Arrange
        $tempUser = TestUser::create(['name' => 'Temp', 'email' => 'temp@example.com']);
        $record = $this->hydration->hydrate(NemesisTokenRecord::class, ['name' => 'Temp Token']);
        [$token, $plainToken] = $this->nemesisService->createWithPlainToken($record, $tempUser);

        // Supprimer l'utilisateur
        $tempUser->delete();
        $this->withBearerToken($plainToken);

        // Act
        $result = $this->authService->authenticate($this->app['request']);

        // Assert
        $this->assertFalse($result->isSuccess());
        $this->assertEquals(ErrorCode::INVALID_TOKEN, $result->getErrorCode());
    }

    // ============================================================================
    // authenticateToRecord Tests
    // ============================================================================

    public function test_authenticate_to_record_returns_record(): void
    {
        // Arrange
        $this->withBearerToken($this->plainToken);

        // Act
        $record = $this->authService->authenticateToRecord($this->app['request']);

        // Assert
        $this->assertInstanceOf(AuthenticationResultRecord::class, $record);
        $this->assertTrue($record->success);
    }

    // ============================================================================
    // getFormattedAuthenticatable Tests
    // ============================================================================

    public function test_get_formatted_authenticatable_returns_record(): void
    {
        // Arrange
        // Récupérer le token model via le service
        $tokenModel = $this->nemesisService->findByHash($this->tokenModel->token_hash);

        // Récupérer l'authenticatable depuis le modèle
        $authenticatable = $tokenModel->tokenable;

        // Act
        $formatted = $this->authService->getFormattedAuthenticatable($authenticatable);

        // Assert
        $this->assertInstanceOf(AbstractRecord::class, $formatted);
    }

    public function test_get_formatted_authenticatable_returns_null_for_invalid_model(): void
    {
        // Arrange
        $invalidModel = new \stdClass();

        // Act
        $formatted = $this->authService->getFormattedAuthenticatable($invalidModel);

        // Assert
        $this->assertNull($formatted);
    }

    // ============================================================================
    // Wildcard Origin Tests
    // ============================================================================

    public function test_authenticate_accepts_wildcard_origin_match(): void
    {
        // Arrange
        $origins = new StringTypedCollection();
        $origins->add('https://*.example.com');

        $record = $this->hydration->hydrate(NemesisTokenRecord::class, [
            'name' => 'Wildcard Token',
            'allowed_origins' => $origins,
        ]);
        [$token, $plainToken] = $this->nemesisService->createWithPlainToken($record, $this->user);
        $this->withBearerToken($plainToken);
        $this->withOrigin('https://sub.example.com');

        // Act
        $result = $this->authService->authenticate($this->app['request']);

        // Assert
        $this->assertTrue($result->isSuccess());
    }
}
