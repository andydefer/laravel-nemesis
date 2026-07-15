<?php

// src/Directives/InstallNemesisDirective.php

declare(strict_types=1);

namespace AndyDefer\Nemesis\Directives;

use AndyDefer\ConsoleWriter\Console\Enums\ListStyle;
use AndyDefer\Directive\AbstractDirective;
use AndyDefer\Directive\Enums\ExitCode;
use AndyDefer\DomainStructures\Collections\Utility\StringTypedCollection;
use AndyDefer\DomainStructures\Utils\SetCollection;
use AndyDefer\Nemesis\Contracts\Configs\NemesisConfigInterface;
use AndyDefer\Nemesis\Contracts\MustNemesis;
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
        return 'nemesis:install {--force}#"Force overwrite existing files"';
    }

    public function getDescription(): string
    {
        return 'Install the Nemesis package for multi-model token authentication';
    }

    public function getAliases(): StringTypedCollection
    {
        return StringTypedCollection::from(['nemesis-install', 'setup-nemesis']);
    }

    private function getSchemaBuilder()
    {
        $app = $this->getApplication()->make(Application::class);
        $db = $this->getApplication()->make(DatabaseManager::class);
        $connection = $db->connection();

        return $connection->getSchemaBuilder();
    }

    public function execute(): ExitCode
    {
        $console = $this->getConsole();
        $app = $this->getApplication()->make(Application::class);
        $kernel = $this->getApplication()->make(Kernel::class);
        $config = $this->getApplication()->make(NemesisConfigInterface::class);
        $filesystem = new FileSystemService;

        $force = $this->isFlagActive('force');

        if (! $force && ! $this->confirm('Install Nemesis package? This will publish migrations and config.')) {
            $console->alertWarning('Installation cancelled.');

            return ExitCode::SUCCESS;
        }

        // ========================================================================
        // 1. VÉRIFIER QUE LE PACKAGE EXISTE
        // ========================================================================
        $packageRoot = getcwd().'/vendor/andydefer/laravel-nemesis';

        $console->info("\n📦 Checking package files...");

        if (! $filesystem->exists($packageRoot)) {
            $console->error("Package not found at: {$packageRoot}");
            $console->error('Please run: composer require andydefer/laravel-nemesis');

            return ExitCode::FAILURE;
        }
        $console->info('  ✓ Package found');

        // ========================================================================
        // 2. COPIER LA CONFIGURATION
        // ========================================================================
        $console->info("\n📄 Publishing configuration...");

        $configSource = $packageRoot.'/config/nemesis.php';
        $configDestination = $app->basePath('config/nemesis.php');

        if (! $filesystem->exists($configSource)) {
            $console->error("Config source not found: {$configSource}");

            return ExitCode::FAILURE;
        }

        if ($filesystem->exists($configDestination) && ! $force) {
            $console->alertWarning('Config already exists, use --force to overwrite');
        } else {
            $this->ensureDirectoryExists($filesystem, dirname($configDestination));
            $content = $filesystem->get($configSource);
            $filesystem->put($configDestination, $content);
            $console->info('  ✓ Config published to: config/nemesis.php');
        }

        // ========================================================================
        // 3. COPIER LA MIGRATION
        // ========================================================================
        $console->info("\n🗄️ Publishing migration...");

        $migrationSource = $packageRoot.'/database/migrations/2024_01_01_000001_create_nemesis_tokens_table.php';
        $migrationDestination = $app->databasePath('migrations/2024_01_01_000001_create_nemesis_tokens_table.php');

        if (! $filesystem->exists($migrationSource)) {
            $console->error("Migration source not found: {$migrationSource}");

            return ExitCode::FAILURE;
        }

        if ($filesystem->exists($migrationDestination) && ! $force) {
            $console->alertWarning('Migration already exists, use --force to overwrite');
        } else {
            $this->ensureDirectoryExists($filesystem, dirname($migrationDestination));
            $content = $filesystem->get($migrationSource);
            $filesystem->put($migrationDestination, $content);
            $console->info('  ✓ Migration published');
        }

        // ========================================================================
        // 4. LANCER LES MIGRATIONS VIA ARTISAN
        // ========================================================================
        $console->info("\n🗄️ Running migrations...");

        $exitCode = $kernel->call('migrate', ['--force' => true]);

        if ($exitCode !== 0) {
            $console->error('Failed to run migrations.');

            return ExitCode::FAILURE;
        }
        $console->info('  ✓ Migrations executed');

        // ========================================================================
        // 5. VÉRIFIER QUE LA TABLE A ÉTÉ CRÉÉE
        // ========================================================================
        $console->info("\n✅ Verifying database table...");

        $schemaBuilder = $this->getSchemaBuilder();

        if (! $schemaBuilder->hasTable('nemesis_tokens')) {
            $console->error("Table 'nemesis_tokens' not found.");

            return ExitCode::FAILURE;
        }
        $console->info('  ✓ Table "nemesis_tokens" exists');

        // ========================================================================
        // 6. VÉRIFIER LA CONFIGURATION
        // ========================================================================
        $console->info("\n⚙️ Loading configuration...");
        $console->info('  ✓ token_length: '.$config->tokenConfig()->token_length);
        $console->info('  ✓ hash_algorithm: '.$config->tokenConfig()->hash_algorithm);
        $console->info('  ✓ expiration: '.($config->tokenConfig()->expiration_minutes ?? 'never').' minutes');
        $console->info('  ✓ validate_origin: '.($config->middlewareConfig()->validate_origin ? 'true' : 'false'));

        // ========================================================================
        // 7. INSTALLATION FINALE
        // ========================================================================
        $console->info("\n✨ Nemesis package installed successfully!");
        $console->newLine();

        $console->info('📝 Next steps:');

        $steps = SetCollection::from([
            'Implement the MustNemesis interface on your models: class User extends Model implements '.MustNemesis::class,
            'Define the nemesisFormat() method: public function nemesisFormat(): AbstractData { return new UserData(...); }',
            'Create tokens for your models: $record = NemesisTokenRecord::from([...]); [$token, $plainToken] = $nemesisService->createWithPlainToken($record, $user);',
            'Protect your routes with middleware: Route::middleware(["nemesis.token"])->group(...);',
            'Use the NemesisHelper facade: NemesisHelper::getCurrentAuthenticatable() or NemesisHelper::getCurrentAuthenticatableFormat()',
        ]);

        $console->list($steps, ListStyle::NUMBER);

        return ExitCode::SUCCESS;
    }

    private function ensureDirectoryExists(FileSystemInterface $filesystem, string $path): void
    {
        if (! $filesystem->isDirectory($path)) {
            $filesystem->makeDirectory($path, PermissionMode::DIRECTORY, true);
        }
    }
}
