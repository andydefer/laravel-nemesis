<?php

// tests/Integration/Repositories/NemesisTokenRepositoryTest.php

declare(strict_types=1);

namespace AndyDefer\Nemesis\Tests\Integration\Repositories;

use AndyDefer\Nemesis\Models\NemesisToken;
use AndyDefer\Nemesis\Records\NemesisTokenFilterRecord;
use AndyDefer\Nemesis\Repositories\NemesisTokenRepository;
use AndyDefer\Nemesis\Tests\Fixtures\Models\TestUser;
use AndyDefer\Nemesis\Tests\IntegrationTestCase;
use AndyDefer\PhpVo\ValueObjects\DateTimeVO;
use Carbon\Carbon;
use Illuminate\Support\Collection;

final class NemesisTokenRepositoryTest extends IntegrationTestCase
{
    private NemesisTokenRepository $repository;

    private TestUser $user;

    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow(Carbon::create(2024, 1, 1, 12, 0, 0));

        $this->repository = new NemesisTokenRepository;
        $this->user = TestUser::create([
            'name' => 'John Doe',
            'email' => 'john@example.com',
        ]);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function createToken(array $overrides = []): NemesisToken
    {
        $data = array_merge([
            'token_hash' => hash('sha256', uniqid('token-', true).bin2hex(random_bytes(16))),
            'tokenable_type' => $this->user->getMorphClass(),
            'tokenable_id' => $this->user->id,
            'name' => 'Test Token',
            'source' => 'web',
        ], $overrides);

        return NemesisToken::create($data);
    }

    // ============================================================================
    // findWithTrashedByFilters Tests
    // ============================================================================

    public function test_find_with_trashed_by_filters_returns_soft_deleted_tokens(): void
    {
        // Arrange
        $token = $this->createToken(['name' => 'To Delete']);
        $this->repository->delete($token->id);

        $filters = new NemesisTokenFilterRecord(
            tokenable_type: $this->user->getMorphClass(),
            tokenable_id: $this->user->id,
        );

        // Act
        $result = $this->repository->findWithTrashedByFilters($filters);

        // Assert
        $this->assertCount(1, $result);
        $this->assertNotNull($result->first()->deleted_at);
        $this->assertSame('To Delete', $result->first()->name);
    }

    public function test_find_with_trashed_by_filters_returns_both_active_and_deleted_tokens(): void
    {
        // Arrange
        $activeToken = $this->createToken(['name' => 'Active Token']);
        $deletedToken = $this->createToken(['name' => 'Deleted Token']);
        $this->repository->delete($deletedToken->id);

        $filters = new NemesisTokenFilterRecord(
            tokenable_type: $this->user->getMorphClass(),
            tokenable_id: $this->user->id,
        );

        // Act
        $result = $this->repository->findWithTrashedByFilters($filters);

        // Assert
        $this->assertCount(2, $result);
    }

    public function test_find_with_trashed_by_filters_with_filters_applies_conditions(): void
    {
        // Arrange
        $token1 = $this->createToken(['name' => 'Web Token', 'source' => 'web']);
        $token2 = $this->createToken(['name' => 'Mobile Token', 'source' => 'mobile']);
        $this->repository->delete($token1->id);

        $filters = new NemesisTokenFilterRecord(
            tokenable_type: $this->user->getMorphClass(),
            tokenable_id: $this->user->id,
            source: 'web',
        );

        // Act
        $result = $this->repository->findWithTrashedByFilters($filters);

        // Assert
        $this->assertCount(1, $result);
        $this->assertSame('Web Token', $result->first()->name);
        $this->assertNotNull($result->first()->deleted_at);
    }

    public function test_find_with_trashed_by_filters_returns_empty_collection_when_no_tokens(): void
    {
        // Arrange
        $filters = new NemesisTokenFilterRecord(
            tokenable_type: $this->user->getMorphClass(),
            tokenable_id: $this->user->id,
        );

        // Act
        $result = $this->repository->findWithTrashedByFilters($filters);

        // Assert
        $this->assertCount(0, $result);
        $this->assertInstanceOf(Collection::class, $result);
    }

    // ============================================================================
    // existsWithTrashed Tests
    // ============================================================================

    public function test_exists_with_trashed_returns_true_when_active_token_exists(): void
    {
        // Arrange
        $this->createToken(['name' => 'Active Token']);

        $filters = new NemesisTokenFilterRecord(
            tokenable_type: $this->user->getMorphClass(),
            tokenable_id: $this->user->id,
        );

        // Act
        $exists = $this->repository->existsWithTrashed($filters);

        // Assert
        $this->assertTrue($exists);
    }

    public function test_exists_with_trashed_returns_true_when_only_deleted_token_exists(): void
    {
        // Arrange
        $token = $this->createToken(['name' => 'To Delete']);
        $this->repository->delete($token->id);

        $filters = new NemesisTokenFilterRecord(
            tokenable_type: $this->user->getMorphClass(),
            tokenable_id: $this->user->id,
        );

        // Act
        $exists = $this->repository->existsWithTrashed($filters);

        // Assert
        $this->assertTrue($exists);
    }

    public function test_exists_with_trashed_returns_false_when_no_tokens(): void
    {
        // Arrange
        $filters = new NemesisTokenFilterRecord(
            tokenable_type: $this->user->getMorphClass(),
            tokenable_id: $this->user->id,
        );

        // Act
        $exists = $this->repository->existsWithTrashed($filters);

        // Assert
        $this->assertFalse($exists);
    }

    public function test_exists_with_trashed_with_filters_returns_true_when_matching_deleted_token(): void
    {
        // Arrange
        $token = $this->createToken(['name' => 'Web Token', 'source' => 'web']);
        $this->repository->delete($token->id);

        $filters = new NemesisTokenFilterRecord(
            tokenable_type: $this->user->getMorphClass(),
            tokenable_id: $this->user->id,
            source: 'web',
        );

        // Act
        $exists = $this->repository->existsWithTrashed($filters);

        // Assert
        $this->assertTrue($exists);
    }

    public function test_exists_with_trashed_with_filters_returns_false_when_no_matching_deleted_token(): void
    {
        // Arrange
        $token = $this->createToken(['name' => 'Web Token', 'source' => 'web']);
        $this->repository->delete($token->id);

        $filters = new NemesisTokenFilterRecord(
            tokenable_type: $this->user->getMorphClass(),
            tokenable_id: $this->user->id,
            source: 'mobile', // Different source
        );

        // Act
        $exists = $this->repository->existsWithTrashed($filters);

        // Assert
        $this->assertFalse($exists);
    }

    // ============================================================================
    // restoreBulkForTokenable Tests
    // ============================================================================

    public function test_restore_bulk_for_tokenable_restores_all_soft_deleted_tokens(): void
    {
        // Arrange
        $token1 = $this->createToken(['name' => 'Token 1']);
        $token2 = $this->createToken(['name' => 'Token 2']);
        $token3 = $this->createToken(['name' => 'Token 3']);

        $this->repository->delete($token1->id);
        $this->repository->delete($token2->id);
        // token3 reste actif

        // Vérifier que les tokens sont soft deletés
        $filters = new NemesisTokenFilterRecord(
            tokenable_type: $this->user->getMorphClass(),
            tokenable_id: $this->user->id,
            is_revoked: true,
        );
        $deletedTokens = $this->repository->findWithTrashedByFilters($filters);
        $this->assertCount(2, $deletedTokens);

        // Act
        $restoredCount = $this->repository->restoreBulkForTokenable(
            $this->user->getMorphClass(),
            $this->user->id
        );

        // Assert
        $this->assertSame(2, $restoredCount);

        // Vérifier que tous les tokens sont actifs
        $activeTokens = $this->repository->findWithTrashedByFilters($filters);
        $this->assertCount(0, $activeTokens);
    }

    public function test_restore_bulk_for_tokenable_only_restores_tokens_for_specific_tokenable(): void
    {
        // Arrange
        $user2 = TestUser::create([
            'name' => 'Jane Doe',
            'email' => 'jane@example.com',
        ]);

        $tokenUser1 = $this->createToken(['name' => 'User1 Token']);
        $tokenUser2 = NemesisToken::create([
            'token_hash' => hash('sha256', uniqid('token-', true)),
            'tokenable_type' => $user2->getMorphClass(),
            'tokenable_id' => $user2->id,
            'name' => 'User2 Token',
            'source' => 'web',
        ]);

        $this->repository->delete($tokenUser1->id);
        $this->repository->delete($tokenUser2->id);

        // Act
        $restoredCount = $this->repository->restoreBulkForTokenable(
            $this->user->getMorphClass(),
            $this->user->id
        );

        // Assert
        $this->assertSame(1, $restoredCount);

        // Vérifier que le token du user1 est restauré
        $restoredToken = $this->repository->find($tokenUser1->id);
        $this->assertNotNull($restoredToken);
        $this->assertNull($restoredToken->deleted_at);

        // Vérifier que le token du user2 est toujours soft deleté
        $stillDeletedToken = $this->repository->findWithTrashed($tokenUser2->id);
        $this->assertNotNull($stillDeletedToken);
        $this->assertNotNull($stillDeletedToken->deleted_at);
    }

    public function test_restore_bulk_for_tokenable_returns_zero_when_no_soft_deleted_tokens(): void
    {
        // Arrange
        $this->createToken(['name' => 'Active Token']);

        // Act
        $restoredCount = $this->repository->restoreBulkForTokenable(
            $this->user->getMorphClass(),
            $this->user->id
        );

        // Assert
        $this->assertSame(0, $restoredCount);
    }

    public function test_restore_bulk_for_tokenable_returns_zero_when_tokenable_has_no_tokens(): void
    {
        // Act
        $restoredCount = $this->repository->restoreBulkForTokenable(
            $this->user->getMorphClass(),
            $this->user->id
        );

        // Assert
        $this->assertSame(0, $restoredCount);
    }

    public function test_restore_bulk_for_tokenable_restores_only_deleted_tokens_keeps_active_tokens(): void
    {
        // Arrange
        $activeToken = $this->createToken(['name' => 'Active Token']);
        $deletedToken = $this->createToken(['name' => 'Deleted Token']);
        $this->repository->delete($deletedToken->id);

        // Act
        $restoredCount = $this->repository->restoreBulkForTokenable(
            $this->user->getMorphClass(),
            $this->user->id
        );

        // Assert
        $this->assertSame(1, $restoredCount);

        // Vérifier que le token actif est toujours actif
        $activeToken->refresh();
        $this->assertNull($activeToken->deleted_at);

        // Vérifier que le token supprimé est restauré
        $deletedToken->refresh();
        $this->assertNull($deletedToken->deleted_at);
    }

    // ============================================================================
    // Additional Tests for applyFilters with created_before
    // ============================================================================

    public function test_find_with_trashed_by_filters_with_created_before_filter(): void
    {
        // Arrange
        $oldDate = DateTimeVO::from(Carbon::getTestNow()->subDays(10)->toIso8601String());

        $oldToken = $this->createToken(['name' => 'Old Token']);
        $oldToken->created_at = Carbon::getTestNow()->subDays(15);
        $oldToken->save();

        $newToken = $this->createToken(['name' => 'New Token']);
        $newToken->created_at = Carbon::getTestNow();
        $newToken->save();

        $filters = new NemesisTokenFilterRecord(
            tokenable_type: $this->user->getMorphClass(),
            tokenable_id: $this->user->id,
            created_before: $oldDate,
        );

        // Act
        $result = $this->repository->findWithTrashedByFilters($filters);

        // Assert
        $this->assertCount(1, $result);
        $this->assertSame('Old Token', $result->first()->name);
    }
}
