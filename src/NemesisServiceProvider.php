<?php

// src/NemesisServiceProvider.php

declare(strict_types=1);

namespace AndyDefer\Nemesis;

use AndyDefer\DataValidator\Services\MetadataValidator;
use AndyDefer\Directive\Contexts\DirectiveContext;
use AndyDefer\Directive\Services\DirectiveInteractionService;
use AndyDefer\Directive\Services\FileSystemService;
use AndyDefer\DomainStructures\Services\HydrationService;
use AndyDefer\PhpServices\Services\RecordTransformableService;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Database\DatabaseManager;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Str;
use AndyDefer\Nemesis\Configs\NemesisConfig;
use AndyDefer\Nemesis\Contracts\Configs\NemesisConfigInterface;
use AndyDefer\Nemesis\Directives\CleanTokensDirective;
use AndyDefer\Nemesis\Directives\InstallNemesisDirective;
use AndyDefer\Nemesis\Directives\ListTokensDirective;
use AndyDefer\Nemesis\Directives\NemesisCleanDirective;
use AndyDefer\Nemesis\Helpers\NemesisHelper;
use AndyDefer\Nemesis\Http\Middleware\NemesisTokenMiddleware;
use AndyDefer\Nemesis\Repositories\NemesisTokenRepository;
use AndyDefer\Nemesis\Services\HttpHeaderService;
use AndyDefer\Nemesis\Services\NemesisAuthenticationService;
use AndyDefer\Nemesis\Services\NemesisService;

final class NemesisServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(
            __DIR__ . '/../config/nemesis.php',
            'nemesis'
        );

        $this->app->singleton(NemesisHelper::class, function (Application $app) {
            return new NemesisHelper(
                $app->make('request'),
                $app->make(NemesisConfigInterface::class),
            );
        });

        $this->registerNemesisConfig();
        $this->registerServices();
        $this->registerDirectives();
        $this->registerMiddleware();
    }

    public function boot(): void
    {
        if ($this->app->runningInConsole()) {
            $this->publishes([
                __DIR__ . '/../config/nemesis.php' => config_path('nemesis.php'),
            ], 'nemesis-config');

            $this->publishes([
                __DIR__ . '/../database/migrations/' => database_path('migrations'),
            ], 'nemesis-migrations');
        }
    }

    private function registerNemesisConfig(): void
    {
        // ✅ Binder l'interface avec l'implémentation concrète
        $this->app->singleton(NemesisConfigInterface::class, NemesisConfig::class);
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

        // HttpHeaderService - utilise l'interface
        $this->app->singleton(HttpHeaderService::class, function (Application $app): HttpHeaderService {
            return new HttpHeaderService(
                $app->make(NemesisConfigInterface::class),
                $app,
            );
        });

        // ✅ NemesisAuthenticationService - avec les 6 arguments
        $this->app->singleton(NemesisAuthenticationService::class, function (Application $app): NemesisAuthenticationService {
            return new NemesisAuthenticationService(
                config: $app->make(NemesisConfigInterface::class),
                nemesisService: $app->make(NemesisService::class),
                recordTransformableService: $app->make(RecordTransformableService::class),
                db: $app->make(DatabaseManager::class),
                metadataValidator: $app->make(MetadataValidator::class),
                hydration: $app->make(HydrationService::class),
            );
        });

        // ✅ NemesisService - Service principal (toutes les dépendances injectées)
        $this->app->singleton(NemesisService::class, function (Application $app): NemesisService {
            return new NemesisService(
                repository: $app->make(NemesisTokenRepository::class),
                config: $app->make(NemesisConfigInterface::class),
                str: $app->make(Str::class),
                metadataValidator: $app->make(MetadataValidator::class),
                hydration: $app->make(HydrationService::class),
            );
        });
    }

    private function registerDirectives(): void
    {
        // InstallNemesisDirective - avec toutes les dépendances injectées
        $this->app->singleton(InstallNemesisDirective::class, function (Application $app): InstallNemesisDirective {
            return new InstallNemesisDirective(
                $app->make(DirectiveContext::class),
                $app->make(DirectiveInteractionService::class),
                $app->make(Kernel::class),
                $app,
                $app->make(FileSystemService::class),
                $app->make(DatabaseManager::class),
                $app->make(NemesisConfigInterface::class),
            );
        });

        // ListTokensDirective
        $this->app->singleton(ListTokensDirective::class, function (Application $app): ListTokensDirective {
            return new ListTokensDirective(
                $app->make(DirectiveContext::class),
                $app->make(DirectiveInteractionService::class),
                $app->make(NemesisService::class),
            );
        });

        // CleanTokensDirective - utilise l'interface
        $this->app->singleton(CleanTokensDirective::class, function (Application $app): CleanTokensDirective {
            return new CleanTokensDirective(
                $app->make(DirectiveContext::class),
                $app->make(DirectiveInteractionService::class),
                $app->make(NemesisConfigInterface::class),
                $app->make(NemesisService::class),
            );
        });

        // NemesisCleanDirective
        $this->app->singleton(NemesisCleanDirective::class, function (Application $app): NemesisCleanDirective {
            return new NemesisCleanDirective(
                $app->make(DirectiveContext::class),
                $app->make(DirectiveInteractionService::class),
                $app->make(NemesisConfigInterface::class),
                $app->make(NemesisTokenRepository::class),
            );
        });
    }

    private function registerMiddleware(): void
    {
        // NemesisTokenMiddleware - utilise l'interface
        $this->app->singleton(NemesisTokenMiddleware::class, function (Application $app): NemesisTokenMiddleware {
            return new NemesisTokenMiddleware(
                $app->make(NemesisConfigInterface::class),
                $app->make(NemesisAuthenticationService::class),
                $app->make(HttpHeaderService::class),
                $app->make(HydrationService::class),
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
