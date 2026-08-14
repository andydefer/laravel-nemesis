<?php

// tests/Integration/Services/NemesisAuthenticationServiceTest.php

declare(strict_types=1);

namespace AndyDefer\Nemesis\Tests\Integration\Services;

use AndyDefer\DomainStructures\Abstracts\AbstractData;
use AndyDefer\DomainStructures\Collections\Utility\StringTypedCollection;
use AndyDefer\DomainStructures\Services\HydrationService;
use AndyDefer\Nemesis\Enums\ErrorCode;
use AndyDefer\Nemesis\Models\NemesisToken;
use AndyDefer\Nemesis\Records\AuthenticationResultRecord;
use AndyDefer\Nemesis\Records\NemesisTokenRecord;
use AndyDefer\Nemesis\Services\NemesisAuthenticationService;
use AndyDefer\Nemesis\Services\NemesisService;
use AndyDefer\Nemesis\Tests\Fixtures\Models\TestUser;
use AndyDefer\Nemesis\Tests\IntegrationTestCase;
use AndyDefer\PhpVo\ValueObjects\DateTimeVO;
use Carbon\Carbon;

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

        $this->hydration = new HydrationService;
        $this->nemesisService = $this->app->make(NemesisService::class);
        $this->authService = $this->app->make(NemesisAuthenticationService::class);

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
        $this->app['request']->headers->set('Authorization', 'Bearer '.$token);
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
        $this->withBearerToken($this->plainToken);

        $result = $this->authService->authenticate($this->app['request']);

        $this->assertTrue($result->isSuccess());
        $this->assertNotNull($result->getTokenRecord());
        $this->assertNull($result->getErrorCode());
    }

    public function test_authenticate_returns_token_record(): void
    {
        $this->withBearerToken($this->plainToken);

        $result = $this->authService->authenticate($this->app['request']);

        $tokenRecord = $result->getTokenRecord();
        $this->assertInstanceOf(NemesisTokenRecord::class, $tokenRecord);
        $this->assertSame($this->tokenModel->id, $tokenRecord->id);
        $this->assertSame('Test Token', $tokenRecord->name);
    }

    public function test_authenticate_updates_last_used(): void
    {
        $this->assertNull($this->tokenModel->last_used_at);
        $this->withBearerToken($this->plainToken);

        $this->authService->authenticate($this->app['request']);

        $this->tokenModel->refresh();
        $this->assertNotNull($this->tokenModel->last_used_at);
    }

    public function test_authenticate_adds_tracking_metadata(): void
    {
        $this->withBearerToken($this->plainToken);
        $this->app['request']->server->set('REMOTE_ADDR', '192.168.1.1');
        $this->app['request']->headers->set('User-Agent', 'Mozilla/5.0');

        $this->authService->authenticate($this->app['request']);

        $this->tokenModel->refresh();
        $this->assertArrayHasKey('last_auth_ip', $this->tokenModel->metadata);
        $this->assertArrayHasKey('last_auth_ua', $this->tokenModel->metadata);
        $this->assertArrayHasKey('auth_count', $this->tokenModel->metadata);
        $this->assertEquals(1, $this->tokenModel->metadata['auth_count']);
    }

    public function test_authenticate_increments_auth_count(): void
    {
        $this->nemesisService->mergeMetadata($this->tokenModel, ['auth_count' => 5]);
        $this->withBearerToken($this->plainToken);

        $this->authService->authenticate($this->app['request']);

        $this->tokenModel->refresh();
        $this->assertEquals(6, $this->tokenModel->metadata['auth_count']);
    }

    // ============================================================================
    // Authentication Failure Tests
    // ============================================================================

    public function test_authenticate_returns_missing_token_error(): void
    {
        $result = $this->authService->authenticate($this->app['request']);

        $this->assertFalse($result->isSuccess());
        $this->assertEquals(ErrorCode::MISSING_TOKEN, $result->getErrorCode());
    }

    public function test_authenticate_returns_invalid_token_error(): void
    {
        $this->withBearerToken('invalid-token');

        $result = $this->authService->authenticate($this->app['request']);

        $this->assertFalse($result->isSuccess());
        $this->assertEquals(ErrorCode::INVALID_TOKEN, $result->getErrorCode());
    }

    public function test_authenticate_returns_expired_token_error(): void
    {
        $expiredDate = new DateTimeVO(Carbon::getTestNow()->subDay()->format('Y-m-d\TH:i:sP'));
        $record = $this->hydration->hydrate(NemesisTokenRecord::class, [
            'name' => 'Expired Token',
            'expires_at' => $expiredDate,
        ]);
        [$expiredToken, $plainExpiredToken] = $this->nemesisService->createWithPlainToken($record, $this->user);
        $this->withBearerToken($plainExpiredToken);

        $result = $this->authService->authenticate($this->app['request']);

        $this->assertFalse($result->isSuccess());
        $this->assertEquals(ErrorCode::TOKEN_EXPIRED, $result->getErrorCode());
    }

    public function test_authenticate_returns_token_expired_error_with_ability(): void
    {
        $expiredDate = new DateTimeVO(Carbon::getTestNow()->subDay()->format('Y-m-d\TH:i:sP'));
        $record = $this->hydration->hydrate(NemesisTokenRecord::class, [
            'name' => 'Expired Token',
            'abilities' => ['read'],
            'expires_at' => $expiredDate,
        ]);
        [$expiredToken, $plainExpiredToken] = $this->nemesisService->createWithPlainToken($record, $this->user);
        $this->withBearerToken($plainExpiredToken);

        $result = $this->authService->authenticate($this->app['request'], 'read');

        $this->assertFalse($result->isSuccess());
        $this->assertEquals(ErrorCode::TOKEN_EXPIRED, $result->getErrorCode());
    }

    // ============================================================================
    // Ability Check Tests
    // ============================================================================

    public function test_authenticate_accepts_token_with_required_ability(): void
    {
        $abilities = new StringTypedCollection;
        $abilities->add('read');
        $abilities->add('write');

        $record = $this->hydration->hydrate(NemesisTokenRecord::class, [
            'name' => 'Ability Token',
            'abilities' => $abilities,
        ]);
        [$token, $plainToken] = $this->nemesisService->createWithPlainToken($record, $this->user);
        $this->withBearerToken($plainToken);

        $result = $this->authService->authenticate($this->app['request'], 'read');

        $this->assertTrue($result->isSuccess());
    }

    public function test_authenticate_accepts_token_with_multiple_abilities(): void
    {
        $abilities = new StringTypedCollection;
        $abilities->add('read');
        $abilities->add('write');
        $abilities->add('admin');

        $record = $this->hydration->hydrate(NemesisTokenRecord::class, [
            'name' => 'Multi Ability Token',
            'abilities' => $abilities,
        ]);
        [$token, $plainToken] = $this->nemesisService->createWithPlainToken($record, $this->user);
        $this->withBearerToken($plainToken);

        $result = $this->authService->authenticate($this->app['request'], 'admin');

        $this->assertTrue($result->isSuccess());
    }

    public function test_authenticate_returns_insufficient_permissions_error(): void
    {
        $abilities = new StringTypedCollection;
        $abilities->add('read');

        $record = $this->hydration->hydrate(NemesisTokenRecord::class, [
            'name' => 'Limited Token',
            'abilities' => $abilities,
        ]);
        [$token, $plainToken] = $this->nemesisService->createWithPlainToken($record, $this->user);
        $this->withBearerToken($plainToken);

        $result = $this->authService->authenticate($this->app['request'], 'admin');

        $this->assertFalse($result->isSuccess());
        $this->assertEquals(ErrorCode::INSUFFICIENT_PERMISSIONS, $result->getErrorCode());

        $additionalData = $result->getAdditionalData();
        $this->assertEquals('admin', $additionalData->toArray()['required_ability']);
    }

    public function test_authenticate_returns_insufficient_permissions_when_token_has_no_abilities(): void
    {
        // Token sans abilities (abilities = null)
        $this->withBearerToken($this->plainToken);

        $result = $this->authService->authenticate($this->app['request'], 'admin');

        $this->assertFalse($result->isSuccess());
        $this->assertEquals(ErrorCode::INSUFFICIENT_PERMISSIONS, $result->getErrorCode());

        $additionalData = $result->getAdditionalData();
        $this->assertEquals('admin', $additionalData->toArray()['required_ability']);
        $this->assertNull($additionalData->toArray()['token_abilities']);
    }

    public function test_authenticate_accepts_token_with_abilities_when_no_ability_required(): void
    {
        $abilities = new StringTypedCollection;
        $abilities->add('read');
        $abilities->add('write');

        $record = $this->hydration->hydrate(NemesisTokenRecord::class, [
            'name' => 'Ability Token No Check',
            'abilities' => $abilities,
        ]);
        [$token, $plainToken] = $this->nemesisService->createWithPlainToken($record, $this->user);
        $this->withBearerToken($plainToken);

        $result = $this->authService->authenticate($this->app['request']);

        $this->assertTrue($result->isSuccess());
    }

    public function test_authenticate_accepts_token_with_no_abilities_when_no_ability_required(): void
    {
        $this->withBearerToken($this->plainToken);

        $result = $this->authService->authenticate($this->app['request']);

        $this->assertTrue($result->isSuccess());
    }

    public function test_authenticate_returns_insufficient_permissions_when_token_has_partial_abilities(): void
    {
        $abilities = new StringTypedCollection;
        $abilities->add('read');

        $record = $this->hydration->hydrate(NemesisTokenRecord::class, [
            'name' => 'Partial Token',
            'abilities' => $abilities,
        ]);
        [$token, $plainToken] = $this->nemesisService->createWithPlainToken($record, $this->user);
        $this->withBearerToken($plainToken);

        $result = $this->authService->authenticate($this->app['request'], 'write');

        $this->assertFalse($result->isSuccess());
        $this->assertEquals(ErrorCode::INSUFFICIENT_PERMISSIONS, $result->getErrorCode());
    }

    // ============================================================================
    // Origin Restriction Tests
    // ============================================================================

    public function test_authenticate_accepts_request_from_allowed_origin(): void
    {
        $origins = new StringTypedCollection;
        $origins->add('https://allowed.com');

        $record = $this->hydration->hydrate(NemesisTokenRecord::class, [
            'name' => 'Origin Token',
            'allowed_origins' => $origins,
        ]);
        [$token, $plainToken] = $this->nemesisService->createWithPlainToken($record, $this->user);
        $this->withBearerToken($plainToken);
        $this->withOrigin('https://allowed.com');

        $result = $this->authService->authenticate($this->app['request']);

        $this->assertTrue($result->isSuccess());
    }

    public function test_authenticate_returns_origin_not_allowed_error(): void
    {
        $origins = new StringTypedCollection;
        $origins->add('https://allowed.com');

        $record = $this->hydration->hydrate(NemesisTokenRecord::class, [
            'name' => 'Restricted Token',
            'allowed_origins' => $origins,
        ]);
        [$token, $plainToken] = $this->nemesisService->createWithPlainToken($record, $this->user);
        $this->withBearerToken($plainToken);
        $this->withOrigin('https://evil.com');

        $result = $this->authService->authenticate($this->app['request']);

        $this->assertFalse($result->isSuccess());
        $this->assertEquals(ErrorCode::ORIGIN_NOT_ALLOWED, $result->getErrorCode());

        $additionalData = $result->getAdditionalData();
        $this->assertEquals('https://evil.com', $additionalData->toArray()['origin']);
    }

    public function test_authenticate_ignores_origin_when_validation_disabled(): void
    {
        config()->set('nemesis.middleware.validate_origin', false);

        $origins = new StringTypedCollection;
        $origins->add('https://allowed.com');

        $record = $this->hydration->hydrate(NemesisTokenRecord::class, [
            'name' => 'No Origin Check Token',
            'allowed_origins' => $origins,
        ]);
        [$token, $plainToken] = $this->nemesisService->createWithPlainToken($record, $this->user);
        $this->withBearerToken($plainToken);
        $this->withOrigin('https://evil.com');

        $result = $this->authService->authenticate($this->app['request']);

        $this->assertTrue($result->isSuccess());
    }

    public function test_authenticate_accepts_request_from_allowed_origin_with_ability(): void
    {
        $origins = new StringTypedCollection;
        $origins->add('https://allowed.com');

        $abilities = new StringTypedCollection;
        $abilities->add('read');

        $record = $this->hydration->hydrate(NemesisTokenRecord::class, [
            'name' => 'Origin Ability Token',
            'allowed_origins' => $origins,
            'abilities' => $abilities,
        ]);
        [$token, $plainToken] = $this->nemesisService->createWithPlainToken($record, $this->user);
        $this->withBearerToken($plainToken);
        $this->withOrigin('https://allowed.com');

        $result = $this->authService->authenticate($this->app['request'], 'read');

        $this->assertTrue($result->isSuccess());
    }

    public function test_authenticate_returns_origin_not_allowed_with_ability(): void
    {
        $origins = new StringTypedCollection;
        $origins->add('https://allowed.com');

        $abilities = new StringTypedCollection;
        $abilities->add('read');

        $record = $this->hydration->hydrate(NemesisTokenRecord::class, [
            'name' => 'Restricted Ability Token',
            'allowed_origins' => $origins,
            'abilities' => $abilities,
        ]);
        [$token, $plainToken] = $this->nemesisService->createWithPlainToken($record, $this->user);
        $this->withBearerToken($plainToken);
        $this->withOrigin('https://evil.com');

        $result = $this->authService->authenticate($this->app['request'], 'read');

        $this->assertFalse($result->isSuccess());
        $this->assertEquals(ErrorCode::ORIGIN_NOT_ALLOWED, $result->getErrorCode());
    }

    // ============================================================================
    // Custom Header Tests
    // ============================================================================

    public function test_authenticate_accepts_token_in_custom_header(): void
    {
        $this->withCustomHeader($this->plainToken, 'X-Custom-Auth');

        $result = $this->authService->authenticate($this->app['request']);

        $this->assertTrue($result->isSuccess());
    }

    public function test_authenticate_accepts_token_in_custom_header_with_ability(): void
    {
        $abilities = new StringTypedCollection;
        $abilities->add('read');

        $record = $this->hydration->hydrate(NemesisTokenRecord::class, [
            'name' => 'Custom Header Token',
            'abilities' => $abilities,
        ]);
        [$token, $plainToken] = $this->nemesisService->createWithPlainToken($record, $this->user);
        $this->withCustomHeader($plainToken, 'X-Custom-Auth');

        $result = $this->authService->authenticate($this->app['request'], 'read');

        $this->assertTrue($result->isSuccess());
    }

    // ============================================================================
    // Edge Cases Tests
    // ============================================================================

    public function test_authenticate_handles_token_with_deleted_tokenable(): void
    {
        $tempUser = TestUser::create(['name' => 'Temp', 'email' => 'temp@example.com']);
        $record = $this->hydration->hydrate(NemesisTokenRecord::class, ['name' => 'Temp Token']);
        [$token, $plainToken] = $this->nemesisService->createWithPlainToken($record, $tempUser);

        $tempUser->delete();
        $this->withBearerToken($plainToken);

        $result = $this->authService->authenticate($this->app['request']);

        $this->assertFalse($result->isSuccess());
        $this->assertEquals(ErrorCode::INVALID_TOKEN, $result->getErrorCode());
    }

    public function test_authenticate_handles_token_with_deleted_tokenable_with_ability(): void
    {
        $tempUser = TestUser::create(['name' => 'Temp', 'email' => 'temp@example.com']);
        $record = $this->hydration->hydrate(NemesisTokenRecord::class, [
            'name' => 'Temp Token',
            'abilities' => ['read'],
        ]);
        [$token, $plainToken] = $this->nemesisService->createWithPlainToken($record, $tempUser);

        $tempUser->delete();
        $this->withBearerToken($plainToken);

        $result = $this->authService->authenticate($this->app['request'], 'read');

        $this->assertFalse($result->isSuccess());
        $this->assertEquals(ErrorCode::INVALID_TOKEN, $result->getErrorCode());
    }

    public function test_authenticate_handles_token_with_nonexistent_tokenable_type(): void
    {
        $token = NemesisToken::create([
            'token_hash' => hash('sha256', 'bad-token'),
            'tokenable_type' => 'NonExistent\\Model\\Class',
            'tokenable_id' => 999,
            'name' => 'Bad Token',
            'source' => 'web',
        ]);

        $this->withBearerToken('bad-token');

        $result = $this->authService->authenticate($this->app['request']);

        $this->assertFalse($result->isSuccess());
        $this->assertEquals(ErrorCode::INVALID_TOKEN, $result->getErrorCode());
    }

    public function test_authenticate_handles_token_with_revoked_token(): void
    {
        $this->nemesisService->revoke($this->tokenModel);
        $this->withBearerToken($this->plainToken);

        $result = $this->authService->authenticate($this->app['request']);

        $this->assertFalse($result->isSuccess());
        $this->assertEquals(ErrorCode::INVALID_TOKEN, $result->getErrorCode());
    }

    public function test_authenticate_handles_revoked_token_with_ability(): void
    {
        $abilities = new StringTypedCollection;
        $abilities->add('read');

        $record = $this->hydration->hydrate(NemesisTokenRecord::class, [
            'name' => 'Revoked Ability Token',
            'abilities' => $abilities,
        ]);
        [$token, $plainToken] = $this->nemesisService->createWithPlainToken($record, $this->user);

        $this->nemesisService->revoke($token);
        $this->withBearerToken($plainToken);

        $result = $this->authService->authenticate($this->app['request'], 'read');

        $this->assertFalse($result->isSuccess());
        $this->assertEquals(ErrorCode::INVALID_TOKEN, $result->getErrorCode());
    }

    // ============================================================================
    // authenticateToRecord Tests
    // ============================================================================

    public function test_authenticate_to_record_returns_record(): void
    {
        $this->withBearerToken($this->plainToken);

        $record = $this->authService->authenticateToRecord($this->app['request']);

        $this->assertInstanceOf(AuthenticationResultRecord::class, $record);
        $this->assertTrue($record->success);
    }

    public function test_authenticate_to_record_returns_error_record(): void
    {
        $record = $this->authService->authenticateToRecord($this->app['request']);

        $this->assertInstanceOf(AuthenticationResultRecord::class, $record);
        $this->assertFalse($record->success);
        $this->assertEquals(ErrorCode::MISSING_TOKEN, $record->error_code);
    }

    public function test_authenticate_to_record_with_ability_returns_record(): void
    {
        $abilities = new StringTypedCollection;
        $abilities->add('read');

        $record = $this->hydration->hydrate(NemesisTokenRecord::class, [
            'name' => 'Ability Record Token',
            'abilities' => $abilities,
        ]);
        [$token, $plainToken] = $this->nemesisService->createWithPlainToken($record, $this->user);
        $this->withBearerToken($plainToken);

        $result = $this->authService->authenticateToRecord($this->app['request'], 'read');

        $this->assertInstanceOf(AuthenticationResultRecord::class, $result);
        $this->assertTrue($result->success);
    }

    // ============================================================================
    // getFormattedAuthenticatable Tests
    // ============================================================================

    public function test_get_formatted_authenticatable_returns_record(): void
    {
        $tokenModel = $this->nemesisService->findByHash($this->tokenModel->token_hash);
        $authenticatable = $tokenModel->tokenable;

        $formatted = $this->authService->getFormattedAuthenticatable($authenticatable);

        $this->assertInstanceOf(AbstractData::class, $formatted);
    }

    public function test_get_formatted_authenticatable_returns_null_for_invalid_model(): void
    {
        $invalidModel = new \stdClass;

        $formatted = $this->authService->getFormattedAuthenticatable($invalidModel);

        $this->assertNull($formatted);
    }

    // ============================================================================
    // Wildcard Origin Tests
    // ============================================================================

    public function test_authenticate_accepts_wildcard_origin_match(): void
    {
        $origins = new StringTypedCollection;
        $origins->add('https://*.example.com');

        $record = $this->hydration->hydrate(NemesisTokenRecord::class, [
            'name' => 'Wildcard Token',
            'allowed_origins' => $origins,
        ]);
        [$token, $plainToken] = $this->nemesisService->createWithPlainToken($record, $this->user);
        $this->withBearerToken($plainToken);
        $this->withOrigin('https://sub.example.com');

        $result = $this->authService->authenticate($this->app['request']);

        $this->assertTrue($result->isSuccess());
    }

    public function test_authenticate_accepts_wildcard_origin_with_ability(): void
    {
        $origins = new StringTypedCollection;
        $origins->add('https://*.example.com');

        $abilities = new StringTypedCollection;
        $abilities->add('read');

        $record = $this->hydration->hydrate(NemesisTokenRecord::class, [
            'name' => 'Wildcard Ability Token',
            'allowed_origins' => $origins,
            'abilities' => $abilities,
        ]);
        [$token, $plainToken] = $this->nemesisService->createWithPlainToken($record, $this->user);
        $this->withBearerToken($plainToken);
        $this->withOrigin('https://sub.example.com');

        $result = $this->authService->authenticate($this->app['request'], 'read');

        $this->assertTrue($result->isSuccess());
    }

    public function test_authenticate_rejects_wildcard_origin_with_ability(): void
    {
        $origins = new StringTypedCollection;
        $origins->add('https://*.example.com');

        $abilities = new StringTypedCollection;
        $abilities->add('read');

        $record = $this->hydration->hydrate(NemesisTokenRecord::class, [
            'name' => 'Wildcard Restricted Token',
            'allowed_origins' => $origins,
            'abilities' => $abilities,
        ]);
        [$token, $plainToken] = $this->nemesisService->createWithPlainToken($record, $this->user);
        $this->withBearerToken($plainToken);
        $this->withOrigin('https://sub.example.org');

        $result = $this->authService->authenticate($this->app['request'], 'read');

        $this->assertFalse($result->isSuccess());
        $this->assertEquals(ErrorCode::ORIGIN_NOT_ALLOWED, $result->getErrorCode());
    }

    public function test_authenticate_accepts_subdomain_wildcard_origin(): void
    {
        $origins = new StringTypedCollection;
        $origins->add('https://*.example.com');

        $record = $this->hydration->hydrate(NemesisTokenRecord::class, [
            'name' => 'Subdomain Token',
            'allowed_origins' => $origins,
        ]);
        [$token, $plainToken] = $this->nemesisService->createWithPlainToken($record, $this->user);
        $this->withBearerToken($plainToken);
        $this->withOrigin('https://api.example.com');

        $result = $this->authService->authenticate($this->app['request']);

        $this->assertTrue($result->isSuccess());
    }
}
