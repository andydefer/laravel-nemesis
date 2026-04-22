<?php

declare(strict_types=1);

namespace Kani\Nemesis;

use Illuminate\Support\ServiceProvider;
use Kani\Nemesis\Commands\InstallNemesisCommand;
use Kani\Nemesis\Commands\CleanTokensCommand;
use Kani\Nemesis\Commands\ListTokensCommand;
use Kani\Nemesis\Http\Middleware\NemesisAuth;
use Kani\Nemesis\Models\NemesisToken;
use Kani\Nemesis\Observers\TokenObserver;

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
     * Registers observers, initializes systems, and publishes resources.
     */
    public function boot(): void
    {
        if ($this->app->runningInConsole()) {
            $this->commands([
                InstallNemesisCommand::class,
                CleanTokensCommand::class,
                ListTokensCommand::class,
            ]);
            $this->publishResources();
        }
    }

    /**
     * Register package services and dependencies.
     */
    public function register(): void
    {
        $this->mergeConfigFrom(
            __DIR__ . '/../config/nemesis.php',
            'nemesis'
        );

        $this->loadHelpers();
        $this->registerMiddleware();
        $this->registerTokenManager();
    }

    /**
     * Load package helper functions.
     */
    protected function loadHelpers(): void
    {
        $helpersPath = __DIR__ . '/helpers.php';

        if (file_exists($helpersPath)) {
            require_once $helpersPath;
        }
    }

    /**
     * Register package middleware.
     */
    protected function registerMiddleware(): void
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
     * Register the token manager as a singleton.
     */
    protected function registerTokenManager(): void
    {
        $this->app->singleton('nemesis', function ($app) {
            return new NemesisManager();
        });
    }

    /**
     * Publish package resources for user customization.
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
