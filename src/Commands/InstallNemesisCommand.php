<?php

declare(strict_types=1);

namespace Kani\Nemesis\Commands;

use Illuminate\Console\Command;
use Kani\Nemesis\Services\NemesisInstallerService;

/**
 * Command to install the Nemesis package.
 *
 * This command publishes configuration files, database migrations,
 * and performs initial setup for the token authentication system.
 *
 * @package Kani\Nemesis\Commands
 */
final class InstallNemesisCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'nemesis:install {--force : Force publish without confirmation}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Install the Nemesis package for multi-model token authentication';

    /**
     * Execute the console command.
     *
     * @param NemesisInstallerService $installer The installer service
     * @return int Exit code (0 for success)
     */
    public function handle(NemesisInstallerService $installer): int
    {
        $force = $this->shouldForceInstallation();

        $installer->install(
            command: $this,
            force: $force
        );

        return self::SUCCESS;
    }

    /**
     * Determine if installation should be forced.
     *
     * @return bool True if force option is present, false otherwise
     */
    private function shouldForceInstallation(): bool
    {
        return (bool) $this->option('force');
    }
}
