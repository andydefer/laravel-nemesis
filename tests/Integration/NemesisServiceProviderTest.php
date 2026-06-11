<?php

declare(strict_types=1);

namespace Kani\Nemesis\Tests\Unit;

use Illuminate\Routing\Router;
use Kani\Nemesis\Contracts\Configs\NemesisConfigInterface;
use Kani\Nemesis\Directives\CleanTokensDirective;
use Kani\Nemesis\Directives\InstallNemesisDirective;
use Kani\Nemesis\Directives\ListTokensDirective;
use Kani\Nemesis\Helpers\NemesisHelper;
use Kani\Nemesis\Http\Middleware\NemesisTokenMiddleware;
use Kani\Nemesis\NemesisServiceProvider;
use Kani\Nemesis\Services\NemesisAuthenticationService;
use Kani\Nemesis\Services\NemesisService;
use Kani\Nemesis\Tests\IntegrationTestCase;

/**
 * Test suite for NemesisServiceProvider service registration.
 *
 * Validates that all required services are properly registered
 * and bound in the Laravel service container.
 */
final class NemesisServiceProviderTest extends IntegrationTestCase
{
    /**
     * Test that the service provider registers and binds all required services.
     */
    public function test_service_provider_registers_and_binds_services(): void
    {
        // Arrange: Create service provider instance with the application container
        $provider = new NemesisServiceProvider($this->app);

        // Act: Execute both registration and booting of the service provider
        $provider->register();
        $provider->boot();

        // Assert: Key services should be bound in the container (using Interface)
        $this->assertTrue($this->app->bound(NemesisConfigInterface::class));
        $this->assertTrue($this->app->bound(NemesisService::class));
        $this->assertTrue($this->app->bound(NemesisAuthenticationService::class));
        $this->assertTrue($this->app->bound(NemesisHelper::class));
    }

    /**
     * Test that the service provider registers the nemesis.token middleware.
     */
    public function test_service_provider_registers_middleware(): void
    {
        // Arrange: Create service provider instance and get the router instance
        $provider = new NemesisServiceProvider($this->app);

        // Act: Register and boot the service provider to trigger middleware registration
        $provider->register();
        $provider->boot();

        // Assert: The middleware should be registered with the correct alias and class
        /** @var Router $router */
        $router = $this->app['router'];

        $this->assertArrayHasKey('nemesis.token', $router->getMiddleware());
        $this->assertEquals(NemesisTokenMiddleware::class, $router->getMiddleware()['nemesis.token']);
    }

    /**
     * Test that the service provider registers the nemesis middleware group.
     */
    public function test_service_provider_registers_middleware_group(): void
    {
        // Arrange: Create service provider instance and get the router instance
        $provider = new NemesisServiceProvider($this->app);

        // Act: Register and boot the service provider to trigger middleware group registration
        $provider->register();
        $provider->boot();

        // Assert: The middleware group should contain the NemesisTokenMiddleware
        /** @var Router $router */
        $router = $this->app['router'];

        $this->assertArrayHasKey('nemesis', $router->getMiddlewareGroups());
        $this->assertContains(NemesisTokenMiddleware::class, $router->getMiddlewareGroups()['nemesis']);
    }

    /**
     * Test that the service provider merges configuration.
     */
    public function test_service_provider_merges_configuration(): void
    {
        // Arrange: Create service provider instance
        $provider = new NemesisServiceProvider($this->app);

        // Act: Register the service provider to merge configuration
        $provider->register();

        // Assert: Configuration should be merged with default values from the package
        $this->assertNotNull(config('nemesis'));
        $this->assertArrayHasKey('token_length', config('nemesis'));
        $this->assertArrayHasKey('hash_algorithm', config('nemesis'));
        $this->assertArrayHasKey('middleware', config('nemesis'));
    }

    /**
     * Test that the service provider registers directives when running in console.
     */
    public function test_service_provider_registers_directives_in_console(): void
    {
        // Arrange: Create service provider instance
        $provider = new NemesisServiceProvider($this->app);

        // Act: Register and boot the service provider to register directives
        $provider->register();
        $provider->boot();

        // Assert: All directive classes should exist and be loadable
        $this->assertTrue(class_exists(InstallNemesisDirective::class));
        $this->assertTrue(class_exists(ListTokensDirective::class));
        $this->assertTrue(class_exists(CleanTokensDirective::class));
    }

    /**
     * Test that the service provider publishes resources.
     */
    public function test_service_provider_publishes_resources(): void
    {
        // Arrange: Create service provider instance
        $provider = new NemesisServiceProvider($this->app);

        // Act: Register and boot the service provider to set up publishing
        $provider->register();
        $provider->boot();

        // Assert: Package configuration and migration files exist and are ready for publishing
        $configPath = __DIR__ . '/../../config/nemesis.php';
        $migrationPath = __DIR__ . '/../../database/migrations/';

        $this->assertFileExists($configPath);
        $this->assertDirectoryExists($migrationPath);
    }

    /**
     * Test that the service provider registers NemesisConfigInterface as a singleton.
     */
    public function test_service_provider_registers_nemesis_config(): void
    {
        // Arrange: Create service provider instance
        $provider = new NemesisServiceProvider($this->app);

        // Act: Register the service provider
        $provider->register();

        // Assert: NemesisConfigInterface should be bound in the container
        $this->assertTrue($this->app->bound(NemesisConfigInterface::class));

        $config = $this->app->make(NemesisConfigInterface::class);
        $this->assertInstanceOf(NemesisConfigInterface::class, $config);

        // Assert config values are loaded correctly via new API
        $this->assertSame('Authorization', $config->middlewareConfig()->token_header);
        $this->assertSame('sha256', $config->tokenConfig()->hash_algorithm);
        $this->assertSame('nemesisAuth', $config->middlewareConfig()->parameter_name);
        $this->assertTrue($config->middlewareConfig()->validate_origin);
        $this->assertTrue($config->middlewareConfig()->security_headers);
        $this->assertTrue($config->corsConfig()->allow_credentials);
        $this->assertSame(86400, $config->corsConfig()->max_age);
        $this->assertFalse($config->corsConfig()->expose_token_info);
    }

    /**
     * Test that NemesisConfigInterface is a singleton (same instance throughout the app).
     */
    public function test_nemesis_config_is_singleton(): void
    {
        // Arrange: Create service provider instance
        $provider = new NemesisServiceProvider($this->app);

        // Act: Register the service provider and resolve config twice
        $provider->register();

        $firstInstance = $this->app->make(NemesisConfigInterface::class);
        $secondInstance = $this->app->make(NemesisConfigInterface::class);

        // Assert: Both instances should be the same object
        $this->assertSame($firstInstance, $secondInstance);
    }

    /**
     * Test that NemesisService is a singleton.
     */
    public function test_nemesis_service_is_singleton(): void
    {
        // Arrange: Create service provider instance
        $provider = new NemesisServiceProvider($this->app);

        // Act: Register the service provider and resolve service twice
        $provider->register();

        $firstInstance = $this->app->make(NemesisService::class);
        $secondInstance = $this->app->make(NemesisService::class);

        // Assert: Both instances should be the same object
        $this->assertSame($firstInstance, $secondInstance);
    }

    /**
     * Test that InstallNemesisDirective receives dependencies via constructor injection.
     */
    public function test_install_nemesis_directive_receives_dependencies(): void
    {
        // Arrange: Create service provider instance
        $provider = new NemesisServiceProvider($this->app);

        // Act: Register the service provider and resolve InstallNemesisDirective
        $provider->register();

        /** @var InstallNemesisDirective $directive */
        $directive = $this->app->make(InstallNemesisDirective::class);

        // Assert: Directive should be instantiated without errors
        $this->assertInstanceOf(InstallNemesisDirective::class, $directive);
    }

    /**
     * Test that ListTokensDirective receives dependencies via constructor injection.
     */
    public function test_list_tokens_directive_receives_dependencies(): void
    {
        // Arrange: Create service provider instance
        $provider = new NemesisServiceProvider($this->app);

        // Act: Register the service provider and resolve ListTokensDirective
        $provider->register();

        /** @var ListTokensDirective $directive */
        $directive = $this->app->make(ListTokensDirective::class);

        // Assert: Directive should be instantiated without errors
        $this->assertInstanceOf(ListTokensDirective::class, $directive);
    }

    /**
     * Test that CleanTokensDirective receives dependencies via constructor injection.
     */
    public function test_clean_tokens_directive_receives_dependencies(): void
    {
        // Arrange: Create service provider instance
        $provider = new NemesisServiceProvider($this->app);

        // Act: Register the service provider and resolve CleanTokensDirective
        $provider->register();

        /** @var CleanTokensDirective $directive */
        $directive = $this->app->make(CleanTokensDirective::class);

        // Assert: Directive should be instantiated without errors
        $this->assertInstanceOf(CleanTokensDirective::class, $directive);
    }

    /**
     * Test that NemesisTokenMiddleware receives dependencies via constructor injection.
     */
    public function test_nemesis_token_middleware_receives_dependencies(): void
    {
        // Arrange: Create service provider instance
        $provider = new NemesisServiceProvider($this->app);

        // Act: Register the service provider and resolve NemesisTokenMiddleware
        $provider->register();

        /** @var NemesisTokenMiddleware $middleware */
        $middleware = $this->app->make(NemesisTokenMiddleware::class);

        // Assert: Middleware should be instantiated without errors
        $this->assertInstanceOf(NemesisTokenMiddleware::class, $middleware);
    }

    /**
     * Test that custom configuration values are properly loaded.
     */
    public function test_custom_configuration_values_are_loaded(): void
    {
        // Arrange: Set custom configuration values
        config()->set('nemesis.middleware.token_header', 'X-Custom-Token');
        config()->set('nemesis.hash_algorithm', 'sha512');
        config()->set('nemesis.middleware.parameter_name', 'customAuth');
        config()->set('nemesis.middleware.validate_origin', false);
        config()->set('nemesis.middleware.security_headers', false);
        config()->set('nemesis.cors.allow_credentials', false);
        config()->set('nemesis.cors.max_age', 3600);
        config()->set('nemesis.cors.expose_token_info', true);

        // Act: Create service provider instance and register
        $provider = new NemesisServiceProvider($this->app);
        $provider->register();

        // Assert: Custom values should be reflected in the config object (via new API)
        $config = $this->app->make(NemesisConfigInterface::class);

        $this->assertEquals('X-Custom-Token', $config->middlewareConfig()->token_header);
        $this->assertEquals('sha512', $config->tokenConfig()->hash_algorithm);
        $this->assertEquals('customAuth', $config->middlewareConfig()->parameter_name);
        $this->assertFalse($config->middlewareConfig()->validate_origin);
        $this->assertFalse($config->middlewareConfig()->security_headers);
        $this->assertFalse($config->corsConfig()->allow_credentials);
        $this->assertEquals(3600, $config->corsConfig()->max_age);
        $this->assertTrue($config->corsConfig()->expose_token_info);
    }
}
