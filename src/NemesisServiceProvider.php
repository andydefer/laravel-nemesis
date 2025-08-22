<?php

namespace Kani\Nemesis;

use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\File;

class NemesisServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__ . '/../config/nemesis.php', 'nemesis');
    }

    public function boot(): void
    {
        $this->publishes([
            __DIR__ . '/../config/nemesis.php' => config_path('nemesis.php'),
        ], 'config');

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
    public static function cleanup()
    {
        $configPath = config_path('app.php');

        if (!File::exists($configPath)) {
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
            // Logger l'erreur silencieusement sans interrompre le processus
            error_log("Nemesis cleanup error: " . $e->getMessage());
        }
    }
}
