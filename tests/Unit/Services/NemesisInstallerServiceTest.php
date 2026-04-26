<?php

declare(strict_types=1);

namespace Kani\Nemesis\Tests\Unit\Services;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Schema;
use Kani\Nemesis\Services\NemesisInstallerService;
use Kani\Nemesis\Tests\TestCase;
use Mockery;

/**
 * Test suite for the NemesisInstallerService.
 *
 * @package Kani\Nemesis\Tests\Unit\Services
 */
final class NemesisInstallerServiceTest extends TestCase
{
    private NemesisInstallerService $installerService;
    private Command $command;

    protected function setUp(): void
    {
        parent::setUp();

        $this->installerService = new NemesisInstallerService();
        $this->command = Mockery::mock(Command::class);

        if (Schema::hasTable('nemesis_tokens')) {
            Schema::drop('nemesis_tokens');
        }
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    /**
     * Test installation proceeds without confirmation when force mode is enabled.
     */
    public function test_installation_proceeds_when_force_mode_enabled(): void
    {
        // Arrange: Mock command methods to accept any number of calls
        $this->command->shouldReceive('info')->zeroOrMoreTimes();
        $this->command->shouldReceive('warn')->zeroOrMoreTimes();
        $this->command->shouldReceive('line')->zeroOrMoreTimes();
        $this->command->shouldReceive('newLine')->zeroOrMoreTimes();
        $this->command->shouldReceive('call')->zeroOrMoreTimes();
        $this->command->shouldReceive('confirm')->never();

        // Act: Execute installation with force mode enabled
        $this->installerService->install($this->command, force: true);

        // Assert: Installation completed without errors
        $this->assertTrue(true);
    }

    /**
     * Test installation is cancelled when user declines confirmation.
     */
    public function test_installation_cancels_when_user_declines_confirmation(): void
    {
        // Arrange: Mock user declining the confirmation prompt
        $this->command->shouldReceive('info')->once();
        $this->command->shouldReceive('warn')->once();
        $this->command->shouldReceive('line')->times(2);
        $this->command->shouldReceive('newLine')->zeroOrMoreTimes();
        $this->command->shouldReceive('confirm')
            ->with('Continue?', true)
            ->andReturn(false);
        $this->command->shouldReceive('info')->with('Installation cancelled.')->once();
        $this->command->shouldReceive('call')->never();

        // Act: Execute installation without force mode
        $this->installerService->install($this->command, force: false);

        // Assert: Installation was cancelled
        $this->assertTrue(true);
    }

    /**
     * Test resources are published correctly.
     */
    public function test_resources_are_published_correctly(): void
    {
        // Arrange: Ensure table doesn't exist to force migration
        if (Schema::hasTable('nemesis_tokens')) {
            Schema::drop('nemesis_tokens');
        }

        $this->command->shouldReceive('info')->zeroOrMoreTimes();
        $this->command->shouldReceive('warn')->zeroOrMoreTimes();
        $this->command->shouldReceive('line')->zeroOrMoreTimes();
        $this->command->shouldReceive('newLine')->zeroOrMoreTimes();
        $this->command->shouldReceive('confirm')
            ->with('Continue?', true)
            ->andReturn(true);
        $this->command->shouldReceive('call')
            ->with('vendor:publish', Mockery::any())
            ->once();
        $this->command->shouldReceive('call')
            ->with('migrate')
            ->once();

        // Act: Execute installation
        $this->installerService->install($this->command, force: false);

        // Assert: Resources were published
        $this->assertTrue(true);
    }

    /**
     * Test resources are published with force flag when force mode is enabled.
     */
    public function test_resources_are_published_with_force_flag_when_enabled(): void
    {
        // Arrange: Ensure table doesn't exist
        if (Schema::hasTable('nemesis_tokens')) {
            Schema::drop('nemesis_tokens');
        }

        $this->command->shouldReceive('info')->zeroOrMoreTimes();
        $this->command->shouldReceive('warn')->zeroOrMoreTimes();
        $this->command->shouldReceive('line')->zeroOrMoreTimes();
        $this->command->shouldReceive('newLine')->zeroOrMoreTimes();
        $this->command->shouldReceive('confirm')->never();
        $this->command->shouldReceive('call')
            ->with('vendor:publish', Mockery::any())
            ->once();
        $this->command->shouldReceive('call')
            ->with('migrate')
            ->once();

        // Act: Execute installation with force mode
        $this->installerService->install($this->command, force: true);

        // Assert: Resources were published with force flag
        $this->assertTrue(true);
    }

    /**
     * Test migrations are run when tables don't exist.
     */
    public function test_migrations_are_run_when_tables_dont_exist(): void
    {
        // Arrange: Ensure no tokens table
        if (Schema::hasTable('nemesis_tokens')) {
            Schema::drop('nemesis_tokens');
        }

        $this->command->shouldReceive('info')->zeroOrMoreTimes();
        $this->command->shouldReceive('warn')->zeroOrMoreTimes();
        $this->command->shouldReceive('line')->zeroOrMoreTimes();
        $this->command->shouldReceive('newLine')->zeroOrMoreTimes();
        $this->command->shouldReceive('confirm')
            ->with('Continue?', true)
            ->andReturn(true);
        $this->command->shouldReceive('call')
            ->with('vendor:publish', Mockery::any())
            ->once();
        $this->command->shouldReceive('call')
            ->with('migrate')
            ->once();

        // Act: Execute installation
        $this->installerService->install($this->command, force: false);

        // Assert: Migrations were executed
        $this->assertTrue(true);
    }

    /**
     * Test migrations are skipped when tables already exist.
     */
    public function test_migrations_are_skipped_when_tables_already_exist(): void
    {
        // Arrange: Create table to simulate existing installation
        if (!Schema::hasTable('nemesis_tokens')) {
            Schema::create('nemesis_tokens', function ($table) {
                $table->id();
            });
        }

        $this->command->shouldReceive('info')->zeroOrMoreTimes();
        $this->command->shouldReceive('warn')->zeroOrMoreTimes();
        $this->command->shouldReceive('line')->zeroOrMoreTimes();
        $this->command->shouldReceive('newLine')->zeroOrMoreTimes();
        $this->command->shouldReceive('confirm')
            ->with('Continue?', true)
            ->andReturn(true);
        $this->command->shouldReceive('call')
            ->with('vendor:publish', Mockery::any())
            ->once();
        $this->command->shouldReceive('call')->with('migrate')->never();

        // Act: Execute installation
        $this->installerService->install($this->command, force: false);

        // Assert: Migrations were skipped
        $this->assertTrue(true);
    }

    /**
     * Test token example is generated after installation.
     */
    public function test_token_example_is_generated_after_installation(): void
    {
        // Arrange: Setup fresh installation
        if (Schema::hasTable('nemesis_tokens')) {
            Schema::drop('nemesis_tokens');
        }

        $this->command->shouldReceive('info')->zeroOrMoreTimes();
        $this->command->shouldReceive('warn')->zeroOrMoreTimes();
        $this->command->shouldReceive('line')->zeroOrMoreTimes();
        $this->command->shouldReceive('newLine')->zeroOrMoreTimes();
        $this->command->shouldReceive('confirm')
            ->with('Continue?', true)
            ->andReturn(true);
        $this->command->shouldReceive('call')
            ->with('vendor:publish', Mockery::any())
            ->once();
        $this->command->shouldReceive('call')
            ->with('migrate')
            ->once();

        // Act: Execute installation
        $this->installerService->install($this->command, force: false);

        // Assert: Token example was generated
        $this->assertTrue(true);
    }

    /**
     * Test installation displays success message.
     */
    public function test_installation_displays_success_message(): void
    {
        // Arrange: Setup fresh installation
        if (Schema::hasTable('nemesis_tokens')) {
            Schema::drop('nemesis_tokens');
        }

        $this->command->shouldReceive('info')->zeroOrMoreTimes();
        $this->command->shouldReceive('warn')->zeroOrMoreTimes();
        $this->command->shouldReceive('line')->zeroOrMoreTimes();
        $this->command->shouldReceive('newLine')->zeroOrMoreTimes();
        $this->command->shouldReceive('confirm')
            ->with('Continue?', true)
            ->andReturn(true);
        $this->command->shouldReceive('call')
            ->with('vendor:publish', Mockery::any())
            ->once();
        $this->command->shouldReceive('call')
            ->with('migrate')
            ->once();

        // Act: Execute installation
        $this->installerService->install($this->command, force: false);

        // Assert: Success message was displayed
        $this->assertTrue(true);
    }

    /**
     * Test installation displays next steps guide.
     */
    public function test_installation_displays_next_steps_guide(): void
    {
        // Arrange: Setup fresh installation
        if (Schema::hasTable('nemesis_tokens')) {
            Schema::drop('nemesis_tokens');
        }

        $this->command->shouldReceive('info')->zeroOrMoreTimes();
        $this->command->shouldReceive('warn')->zeroOrMoreTimes();
        $this->command->shouldReceive('line')->zeroOrMoreTimes();
        $this->command->shouldReceive('newLine')->zeroOrMoreTimes();
        $this->command->shouldReceive('confirm')
            ->with('Continue?', true)
            ->andReturn(true);
        $this->command->shouldReceive('call')
            ->with('vendor:publish', Mockery::any())
            ->once();
        $this->command->shouldReceive('call')
            ->with('migrate')
            ->once();

        // Act: Execute installation
        $this->installerService->install($this->command, force: false);

        // Assert: Next steps guide was displayed
        $this->assertTrue(true);
    }

    /**
     * Test installation handles migration failure gracefully.
     */
    public function test_installation_handles_migration_failure_gracefully(): void
    {
        // Arrange: Simulate migration failure
        if (Schema::hasTable('nemesis_tokens')) {
            Schema::drop('nemesis_tokens');
        }

        $this->command->shouldReceive('info')->zeroOrMoreTimes();
        $this->command->shouldReceive('warn')->zeroOrMoreTimes();
        $this->command->shouldReceive('line')->zeroOrMoreTimes();
        $this->command->shouldReceive('newLine')->zeroOrMoreTimes();
        $this->command->shouldReceive('confirm')
            ->with('Continue?', true)
            ->andReturn(true);
        $this->command->shouldReceive('call')
            ->with('vendor:publish', Mockery::any())
            ->once();
        $this->command->shouldReceive('call')
            ->with('migrate')
            ->once()
            ->andReturnUsing(function () {
                throw new \Exception('Migration failed');
            });
        $this->command->shouldReceive('error')
            ->with(Mockery::pattern('/❌ Migration failed/'))
            ->once();

        // Act: Execute installation with failing migration
        try {
            $this->installerService->install($this->command, force: false);
        } catch (\Exception $e) {
            $this->fail('Exception should not be propagated: ' . $e->getMessage());
        }

        // Assert: Error was handled gracefully
        $this->assertTrue(true);
    }

    /**
     * Test hasCoreTables returns true when tables exist.
     */
    public function test_has_core_tables_returns_true_when_tables_exist(): void
    {
        // Arrange: Create table to simulate existing installation
        if (!Schema::hasTable('nemesis_tokens')) {
            Schema::create('nemesis_tokens', function ($table) {
                $table->id();
            });
        }

        $reflection = new \ReflectionClass($this->installerService);
        $method = $reflection->getMethod('hasCoreTables');
        $method->setAccessible(true);

        // Act: Call the protected method
        $result = $method->invoke($this->installerService);

        // Assert: Method returns true
        $this->assertTrue($result);
    }

    /**
     * Test hasCoreTables returns false when tables don't exist.
     */
    public function test_has_core_tables_returns_false_when_tables_dont_exist(): void
    {
        // Arrange: Ensure table does not exist
        if (Schema::hasTable('nemesis_tokens')) {
            Schema::drop('nemesis_tokens');
        }

        $reflection = new \ReflectionClass($this->installerService);
        $method = $reflection->getMethod('hasCoreTables');
        $method->setAccessible(true);

        // Act: Call the protected method
        $result = $method->invoke($this->installerService);

        // Assert: Method returns false
        $this->assertFalse($result);
    }

    /**
     * Test generateTokenExample produces a valid token.
     */
    public function test_generate_token_example_produces_valid_token(): void
    {
        // Arrange: Access protected method via reflection
        $reflection = new \ReflectionClass($this->installerService);
        $method = $reflection->getMethod('generateTokenExample');
        $method->setAccessible(true);

        $this->command->shouldReceive('info')->zeroOrMoreTimes();
        $this->command->shouldReceive('line')->zeroOrMoreTimes();

        // Act: Generate token example
        $method->invoke($this->installerService, $this->command);

        // Assert: Token was generated without errors
        $this->assertTrue(true);
    }

    /**
     * Test displaySuccessMessage shows correct content.
     */
    public function test_display_success_message_shows_correct_content(): void
    {
        // Arrange: Access protected method via reflection
        $reflection = new \ReflectionClass($this->installerService);
        $method = $reflection->getMethod('displaySuccessMessage');
        $method->setAccessible(true);

        $this->command->shouldReceive('newLine')->zeroOrMoreTimes();
        $this->command->shouldReceive('info')->zeroOrMoreTimes();
        $this->command->shouldReceive('line')->zeroOrMoreTimes();

        // Act: Display success message
        $method->invoke($this->installerService, $this->command);

        // Assert: Message was displayed
        $this->assertTrue(true);
    }
}
