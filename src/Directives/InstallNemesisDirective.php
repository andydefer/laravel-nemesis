<?php

// src/Directives/InstallNemesisDirective.php

declare(strict_types=1);

namespace AndyDefer\Nemesis\Directives;

use AndyDefer\Directive\AbstractDirective;
use AndyDefer\Directive\Enums\ExitCode;
use AndyDefer\DomainStructures\Collections\Utility\StringTypedCollection;
use AndyDefer\Nemesis\Contracts\Configs\NemesisConfigInterface;
use AndyDefer\PhpServices\Contracts\FileSystemInterface;
use AndyDefer\PhpServices\Enums\PermissionMode;
use AndyDefer\PhpServices\Services\FileSystemService;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Database\DatabaseManager;

final class InstallNemesisDirective extends AbstractDirective
{
    public function getSignature(): string
    {
        return 'install-nemesis {--force}';
    }

    public function getDescription(): string
    {
        return 'Install the Nemesis package for multi-model token authentication';
    }

    public function getAliases(): StringTypedCollection
    {
        $aliases = new StringTypedCollection;
        $aliases->add('nemesis-install');
        $aliases->add('setup-nemesis');

        return $aliases;
    }

    public function shouldBootLaravel(): bool
    {
        return true;
    }

    private function getSchemaBuilder()
    {
        $laravel = $this->getLaravel();
        $db = $laravel->make(DatabaseManager::class);
        $connection = $db->connection();

        return $connection->getSchemaBuilder();
    }

    public function execute(): ExitCode
    {
        $laravel = $this->getLaravel();

        $kernel = $laravel->make(Kernel::class);
        $app = $laravel->make(Application::class);
        $config = $laravel->make(NemesisConfigInterface::class);
        $filesystem = new FileSystemService;

        $force = $this->hasOption('force');

        if (! $force && ! $this->confirm('Install Nemesis package? This will publish migrations and config.')) {
            $this->warn('Installation cancelled.');

            return ExitCode::SUCCESS;
        }

        // ========================================================================
        // 1. VÉRIFIER QUE LE PACKAGE EXISTE
        // ========================================================================
        $packageRoot = getcwd().'/vendor/andydefer/laravel-nemesis';

        $this->info("\n📦 Checking package files...");

        if (! $filesystem->exists($packageRoot)) {
            $this->error("Package not found at: {$packageRoot}");
            $this->error('Please run: composer require andydefer/laravel-nemesis');

            return ExitCode::FAILURE;
        }
        $this->info('  ✓ Package found');

        // ========================================================================
        // 2. COPIER LA CONFIGURATION
        // ========================================================================
        $this->info("\n📄 Publishing configuration...");

        $configSource = $packageRoot.'/config/nemesis.php';
        $configDestination = $app->basePath('config/nemesis.php');

        if (! $filesystem->exists($configSource)) {
            $this->error("Config source not found: {$configSource}");

            return ExitCode::FAILURE;
        }

        if ($filesystem->exists($configDestination) && ! $force) {
            $this->warn('Config already exists, use --force to overwrite');
        } else {
            $this->ensureDirectoryExists($filesystem, dirname($configDestination));
            $content = $filesystem->get($configSource);
            $filesystem->put($configDestination, $content);
            $this->info('  ✓ Config published to: config/nemesis.php');
        }

        // ========================================================================
        // 3. COPIER LA MIGRATION
        // ========================================================================
        $this->info("\n🗄️ Publishing migration...");

        $migrationSource = $packageRoot.'/database/migrations/2024_01_01_000001_create_nemesis_tokens_table.php';
        $migrationDestination = $app->databasePath('migrations/2024_01_01_000001_create_nemesis_tokens_table.php');

        if (! $filesystem->exists($migrationSource)) {
            $this->error("Migration source not found: {$migrationSource}");

            return ExitCode::FAILURE;
        }

        if ($filesystem->exists($migrationDestination) && ! $force) {
            $this->warn('Migration already exists, use --force to overwrite');
        } else {
            $this->ensureDirectoryExists($filesystem, dirname($migrationDestination));
            $content = $filesystem->get($migrationSource);
            $filesystem->put($migrationDestination, $content);
            $this->info('  ✓ Migration published');
        }

        // ========================================================================
        // 4. LANCER LES MIGRATIONS VIA ARTISAN
        // ========================================================================
        $this->info("\n🗄️ Running migrations...");

        $exitCode = $kernel->call('migrate', ['--force' => true]);

        if ($exitCode !== 0) {
            $this->error('Failed to run migrations.');

            return ExitCode::FAILURE;
        }
        $this->info('  ✓ Migrations executed');

        // ========================================================================
        // 5. VÉRIFIER QUE LA TABLE A ÉTÉ CRÉÉE
        // ========================================================================
        $this->info("\n✅ Verifying database table...");

        $schemaBuilder = $this->getSchemaBuilder();

        if (! $schemaBuilder->hasTable('nemesis_tokens')) {
            $this->error("Table 'nemesis_tokens' not found.");

            return ExitCode::FAILURE;
        }
        $this->info('  ✓ Table "nemesis_tokens" exists');

        // ========================================================================
        // 6. VÉRIFIER LA CONFIGURATION
        // ========================================================================
        $this->info("\n⚙️ Loading configuration...");
        $this->info('  ✓ token_length: '.$config->tokenConfig()->token_length);
        $this->info('  ✓ hash_algorithm: '.$config->tokenConfig()->hash_algorithm);
        $this->info('  ✓ expiration: '.($config->tokenConfig()->expiration_minutes ?? 'never').' minutes');
        $this->info('  ✓ validate_origin: '.($config->middlewareConfig()->validate_origin ? 'true' : 'false'));

        // ========================================================================
        // 7. INSTALLATION FINALE
        // ========================================================================
        $this->info("\n✨ Nemesis package installed successfully!");
        $this->newLine();
        $this->info('📝 Next steps:');
        $this->line('   1. Add the HasNemesisTokens trait to your models:');
        $this->line('      use AndyDefer\\Nemesis\\Traits\\HasNemesisTokens;');
        $this->line('      use AndyDefer\\Nemesis\\Contracts\\MustNemesis;');
        $this->line('');
        $this->line('   2. Implement the MustNemesis interface on your model:');
        $this->line('      class User extends Authenticatable implements MustNemesis');
        $this->line('');
        $this->line('   3. Create tokens for your models:');
        $this->line('      $token = $user->createNemesisToken("Mobile App", "phone");');
        $this->line('');
        $this->line('   4. Protect your routes with middleware:');
        $this->line('      Route::middleware(["nemesis.token"])->group(...);');

        return ExitCode::SUCCESS;
    }

    private function ensureDirectoryExists(FileSystemInterface $filesystem, string $path): void
    {
        if (! $filesystem->isDirectory($path)) {
            $filesystem->makeDirectory($path, PermissionMode::DIRECTORY, true);
        }
    }
}
