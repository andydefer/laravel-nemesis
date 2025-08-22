<?php

namespace Kani\Nemesis;

use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Schema;
use Illuminate\Routing\Router;
use Kani\Nemesis\Console\CreateNemesisToken;
use Kani\Nemesis\Console\ResetNemesisQuota;
use Kani\Nemesis\Console\BlockNemesisToken;
use Kani\Nemesis\Console\ListNemesisTokens;
use Kani\Nemesis\Console\UnblockNemesisToken;
use Kani\Nemesis\Http\Middleware\NemesisMiddleware;

class NemesisServiceProvider extends ServiceProvider
{
    /**
     * Register services.
     */
    public function register(): void
    {
        // Merge la config par défaut
        $this->mergeConfigFrom(__DIR__ . '/../config/nemesis.php', 'nemesis');

        // Enregistrer les commandes artisan seulement si on est en console
        if ($this->app->runningInConsole()) {
            $this->commands([
                CreateNemesisToken::class,
                ResetNemesisQuota::class,
                BlockNemesisToken::class,
                UnblockNemesisToken::class,
                ListNemesisTokens::class
            ]);
        }
    }

    /**
     * Bootstrap services.
     */
    public function boot(Router $router): void
    {
        // Publier le fichier de config
        $this->publishes([
            __DIR__ . '/../config/nemesis.php' => config_path('nemesis.php'),
        ], 'config');

        // Enregistrer le middleware
        $router->aliasMiddleware('nemesis', NemesisMiddleware::class);

        // Publier la migration si elle n'existe pas déjà
        if (! Schema::hasTable('nemesis_tokens')) {
            $timestamp = date('Y_m_d_His') . rand(1000, 9999); // suffixe aléatoire pour garantir l'unicité
            $this->publishes([
                __DIR__ . '/../database/migrations/create_nemesis_tokens_table.php.stub' =>
                database_path("migrations/{$timestamp}_create_nemesis_tokens_table.php"),
            ], 'nemesis-migrations');
        }
    }

    /**
     * Méthode statique pour nettoyer les références lors de la désinstallation.
     */
    public static function cleanup(): void
    {
        try {
            // Supprimer le fichier de config
            $configPath = config_path('nemesis.php');
            if (File::exists($configPath)) {
                File::delete($configPath);
            }

            // Supprimer le provider de app.php
            $appConfigPath = config_path('app.php');
            if (File::exists($appConfigPath)) {
                $content = File::get($appConfigPath);
                $providerClass = 'Kani\\Nemesis\\NemesisServiceProvider::class';

                // Pattern pour supprimer le provider
                $patterns = [
                    "/\s*{$providerClass},\s*/",
                    "/{$providerClass},\s*/",
                    "/\s*{$providerClass}\s*/",
                ];

                $newContent = $content;
                foreach ($patterns as $pattern) {
                    $newContent = preg_replace($pattern, '', $newContent);
                }

                // Nettoyer les lignes vides multiples
                $newContent = preg_replace("/\n{3,}/", "\n\n", $newContent);

                if ($newContent !== $content) {
                    File::put($appConfigPath, $newContent);
                }
            }

            // Supprimer les caches
            if (function_exists('app')) {
                app('cache')->forget('spatie.permission.cache');
            }
        } catch (\Exception $e) {
            // Logger silencieusement
            if (function_exists('logger')) {
                logger()->error("Nemesis cleanup error: " . $e->getMessage());
            }
        }
    }
}
