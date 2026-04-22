<?php
// src/Commands/InstallNemesisCommand.php

declare(strict_types=1);

namespace Kani\Nemesis\Commands;

use Illuminate\Console\Command;
use Kani\Nemesis\Services\NemesisInstallerService;

final class InstallNemesisCommand extends Command
{
    protected $signature = 'nemesis:install {--force : Force publish without confirmation}';

    protected $description = 'Install the Nemesis package for multi-model token authentication';

    public function handle(NemesisInstallerService $nemesisInstallerService): int
    {
        $nemesisInstallerService->install($this, (bool) $this->option('force'));
        return self::SUCCESS;
    }
}
