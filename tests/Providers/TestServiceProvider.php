<?php

declare(strict_types=1);

namespace AndyDefer\Nemesis\Tests\Providers;

use Illuminate\Support\ServiceProvider;

/**
 * Service provider for test environment.
 * Loads migrations from the tests/database/migrations directory.
 */
final class TestServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        // ✅ Charger les migrations depuis tests/database/migrations
        if (is_dir(__DIR__.'/../../database/migrations')) {

            $this->loadMigrationsFrom(__DIR__.'/../../database/migrations');
        }
    }

    public function register(): void
    {
        // Rien à enregistrer pour les tests
    }
}
