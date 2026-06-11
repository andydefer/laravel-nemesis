<?php

// tests/Integration/Services/NemesisServiceTest.php

declare(strict_types=1);

namespace Kani\Nemesis\Tests\Integration\Services;

use AndyDefer\DomainStructures\Collections\Utility\StringTypedCollection;
use AndyDefer\DomainStructures\Services\HydrationService;
use AndyDefer\DomainStructures\Utils\StrictDataObject;
use AndyDefer\PhpVo\ValueObjects\DateTimeVO;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Kani\Nemesis\Configs\NemesisConfig;
use Kani\Nemesis\Contracts\Configs\NemesisConfigInterface;
use Kani\Nemesis\Models\NemesisToken;
use Kani\Nemesis\Records\NemesisTokenFilterRecord;
use Kani\Nemesis\Records\NemesisTokenRecord;
use Kani\Nemesis\Repositories\NemesisTokenRepository;
use Kani\Nemesis\Services\NemesisService;
use Kani\Nemesis\Tests\Fixtures\Models\TestUser;
use Kani\Nemesis\Tests\IntegrationTestCase;

final class NemesisServiceTest extends IntegrationTestCase
{
    private NemesisService $service;
    private TestUser $user;
    private NemesisTokenRepository $repository;
    private HydrationService $hydration;

    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow(Carbon::create(2024, 1, 1, 12, 0, 0));

        $this->hydration = new HydrationService();
        $this->repository = new NemesisTokenRepository();
        $this->service = new NemesisService(
            repository: $this->repository,
            config: $this->app->make(NemesisConfigInterface::class),
            str: new Str(),
            metadataValidator: $this->app->make(\AndyDefer\DataValidator\Services\MetadataValidator::class),
            hydration: $this->hydration,
        );

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

    private function generateUniqueHash(): string
    {
        return hash('sha256', uniqid('token-', true) . bin2hex(random_bytes(16)));
    }

    private function createBasicToken(): NemesisToken
    {
        $record = $this->hydration->hydrate(NemesisTokenRecord::class, [
            'token_hash' => $this->generateUniqueHash(),
            'name' => 'Basic Token',
            'source' => 'web',
        ]);

        return $this->service->create($record, $this->user);
    }

    private function createTokenWithHash(string $plainToken): NemesisToken
    {
        $record = $this->hydration->hydrate(NemesisTokenRecord::class, [
            'token_hash' => hash('sha256', $plainToken),
            'name' => 'Custom Token',
            'source' => 'web',
        ]);

        return $this->service->create($record, $this->user);
    }

    private function withBearerToken(string $token): void
    {
        $this->app['request']->headers->set('Authorization', 'Bearer ' . $token);
    }

    // ============================================================================
    // Token Creation Tests
    // ============================================================================

    public function test_create_creates_token(): void
    {
        $record = $this->hydration->hydrate(NemesisTokenRecord::class, [
            'token_hash' => $this->generateUniqueHash(),
            'name' => 'API Token',
            'source' => 'api',
        ]);

        $token = $this->service->create($record, $this->user);

        $this->assertInstanceOf(NemesisToken::class, $token);
        $this->assertSame('API Token', $token->name);
        $this->assertSame('api', $token->source);
        $this->assertSame($this->user->getMorphClass(), $token->tokenable_type);
        $this->assertSame($this->user->id, $token->tokenable_id);
    }

    public function test_create_with_plain_token_returns_token_and_plain_token(): void
    {
        $record = $this->hydration->hydrate(NemesisTokenRecord::class, [
            'name' => 'Plain Token',
            'source' => 'cli',
        ]);

        [$token, $plainToken] = $this->service->createWithPlainToken($record, $this->user);

        $this->assertInstanceOf(NemesisToken::class, $token);
        $this->assertIsString($plainToken);
        $this->assertSame('Plain Token', $token->name);
        $this->assertSame('cli', $token->source);
    }

    // ============================================================================
    // CRUD Operations Tests
    // ============================================================================

    public function test_find_returns_token(): void
    {
        $token = $this->createBasicToken();

        $found = $this->service->find($token->id);

        $this->assertNotNull($found);
        $this->assertSame($token->id, $found->id);
    }

    public function test_find_returns_null_for_nonexistent(): void
    {
        $found = $this->service->find(99999);

        $this->assertNull($found);
    }

    public function test_find_with_trashed_returns_deleted_token(): void
    {
        $token = $this->createBasicToken();
        $this->service->delete($token->id);

        $found = $this->service->findWithTrashed($token->id);

        $this->assertNotNull($found);
        $this->assertNotNull($found->deleted_at);
    }

    public function test_find_by_hash_returns_token(): void
    {
        $token = $this->createBasicToken();

        $found = $this->service->findByHash($token->token_hash);

        $this->assertNotNull($found);
        $this->assertSame($token->id, $found->id);
    }

    public function test_update_modifies_token(): void
    {
        $token = $this->createBasicToken();

        $updateRecord = $this->hydration->hydrate(NemesisTokenRecord::class, [
            'name' => 'Updated Name',
            'source' => 'mobile',
        ]);

        $updated = $this->service->update($token->id, $updateRecord);

        $this->assertSame('Updated Name', $updated->name);
        $this->assertSame('mobile', $updated->source);
    }

    public function test_delete_soft_deletes_token(): void
    {
        $token = $this->createBasicToken();

        $deleted = $this->service->delete($token->id);

        $this->assertTrue($deleted);
        $this->assertNull($this->service->find($token->id));
        $this->assertDatabaseHas('nemesis_tokens', ['id' => $token->id]);
        $this->assertDatabaseMissing('nemesis_tokens', ['id' => $token->id, 'deleted_at' => null]);
    }

    public function test_restore_recovers_deleted_token(): void
    {
        $token = $this->createBasicToken();
        $this->service->delete($token->id);

        $restored = $this->service->restore($token->id);

        $this->assertTrue($restored);
        $this->assertNotNull($this->service->find($token->id));
        $this->assertDatabaseHas('nemesis_tokens', ['id' => $token->id, 'deleted_at' => null]);
    }

    public function test_force_delete_permanently_deletes_token(): void
    {
        $token = $this->createBasicToken();

        $deleted = $this->service->forceDelete($token->id);

        $this->assertTrue($deleted);
        $this->assertDatabaseMissing('nemesis_tokens', ['id' => $token->id]);
    }

    public function test_delete_bulk_soft_deletes_matching_tokens(): void
    {
        $token1 = $this->service->create($this->hydration->hydrate(NemesisTokenRecord::class, [
            'token_hash' => $this->generateUniqueHash(),
            'source' => 'web',
            'name' => 'Token 1',
        ]), $this->user);

        $token2 = $this->service->create($this->hydration->hydrate(NemesisTokenRecord::class, [
            'token_hash' => $this->generateUniqueHash(),
            'source' => 'web',
            'name' => 'Token 2',
        ]), $this->user);

        $token3 = $this->service->create($this->hydration->hydrate(NemesisTokenRecord::class, [
            'token_hash' => $this->generateUniqueHash(),
            'source' => 'mobile',
            'name' => 'Token 3',
        ]), $this->user);

        $filters = $this->hydration->hydrate(NemesisTokenFilterRecord::class, ['source' => 'web']);

        $deletedCount = $this->service->deleteBulk($filters);

        $this->assertSame(2, $deletedCount);
        $this->assertNull($this->service->find($token1->id));
        $this->assertNull($this->service->find($token2->id));
        $this->assertNotNull($this->service->find($token3->id));
        $this->assertDatabaseHas('nemesis_tokens', ['id' => $token1->id]);
        $this->assertDatabaseHas('nemesis_tokens', ['id' => $token2->id]);
        $this->assertDatabaseMissing('nemesis_tokens', ['id' => $token1->id, 'deleted_at' => null]);
        $this->assertDatabaseMissing('nemesis_tokens', ['id' => $token2->id, 'deleted_at' => null]);
    }

    public function test_delete_bulk_returns_zero_when_no_tokens_match(): void
    {
        $filters = $this->hydration->hydrate(NemesisTokenFilterRecord::class, ['source' => 'nonexistent']);

        $deletedCount = $this->service->deleteBulk($filters);

        $this->assertSame(0, $deletedCount);
    }

    public function test_delete_bulk_with_empty_filters_deletes_all_tokens(): void
    {
        $this->createBasicToken();
        $this->createBasicToken();
        $filters = $this->hydration->hydrate(NemesisTokenFilterRecord::class, []);

        $deletedCount = $this->service->deleteBulk($filters);

        $this->assertSame(2, $deletedCount);
        $this->assertCount(0, $this->service->getTokensFor($this->user));
    }

    public function test_force_delete_bulk_permanently_deletes_matching_tokens(): void
    {
        $token1 = $this->service->create($this->hydration->hydrate(NemesisTokenRecord::class, [
            'token_hash' => $this->generateUniqueHash(),
            'source' => 'web',
            'name' => 'Token 1',
        ]), $this->user);

        $token2 = $this->service->create($this->hydration->hydrate(NemesisTokenRecord::class, [
            'token_hash' => $this->generateUniqueHash(),
            'source' => 'web',
            'name' => 'Token 2',
        ]), $this->user);

        $token3 = $this->service->create($this->hydration->hydrate(NemesisTokenRecord::class, [
            'token_hash' => $this->generateUniqueHash(),
            'source' => 'mobile',
            'name' => 'Token 3',
        ]), $this->user);

        $filters = $this->hydration->hydrate(NemesisTokenFilterRecord::class, ['source' => 'web']);

        $deletedCount = $this->service->forceDeleteBulk($filters);

        $this->assertSame(2, $deletedCount);
        $this->assertDatabaseMissing('nemesis_tokens', ['id' => $token1->id]);
        $this->assertDatabaseMissing('nemesis_tokens', ['id' => $token2->id]);
        $this->assertDatabaseHas('nemesis_tokens', ['id' => $token3->id]);
    }

    public function test_force_delete_bulk_on_soft_deleted_tokens_permanently_deletes_them(): void
    {
        $token1 = $this->service->create($this->hydration->hydrate(NemesisTokenRecord::class, [
            'token_hash' => $this->generateUniqueHash(),
            'source' => 'web',
            'name' => 'Token 1',
        ]), $this->user);

        $token2 = $this->service->create($this->hydration->hydrate(NemesisTokenRecord::class, [
            'token_hash' => $this->generateUniqueHash(),
            'source' => 'web',
            'name' => 'Token 2',
        ]), $this->user);

        $this->service->delete($token1->id);
        $this->service->delete($token2->id);

        $this->assertDatabaseHas('nemesis_tokens', ['id' => $token1->id]);
        $this->assertDatabaseHas('nemesis_tokens', ['id' => $token2->id]);

        $filters = $this->hydration->hydrate(NemesisTokenFilterRecord::class, [
            'source' => 'web',
            'is_revoked' => true,
        ]);

        $deletedCount = $this->service->forceDeleteBulk($filters);

        $this->assertSame(2, $deletedCount);
        $this->assertDatabaseMissing('nemesis_tokens', ['id' => $token1->id]);
        $this->assertDatabaseMissing('nemesis_tokens', ['id' => $token2->id]);
    }

    public function test_force_delete_bulk_returns_zero_when_no_tokens_match(): void
    {
        $filters = $this->hydration->hydrate(NemesisTokenFilterRecord::class, ['source' => 'nonexistent']);

        $deletedCount = $this->service->forceDeleteBulk($filters);

        $this->assertSame(0, $deletedCount);
    }

    public function test_delete_bulk_and_force_delete_bulk_with_multiple_filters(): void
    {
        $this->service->create($this->hydration->hydrate(NemesisTokenRecord::class, [
            'token_hash' => $this->generateUniqueHash(),
            'source' => 'web',
            'name' => 'Admin Token',
        ]), $this->user);

        $this->service->create($this->hydration->hydrate(NemesisTokenRecord::class, [
            'token_hash' => $this->generateUniqueHash(),
            'source' => 'web',
            'name' => 'User Token',
        ]), $this->user);

        $this->service->create($this->hydration->hydrate(NemesisTokenRecord::class, [
            'token_hash' => $this->generateUniqueHash(),
            'source' => 'mobile',
            'name' => 'Admin Token',
        ]), $this->user);

        $filters = $this->hydration->hydrate(NemesisTokenFilterRecord::class, [
            'source' => 'web',
            'name' => 'Admin Token',
        ]);

        $deletedCount = $this->service->forceDeleteBulk($filters);

        $this->assertSame(1, $deletedCount);
    }

    public function test_delete_bulk_with_expired_filter(): void
    {
        $expiredDate = new DateTimeVO(Carbon::getTestNow()->subDay()->format('Y-m-d\TH:i:sP'));

        $this->service->create($this->hydration->hydrate(NemesisTokenRecord::class, [
            'token_hash' => $this->generateUniqueHash(),
            'expires_at' => $expiredDate,
            'name' => 'Expired Token 1',
        ]), $this->user);

        $this->service->create($this->hydration->hydrate(NemesisTokenRecord::class, [
            'token_hash' => $this->generateUniqueHash(),
            'expires_at' => $expiredDate,
            'name' => 'Expired Token 2',
        ]), $this->user);

        $this->createBasicToken();

        $filters = $this->hydration->hydrate(NemesisTokenFilterRecord::class, ['is_expired' => true]);

        $deletedCount = $this->service->deleteBulk($filters);

        $this->assertSame(2, $deletedCount);
    }

    public function test_force_delete_bulk_with_created_before_filter(): void
    {
        $oldDate = new DateTimeVO(Carbon::getTestNow()->subDays(30)->format('Y-m-d\TH:i:sP'));

        $oldToken = $this->service->create($this->hydration->hydrate(NemesisTokenRecord::class, [
            'token_hash' => $this->generateUniqueHash(),
            'name' => 'Old Token',
        ]), $this->user);

        NemesisToken::where('id', $oldToken->id)->update(['created_at' => Carbon::getTestNow()->subDays(31)]);

        $this->createBasicToken();

        $filters = $this->hydration->hydrate(NemesisTokenFilterRecord::class, ['created_before' => $oldDate]);

        $deletedCount = $this->service->forceDeleteBulk($filters);

        $this->assertSame(1, $deletedCount);
        $this->assertDatabaseMissing('nemesis_tokens', ['id' => $oldToken->id]);
    }

    // ============================================================================
    // Tokenable Operations (Bulk)
    // ============================================================================

    public function test_get_tokens_for_returns_all_tokens(): void
    {
        $this->createBasicToken();
        $this->createBasicToken();

        $tokens = $this->service->getTokensFor($this->user);

        $this->assertCount(2, $tokens);
    }

    public function test_get_tokens_for_with_trashed_includes_deleted(): void
    {
        $token = $this->createBasicToken();
        $this->service->delete($token->id);

        $tokens = $this->service->getTokensFor($this->user, withTrashed: true);

        $this->assertCount(1, $tokens);
        $this->assertNotNull($tokens->first()->deleted_at);
    }

    public function test_get_tokens_by_source_returns_filtered_tokens(): void
    {
        $this->service->create($this->hydration->hydrate(NemesisTokenRecord::class, [
            'token_hash' => $this->generateUniqueHash(),
            'source' => 'web',
        ]), $this->user);
        $this->service->create($this->hydration->hydrate(NemesisTokenRecord::class, [
            'token_hash' => $this->generateUniqueHash(),
            'source' => 'web',
        ]), $this->user);
        $this->service->create($this->hydration->hydrate(NemesisTokenRecord::class, [
            'token_hash' => $this->generateUniqueHash(),
            'source' => 'mobile',
        ]), $this->user);

        $webTokens = $this->service->getTokensBySource($this->user, 'web');
        $mobileTokens = $this->service->getTokensBySource($this->user, 'mobile');

        $this->assertCount(2, $webTokens);
        $this->assertCount(1, $mobileTokens);
    }

    public function test_get_tokens_by_name_returns_filtered_tokens(): void
    {
        $this->service->create($this->hydration->hydrate(NemesisTokenRecord::class, [
            'token_hash' => $this->generateUniqueHash(),
            'name' => 'Admin Token',
        ]), $this->user);
        $this->service->create($this->hydration->hydrate(NemesisTokenRecord::class, [
            'token_hash' => $this->generateUniqueHash(),
            'name' => 'Admin Token',
        ]), $this->user);
        $this->service->create($this->hydration->hydrate(NemesisTokenRecord::class, [
            'token_hash' => $this->generateUniqueHash(),
            'name' => 'User Token',
        ]), $this->user);

        $adminTokens = $this->service->getTokensByName($this->user, 'Admin Token');

        $this->assertCount(2, $adminTokens);
    }

    public function test_has_tokens_returns_true_when_tokens_exist(): void
    {
        $this->createBasicToken();

        $hasTokens = $this->service->hasTokens($this->user);

        $this->assertTrue($hasTokens);
    }

    public function test_has_tokens_with_trashed_returns_true_when_only_deleted_tokens_exist(): void
    {
        $token = $this->createBasicToken();
        $this->service->delete($token->id);

        $hasTokens = $this->service->hasTokens($this->user, withTrashed: true);

        $this->assertTrue($hasTokens);
    }

    public function test_has_tokens_returns_false_when_no_tokens(): void
    {
        $hasTokens = $this->service->hasTokens($this->user);

        $this->assertFalse($hasTokens);
    }

    public function test_delete_all_tokens_deletes_all_tokens(): void
    {
        $this->createBasicToken();
        $this->createBasicToken();

        $deletedCount = $this->service->deleteAllTokens($this->user);

        $this->assertSame(2, $deletedCount);
        $this->assertCount(0, $this->service->getTokensFor($this->user));
    }

    public function test_delete_all_tokens_with_force_permanently_deletes(): void
    {
        $token = $this->createBasicToken();

        $deletedCount = $this->service->deleteAllTokens($this->user, force: true);

        $this->assertSame(1, $deletedCount);
        $this->assertDatabaseMissing('nemesis_tokens', ['id' => $token->id]);
    }

    public function test_revoke_all_tokens_soft_deletes_all_tokens(): void
    {
        $token1 = $this->createBasicToken();
        $token2 = $this->createBasicToken();

        $revokedCount = $this->service->revokeAllTokens($this->user);

        $this->assertSame(2, $revokedCount);
        $this->assertNull($this->service->find($token1->id));
        $this->assertNull($this->service->find($token2->id));
        $this->assertDatabaseHas('nemesis_tokens', ['id' => $token1->id]);
        $this->assertDatabaseHas('nemesis_tokens', ['id' => $token2->id]);
    }

    public function test_restore_all_tokens_restores_all_deleted_tokens(): void
    {
        $token1 = $this->createBasicToken();
        $token2 = $this->createBasicToken();
        $this->service->delete($token1->id);
        $this->service->delete($token2->id);

        $restoredCount = $this->service->restoreAllTokens($this->user);

        $this->assertSame(2, $restoredCount);
        $this->assertNotNull($this->service->find($token1->id));
        $this->assertNotNull($this->service->find($token2->id));
    }

    public function test_revoke_tokens_by_source_soft_deletes_matching_tokens(): void
    {
        $this->service->create($this->hydration->hydrate(NemesisTokenRecord::class, [
            'token_hash' => $this->generateUniqueHash(),
            'source' => 'web',
        ]), $this->user);
        $this->service->create($this->hydration->hydrate(NemesisTokenRecord::class, [
            'token_hash' => $this->generateUniqueHash(),
            'source' => 'web',
        ]), $this->user);
        $webToken = $this->service->create($this->hydration->hydrate(NemesisTokenRecord::class, [
            'token_hash' => $this->generateUniqueHash(),
            'source' => 'web',
        ]), $this->user);
        $this->service->create($this->hydration->hydrate(NemesisTokenRecord::class, [
            'token_hash' => $this->generateUniqueHash(),
            'source' => 'mobile',
        ]), $this->user);

        $revokedCount = $this->service->revokeTokensBySource($this->user, 'web');

        $this->assertSame(3, $revokedCount);
        $this->assertNull($this->service->find($webToken->id));
    }

    public function test_revoke_tokens_by_source_with_force_permanently_deletes(): void
    {
        $webToken = $this->service->create($this->hydration->hydrate(NemesisTokenRecord::class, [
            'token_hash' => $this->generateUniqueHash(),
            'source' => 'web',
        ]), $this->user);

        $revokedCount = $this->service->revokeTokensBySource($this->user, 'web', force: true);

        $this->assertSame(1, $revokedCount);
        $this->assertDatabaseMissing('nemesis_tokens', ['id' => $webToken->id]);
    }

    public function test_revoke_tokens_by_name_soft_deletes_matching_tokens(): void
    {
        $this->service->create($this->hydration->hydrate(NemesisTokenRecord::class, [
            'token_hash' => $this->generateUniqueHash(),
            'name' => 'Admin',
        ]), $this->user);
        $this->service->create($this->hydration->hydrate(NemesisTokenRecord::class, [
            'token_hash' => $this->generateUniqueHash(),
            'name' => 'Admin',
        ]), $this->user);
        $adminToken = $this->service->create($this->hydration->hydrate(NemesisTokenRecord::class, [
            'token_hash' => $this->generateUniqueHash(),
            'name' => 'Admin',
        ]), $this->user);
        $this->service->create($this->hydration->hydrate(NemesisTokenRecord::class, [
            'token_hash' => $this->generateUniqueHash(),
            'name' => 'User',
        ]), $this->user);

        $revokedCount = $this->service->revokeTokensByName($this->user, 'Admin');

        $this->assertSame(3, $revokedCount);
        $this->assertNull($this->service->find($adminToken->id));
    }

    public function test_revoke_tokens_by_source_and_name_soft_deletes_matching_tokens(): void
    {
        $this->service->create($this->hydration->hydrate(NemesisTokenRecord::class, [
            'token_hash' => $this->generateUniqueHash(),
            'source' => 'web',
            'name' => 'Admin',
        ]), $this->user);
        $this->service->create($this->hydration->hydrate(NemesisTokenRecord::class, [
            'token_hash' => $this->generateUniqueHash(),
            'source' => 'web',
            'name' => 'Admin',
        ]), $this->user);
        $this->service->create($this->hydration->hydrate(NemesisTokenRecord::class, [
            'token_hash' => $this->generateUniqueHash(),
            'source' => 'web',
            'name' => 'User',
        ]), $this->user);
        $this->service->create($this->hydration->hydrate(NemesisTokenRecord::class, [
            'token_hash' => $this->generateUniqueHash(),
            'source' => 'mobile',
            'name' => 'Admin',
        ]), $this->user);

        $revokedCount = $this->service->revokeTokensBySourceAndName($this->user, 'web', 'Admin');

        $this->assertSame(2, $revokedCount);
    }

    public function test_revoke_all_tokens_except_source_keeps_specified_source(): void
    {
        $this->service->create($this->hydration->hydrate(NemesisTokenRecord::class, [
            'token_hash' => $this->generateUniqueHash(),
            'source' => 'web',
        ]), $this->user);
        $this->service->create($this->hydration->hydrate(NemesisTokenRecord::class, [
            'token_hash' => $this->generateUniqueHash(),
            'source' => 'web',
        ]), $this->user);
        $mobileToken = $this->service->create($this->hydration->hydrate(NemesisTokenRecord::class, [
            'token_hash' => $this->generateUniqueHash(),
            'source' => 'mobile',
        ]), $this->user);

        $revokedCount = $this->service->revokeAllTokensExceptSource($this->user, 'mobile');

        $this->assertSame(2, $revokedCount);
        $this->assertNotNull($this->service->find($mobileToken->id));
    }

    public function test_revoke_all_tokens_except_source_with_force_permanently_deletes(): void
    {
        $webToken = $this->service->create($this->hydration->hydrate(NemesisTokenRecord::class, [
            'token_hash' => $this->generateUniqueHash(),
            'source' => 'web',
        ]), $this->user);
        $mobileToken = $this->service->create($this->hydration->hydrate(NemesisTokenRecord::class, [
            'token_hash' => $this->generateUniqueHash(),
            'source' => 'mobile',
        ]), $this->user);

        $revokedCount = $this->service->revokeAllTokensExceptSource($this->user, 'mobile', force: true);

        $this->assertSame(1, $revokedCount);
        $this->assertDatabaseMissing('nemesis_tokens', ['id' => $webToken->id]);
        $this->assertDatabaseHas('nemesis_tokens', ['id' => $mobileToken->id]);
    }

    // ============================================================================
    // Current Token Operations Tests
    // ============================================================================

    public function test_get_current_token_returns_token_from_request(): void
    {
        $plainToken = 'my-secret-token-' . uniqid();
        $token = $this->createTokenWithHash($plainToken);

        $this->withBearerToken($plainToken);

        $request = $this->app->make(Request::class);
        $currentToken = $this->service->getCurrentToken($this->user, $request);

        $this->assertNotNull($currentToken);
        $this->assertSame($token->id, $currentToken->id);
    }

    public function test_revoke_current_token_soft_deletes_current_token(): void
    {
        $plainToken = 'my-secret-token-' . uniqid();
        $token = $this->createTokenWithHash($plainToken);

        $this->withBearerToken($plainToken);

        $request = $this->app->make(Request::class);
        $revoked = $this->service->revokeCurrentToken($this->user, $request);

        $this->assertTrue($revoked);
        $this->assertNull($this->service->find($token->id));
        $this->assertDatabaseHas('nemesis_tokens', ['id' => $token->id]);
    }

    public function test_delete_current_token_permanently_deletes_current_token(): void
    {
        $plainToken = 'my-secret-token-' . uniqid();
        $token = $this->createTokenWithHash($plainToken);

        $this->withBearerToken($plainToken);

        $request = $this->app->make(Request::class);
        $deleted = $this->service->deleteCurrentToken($this->user, $request);

        $this->assertTrue($deleted);
        $this->assertDatabaseMissing('nemesis_tokens', ['id' => $token->id]);
    }

    // ============================================================================
    // Token Validation Tests
    // ============================================================================

    public function test_validate_token_returns_true_for_valid_token(): void
    {
        $plainToken = 'valid-token-' . uniqid();
        $this->createTokenWithHash($plainToken);

        $isValid = $this->service->validateToken($plainToken, $this->user);

        $this->assertTrue($isValid);
    }

    public function test_validate_token_returns_false_for_invalid_token(): void
    {
        $isValid = $this->service->validateToken('invalid-token', $this->user);

        $this->assertFalse($isValid);
    }

    public function test_validate_token_with_include_revoked_returns_true_for_revoked_token(): void
    {
        $plainToken = 'revoked-token-' . uniqid();
        $token = $this->createTokenWithHash($plainToken);
        $this->service->delete($token->id);

        $isValid = $this->service->validateToken($plainToken, $this->user, includeRevoked: true);

        $this->assertTrue($isValid);
    }

    public function test_get_token_by_plain_text_returns_token(): void
    {
        $plainToken = 'findable-token-' . uniqid();
        $token = $this->createTokenWithHash($plainToken);

        $found = $this->service->getTokenByPlainText($plainToken, $this->user);

        $this->assertNotNull($found);
        $this->assertSame($token->id, $found->id);
    }

    public function test_get_token_by_plain_text_with_trashed_returns_deleted_token(): void
    {
        $plainToken = 'deleted-token-' . uniqid();
        $token = $this->createTokenWithHash($plainToken);
        $this->service->delete($token->id);

        $found = $this->service->getTokenByPlainText($plainToken, $this->user, withTrashed: true);

        $this->assertNotNull($found);
        $this->assertSame($token->id, $found->id);
        $this->assertNotNull($found->deleted_at);
    }

    public function test_touch_token_updates_last_used_at(): void
    {
        $plainToken = 'touchable-token-' . uniqid();
        $token = $this->createTokenWithHash($plainToken);
        $this->assertNull($token->last_used_at);

        $touched = $this->service->touchToken($plainToken, $this->user);

        $this->assertTrue($touched);

        $updatedToken = $this->service->find($token->id);
        $this->assertNotNull($updatedToken->last_used_at);
    }

    // ============================================================================
    // Expired Tokens Management Tests
    // ============================================================================

    public function test_revoke_expired_tokens_soft_deletes_expired_tokens(): void
    {
        $futureDate = new DateTimeVO(Carbon::getTestNow()->addDay()->format('Y-m-d\TH:i:sP'));
        $expiredDate = new DateTimeVO(Carbon::getTestNow()->subDay()->format('Y-m-d\TH:i:sP'));

        $this->service->create($this->hydration->hydrate(NemesisTokenRecord::class, [
            'token_hash' => $this->generateUniqueHash(),
            'expires_at' => $futureDate,
        ]), $this->user);
        $expiredToken = $this->service->create($this->hydration->hydrate(NemesisTokenRecord::class, [
            'token_hash' => $this->generateUniqueHash(),
            'expires_at' => $expiredDate,
        ]), $this->user);

        $revokedCount = $this->service->revokeExpiredTokens($this->user);

        $this->assertSame(1, $revokedCount);
        $this->assertNull($this->service->find($expiredToken->id));
        $this->assertDatabaseHas('nemesis_tokens', ['id' => $expiredToken->id]);
    }

    public function test_force_delete_expired_tokens_permanently_deletes_expired_tokens(): void
    {
        $futureDate = new DateTimeVO(Carbon::getTestNow()->addDay()->format('Y-m-d\TH:i:sP'));
        $expiredDate = new DateTimeVO(Carbon::getTestNow()->subDay()->format('Y-m-d\TH:i:sP'));

        $this->service->create($this->hydration->hydrate(NemesisTokenRecord::class, [
            'token_hash' => $this->generateUniqueHash(),
            'expires_at' => $futureDate,
        ]), $this->user);
        $expiredToken = $this->service->create($this->hydration->hydrate(NemesisTokenRecord::class, [
            'token_hash' => $this->generateUniqueHash(),
            'expires_at' => $expiredDate,
        ]), $this->user);

        $deletedCount = $this->service->forceDeleteExpiredTokens($this->user);

        $this->assertSame(1, $deletedCount);
        $this->assertDatabaseMissing('nemesis_tokens', ['id' => $expiredToken->id]);
    }

    // ============================================================================
    // Token Lifecycle Operations Tests
    // ============================================================================

    public function test_update_last_used_updates_timestamp(): void
    {
        $token = $this->createBasicToken();
        $this->assertNull($token->last_used_at);

        $updated = $this->service->updateLastUsed($token);

        $this->assertNotNull($updated->last_used_at);
    }

    public function test_revoke_soft_deletes_token(): void
    {
        $token = $this->createBasicToken();

        $revoked = $this->service->revoke($token);

        $this->assertTrue($revoked);
        $this->assertNull($this->service->find($token->id));
        $this->assertDatabaseHas('nemesis_tokens', ['id' => $token->id]);
    }

    public function test_restore_token_recovers_deleted_token(): void
    {
        $token = $this->createBasicToken();
        $this->service->revoke($token);

        $restored = $this->service->restoreToken($token);

        $this->assertTrue($restored);
        $this->assertNotNull($this->service->find($token->id));
    }

    // ============================================================================
    // Global Operations Tests
    // ============================================================================

    public function test_find_all_active_returns_only_active_tokens(): void
    {
        $this->createBasicToken();

        $expiredDate = new DateTimeVO(Carbon::getTestNow()->subDay()->format('Y-m-d\TH:i:sP'));
        $this->service->create($this->hydration->hydrate(NemesisTokenRecord::class, [
            'token_hash' => $this->generateUniqueHash(),
            'expires_at' => $expiredDate,
        ]), $this->user);

        $activeTokens = $this->service->findAllActive();

        $this->assertCount(1, $activeTokens);
    }

    public function test_find_all_expired_returns_only_expired_tokens(): void
    {
        $expiredDate = new DateTimeVO(Carbon::getTestNow()->subDay()->format('Y-m-d\TH:i:sP'));
        $this->service->create($this->hydration->hydrate(NemesisTokenRecord::class, [
            'token_hash' => $this->generateUniqueHash(),
            'expires_at' => $expiredDate,
        ]), $this->user);
        $this->service->create($this->hydration->hydrate(NemesisTokenRecord::class, [
            'token_hash' => $this->generateUniqueHash(),
            'expires_at' => $expiredDate,
        ]), $this->user);
        $this->createBasicToken();

        $expiredTokens = $this->service->findAllExpired();

        $this->assertCount(2, $expiredTokens);
    }

    public function test_find_all_revoked_returns_only_revoked_tokens(): void
    {
        $token = $this->createBasicToken();
        $this->service->delete($token->id);
        $this->createBasicToken();

        $revokedTokens = $this->service->findAllRevoked();

        $this->assertCount(1, $revokedTokens);
    }

    public function test_revoke_all_expired_tokens_globally_soft_deletes_all_expired_tokens(): void
    {
        $expiredDate = new DateTimeVO(Carbon::getTestNow()->subDay()->format('Y-m-d\TH:i:sP'));
        $this->service->create($this->hydration->hydrate(NemesisTokenRecord::class, [
            'token_hash' => $this->generateUniqueHash(),
            'expires_at' => $expiredDate,
        ]), $this->user);
        $this->service->create($this->hydration->hydrate(NemesisTokenRecord::class, [
            'token_hash' => $this->generateUniqueHash(),
            'expires_at' => $expiredDate,
        ]), $this->user);
        $this->createBasicToken();

        $revokedCount = $this->service->revokeAllExpiredTokensGlobally();

        $this->assertSame(2, $revokedCount);
    }

    public function test_force_delete_all_expired_tokens_globally_permanently_deletes(): void
    {
        $expiredDate = new DateTimeVO(Carbon::getTestNow()->subDay()->format('Y-m-d\TH:i:sP'));
        $expiredToken = $this->service->create($this->hydration->hydrate(NemesisTokenRecord::class, [
            'token_hash' => $this->generateUniqueHash(),
            'expires_at' => $expiredDate,
        ]), $this->user);
        $this->createBasicToken();

        $deletedCount = $this->service->forceDeleteAllExpiredTokensGlobally();

        $this->assertSame(1, $deletedCount);
        $this->assertDatabaseMissing('nemesis_tokens', ['id' => $expiredToken->id]);
    }

    // ============================================================================
    // Token Capabilities Tests
    // ============================================================================

    public function test_can_returns_true_when_token_has_ability(): void
    {
        $abilities = new StringTypedCollection();
        $abilities->add('read');
        $abilities->add('write');

        $record = $this->hydration->hydrate(NemesisTokenRecord::class, [
            'token_hash' => $this->generateUniqueHash(),
            'abilities' => $abilities,
        ]);
        $token = $this->service->create($record, $this->user);

        $this->assertTrue($this->service->can($token, 'read'));
        $this->assertTrue($this->service->can($token, 'write'));
        $this->assertFalse($this->service->can($token, 'delete'));
    }

    public function test_can_returns_true_when_abilities_are_null_unrestricted(): void
    {
        $record = $this->hydration->hydrate(NemesisTokenRecord::class, [
            'token_hash' => $this->generateUniqueHash(),
            'abilities' => null,
        ]);
        $token = $this->service->create($record, $this->user);

        $this->assertTrue($this->service->can($token, 'anything'));
        $this->assertTrue($this->service->can($token, 'foo'));
        $this->assertTrue($this->service->can($token, 'bar'));
    }

    public function test_can_all_returns_true_when_token_has_all_abilities(): void
    {
        $abilities = new StringTypedCollection();
        $abilities->add('read');
        $abilities->add('write');
        $abilities->add('delete');

        $record = $this->hydration->hydrate(NemesisTokenRecord::class, [
            'token_hash' => $this->generateUniqueHash(),
            'abilities' => $abilities,
        ]);
        $token = $this->service->create($record, $this->user);

        $this->assertTrue($this->service->canAll($token, ['read', 'write']));
        $this->assertTrue($this->service->canAll($token, ['read', 'write', 'delete']));
    }

    public function test_can_all_returns_false_when_token_misses_any_ability(): void
    {
        $abilities = new StringTypedCollection();
        $abilities->add('read');
        $abilities->add('write');

        $record = $this->hydration->hydrate(NemesisTokenRecord::class, [
            'token_hash' => $this->generateUniqueHash(),
            'abilities' => $abilities,
        ]);
        $token = $this->service->create($record, $this->user);

        $this->assertFalse($this->service->canAll($token, ['read', 'delete']));
        $this->assertFalse($this->service->canAll($token, ['write', 'delete']));
    }

    // ============================================================================
    // Allowed Origins Management Tests
    // ============================================================================

    public function test_add_allowed_origin_appends_origin(): void
    {
        $origins = new StringTypedCollection();
        $origins->add('https://example.com');

        $record = $this->hydration->hydrate(NemesisTokenRecord::class, [
            'token_hash' => $this->generateUniqueHash(),
            'allowed_origins' => $origins,
            'name' => 'Origin Test Token',
            'source' => 'web',
        ]);
        $token = $this->service->create($record, $this->user);

        $updated = $this->service->addAllowedOrigin($token, 'https://api.example.com');

        $this->assertContains('https://api.example.com', $updated->allowed_origins);
        $this->assertContains('https://example.com', $updated->allowed_origins);
        $this->assertCount(2, $updated->allowed_origins);
    }

    public function test_add_allowed_origin_does_not_duplicate(): void
    {
        $origins = new StringTypedCollection();
        $origins->add('https://example.com');

        $record = $this->hydration->hydrate(NemesisTokenRecord::class, [
            'token_hash' => $this->generateUniqueHash(),
            'allowed_origins' => $origins,
        ]);
        $token = $this->service->create($record, $this->user);

        $updated = $this->service->addAllowedOrigin($token, 'https://example.com');

        $this->assertCount(1, $updated->allowed_origins);
    }

    public function test_remove_allowed_origin_removes_origin(): void
    {
        $origins = new StringTypedCollection();
        $origins->add('https://example.com');
        $origins->add('https://api.example.com');

        $record = $this->hydration->hydrate(NemesisTokenRecord::class, [
            'token_hash' => $this->generateUniqueHash(),
            'allowed_origins' => $origins,
        ]);
        $token = $this->service->create($record, $this->user);

        $updated = $this->service->removeAllowedOrigin($token, 'https://api.example.com');

        $this->assertNotContains('https://api.example.com', $updated->allowed_origins);
        $this->assertContains('https://example.com', $updated->allowed_origins);
        $this->assertCount(1, $updated->allowed_origins);
    }

    public function test_set_allowed_origins_replaces_all_origins(): void
    {
        $origins = new StringTypedCollection();
        $origins->add('https://old.com');

        $record = $this->hydration->hydrate(NemesisTokenRecord::class, [
            'token_hash' => $this->generateUniqueHash(),
            'allowed_origins' => $origins,
        ]);
        $token = $this->service->create($record, $this->user);

        $updated = $this->service->setAllowedOrigins($token, ['https://new1.com', 'https://new2.com']);

        $this->assertNotContains('https://old.com', $updated->allowed_origins);
        $this->assertContains('https://new1.com', $updated->allowed_origins);
        $this->assertContains('https://new2.com', $updated->allowed_origins);
        $this->assertCount(2, $updated->allowed_origins);
    }

    // ============================================================================
    // Metadata Management Tests
    // ============================================================================

    public function test_set_and_get_metadata(): void
    {
        $token = $this->createBasicToken();

        $updated = $this->service->setMetadata($token, 'user_agent', 'Mozilla/5.0');

        $this->assertSame('Mozilla/5.0', $updated->metadata['user_agent']);
        $this->assertSame('Mozilla/5.0', $this->service->getMetadata($updated, 'user_agent'));
    }

    public function test_get_metadata_returns_default_when_key_not_found(): void
    {
        $token = $this->createBasicToken();

        $value = $this->service->getMetadata($token, 'nonexistent', 'default_value');

        $this->assertSame('default_value', $value);
    }

    public function test_has_metadata_checks_key_existence(): void
    {
        $metadata = new StrictDataObject(['existing' => 'value']);
        $record = $this->hydration->hydrate(NemesisTokenRecord::class, [
            'token_hash' => $this->generateUniqueHash(),
            'metadata' => $metadata,
        ]);
        $token = $this->service->create($record, $this->user);

        $this->assertTrue($this->service->hasMetadata($token, 'existing'));
        $this->assertFalse($this->service->hasMetadata($token, 'nonexistent'));
    }

    public function test_remove_metadata_deletes_key(): void
    {
        $metadata = new StrictDataObject(['key1' => 'value1', 'key2' => 'value2']);
        $record = $this->hydration->hydrate(NemesisTokenRecord::class, [
            'token_hash' => $this->generateUniqueHash(),
            'metadata' => $metadata,
        ]);
        $token = $this->service->create($record, $this->user);

        $updated = $this->service->removeMetadata($token, 'key1');

        $this->assertNull($updated->metadata['key1'] ?? null);
        $this->assertSame('value2', $updated->metadata['key2']);
    }

    public function test_merge_metadata_merges_with_existing(): void
    {
        $metadata = new StrictDataObject(['key1' => 'value1', 'key2' => 'value2']);
        $record = $this->hydration->hydrate(NemesisTokenRecord::class, [
            'token_hash' => $this->generateUniqueHash(),
            'metadata' => $metadata,
        ]);
        $token = $this->service->create($record, $this->user);

        $updated = $this->service->mergeMetadata($token, ['key2' => 'new_value2', 'key3' => 'value3']);

        $this->assertSame('value1', $updated->metadata['key1']);
        $this->assertSame('new_value2', $updated->metadata['key2']);
        $this->assertSame('value3', $updated->metadata['key3']);
    }

    public function test_set_all_metadata_replaces_all_metadata(): void
    {
        $metadata = new StrictDataObject(['old' => 'value']);
        $record = $this->hydration->hydrate(NemesisTokenRecord::class, [
            'token_hash' => $this->generateUniqueHash(),
            'metadata' => $metadata,
        ]);
        $token = $this->service->create($record, $this->user);

        $updated = $this->service->setAllMetadata($token, ['new1' => 'val1', 'new2' => 'val2']);

        $this->assertNull($updated->metadata['old'] ?? null);
        $this->assertSame('val1', $updated->metadata['new1']);
        $this->assertSame('val2', $updated->metadata['new2']);
    }

    public function test_clear_metadata_removes_all_metadata(): void
    {
        $metadata = new StrictDataObject(['key1' => 'value1', 'key2' => 'value2']);
        $record = $this->hydration->hydrate(NemesisTokenRecord::class, [
            'token_hash' => $this->generateUniqueHash(),
            'metadata' => $metadata,
        ]);
        $token = $this->service->create($record, $this->user);

        $updated = $this->service->clearMetadata($token);

        $this->assertNull($updated->metadata);
    }

    // ============================================================================
    // Allowed Origins Management (CORS)
    // ============================================================================

    public function test_can_use_from_origin_allows_null_origin(): void
    {
        $record = $this->hydration->hydrate(NemesisTokenRecord::class, [
            'token_hash' => $this->generateUniqueHash(),
            'allowed_origins' => ['https://example.com'],
        ]);
        $token = $this->service->create($record, $this->user);

        $this->assertTrue($this->service->canUseFromOrigin($token, null));
    }

    public function test_can_use_from_origin_allows_when_no_restrictions(): void
    {
        $record = $this->hydration->hydrate(NemesisTokenRecord::class, [
            'token_hash' => $this->generateUniqueHash(),
            'allowed_origins' => null,
        ]);
        $token = $this->service->create($record, $this->user);

        $this->assertTrue($this->service->canUseFromOrigin($token, 'https://anywhere.com'));
    }

    public function test_can_use_from_origin_allows_exact_match(): void
    {
        $origins = new StringTypedCollection();
        $origins->add('https://example.com');
        $origins->add('https://api.example.com');

        $record = $this->hydration->hydrate(NemesisTokenRecord::class, [
            'token_hash' => $this->generateUniqueHash(),
            'allowed_origins' => $origins,
        ]);
        $token = $this->service->create($record, $this->user);

        $this->assertTrue($this->service->canUseFromOrigin($token, 'https://example.com'));
        $this->assertTrue($this->service->canUseFromOrigin($token, 'https://api.example.com'));
    }

    public function test_can_use_from_origin_allows_wildcard_match(): void
    {
        $origins = new StringTypedCollection();
        $origins->add('https://*.example.com');
        $origins->add('https://*.api.com');

        $record = $this->hydration->hydrate(NemesisTokenRecord::class, [
            'token_hash' => $this->generateUniqueHash(),
            'allowed_origins' => $origins,
        ]);
        $token = $this->service->create($record, $this->user);

        $this->assertTrue($this->service->canUseFromOrigin($token, 'https://sub.example.com'));
        $this->assertTrue($this->service->canUseFromOrigin($token, 'https://deep.sub.example.com'));
        $this->assertTrue($this->service->canUseFromOrigin($token, 'https://v1.api.com'));
        $this->assertFalse($this->service->canUseFromOrigin($token, 'https://other.com'));
    }

    public function test_can_use_from_origin_denies_non_matching_origin(): void
    {
        $origins = new StringTypedCollection();
        $origins->add('https://example.com');

        $record = $this->hydration->hydrate(NemesisTokenRecord::class, [
            'token_hash' => $this->generateUniqueHash(),
            'allowed_origins' => $origins,
        ]);
        $token = $this->service->create($record, $this->user);

        $this->assertFalse($this->service->canUseFromOrigin($token, 'https://evil.com'));
        $this->assertFalse($this->service->canUseFromOrigin($token, 'http://example.com'));
    }

    public function test_can_use_from_current_request_checks_origin(): void
    {
        $origins = new StringTypedCollection();
        $origins->add('https://allowed.com');

        $record = $this->hydration->hydrate(NemesisTokenRecord::class, [
            'token_hash' => $this->generateUniqueHash(),
            'allowed_origins' => $origins,
        ]);
        $token = $this->service->create($record, $this->user);

        $request = $this->app->make(Request::class);
        $request->headers->set('Origin', 'https://allowed.com');
        $this->assertTrue($this->service->canUseFromCurrentRequest($token, $request));

        $request->headers->set('Origin', 'https://evil.com');
        $this->assertFalse($this->service->canUseFromCurrentRequest($token, $request));
    }

    // ============================================================================
    // Force Expire By Minutes Tests
    // ============================================================================

    public function test_force_expire_by_minutes_sets_expiration_to_past(): void
    {
        $minutes = 30;
        $record = $this->hydration->hydrate(NemesisTokenRecord::class, [
            'token_hash' => $this->generateUniqueHash(),
        ]);
        $token = $this->service->create($record, $this->user);

        $futureDate = new DateTimeVO(Carbon::getTestNow()->addMinutes($minutes)->format('Y-m-d\TH:i:sP'));
        $this->service->update($token->id, $this->hydration->hydrate(NemesisTokenRecord::class, ['expires_at' => $futureDate]));

        $token->refresh();
        $this->assertFalse($token->isExpired());

        $expired = $this->service->forceExpireByMinutes($token, $minutes);

        $this->assertTrue($expired->isExpired());
    }

    // ============================================================================
    // Query Methods Tests
    // ============================================================================

    public function test_find_by_filters_returns_filtered_tokens(): void
    {
        $this->service->create($this->hydration->hydrate(NemesisTokenRecord::class, [
            'token_hash' => $this->generateUniqueHash(),
            'source' => 'web',
            'name' => 'Token 1',
        ]), $this->user);
        $this->service->create($this->hydration->hydrate(NemesisTokenRecord::class, [
            'token_hash' => $this->generateUniqueHash(),
            'source' => 'web',
            'name' => 'Token 2',
        ]), $this->user);
        $this->service->create($this->hydration->hydrate(NemesisTokenRecord::class, [
            'token_hash' => $this->generateUniqueHash(),
            'source' => 'mobile',
            'name' => 'Token 3',
        ]), $this->user);

        $filters = $this->hydration->hydrate(NemesisTokenFilterRecord::class, ['source' => 'web']);
        $tokens = $this->service->findByFilters($filters);

        $this->assertCount(2, $tokens);
    }

    public function test_find_by_filters_with_limit_limits_results(): void
    {
        $this->service->create($this->hydration->hydrate(NemesisTokenRecord::class, [
            'token_hash' => $this->generateUniqueHash(),
            'source' => 'web',
        ]), $this->user);
        $this->service->create($this->hydration->hydrate(NemesisTokenRecord::class, [
            'token_hash' => $this->generateUniqueHash(),
            'source' => 'web',
        ]), $this->user);

        $filters = $this->hydration->hydrate(NemesisTokenFilterRecord::class, ['source' => 'web']);
        $tokens = $this->service->findByFilters($filters, limit: 1);

        $this->assertCount(1, $tokens);
    }

    public function test_find_by_filters_with_sort_by_sorts_results(): void
    {
        $this->service->create($this->hydration->hydrate(NemesisTokenRecord::class, [
            'token_hash' => $this->generateUniqueHash(),
            'name' => 'B Token',
        ]), $this->user);
        $this->service->create($this->hydration->hydrate(NemesisTokenRecord::class, [
            'token_hash' => $this->generateUniqueHash(),
            'name' => 'A Token',
        ]), $this->user);

        $filters = $this->hydration->hydrate(NemesisTokenFilterRecord::class, []);
        $tokens = $this->service->findByFilters($filters, sortBy: 'name');

        $this->assertSame('A Token', $tokens->first()->name);
    }

    public function test_find_by_filters_with_columns_selects_specific_columns(): void
    {
        $this->service->create($this->hydration->hydrate(NemesisTokenRecord::class, [
            'token_hash' => $this->generateUniqueHash(),
            'name' => 'Test Token',
            'source' => 'web',
        ]), $this->user);

        $filters = $this->hydration->hydrate(NemesisTokenFilterRecord::class, []);
        $tokens = $this->service->findByFilters($filters, columns: ['id', 'name']);

        $token = $tokens->first();
        $this->assertNotNull($token->id);
        $this->assertSame('Test Token', $token->name);
        $this->assertNull($token->source);
    }

    public function test_exists_returns_true_when_tokens_match(): void
    {
        $this->createBasicToken();

        $filters = $this->hydration->hydrate(NemesisTokenFilterRecord::class, ['source' => 'web']);
        $exists = $this->service->exists($filters);

        $this->assertTrue($exists);
    }

    public function test_exists_returns_false_when_no_tokens_match(): void
    {
        $filters = $this->hydration->hydrate(NemesisTokenFilterRecord::class, ['source' => 'nonexistent']);
        $exists = $this->service->exists($filters);

        $this->assertFalse($exists);
    }

    public function test_count_returns_number_of_matching_tokens(): void
    {
        $this->service->create($this->hydration->hydrate(NemesisTokenRecord::class, [
            'token_hash' => $this->generateUniqueHash(),
            'source' => 'web',
        ]), $this->user);
        $this->service->create($this->hydration->hydrate(NemesisTokenRecord::class, [
            'token_hash' => $this->generateUniqueHash(),
            'source' => 'web',
        ]), $this->user);
        $this->service->create($this->hydration->hydrate(NemesisTokenRecord::class, [
            'token_hash' => $this->generateUniqueHash(),
            'source' => 'mobile',
        ]), $this->user);

        $filters = $this->hydration->hydrate(NemesisTokenFilterRecord::class, ['source' => 'web']);
        $count = $this->service->count($filters);

        $this->assertSame(2, $count);
    }
}
