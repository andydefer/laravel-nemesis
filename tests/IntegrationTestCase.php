<?php

declare(strict_types=1);

namespace Kani\Nemesis\Tests;

use Carbon\Carbon;
use Illuminate\Foundation\Application;
use Kani\Nemesis\NemesisServiceProvider;
use Orchestra\Testbench\TestCase as Orchestra;

/**
 * Base test case for integration tests that need Laravel.
 *
 * ⚠️ RÈGLE : Les tests qui héritent de cette classe :
 * - PEUVENT utiliser la base de données
 * - PEUVENT utiliser les facades Laravel
 * - PEUVENT faire des requêtes HTTP
 * - SQLite in-memory database for fast, isolated tests
 * - Frozen time for deterministic tests
 */
abstract class IntegrationTestCase extends Orchestra
{
    protected function setUp(): void
    {
        parent::setUp();

        // Freeze time for deterministic test results
        Carbon::setTestNow(Carbon::create(2024, 1, 1, 12, 0, 0));

        $this->runMigrations();
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
        \Mockery::close();
    }

    /**
     * Get the package service providers to register.
     *
     * @param Application $app
     * @return array<int, class-string>
     */
    protected function getPackageProviders($app): array
    {
        return [
            NemesisServiceProvider::class,
        ];
    }

    /**
     * Configure the test environment.
     *
     * @param Application $app
     */
    protected function getEnvironmentSetUp($app): void
    {
        // Configure SQLite in-memory database
        $app['config']->set('database.default', 'testbench');
        $app['config']->set('database.connections.testbench', [
            'driver' => 'sqlite',
            'database' => ':memory:',
            'prefix' => '',
            'foreign_key_constraints' => false,
        ]);

        // Configure Nemesis package defaults for testing
        $app['config']->set('nemesis.token_length', 64);
        $app['config']->set('nemesis.hash_algorithm', 'sha256');
        $app['config']->set('nemesis.middleware.parameter_name', 'nemesisAuth');
        $app['config']->set('nemesis.expiration', 60);
    }

    /**
     * Run database migrations for tests.
     */
    protected function runMigrations(): void
    {
        // Load package migrations
        $packageMigrationsPath = __DIR__ . '/../database/migrations';
        if (is_dir($packageMigrationsPath)) {
            $this->loadMigrationsFrom($packageMigrationsPath);
        }

        // Load test-specific migrations
        $testMigrationsPath = __DIR__ . '/database/migrations';
        if (is_dir($testMigrationsPath)) {
            $this->loadMigrationsFrom($testMigrationsPath);
        }

        // Run migrations
        $this->artisan('migrate', [
            '--database' => 'testbench',
            '--force' => true,
        ])->run();
    }
}
