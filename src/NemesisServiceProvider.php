<?php

namespace Kani\Nemesis;

use Illuminate\Support\ServiceProvider;

class NemesisServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/nemesis.php', 'nemesis');
    }

    public function boot(): void
    {
        $this->publishes([
            __DIR__.'/../config/nemesis.php' => config_path('nemesis.php'),
        ], 'config');

        if (! class_exists('CreateNemesisTokensTable')) {
            $this->publishes([
                __DIR__.'/../database/migrations/create_nemesis_tokens_table.php.stub' =>
                    database_path('migrations/'.date('Y_m_d_His', time()).'_create_nemesis_tokens_table.php'),
            ], 'migrations');
        }
    }
}
