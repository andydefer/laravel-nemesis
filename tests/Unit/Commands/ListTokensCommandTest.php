<?php

declare(strict_types=1);

namespace Kani\Nemesis\Tests\Unit\Commands;

use Carbon\Carbon;
use Illuminate\Support\Facades\Artisan;
use Kani\Nemesis\Commands\ListTokensCommand;
use Kani\Nemesis\Models\NemesisToken;
use Kani\Nemesis\Tests\Support\TestApiClient;
use Kani\Nemesis\Tests\Support\TestUser;
use Kani\Nemesis\Tests\TestCase;

/**
 * Test suite for ListTokensCommand.
 *
 * Verifies that the command correctly lists tokens with filtering capabilities.
 */
final class ListTokensCommandTest extends TestCase
{
    private TestUser $user;
    private TestApiClient $apiClient;

    protected function setUp(): void
    {
        parent::setUp();

        // Set fixed time for consistent testing
        Carbon::setTestNow(Carbon::create(2025, 1, 1, 12, 0, 0));

        // Create test models
        $this->user = TestUser::create([
            'name' => 'Test User',
            'email' => 'test@example.com'
        ]);

        $this->apiClient = TestApiClient::create([
            'name' => 'API Client',
            'api_key' => 'test-api-key-123'
        ]);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    // ============================================================================
    // Tests for command configuration
    // ============================================================================

    /**
     * Test that the command can be instantiated with correct properties.
     */
    public function test_command_can_be_instantiated(): void
    {
        // Arrange: Create command instance
        $command = new ListTokensCommand();

        // Assert: Verify command name and description
        $this->assertSame('nemesis:list', $command->getName());
        $this->assertSame('List all tokens in the system', $command->getDescription());
    }

    /**
     * Test that the command has correct signature options.
     */
    public function test_command_has_correct_signature(): void
    {
        // Arrange: Create command instance
        $command = new ListTokensCommand();

        // Assert: Verify signature contains expected options
        $signature = $command->getSynopsis();
        $this->assertStringContainsString('--model', $signature);
    }

    // ============================================================================
    // Tests for listing tokens
    // ============================================================================

    /**
     * Test that the command shows warning when no tokens exist.
     */
    public function test_command_shows_warning_when_no_tokens(): void
    {
        // Act: Execute command
        $exitCode = Artisan::call('nemesis:list');

        // Assert: Command succeeded and output contains warning
        $this->assertEquals(0, $exitCode);
        $output = Artisan::output();
        $this->assertStringContainsString('No tokens found', $output);
    }

    /**
     * Test that the command lists all tokens.
     */
    public function test_command_lists_all_tokens(): void
    {
        // Arrange: Create tokens
        $this->user->createNemesisToken('User Token 1', 'web');
        $this->user->createNemesisToken('User Token 2', 'mobile');
        $this->apiClient->createNemesisToken('API Token', 'api');

        // Act: Execute command
        $exitCode = Artisan::call('nemesis:list');

        // Assert: Command succeeded
        $this->assertEquals(0, $exitCode);

        // Assert: Output contains token information
        $output = Artisan::output();
        $this->assertStringContainsString('User Token 1', $output);
        $this->assertStringContainsString('User Token 2', $output);
        $this->assertStringContainsString('API Token', $output);
    }

    /**
     * Test that the command displays correct table headers.
     */
    public function test_command_displays_correct_table_headers(): void
    {
        // Arrange: Create a token
        $this->user->createNemesisToken('Test Token', 'web');

        // Act: Execute command
        $exitCode = Artisan::call('nemesis:list');

        // Assert: Command succeeded
        $this->assertEquals(0, $exitCode);

        // Assert: Output contains expected headers
        $output = Artisan::output();
        $this->assertStringContainsString('ID', $output);
        $this->assertStringContainsString('Tokenable Type', $output);
        $this->assertStringContainsString('Tokenable ID', $output);
        $this->assertStringContainsString('Name', $output);
        $this->assertStringContainsString('Source', $output);
        $this->assertStringContainsString('Last Used', $output);
        $this->assertStringContainsString('Expires At', $output);
    }

    /**
     * Test that the command displays tokenable type as class basename.
     */
    public function test_command_displays_tokenable_type_as_basename(): void
    {
        // Arrange: Create a token
        $this->user->createNemesisToken('Test Token', 'web');

        // Act: Execute command
        $exitCode = Artisan::call('nemesis:list');

        // Assert: Command succeeded
        $this->assertEquals(0, $exitCode);

        // Assert: Output contains basename (TestUser) not full class path
        $output = Artisan::output();
        $this->assertStringContainsString('TestUser', $output);
        $this->assertStringNotContainsString('Kani\\Nemesis\\Tests\\Support\\TestUser', $output);
    }

    // ============================================================================
    // Tests for --model filter
    // ============================================================================

    /**
     * Test that the command filters tokens by model type.
     */
    public function test_command_filters_tokens_by_model_type(): void
    {
        // Arrange: Create tokens for different models
        $this->user->createNemesisToken('User Token', 'web');
        $this->apiClient->createNemesisToken('API Token', 'api');

        // Act: Execute command with model filter
        $exitCode = Artisan::call('nemesis:list', ['--model' => TestUser::class]);

        // Assert: Command succeeded
        $this->assertEquals(0, $exitCode);

        // Assert: Only user token is displayed
        $output = Artisan::output();
        $this->assertStringContainsString('User Token', $output);
        $this->assertStringNotContainsString('API Token', $output);
    }

    /**
     * Test that the command with model filter shows no results for non-existent model.
     */
    public function test_command_with_model_filter_shows_no_results_for_non_existent_model(): void
    {
        // Arrange: Create a token
        $this->user->createNemesisToken('User Token', 'web');

        // Act: Execute command with non-existent model filter
        $exitCode = Artisan::call('nemesis:list', ['--model' => 'NonExistentModel']);

        // Assert: Command succeeded
        $this->assertEquals(0, $exitCode);

        // Assert: Output shows warning
        $output = Artisan::output();
        $this->assertStringContainsString('No tokens found', $output);
    }

    /**
     * Test that the command with model filter works with different model classes.
     */
    public function test_command_with_model_filter_works_with_different_models(): void
    {
        // Arrange: Create tokens for different models
        $this->user->createNemesisToken('User Token', 'web');
        $this->apiClient->createNemesisToken('API Token', 'api');

        // Act: Execute command with API client filter
        $exitCode = Artisan::call('nemesis:list', ['--model' => TestApiClient::class]);

        // Assert: Command succeeded
        $this->assertEquals(0, $exitCode);

        // Assert: Only API token is displayed
        $output = Artisan::output();
        $this->assertStringContainsString('API Token', $output);
        $this->assertStringNotContainsString('User Token', $output);
    }

    // ============================================================================
    // Tests for display formatting
    // ============================================================================

    /**
     * Test that the command displays 'N/A' for null name.
     */
    public function test_command_displays_na_for_null_name(): void
    {
        // Arrange: Create a token without name
        $plainToken = $this->user->createNemesisToken(null, 'web');
        $tokenModel = $this->user->getNemesisToken($plainToken);
        $tokenModel->name = null;
        $tokenModel->save();

        // Act: Execute command
        $exitCode = Artisan::call('nemesis:list');

        // Assert: Command succeeded
        $this->assertEquals(0, $exitCode);

        // Assert: Output contains 'N/A' for name
        $output = Artisan::output();
        $this->assertStringContainsString('N/A', $output);
    }

    /**
     * Test that the command displays 'N/A' for null source.
     */
    public function test_command_displays_na_for_null_source(): void
    {
        // Arrange: Create a token without source
        $plainToken = $this->user->createNemesisToken('Test Token', null);
        $tokenModel = $this->user->getNemesisToken($plainToken);
        $tokenModel->source = null;
        $tokenModel->save();

        // Act: Execute command
        $exitCode = Artisan::call('nemesis:list');

        // Assert: Command succeeded
        $this->assertEquals(0, $exitCode);

        // Assert: Output contains 'N/A' for source
        $output = Artisan::output();
        $this->assertStringContainsString('N/A', $output);
    }

    /**
     * Test that the command displays 'Never' for never used tokens.
     */
    public function test_command_displays_never_for_never_used_tokens(): void
    {
        // Arrange: Create a token without last_used_at
        $plainToken = $this->user->createNemesisToken('Test Token', 'web');
        $tokenModel = $this->user->getNemesisToken($plainToken);
        $tokenModel->last_used_at = null;
        $tokenModel->save();

        // Act: Execute command
        $exitCode = Artisan::call('nemesis:list');

        // Assert: Command succeeded
        $this->assertEquals(0, $exitCode);

        // Assert: Output contains 'Never' for last used
        $output = Artisan::output();
        $this->assertStringContainsString('Never', $output);
    }

    /**
     * Test that the command displays 'Never' for never expiring tokens.
     */
    public function test_command_displays_never_for_never_expiring_tokens(): void
    {
        // Arrange: Create a token without expiration
        $plainToken = $this->user->createNemesisToken('Test Token', 'web');
        $tokenModel = $this->user->getNemesisToken($plainToken);
        $tokenModel->expires_at = null;
        $tokenModel->save();

        // Act: Execute command
        $exitCode = Artisan::call('nemesis:list');

        // Assert: Command succeeded
        $this->assertEquals(0, $exitCode);

        // Assert: Output contains 'Never' for expires at
        $output = Artisan::output();
        $this->assertStringContainsString('Never', $output);
    }

    /**
     * Test that the command displays human-readable time differences.
     */
    public function test_command_displays_human_readable_time_differences(): void
    {
        // Arrange: Create a token with last_used_at in the past
        $plainToken = $this->user->createNemesisToken('Test Token', 'web');
        $tokenModel = $this->user->getNemesisToken($plainToken);
        $tokenModel->last_used_at = now()->subDays(5);
        $tokenModel->expires_at = now()->addDays(7); // Changed to 7 days (1 week)
        $tokenModel->save();

        // Act: Execute command
        $exitCode = Artisan::call('nemesis:list');

        // Assert: Command succeeded
        $this->assertEquals(0, $exitCode);

        // Assert: Output contains human-readable time
        $output = Artisan::output();
        $this->assertStringContainsString('5 days ago', $output);
        $this->assertStringContainsString('1 week from now', $output);
    }

    // ============================================================================
    // Tests for token ordering
    // ============================================================================

    /**
     * Test that the command lists tokens in latest order.
     */
    public function test_command_lists_tokens_in_latest_order(): void
    {
        // Arrange: Create tokens with different creation times
        Carbon::setTestNow(Carbon::create(2025, 1, 1, 12, 0, 0));
        $firstToken = $this->user->createNemesisToken('First Token', 'web');
        $firstTokenModel = $this->user->getNemesisToken($firstToken);
        $firstTokenModel->created_at = now()->subDays(10);
        $firstTokenModel->save();

        Carbon::setTestNow(Carbon::create(2025, 1, 2, 12, 0, 0));
        $secondToken = $this->user->createNemesisToken('Second Token', 'web');
        $secondTokenModel = $this->user->getNemesisToken($secondToken);
        $secondTokenModel->created_at = now()->subDays(5);
        $secondTokenModel->save();

        Carbon::setTestNow(Carbon::create(2025, 1, 3, 12, 0, 0));
        $thirdToken = $this->user->createNemesisToken('Third Token', 'web');
        $thirdTokenModel = $this->user->getNemesisToken($thirdToken);
        $thirdTokenModel->created_at = now();
        $thirdTokenModel->save();

        // Reset time
        Carbon::setTestNow(Carbon::create(2025, 1, 3, 12, 0, 0));

        // Act: Execute command
        $exitCode = Artisan::call('nemesis:list');

        // Assert: Command succeeded
        $this->assertEquals(0, $exitCode);

        // Assert: Tokens are ordered by latest first (Third, Second, First)
        $output = Artisan::output();
        $outputLines = explode("\n", $output);

        // Find the table rows
        $thirdTokenPos = strpos($output, 'Third Token');
        $secondTokenPos = strpos($output, 'Second Token');
        $firstTokenPos = strpos($output, 'First Token');

        $this->assertLessThan($secondTokenPos, $thirdTokenPos, 'Third token should appear before Second token');
        $this->assertLessThan($firstTokenPos, $secondTokenPos, 'Second token should appear before First token');
    }

    // ============================================================================
    // Tests for edge cases
    // ============================================================================

    /**
     * Test that the command handles tokens with special characters in name.
     */
    public function test_command_handles_tokens_with_special_characters(): void
    {
        // Arrange: Create a token with special characters in name
        $specialName = 'Token!@#$%^&*()_+{}|:"<>?';
        $this->user->createNemesisToken($specialName, 'web');

        // Act: Execute command
        $exitCode = Artisan::call('nemesis:list');

        // Assert: Command succeeded
        $this->assertEquals(0, $exitCode);

        // Assert: Output contains special characters
        $output = Artisan::output();
        $this->assertStringContainsString($specialName, $output);
    }

    /**
     * Test that the command handles many tokens efficiently.
     */
    public function test_command_handles_many_tokens(): void
    {
        // Arrange: Create many tokens
        $tokenCount = 50;
        for ($i = 0; $i < $tokenCount; $i++) {
            $this->user->createNemesisToken("Token {$i}", 'web');
        }

        // Act: Execute command
        $exitCode = Artisan::call('nemesis:list');

        // Assert: Command succeeded
        $this->assertEquals(0, $exitCode);

        // Assert: Output contains all tokens
        $output = Artisan::output();
        for ($i = 0; $i < $tokenCount; $i++) {
            $this->assertStringContainsString("Token {$i}", $output);
        }
    }

    /**
     * Test that the command returns success exit code even with no tokens.
     */
    public function test_command_returns_success_when_no_tokens(): void
    {
        // Act: Execute command
        $exitCode = Artisan::call('nemesis:list');

        // Assert: Exit code is 0 (success)
        $this->assertEquals(0, $exitCode);
    }

    /**
     * Test that the command returns success exit code when tokens exist.
     */
    public function test_command_returns_success_when_tokens_exist(): void
    {
        // Arrange: Create a token
        $this->user->createNemesisToken('Test Token', 'web');

        // Act: Execute command
        $exitCode = Artisan::call('nemesis:list');

        // Assert: Exit code is 0 (success)
        $this->assertEquals(0, $exitCode);
    }
}
