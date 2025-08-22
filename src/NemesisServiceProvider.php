<?php

namespace Kani\Nemesis;

use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\Route;

class NemesisServiceProvider extends ServiceProvider
{
    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Enregistrer le middleware en alias utilisable dans les routes
        $router = $this->app['router'];
        $router->aliasMiddleware('nemesis', \Kani\Nemesis\Http\Middleware\NemesisMiddleware::class);

        // (Optionnel) Tu peux publier la config si tu en as une
        $this->publishes([
            __DIR__ . '/../config/nemesis.php' => config_path('nemesis.php'),
        ], 'nemesis-config');

        // (Optionnel) Charger les migrations si tu veux que les tokens soient persistés
        $this->loadMigrationsFrom(__DIR__ . '/../database/migrations');
    }

    /**
     * Register any application services.
     */
    public function register(): void
    {
        // Fusionner la config par défaut
        $this->mergeConfigFrom(
            __DIR__ . '/../config/nemesis.php',
            'nemesis'
        );
    }
}
