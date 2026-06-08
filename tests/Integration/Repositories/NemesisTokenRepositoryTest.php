<?php
// tests/Integration/Repositories/NemesisTokenRepositoryTest.php

declare(strict_types=1);

namespace Kani\Nemesis\Tests\Integration\Repositories;

use AndyDefer\Repository\Records\FindByRecord;
use Carbon\Carbon;
use Kani\Nemesis\Models\NemesisToken;
use Kani\Nemesis\Records\NemesisTokenFilterRecord;
use Kani\Nemesis\Records\NemesisTokenRecord;
use Kani\Nemesis\Repositories\NemesisTokenRepository;
use Kani\Nemesis\Tests\Fixtures\Models\TestUser;
use Kani\Nemesis\Tests\IntegrationTestCase;

final class NemesisTokenRepositoryTest extends IntegrationTestCase
{
    private NemesisTokenRepository $repository;
    private TestUser $user;

    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow(Carbon::create(2024, 1, 1, 12, 0, 0));

        $this->repository = new NemesisTokenRepository();
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
            'token_hash' => hash('sha256', 'test-token-' . uniqid()),
            'tokenable_type' => $this->user->getMorphClass(),
            'tokenable_id' => $this->user->id,
            'name' => 'Test Token',
            'source' => 'web',
        ], $overrides);

        $record = NemesisTokenRecord::from($data);

        return $this->repository->create($record);
    }

    // ============================================================================
    // findWithTrashedByFilters Tests
    // ============================================================================

    public function test_find_with_trashed_by_filters_returns_soft_deleted_tokens(): void
    {
        $token = $this->createToken(['name' => 'To Delete']);
        $this->repository->delete($token->id);

        $filters = new NemesisTokenFilterRecord(
            tokenable_type: $this->user->getMorphClass(),
            tokenable_id: $this->user->id,
        );

        $result = $this->repository->findWithTrashedByFilters($filters);

        $this->assertCount(1, $result);
        $this->assertNotNull($result->first()->deleted_at);
    }

    // ============================================================================
    // existsWithTrashed Tests
    // ============================================================================

    public function test_exists_with_trashed_returns_true_when_soft_deleted_token_exists(): void
    {
        $token = $this->createToken();
        $this->repository->delete($token->id);

        $filters = new NemesisTokenFilterRecord(
            tokenable_type: $this->user->getMorphClass(),
            tokenable_id: $this->user->id,
        );

        $exists = $this->repository->existsWithTrashed($filters);

        $this->assertTrue($exists);
    }

    public function test_exists_with_trashed_returns_false_when_no_tokens(): void
    {
        $filters = new NemesisTokenFilterRecord(
            tokenable_type: $this->user->getMorphClass(),
            tokenable_id: $this->user->id,
        );

        $exists = $this->repository->existsWithTrashed($filters);

        $this->assertFalse($exists);
    }

    // ============================================================================
    // restoreBulkForTokenable Tests
    // ============================================================================

    public function test_restore_bulk_for_tokenable_restores_all_soft_deleted_tokens(): void
    {
        $token1 = $this->createToken(['name' => 'Token 1']);
        $token2 = $this->createToken(['name' => 'Token 2']);
        $token3 = $this->createToken(['name' => 'Token 3']);

        $this->repository->delete($token1->id);
        $this->repository->delete($token2->id);

        $restoredCount = $this->repository->restoreBulkForTokenable(
            $this->user->getMorphClass(),
            $this->user->id
        );

        $this->assertSame(2, $restoredCount);

        $filters = new NemesisTokenFilterRecord(
            tokenable_type: $this->user->getMorphClass(),
            tokenable_id: $this->user->id,
            is_revoked: false,
        );
        $findByRecord = new FindByRecord(filters: $filters);
        $activeTokens = $this->repository->findBy($findByRecord);
        $this->assertCount(3, $activeTokens);
    }

    public function test_restore_bulk_for_tokenable_returns_zero_when_no_soft_deleted_tokens(): void
    {
        $this->createToken(['name' => 'Active Token']);

        $restoredCount = $this->repository->restoreBulkForTokenable(
            $this->user->getMorphClass(),
            $this->user->id
        );

        $this->assertSame(0, $restoredCount);
    }

    // ============================================================================
    // forceDeleteBulk Tests (hérité de AbstractRepository)
    // ============================================================================

    public function test_force_delete_bulk_permanently_deletes_matching_tokens(): void
    {
        $token1 = $this->createToken(['source' => 'web']);
        $token2 = $this->createToken(['source' => 'web']);
        $token3 = $this->createToken(['source' => 'mobile']);

        $filters = new NemesisTokenFilterRecord(
            tokenable_type: $this->user->getMorphClass(),
            tokenable_id: $this->user->id,
            source: 'web',
        );

        $deletedCount = $this->repository->forceDeleteBulk($filters);

        $this->assertSame(2, $deletedCount);
        $this->assertDatabaseMissing('nemesis_tokens', ['id' => $token1->id]);
        $this->assertDatabaseMissing('nemesis_tokens', ['id' => $token2->id]);
        $this->assertDatabaseHas('nemesis_tokens', ['id' => $token3->id]);
    }

    public function test_force_delete_bulk_on_soft_deleted_tokens_permanently_deletes_them(): void
    {
        $token1 = $this->createToken(['source' => 'web']);
        $token2 = $this->createToken(['source' => 'web']);

        $this->repository->delete($token1->id);
        $this->repository->delete($token2->id);

        $filters = new NemesisTokenFilterRecord(
            tokenable_type: $this->user->getMorphClass(),
            tokenable_id: $this->user->id,
            source: 'web',
            is_revoked: true,
        );

        $deletedCount = $this->repository->forceDeleteBulk($filters);

        $this->assertSame(2, $deletedCount);
        $this->assertDatabaseMissing('nemesis_tokens', ['id' => $token1->id]);
        $this->assertDatabaseMissing('nemesis_tokens', ['id' => $token2->id]);
    }
}
