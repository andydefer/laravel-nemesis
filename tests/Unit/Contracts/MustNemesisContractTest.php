<?php

declare(strict_types=1);

namespace Kani\Nemesis\Tests\Unit\Contracts;

use AndyDefer\DomainStructures\Abstracts\AbstractRecord;
use Kani\Nemesis\Contracts\MustNemesis;
use Kani\Nemesis\Tests\Fixtures\Models\TestApiClient;
use Kani\Nemesis\Tests\Fixtures\Models\TestCheckPoint;
use Kani\Nemesis\Tests\Fixtures\Models\TestCustomFormatUser;
use Kani\Nemesis\Tests\Fixtures\Models\TestUser;
use Kani\Nemesis\Tests\TestCase;

/**
 * Test suite for MustNemesis contract.
 *
 * Verifies that models implementing MustNemesis must provide
 * the nemesisFormat() method and that each model returns the
 * expected data structure as a Record.
 */
final class MustNemesisContractTest extends TestCase
{
    /**
     * Test that TestUser correctly implements nemesisFormat() with standard user fields.
     */
    public function test_test_user_implements_nemesis_format(): void
    {
        // Arrange: Create a TestUser instance with test data
        $user = new TestUser;
        $user->id = 1;
        $user->name = 'John Doe';
        $user->email = 'john@example.com';

        // Act: Get the formatted representation
        $formatted = $user->nemesisFormat();

        // Assert: Format is a Record
        $this->assertInstanceOf(AbstractRecord::class, $formatted);

        // Assert: Format contains expected user fields
        $this->assertEquals(1, $formatted->id);
        $this->assertEquals('John Doe', $formatted->name);
        $this->assertEquals('john@example.com', $formatted->email);
    }

    /**
     * Test that TestApiClient correctly implements nemesisFormat() excluding sensitive data.
     */
    public function test_test_api_client_implements_nemesis_format(): void
    {
        // Arrange: Create a TestApiClient instance with test data
        $apiClient = new TestApiClient;
        $apiClient->id = 42;
        $apiClient->name = 'API Service';

        // Act: Get the formatted representation
        $formatted = $apiClient->nemesisFormat();

        // Assert: Format is a Record
        $this->assertInstanceOf(AbstractRecord::class, $formatted);

        // Assert: Format contains expected API client fields
        $this->assertEquals(42, $formatted->id);
        $this->assertEquals('API Service', $formatted->name);
        $this->assertEquals('api_client', $formatted->type);

        // Assert: Sensitive api_key is NOT exposed
        $this->assertNull($formatted->api_key ?? null);
    }

    /**
     * Test that TestCheckPoint correctly implements nemesisFormat() with checkpoint-specific fields.
     */
    public function test_test_checkpoint_implements_nemesis_format(): void
    {
        // Arrange: Create a TestCheckPoint instance with test data
        $checkpoint = new TestCheckPoint;
        $checkpoint->id = 10;
        $checkpoint->name = 'Main Entrance';
        $checkpoint->location = 'Gate A';
        $checkpoint->is_active = true;

        // Act: Get the formatted representation
        $formatted = $checkpoint->nemesisFormat();

        // Assert: Format is a Record
        $this->assertInstanceOf(AbstractRecord::class, $formatted);

        // Assert: Format contains checkpoint-specific fields
        $this->assertEquals(10, $formatted->id);
        $this->assertEquals('Main Entrance', $formatted->name);
        $this->assertEquals('Gate A', $formatted->location);
        $this->assertEquals('active', $formatted->status);
        $this->assertEquals('checkpoint', $formatted->type);
    }

    /**
     * Test that TestCustomFormatUser uses custom format with different field names.
     */
    public function test_custom_format_user_uses_different_structure(): void
    {
        // Arrange: Create a TestCustomFormatUser instance with test data
        $user = new TestCustomFormatUser;
        $user->id = 5;
        $user->name = 'Jane Smith';
        $user->email = 'jane@example.com';
        $user->email_verified_at = now();

        // Act: Get the formatted representation
        $formatted = $user->nemesisFormat();

        // Assert: Format is a Record
        $this->assertInstanceOf(AbstractRecord::class, $formatted);

        // Assert: Field values are correctly mapped
        $this->assertEquals(5, $formatted->user_id);
        $this->assertEquals('Jane Smith', $formatted->full_name);
        $this->assertTrue($formatted->is_verified);
        $this->assertEquals('only_for_api', $formatted->custom_field);
        $this->assertEquals('custom_user', $formatted->type);

        // Assert: Email is NOT exposed in custom format (security)
        $this->assertNull($formatted->email ?? null);
    }

    /**
     * Test that all test models correctly implement the MustNemesis contract.
     */
    public function test_all_test_models_implement_must_nemesis(): void
    {
        // Arrange: Create instances of all test models
        $models = [
            new TestUser,
            new TestApiClient,
            new TestCheckPoint,
            new TestCustomFormatUser,
        ];

        // Act & Assert: Each model implements the contract correctly
        foreach ($models as $model) {
            // Assert: Model implements MustNemesis interface
            $this->assertInstanceOf(MustNemesis::class, $model);

            // Assert: Model has nemesisFormat method
            $this->assertTrue(method_exists($model, 'nemesisFormat'));

            // Assert: nemesisFormat returns an AbstractRecord
            $this->assertInstanceOf(AbstractRecord::class, $model->nemesisFormat());
        }
    }
}
