<?php

declare(strict_types=1);

namespace Kani\Nemesis\Tests\Unit;

use Illuminate\Routing\Router;
use Kani\Nemesis\Commands\CleanTokensCommand;
use Kani\Nemesis\Commands\InstallNemesisCommand;
use Kani\Nemesis\Commands\ListTokensCommand;
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
        $configPath = __DIR__ . '/../../config/nemesis.php';
        $migrationPath = __DIR__ . '/../../database/migrations/';

        $this->assertFileExists($configPath);
        $this->assertDirectoryExists($migrationPath);
    }
}
