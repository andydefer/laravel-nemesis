<?php

declare(strict_types=1);

namespace Kani\Nemesis;

use Illuminate\Support\ServiceProvider;
use Kani\Nemesis\Commands\CleanTokensCommand;
use Kani\Nemesis\Commands\InstallNemesisCommand;
use Kani\Nemesis\Commands\ListTokensCommand;
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
     * Register package middleware with the router.
     *
     * Registers both an alias and a middleware group for flexibility.
     */
    private function registerMiddleware(): void
    {
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
        $this->app->singleton('nemesis', function ($app): NemesisManager {
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
