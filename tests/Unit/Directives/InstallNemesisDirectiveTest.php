<?php

// tests/Unit/Directives/InstallNemesisDirectiveTest.php

declare(strict_types=1);

namespace Kani\Nemesis\Tests\Unit\Directives;

use AndyDefer\Directive\Collections\ParameterVOCollection;
use AndyDefer\Directive\Contexts\DirectiveContext;
use AndyDefer\Directive\Contexts\LaravelBootstrapperContext;
use AndyDefer\Directive\Enums\ExitCode;
use AndyDefer\Directive\Records\DirectiveBlueprintRecord;
use AndyDefer\Directive\Services\DirectiveInteractionService;
use AndyDefer\Directive\Services\FileSystemService;
use AndyDefer\Directive\ValueObjects\ParameterVO;
use AndyDefer\DomainStructures\Collections\Utility\StringTypedCollection;
use AndyDefer\DomainStructures\Services\HydrationService;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Database\Connection;
use Illuminate\Database\DatabaseManager;
use Illuminate\Database\Schema\Builder;
use Kani\Nemesis\Contracts\Configs\NemesisConfigInterface;
use Kani\Nemesis\Directives\InstallNemesisDirective;
use Kani\Nemesis\Records\MiddlewareConfigRecord;
use Kani\Nemesis\Records\TokenConfigRecord;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\TestCase;

#[AllowMockObjectsWithoutExpectations]
final class InstallNemesisDirectiveTest extends TestCase
{
    private $kernel;
    private $app;
    private $filesystem;
    private $db;
    private $connection;
    private $schemaBuilder;
    private $config;
    private $interaction;

    protected function setUp(): void
    {
        parent::setUp();

        $this->kernel = $this->createMock(Kernel::class);
        $this->app = $this->createMock(Application::class);
        $this->filesystem = $this->createMock(FileSystemService::class);

        $this->db = $this->createMock(DatabaseManager::class);
        $this->connection = $this->createMock(Connection::class);
        $this->schemaBuilder = $this->createMock(Builder::class);

        $this->db->method('connection')->willReturn($this->connection);
        $this->connection->method('getSchemaBuilder')->willReturn($this->schemaBuilder);

        $this->config = $this->createMock(NemesisConfigInterface::class);
        $this->interaction = $this->createMock(DirectiveInteractionService::class);
    }

    private function createDirectiveWithOptions(array $options = [], array $fileExistsMap = []): InstallNemesisDirective
    {
        $hydration = new HydrationService();

        $tokenConfig = $hydration->hydrate(TokenConfigRecord::class, [
            'token_length' => 64,
            'hash_algorithm' => 'sha256',
            'expiration_minutes' => 60,
        ]);

        $middlewareConfig = $hydration->hydrate(MiddlewareConfigRecord::class, [
            'parameter_name' => 'nemesisAuth',
            'token_header' => 'Authorization',
            'security_headers' => true,
            'validate_origin' => true,
        ]);

        $this->config->method('tokenConfig')->willReturn($tokenConfig);
        $this->config->method('middlewareConfig')->willReturn($middlewareConfig);

        $this->filesystem->method('exists')->willReturnCallback(function ($path) use ($fileExistsMap) {
            foreach ($fileExistsMap as $pattern => $exists) {
                if (str_contains($path, $pattern)) {
                    return $exists;
                }
            }
            return false;
        });

        $this->app->method('basePath')->willReturn('/fake/project');
        $this->app->method('databasePath')->willReturn('/fake/project/database');

        $mockConfig = new \stdClass();
        $mockConfig->providers = [];
        $this->app->method('make')->willReturnCallback(function ($abstract) use ($mockConfig) {
            if ($abstract === 'config') {
                return $mockConfig;
            }
            return null;
        });

        $optionsCollection = new ParameterVOCollection();
        foreach ($options as $key => $value) {
            $reflection = new \ReflectionClass($optionsCollection);
            $itemsProperty = $reflection->getProperty('items');
            $items = $itemsProperty->getValue($optionsCollection);

            $paramVO = new ParameterVO(
                name: $key,
                value: $value,
                type: \AndyDefer\Directive\Enums\PrimitiveType::BOOL
            );
            $items[] = $paramVO;
            $itemsProperty->setValue($optionsCollection, $items);
        }

        $context = new DirectiveContext(
            laravelBootstrapper: new LaravelBootstrapperContext(),
            blueprint: new DirectiveBlueprintRecord(
                InstallNemesisDirective::class,
                'install-nemesis',
                'Install the Nemesis package'
            ),
            aliases: new StringTypedCollection(),
            shouldBootLaravel: true,
        );

        $context->setOptions($optionsCollection);

        return new InstallNemesisDirective(
            $context,
            $this->interaction,
            $this->kernel,
            $this->app,
            $this->filesystem,
            $this->db,
            $this->config,
        );
    }

    // ============================================================================
    // Signature Tests
    // ============================================================================

    public function test_get_signature_returns_install_nemesis(): void
    {
        $directive = new InstallNemesisDirective(
            new DirectiveContext(
                new LaravelBootstrapperContext(),
                new DirectiveBlueprintRecord(InstallNemesisDirective::class, '', ''),
                new StringTypedCollection(),
                true
            ),
            $this->interaction,
            $this->kernel,
            $this->app,
            $this->filesystem,
            $this->db,
            $this->config
        );

        $signature = $directive->getSignature();

        $this->assertStringContainsString('install-nemesis', $signature);
        $this->assertStringContainsString('--force', $signature);
    }

    public function test_get_description_returns_description(): void
    {
        $directive = new InstallNemesisDirective(
            new DirectiveContext(
                new LaravelBootstrapperContext(),
                new DirectiveBlueprintRecord(InstallNemesisDirective::class, '', ''),
                new StringTypedCollection(),
                true
            ),
            $this->interaction,
            $this->kernel,
            $this->app,
            $this->filesystem,
            $this->db,
            $this->config
        );

        $description = $directive->getDescription();

        $this->assertSame('Install the Nemesis package for multi-model token authentication', $description);
    }

    public function test_get_aliases_returns_aliases(): void
    {
        $directive = new InstallNemesisDirective(
            new DirectiveContext(
                new LaravelBootstrapperContext(),
                new DirectiveBlueprintRecord(InstallNemesisDirective::class, '', ''),
                new StringTypedCollection(),
                true
            ),
            $this->interaction,
            $this->kernel,
            $this->app,
            $this->filesystem,
            $this->db,
            $this->config
        );

        $aliases = $directive->getAliases();

        $this->assertTrue($aliases->contains('nemesis-install'));
        $this->assertTrue($aliases->contains('setup-nemesis'));
        $this->assertSame(2, $aliases->count());
    }

    public function test_should_boot_laravel_returns_true(): void
    {
        $directive = new InstallNemesisDirective(
            new DirectiveContext(
                new LaravelBootstrapperContext(),
                new DirectiveBlueprintRecord(InstallNemesisDirective::class, '', ''),
                new StringTypedCollection(),
                true
            ),
            $this->interaction,
            $this->kernel,
            $this->app,
            $this->filesystem,
            $this->db,
            $this->config
        );

        $this->assertTrue($directive->shouldBootLaravel());
    }

    // ============================================================================
    // Confirmation Tests
    // ============================================================================

    public function test_execute_cancels_when_user_declines_confirmation(): void
    {
        $this->interaction->expects($this->once())
            ->method('confirm')
            ->willReturn(false);

        $directive = $this->createDirectiveWithOptions(['force' => false]);

        $result = $directive->execute();

        $this->assertSame(ExitCode::SUCCESS, $result);
    }

    public function test_execute_proceeds_when_user_confirms(): void
    {
        $this->interaction->expects($this->once())
            ->method('confirm')
            ->willReturn(true);

        $this->kernel->expects($this->once())
            ->method('call')
            ->with('migrate', ['--force' => true])
            ->willReturn(0);

        $this->schemaBuilder->method('hasTable')->willReturn(true);

        $directive = $this->createDirectiveWithOptions(
            ['force' => false],
            [
                'vendor/andydefer/laravel-nemesis' => true,
                '/config/nemesis.php' => true,
                '/database/migrations/' => true,
            ]
        );

        $result = $directive->execute();

        $this->assertSame(ExitCode::SUCCESS, $result);
    }

    // ============================================================================
    // Force Option Tests
    // ============================================================================

    public function test_execute_proceeds_with_force_option(): void
    {
        $this->interaction->expects($this->never())->method('confirm');

        $this->kernel->expects($this->once())
            ->method('call')
            ->with('migrate', ['--force' => true])
            ->willReturn(0);

        $this->schemaBuilder->method('hasTable')->willReturn(true);

        $directive = $this->createDirectiveWithOptions(
            ['force' => true],
            [
                'vendor/andydefer/laravel-nemesis' => true,
                '/config/nemesis.php' => true,
                '/database/migrations/' => true,
            ]
        );

        $result = $directive->execute();

        $this->assertSame(ExitCode::SUCCESS, $result);
    }

    // ============================================================================
    // Package Not Found Tests
    // ============================================================================

    public function test_execute_returns_failure_when_package_not_found(): void
    {
        $this->interaction->expects($this->once())
            ->method('confirm')
            ->willReturn(true);

        $directive = $this->createDirectiveWithOptions(
            ['force' => false],
            ['vendor/andydefer/laravel-nemesis' => false]
        );

        $result = $directive->execute();

        $this->assertSame(ExitCode::FAILURE, $result);
    }

    // ============================================================================
    // Config File Tests
    // ============================================================================

    public function test_execute_returns_failure_when_config_source_missing(): void
    {
        $this->interaction->expects($this->once())
            ->method('confirm')
            ->willReturn(true);

        $directive = $this->createDirectiveWithOptions(
            ['force' => false],
            [
                'vendor/andydefer/laravel-nemesis' => true,
                '/config/nemesis.php' => false,
            ]
        );

        $result = $directive->execute();

        $this->assertSame(ExitCode::FAILURE, $result);
    }

    public function test_execute_warns_when_config_already_exists_without_force(): void
    {
        $this->interaction->expects($this->once())
            ->method('confirm')
            ->willReturn(true);

        $this->kernel->expects($this->once())
            ->method('call')
            ->with('migrate', ['--force' => true])
            ->willReturn(0);

        $this->schemaBuilder->method('hasTable')->willReturn(true);

        $directive = $this->createDirectiveWithOptions(
            ['force' => false],
            [
                'vendor/andydefer/laravel-nemesis' => true,
                '/config/nemesis.php' => true,
                '/database/migrations/' => true,
                'basePath/config/nemesis.php' => true,
            ]
        );

        $result = $directive->execute();

        $this->assertSame(ExitCode::SUCCESS, $result);
    }

    // ============================================================================
    // Migration File Tests
    // ============================================================================

    public function test_execute_returns_failure_when_migration_source_missing(): void
    {
        $this->interaction->expects($this->once())
            ->method('confirm')
            ->willReturn(true);

        $directive = $this->createDirectiveWithOptions(
            ['force' => false],
            [
                'vendor/andydefer/laravel-nemesis' => true,
                '/config/nemesis.php' => true,
                '/database/migrations/' => false,
            ]
        );

        $result = $directive->execute();

        $this->assertSame(ExitCode::FAILURE, $result);
    }

    public function test_execute_warns_when_migration_already_exists_without_force(): void
    {
        $this->interaction->expects($this->once())
            ->method('confirm')
            ->willReturn(true);

        $this->kernel->expects($this->once())
            ->method('call')
            ->with('migrate', ['--force' => true])
            ->willReturn(0);

        $this->schemaBuilder->method('hasTable')->willReturn(true);

        $directive = $this->createDirectiveWithOptions(
            ['force' => false],
            [
                'vendor/andydefer/laravel-nemesis' => true,
                '/config/nemesis.php' => true,
                '/database/migrations/' => true,
                'databasePath/migrations' => true,
            ]
        );

        $result = $directive->execute();

        $this->assertSame(ExitCode::SUCCESS, $result);
    }

    // ============================================================================
    // Migration Execution Tests
    // ============================================================================

    public function test_execute_returns_failure_when_migration_fails(): void
    {
        $this->interaction->expects($this->never())->method('confirm');

        $this->kernel->expects($this->once())
            ->method('call')
            ->with('migrate', ['--force' => true])
            ->willReturn(1);

        $directive = $this->createDirectiveWithOptions(
            ['force' => true],
            [
                'vendor/andydefer/laravel-nemesis' => true,
                '/config/nemesis.php' => true,
                '/database/migrations/' => true,
            ]
        );

        $result = $directive->execute();

        $this->assertSame(ExitCode::FAILURE, $result);
    }

    // ============================================================================
    // Database Table Verification Tests
    // ============================================================================

    public function test_execute_returns_failure_when_table_does_not_exist(): void
    {
        $this->interaction->expects($this->never())->method('confirm');

        $this->kernel->expects($this->once())
            ->method('call')
            ->with('migrate', ['--force' => true])
            ->willReturn(0);

        $this->schemaBuilder->method('hasTable')->willReturn(false);

        $directive = $this->createDirectiveWithOptions(
            ['force' => true],
            [
                'vendor/andydefer/laravel-nemesis' => true,
                '/config/nemesis.php' => true,
                '/database/migrations/' => true,
            ]
        );

        $result = $directive->execute();

        $this->assertSame(ExitCode::FAILURE, $result);
    }

    // ============================================================================
    // Overwrite Tests
    // ============================================================================

    public function test_execute_overwrites_config_with_force(): void
    {
        $this->interaction->expects($this->never())->method('confirm');

        $this->kernel->expects($this->once())
            ->method('call')
            ->with('migrate', ['--force' => true])
            ->willReturn(0);

        $this->schemaBuilder->method('hasTable')->willReturn(true);

        $directive = $this->createDirectiveWithOptions(
            ['force' => true],
            [
                'vendor/andydefer/laravel-nemesis' => true,
                '/config/nemesis.php' => true,
                '/database/migrations/' => true,
                'basePath/config/nemesis.php' => true,
                'databasePath/migrations' => true,
            ]
        );

        $result = $directive->execute();

        $this->assertSame(ExitCode::SUCCESS, $result);
    }

    // ============================================================================
    // Alias Tests
    // ============================================================================

    public function test_alias_nemesis_install_exists(): void
    {
        $directive = new InstallNemesisDirective(
            new DirectiveContext(
                new LaravelBootstrapperContext(),
                new DirectiveBlueprintRecord(InstallNemesisDirective::class, '', ''),
                new StringTypedCollection(),
                true
            ),
            $this->interaction,
            $this->kernel,
            $this->app,
            $this->filesystem,
            $this->db,
            $this->config
        );

        $aliases = $directive->getAliases();

        $this->assertTrue($aliases->contains('nemesis-install'));
    }

    public function test_alias_setup_nemesis_exists(): void
    {
        $directive = new InstallNemesisDirective(
            new DirectiveContext(
                new LaravelBootstrapperContext(),
                new DirectiveBlueprintRecord(InstallNemesisDirective::class, '', ''),
                new StringTypedCollection(),
                true
            ),
            $this->interaction,
            $this->kernel,
            $this->app,
            $this->filesystem,
            $this->db,
            $this->config
        );

        $aliases = $directive->getAliases();

        $this->assertTrue($aliases->contains('setup-nemesis'));
    }
}
