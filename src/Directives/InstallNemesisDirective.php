<?php

declare(strict_types=1);

namespace AndyDefer\Nemesis\Directives;

use AndyDefer\ConsoleWriter\Console\Components\Timeline;
use AndyDefer\Directive\AbstractDirective;
use AndyDefer\Directive\Enums\ExitCode;
use AndyDefer\DomainStructures\Collections\Utility\StringTypedCollection;
use AndyDefer\DomainStructures\Utils\ListCollection;
use AndyDefer\Nemesis\Contracts\Configs\NemesisConfigInterface;
use AndyDefer\Nemesis\Contracts\MustNemesis;
use AndyDefer\PhpServices\Contracts\FileSystemInterface;
use AndyDefer\PhpServices\Enums\PermissionMode;
use AndyDefer\PhpServices\Services\FileSystemService;
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
        $db = $this->getApplication()->make(DatabaseManager::class);
        $connection = $db->connection();

        return $connection->getSchemaBuilder();
    }

    public function execute(): ExitCode
    {
        $console = $this->getConsole();
        $app = $this->getApplication()->make(Application::class);
        $config = $this->getApplication()->make(NemesisConfigInterface::class);
        $filesystem = new FileSystemService;

        $force = $this->isFlagActive('force');

        if (! $force && ! $this->confirm('Install Nemesis package? This will publish migrations and config.')) {
            $console->alertWarning('Installation cancelled.');

            return ExitCode::SUCCESS;
        }

        $console->title('🔐 Nemesis Installation');

        // ========================================================================
        // 1. VÉRIFIER QUE LE PACKAGE EXISTE
        // ========================================================================
        $packageRoot = getcwd().'/vendor/andydefer/laravel-nemesis';

        $console->info('📦 Checking package files...');

        if (! $filesystem->exists($packageRoot)) {
            $console->error("Package not found at: {$packageRoot}");
            $console->error('Please run: composer require andydefer/laravel-nemesis');

            return ExitCode::FAILURE;
        }
        $console->success('  ✓ Package found');

        // ========================================================================
        // 2. COPIER LA CONFIGURATION
        // ========================================================================
        $console->info('📄 Publishing configuration...');

        $configSource = $packageRoot.'/config/nemesis.php';
        $configDestination = $app->basePath('config/nemesis.php');

        if (! $filesystem->exists($configSource)) {
            $console->error("Config source not found: {$configSource}");

            return ExitCode::FAILURE;
        }

        if ($filesystem->exists($configDestination) && ! $force) {
            $console->logWarning('  Config already exists, use --force to overwrite');
        } else {
            $this->ensureDirectoryExists($filesystem, dirname($configDestination));
            $content = $filesystem->get($configSource);
            $filesystem->put($configDestination, $content);
            $console->success('  ✓ Config published to config/nemesis.php');
        }

        // ========================================================================
        // 3. COPIER LA MIGRATION
        // ========================================================================
        $console->info('🗄️ Publishing migration...');

        $migrationSource = $packageRoot.'/database/migrations/2024_01_01_000001_create_nemesis_tokens_table.php';
        $migrationDestination = $app->databasePath('migrations/2024_01_01_000001_create_nemesis_tokens_table.php');

        if (! $filesystem->exists($migrationSource)) {
            $console->error("Migration source not found: {$migrationSource}");

            return ExitCode::FAILURE;
        }

        if ($filesystem->exists($migrationDestination) && ! $force) {
            $console->logWarning('  Migration already exists, use --force to overwrite');
        } else {
            $this->ensureDirectoryExists($filesystem, dirname($migrationDestination));
            $content = $filesystem->get($migrationSource);
            $filesystem->put($migrationDestination, $content);
            $console->success('  ✓ Migration published');
        }

        // ========================================================================
        // 4. LANCER LES MIGRATIONS VIA exec()
        // ========================================================================
        $console->info('🗄️ Running migrations...');

        $phpBinary = PHP_BINARY;
        $artisanPath = $app->basePath('artisan');

        $command = $phpBinary.' '.$artisanPath.' migrate --force 2>&1';

        $output = [];
        $exitCode = 0;
        exec($command, $output, $exitCode);

        if ($exitCode !== 0) {
            $console->error('Failed to run migrations.');
            $console->error('Output: '.implode("\n", $output));

            return ExitCode::FAILURE;
        }

        $console->success('  ✓ Migrations executed');

        // ========================================================================
        // 5. VÉRIFIER QUE LA TABLE A ÉTÉ CRÉÉE
        // ========================================================================
        $console->info('✅ Verifying database table...');

        $schemaBuilder = $this->getSchemaBuilder();

        if (! $schemaBuilder->hasTable('nemesis_tokens')) {
            $console->error("Table 'nemesis_tokens' not found.");

            return ExitCode::FAILURE;
        }
        $console->success('  ✓ Table "nemesis_tokens" exists');

        // ========================================================================
        // 6. VÉRIFIER LA CONFIGURATION
        // ========================================================================
        $console->info('⚙️ Configuration loaded:');
        $console->keyValue([
            'token_length' => $config->tokenConfig()->token_length,
            'hash_algorithm' => $config->tokenConfig()->hash_algorithm,
            'expiration' => $config->tokenConfig()->expiration_minutes ?? 'never',
            'validate_origin' => $config->middlewareConfig()->validate_origin ? 'true' : 'false',
        ]);

        // ========================================================================
        // 7. TIMELINE DES ÉTAPES
        // ========================================================================
        $console->separator('-', 60);
        $console->info('📋 Installation summary:');
        $console->newLine();

        $timelineEvents = ListCollection::from([
            ListCollection::from(['Package verified', 'andydefer/laravel-nemesis found']),
            ListCollection::from(['Configuration published', 'config/nemesis.php']),
            ListCollection::from(['Migration published', '2024_01_01_000001_create_nemesis_tokens_table.php']),
            ListCollection::from(['Migrations executed', 'Table nemesis_tokens created']),
            ListCollection::from(['Configuration validated', 'Token length: '.$config->tokenConfig()->token_length]),
        ]);

        $timelineStatuses = ['success', 'success', 'success', 'success', 'success'];

        $console->line(Timeline::renderWithStatus($timelineEvents, $timelineStatuses));

        // ========================================================================
        // 8. INSTALLATION FINALE
        // ========================================================================
        $console->separator('=', 60);
        $console->success('✨ Nemesis package installed successfully!');
        $console->newLine();

        $console->info('📝 Next steps:');

        $steps = [
            'Implement MustNemesis on your models: class User extends Model implements '.MustNemesis::class,
            'Define nemesisFormat(): public function nemesisFormat(): AbstractData { return new UserData(...); }',
            'Create tokens: [$token, $plainToken] = $nemesisService->createWithPlainToken($record, $user);',
            'Protect routes: Route::middleware(["nemesis.token"])->group(...);',
            'Use NemesisHelper: NemesisHelper::getCurrentAuthenticatable()',
        ];

        foreach ($steps as $index => $step) {
            $console->line('  '.($index + 1).'. '.$step);
        }

        $console->newLine();
        $console->badgeSuccess('READY');

        return ExitCode::SUCCESS;
    }

    private function ensureDirectoryExists(FileSystemInterface $filesystem, string $path): void
    {
        if (! $filesystem->isDirectory($path)) {
            $filesystem->makeDirectory($path, PermissionMode::DIRECTORY, true);
        }
    }
}
