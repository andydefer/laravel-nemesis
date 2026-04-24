<?php

declare(strict_types=1);

namespace Kani\Nemesis\Tests\Unit\Commands;

use Illuminate\Support\Facades\Artisan;
use Kani\Nemesis\Commands\InstallNemesisCommand;
use Kani\Nemesis\Services\NemesisInstallerService;
use Kani\Nemesis\Tests\TestCase;
use Mockery;

/**
 * Test suite for InstallNemesisCommand.
 *
 * Verifies that the installation command correctly calls the
 * installer service with the appropriate parameters.
 */
final class InstallNemesisCommandTest extends TestCase
{
    /**
     * Test that the command can be instantiated with correct properties.
     */
    public function test_command_can_be_instantiated(): void
    {
        // Arrange: Create command instance
        $command = new InstallNemesisCommand();

        // Assert: Verify command name and description
        $this->assertSame('nemesis:install', $command->getName());
        $this->assertSame('Install the Nemesis package for multi-model token authentication', $command->getDescription());
    }

    /**
     * Test that the command has correct signature options.
     */
    public function test_command_has_correct_signature(): void
    {
        // Arrange: Create command instance
        $command = new InstallNemesisCommand();

        // Assert: Verify signature contains expected options
        $signature = $command->getSynopsis();
        $this->assertStringContainsString('--force', $signature);
    }

    /**
     * Test that the command handles installation with force option.
     */
    public function test_handle_calls_installer_service_with_force_option(): void
    {
        // Arrange: Mock service expecting force flag = true
        $mockService = Mockery::mock(NemesisInstallerService::class);
        $mockService->shouldReceive('install')
            ->once()
            ->withArgs(function ($command, $force): bool {
                return $command instanceof InstallNemesisCommand && $force === true;
            });

        // Act: Register mock and execute command with force option
        $this->app->instance(NemesisInstallerService::class, $mockService);

        // Assert: Command should execute successfully
        $this->artisan('nemesis:install', ['--force' => true])
            ->assertExitCode(0);
    }

    /**
     * Test that the command handles installation without force option.
     */
    public function test_handle_calls_installer_service_without_force_option(): void
    {
        // Arrange: Mock service expecting force flag = false
        $mockService = Mockery::mock(NemesisInstallerService::class);
        $mockService->shouldReceive('install')
            ->once()
            ->withArgs(function ($command, $force): bool {
                return $command instanceof InstallNemesisCommand && $force === false;
            });

        // Act: Register mock and execute command without force option
        $this->app->instance(NemesisInstallerService::class, $mockService);

        // Assert: Command should execute successfully
        $this->artisan('nemesis:install')
            ->assertExitCode(0);
    }

    /**
     * Test that the command passes itself to the installer service.
     */
    public function test_command_passes_itself_to_installer_service(): void
    {
        // Arrange: Mock service expecting the command instance
        $mockService = Mockery::mock(NemesisInstallerService::class);
        $mockService->shouldReceive('install')
            ->once()
            ->withArgs(function ($command, $force): bool {
                return $command instanceof InstallNemesisCommand;
            });

        // Act: Register mock and execute command
        $this->app->instance(NemesisInstallerService::class, $mockService);

        // Assert: Command should execute successfully
        $this->artisan('nemesis:install')
            ->assertExitCode(0);
    }

    /**
     * Test that the command returns success exit code.
     */
    public function test_command_returns_success_exit_code(): void
    {
        // Arrange: Mock service
        $mockService = Mockery::mock(NemesisInstallerService::class);
        $mockService->shouldReceive('install')->once();

        // Act: Register mock and execute command
        $this->app->instance(NemesisInstallerService::class, $mockService);
        $exitCode = Artisan::call('nemesis:install');

        // Assert: Exit code is 0 (success)
        $this->assertEquals(0, $exitCode);
    }

    /**
     * Test that the command handles force option correctly when not provided.
     */
    public function test_handle_calls_installer_service_with_force_option_false_when_not_provided(): void
    {
        // Arrange: Mock service expecting force flag = false
        $mockService = Mockery::mock(NemesisInstallerService::class);
        $mockService->shouldReceive('install')
            ->once()
            ->withArgs(function ($command, $force): bool {
                return $command instanceof InstallNemesisCommand && $force === false;
            });

        // Act: Register mock and execute command without force option
        $this->app->instance(NemesisInstallerService::class, $mockService);

        // Assert: Command should execute successfully
        $this->artisan('nemesis:install')
            ->assertExitCode(0);
    }
}
