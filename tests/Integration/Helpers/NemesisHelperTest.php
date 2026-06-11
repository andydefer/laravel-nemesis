<?php

// tests/Integration/Helpers/NemesisHelperTest.php

declare(strict_types=1);

namespace AndyDefer\Nemesis\Tests\Integration\Helpers;

use AndyDefer\DomainStructures\Services\HydrationService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use AndyDefer\Nemesis\Contracts\Configs\NemesisConfigInterface;
use AndyDefer\Nemesis\Helpers\NemesisHelper;
use AndyDefer\Nemesis\Models\NemesisToken;
use AndyDefer\Nemesis\Records\NemesisTokenRecord;
use AndyDefer\Nemesis\Tests\Fixtures\Models\TestUser;
use AndyDefer\Nemesis\Tests\IntegrationTestCase;

final class NemesisHelperTest extends IntegrationTestCase
{
    private TestUser $user;
    private NemesisConfigInterface $config;
    private HydrationService $hydration;

    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow(Carbon::create(2024, 1, 1, 12, 0, 0));

        $this->hydration = new HydrationService();
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

    private function createTokenRecord(): NemesisTokenRecord
    {
        $token = NemesisToken::create([
            'token_hash' => hash('sha256', 'test-token-123'),
            'tokenable_type' => $this->user->getMorphClass(),
            'tokenable_id' => $this->user->id,
            'name' => 'Test Token',
            'source' => 'web',
        ]);

        return $this->hydration->hydrate(NemesisTokenRecord::class, [
            'id' => $token->id,
            'token_hash' => $token->token_hash,
            'tokenable_type' => $token->tokenable_type,
            'tokenable_id' => $token->tokenable_id,
            'name' => $token->name,
            'source' => $token->source,
            'last_used_at' => $token->last_used_at?->toIso8601String(),
            'expires_at' => $token->expires_at?->toIso8601String(),
            'created_at' => $token->created_at->toIso8601String(),
            'updated_at' => $token->updated_at->toIso8601String(),
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

        // Créer un mock de la config
        $mockConfig = $this->createStub(NemesisConfigInterface::class);
        $middlewareConfig = $this->hydration->hydrate(\AndyDefer\Nemesis\Records\MiddlewareConfigRecord::class, [
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
        $formatKey = $parameterName . '_format';
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
        $formatKey = $parameterName . '_format';
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
    // Integration Tests with Real Middleware
    // ============================================================================

    public function test_helper_works_with_real_middleware_authentication(): void
    {
        // Arrange
        $parameterName = $this->config->middlewareConfig()->parameter_name;

        $this->app['request']->merge([
            'current_nemesis_token' => $this->createTokenRecord(),
            $parameterName => $this->user,
            $parameterName . '_format' => $this->user->nemesisFormat(),
        ]);

        $helper = $this->getHelper();

        // Act & Assert
        $this->assertNotNull($helper->getCurrentToken());
        $this->assertNotNull($helper->getCurrentAuthenticatable());
        $this->assertNotNull($helper->getCurrentAuthenticatableFormat());
        $this->assertTrue($helper->hasCurrentToken());
        $this->assertTrue($helper->hasCurrentAuthenticatable());
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
    }
}
