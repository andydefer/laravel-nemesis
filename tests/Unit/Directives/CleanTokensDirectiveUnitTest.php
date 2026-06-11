<?php

// tests/Unit/Directives/CleanTokensDirectiveUnitTest.php

declare(strict_types=1);

namespace Kani\Nemesis\Tests\Unit\Directives;

use AndyDefer\Directive\Contexts\DirectiveContext;
use AndyDefer\Directive\Contexts\LaravelBootstrapperContext;
use AndyDefer\Directive\Enums\ExitCode;
use AndyDefer\Directive\Records\DirectiveBlueprintRecord;
use AndyDefer\Directive\Services\DirectiveInteractionService;
use AndyDefer\DomainStructures\Collections\Utility\StringTypedCollection;
use Kani\Nemesis\Contracts\Configs\NemesisConfigInterface;
use Kani\Nemesis\Directives\CleanTokensDirective;
use Kani\Nemesis\Services\NemesisService;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\TestCase;

#[AllowMockObjectsWithoutExpectations]
final class CleanTokensDirectiveUnitTest extends TestCase
{
    private $interaction;
    private $config;
    private $service;

    protected function setUp(): void
    {
        parent::setUp();

        $this->interaction = $this->createMock(DirectiveInteractionService::class);
        $this->config = $this->createStub(NemesisConfigInterface::class);
        $this->service = $this->createMock(NemesisService::class);
    }

    private function createDirective(): CleanTokensDirective
    {
        $context = new DirectiveContext(
            laravelBootstrapper: new LaravelBootstrapperContext(),
            blueprint: new DirectiveBlueprintRecord(
                CleanTokensDirective::class,
                'clean-tokens',
                'Clean expired and old tokens'
            ),
            aliases: new StringTypedCollection(),
            shouldBootLaravel: true,
        );

        return new CleanTokensDirective(
            $context,
            $this->interaction,
            $this->config,
            $this->service,
        );
    }

    // ============================================================================
    // Confirmation Tests (uniquement ceux qui nécessitent de l'interaction)
    // ============================================================================

    public function test_without_force_asks_confirmation(): void
    {
        // Simuler que confirm() est appelé
        $this->interaction->expects($this->once())
            ->method('confirm')
            ->willReturn(false);

        $directive = $this->createDirective();

        // Simuler l'option 'force' = false
        $reflection = new \ReflectionClass($directive);
        $contextProperty = $reflection->getProperty('context');
        $context = $contextProperty->getValue($directive);

        // Forcer l'option 'force' à false via le contexte
        $optionsCollection = new \AndyDefer\Directive\Collections\ParameterVOCollection();
        $context->setOptions($optionsCollection);

        $result = $directive->execute();

        $this->assertSame(ExitCode::SUCCESS, $result);
    }
}
