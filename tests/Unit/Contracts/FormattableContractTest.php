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
 * the nemesisFormat() method and that each model returns the
 * expected data structure.
 */
final class FormattableContractTest extends TestCase
{
    /**
     * Test that TestUser correctly implements nemesisFormat() with standard user fields.
     */
    public function test_test_user_implements_nemesis_format(): void
    {
        // Arrange: Create a TestUser instance with test data
        $user = new TestUser();
        $user->id = 1;
        $user->name = 'John Doe';
        $user->email = 'john@example.com';

        // Act: Get the formatted representation
        $formatted = $user->nemesisFormat();

        // Assert: Format contains expected user fields
        $this->assertIsArray($formatted);
        $this->assertEquals(1, $formatted['id']);
        $this->assertEquals('John Doe', $formatted['name']);
        $this->assertEquals('john@example.com', $formatted['email']);
        $this->assertEquals('user', $formatted['type']);
    }

    /**
     * Test that TestApiClient correctly implements nemesisFormat() excluding sensitive data.
     */
    public function test_test_api_client_implements_nemesis_format(): void
    {
        // Arrange: Create a TestApiClient instance with test data
        $apiClient = new TestApiClient();
        $apiClient->id = 42;
        $apiClient->name = 'API Service';

        // Act: Get the formatted representation
        $formatted = $apiClient->nemesisFormat();

        // Assert: Format contains expected API client fields
        $this->assertIsArray($formatted);
        $this->assertEquals(42, $formatted['id']);
        $this->assertEquals('API Service', $formatted['name']);
        $this->assertEquals('api_client', $formatted['type']);

        // Assert: Sensitive api_key is NOT exposed
        $this->assertArrayNotHasKey('api_key', $formatted);
    }

    /**
     * Test that TestCheckPoint correctly implements nemesisFormat() with checkpoint-specific fields.
     */
    public function test_test_checkpoint_implements_nemesis_format(): void
    {
        // Arrange: Create a TestCheckPoint instance with test data
        $checkpoint = new TestCheckPoint();
        $checkpoint->id = 10;
        $checkpoint->name = 'Main Entrance';
        $checkpoint->location = 'Gate A';
        $checkpoint->is_active = true;

        // Act: Get the formatted representation
        $formatted = $checkpoint->nemesisFormat();

        // Assert: Format contains checkpoint-specific fields
        $this->assertIsArray($formatted);
        $this->assertEquals(10, $formatted['id']);
        $this->assertEquals('Main Entrance', $formatted['name']);
        $this->assertEquals('Gate A', $formatted['location']);
        $this->assertEquals('active', $formatted['status']);
        $this->assertEquals('checkpoint', $formatted['type']);
    }

    /**
     * Test that TestCustomFormatUser uses custom format with different field names.
     */
    public function test_custom_format_user_uses_different_structure(): void
    {
        // Arrange: Create a TestCustomFormatUser instance with test data
        $user = new TestCustomFormatUser();
        $user->id = 5;
        $user->name = 'Jane Smith';
        $user->email = 'jane@example.com';
        $user->email_verified_at = now();

        // Act: Get the formatted representation
        $formatted = $user->nemesisFormat();

        // Assert: Format uses custom field names (user_id, full_name)
        $this->assertIsArray($formatted);
        $this->assertArrayHasKey('user_id', $formatted);
        $this->assertArrayHasKey('full_name', $formatted);
        $this->assertArrayHasKey('is_verified', $formatted);
        $this->assertArrayHasKey('custom_field', $formatted);
        $this->assertArrayHasKey('type', $formatted);

        // Assert: Field values are correctly mapped
        $this->assertEquals(5, $formatted['user_id']);
        $this->assertEquals('Jane Smith', $formatted['full_name']);
        $this->assertTrue($formatted['is_verified']);
        $this->assertEquals('only_for_api', $formatted['custom_field']);
        $this->assertEquals('custom_user', $formatted['type']);

        // Assert: Email is NOT exposed in custom format (security)
        $this->assertArrayNotHasKey('email', $formatted);
    }

    /**
     * Test that all test models correctly implement the CanBeFormatted contract.
     */
    public function test_all_test_models_implement_can_be_formatted(): void
    {
        // Arrange: Create instances of all test models
        $models = [
            new TestUser(),
            new TestApiClient(),
            new TestCheckPoint(),
            new TestCustomFormatUser(),
        ];

        // Act & Assert: Each model implements the contract correctly
        foreach ($models as $model) {
            // Assert: Model implements CanBeFormatted interface
            $this->assertInstanceOf(CanBeFormatted::class, $model);

            // Assert: Model has nemesisFormat method
            $this->assertTrue(method_exists($model, 'nemesisFormat'));

            // Assert: nemesisFormat returns an array
            $this->assertIsArray($model->nemesisFormat());
        }
    }
}
