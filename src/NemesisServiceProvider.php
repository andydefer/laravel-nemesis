<?php

namespace Kani\Nemesis;

use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Schema;
use Illuminate\Routing\Router;
use Kani\Nemesis\Console\CreateNemesisToken;
use Kani\Nemesis\Console\ResetNemesisQuota;
use Kani\Nemesis\Console\BlockNemesisToken;
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

        // Publier la migration si elle n'existe pas déjà
        if (! Schema::hasTable('nemesis_tokens')) {
            $migrationFile = database_path('migrations/' . date('Y_m_d_His') . '_create_nemesis_tokens_table.php');

            $this->publishes([
                __DIR__ . '/../database/migrations/create_nemesis_tokens_table.php.stub' => $migrationFile,
            ], 'migrations');
        }

        // 👉 Enregistrement du middleware
        $router->aliasMiddleware('nemesis', NemesisMiddleware::class);
    }

    /**
     * Méthode statique pour nettoyer les références lors de la désinstallation.
     */
    public static function cleanup(): void
    {
        $configPath = config_path('app.php');

        if (! File::exists($configPath)) {
            return;
        }

        try {
            $content = File::get($configPath);
            $providerClass = 'Kani\\Nemesis\\NemesisServiceProvider::class';

            // Pattern pour trouver la ligne du provider (avec différentes indentations possibles)
            $patterns = [
                "/\n\s*{$providerClass},/",
                "/{$providerClass},/",
            ];

            $newContent = $content;
            foreach ($patterns as $pattern) {
                $newContent = preg_replace($pattern, '', $newContent);
            }

            // Vérifier si le contenu a changé avant d'écrire
            if ($newContent !== $content) {
                File::put($configPath, $newContent);
            }
        } catch (\Exception $e) {
            // Logger l'erreur silencieusement
            error_log("Nemesis cleanup error: " . $e->getMessage());
        }
    }
}
