<?php

namespace Kani\Nemesis;

use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\File;
use Kani\Nemesis\Console\CreateNemesisToken;

class NemesisServiceProvider extends ServiceProvider
{
    /**
     * Register services.
     */
    public function register(): void
    {
        // Merge la config par défaut
        $this->mergeConfigFrom(__DIR__ . '/../config/nemesis.php', 'nemesis');

        // Enregistrer la commande artisan seulement si on est en console
        if ($this->app->runningInConsole()) {
            $this->commands([
                \Kani\Nemesis\Console\CreateNemesisToken::class,
                \Kani\Nemesis\Console\ResetNemesisQuota::class,
                \Kani\Nemesis\Console\BlockNemesisToken::class,
                \Kani\Nemesis\Console\UnblockNemesisToken::class,
            ]);
        }
    }

    /**
     * Bootstrap services.
     */
    public function boot(): void
    {
        // Publier le fichier de config
        $this->publishes([
            __DIR__ . '/../config/nemesis.php' => config_path('nemesis.php'),
        ], 'config');

        // Publier la migration si elle n'existe pas déjà
        if (! class_exists('CreateNemesisTokensTable')) {
            $this->publishes([
                __DIR__ . '/../database/migrations/create_nemesis_tokens_table.php.stub' =>
                database_path('migrations/' . date('Y_m_d_His', time()) . '_create_nemesis_tokens_table.php'),
            ], 'migrations');
        }
    }

    /**
     * Méthode statique pour nettoyer les références lors de la désinstallation
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
                "/\n\\s*{$providerClass},/",
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
