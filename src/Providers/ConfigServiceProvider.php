<?php

declare(strict_types=1);

namespace AndyDefer\Nemesis\Providers;

use Illuminate\Config\Repository;
use Illuminate\Contracts\Config\Repository as ConfigRepository;
use Illuminate\Support\ServiceProvider;

final class ConfigServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(ConfigRepository::class, function ($app) {
            $config = [];
            $configFile = $app->basePath().'/config/nemesis.php';

            if (file_exists($configFile)) {
                $config = require $configFile;
                if (! is_array($config)) {
                    $config = [];
                }
            }

            $repository = new Repository(['nemesis' => $config]);

            // ✅ Enregistrer le service 'config'
            $this->app->instance('config', $repository);

            return $repository;
        });

        // ✅ Alias 'config' vers ConfigRepository
        $this->app->alias(ConfigRepository::class, 'config');
    }
}
