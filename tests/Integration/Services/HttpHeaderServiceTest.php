<?php

// tests/Integration/Services/HttpHeaderServiceTest.php

declare(strict_types=1);

namespace Kani\Nemesis\Tests\Integration\Services;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Kani\Nemesis\Contracts\Configs\NemesisConfigInterface;
use Kani\Nemesis\Services\HttpHeaderService;
use Kani\Nemesis\Tests\IntegrationTestCase;

final class HttpHeaderServiceTest extends IntegrationTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
    }

    protected function runMigrations(): void
    {
        // Do nothing - no migrations needed for header tests
    }

    private function createMockResponse(): Response
    {
        return new Response();
    }

    private function createMockJsonResponse(): JsonResponse
    {
        return new JsonResponse();
    }

    // ============================================================================
    // applySecurityHeaders Tests
    // ============================================================================

    public function test_apply_security_headers_returns_response_unchanged_when_security_headers_disabled(): void
    {
        // Arrange
        config()->set('nemesis.middleware.security_headers', false);

        $config = $this->app->make(NemesisConfigInterface::class);

        $service = new HttpHeaderService($config, $this->app);
        $response = $this->createMockResponse();

        // Act
        $result = $service->applySecurityHeaders($response);

        // Assert
        $this->assertSame($response, $result);
        $this->assertFalse($result->headers->has('X-Frame-Options'));
    }

    public function test_apply_security_headers_returns_response_unchanged_when_no_header_method(): void
    {
        // Arrange
        config()->set('nemesis.middleware.security_headers', true);
        $config = $this->app->make(NemesisConfigInterface::class);
        $service = new HttpHeaderService($config, $this->app);
        $nonResponseObject = new \stdClass();

        // Act
        $result = $service->applySecurityHeaders($nonResponseObject);

        // Assert
        $this->assertSame($nonResponseObject, $result);
    }

    public function test_apply_security_headers_adds_all_security_headers(): void
    {
        // Arrange
        config()->set('nemesis.middleware.security_headers', true);
        $config = $this->app->make(NemesisConfigInterface::class);
        $service = new HttpHeaderService($config, $this->app);
        $response = $this->createMockResponse();

        // Act
        $result = $service->applySecurityHeaders($response);

        // Assert
        $this->assertTrue($result->headers->has('X-Frame-Options'));
        $this->assertEquals('DENY', $result->headers->get('X-Frame-Options'));
        $this->assertTrue($result->headers->has('X-XSS-Protection'));
        $this->assertEquals('1; mode=block', $result->headers->get('X-XSS-Protection'));
        $this->assertTrue($result->headers->has('X-Content-Type-Options'));
        $this->assertEquals('nosniff', $result->headers->get('X-Content-Type-Options'));
        $this->assertTrue($result->headers->has('Referrer-Policy'));
        $this->assertEquals('strict-origin-when-cross-origin', $result->headers->get('Referrer-Policy'));
    }

    public function test_apply_security_headers_adds_hsts_header_in_production(): void
    {
        // Arrange
        config()->set('nemesis.middleware.security_headers', true);
        $this->app['env'] = 'production';
        $config = $this->app->make(NemesisConfigInterface::class);
        $service = new HttpHeaderService($config, $this->app);
        $response = $this->createMockResponse();

        // Act
        $result = $service->applySecurityHeaders($response);

        // Assert
        $this->assertTrue($result->headers->has('Strict-Transport-Security'));
        $this->assertEquals('max-age=31536000; includeSubDomains', $result->headers->get('Strict-Transport-Security'));
    }

    public function test_apply_security_headers_does_not_add_hsts_header_in_non_production(): void
    {
        // Arrange
        config()->set('nemesis.middleware.security_headers', true);
        $this->app['env'] = 'local';
        $config = $this->app->make(NemesisConfigInterface::class);
        $service = new HttpHeaderService($config, $this->app);
        $response = $this->createMockResponse();

        // Act
        $result = $service->applySecurityHeaders($response);

        // Assert
        $this->assertFalse($result->headers->has('Strict-Transport-Security'));
    }

    public function test_apply_security_headers_works_with_json_response(): void
    {
        // Arrange
        config()->set('nemesis.middleware.security_headers', true);
        $config = $this->app->make(NemesisConfigInterface::class);
        $service = new HttpHeaderService($config, $this->app);
        $response = $this->createMockJsonResponse();

        // Act
        $result = $service->applySecurityHeaders($response);

        // Assert
        $this->assertTrue($result->headers->has('X-Frame-Options'));
        $this->assertEquals('DENY', $result->headers->get('X-Frame-Options'));
    }

    // ============================================================================
    // applyCorsHeaders Tests
    // ============================================================================

    public function test_apply_cors_headers_returns_response_unchanged_when_validate_origin_disabled(): void
    {
        // Arrange
        config()->set('nemesis.middleware.validate_origin', false);
        $config = $this->app->make(NemesisConfigInterface::class);
        $service = new HttpHeaderService($config, $this->app);
        $response = $this->createMockResponse();
        $request = new Request();

        // Act
        $result = $service->applyCorsHeaders($response, $request);

        // Assert
        $this->assertSame($response, $result);
    }

    public function test_apply_cors_headers_returns_response_unchanged_when_no_header_method(): void
    {
        // Arrange
        config()->set('nemesis.middleware.validate_origin', true);
        $config = $this->app->make(NemesisConfigInterface::class);
        $service = new HttpHeaderService($config, $this->app);
        $nonResponseObject = new \stdClass();
        $request = new Request();

        // Act
        $result = $service->applyCorsHeaders($nonResponseObject, $request);

        // Assert
        $this->assertSame($nonResponseObject, $result);
    }

    public function test_apply_cors_headers_returns_response_unchanged_when_no_origin_header(): void
    {
        // Arrange
        config()->set('nemesis.middleware.validate_origin', true);
        $config = $this->app->make(NemesisConfigInterface::class);
        $service = new HttpHeaderService($config, $this->app);
        $response = $this->createMockResponse();
        $request = new Request();

        // Act
        $result = $service->applyCorsHeaders($response, $request);

        // Assert
        $this->assertSame($response, $result);
    }

    public function test_apply_cors_headers_adds_access_control_allow_origin(): void
    {
        // Arrange
        config()->set('nemesis.middleware.validate_origin', true);
        config()->set('nemesis.cors.allow_credentials', false);
        config()->set('nemesis.cors.expose_token_info', false);
        $config = $this->app->make(NemesisConfigInterface::class);
        $service = new HttpHeaderService($config, $this->app);
        $response = $this->createMockResponse();
        $request = new Request();
        $request->headers->set('Origin', 'https://example.com');

        // Act
        $result = $service->applyCorsHeaders($response, $request);

        // Assert
        $this->assertTrue($result->headers->has('Access-Control-Allow-Origin'));
        $this->assertEquals('https://example.com', $result->headers->get('Access-Control-Allow-Origin'));
    }

    public function test_apply_cors_headers_adds_credentials_header_when_allowed(): void
    {
        // Arrange
        config()->set('nemesis.middleware.validate_origin', true);
        config()->set('nemesis.cors.allow_credentials', true);
        config()->set('nemesis.cors.expose_token_info', false);
        $config = $this->app->make(NemesisConfigInterface::class);
        $service = new HttpHeaderService($config, $this->app);
        $response = $this->createMockResponse();
        $request = new Request();
        $request->headers->set('Origin', 'https://example.com');

        // Act
        $result = $service->applyCorsHeaders($response, $request);

        // Assert
        $this->assertTrue($result->headers->has('Access-Control-Allow-Credentials'));
        $this->assertEquals('true', $result->headers->get('Access-Control-Allow-Credentials'));
    }

    public function test_apply_cors_headers_adds_preflight_headers_for_options_request(): void
    {
        // Arrange
        config()->set('nemesis.middleware.validate_origin', true);
        config()->set('nemesis.cors.allow_credentials', false);
        config()->set('nemesis.cors.max_age', 86400);
        config()->set('nemesis.cors.expose_token_info', false);
        $config = $this->app->make(NemesisConfigInterface::class);
        $service = new HttpHeaderService($config, $this->app);
        $response = $this->createMockResponse();
        $request = new Request();
        $request->setMethod('OPTIONS');
        $request->headers->set('Origin', 'https://example.com');

        // Act
        $result = $service->applyCorsHeaders($response, $request);

        // Assert
        $this->assertTrue($result->headers->has('Access-Control-Allow-Methods'));
        $this->assertStringContainsString('GET', $result->headers->get('Access-Control-Allow-Methods'));
        $this->assertStringContainsString('POST', $result->headers->get('Access-Control-Allow-Methods'));
        $this->assertTrue($result->headers->has('Access-Control-Allow-Headers'));
        $this->assertStringContainsString('Content-Type', $result->headers->get('Access-Control-Allow-Headers'));
        $this->assertTrue($result->headers->has('Access-Control-Max-Age'));
        $this->assertEquals('86400', $result->headers->get('Access-Control-Max-Age'));
    }

    public function test_apply_cors_headers_adds_expose_headers_when_enabled(): void
    {
        // Arrange
        config()->set('nemesis.middleware.validate_origin', true);
        config()->set('nemesis.cors.allow_credentials', false);
        config()->set('nemesis.cors.expose_token_info', true);
        $config = $this->app->make(NemesisConfigInterface::class);
        $service = new HttpHeaderService($config, $this->app);
        $response = $this->createMockResponse();
        $request = new Request();
        $request->headers->set('Origin', 'https://example.com');

        // Act
        $result = $service->applyCorsHeaders($response, $request);

        // Assert
        $this->assertTrue($result->headers->has('Access-Control-Expose-Headers'));
        $this->assertStringContainsString('X-Token-Expires-At', $result->headers->get('Access-Control-Expose-Headers'));
        $this->assertStringContainsString('X-Token-Abilities', $result->headers->get('Access-Control-Expose-Headers'));
    }

    // ============================================================================
    // addCorsToErrorResponse Tests
    // ============================================================================

    public function test_add_cors_to_error_response_does_nothing_when_validate_origin_disabled(): void
    {
        // Arrange
        config()->set('nemesis.middleware.validate_origin', false);
        $config = $this->app->make(NemesisConfigInterface::class);
        $service = new HttpHeaderService($config, $this->app);
        $response = $this->createMockJsonResponse();
        $request = new Request();

        // Act
        $service->addCorsToErrorResponse($response, $request);

        // Assert
        $this->assertFalse($response->headers->has('Access-Control-Allow-Origin'));
    }

    public function test_add_cors_to_error_response_does_nothing_when_no_origin(): void
    {
        // Arrange
        config()->set('nemesis.middleware.validate_origin', true);
        $config = $this->app->make(NemesisConfigInterface::class);
        $service = new HttpHeaderService($config, $this->app);
        $response = $this->createMockJsonResponse();
        $request = new Request();

        // Act
        $service->addCorsToErrorResponse($response, $request);

        // Assert
        $this->assertFalse($response->headers->has('Access-Control-Allow-Origin'));
    }

    public function test_add_cors_to_error_response_adds_origin_header(): void
    {
        // Arrange
        config()->set('nemesis.middleware.validate_origin', true);
        config()->set('nemesis.cors.allow_credentials', false);
        $config = $this->app->make(NemesisConfigInterface::class);
        $service = new HttpHeaderService($config, $this->app);
        $response = $this->createMockJsonResponse();

        $request = new Request();
        $request->headers->set('Origin', 'https://example.com');

        // Act
        $service->addCorsToErrorResponse($response, $request);

        // Assert
        $this->assertTrue($response->headers->has('Access-Control-Allow-Origin'));
        $this->assertEquals('https://example.com', $response->headers->get('Access-Control-Allow-Origin'));
    }

    public function test_add_cors_to_error_response_adds_credentials_header_when_allowed(): void
    {
        // Arrange
        config()->set('nemesis.middleware.validate_origin', true);
        config()->set('nemesis.cors.allow_credentials', true);
        $config = $this->app->make(NemesisConfigInterface::class);
        $service = new HttpHeaderService($config, $this->app);
        $response = $this->createMockJsonResponse();

        $request = new Request();
        $request->headers->set('Origin', 'https://example.com');

        // Act
        $service->addCorsToErrorResponse($response, $request);

        // Assert
        $this->assertTrue($response->headers->has('Access-Control-Allow-Credentials'));
        $this->assertEquals('true', $response->headers->get('Access-Control-Allow-Credentials'));
    }

    public function test_add_cors_to_error_response_does_not_add_credentials_header_when_not_allowed(): void
    {
        // Arrange
        config()->set('nemesis.middleware.validate_origin', true);
        config()->set('nemesis.cors.allow_credentials', false);
        $config = $this->app->make(NemesisConfigInterface::class);
        $service = new HttpHeaderService($config, $this->app);
        $response = $this->createMockJsonResponse();

        $request = new Request();
        $request->headers->set('Origin', 'https://example.com');

        // Act
        $service->addCorsToErrorResponse($response, $request);

        // Assert
        $this->assertFalse($response->headers->has('Access-Control-Allow-Credentials'));
    }
}
