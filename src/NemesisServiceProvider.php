<?php

declare(strict_types=1);

namespace AndyDefer\Nemesis;

use AndyDefer\Directive\DirectiveKernel;
use AndyDefer\Nemesis\Configs\NemesisConfig;
use AndyDefer\Nemesis\Contracts\Configs\NemesisConfigInterface;
use AndyDefer\Nemesis\Contracts\Repositories\NemesisTokenRepositoryInterface;
use AndyDefer\Nemesis\Contracts\Services\HttpHeaderInterface;
use AndyDefer\Nemesis\Contracts\Services\MetadataValidatorInterface;
use AndyDefer\Nemesis\Contracts\Services\NemesisAuthenticationInterface;
use AndyDefer\Nemesis\Contracts\Services\NemesisInterface;
use AndyDefer\Nemesis\Http\Middleware\NemesisTokenMiddleware;
use AndyDefer\Nemesis\Repositories\NemesisTokenRepository;
use AndyDefer\Nemesis\Services\HttpHeaderService;
use AndyDefer\Nemesis\Services\MetadataValidatorService;
use AndyDefer\Nemesis\Services\NemesisAuthenticationService;
use AndyDefer\Nemesis\Services\NemesisService;
use Illuminate\Config\Repository;
use Illuminate\Contracts\Config\Repository as ConfigRepository;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Database\DatabaseManager;
use Illuminate\Routing\Router;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Str;

/**
 * Service provider for the Nemesis package.
 *
 * Registers all services, directives, middleware, and configuration
 * for the token management and authentication system.
 */
final class NemesisServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        // ✅ Lier ConfigRepository AVANT tout
        $this->app->singleton(ConfigRepository::class, function ($app) {
            $config = [];
            $configFile = $app->basePath().'/config/nemesis.php';

            if (file_exists($configFile)) {
                $config = require $configFile;
                if (! is_array($config)) {
                    $config = [];
                }
            }

            // ✅ Ajouter la config sous la clé 'nemesis'
            return new Repository(['nemesis' => $config]);
        });

        $this->registerConfig();
        $this->registerRepositories();
        $this->registerServices();
        $this->registerMiddleware();
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        if ($this->app->runningInConsole()) {
            $this->publishes([
                __DIR__.'/../config/nemesis.php' => config_path('nemesis.php'),
            ], 'nemesis-config');

            $this->publishes([
                __DIR__.'/../database/migrations/' => database_path('migrations'),
            ], 'nemesis-migrations');
        }
    }

    /**
     * Register the configuration service.
     */
    private function registerConfig(): void
    {
        $this->app->singleton(
            abstract: NemesisConfigInterface::class,
            concrete: function ($app) {
                return new NemesisConfig(
                    $app->make(ConfigRepository::class)
                );
            }
        );
    }

    /**
     * Register all repositories.
     */
    private function registerRepositories(): void
    {
        $this->app->bind(
            abstract: NemesisTokenRepositoryInterface::class,
            concrete: NemesisTokenRepository::class,
        );
    }

    /**
     * Register all services.
     */
    private function registerServices(): void
    {
        // ✅ MetadataValidatorInterface - bind interface to concrete
        $this->app->singleton(
            abstract: MetadataValidatorInterface::class,
            concrete: MetadataValidatorService::class,
        );

        // ✅ HttpHeaderInterface - bind interface to concrete
        $this->app->singleton(
            abstract: HttpHeaderInterface::class,
            concrete: function (Application $app): HttpHeaderService {
                return new HttpHeaderService(
                    config: $app->make(NemesisConfigInterface::class),
                    app: $app,
                );
            }
        );

        // ✅ NemesisInterface - Service principal de gestion des tokens
        $this->app->singleton(
            abstract: NemesisInterface::class,
            concrete: function (Application $app): NemesisService {
                return new NemesisService(
                    repository: $app->make(NemesisTokenRepositoryInterface::class),
                    config: $app->make(NemesisConfigInterface::class),
                    str: $app->make(Str::class),
                    metadataValidator: $app->make(MetadataValidatorInterface::class),
                );
            }

        );

        // ✅ DirectiveKernel
        $this->app->singleton(
            abstract: DirectiveKernel::class,
            concrete: function () {
                return DirectiveKernel::init($this->app);
            }
        );

        // ✅ NemesisAuthenticationInterface - Service d'authentification
        $this->app->singleton(
            abstract: NemesisAuthenticationInterface::class,
            concrete: function (Application $app): NemesisAuthenticationService {
                return new NemesisAuthenticationService(
                    config: $app->make(NemesisConfigInterface::class),
                    nemesisService: $app->make(NemesisInterface::class),
                    db: $app->make(DatabaseManager::class),
                    metadataValidator: $app->make(MetadataValidatorInterface::class),
                );
            }
        );
    }

    /**
     * Register the token middleware.
     */
    private function registerMiddleware(): void
    {
        // NemesisTokenMiddleware
        $this->app->singleton(
            abstract: NemesisTokenMiddleware::class,
            concrete: function (Application $app): NemesisTokenMiddleware {
                return new NemesisTokenMiddleware(
                    config: $app->make(NemesisConfigInterface::class),
                    authService: $app->make(NemesisAuthenticationInterface::class),
                    headerService: $app->make(HttpHeaderInterface::class),
                );
            }
        );

        /** @var Router $router */
        $router = $this->app->make(Router::class);

        $router->aliasMiddleware(
            name: 'nemesis.token',
            class: NemesisTokenMiddleware::class
        );

        $router->middlewareGroup(
            name: 'nemesis',
            middleware: [
                NemesisTokenMiddleware::class,
            ]
        );
    }

    public function getApp(): Application
    {
        return $this->app;
    }
}
