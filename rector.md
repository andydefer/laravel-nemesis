# Rector Refactoring Report
*Generated: dim. 14 juin 2026 17:58:51 WAT*


25 files with changes
=====================

1) /home/andy-kani/pro/sites/packages/laravel-nemesis/src/Directives/CleanTokensDirective.php:190

    ---------- begin diff ----------
@@ Line 190 @@

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


2) /home/andy-kani/pro/sites/packages/laravel-nemesis/src/Directives/InstallNemesisDirective.php:6

    ---------- begin diff ----------
@@ Line 6 @@

 namespace AndyDefer\Nemesis\Directives;

+use Illuminate\Database\DatabaseManager;
+use Illuminate\Contracts\Console\Kernel;
+use Illuminate\Contracts\Foundation\Application;
 use AndyDefer\Directive\AbstractDirective;
-use AndyDefer\Directive\Contexts\DirectiveContext;
 use AndyDefer\Directive\Enums\ExitCode;
-use AndyDefer\Directive\Services\DirectiveInteractionService;
 use AndyDefer\DomainStructures\Collections\Utility\StringTypedCollection;
 use AndyDefer\PhpServices\Contracts\FileSystemInterface;
 use AndyDefer\PhpServices\Enums\PermissionMode;
@@ Line 46 @@
     private function getSchemaBuilder()
     {
         $laravel = $this->getLaravel();
-        $db = $laravel->make(\Illuminate\Database\DatabaseManager::class);
+        $db = $laravel->make(DatabaseManager::class);
         $connection = $db->connection();
         return $connection->getSchemaBuilder();
     }
@@ Line 55 @@
     {
         $laravel = $this->getLaravel();

-        $kernel = $laravel->make(\Illuminate\Contracts\Console\Kernel::class);
-        $app = $laravel->make(\Illuminate\Contracts\Foundation\Application::class);
+        $kernel = $laravel->make(Kernel::class);
+        $app = $laravel->make(Application::class);
         $config = $laravel->make(NemesisConfigInterface::class);
         $filesystem = new FileSystemService();

@@ Line 75 @@
         $this->info("\n📦 Checking package files...");

         if (!$filesystem->exists($packageRoot)) {
-            $this->error("Package not found at: {$packageRoot}");
+            $this->error('Package not found at: ' . $packageRoot);
             $this->error('Please run: composer require andydefer/laravel-nemesis');
             return ExitCode::FAILURE;
         }
+
         $this->info("  ✓ Package found");

         // ========================================================================
@@ Line 90 @@
         $configDestination = $app->basePath('config/nemesis.php');

         if (!$filesystem->exists($configSource)) {
-            $this->error("Config source not found: {$configSource}");
+            $this->error('Config source not found: ' . $configSource);
             return ExitCode::FAILURE;
         }

@@ Line 112 @@
         $migrationDestination = $app->databasePath('migrations/2024_01_01_000001_create_nemesis_tokens_table.php');

         if (!$filesystem->exists($migrationSource)) {
-            $this->error("Migration source not found: {$migrationSource}");
+            $this->error('Migration source not found: ' . $migrationSource);
             return ExitCode::FAILURE;
         }

@@ Line 136 @@
             $this->error('Failed to run migrations.');
             return ExitCode::FAILURE;
         }
+
         $this->info('  ✓ Migrations executed');

         // ========================================================================
@@ Line 149 @@
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


3) /home/andy-kani/pro/sites/packages/laravel-nemesis/src/Directives/ListTokensDirective.php:8

    ---------- begin diff ----------
@@ Line 8 @@

 use AndyDefer\Directive\AbstractDirective;
 use AndyDefer\Directive\Collections\RowCollection;
-use AndyDefer\Directive\Contexts\DirectiveContext;
 use AndyDefer\Directive\Enums\ExitCode;
-use AndyDefer\Directive\Services\DirectiveInteractionService;
 use AndyDefer\DomainStructures\Collections\Utility\StringTypedCollection;
 use AndyDefer\Nemesis\Models\NemesisToken;
 use AndyDefer\Nemesis\Records\NemesisTokenFilterRecord;
@@ Line 19 @@

 final class ListTokensDirective extends AbstractDirective
 {
-    public function __construct(
-        DirectiveContext $context,
-        DirectiveInteractionService $interaction,
-    ) {
-        parent::__construct($context, $interaction);
-    }
-
     public function getSignature(): string
     {
         return 'list-tokens {--model=}';
@@ Line 60 @@
         if (is_string($modelFilter) && $modelFilter !== '') {
             $this->info(sprintf('Filtering by model: %s', $modelFilter));
             // Recherche LIKE pour trouver le namespace complet contenant le basename
-            $tokens = NemesisToken::where('tokenable_type', 'LIKE', "%{$modelFilter}%")->get();
+            $tokens = NemesisToken::where('tokenable_type', 'LIKE', sprintf('%%%s%%', $modelFilter))->get();
         } else {
             $tokens = $service->findByFilters(new NemesisTokenFilterRecord);
         }
    ----------- end diff -----------

Applied rules:
 * EncapsedStringsToSprintfRector
 * RemoveParentDelegatingConstructorRector


4) /home/andy-kani/pro/sites/packages/laravel-nemesis/src/Directives/NemesisCleanDirective.php:8

    ---------- begin diff ----------
@@ Line 8 @@

 use AndyDefer\Directive\AbstractDirective;
 use AndyDefer\Directive\Collections\RowCollection;
-use AndyDefer\Directive\Contexts\DirectiveContext;
 use AndyDefer\Directive\Enums\ExitCode;
-use AndyDefer\Directive\Services\DirectiveInteractionService;
 use AndyDefer\DomainStructures\Collections\Utility\StringTypedCollection;
 use AndyDefer\DomainStructures\Services\HydrationService;
 use AndyDefer\PhpVo\ValueObjects\DateTimeVO;
@@ Line 59 @@

         $statistics = $this->performCleanup($config, $repository, $hydration);

-        $this->displayResults($statistics, $config, $repository, $hydration);
+        $this->displayResults($statistics, $config);

         return ExitCode::SUCCESS;
     }
@@ Line 166 @@
         return $configDays;
     }

+    /**
+     * @param array<string, mixed> $statistics
+     */
     private function displayResults(
         array $statistics,
         NemesisConfigInterface $config,
-        NemesisTokenRepository $repository,
-        HydrationService $hydration,
     ): void {
         $this->newLine();
         $this->line('═══════════════════════════════════════════════════════');
@@ Line 188 @@
         $this->displayConfigurationSummary($config);
     }

+    /**
+     * @param array<string, mixed> $statistics
+     */
     private function displayStatisticsTable(array $statistics): void
     {
         $headers = new StringTypedCollection;
@@ Line 197 @@

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
 * RemoveUnusedPrivateMethodParameterRector
 * AddParamArrayDocblockFromDimFetchAccessRector


5) /home/andy-kani/pro/sites/packages/laravel-nemesis/src/Exceptions/MetadataValidationException.php:73

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


6) /home/andy-kani/pro/sites/packages/laravel-nemesis/src/Helpers/NemesisHelper.php:59

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


7) /home/andy-kani/pro/sites/packages/laravel-nemesis/src/Http/Middleware/NemesisTokenMiddleware.php:6

    ---------- begin diff ----------
@@ Line 6 @@

 namespace AndyDefer\Nemesis\Http\Middleware;

+use AndyDefer\DomainStructures\Abstracts\AbstractRecord;
 use AndyDefer\Actions\Http\ResponseFactory;
 use AndyDefer\DomainStructures\Services\HydrationService;
 use Closure;
@@ Line 118 @@
             'current_nemesis_token' => $tokenRecord,
         ]);

-        if ($formattedAuthenticatable !== null) {
+        if ($formattedAuthenticatable instanceof AbstractRecord) {
             $formatKey = $parameterName . '_format';
             $request->merge([
                 $formatKey => $formattedAuthenticatable,
    ----------- end diff -----------

Applied rules:
 * FlipTypeControlToUseExclusiveTypeRector


8) /home/andy-kani/pro/sites/packages/laravel-nemesis/src/NemesisServiceProvider.php:39

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


9) /home/andy-kani/pro/sites/packages/laravel-nemesis/src/Repositories/NemesisTokenRepository.php:6

    ---------- begin diff ----------
@@ Line 6 @@

 namespace AndyDefer\Nemesis\Repositories;

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


10) /home/andy-kani/pro/sites/packages/laravel-nemesis/src/Services/NemesisAuthenticationService.php:50

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


11) /home/andy-kani/pro/sites/packages/laravel-nemesis/src/Services/NemesisService.php:52

    ---------- begin diff ----------
@@ Line 52 @@
         return $this->repository->create($fullRecord);
     }

+    /**
+     * @return array<int, Model|string>
+     */
     public function createWithPlainToken(NemesisTokenRecord $record, Model $tokenable): array
     {
         $tokenConfig = $this->config->tokenConfig();
@@ Line 297 @@
                 } else {
                     $this->repository->delete($token->id);
                 }
-                $count++;
+
+                ++$count;
             }
         }

@@ Line 335 @@
     {
         $token = $this->getCurrentToken($tokenable, $request);

-        if ($token === null) {
+        if (!$token instanceof NemesisToken) {
             return false;
         }

@@ Line 346 @@
     {
         $token = $this->getCurrentToken($tokenable, $request);

-        if ($token === null) {
+        if (!$token instanceof NemesisToken) {
             return false;
         }

@@ Line 413 @@
     {
         $token = $this->getTokenByPlainText($plainToken, $tokenable);

-        if ($token === null) {
+        if (!$token instanceof NemesisToken) {
             return false;
         }

@@ Line 529 @@
             return in_array($ability, $token->abilities);
         }

-        if (is_object($token->abilities) && $token->abilities instanceof StringTypedCollection) {
+        if ($token->abilities instanceof StringTypedCollection) {
             return $token->abilities->contains($ability);
         }

@@ Line 685 @@

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


12) /home/andy-kani/pro/sites/packages/laravel-nemesis/src/ValueObjects/AuthenticationResultVO.php:38

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


13) /home/andy-kani/pro/sites/packages/laravel-nemesis/tests/Fixtures/Models/TestApiClient.php:8

    ---------- begin diff ----------
@@ Line 8 @@
 use Illuminate\Database\Eloquent\SoftDeletes;
 use AndyDefer\Nemesis\Contracts\MustNemesis;
 use AndyDefer\Nemesis\Tests\Fixtures\Records\TestApiClientRecord;
-use AndyDefer\Nemesis\Traits\HasNemesisTokens;

 /**
  * Test model for API clients that can authenticate with tokens.
    ----------- end diff -----------

Applied rules:


14) /home/andy-kani/pro/sites/packages/laravel-nemesis/tests/Integration/Directives/CleanTokensDirectiveTest.php:55

    ---------- begin diff ----------
@@ Line 55 @@
         $this->assertTrue($aliases->contains('tokens-clean'));
         $this->assertTrue($aliases->contains('token-clean'));
         $this->assertTrue($aliases->contains('clean-expired'));
-        $this->assertSame(3, $aliases->count());
+        $this->assertCount(3, $aliases);
     }

     // ==================== Tests: Cleanup Execution ====================
    ----------- end diff -----------

15) /home/andy-kani/pro/sites/packages/laravel-nemesis/tests/Integration/Directives/ListTokensDirectiveTest.php:53

    ---------- begin diff ----------
@@ Line 53 @@

         $this->assertTrue($aliases->contains('tokens-list'));
         $this->assertTrue($aliases->contains('nemesis-tokens'));
-        $this->assertSame(2, $aliases->count());
+        $this->assertCount(2, $aliases);
     }

     public function test_execute_returns_success_when_no_tokens(): void
@@ Line 162 @@
     }

     // ==================== Helper Methods ====================
-
+    /**
+     * @param array<string, string>|array<string, class-string<TestUser>>|array<string, null> $overrides
+     */
     private function createTestToken(array $overrides = []): NemesisToken
     {
         $defaults = [
    ----------- end diff -----------

Applied rules:
 * ClassMethodArrayDocblockParamFromLocalCallsRector


16) /home/andy-kani/pro/sites/packages/laravel-nemesis/tests/Integration/Directives/NemesisCleanDirectiveTest.php:54

    ---------- begin diff ----------
@@ Line 54 @@

         $this->assertTrue($aliases->contains('token-clean'));
         $this->assertTrue($aliases->contains('tokens-clean'));
-        $this->assertSame(2, $aliases->count());
+        $this->assertCount(2, $aliases);
     }

     // ==================== Tests: Cleanup Execution ====================
    ----------- end diff -----------

17) /home/andy-kani/pro/sites/packages/laravel-nemesis/tests/Integration/Helpers/NemesisHelperTest.php:6

    ---------- begin diff ----------
@@ Line 6 @@

 namespace AndyDefer\Nemesis\Tests\Integration\Helpers;

+use Illuminate\Database\Eloquent\Model;
+use AndyDefer\Nemesis\Records\MiddlewareConfigRecord;
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
             $data['current_nemesis_token'] = $tokenRecord;
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
-        $middlewareConfig = $this->hydration->hydrate(\AndyDefer\Nemesis\Records\MiddlewareConfigRecord::class, [
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


18) /home/andy-kani/pro/sites/packages/laravel-nemesis/tests/Integration/Http/Middleware/NemesisTokenMiddlewareTest.php:21

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


19) /home/andy-kani/pro/sites/packages/laravel-nemesis/tests/Integration/Repositories/NemesisTokenRepositoryTest.php:6

    ---------- begin diff ----------
@@ Line 6 @@

 namespace AndyDefer\Nemesis\Tests\Integration\Repositories;

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


20) /home/andy-kani/pro/sites/packages/laravel-nemesis/tests/Integration/Services/HttpHeaderServiceTest.php:6

    ---------- begin diff ----------
@@ Line 6 @@

 namespace AndyDefer\Nemesis\Tests\Integration\Services;

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


21) /home/andy-kani/pro/sites/packages/laravel-nemesis/tests/Integration/Services/NemesisAuthenticationServiceTest.php:6

    ---------- begin diff ----------
@@ Line 6 @@

 namespace AndyDefer\Nemesis\Tests\Integration\Services;

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


22) /home/andy-kani/pro/sites/packages/laravel-nemesis/tests/Integration/Services/NemesisServiceTest.php:6

    ---------- begin diff ----------
@@ Line 6 @@

 namespace AndyDefer\Nemesis\Tests\Integration\Services;

+use AndyDefer\DataValidator\Services\MetadataValidator;
 use AndyDefer\DomainStructures\Collections\Utility\StringTypedCollection;
 use AndyDefer\DomainStructures\Services\HydrationService;
 use AndyDefer\DomainStructures\Utils\StrictDataObject;
@@ Line 13 @@
 use Carbon\Carbon;
 use Illuminate\Http\Request;
 use Illuminate\Support\Str;
-use AndyDefer\Nemesis\Configs\NemesisConfig;
 use AndyDefer\Nemesis\Contracts\Configs\NemesisConfigInterface;
 use AndyDefer\Nemesis\Models\NemesisToken;
 use AndyDefer\Nemesis\Records\NemesisTokenFilterRecord;
@@ Line 27 @@
 final class NemesisServiceTest extends IntegrationTestCase
 {
     private NemesisService $service;
+
     private TestUser $user;
-    private NemesisTokenRepository $repository;
     private HydrationService $hydration;

     protected function setUp(): void
@@ Line 38 @@
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

@@ Line 137 @@

         $found = $this->service->find($token->id);

-        $this->assertNotNull($found);
+        $this->assertInstanceOf(NemesisToken::class, $found);
         $this->assertSame($token->id, $found->id);
     }

@@ Line 145 @@
     {
         $found = $this->service->find(99999);

-        $this->assertNull($found);
+        $this->assertNotInstanceOf(NemesisToken::class, $found);
     }

     public function test_find_with_trashed_returns_deleted_token(): void
@@ Line 155 @@

         $found = $this->service->findWithTrashed($token->id);

-        $this->assertNotNull($found);
+        $this->assertInstanceOf(NemesisToken::class, $found);
         $this->assertNotNull($found->deleted_at);
     }

@@ Line 165 @@

         $found = $this->service->findByHash($token->token_hash);

-        $this->assertNotNull($found);
+        $this->assertInstanceOf(NemesisToken::class, $found);
         $this->assertSame($token->id, $found->id);
     }

@@ Line 191 @@
         $deleted = $this->service->delete($token->id);

         $this->assertTrue($deleted);
-        $this->assertNull($this->service->find($token->id));
+        $this->assertNotInstanceOf(NemesisToken::class, $this->service->find($token->id));
         $this->assertDatabaseHas('nemesis_tokens', ['id' => $token->id]);
         $this->assertDatabaseMissing('nemesis_tokens', ['id' => $token->id, 'deleted_at' => null]);
     }
@@ Line 204 @@
         $restored = $this->service->restore($token->id);

         $this->assertTrue($restored);
-        $this->assertNotNull($this->service->find($token->id));
+        $this->assertInstanceOf(NemesisToken::class, $this->service->find($token->id));
         $this->assertDatabaseHas('nemesis_tokens', ['id' => $token->id, 'deleted_at' => null]);
     }

@@ Line 243 @@
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
@@ Line 542 @@
         $revokedCount = $this->service->revokeAllTokens($this->user);

         $this->assertSame(2, $revokedCount);
-        $this->assertNull($this->service->find($token1->id));
-        $this->assertNull($this->service->find($token2->id));
+        $this->assertNotInstanceOf(NemesisToken::class, $this->service->find($token1->id));
+        $this->assertNotInstanceOf(NemesisToken::class, $this->service->find($token2->id));
         $this->assertDatabaseHas('nemesis_tokens', ['id' => $token1->id]);
         $this->assertDatabaseHas('nemesis_tokens', ['id' => $token2->id]);
     }
@@ Line 558 @@
         $restoredCount = $this->service->restoreAllTokens($this->user);

         $this->assertSame(2, $restoredCount);
-        $this->assertNotNull($this->service->find($token1->id));
-        $this->assertNotNull($this->service->find($token2->id));
+        $this->assertInstanceOf(NemesisToken::class, $this->service->find($token1->id));
+        $this->assertInstanceOf(NemesisToken::class, $this->service->find($token2->id));
     }

     public function test_revoke_tokens_by_source_soft_deletes_matching_tokens(): void
@@ Line 584 @@
         $revokedCount = $this->service->revokeTokensBySource($this->user, 'web');

         $this->assertSame(3, $revokedCount);
-        $this->assertNull($this->service->find($webToken->id));
+        $this->assertNotInstanceOf(NemesisToken::class, $this->service->find($webToken->id));
     }

     public function test_revoke_tokens_by_source_with_force_permanently_deletes(): void
@@ Line 622 @@
         $revokedCount = $this->service->revokeTokensByName($this->user, 'Admin');

         $this->assertSame(3, $revokedCount);
-        $this->assertNull($this->service->find($adminToken->id));
+        $this->assertNotInstanceOf(NemesisToken::class, $this->service->find($adminToken->id));
     }

     public function test_revoke_tokens_by_source_and_name_soft_deletes_matching_tokens(): void
@@ Line 671 @@
         $revokedCount = $this->service->revokeAllTokensExceptSource($this->user, 'mobile');

         $this->assertSame(2, $revokedCount);
-        $this->assertNotNull($this->service->find($mobileToken->id));
+        $this->assertInstanceOf(NemesisToken::class, $this->service->find($mobileToken->id));
     }

     public function test_revoke_all_tokens_except_source_with_force_permanently_deletes(): void
@@ Line 706 @@
         $request = $this->app->make(Request::class);
         $currentToken = $this->service->getCurrentToken($this->user, $request);

-        $this->assertNotNull($currentToken);
+        $this->assertInstanceOf(NemesisToken::class, $currentToken);
         $this->assertSame($token->id, $currentToken->id);
     }

@@ Line 721 @@
         $revoked = $this->service->revokeCurrentToken($this->user, $request);

         $this->assertTrue($revoked);
-        $this->assertNull($this->service->find($token->id));
+        $this->assertNotInstanceOf(NemesisToken::class, $this->service->find($token->id));
         $this->assertDatabaseHas('nemesis_tokens', ['id' => $token->id]);
     }

@@ Line 778 @@

         $found = $this->service->getTokenByPlainText($plainToken, $this->user);

-        $this->assertNotNull($found);
+        $this->assertInstanceOf(NemesisToken::class, $found);
         $this->assertSame($token->id, $found->id);
     }

@@ Line 790 @@

         $found = $this->service->getTokenByPlainText($plainToken, $this->user, withTrashed: true);

-        $this->assertNotNull($found);
+        $this->assertInstanceOf(NemesisToken::class, $found);
         $this->assertSame($token->id, $found->id);
         $this->assertNotNull($found->deleted_at);
     }
@@ Line 830 @@
         $revokedCount = $this->service->revokeExpiredTokens($this->user);

         $this->assertSame(1, $revokedCount);
-        $this->assertNull($this->service->find($expiredToken->id));
+        $this->assertNotInstanceOf(NemesisToken::class, $this->service->find($expiredToken->id));
         $this->assertDatabaseHas('nemesis_tokens', ['id' => $expiredToken->id]);
     }

@@ Line 875 @@
         $revoked = $this->service->revoke($token);

         $this->assertTrue($revoked);
-        $this->assertNull($this->service->find($token->id));
+        $this->assertNotInstanceOf(NemesisToken::class, $this->service->find($token->id));
         $this->assertDatabaseHas('nemesis_tokens', ['id' => $token->id]);
     }

@@ Line 887 @@
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


23) /home/andy-kani/pro/sites/packages/laravel-nemesis/tests/IntegrationTestCase.php:4

    ---------- begin diff ----------
@@ Line 4 @@

 namespace AndyDefer\Nemesis\Tests;

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


24) /home/andy-kani/pro/sites/packages/laravel-nemesis/tests/Unit/Contracts/MustNemesisContractTest.php:39

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


25) /home/andy-kani/pro/sites/packages/laravel-nemesis/tests/UnitTestCase.php:4

    ---------- begin diff ----------
@@ Line 4 @@

 namespace AndyDefer\Nemesis\Tests;

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


 [OK] 25 files would have been changed (dry-run) by Rector                                                              

