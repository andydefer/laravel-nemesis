<?php

declare(strict_types=1);

namespace Kani\Nemesis\Tests\Unit;

use Illuminate\Routing\Router;
use Kani\Nemesis\Commands\CleanTokensCommand;
use Kani\Nemesis\Commands\InstallNemesisCommand;
use Kani\Nemesis\Commands\ListTokensCommand;
use Kani\Nemesis\Config\NemesisConfig;
use Kani\Nemesis\Http\Middleware\NemesisAuth;
use Kani\Nemesis\NemesisManager;
use Kani\Nemesis\NemesisServiceProvider;
use Kani\Nemesis\Tests\TestCase;

/**
 * Test suite for NemesisServiceProvider service registration.
 *
 * Validates that all required services are properly registered
 * and bound in the Laravel service container.
 */
final class NemesisServiceProviderTest extends TestCase
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

        // Assert: The nemesis binding should exist in the container and return a NemesisManager instance
        $this->assertTrue($this->app->bound('nemesis'));
        $this->assertInstanceOf(NemesisManager::class, $this->app->make('nemesis'));
    }

    /**
     * Test that the service provider registers the nemesis.auth middleware.
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

        $this->assertArrayHasKey('nemesis.auth', $router->getMiddleware());
        $this->assertEquals(NemesisAuth::class, $router->getMiddleware()['nemesis.auth']);
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

        // Assert: The middleware group should contain the NemesisAuth middleware
        /** @var Router $router */
        $router = $this->app['router'];

        $this->assertArrayHasKey('nemesis', $router->getMiddlewareGroups());
        $this->assertContains(NemesisAuth::class, $router->getMiddlewareGroups()['nemesis']);
    }

    /**
     * Test that the service provider loads helper functions.
     */
    public function test_service_provider_loads_helper_functions(): void
    {
        // Arrange: Create service provider instance
        $provider = new NemesisServiceProvider($this->app);

        // Act: Register and boot the service provider to load helper files
        $provider->register();
        $provider->boot();

        // Assert: All expected helper functions should be available globally
        $this->assertTrue(function_exists('nemesis'));
        $this->assertTrue(function_exists('current_token'));
        $this->assertTrue(function_exists('current_authenticatable'));
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
     * Test that the service provider registers commands when running in console.
     */
    public function test_service_provider_registers_commands_in_console(): void
    {
        // Arrange: Create service provider instance
        $provider = new NemesisServiceProvider($this->app);

        // Act: Register and boot the service provider to register console commands
        $provider->register();
        $provider->boot();

        // Assert: All command classes should exist and be loadable
        $this->assertTrue(class_exists(InstallNemesisCommand::class));
        $this->assertTrue(class_exists(CleanTokensCommand::class));
        $this->assertTrue(class_exists(ListTokensCommand::class));
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
        $configPath = __DIR__.'/../../config/nemesis.php';
        $migrationPath = __DIR__.'/../../database/migrations/';

        $this->assertFileExists($configPath);
        $this->assertDirectoryExists($migrationPath);
    }

    /**
     * Test that the service provider registers NemesisConfig as a singleton.
     */
    public function test_service_provider_registers_nemesis_config(): void
    {
        // Arrange: Create service provider instance
        $provider = new NemesisServiceProvider($this->app);

        // Act: Register the service provider
        $provider->register();

        // Assert: NemesisConfig should be bound in the container
        $this->assertTrue($this->app->bound(NemesisConfig::class));

        $config = $this->app->make(NemesisConfig::class);
        $this->assertInstanceOf(NemesisConfig::class, $config);

        // Assert config values are loaded correctly
        $this->assertSame('Authorization', $config->tokenHeader);
        $this->assertSame('sha256', $config->hashAlgorithm);
        $this->assertSame('nemesisAuth', $config->parameterName);
        $this->assertTrue($config->validateOrigin);
        $this->assertTrue($config->securityHeaders);
        $this->assertTrue($config->allowCredentials);
        $this->assertSame(86400, $config->maxAge);
        $this->assertFalse($config->exposeTokenInfo);
    }

    /**
     * Test that NemesisConfig is a singleton (same instance throughout the app).
     */
    public function test_nemesis_config_is_singleton(): void
    {
        // Arrange: Create service provider instance
        $provider = new NemesisServiceProvider($this->app);

        // Act: Register the service provider and resolve config twice
        $provider->register();

        $firstInstance = $this->app->make(NemesisConfig::class);
        $secondInstance = $this->app->make(NemesisConfig::class);

        // Assert: Both instances should be the same object
        $this->assertSame($firstInstance, $secondInstance);
    }

    /**
     * Test that NemesisAuth receives the NemesisConfig dependency via constructor injection.
     */
    public function test_nemesis_auth_receives_config_dependency(): void
    {
        // Arrange: Create service provider instance
        $provider = new NemesisServiceProvider($this->app);

        // Act: Register the service provider and resolve NemesisAuth
        $provider->register();

        /** @var NemesisAuth $middleware */
        $middleware = $this->app->make(NemesisAuth::class);

        // Assert: Middleware should be instantiated without errors
        $this->assertInstanceOf(NemesisAuth::class, $middleware);
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

        // Assert: Custom values should be reflected in the config object
        $config = $this->app->make(NemesisConfig::class);

        $this->assertEquals('X-Custom-Token', $config->tokenHeader);
        $this->assertEquals('sha512', $config->hashAlgorithm);
        $this->assertEquals('customAuth', $config->parameterName);
        $this->assertFalse($config->validateOrigin);
        $this->assertFalse($config->securityHeaders);
        $this->assertFalse($config->allowCredentials);
        $this->assertEquals(3600, $config->maxAge);
        $this->assertTrue($config->exposeTokenInfo);
    }
}
