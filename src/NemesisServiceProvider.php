<?php
// src/NemesisServiceProvider.php

declare(strict_types=1);

namespace Kani\Nemesis;

use Illuminate\Contracts\Foundation\Application;
use Illuminate\Support\ServiceProvider;
use Kani\Nemesis\Config\NemesisConfig;
use Kani\Nemesis\Http\Middleware\NemesisTokenMiddleware;
use Kani\Nemesis\Repositories\NemesisTokenRepository;
use Kani\Nemesis\Services\HttpHeaderService;
use Kani\Nemesis\Services\NemesisAuthenticationService;
use Kani\Nemesis\Services\NemesisService;
use AndyDefer\PhpServices\Services\RecordTransformableService;

final class NemesisServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(
            __DIR__ . '/../config/nemesis.php',
            'nemesis'
        );

        $this->registerNemesisConfig();
        $this->registerServices();
        $this->registerMiddleware();
    }

    public function boot(): void
    {
        if ($this->app->runningInConsole()) {
            $this->publishes([
                __DIR__ . '/../../config/nemesis.php' => config_path('nemesis.php'),
            ], 'nemesis-config');

            $this->publishes([
                __DIR__ . '/../../database/migrations/' => database_path('migrations'),
            ], 'nemesis-migrations');
        }
    }

    private function registerNemesisConfig(): void
    {
        $this->app->singleton(NemesisConfig::class, function (): NemesisConfig {
            return new NemesisConfig();
        });
    }

    private function registerServices(): void
    {
        // Repository
        $this->app->bind(NemesisTokenRepository::class, function (): NemesisTokenRepository {
            return new NemesisTokenRepository();
        });

        // RecordTransformableService
        $this->app->singleton(RecordTransformableService::class, function (): RecordTransformableService {
            return new RecordTransformableService();
        });

        // HttpHeaderService
        $this->app->singleton(HttpHeaderService::class, function (Application $app): HttpHeaderService {
            return new HttpHeaderService(
                $app->make(NemesisConfig::class),
                $app,
            );
        });

        // NemesisAuthenticationService
        $this->app->singleton(NemesisAuthenticationService::class, function (Application $app): NemesisAuthenticationService {
            return new NemesisAuthenticationService(
                $app->make(NemesisConfig::class),
                $app->make(NemesisService::class),
                $app->make(RecordTransformableService::class),
                $app->make('db'),
            );
        });

        // NemesisService - Service principal
        $this->app->singleton(NemesisService::class, function (Application $app): NemesisService {
            return new NemesisService(
                $app->make(NemesisTokenRepository::class),
                $app->make(NemesisConfig::class),
                new \Illuminate\Support\Str(),
            );
        });
    }

    private function registerMiddleware(): void
    {
        $this->app->singleton(NemesisTokenMiddleware::class, function (Application $app): NemesisTokenMiddleware {
            return new NemesisTokenMiddleware(
                $app->make(NemesisConfig::class),
                $app->make(NemesisAuthenticationService::class),
                $app->make(HttpHeaderService::class),
            );
        });

        $this->app['router']->aliasMiddleware(
            'nemesis.token',
            NemesisTokenMiddleware::class
        );

        $this->app['router']->middlewareGroup('nemesis', [
            NemesisTokenMiddleware::class,
        ]);
    }
}
