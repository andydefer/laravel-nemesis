<?php

// src/NemesisServiceProvider.php

declare(strict_types=1);

namespace AndyDefer\Nemesis;

use AndyDefer\Directive\DirectiveKernel;
use AndyDefer\Nemesis\Configs\NemesisConfig;
use AndyDefer\Nemesis\Contracts\Configs\NemesisConfigInterface;
use AndyDefer\Nemesis\Contracts\Helpers\NemesisHelperInterface;
use AndyDefer\Nemesis\Contracts\Repositories\NemesisTokenRepositoryInterface;
use AndyDefer\Nemesis\Contracts\Services\CookieTokenStorageInterface;
use AndyDefer\Nemesis\Contracts\Services\HttpHeaderInterface;
use AndyDefer\Nemesis\Contracts\Services\MetadataValidatorInterface;
use AndyDefer\Nemesis\Contracts\Services\NemesisAuthenticationInterface;
use AndyDefer\Nemesis\Contracts\Services\NemesisInterface;
use AndyDefer\Nemesis\Helpers\AutonomousNemesisHelper;
use AndyDefer\Nemesis\Helpers\NemesisHelper;
use AndyDefer\Nemesis\Http\Middleware\NemesisApiGuestMiddleware;
use AndyDefer\Nemesis\Http\Middleware\NemesisApiVerifiedMiddleware;
use AndyDefer\Nemesis\Http\Middleware\NemesisGuestMiddleware;
use AndyDefer\Nemesis\Http\Middleware\NemesisTokenMiddleware;
use AndyDefer\Nemesis\Http\Middleware\NemesisWebMiddleware;
use AndyDefer\Nemesis\Http\Middleware\NemesisWebVerifiedMiddleware;
use AndyDefer\Nemesis\Repositories\NemesisTokenRepository;
use AndyDefer\Nemesis\Services\CookieTokenStorageService;
use AndyDefer\Nemesis\Services\HttpHeaderService;
use AndyDefer\Nemesis\Services\MetadataValidatorService;
use AndyDefer\Nemesis\Services\NemesisAuthenticationService;
use AndyDefer\Nemesis\Services\NemesisService;
use Illuminate\Config\Repository;
use Illuminate\Contracts\Config\Repository as ConfigRepository;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Database\DatabaseManager;
use Illuminate\Http\Request;
use Illuminate\Routing\Router;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Str;

/**
 * Service provider for the Nemesis authentication package.
 *
 * Registers all services, repositories, middleware, directives, and configuration
 * needed for multi-model token-based authentication with both API and web support.
 *
 * This provider handles the complete registration of:
 * - Configuration and settings management
 * - Repository bindings for token storage
 * - Core services (token management, authentication, metadata validation)
 * - Middleware for API (Bearer token) and Web (cookie-based) authentication
 * - Guest middleware for redirecting authenticated users from public routes
 * - Verified middleware for email verification checks
 */
final class NemesisServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     *
     * This method is called when the service provider is registered.
     * It sets up all container bindings for the package.
     */
    public function register(): void
    {
        $this->bindConfigRepository();
        $this->registerConfig();
        $this->registerRepositories();
        $this->registerServices();
        $this->registerMiddleware();
        $this->registerHelpers();
    }

    /**
     * Bootstrap any application services.
     *
     * This method is called after all service providers have been registered.
     * It handles publishing of configuration and migration files.
     */
    public function boot(): void
    {
        if ($this->app->runningInConsole()) {
            $this->publishes(
                [
                    __DIR__.'/../config/nemesis.php' => config_path('nemesis.php'),
                ],
                'nemesis-config'
            );

            $this->publishes(
                [
                    __DIR__.'/../database/migrations/' => database_path('migrations'),
                ],
                'nemesis-migrations'
            );
        }
    }

    /**
     * Bind the configuration repository to the container.
     *
     * Loads the configuration from the nemesis.php config file and binds
     * it as a singleton to the container for dependency injection.
     */
    private function bindConfigRepository(): void
    {
        $this->app->singleton(
            abstract: ConfigRepository::class,
            concrete: function (Application $app): Repository {
                $config = [];
                $configFile = $app->basePath().'/config/nemesis.php';

                if (file_exists($configFile)) {
                    $loadedConfig = require $configFile;

                    if (is_array($loadedConfig)) {
                        $config = $loadedConfig;
                    }
                }

                return new Repository(['nemesis' => $config]);
            }
        );
    }

    /**
     * Register the configuration service.
     *
     * Binds the NemesisConfigInterface to its concrete implementation
     * as a singleton in the container.
     */
    private function registerConfig(): void
    {
        $this->app->singleton(
            abstract: NemesisConfigInterface::class,
            concrete: function (Application $app): NemesisConfig {
                return new NemesisConfig(
                    $app->make(ConfigRepository::class)
                );
            }
        );
    }

    /**
     * Register repository bindings.
     *
     * Binds the repository interfaces to their concrete implementations
     * for database operations on tokens.
     */
    private function registerRepositories(): void
    {
        $this->app->bind(
            abstract: NemesisTokenRepositoryInterface::class,
            concrete: NemesisTokenRepository::class
        );
    }

    /**
     * Register service bindings.
     *
     * Registers all core services as singletons in the container:
     * - Metadata validator service
     * - HTTP header service
     * - Core token management service (NemesisInterface)
     * - Cookie token storage service
     * - Directive kernel for CLI commands
     * - Authentication service
     */
    private function registerServices(): void
    {
        $this->app->singleton(
            abstract: MetadataValidatorInterface::class,
            concrete: MetadataValidatorService::class
        );

        $this->app->singleton(
            abstract: HttpHeaderInterface::class,
            concrete: function (Application $app): HttpHeaderService {
                return new HttpHeaderService(
                    config: $app->make(NemesisConfigInterface::class),
                    app: $app
                );
            }
        );

        $this->app->singleton(
            abstract: NemesisInterface::class,
            concrete: function (Application $app): NemesisService {
                return new NemesisService(
                    repository: $app->make(NemesisTokenRepositoryInterface::class),
                    config: $app->make(NemesisConfigInterface::class),
                    str: $app->make(Str::class),
                    metadataValidator: $app->make(MetadataValidatorInterface::class)
                );
            }
        );

        $this->app->singleton(
            abstract: CookieTokenStorageInterface::class,
            concrete: function (Application $app): CookieTokenStorageService {
                return new CookieTokenStorageService(
                    nemesisService: $app->make(NemesisInterface::class),
                    config: $app->make(NemesisConfigInterface::class)
                );
            }
        );

        $this->app->singleton(
            abstract: DirectiveKernel::class,
            concrete: function (): DirectiveKernel {
                return DirectiveKernel::init($this->app);
            }
        );

        $this->app->singleton(
            abstract: NemesisAuthenticationInterface::class,
            concrete: function (Application $app): NemesisAuthenticationService {
                return new NemesisAuthenticationService(
                    config: $app->make(NemesisConfigInterface::class),
                    nemesisService: $app->make(NemesisInterface::class),
                    db: $app->make(DatabaseManager::class),
                    metadataValidator: $app->make(MetadataValidatorInterface::class)
                );
            }
        );
    }

    /**
     * Register middleware bindings and aliases.
     *
     * Registers all middleware classes and their aliases for use in routes,
     * and sets up middleware groups for convenient route protection.
     */
    private function registerMiddleware(): void
    {
        /** @var Router $router */
        $router = $this->app->make(Router::class);

        $this->registerApiMiddleware($router);
        $this->registerApiVerifiedMiddleware($router);
        $this->registerWebMiddleware($router);
        $this->registerWebVerifiedMiddleware($router);
        $this->registerGuestMiddleware($router);
        $this->registerApiGuestMiddleware($router);
        $this->registerMiddlewareGroups($router);
    }

    /**
     * Register helper bindings.
     *
     * Registers both NemesisHelper and AutonomousNemesisHelper as singletons.
     * AutonomousNemesisHelper can work with or without middleware.
     */
    private function registerHelpers(): void
    {
        // ✅ Enregistrer le helper standard (dépend du middleware)
        $this->app->singleton(
            abstract: NemesisHelper::class,
            concrete: function (Application $app): NemesisHelper {
                return new NemesisHelper(
                    request: $app->make(Request::class),
                    config: $app->make(NemesisConfigInterface::class)
                );
            }
        );

        // ✅ Enregistrer le helper autonome (peut lire les cookies directement)
        $this->app->singleton(
            abstract: AutonomousNemesisHelper::class,
            concrete: function (Application $app): AutonomousNemesisHelper {
                return new AutonomousNemesisHelper(
                    cookieStorage: $app->make(CookieTokenStorageInterface::class),
                    nemesisService: $app->make(NemesisInterface::class)
                );
            }
        );

        // ✅ Alias : par défaut, on utilise le helper autonome
        $this->app->alias(AutonomousNemesisHelper::class, NemesisHelperInterface::class);
    }

    /**
     * Register the API token middleware.
     *
     * Registers the NemesisTokenMiddleware for protecting API routes
     * with Bearer token authentication.
     *
     * @param  Router  $router  The Laravel router instance
     */
    private function registerApiMiddleware(Router $router): void
    {
        $this->app->singleton(
            abstract: NemesisTokenMiddleware::class,
            concrete: function (Application $app): NemesisTokenMiddleware {
                return new NemesisTokenMiddleware(
                    config: $app->make(NemesisConfigInterface::class),
                    authService: $app->make(NemesisAuthenticationInterface::class),
                    headerService: $app->make(HttpHeaderInterface::class)
                );
            }
        );

        $router->aliasMiddleware(
            name: 'nemesis.token',
            class: NemesisTokenMiddleware::class
        );
    }

    /**
     * Register the API verified middleware.
     *
     * Registers the NemesisApiVerifiedMiddleware for protecting API routes
     * with Bearer token authentication AND email verification check.
     *
     * @param  Router  $router  The Laravel router instance
     */
    private function registerApiVerifiedMiddleware(Router $router): void
    {
        $this->app->singleton(
            abstract: NemesisApiVerifiedMiddleware::class,
            concrete: function (Application $app): NemesisApiVerifiedMiddleware {
                return new NemesisApiVerifiedMiddleware(
                    authService: $app->make(NemesisAuthenticationInterface::class),
                    config: $app->make(NemesisConfigInterface::class)
                );
            }
        );

        $router->aliasMiddleware(
            name: 'nemesis.api.verified',
            class: NemesisApiVerifiedMiddleware::class
        );
    }

    /**
     * Register the web cookie-based middleware.
     *
     * Registers the NemesisWebMiddleware for protecting web routes
     * with cookie-based token authentication.
     *
     * @param  Router  $router  The Laravel router instance
     */
    private function registerWebMiddleware(Router $router): void
    {
        $this->app->singleton(
            abstract: NemesisWebMiddleware::class,
            concrete: function (Application $app): NemesisWebMiddleware {
                return new NemesisWebMiddleware(
                    cookieTokenStorage: $app->make(CookieTokenStorageInterface::class),
                    config: $app->make(NemesisConfigInterface::class),
                    authService: $app->make(NemesisAuthenticationInterface::class)
                );
            }
        );

        $router->aliasMiddleware(
            name: 'nemesis.web',
            class: NemesisWebMiddleware::class
        );
    }

    /**
     * Register the web verified middleware.
     *
     * Registers the NemesisWebVerifiedMiddleware for protecting web routes
     * with cookie-based token authentication AND email verification check.
     *
     * @param  Router  $router  The Laravel router instance
     */
    private function registerWebVerifiedMiddleware(Router $router): void
    {
        $this->app->singleton(
            abstract: NemesisWebVerifiedMiddleware::class,
            concrete: function (Application $app): NemesisWebVerifiedMiddleware {
                return new NemesisWebVerifiedMiddleware(
                    cookieTokenStorage: $app->make(CookieTokenStorageInterface::class),
                    authService: $app->make(NemesisAuthenticationInterface::class),
                    config: $app->make(NemesisConfigInterface::class)
                );
            }
        );

        $router->aliasMiddleware(
            name: 'nemesis.web.verified',
            class: NemesisWebVerifiedMiddleware::class
        );
    }

    /**
     * Register the web guest middleware.
     *
     * Registers the NemesisGuestMiddleware for protecting guest-only routes
     * by redirecting authenticated users away from login/registration pages.
     *
     * @param  Router  $router  The Laravel router instance
     */
    private function registerGuestMiddleware(Router $router): void
    {
        $this->app->singleton(
            abstract: NemesisGuestMiddleware::class,
            concrete: function (Application $app): NemesisGuestMiddleware {
                return new NemesisGuestMiddleware(
                    cookieTokenStorage: $app->make(CookieTokenStorageInterface::class),
                    authService: $app->make(NemesisAuthenticationInterface::class),
                    config: $app->make(NemesisConfigInterface::class)
                );
            }
        );

        $router->aliasMiddleware(
            name: 'nemesis.guest',
            class: NemesisGuestMiddleware::class
        );
    }

    /**
     * Register the API guest middleware.
     *
     * Registers the NemesisApiGuestMiddleware for protecting API guest-only routes
     * by returning a 400 error when authenticated users try to access them.
     *
     * @param  Router  $router  The Laravel router instance
     */
    private function registerApiGuestMiddleware(Router $router): void
    {
        $this->app->singleton(
            abstract: NemesisApiGuestMiddleware::class,
            concrete: function (Application $app): NemesisApiGuestMiddleware {
                return new NemesisApiGuestMiddleware(
                    authService: $app->make(NemesisAuthenticationInterface::class),
                    config: $app->make(NemesisConfigInterface::class)
                );
            }
        );

        $router->aliasMiddleware(
            name: 'nemesis.api.guest',
            class: NemesisApiGuestMiddleware::class
        );
    }

    /**
     * Register middleware groups.
     *
     * Defines convenient middleware groups for common use cases:
     * - 'nemesis' for API authentication with Bearer token
     * - 'nemesis.web' for web authentication with cookie
     *
     * @param  Router  $router  The Laravel router instance
     */
    private function registerMiddlewareGroups(Router $router): void
    {
        $router->middlewareGroup(
            name: 'nemesis',
            middleware: [NemesisTokenMiddleware::class]
        );

        $router->middlewareGroup(
            name: 'nemesis.web',
            middleware: [NemesisWebMiddleware::class]
        );

        $router->middlewareGroup(
            name: 'nemesis.api.verified',
            middleware: [NemesisApiVerifiedMiddleware::class]
        );

        $router->middlewareGroup(
            name: 'nemesis.web.verified',
            middleware: [NemesisWebVerifiedMiddleware::class]
        );
    }

    /**
     * Get the application instance.
     *
     * @return Application The Laravel application instance
     */
    public function getApp(): Application
    {
        return $this->app;
    }
}
