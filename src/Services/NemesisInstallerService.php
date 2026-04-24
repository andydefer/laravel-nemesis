<?php

// src/Services/NemesisInstallerService.php

declare(strict_types=1);

namespace Kani\Nemesis\Services;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Schema;
use Kani\Nemesis\NemesisServiceProvider;

/**
 * Service for installing and setting up the Nemesis package.
 *
 * Handles the complete installation process including resource publishing,
 * database migrations, and post-installation steps.
 */
class NemesisInstallerService
{
    /** @var array<int, string> Core tables required by the Nemesis package */
    private const CORE_TABLES = [
        'nemesis_tokens',
    ];

    /**
     * Execute the complete Nemesis package installation process.
     *
     * @param  Command  $command  Console command instance for user interaction
     * @param  bool  $force  Skip confirmation prompts when true
     */
    public function install(Command $command, bool $force = false): void
    {
        $command->info('🔐 Installing Nemesis package...');

        if (! $this->shouldProceedWithInstallation($command, $force)) {
            return;
        }

        $this->publishResources($command, $force);
        $this->handleDatabaseMigrations($command);

        $this->displaySuccessMessage($command);
        $this->generateTokenExample($command);
    }

    /**
     * Check if installation should proceed based on user confirmation.
     *
     * @param  Command  $command  Console command instance
     * @param  bool  $force  Skip confirmation when true
     * @return bool True if installation should proceed
     */
    private function shouldProceedWithInstallation(Command $command, bool $force): bool
    {
        if ($force) {
            return true;
        }

        $command->warn('📦 This will publish:');
        $command->line('   - Configuration (config/nemesis.php)');
        $command->line('   - Database migrations (nemesis_tokens table)');

        if (! $command->confirm('Continue?', true)) {
            $command->info('Installation cancelled.');

            return false;
        }

        return true;
    }

    /**
     * Publish package resources to the application.
     *
     * @param  Command  $command  Console command instance
     * @param  bool  $force  Overwrite existing files when true
     */
    private function publishResources(Command $command, bool $force): void
    {
        $command->info('📤 Publishing resources...');

        $command->call('vendor:publish', [
            '--provider' => NemesisServiceProvider::class,
            '--tag' => ['nemesis-config', 'nemesis-migrations'],
            '--force' => $force,
        ]);
    }

    /**
     * Handle database migrations based on existing tables.
     *
     * @param  Command  $command  Console command instance
     */
    private function handleDatabaseMigrations(Command $command): void
    {
        if ($this->hasCoreTables()) {
            $command->warn('⚠️ Nemesis tables already exist. Skipping migrations.');

            return;
        }

        $command->info('📊 Running migrations...');
        $command->call('migrate');
    }

    /**
     * Check if any core Nemesis tables already exist in the database.
     *
     * @return bool True if any core table exists
     */
    private function hasCoreTables(): bool
    {
        foreach (self::CORE_TABLES as $table) {
            if (Schema::hasTable($table)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Generate token example for the user.
     *
     * @param  Command  $command  Console command instance
     */
    private function generateTokenExample(Command $command): void
    {
        $command->info('🔑 Generating token example...');

        // Create a simple example token for demonstration
        $exampleToken = bin2hex(random_bytes(32));

        $command->line('💡 Example token (for testing):');
        $command->line(sprintf('   <info>%s</info>', $exampleToken));
    }

    /**
     * Display installation success message with next steps.
     *
     * @param  Command  $command  Console command instance
     */
    private function displaySuccessMessage(Command $command): void
    {
        $command->newLine();
        $command->info('✅ Nemesis package installed successfully!');
        $command->line('📝 Next steps:');
        $command->line('   1. Add the HasNemesisTokens trait to your models:');
        $command->line('      <info>use Kani\\Nemesis\\Traits\\HasNemesisTokens;</info>');
        $command->line('      <info>use Kani\\Nemesis\\Contracts\\MustNemesis;</info>');
        $command->line('');
        $command->line('   2. Implement the MustNemesis interface on your model:');
        $command->line('      <info>class User extends Authenticatable implements MustNemesis</info>');
        $command->line('');
        $command->line('   3. Create tokens for your models:');
        $command->line('      <info>$token = $user->createNemesisToken("Mobile App", "phone");</info>');
        $command->line('');
        $command->line('   4. Protect your routes with middleware:');
        $command->line('      <info>Route::middleware(["nemesis.auth"])->group(...);</info>');
        $command->line('');
        $command->line('   5. Review config/nemesis.php for configuration options');
        $command->line('   6. Check abilities and token expiration settings');
    }
}
