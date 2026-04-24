<?php

declare(strict_types=1);

namespace Kani\Nemesis;

use Illuminate\Contracts\Config\Repository as ConfigRepository;
use Illuminate\Foundation\Application;
use Illuminate\Support\ServiceProvider;
use Kani\Nemesis\Commands\CleanTokensCommand;
use Kani\Nemesis\Commands\InstallNemesisCommand;
use Kani\Nemesis\Commands\ListTokensCommand;
use Kani\Nemesis\Config\NemesisConfig;
use Kani\Nemesis\Http\Middleware\NemesisAuth;

/**
 * Service provider for the Nemesis package.
 *
 * Handles registration of all package services, configurations,
 * and bootstrapping for the multi-model token authentication system.
 */
class NemesisServiceProvider extends ServiceProvider
{
    /**
     * Bootstrap package services.
     *
     * Registers console commands and publishes resources
     * only when running in the console context.
     */
    public function boot(): void
    {
        if ($this->app->runningInConsole()) {
            $this->registerConsoleCommands();
            $this->publishResources();
        }
    }

    /**
     * Register package services and dependencies in the container.
     */
    public function register(): void
    {
        $this->mergeConfigFrom(
            __DIR__ . '/../config/nemesis.php',
            'nemesis'
        );

        $this->registerHelperFunctions();
        $this->registerNemesisConfig();
        $this->registerMiddleware();
        $this->registerTokenManager();
    }

    /**
     * Register all package console commands.
     */
    private function registerConsoleCommands(): void
    {
        $this->commands([
            InstallNemesisCommand::class,
            CleanTokensCommand::class,
            ListTokensCommand::class,
        ]);
    }

    /**
     * Load package helper functions from the helpers.php file.
     */
    private function registerHelperFunctions(): void
    {
        $helpersPath = __DIR__ . '/helpers.php';

        if (file_exists($helpersPath)) {
            require_once $helpersPath;
        }
    }

    /**
     * Register the NemesisConfig value object as a singleton.
     *
     * This makes the configuration available throughout the application
     * as an immutable value object instead of accessing config() directly.
     */
    private function registerNemesisConfig(): void
    {
        $this->app->singleton(NemesisConfig::class, function (Application $app): NemesisConfig {
            /** @var ConfigRepository $config */
            $config = $app['config'];

            return NemesisConfig::fromLaravelConfig($config);
        });
    }

    /**
     * Register package middleware with the router.
     *
     * Registers both an alias and a middleware group for flexibility.
     * Uses dependency injection to pass the NemesisConfig to the middleware.
     */
    private function registerMiddleware(): void
    {
        // Register the middleware with dependency injection
        $this->app->singleton(NemesisAuth::class, function (Application $app): NemesisAuth {
            return new NemesisAuth(
                config: $app->make(NemesisConfig::class)
            );
        });

        $this->app['router']->aliasMiddleware(
            'nemesis.auth',
            NemesisAuth::class
        );

        $this->app['router']->middlewareGroup('nemesis', [
            NemesisAuth::class,
        ]);
    }

    /**
     * Register the token manager as a singleton in the container.
     */
    private function registerTokenManager(): void
    {
        $this->app->singleton('nemesis', function (Application $app): NemesisManager {
            return new NemesisManager();
        });
    }

    /**
     * Publish package resources for user customization.
     *
     * Publishes configuration file and database migrations
     * so users can override defaults.
     */
    private function publishResources(): void
    {
        $this->publishes([
            __DIR__ . '/../config/nemesis.php' => config_path('nemesis.php'),
        ], 'nemesis-config');

        $this->publishes([
            __DIR__ . '/../database/migrations/' => database_path('migrations'),
        ], 'nemesis-migrations');
    }
}
