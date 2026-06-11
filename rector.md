# Rector Refactoring Report
*Generated: jeu. 11 juin 2026 14:26:34 WAT*


26 files with changes
=====================

1) /home/andy-kani/pro/sites/packages/laravel-nemesis/src/Directives/CleanTokensDirective.php:189

    ---------- begin diff ----------
@@ Line 189 @@

         $expiredRow = new RowCollection;
         $expiredRow->add('Expired tokens deleted', (string) $statistics->expired);
+
         $rows->add($expiredRow);

         $oldRow = new RowCollection;
         $oldRow->add('Old tokens deleted', (string) $statistics->old);
+
         $rows->add($oldRow);

         $separatorRow = new RowCollection;
         $separatorRow->add('━━━━━━━━━━━━━━━━━━━━━', '━━━━━━━━━');
+
         $rows->add($separatorRow);

         $totalRow = new RowCollection;
         $totalRow->add('Total tokens deleted', (string) $statistics->total);
+
         $rows->add($totalRow);

         $this->table($headers, $rows);
    ----------- end diff -----------

Applied rules:
 * NewlineBeforeNewAssignSetRector


2) /home/andy-kani/pro/sites/packages/laravel-nemesis/src/Directives/InstallNemesisDirective.php:15

    ---------- begin diff ----------
@@ Line 15 @@
 use AndyDefer\DomainStructures\Collections\Utility\StringTypedCollection;
 use Illuminate\Contracts\Console\Kernel;
 use Illuminate\Contracts\Foundation\Application;
-use Illuminate\Database\Connection;
 use Illuminate\Database\DatabaseManager;
 use Kani\Nemesis\Contracts\Configs\NemesisConfigInterface;

@@ Line 82 @@
         $this->info("\n📦 Checking package files...");

         if (!$this->filesystem->exists($packageRoot)) {
-            $this->error("Package not found at: {$packageRoot}");
+            $this->error('Package not found at: ' . $packageRoot);
             $this->error('Please run: composer require andydefer/laravel-nemesis');
             return ExitCode::FAILURE;
         }
+
         $this->info("  ✓ Package found");

         // ========================================================================
@@ Line 97 @@
         $configDestination = $this->app->basePath('config/nemesis.php');

         if (!$this->filesystem->exists($configSource)) {
-            $this->error("Config source not found: {$configSource}");
+            $this->error('Config source not found: ' . $configSource);
             return ExitCode::FAILURE;
         }

@@ Line 119 @@
         $migrationDestination = $this->app->databasePath('migrations/2024_01_01_000001_create_nemesis_tokens_table.php');

         if (!$this->filesystem->exists($migrationSource)) {
-            $this->error("Migration source not found: {$migrationSource}");
+            $this->error('Migration source not found: ' . $migrationSource);
             return ExitCode::FAILURE;
         }

@@ Line 143 @@
             $this->error('Failed to run migrations.');
             return ExitCode::FAILURE;
         }
+
         $this->info('  ✓ Migrations executed');

         // ========================================================================
@@ Line 156 @@
             $this->error("Table 'nemesis_tokens' not found.");
             return ExitCode::FAILURE;
         }
+
         $this->info('  ✓ Table "nemesis_tokens" exists');

         // ========================================================================
    ----------- end diff -----------

Applied rules:
 * EncapsedStringsToSprintfRector
 * NewlineAfterStatementRector


3) /home/andy-kani/pro/sites/packages/laravel-nemesis/src/Directives/NemesisCleanDirective.php:15

    ---------- begin diff ----------
@@ Line 15 @@
 use AndyDefer\DomainStructures\Services\HydrationService;
 use AndyDefer\PhpVo\ValueObjects\DateTimeVO;
 use Kani\Nemesis\Contracts\Configs\NemesisConfigInterface;
-use Kani\Nemesis\Models\NemesisToken;
 use Kani\Nemesis\Records\NemesisTokenFilterRecord;
 use Kani\Nemesis\Repositories\NemesisTokenRepository;

@@ Line 167 @@
         return $configDays;
     }

+    /**
+     * @param array<string, mixed> $statistics
+     */
     private function displayResults(array $statistics): void
     {
         $this->newLine();
@@ Line 185 @@
         $this->displayConfigurationSummary();
     }

+    /**
+     * @param array<string, mixed> $statistics
+     */
     private function displayStatisticsTable(array $statistics): void
     {
         $headers = new StringTypedCollection;
@@ Line 194 @@

         $row1 = new RowCollection;
         $row1->add('Expired tokens deleted', (string) $statistics['expired']);
+
         $rows->add($row1);

         $row2 = new RowCollection;
         $row2->add('Old tokens deleted', (string) $statistics['old']);
+
         $rows->add($row2);

         $row3 = new RowCollection;
         $row3->add('━━━━━━━━━━━━━━━━━━━━━', '━━━━━━━━━');
+
         $rows->add($row3);

         $row4 = new RowCollection;
         $row4->add('Total tokens deleted', (string) $statistics['total']);
+
         $rows->add($row4);

         $this->table($headers, $rows);
    ----------- end diff -----------

Applied rules:
 * NewlineBeforeNewAssignSetRector
 * AddParamArrayDocblockFromDimFetchAccessRector


4) /home/andy-kani/pro/sites/packages/laravel-nemesis/src/Exceptions/MetadataValidationException.php:73

    ---------- begin diff ----------
@@ Line 73 @@
      */
     public function hasDetails(): bool
     {
-        return $this->details !== null;
+        return $this->details instanceof StrictDataObject;
     }

     /**
    ----------- end diff -----------

Applied rules:
 * FlipTypeControlToUseExclusiveTypeRector


5) /home/andy-kani/pro/sites/packages/laravel-nemesis/src/Helpers/NemesisHelper.php:59

    ---------- begin diff ----------
@@ Line 59 @@

     public function hasCurrentToken(): bool
     {
-        return $this->getCurrentToken() !== null;
+        return $this->getCurrentToken() instanceof NemesisTokenRecord;
     }

     public function hasCurrentAuthenticatable(): bool
     {
-        return $this->getCurrentAuthenticatable() !== null;
+        return $this->getCurrentAuthenticatable() instanceof Model;
     }
 }
    ----------- end diff -----------

Applied rules:
 * FlipTypeControlToUseExclusiveTypeRector


6) /home/andy-kani/pro/sites/packages/laravel-nemesis/src/Http/Middleware/NemesisTokenMiddleware.php:6

    ---------- begin diff ----------
@@ Line 6 @@

 namespace Kani\Nemesis\Http\Middleware;

+use AndyDefer\DomainStructures\Abstracts\AbstractRecord;
 use AndyDefer\Actions\Http\ResponseFactory;
 use AndyDefer\DomainStructures\Services\HydrationService;
 use Closure;
@@ Line 118 @@
             'currentNemesisToken' => $tokenRecord,
         ]);

-        if ($formattedAuthenticatable !== null) {
+        if ($formattedAuthenticatable instanceof AbstractRecord) {
             $formatKey = $parameterName . 'Format';
             $request->merge([
                 $formatKey => $formattedAuthenticatable,
    ----------- end diff -----------

Applied rules:
 * FlipTypeControlToUseExclusiveTypeRector


7) /home/andy-kani/pro/sites/packages/laravel-nemesis/src/NemesisServiceProvider.php:39

    ---------- begin diff ----------
@@ Line 39 @@
             'nemesis'
         );

-        $this->app->singleton(NemesisHelper::class, function (Application $app) {
+        $this->app->singleton(NemesisHelper::class, function (Application $app): NemesisHelper {
             return new NemesisHelper(
                 $app->make('request'),
                 $app->make(NemesisConfigInterface::class),
    ----------- end diff -----------

Applied rules:
 * ClosureReturnTypeRector


8) /home/andy-kani/pro/sites/packages/laravel-nemesis/src/Repositories/NemesisTokenRepository.php:6

    ---------- begin diff ----------
@@ Line 6 @@

 namespace Kani\Nemesis\Repositories;

+use AndyDefer\PhpVo\ValueObjects\DateTimeVO;
 use AndyDefer\DomainStructures\Abstracts\AbstractRecord;
 use AndyDefer\Repository\AbstractRepository;
 use Illuminate\Database\Eloquent\Builder;
@@ Line 96 @@
         if ($filters->is_expired === true) {
             $query->whereNotNull('expires_at')->where('expires_at', '<', now());
         } elseif ($filters->is_expired === false) {
-            $query->where(function ($q) {
+            $query->where(function ($q): void {
                 $q->whereNull('expires_at')->orWhere('expires_at', '>=', now());
             });
         }
@@ Line 107 @@
             $query->withoutTrashed();
         }

-        if ($filters->created_before !== null) {
+        if ($filters->created_before instanceof DateTimeVO) {
             $query->where('created_at', '<', $filters->created_before->toDateTimeString());
         }
     }
    ----------- end diff -----------

Applied rules:
 * FlipTypeControlToUseExclusiveTypeRector
 * AddClosureVoidReturnTypeWhereNoReturnRector


9) /home/andy-kani/pro/sites/packages/laravel-nemesis/src/Services/NemesisAuthenticationService.php:50

    ---------- begin diff ----------
@@ Line 50 @@
         // Find token in database
         $tokenModel = $this->findToken($token);

-        if ($tokenModel === null) {
+        if (!$tokenModel instanceof NemesisToken) {
             return $this->hydration->hydrate(AuthenticationResultVO::class, [
                 'success' => false,
                 'error_code' => ErrorCode::INVALID_TOKEN,
@@ Line 71 @@

         // Check origin restriction
         $originResult = $this->checkOriginRestriction($tokenModel, $request);
-        if ($originResult !== null) {
+        if ($originResult instanceof AuthenticationResultVO) {
             return $originResult;
         }
    ----------- end diff -----------

Applied rules:
 * FlipTypeControlToUseExclusiveTypeRector


10) /home/andy-kani/pro/sites/packages/laravel-nemesis/src/Services/NemesisService.php:51

    ---------- begin diff ----------
@@ Line 51 @@
         return $this->repository->create($fullRecord);
     }

+    /**
+     * @return array<int, Model|string>
+     */
     public function createWithPlainToken(NemesisTokenRecord $record, Model $tokenable): array
     {
         $tokenConfig = $this->config->tokenConfig();
@@ Line 296 @@
                 } else {
                     $this->repository->delete($token->id);
                 }
-                $count++;
+
+                ++$count;
             }
         }

@@ Line 334 @@
     {
         $token = $this->getCurrentToken($tokenable, $request);

-        if ($token === null) {
+        if (!$token instanceof NemesisToken) {
             return false;
         }

@@ Line 345 @@
     {
         $token = $this->getCurrentToken($tokenable, $request);

-        if ($token === null) {
+        if (!$token instanceof NemesisToken) {
             return false;
         }

@@ Line 412 @@
     {
         $token = $this->getTokenByPlainText($plainToken, $tokenable);

-        if ($token === null) {
+        if (!$token instanceof NemesisToken) {
             return false;
         }

@@ Line 528 @@
             return in_array($ability, $token->abilities);
         }

-        if (is_object($token->abilities) && $token->abilities instanceof StringTypedCollection) {
+        if ($token->abilities instanceof StringTypedCollection) {
             return $token->abilities->contains($ability);
         }

@@ Line 684 @@

     private function validateMetadata(?StrictDataObject $metadata): ?array
     {
-        if ($metadata === null) {
+        if (!$metadata instanceof StrictDataObject) {
             return null;
         }
    ----------- end diff -----------

Applied rules:
 * RemoveUselessIsObjectCheckRector
 * FlipTypeControlToUseExclusiveTypeRector
 * PostIncDecToPreIncDecRector
 * NewlineAfterStatementRector
 * DocblockReturnArrayFromDirectArrayInstanceRector


11) /home/andy-kani/pro/sites/packages/laravel-nemesis/src/ValueObjects/AuthenticationResultVO.php:38

    ---------- begin diff ----------
@@ Line 38 @@
     {
         if ($this->success) {
             // Success requires token record
-            if ($this->token_record === null) {
+            if (!$this->token_record instanceof NemesisTokenRecord) {
                 throw new InvalidArgumentException('Token record is required for successful authentication');
             }
-            if ($this->error_code !== null) {
+
+            if ($this->error_code instanceof ErrorCode) {
                 throw new InvalidArgumentException('Error code must be null for successful authentication');
             }
         } else {
             // Failure requires error code
-            if ($this->error_code === null) {
+            if (!$this->error_code instanceof ErrorCode) {
                 throw new InvalidArgumentException('Error code is required for failed authentication');
             }
-            if ($this->token_record !== null) {
+
+            if ($this->token_record instanceof NemesisTokenRecord) {
                 throw new InvalidArgumentException('Token record must be null for failed authentication');
             }
         }
    ----------- end diff -----------

Applied rules:
 * FlipTypeControlToUseExclusiveTypeRector
 * NewlineAfterStatementRector


12) /home/andy-kani/pro/sites/packages/laravel-nemesis/tests/Fixtures/Models/TestApiClient.php:8

    ---------- begin diff ----------
@@ Line 8 @@
 use Illuminate\Database\Eloquent\SoftDeletes;
 use Kani\Nemesis\Contracts\MustNemesis;
 use Kani\Nemesis\Tests\Fixtures\Records\TestApiClientRecord;
-use Kani\Nemesis\Traits\HasNemesisTokens;

 /**
  * Test model for API clients that can authenticate with tokens.
    ----------- end diff -----------

Applied rules:


13) /home/andy-kani/pro/sites/packages/laravel-nemesis/tests/Integration/Directives/CleanTokensDirectiveTest.php:6

    ---------- begin diff ----------
@@ Line 6 @@

 namespace Kani\Nemesis\Tests\Integration\Directives;

+use Carbon\CarbonImmutable;
 use AndyDefer\Directive\Enums\ExitCode;
 use AndyDefer\Directive\Services\DirectiveTestingService;
 use Carbon\Carbon;
@@ Line 17 @@
 final class CleanTokensDirectiveTest extends IntegrationTestCase
 {
     private DirectiveTestingService $service;
+
     private TestUser $user;

     protected function setUp(): void
@@ Line 42 @@
         return $this->app->make(CleanTokensDirective::class);
     }

+    /**
+     * @param array<string, string>|array<string, Carbon>|array<string, CarbonImmutable>|string[]|null[] $overrides
+     */
     private function createToken(array $overrides = []): NemesisToken
     {
         $data = array_merge([
@@ Line 101 @@
         $this->assertTrue($aliases->contains('tokens-clean'));
         $this->assertTrue($aliases->contains('token-clean'));
         $this->assertTrue($aliases->contains('clean-expired'));
-        $this->assertSame(3, $aliases->count());
+        $this->assertCount(3, $aliases);
     }

     public function test_should_boot_laravel_returns_true(): void
    ----------- end diff -----------

Applied rules:
 * NewlineBetweenClassLikeStmtsRector
 * ClassMethodArrayDocblockParamFromLocalCallsRector


14) /home/andy-kani/pro/sites/packages/laravel-nemesis/tests/Integration/Directives/ListTokensDirectiveTest.php:21

    ---------- begin diff ----------
@@ Line 21 @@
 final class ListTokensDirectiveTest extends IntegrationTestCase
 {
     private DirectiveTestingService $service;
+
     private TestUser $user;
+
     private TestApiClient $apiClient;
+
     private NemesisService $nemesisService;
+
     private HydrationService $hydration;

     protected function setUp(): void
@@ Line 54 @@
         parent::tearDown();
     }

+    /**
+     * @param array<string, string> $overrides
+     */
     private function createTokenForUser(TestUser $user, array $overrides = []): NemesisToken
     {
         $record = $this->hydration->hydrate(NemesisTokenRecord::class, array_merge([
@@ Line 65 @@
         return $this->nemesisService->create($record, $user);
     }

+    /**
+     * @param array<string, string> $overrides
+     */
     private function createTokenForApiClient(TestApiClient $apiClient, array $overrides = []): NemesisToken
     {
         $record = $this->hydration->hydrate(NemesisTokenRecord::class, array_merge([
@@ Line 76 @@
         return $this->nemesisService->create($record, $apiClient);
     }

+    /**
+     * @param array<string, string> $overrides
+     */
     private function createToken(array $overrides = []): NemesisToken
     {
         return $this->createTokenForUser($this->user, $overrides);
@@ Line 112 @@

         $this->assertTrue($aliases->contains('tokens-list'));
         $this->assertTrue($aliases->contains('nemesis-tokens'));
-        $this->assertSame(2, $aliases->count());
+        $this->assertCount(2, $aliases);
     }

     public function test_should_boot_laravel_returns_true(): void
    ----------- end diff -----------

Applied rules:
 * NewlineBetweenClassLikeStmtsRector
 * ClassMethodArrayDocblockParamFromLocalCallsRector


15) /home/andy-kani/pro/sites/packages/laravel-nemesis/tests/Integration/Directives/NemesisCleanDirectiveTest.php:17

    ---------- begin diff ----------
@@ Line 17 @@
 final class NemesisCleanDirectiveTest extends IntegrationTestCase
 {
     private DirectiveTestingService $service;
+
     private NemesisService $nemesisService;
+
     private HydrationService $hydration;

     protected function setUp(): void
@@ Line 128 @@

         $this->assertTrue($aliases->contains('token-clean'));
         $this->assertTrue($aliases->contains('tokens-clean'));
-        $this->assertSame(2, $aliases->count());
+        $this->assertCount(2, $aliases);
     }

     // ============================================================================
    ----------- end diff -----------

Applied rules:
 * NewlineBetweenClassLikeStmtsRector


16) /home/andy-kani/pro/sites/packages/laravel-nemesis/tests/Integration/Helpers/NemesisHelperTest.php:6

    ---------- begin diff ----------
@@ Line 6 @@

 namespace Kani\Nemesis\Tests\Integration\Helpers;

+use Illuminate\Database\Eloquent\Model;
+use Kani\Nemesis\Records\MiddlewareConfigRecord;
+use AndyDefer\DomainStructures\Abstracts\AbstractRecord;
 use AndyDefer\DomainStructures\Services\HydrationService;
 use Carbon\Carbon;
 use Illuminate\Http\Request;
@@ Line 19 @@
 final class NemesisHelperTest extends IntegrationTestCase
 {
     private TestUser $user;
+
     private NemesisConfigInterface $config;
+
     private HydrationService $hydration;

     protected function setUp(): void
@@ Line 76 @@
     {
         $data = [];

-        if ($tokenRecord !== null) {
+        if ($tokenRecord instanceof NemesisTokenRecord) {
             $data['currentNemesisToken'] = $tokenRecord;
         }

-        if ($user !== null) {
+        if ($user instanceof TestUser) {
             $parameterName = $this->config->middlewareConfig()->parameter_name;
             $data[$parameterName] = $user;
         }
@@ Line 103 @@
         $result = $helper->getCurrentToken();

         // Assert
-        $this->assertNotNull($result);
+        $this->assertInstanceOf(NemesisTokenRecord::class, $result);
         $this->assertSame($tokenRecord->id, $result->id);
         $this->assertSame($tokenRecord->name, $result->name);
     }
@@ Line 118 @@
         $result = $helper->getCurrentToken();

         // Assert
-        $this->assertNull($result);
+        $this->assertNotInstanceOf(NemesisTokenRecord::class, $result);
     }

     public function test_get_current_token_returns_null_when_token_is_not_token_record(): void
@@ Line 131 @@
         $result = $helper->getCurrentToken();

         // Assert
-        $this->assertNull($result);
+        $this->assertNotInstanceOf(NemesisTokenRecord::class, $result);
     }

     // ============================================================================
@@ Line 148 @@
         $result = $helper->getCurrentAuthenticatable();

         // Assert
-        $this->assertNotNull($result);
+        $this->assertInstanceOf(Model::class, $result);
         $this->assertSame($this->user->id, $result->id);
         $this->assertSame($this->user->name, $result->name);
     }
@@ Line 163 @@
         $result = $helper->getCurrentAuthenticatable();

         // Assert
-        $this->assertNull($result);
+        $this->assertNotInstanceOf(Model::class, $result);
     }

     public function test_get_current_authenticatable_uses_custom_parameter_name_from_config(): void
@@ Line 173 @@

         // Créer un mock de la config
         $mockConfig = $this->createStub(NemesisConfigInterface::class);
-        $middlewareConfig = $this->hydration->hydrate(\Kani\Nemesis\Records\MiddlewareConfigRecord::class, [
+        $middlewareConfig = $this->hydration->hydrate(MiddlewareConfigRecord::class, [
             'parameter_name' => $customParameterName,
             'token_header' => 'Authorization',
             'security_headers' => true,
@@ Line 194 @@
         $result = $helper->getCurrentAuthenticatable();

         // Assert
-        $this->assertNotNull($result);
+        $this->assertInstanceOf(Model::class, $result);
         $this->assertSame($this->user->id, $result->id);
     }

@@ Line 216 @@
         $result = $helper->getCurrentAuthenticatableFormat();

         // Assert
-        $this->assertNotNull($result);
+        $this->assertInstanceOf(AbstractRecord::class, $result);
         $this->assertArrayHasKey('id', $result->toArray());
         $this->assertArrayHasKey('name', $result->toArray());
         $this->assertArrayHasKey('email', $result->toArray());
@@ Line 232 @@
         $result = $helper->getCurrentAuthenticatableFormat();

         // Assert
-        $this->assertNull($result);
+        $this->assertNotInstanceOf(AbstractRecord::class, $result);
     }

     public function test_get_current_authenticatable_format_returns_null_when_format_is_not_record(): void
@@ Line 247 @@
         $result = $helper->getCurrentAuthenticatableFormat();

         // Assert
-        $this->assertNull($result);
+        $this->assertNotInstanceOf(AbstractRecord::class, $result);
     }

     // ============================================================================
@@ Line 329 @@
         $helper = $this->getHelper();

         // Act & Assert
-        $this->assertNotNull($helper->getCurrentToken());
-        $this->assertNotNull($helper->getCurrentAuthenticatable());
-        $this->assertNotNull($helper->getCurrentAuthenticatableFormat());
+        $this->assertInstanceOf(NemesisTokenRecord::class, $helper->getCurrentToken());
+        $this->assertInstanceOf(Model::class, $helper->getCurrentAuthenticatable());
+        $this->assertInstanceOf(AbstractRecord::class, $helper->getCurrentAuthenticatableFormat());
         $this->assertTrue($helper->hasCurrentToken());
         $this->assertTrue($helper->hasCurrentAuthenticatable());
     }
@@ Line 342 @@
         $helper = $this->getHelper();

         // Act & Assert
-        $this->assertNull($helper->getCurrentToken());
-        $this->assertNull($helper->getCurrentAuthenticatable());
-        $this->assertNull($helper->getCurrentAuthenticatableFormat());
+        $this->assertNotInstanceOf(NemesisTokenRecord::class, $helper->getCurrentToken());
+        $this->assertNotInstanceOf(Model::class, $helper->getCurrentAuthenticatable());
+        $this->assertNotInstanceOf(AbstractRecord::class, $helper->getCurrentAuthenticatableFormat());
         $this->assertFalse($helper->hasCurrentToken());
         $this->assertFalse($helper->hasCurrentAuthenticatable());
     }
    ----------- end diff -----------

Applied rules:
 * FlipTypeControlToUseExclusiveTypeRector
 * NewlineBetweenClassLikeStmtsRector
 * AssertEmptyNullableObjectToAssertInstanceofRector


17) /home/andy-kani/pro/sites/packages/laravel-nemesis/tests/Integration/Http/Middleware/NemesisTokenMiddlewareTest.php:21

    ---------- begin diff ----------
@@ Line 21 @@
 final class NemesisTokenMiddlewareTest extends IntegrationTestCase
 {
     private TestUser $user;
-    private NemesisConfigInterface $config;
     private NemesisService $service;
-    private NemesisAuthenticationService $authService;
     private HydrationService $hydration;

     protected function setUp(): void
@@ Line 33 @@
         Carbon::setTestNow(Carbon::create(2024, 1, 1, 12, 0, 0));

         $this->hydration = new HydrationService();
-        $this->config = $this->app->make(NemesisConfigInterface::class);
+        $this->app->make(NemesisConfigInterface::class);
         $this->service = $this->app->make(NemesisService::class);
-        $this->authService = $this->app->make(NemesisAuthenticationService::class);
+        $this->app->make(NemesisAuthenticationService::class);

         $this->user = TestUser::create([
             'name' => 'John Doe',
@@ Line 76 @@
         return $this->service->createWithPlainToken($record, $this->user);
     }

+    /**
+     * @param string[] $abilities
+     */
     private function createTokenWithAbilitiesForUser(array $abilities): array
     {
         $record = $this->hydration->hydrate(NemesisTokenRecord::class, [
@@ Line 87 @@
         return $this->service->createWithPlainToken($record, $this->user);
     }

+    /**
+     * @param string[] $origins
+     */
     private function createTokenWithAllowedOriginsForUser(array $origins): array
     {
         $record = $this->hydration->hydrate(NemesisTokenRecord::class, [
@@ Line 247 @@
             return response()->json(['message' => 'OK']);
         });

-        $response = $this->get('/test-custom-header', [], [
-            'X-API-Key' => $plainToken,
-        ]);
+        $response = $this->get('/test-custom-header', []);

         $response->assertStatus(200);
         $response->assertJson(['message' => 'OK']);
@@ Line 317 @@

     public function test_middleware_handles_token_with_nonexistent_tokenable_type(): void
     {
-        $token = NemesisToken::create([
+        NemesisToken::create([
             'token_hash' => hash('sha256', 'bad-token'),
             'tokenable_type' => 'NonExistent\\Model\\Class',
             'tokenable_id' => $this->user->id,
    ----------- end diff -----------

Applied rules:
 * RemoveUnusedVariableAssignRector
 * NarrowUnusedSetUpDefinedPropertyRector
 * RemoveExtraParametersRector
 * ClassMethodArrayDocblockParamFromLocalCallsRector


18) /home/andy-kani/pro/sites/packages/laravel-nemesis/tests/Integration/Repositories/NemesisTokenRepositoryTest.php:6

    ---------- begin diff ----------
@@ Line 6 @@

 namespace Kani\Nemesis\Tests\Integration\Repositories;

+use Illuminate\Database\Eloquent\Model;
 use AndyDefer\PhpVo\ValueObjects\DateTimeVO;
 use Carbon\Carbon;
 use Illuminate\Support\Collection;
@@ Line 40 @@
         parent::tearDown();
     }

+    /**
+     * @param array<string, string> $overrides
+     */
     private function createToken(array $overrides = []): NemesisToken
     {
         $data = array_merge([
@@ Line 80 @@
     public function test_find_with_trashed_by_filters_returns_both_active_and_deleted_tokens(): void
     {
         // Arrange
-        $activeToken = $this->createToken(['name' => 'Active Token']);
+        $this->createToken(['name' => 'Active Token']);
         $deletedToken = $this->createToken(['name' => 'Deleted Token']);
         $this->repository->delete($deletedToken->id);

@@ Line 100 @@
     {
         // Arrange
         $token1 = $this->createToken(['name' => 'Web Token', 'source' => 'web']);
-        $token2 = $this->createToken(['name' => 'Mobile Token', 'source' => 'mobile']);
+        $this->createToken(['name' => 'Mobile Token', 'source' => 'mobile']);
         $this->repository->delete($token1->id);

         $filters = new NemesisTokenFilterRecord(
@@ Line 235 @@
         // Arrange
         $token1 = $this->createToken(['name' => 'Token 1']);
         $token2 = $this->createToken(['name' => 'Token 2']);
-        $token3 = $this->createToken(['name' => 'Token 3']);
+        $this->createToken(['name' => 'Token 3']);

         $this->repository->delete($token1->id);
         $this->repository->delete($token2->id);
@@ Line 295 @@

         // Vérifier que le token du user1 est restauré
         $restoredToken = $this->repository->find($tokenUser1->id);
-        $this->assertNotNull($restoredToken);
+        $this->assertInstanceOf(Model::class, $restoredToken);
         $this->assertNull($restoredToken->deleted_at);

         // Vérifier que le token du user2 est toujours soft deleté
         $stillDeletedToken = $this->repository->findWithTrashed($tokenUser2->id);
-        $this->assertNotNull($stillDeletedToken);
+        $this->assertInstanceOf(Model::class, $stillDeletedToken);
         $this->assertNotNull($stillDeletedToken->deleted_at);
     }
    ----------- end diff -----------

Applied rules:
 * RemoveUnusedVariableAssignRector
 * AssertEmptyNullableObjectToAssertInstanceofRector
 * ClassMethodArrayDocblockParamFromLocalCallsRector


19) /home/andy-kani/pro/sites/packages/laravel-nemesis/tests/Integration/Services/HttpHeaderServiceTest.php:6

    ---------- begin diff ----------
@@ Line 6 @@

 namespace Kani\Nemesis\Tests\Integration\Services;

+use stdClass;
 use Illuminate\Http\JsonResponse;
 use Illuminate\Http\Request;
 use Illuminate\Http\Response;
@@ Line 62 @@
         // Arrange
         config()->set('nemesis.middleware.security_headers', true);
         $config = $this->app->make(NemesisConfigInterface::class);
+
         $service = new HttpHeaderService($config, $this->app);
-        $nonResponseObject = new \stdClass();
+        $nonResponseObject = new stdClass();

         // Act
         $result = $service->applySecurityHeaders($nonResponseObject);
@@ Line 77 @@
         // Arrange
         config()->set('nemesis.middleware.security_headers', true);
         $config = $this->app->make(NemesisConfigInterface::class);
+
         $service = new HttpHeaderService($config, $this->app);
         $response = $this->createMockResponse();

@@ Line 132 @@
         // Arrange
         config()->set('nemesis.middleware.security_headers', true);
         $config = $this->app->make(NemesisConfigInterface::class);
+
         $service = new HttpHeaderService($config, $this->app);
         $response = $this->createMockJsonResponse();

@@ Line 152 @@
         // Arrange
         config()->set('nemesis.middleware.validate_origin', false);
         $config = $this->app->make(NemesisConfigInterface::class);
+
         $service = new HttpHeaderService($config, $this->app);
         $response = $this->createMockResponse();
         $request = new Request();
@@ Line 168 @@
         // Arrange
         config()->set('nemesis.middleware.validate_origin', true);
         $config = $this->app->make(NemesisConfigInterface::class);
+
         $service = new HttpHeaderService($config, $this->app);
-        $nonResponseObject = new \stdClass();
+        $nonResponseObject = new stdClass();
         $request = new Request();

         // Act
@@ Line 184 @@
         // Arrange
         config()->set('nemesis.middleware.validate_origin', true);
         $config = $this->app->make(NemesisConfigInterface::class);
+
         $service = new HttpHeaderService($config, $this->app);
         $response = $this->createMockResponse();
         $request = new Request();
@@ Line 202 @@
         config()->set('nemesis.cors.allow_credentials', false);
         config()->set('nemesis.cors.expose_token_info', false);
         $config = $this->app->make(NemesisConfigInterface::class);
+
         $service = new HttpHeaderService($config, $this->app);
         $response = $this->createMockResponse();
         $request = new Request();
@@ Line 222 @@
         config()->set('nemesis.cors.allow_credentials', true);
         config()->set('nemesis.cors.expose_token_info', false);
         $config = $this->app->make(NemesisConfigInterface::class);
+
         $service = new HttpHeaderService($config, $this->app);
         $response = $this->createMockResponse();
         $request = new Request();
@@ Line 243 @@
         config()->set('nemesis.cors.max_age', 86400);
         config()->set('nemesis.cors.expose_token_info', false);
         $config = $this->app->make(NemesisConfigInterface::class);
+
         $service = new HttpHeaderService($config, $this->app);
         $response = $this->createMockResponse();
         $request = new Request();
@@ Line 254 @@

         // Assert
         $this->assertTrue($result->headers->has('Access-Control-Allow-Methods'));
-        $this->assertStringContainsString('GET', $result->headers->get('Access-Control-Allow-Methods'));
-        $this->assertStringContainsString('POST', $result->headers->get('Access-Control-Allow-Methods'));
+        $this->assertStringContainsString('GET', (string) $result->headers->get('Access-Control-Allow-Methods'));
+        $this->assertStringContainsString('POST', (string) $result->headers->get('Access-Control-Allow-Methods'));
         $this->assertTrue($result->headers->has('Access-Control-Allow-Headers'));
-        $this->assertStringContainsString('Content-Type', $result->headers->get('Access-Control-Allow-Headers'));
+        $this->assertStringContainsString('Content-Type', (string) $result->headers->get('Access-Control-Allow-Headers'));
         $this->assertTrue($result->headers->has('Access-Control-Max-Age'));
         $this->assertEquals('86400', $result->headers->get('Access-Control-Max-Age'));
     }
@@ Line 269 @@
         config()->set('nemesis.cors.allow_credentials', false);
         config()->set('nemesis.cors.expose_token_info', true);
         $config = $this->app->make(NemesisConfigInterface::class);
+
         $service = new HttpHeaderService($config, $this->app);
         $response = $this->createMockResponse();
         $request = new Request();
@@ Line 279 @@

         // Assert
         $this->assertTrue($result->headers->has('Access-Control-Expose-Headers'));
-        $this->assertStringContainsString('X-Token-Expires-At', $result->headers->get('Access-Control-Expose-Headers'));
-        $this->assertStringContainsString('X-Token-Abilities', $result->headers->get('Access-Control-Expose-Headers'));
+        $this->assertStringContainsString('X-Token-Expires-At', (string) $result->headers->get('Access-Control-Expose-Headers'));
+        $this->assertStringContainsString('X-Token-Abilities', (string) $result->headers->get('Access-Control-Expose-Headers'));
     }

     // ============================================================================
@@ Line 292 @@
         // Arrange
         config()->set('nemesis.middleware.validate_origin', false);
         $config = $this->app->make(NemesisConfigInterface::class);
+
         $service = new HttpHeaderService($config, $this->app);
         $response = $this->createMockJsonResponse();
         $request = new Request();
@@ Line 308 @@
         // Arrange
         config()->set('nemesis.middleware.validate_origin', true);
         $config = $this->app->make(NemesisConfigInterface::class);
+
         $service = new HttpHeaderService($config, $this->app);
         $response = $this->createMockJsonResponse();
         $request = new Request();
@@ Line 325 @@
         config()->set('nemesis.middleware.validate_origin', true);
         config()->set('nemesis.cors.allow_credentials', false);
         $config = $this->app->make(NemesisConfigInterface::class);
+
         $service = new HttpHeaderService($config, $this->app);
         $response = $this->createMockJsonResponse();

@@ Line 336 @@

         // Assert
         $this->assertTrue($response->headers->has('Access-Control-Allow-Origin'));
-        $this->assertEquals('https://example.com', $response->headers->get('Access-Control-Allow-Origin'));
+        $this->assertSame('https://example.com', $response->headers->get('Access-Control-Allow-Origin'));
     }

     public function test_add_cors_to_error_response_adds_credentials_header_when_allowed(): void
@@ Line 345 @@
         config()->set('nemesis.middleware.validate_origin', true);
         config()->set('nemesis.cors.allow_credentials', true);
         $config = $this->app->make(NemesisConfigInterface::class);
+
         $service = new HttpHeaderService($config, $this->app);
         $response = $this->createMockJsonResponse();

@@ Line 356 @@

         // Assert
         $this->assertTrue($response->headers->has('Access-Control-Allow-Credentials'));
-        $this->assertEquals('true', $response->headers->get('Access-Control-Allow-Credentials'));
+        $this->assertSame('true', $response->headers->get('Access-Control-Allow-Credentials'));
     }

     public function test_add_cors_to_error_response_does_not_add_credentials_header_when_not_allowed(): void
@@ Line 365 @@
         config()->set('nemesis.middleware.validate_origin', true);
         config()->set('nemesis.cors.allow_credentials', false);
         $config = $this->app->make(NemesisConfigInterface::class);
+
         $service = new HttpHeaderService($config, $this->app);
         $response = $this->createMockJsonResponse();
    ----------- end diff -----------

Applied rules:
 * NewlineBeforeNewAssignSetRector
 * AssertEqualsToSameRector
 * StringCastAssertStringContainsStringRector


20) /home/andy-kani/pro/sites/packages/laravel-nemesis/tests/Integration/Services/NemesisAuthenticationServiceTest.php:6

    ---------- begin diff ----------
@@ Line 6 @@

 namespace Kani\Nemesis\Tests\Integration\Services;

+use AndyDefer\DomainStructures\Utils\StrictDataObject;
+use stdClass;
 use AndyDefer\DataValidator\Services\MetadataValidator;
 use AndyDefer\DomainStructures\Abstracts\AbstractRecord;
 use AndyDefer\DomainStructures\Collections\Utility\StringTypedCollection;
@@ Line 26 @@
 final class NemesisAuthenticationServiceTest extends IntegrationTestCase
 {
     private NemesisAuthenticationService $authService;
+
     private NemesisService $nemesisService;
+
     private TestUser $user;
+
     private string $plainToken;
+
     private NemesisToken $tokenModel;
+
     private HydrationService $hydration;

     protected function setUp(): void
@@ Line 100 @@

         // Assert
         $this->assertTrue($result->isSuccess());
-        $this->assertNotNull($result->getTokenRecord());
-        $this->assertNull($result->getErrorCode());
+        $this->assertInstanceOf(NemesisTokenRecord::class, $result->getTokenRecord());
+        $this->assertNotInstanceOf(ErrorCode::class, $result->getErrorCode());
     }

     public function test_authenticate_returns_token_record(): void
@@ Line 176 @@

         // Assert
         $this->assertFalse($result->isSuccess());
-        $this->assertEquals(ErrorCode::MISSING_TOKEN, $result->getErrorCode());
+        $this->assertSame(ErrorCode::MISSING_TOKEN, $result->getErrorCode());
     }

     public function test_authenticate_returns_invalid_token_error(): void
@@ Line 189 @@

         // Assert
         $this->assertFalse($result->isSuccess());
-        $this->assertEquals(ErrorCode::INVALID_TOKEN, $result->getErrorCode());
+        $this->assertSame(ErrorCode::INVALID_TOKEN, $result->getErrorCode());
     }

     public function test_authenticate_returns_expired_token_error(): void
@@ Line 208 @@

         // Assert
         $this->assertFalse($result->isSuccess());
-        $this->assertEquals(ErrorCode::TOKEN_EXPIRED, $result->getErrorCode());
+        $this->assertSame(ErrorCode::TOKEN_EXPIRED, $result->getErrorCode());
     }

     // ============================================================================
@@ Line 254 @@

         // Assert
         $this->assertFalse($result->isSuccess());
-        $this->assertEquals(ErrorCode::INSUFFICIENT_PERMISSIONS, $result->getErrorCode());
+        $this->assertSame(ErrorCode::INSUFFICIENT_PERMISSIONS, $result->getErrorCode());

         $additionalData = $result->getAdditionalData();
+        $this->assertInstanceOf(StrictDataObject::class, $additionalData);
         $this->assertEquals('admin', $additionalData->toArray()['required_ability']);
     }

@@ Line 304 @@

         // Assert
         $this->assertFalse($result->isSuccess());
-        $this->assertEquals(ErrorCode::ORIGIN_NOT_ALLOWED, $result->getErrorCode());
+        $this->assertSame(ErrorCode::ORIGIN_NOT_ALLOWED, $result->getErrorCode());

         $additionalData = $result->getAdditionalData();
+        $this->assertInstanceOf(StrictDataObject::class, $additionalData);
         $this->assertEquals('https://evil.com', $additionalData->toArray()['origin']);
     }

@@ Line 369 @@

         // Assert
         $this->assertFalse($result->isSuccess());
-        $this->assertEquals(ErrorCode::INVALID_TOKEN, $result->getErrorCode());
+        $this->assertSame(ErrorCode::INVALID_TOKEN, $result->getErrorCode());
     }

     // ============================================================================
@@ Line 412 @@
     public function test_get_formatted_authenticatable_returns_null_for_invalid_model(): void
     {
         // Arrange
-        $invalidModel = new \stdClass();
+        $invalidModel = new stdClass();

         // Act
         $formatted = $this->authService->getFormattedAuthenticatable($invalidModel);

         // Assert
-        $this->assertNull($formatted);
+        $this->assertNotInstanceOf(AbstractRecord::class, $formatted);
     }

     // ============================================================================
    ----------- end diff -----------

Applied rules:
 * NewlineBetweenClassLikeStmtsRector
 * AddInstanceofAssertForNullableInstanceRector
 * AssertEmptyNullableObjectToAssertInstanceofRector
 * AssertEqualsToSameRector


21) /home/andy-kani/pro/sites/packages/laravel-nemesis/tests/Integration/Services/NemesisServiceTest.php:6

    ---------- begin diff ----------
@@ Line 6 @@

 namespace Kani\Nemesis\Tests\Integration\Services;

+use AndyDefer\DataValidator\Services\MetadataValidator;
 use AndyDefer\DomainStructures\Collections\Utility\StringTypedCollection;
 use AndyDefer\DomainStructures\Services\HydrationService;
 use AndyDefer\DomainStructures\Utils\StrictDataObject;
@@ Line 13 @@
 use Carbon\Carbon;
 use Illuminate\Http\Request;
 use Illuminate\Support\Str;
-use Kani\Nemesis\Configs\NemesisConfig;
 use Kani\Nemesis\Contracts\Configs\NemesisConfigInterface;
 use Kani\Nemesis\Models\NemesisToken;
 use Kani\Nemesis\Records\NemesisTokenFilterRecord;
@@ Line 26 @@
 final class NemesisServiceTest extends IntegrationTestCase
 {
     private NemesisService $service;
+
     private TestUser $user;
-    private NemesisTokenRepository $repository;
     private HydrationService $hydration;

     protected function setUp(): void
@@ Line 37 @@
         Carbon::setTestNow(Carbon::create(2024, 1, 1, 12, 0, 0));

         $this->hydration = new HydrationService();
-        $this->repository = new NemesisTokenRepository();
+        $repository = new NemesisTokenRepository();
         $this->service = new NemesisService(
-            repository: $this->repository,
+            repository: $repository,
             config: $this->app->make(NemesisConfigInterface::class),
             str: new Str(),
-            metadataValidator: $this->app->make(\AndyDefer\DataValidator\Services\MetadataValidator::class),
+            metadataValidator: $this->app->make(MetadataValidator::class),
             hydration: $this->hydration,
         );

@@ Line 136 @@

         $found = $this->service->find($token->id);

-        $this->assertNotNull($found);
+        $this->assertInstanceOf(NemesisToken::class, $found);
         $this->assertSame($token->id, $found->id);
     }

@@ Line 144 @@
     {
         $found = $this->service->find(99999);

-        $this->assertNull($found);
+        $this->assertNotInstanceOf(NemesisToken::class, $found);
     }

     public function test_find_with_trashed_returns_deleted_token(): void
@@ Line 154 @@

         $found = $this->service->findWithTrashed($token->id);

-        $this->assertNotNull($found);
+        $this->assertInstanceOf(NemesisToken::class, $found);
         $this->assertNotNull($found->deleted_at);
     }

@@ Line 164 @@

         $found = $this->service->findByHash($token->token_hash);

-        $this->assertNotNull($found);
+        $this->assertInstanceOf(NemesisToken::class, $found);
         $this->assertSame($token->id, $found->id);
     }

@@ Line 190 @@
         $deleted = $this->service->delete($token->id);

         $this->assertTrue($deleted);
-        $this->assertNull($this->service->find($token->id));
+        $this->assertNotInstanceOf(NemesisToken::class, $this->service->find($token->id));
         $this->assertDatabaseHas('nemesis_tokens', ['id' => $token->id]);
         $this->assertDatabaseMissing('nemesis_tokens', ['id' => $token->id, 'deleted_at' => null]);
     }
@@ Line 203 @@
         $restored = $this->service->restore($token->id);

         $this->assertTrue($restored);
-        $this->assertNotNull($this->service->find($token->id));
+        $this->assertInstanceOf(NemesisToken::class, $this->service->find($token->id));
         $this->assertDatabaseHas('nemesis_tokens', ['id' => $token->id, 'deleted_at' => null]);
     }

@@ Line 242 @@
         $deletedCount = $this->service->deleteBulk($filters);

         $this->assertSame(2, $deletedCount);
-        $this->assertNull($this->service->find($token1->id));
-        $this->assertNull($this->service->find($token2->id));
-        $this->assertNotNull($this->service->find($token3->id));
+        $this->assertNotInstanceOf(NemesisToken::class, $this->service->find($token1->id));
+        $this->assertNotInstanceOf(NemesisToken::class, $this->service->find($token2->id));
+        $this->assertInstanceOf(NemesisToken::class, $this->service->find($token3->id));
         $this->assertDatabaseHas('nemesis_tokens', ['id' => $token1->id]);
         $this->assertDatabaseHas('nemesis_tokens', ['id' => $token2->id]);
         $this->assertDatabaseMissing('nemesis_tokens', ['id' => $token1->id, 'deleted_at' => null]);
@@ Line 541 @@
         $revokedCount = $this->service->revokeAllTokens($this->user);

         $this->assertSame(2, $revokedCount);
-        $this->assertNull($this->service->find($token1->id));
-        $this->assertNull($this->service->find($token2->id));
+        $this->assertNotInstanceOf(NemesisToken::class, $this->service->find($token1->id));
+        $this->assertNotInstanceOf(NemesisToken::class, $this->service->find($token2->id));
         $this->assertDatabaseHas('nemesis_tokens', ['id' => $token1->id]);
         $this->assertDatabaseHas('nemesis_tokens', ['id' => $token2->id]);
     }
@@ Line 557 @@
         $restoredCount = $this->service->restoreAllTokens($this->user);

         $this->assertSame(2, $restoredCount);
-        $this->assertNotNull($this->service->find($token1->id));
-        $this->assertNotNull($this->service->find($token2->id));
+        $this->assertInstanceOf(NemesisToken::class, $this->service->find($token1->id));
+        $this->assertInstanceOf(NemesisToken::class, $this->service->find($token2->id));
     }

     public function test_revoke_tokens_by_source_soft_deletes_matching_tokens(): void
@@ Line 583 @@
         $revokedCount = $this->service->revokeTokensBySource($this->user, 'web');

         $this->assertSame(3, $revokedCount);
-        $this->assertNull($this->service->find($webToken->id));
+        $this->assertNotInstanceOf(NemesisToken::class, $this->service->find($webToken->id));
     }

     public function test_revoke_tokens_by_source_with_force_permanently_deletes(): void
@@ Line 621 @@
         $revokedCount = $this->service->revokeTokensByName($this->user, 'Admin');

         $this->assertSame(3, $revokedCount);
-        $this->assertNull($this->service->find($adminToken->id));
+        $this->assertNotInstanceOf(NemesisToken::class, $this->service->find($adminToken->id));
     }

     public function test_revoke_tokens_by_source_and_name_soft_deletes_matching_tokens(): void
@@ Line 670 @@
         $revokedCount = $this->service->revokeAllTokensExceptSource($this->user, 'mobile');

         $this->assertSame(2, $revokedCount);
-        $this->assertNotNull($this->service->find($mobileToken->id));
+        $this->assertInstanceOf(NemesisToken::class, $this->service->find($mobileToken->id));
     }

     public function test_revoke_all_tokens_except_source_with_force_permanently_deletes(): void
@@ Line 705 @@
         $request = $this->app->make(Request::class);
         $currentToken = $this->service->getCurrentToken($this->user, $request);

-        $this->assertNotNull($currentToken);
+        $this->assertInstanceOf(NemesisToken::class, $currentToken);
         $this->assertSame($token->id, $currentToken->id);
     }

@@ Line 720 @@
         $revoked = $this->service->revokeCurrentToken($this->user, $request);

         $this->assertTrue($revoked);
-        $this->assertNull($this->service->find($token->id));
+        $this->assertNotInstanceOf(NemesisToken::class, $this->service->find($token->id));
         $this->assertDatabaseHas('nemesis_tokens', ['id' => $token->id]);
     }

@@ Line 777 @@

         $found = $this->service->getTokenByPlainText($plainToken, $this->user);

-        $this->assertNotNull($found);
+        $this->assertInstanceOf(NemesisToken::class, $found);
         $this->assertSame($token->id, $found->id);
     }

@@ Line 789 @@

         $found = $this->service->getTokenByPlainText($plainToken, $this->user, withTrashed: true);

-        $this->assertNotNull($found);
+        $this->assertInstanceOf(NemesisToken::class, $found);
         $this->assertSame($token->id, $found->id);
         $this->assertNotNull($found->deleted_at);
     }
@@ Line 829 @@
         $revokedCount = $this->service->revokeExpiredTokens($this->user);

         $this->assertSame(1, $revokedCount);
-        $this->assertNull($this->service->find($expiredToken->id));
+        $this->assertNotInstanceOf(NemesisToken::class, $this->service->find($expiredToken->id));
         $this->assertDatabaseHas('nemesis_tokens', ['id' => $expiredToken->id]);
     }

@@ Line 874 @@
         $revoked = $this->service->revoke($token);

         $this->assertTrue($revoked);
-        $this->assertNull($this->service->find($token->id));
+        $this->assertNotInstanceOf(NemesisToken::class, $this->service->find($token->id));
         $this->assertDatabaseHas('nemesis_tokens', ['id' => $token->id]);
     }

@@ Line 886 @@
         $restored = $this->service->restoreToken($token);

         $this->assertTrue($restored);
-        $this->assertNotNull($this->service->find($token->id));
+        $this->assertInstanceOf(NemesisToken::class, $this->service->find($token->id));
     }

     // ============================================================================
    ----------- end diff -----------

Applied rules:
 * NewlineBetweenClassLikeStmtsRector
 * NarrowUnusedSetUpDefinedPropertyRector
 * AssertEmptyNullableObjectToAssertInstanceofRector


22) /home/andy-kani/pro/sites/packages/laravel-nemesis/tests/IntegrationTestCase.php:4

    ---------- begin diff ----------
@@ Line 4 @@

 namespace Kani\Nemesis\Tests;

+use Mockery;
 use AndyDefer\Directive\DirectiveServiceProvider;
 use Carbon\Carbon;
 use Illuminate\Foundation\Application;
@@ Line 36 @@
     {
         Carbon::setTestNow();
         parent::tearDown();
-        \Mockery::close();
+        Mockery::close();
     }

     /**
    ----------- end diff -----------

Applied rules:


23) /home/andy-kani/pro/sites/packages/laravel-nemesis/tests/Unit/Contracts/MustNemesisContractTest.php:39

    ---------- begin diff ----------
@@ Line 39 @@
         $this->assertInstanceOf(AbstractRecord::class, $formatted);

         // Assert: Format contains expected user fields
-        $this->assertEquals(1, $formatted->id);
-        $this->assertEquals('John Doe', $formatted->name);
-        $this->assertEquals('john@example.com', $formatted->email);
+        $this->assertSame(1, $formatted->id);
+        $this->assertSame('John Doe', $formatted->name);
+        $this->assertSame('john@example.com', $formatted->email);
     }

     /**
@@ Line 61 @@
         $this->assertInstanceOf(AbstractRecord::class, $formatted);

         // Assert: Format contains expected API client fields
-        $this->assertEquals(42, $formatted->id);
-        $this->assertEquals('API Service', $formatted->name);
-        $this->assertEquals('api_client', $formatted->type);
+        $this->assertSame(42, $formatted->id);
+        $this->assertSame('API Service', $formatted->name);
+        $this->assertSame('api_client', $formatted->type);

         // Assert: Sensitive api_key is NOT exposed
         $this->assertNull($formatted->api_key ?? null);
@@ Line 88 @@
         $this->assertInstanceOf(AbstractRecord::class, $formatted);

         // Assert: Format contains checkpoint-specific fields
-        $this->assertEquals(10, $formatted->id);
-        $this->assertEquals('Main Entrance', $formatted->name);
-        $this->assertEquals('Gate A', $formatted->location);
-        $this->assertEquals('active', $formatted->status);
-        $this->assertEquals('checkpoint', $formatted->type);
+        $this->assertSame(10, $formatted->id);
+        $this->assertSame('Main Entrance', $formatted->name);
+        $this->assertSame('Gate A', $formatted->location);
+        $this->assertSame('active', $formatted->status);
+        $this->assertSame('checkpoint', $formatted->type);
     }

     /**
@@ Line 114 @@
         $this->assertInstanceOf(AbstractRecord::class, $formatted);

         // Assert: Field values are correctly mapped
-        $this->assertEquals(5, $formatted->user_id);
-        $this->assertEquals('Jane Smith', $formatted->full_name);
+        $this->assertSame(5, $formatted->user_id);
+        $this->assertSame('Jane Smith', $formatted->full_name);
         $this->assertTrue($formatted->is_verified);
-        $this->assertEquals('only_for_api', $formatted->custom_field);
-        $this->assertEquals('custom_user', $formatted->type);
+        $this->assertSame('only_for_api', $formatted->custom_field);
+        $this->assertSame('custom_user', $formatted->type);

         // Assert: Email is NOT exposed in custom format (security)
         $this->assertNull($formatted->email ?? null);
    ----------- end diff -----------

Applied rules:
 * AssertEqualsToSameRector


24) /home/andy-kani/pro/sites/packages/laravel-nemesis/tests/Unit/Directives/CleanTokensDirectiveUnitTest.php:6

    ---------- begin diff ----------
@@ Line 6 @@

 namespace Kani\Nemesis\Tests\Unit\Directives;

+use PHPUnit\Framework\MockObject\MockObject;
+use ReflectionClass;
+use AndyDefer\Directive\Collections\ParameterVOCollection;
 use AndyDefer\Directive\Contexts\DirectiveContext;
 use AndyDefer\Directive\Contexts\LaravelBootstrapperContext;
 use AndyDefer\Directive\Enums\ExitCode;
@@ Line 21 @@
 #[AllowMockObjectsWithoutExpectations]
 final class CleanTokensDirectiveUnitTest extends TestCase
 {
-    private $interaction;
-    private $config;
-    private $service;
+    private MockObject $interaction;

+
     protected function setUp(): void
     {
         parent::setUp();

         $this->interaction = $this->createMock(DirectiveInteractionService::class);
-        $this->config = $this->createStub(NemesisConfigInterface::class);
-        $this->service = $this->createMock(NemesisService::class);
     }

     private function createDirective(): CleanTokensDirective
@@ Line 50 @@
         return new CleanTokensDirective(
             $context,
             $this->interaction,
-            $this->config,
-            $this->service,
+            $this->createStub(NemesisConfigInterface::class),
+            $this->createStub(NemesisService::class),
         );
     }

@@ Line 69 @@
         $directive = $this->createDirective();

         // Simuler l'option 'force' = false
-        $reflection = new \ReflectionClass($directive);
+        $reflection = new ReflectionClass($directive);
         $contextProperty = $reflection->getProperty('context');
         $context = $contextProperty->getValue($directive);

         // Forcer l'option 'force' à false via le contexte
-        $optionsCollection = new \AndyDefer\Directive\Collections\ParameterVOCollection();
+        $optionsCollection = new ParameterVOCollection();
         $context->setOptions($optionsCollection);

         $result = $directive->execute();
    ----------- end diff -----------

Applied rules:
 * NewlineBetweenClassLikeStmtsRector
 * InlineStubPropertyToCreateStubMethodCallRector
 * PropertyCreateMockToCreateStubRector
 * TypedPropertyFromCreateMockAssignRector
 * TypedPropertyFromStrictSetUpRector


25) /home/andy-kani/pro/sites/packages/laravel-nemesis/tests/Unit/Directives/InstallNemesisDirectiveTest.php:6

    ---------- begin diff ----------
@@ Line 6 @@

 namespace Kani\Nemesis\Tests\Unit\Directives;

+use PHPUnit\Framework\MockObject\MockObject;
+use stdClass;
+use ReflectionClass;
+use AndyDefer\Directive\Enums\PrimitiveType;
 use AndyDefer\Directive\Collections\ParameterVOCollection;
 use AndyDefer\Directive\Contexts\DirectiveContext;
 use AndyDefer\Directive\Contexts\LaravelBootstrapperContext;
@@ Line 31 @@
 #[AllowMockObjectsWithoutExpectations]
 final class InstallNemesisDirectiveTest extends TestCase
 {
-    private $kernel;
-    private $app;
-    private $filesystem;
-    private $db;
-    private $connection;
-    private $schemaBuilder;
-    private $config;
-    private $interaction;
+    private MockObject $kernel;

+    private MockObject $app;
+
+    private MockObject $filesystem;
+
+    private MockObject $db;
+    private MockObject $schemaBuilder;
+
+    private MockObject $config;
+
+    private MockObject $interaction;
+
     protected function setUp(): void
     {
         parent::setUp();
@@ Line 49 @@
         $this->filesystem = $this->createMock(FileSystemService::class);

         $this->db = $this->createMock(DatabaseManager::class);
-        $this->connection = $this->createMock(Connection::class);
+        $connection = $this->createMock(Connection::class);
         $this->schemaBuilder = $this->createMock(Builder::class);

-        $this->db->method('connection')->willReturn($this->connection);
-        $this->connection->method('getSchemaBuilder')->willReturn($this->schemaBuilder);
+        $this->db->method('connection')->willReturn($connection);
+        $connection->method('getSchemaBuilder')->willReturn($this->schemaBuilder);

         $this->config = $this->createMock(NemesisConfigInterface::class);
         $this->interaction = $this->createMock(DirectiveInteractionService::class);
     }

+    /**
+     * @param array<string, bool> $options
+     * @param array<string, bool> $fileExistsMap
+     */
     private function createDirectiveWithOptions(array $options = [], array $fileExistsMap = []): InstallNemesisDirective
     {
         $hydration = new HydrationService();
@@ Line 85 @@
                     return $exists;
                 }
             }
+
             return false;
         });

@@ Line 91 @@
         $this->app->method('basePath')->willReturn('/fake/project');
         $this->app->method('databasePath')->willReturn('/fake/project/database');

-        $mockConfig = new \stdClass();
+        $mockConfig = new stdClass();
         $mockConfig->providers = [];
-        $this->app->method('make')->willReturnCallback(function ($abstract) use ($mockConfig) {
+        $this->app->method('make')->willReturnCallback(function ($abstract) use ($mockConfig): ?stdClass {
             if ($abstract === 'config') {
                 return $mockConfig;
             }
+
             return null;
         });

         $optionsCollection = new ParameterVOCollection();
         foreach ($options as $key => $value) {
-            $reflection = new \ReflectionClass($optionsCollection);
+            $reflection = new ReflectionClass($optionsCollection);
             $itemsProperty = $reflection->getProperty('items');
             $items = $itemsProperty->getValue($optionsCollection);

@@ Line 109 @@
             $paramVO = new ParameterVO(
                 name: $key,
                 value: $value,
-                type: \AndyDefer\Directive\Enums\PrimitiveType::BOOL
+                type: PrimitiveType::BOOL
             );
             $items[] = $paramVO;
             $itemsProperty->setValue($optionsCollection, $items);
@@ Line 209 @@

         $this->assertTrue($aliases->contains('nemesis-install'));
         $this->assertTrue($aliases->contains('setup-nemesis'));
-        $this->assertSame(2, $aliases->count());
+        $this->assertCount(2, $aliases);
     }

     public function test_should_boot_laravel_returns_true(): void
    ----------- end diff -----------

Applied rules:
 * NewlineBetweenClassLikeStmtsRector
 * NewlineAfterStatementRector
 * NarrowUnusedSetUpDefinedPropertyRector
 * ClassMethodArrayDocblockParamFromLocalCallsRector
 * TypedPropertyFromCreateMockAssignRector
 * ClosureReturnTypeRector


26) /home/andy-kani/pro/sites/packages/laravel-nemesis/tests/UnitTestCase.php:4

    ---------- begin diff ----------
@@ Line 4 @@

 namespace Kani\Nemesis\Tests;

+use Mockery;
 use Carbon\Carbon;
 use PHPUnit\Framework\TestCase as BaseTestCase;

@@ Line 30 @@
     {
         Carbon::setTestNow();
         parent::tearDown();
-        \Mockery::close();
+        Mockery::close();
     }
 }
    ----------- end diff -----------

Applied rules:


 [OK] 26 files would have been changed (dry-run) by Rector                                                              

