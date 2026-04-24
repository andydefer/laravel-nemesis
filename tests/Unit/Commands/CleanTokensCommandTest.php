<?php

declare(strict_types=1);

namespace Kani\Nemesis\Tests\Unit\Commands;

use Carbon\Carbon;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Config;
use Kani\Nemesis\Commands\CleanTokensCommand;
use Kani\Nemesis\Models\NemesisToken;
use Kani\Nemesis\Tests\Support\TestUser;
use Kani\Nemesis\Tests\TestCase;

/**
 * Test suite for CleanTokensCommand.
 *
 * Verifies that the command correctly cleans expired and old tokens
 * based on configuration and command line options.
 *
 * @package Kani\Nemesis\Tests\Unit\Commands
 */
final class CleanTokensCommandTest extends TestCase
{
    private TestUser $user;

    protected function setUp(): void
    {
        parent::setUp();

        // Arrange: Set fixed time for consistent test results
        Carbon::setTestNow(Carbon::create(2025, 1, 1, 12, 0, 0));

        // Arrange: Create a test user that can own tokens
        $this->user = TestUser::create([
            'name' => 'Test User',
            'email' => 'test@example.com'
        ]);
    }

    protected function tearDown(): void
    {
        // Cleanup: Reset time and run parent tearDown
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
        $command = new CleanTokensCommand();

        // Assert: Verify command name matches expected value
        $this->assertSame('nemesis:clean', $command->getName());

        // Assert: Verify command description matches expected value
        $this->assertSame('Clean expired and old tokens based on configuration', $command->getDescription());
    }

    /**
     * Test that the command has correct signature options.
     */
    public function test_command_has_correct_signature(): void
    {
        // Arrange: Create command instance
        $command = new CleanTokensCommand();

        // Act: Get command signature synopsis
        $signature = $command->getSynopsis();

        // Assert: Signature should contain --days option
        $this->assertStringContainsString('--days', $signature);

        // Assert: Signature should contain --force option
        $this->assertStringContainsString('--force', $signature);

        // Assert: Signature should contain --keep-expired option
        $this->assertStringContainsString('--keep-expired', $signature);
    }

    // ============================================================================
    // Tests for token cleanup without options
    // ============================================================================

    /**
     * Test that the command cleans expired tokens by default.
     */
    public function test_command_cleans_expired_tokens_by_default(): void
    {
        // Arrange: Create an expired token
        $expiredToken = $this->user->createNemesisToken('Expired Token');
        $expiredTokenModel = $this->user->getNemesisToken($expiredToken);
        $expiredTokenModel->expires_at = now()->subDay();
        $expiredTokenModel->save();

        // Arrange: Create a valid token that should not be deleted
        $validToken = $this->user->createNemesisToken('Valid Token');
        $validTokenModel = $this->user->getNemesisToken($validToken);
        $validTokenModel->expires_at = now()->addDays(10);
        $validTokenModel->save();

        // Act: Run command with force flag to skip confirmation
        $exitCode = Artisan::call('nemesis:clean', ['--force' => true]);

        // Assert: Command should exit successfully
        $this->assertEquals(0, $exitCode);

        // Assert: Expired token should be deleted
        $this->assertNull($this->user->getNemesisToken($expiredToken));

        // Assert: Valid token should still exist
        $this->assertNotNull($this->user->getNemesisToken($validToken));
    }

    /**
     * Test that the command cleans old tokens based on retention period.
     */
    public function test_command_cleans_old_tokens_based_on_retention(): void
    {
        // Arrange: Set retention period to 30 days in config
        Config::set('nemesis.cleanup.keep_expired_for_days', 30);

        // Arrange: Create an old token (created 40 days ago)
        $oldToken = $this->user->createNemesisToken('Old Token');
        $oldTokenModel = $this->user->getNemesisToken($oldToken);
        $oldTokenModel->created_at = now()->subDays(40);
        $oldTokenModel->expires_at = null;
        $oldTokenModel->save();

        // Arrange: Create a recent token (created 10 days ago)
        $recentToken = $this->user->createNemesisToken('Recent Token');
        $recentTokenModel = $this->user->getNemesisToken($recentToken);
        $recentTokenModel->created_at = now()->subDays(10);
        $recentTokenModel->expires_at = null;
        $recentTokenModel->save();

        // Act: Run command with force flag
        $exitCode = Artisan::call('nemesis:clean', ['--force' => true]);

        // Assert: Command should exit successfully
        $this->assertEquals(0, $exitCode);

        // Assert: Old token should be deleted
        $this->assertNull($this->user->getNemesisToken($oldToken));

        // Assert: Recent token should still exist
        $this->assertNotNull($this->user->getNemesisToken($recentToken));
    }

    // ============================================================================
    // Tests for --days option
    // ============================================================================

    /**
     * Test that the --days option overrides config retention period.
     */
    public function test_days_option_overrides_config_retention(): void
    {
        // Arrange: Set config retention to 30 days
        Config::set('nemesis.cleanup.keep_expired_for_days', 30);

        // Arrange: Create a token created 20 days ago
        $token = $this->user->createNemesisToken('Test Token');
        $tokenModel = $this->user->getNemesisToken($token);
        $tokenModel->created_at = now()->subDays(20);
        $tokenModel->expires_at = null;
        $tokenModel->save();

        // Act: Run command with --days=15 (overrides config 30)
        $exitCode = Artisan::call('nemesis:clean', ['--force' => true, '--days' => 15]);

        // Assert: Command should exit successfully
        $this->assertEquals(0, $exitCode);

        // Assert: Token is deleted (20 days > 15 days threshold)
        $this->assertNull($this->user->getNemesisToken($token));
    }

    /**
     * Test that the --days option with higher value keeps more tokens.
     */
    public function test_days_option_with_higher_value_keeps_more_tokens(): void
    {
        // Arrange: Create a token created 20 days ago
        $token = $this->user->createNemesisToken('Test Token');
        $tokenModel = $this->user->getNemesisToken($token);
        $tokenModel->created_at = now()->subDays(20);
        $tokenModel->expires_at = null;
        $tokenModel->save();

        // Act: Run command with --days=30 (higher than token age)
        $exitCode = Artisan::call('nemesis:clean', ['--force' => true, '--days' => 30]);

        // Assert: Command should exit successfully
        $this->assertEquals(0, $exitCode);

        // Assert: Token is kept (20 days < 30 days threshold)
        $this->assertNotNull($this->user->getNemesisToken($token));
    }

    // ============================================================================
    // Tests for --keep-expired option
    // ============================================================================

    /**
     * Test that --keep-expired option prevents deletion of expired tokens.
     */
    public function test_keep_expired_option_prevents_expired_token_deletion(): void
    {
        // Arrange: Create an expired token
        $expiredToken = $this->user->createNemesisToken('Expired Token');
        $expiredTokenModel = $this->user->getNemesisToken($expiredToken);
        $expiredTokenModel->expires_at = now()->subDay();
        $expiredTokenModel->save();

        // Act: Run command with --keep-expired flag
        $exitCode = Artisan::call('nemesis:clean', ['--force' => true, '--keep-expired' => true]);

        // Assert: Command should exit successfully
        $this->assertEquals(0, $exitCode);

        // Assert: Expired token is NOT deleted (kept by flag)
        $this->assertNotNull($this->user->getNemesisToken($expiredToken));
    }

    /**
     * Test that --keep-expired still cleans old tokens.
     */
    public function test_keep_expired_still_cleans_old_tokens(): void
    {
        // Arrange: Set retention period to 30 days
        Config::set('nemesis.cleanup.keep_expired_for_days', 30);

        // Arrange: Create an expired token
        $expiredToken = $this->user->createNemesisToken('Expired Token');
        $expiredTokenModel = $this->user->getNemesisToken($expiredToken);
        $expiredTokenModel->expires_at = now()->subDay();
        $expiredTokenModel->save();

        // Arrange: Create an old but not expired token
        $oldToken = $this->user->createNemesisToken('Old Token');
        $oldTokenModel = $this->user->getNemesisToken($oldToken);
        $oldTokenModel->created_at = now()->subDays(40);
        $oldTokenModel->expires_at = null;
        $oldTokenModel->save();

        // Act: Run command with --keep-expired flag
        $exitCode = Artisan::call('nemesis:clean', ['--force' => true, '--keep-expired' => true]);

        // Assert: Command should exit successfully
        $this->assertEquals(0, $exitCode);

        // Assert: Expired token is kept (due to --keep-expired)
        $this->assertNotNull($this->user->getNemesisToken($expiredToken));

        // Assert: Old token is deleted (exceeds retention period)
        $this->assertNull($this->user->getNemesisToken($oldToken));
    }

    // ============================================================================
    // Tests for --force option
    // ============================================================================

    /**
     * Test that command executes without confirmation when --force is used.
     */
    public function test_command_executes_without_confirmation_when_force_used(): void
    {
        // Arrange: Create an expired token
        $expiredToken = $this->user->createNemesisToken('Expired Token');
        $expiredTokenModel = $this->user->getNemesisToken($expiredToken);
        $expiredTokenModel->expires_at = now()->subDay();
        $expiredTokenModel->save();

        // Act: Run command with force flag (skips confirmation prompt)
        $exitCode = Artisan::call('nemesis:clean', ['--force' => true]);

        // Assert: Command executed without confirmation
        $this->assertEquals(0, $exitCode);

        // Assert: Expired token was deleted
        $this->assertNull($this->user->getNemesisToken($expiredToken));
    }

    // ============================================================================
    // Tests for edge cases
    // ============================================================================

    /**
     * Test that command handles zero retention period correctly.
     */
    public function test_command_handles_zero_retention_period(): void
    {
        // Arrange: Set retention to 0 (disable old token cleanup)
        Config::set('nemesis.cleanup.keep_expired_for_days', 0);

        // Arrange: Create an expired token
        $expiredToken = $this->user->createNemesisToken('Expired Token');
        $expiredTokenModel = $this->user->getNemesisToken($expiredToken);
        $expiredTokenModel->expires_at = now()->subDay();
        $expiredTokenModel->save();

        // Arrange: Create an old token (100 days old)
        $oldToken = $this->user->createNemesisToken('Old Token');
        $oldTokenModel = $this->user->getNemesisToken($oldToken);
        $oldTokenModel->created_at = now()->subDays(100);
        $oldTokenModel->expires_at = null;
        $oldTokenModel->save();

        // Act: Run command with force flag
        $exitCode = Artisan::call('nemesis:clean', ['--force' => true]);

        // Assert: Command should exit successfully
        $this->assertEquals(0, $exitCode);

        // Assert: Expired token is deleted (always cleaned)
        $this->assertNull($this->user->getNemesisToken($expiredToken));

        // Assert: Old token is NOT deleted (retention period is 0)
        $this->assertNotNull($this->user->getNemesisToken($oldToken));
    }

    /**
     * Test that command handles negative retention period correctly.
     */
    public function test_command_handles_negative_retention_period(): void
    {
        // Arrange: Set retention to negative value (should disable cleanup)
        Config::set('nemesis.cleanup.keep_expired_for_days', -5);

        // Arrange: Create an old token
        $oldToken = $this->user->createNemesisToken('Old Token');
        $oldTokenModel = $this->user->getNemesisToken($oldToken);
        $oldTokenModel->created_at = now()->subDays(100);
        $oldTokenModel->expires_at = null;
        $oldTokenModel->save();

        // Act: Run command with force flag
        $exitCode = Artisan::call('nemesis:clean', ['--force' => true]);

        // Assert: Command should exit successfully
        $this->assertEquals(0, $exitCode);

        // Assert: Old token is NOT deleted (negative retention disables cleanup)
        $this->assertNotNull($this->user->getNemesisToken($oldToken));
    }

    /**
     * Test that command handles no tokens to clean.
     */
    public function test_command_handles_no_tokens_to_clean(): void
    {
        // Act: Run command with force flag (no tokens exist)
        $exitCode = Artisan::call('nemesis:clean', ['--force' => true]);

        // Assert: Command should exit successfully
        $this->assertEquals(0, $exitCode);

        // Assert: Output contains success message indicating no work needed
        $output = Artisan::output();
        $this->assertStringContainsString('✨ No tokens needed cleaning', $output);
    }

    /**
     * Test that command correctly distinguishes between expired and old tokens.
     */
    public function test_command_distinguishes_expired_and_old_tokens(): void
    {
        // Arrange: Set retention period to 30 days
        Config::set('nemesis.cleanup.keep_expired_for_days', 30);

        // Arrange: Create token that is both expired (1 day ago) and old (40 days old)
        $expiredOldToken = $this->user->createNemesisToken('Expired & Old Token');
        $expiredOldModel = $this->user->getNemesisToken($expiredOldToken);
        $expiredOldModel->expires_at = now()->subDay();
        $expiredOldModel->created_at = now()->subDays(40);
        $expiredOldModel->save();

        // Act: Run command with force flag
        $exitCode = Artisan::call('nemesis:clean', ['--force' => true]);

        // Assert: Command should exit successfully
        $this->assertEquals(0, $exitCode);

        // Assert: Token is deleted (counted as expired, not double-counted)
        $this->assertNull($this->user->getNemesisToken($expiredOldToken));
    }

    // ============================================================================
    // Tests for configuration values
    // ============================================================================

    /**
     * Test that command uses config values when no options provided.
     */
    public function test_command_uses_config_values_when_no_options(): void
    {
        // Arrange: Set custom config values
        Config::set('nemesis.cleanup.keep_expired_for_days', 45);
        Config::set('nemesis.cleanup.auto_cleanup', true);
        Config::set('nemesis.cleanup.frequency', 120);

        // Arrange: Create token created 40 days ago (should be kept with 45 days retention)
        $token = $this->user->createNemesisToken('Test Token');
        $tokenModel = $this->user->getNemesisToken($token);
        $tokenModel->created_at = now()->subDays(40);
        $tokenModel->expires_at = null;
        $tokenModel->save();

        // Act: Run command with force flag (no option overrides)
        $exitCode = Artisan::call('nemesis:clean', ['--force' => true]);

        // Assert: Command should exit successfully
        $this->assertEquals(0, $exitCode);

        // Assert: Token is kept (40 days < 45 days retention from config)
        $this->assertNotNull($this->user->getNemesisToken($token));

        // Assert: Output shows config was used
        $output = Artisan::output();
        $this->assertStringContainsString('Using retention period from config: 45 days', $output);
    }

    /**
     * Test that command shows configuration summary in output.
     */
    public function test_command_shows_configuration_summary(): void
    {
        // Arrange: Set config values for testing
        Config::set('nemesis.cleanup.auto_cleanup', true);
        Config::set('nemesis.cleanup.frequency', 60);
        Config::set('nemesis.cleanup.keep_expired_for_days', 30);

        // Act: Run command with force flag
        $exitCode = Artisan::call('nemesis:clean', ['--force' => true]);

        // Assert: Command should exit successfully
        $this->assertEquals(0, $exitCode);

        // Act: Get command output
        $output = Artisan::output();

        // Assert: Output contains configuration summary header
        $this->assertStringContainsString('📋 Current Configuration:', $output);

        // Assert: Output shows auto cleanup status
        $this->assertStringContainsString('Auto cleanup: ✅ Enabled', $output);

        // Assert: Output shows cleanup frequency
        $this->assertStringContainsString('Cleanup frequency: 60 minutes', $output);

        // Assert: Output shows retention period
        $this->assertStringContainsString('Retention period: 30 days', $output);
    }

    /**
     * Test that command shows correct status when keep-expired is used.
     */
    public function test_command_shows_keep_expired_status_in_output(): void
    {
        // Act: Run command with --keep-expired flag
        $exitCode = Artisan::call('nemesis:clean', ['--force' => true, '--keep-expired' => true]);

        // Assert: Command should exit successfully
        $this->assertEquals(0, $exitCode);

        // Act: Get command output
        $output = Artisan::output();

        // Assert: Output indicates expired tokens are being kept
        $this->assertStringContainsString('Keeping expired tokens as requested (--keep-expired)', $output);

        // Assert: Output shows expired tokens status as kept
        $this->assertStringContainsString('Expired tokens: ⏸️  Kept (--keep-expired flag used)', $output);
    }
}
