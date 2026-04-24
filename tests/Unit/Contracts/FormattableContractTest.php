<?php

declare(strict_types=1);

namespace Kani\Nemesis\Tests\Unit\Contracts;

use Kani\Nemesis\Contracts\CanBeFormatted;
use Kani\Nemesis\Tests\Support\TestApiClient;
use Kani\Nemesis\Tests\Support\TestCheckPoint;
use Kani\Nemesis\Tests\Support\TestCustomFormatUser;
use Kani\Nemesis\Tests\Support\TestUser;
use Kani\Nemesis\Tests\TestCase;

/**
 * Test suite for CanBeFormatted contract.
 *
 * Verifies that models implementing CanBeFormatted must provide
 * the nemesisFormat() method.
 */
final class FormattableContractTest extends TestCase
{
    /**
     * Test that TestUser correctly implements nemesisFormat().
     */
    public function test_test_user_implements_nemesis_format(): void
    {
        $user = new TestUser();
        $user->id = 1;
        $user->name = 'John Doe';
        $user->email = 'john@example.com';

        $formatted = $user->nemesisFormat();

        $this->assertIsArray($formatted);
        $this->assertEquals(1, $formatted['id']);
        $this->assertEquals('John Doe', $formatted['name']);
        $this->assertEquals('john@example.com', $formatted['email']);
        $this->assertEquals('user', $formatted['type']);
    }

    /**
     * Test that TestApiClient correctly implements nemesisFormat().
     */
    public function test_test_api_client_implements_nemesis_format(): void
    {
        $apiClient = new TestApiClient();
        $apiClient->id = 42;
        $apiClient->name = 'API Service';

        $formatted = $apiClient->nemesisFormat();

        $this->assertIsArray($formatted);
        $this->assertEquals(42, $formatted['id']);
        $this->assertEquals('API Service', $formatted['name']);
        $this->assertEquals('api_client', $formatted['type']);
        $this->assertArrayNotHasKey('api_key', $formatted);
    }

    /**
     * Test that TestCheckPoint correctly implements nemesisFormat().
     */
    public function test_test_checkpoint_implements_nemesis_format(): void
    {
        $checkpoint = new TestCheckPoint();
        $checkpoint->id = 10;
        $checkpoint->name = 'Main Entrance';
        $checkpoint->location = 'Gate A';
        $checkpoint->is_active = true;

        $formatted = $checkpoint->nemesisFormat();

        $this->assertIsArray($formatted);
        $this->assertEquals(10, $formatted['id']);
        $this->assertEquals('Main Entrance', $formatted['name']);
        $this->assertEquals('Gate A', $formatted['location']);
        $this->assertEquals('active', $formatted['status']);
        $this->assertEquals('checkpoint', $formatted['type']);
    }

    /**
     * Test that TestCustomFormatUser uses custom format.
     */
    public function test_custom_format_user_uses_different_structure(): void
    {
        $user = new TestCustomFormatUser();
        $user->id = 5;
        $user->name = 'Jane Smith';
        $user->email = 'jane@example.com';
        $user->email_verified_at = now();

        $formatted = $user->nemesisFormat();

        $this->assertIsArray($formatted);
        $this->assertArrayHasKey('user_id', $formatted);
        $this->assertArrayHasKey('full_name', $formatted);
        $this->assertArrayHasKey('is_verified', $formatted);
        $this->assertArrayHasKey('custom_field', $formatted);
        $this->assertArrayHasKey('type', $formatted);

        $this->assertEquals(5, $formatted['user_id']);
        $this->assertEquals('Jane Smith', $formatted['full_name']);
        $this->assertTrue($formatted['is_verified']);
        $this->assertEquals('only_for_api', $formatted['custom_field']);
        $this->assertEquals('custom_user', $formatted['type']);

        // Email should NOT be exposed in custom format
        $this->assertArrayNotHasKey('email', $formatted);
    }

    /**
     * Test that multiple model types all implement the contract.
     */
    public function test_all_test_models_implement_can_be_formatted(): void
    {
        $models = [
            new TestUser(),
            new TestApiClient(),
            new TestCheckPoint(),
            new TestCustomFormatUser(),
        ];

        foreach ($models as $model) {
            $this->assertInstanceOf(CanBeFormatted::class, $model);
            $this->assertTrue(method_exists($model, 'nemesisFormat'));
            $this->assertIsArray($model->nemesisFormat());
        }
    }
}
