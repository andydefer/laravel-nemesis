# Rector Refactoring Report
*Generated: ven. 24 avril 2026 22:38:32 WAT*


14 files with changes
=====================

1) /home/andy-kani/pro/sites/packages/laravel-nemesis/src/Commands/ListTokensCommand.php:108

    ---------- begin diff ----------
@@ Line 108 @@
      * Determine if the no tokens warning should be displayed.
      *
      * @param Collection<int, NemesisToken> $tokens
-     * @return bool
      */
     private function shouldShowNoTokensWarning(Collection $tokens): bool
     {
@@ Line 160 @@

     /**
      * Format the tokenable type for display.
-     *
-     * @param NemesisToken $token
-     * @return string
      */
     private function formatTokenableType(NemesisToken $token): string
     {
@@ Line 171 @@

     /**
      * Format the token name for display.
-     *
-     * @param NemesisToken $token
-     * @return string
      */
     private function formatName(NemesisToken $token): string
     {
@@ Line 182 @@

     /**
      * Format the token source for display.
-     *
-     * @param NemesisToken $token
-     * @return string
      */
     private function formatSource(NemesisToken $token): string
     {
@@ Line 193 @@

     /**
      * Format the last used timestamp for display.
-     *
-     * @param NemesisToken $token
-     * @return string
      */
     private function formatLastUsed(NemesisToken $token): string
     {
@@ Line 204 @@

     /**
      * Format the expiration timestamp for display.
-     *
-     * @param NemesisToken $token
-     * @return string
      */
     private function formatExpiration(NemesisToken $token): string
     {
    ----------- end diff -----------

Applied rules:
 * RemoveUselessParamTagRector
 * RemoveUselessReturnTagRector


2) /home/andy-kani/pro/sites/packages/laravel-nemesis/src/Contracts/MustNemesis.php:4

    ---------- begin diff ----------
@@ Line 4 @@

 namespace Kani\Nemesis\Contracts;

+use Kani\Nemesis\Exceptions\MetadataValidationException;
 use Illuminate\Database\Eloquent\Relations\MorphMany;
 use Kani\Nemesis\Models\NemesisToken;

@@ Line 42 @@
      * @param array<string, mixed>|null $metadata Additional metadata (validated and sanitized)
      * @return string The plain text token (store securely, cannot be retrieved again)
      *
-     * @throws \Kani\Nemesis\Exceptions\MetadataValidationException When metadata is invalid
+     * @throws MetadataValidationException When metadata is invalid
      */
     public function createNemesisToken(
         ?string $name = null,
    ----------- end diff -----------

Applied rules:


3) /home/andy-kani/pro/sites/packages/laravel-nemesis/src/Data/ErrorResponseData.php:98

    ---------- begin diff ----------
@@ Line 98 @@
             'status' => $this->status,
         ];

-        if ($this->details !== null && !empty($this->details)) {
+        if ($this->details !== null && $this->details !== []) {
             $data['details'] = $this->details;
         }

@@ Line 172 @@
      */
     public function hasDetails(): bool
     {
-        return $this->details !== null && !empty($this->details);
+        return $this->details !== null && $this->details !== [];
     }

     /**
    ----------- end diff -----------

Applied rules:
 * DisallowedEmptyRuleFixerRector


4) /home/andy-kani/pro/sites/packages/laravel-nemesis/src/Enums/ErrorCode.php:162

    ---------- begin diff ----------
@@ Line 162 @@
      */
     public function isAuthenticationError(): bool
     {
-        return in_array($this->httpStatusCode(), [401], true);
+        return $this->httpStatusCode() === 401;
     }

     /**
@@ Line 172 @@
      */
     public function isAuthorizationError(): bool
     {
-        return in_array($this->httpStatusCode(), [403], true);
+        return $this->httpStatusCode() === 403;
     }

     /**
    ----------- end diff -----------

Applied rules:
 * SingleInArrayToCompareRector


5) /home/andy-kani/pro/sites/packages/laravel-nemesis/src/Exceptions/MetadataValidationException.php:22

    ---------- begin diff ----------
@@ Line 22 @@
 {
     /**
      * The error code enum.
-     *
-     * @var ErrorCode
      */
     private readonly ErrorCode $errorCode;

@@ Line 101 @@
      */
     public function hasDetails(): bool
     {
-        return $this->details !== null && !empty($this->details);
+        return $this->details !== null && $this->details !== [];
     }

     /**
    ----------- end diff -----------

Applied rules:
 * RemoveUselessVarTagRector
 * DisallowedEmptyRuleFixerRector


6) /home/andy-kani/pro/sites/packages/laravel-nemesis/src/Http/Middleware/NemesisAuth.php:52

    ---------- begin diff ----------
@@ Line 52 @@

         $tokenModel = $this->findValidToken($token);

-        if ($tokenModel === null) {
+        if (!$tokenModel instanceof NemesisToken) {
             return $this->sendErrorResponse(ErrorCode::INVALID_TOKEN);
         }

@@ Line 90 @@
         $response = $next($request);

         $response = $this->applySecurityHeaders($response);
-        $response = $this->applyCorsHeaders($response, $request);

-        return $response;
+        return $this->applyCorsHeaders($response, $request);
     }

     /**
    ----------- end diff -----------

Applied rules:
 * SimplifyUselessVariableRector
 * FlipTypeControlToUseExclusiveTypeRector


7) /home/andy-kani/pro/sites/packages/laravel-nemesis/src/Models/NemesisToken.php:94

    ---------- begin diff ----------
@@ Line 94 @@
         if ($this->isExpired()) {
             return false;
         }
+
         return !$this->trashed();
     }
    ----------- end diff -----------

Applied rules:
 * NewlineAfterStatementRector


8) /home/andy-kani/pro/sites/packages/laravel-nemesis/src/NemesisManager.php:99

    ---------- begin diff ----------
@@ Line 99 @@
     {
         $tokenModel = $this->getTokenModel($token);

-        if ($tokenModel === null || $tokenModel->isExpired()) {
+        if (!$tokenModel instanceof NemesisToken || $tokenModel->isExpired()) {
             return null;
         }

@@ Line 124 @@
     {
         $tokenModel = $model->getNemesisToken($token);

-        if ($tokenModel === null) {
+        if (!$tokenModel instanceof NemesisToken) {
             return false;
         }

@@ Line 208 @@
     {
         $tokenModel = $this->getTokenModel($token);

-        if ($tokenModel === null) {
+        if (!$tokenModel instanceof NemesisToken) {
             return false;
         }

@@ Line 231 @@
     {
         $tokenModel = $this->getTokenModel($token);

-        if ($tokenModel === null) {
+        if (!$tokenModel instanceof NemesisToken) {
             return false;
         }

@@ Line 254 @@
     {
         $tokenModel = $this->getTokenModel($token);

-        if ($tokenModel === null) {
+        if (!$tokenModel instanceof NemesisToken) {
             return null;
         }

@@ Line 274 @@
     {
         $tokenModel = $this->getTokenModel($token);

-        if ($tokenModel === null) {
+        if (!$tokenModel instanceof NemesisToken) {
             return false;
         }
    ----------- end diff -----------

Applied rules:
 * FlipTypeControlToUseExclusiveTypeRector


9) /home/andy-kani/pro/sites/packages/laravel-nemesis/src/Services/TokenMetadataService.php:465

    ---------- begin diff ----------
@@ Line 465 @@
                 if ($value === null) {
                     continue;
                 }
+
                 if ($value === []) {
                     continue;
                 }
    ----------- end diff -----------

Applied rules:
 * NewlineAfterStatementRector


10) /home/andy-kani/pro/sites/packages/laravel-nemesis/src/helpers.php:2

    ---------- begin diff ----------
@@ Line 2 @@

 declare(strict_types=1);

+use Illuminate\Database\Eloquent\Model;
 use Kani\Nemesis\Models\NemesisToken;
 use Kani\Nemesis\NemesisManager;

@@ Line 53 @@
      * Retrieves the authenticatable model (User, ApiClient, etc.) that owns
      * the current token. This is the model that was authenticated via the token.
      *
-     * @return \Illuminate\Database\Eloquent\Model|null The authenticated model or null
+     * @return Model|null The authenticated model or null
      *
      * @example
      * $user = current_authenticatable();
@@ Line 61 @@
      *     $name = $user->name;
      * }
      */
-    function current_authenticatable(): ?\Illuminate\Database\Eloquent\Model
+    function current_authenticatable(): ?Model
     {
         $parameterName = config('nemesis.middleware.parameter_name', 'nemesisAuth');
    ----------- end diff -----------

Applied rules:


11) /home/andy-kani/pro/sites/packages/laravel-nemesis/tests/Unit/Commands/CleanTokensCommandTest.php:98

    ---------- begin diff ----------
@@ Line 98 @@
         $expiredToken = $this->user->createNemesisToken('Expired Token');
         $expiredTokenModel = $this->user->getNemesisToken($expiredToken);
         $expiredTokenModel->expires_at = now()->subDay();
+        $this->assertInstanceOf(NemesisToken::class, $expiredTokenModel);
         $expiredTokenModel->save();

         // Arrange: Create a valid token that should not be deleted
@@ Line 104 @@
         $validToken = $this->user->createNemesisToken('Valid Token');
         $validTokenModel = $this->user->getNemesisToken($validToken);
         $validTokenModel->expires_at = now()->addDays(10);
+        $this->assertInstanceOf(NemesisToken::class, $validTokenModel);
         $validTokenModel->save();

         // Act: Run command with force flag to skip confirmation
@@ Line 113 @@
         $this->assertEquals(0, $exitCode);

         // Assert: Expired token should be deleted
-        $this->assertNull($this->user->getNemesisToken($expiredToken));
+        $this->assertNotInstanceOf(NemesisToken::class, $this->user->getNemesisToken($expiredToken));

         // Assert: Valid token should still exist
-        $this->assertNotNull($this->user->getNemesisToken($validToken));
+        $this->assertInstanceOf(NemesisToken::class, $this->user->getNemesisToken($validToken));
     }

     /**
@@ Line 132 @@
         $oldTokenModel = $this->user->getNemesisToken($oldToken);
         $oldTokenModel->created_at = now()->subDays(40);
         $oldTokenModel->expires_at = null;
+        $this->assertInstanceOf(NemesisToken::class, $oldTokenModel);
         $oldTokenModel->save();

         // Arrange: Create a recent token (created 10 days ago)
@@ Line 139 @@
         $recentTokenModel = $this->user->getNemesisToken($recentToken);
         $recentTokenModel->created_at = now()->subDays(10);
         $recentTokenModel->expires_at = null;
+        $this->assertInstanceOf(NemesisToken::class, $recentTokenModel);
         $recentTokenModel->save();

         // Act: Run command with force flag
@@ Line 148 @@
         $this->assertEquals(0, $exitCode);

         // Assert: Old token should be deleted
-        $this->assertNull($this->user->getNemesisToken($oldToken));
+        $this->assertNotInstanceOf(NemesisToken::class, $this->user->getNemesisToken($oldToken));

         // Assert: Recent token should still exist
-        $this->assertNotNull($this->user->getNemesisToken($recentToken));
+        $this->assertInstanceOf(NemesisToken::class, $this->user->getNemesisToken($recentToken));
     }

     // ============================================================================
@@ Line 171 @@
         $tokenModel = $this->user->getNemesisToken($token);
         $tokenModel->created_at = now()->subDays(20);
         $tokenModel->expires_at = null;
+        $this->assertInstanceOf(NemesisToken::class, $tokenModel);
         $tokenModel->save();

         // Act: Run command with --days=15 (overrides config 30)
@@ Line 180 @@
         $this->assertEquals(0, $exitCode);

         // Assert: Token is deleted (20 days > 15 days threshold)
-        $this->assertNull($this->user->getNemesisToken($token));
+        $this->assertNotInstanceOf(NemesisToken::class, $this->user->getNemesisToken($token));
     }

     /**
@@ Line 193 @@
         $tokenModel = $this->user->getNemesisToken($token);
         $tokenModel->created_at = now()->subDays(20);
         $tokenModel->expires_at = null;
+        $this->assertInstanceOf(NemesisToken::class, $tokenModel);
         $tokenModel->save();

         // Act: Run command with --days=30 (higher than token age)
@@ Line 202 @@
         $this->assertEquals(0, $exitCode);

         // Assert: Token is kept (20 days < 30 days threshold)
-        $this->assertNotNull($this->user->getNemesisToken($token));
+        $this->assertInstanceOf(NemesisToken::class, $this->user->getNemesisToken($token));
     }

     // ============================================================================
@@ Line 218 @@
         $expiredToken = $this->user->createNemesisToken('Expired Token');
         $expiredTokenModel = $this->user->getNemesisToken($expiredToken);
         $expiredTokenModel->expires_at = now()->subDay();
+        $this->assertInstanceOf(NemesisToken::class, $expiredTokenModel);
         $expiredTokenModel->save();

         // Act: Run command with --keep-expired flag
@@ Line 227 @@
         $this->assertEquals(0, $exitCode);

         // Assert: Expired token is NOT deleted (kept by flag)
-        $this->assertNotNull($this->user->getNemesisToken($expiredToken));
+        $this->assertInstanceOf(NemesisToken::class, $this->user->getNemesisToken($expiredToken));
     }

     /**
@@ Line 242 @@
         $expiredToken = $this->user->createNemesisToken('Expired Token');
         $expiredTokenModel = $this->user->getNemesisToken($expiredToken);
         $expiredTokenModel->expires_at = now()->subDay();
+        $this->assertInstanceOf(NemesisToken::class, $expiredTokenModel);
         $expiredTokenModel->save();

         // Arrange: Create an old but not expired token
@@ Line 249 @@
         $oldTokenModel = $this->user->getNemesisToken($oldToken);
         $oldTokenModel->created_at = now()->subDays(40);
         $oldTokenModel->expires_at = null;
+        $this->assertInstanceOf(NemesisToken::class, $oldTokenModel);
         $oldTokenModel->save();

         // Act: Run command with --keep-expired flag
@@ Line 258 @@
         $this->assertEquals(0, $exitCode);

         // Assert: Expired token is kept (due to --keep-expired)
-        $this->assertNotNull($this->user->getNemesisToken($expiredToken));
+        $this->assertInstanceOf(NemesisToken::class, $this->user->getNemesisToken($expiredToken));

         // Assert: Old token is deleted (exceeds retention period)
-        $this->assertNull($this->user->getNemesisToken($oldToken));
+        $this->assertNotInstanceOf(NemesisToken::class, $this->user->getNemesisToken($oldToken));
     }

     // ============================================================================
@@ Line 277 @@
         $expiredToken = $this->user->createNemesisToken('Expired Token');
         $expiredTokenModel = $this->user->getNemesisToken($expiredToken);
         $expiredTokenModel->expires_at = now()->subDay();
+        $this->assertInstanceOf(NemesisToken::class, $expiredTokenModel);
         $expiredTokenModel->save();

         // Act: Run command with force flag (skips confirmation prompt)
@@ Line 286 @@
         $this->assertEquals(0, $exitCode);

         // Assert: Expired token was deleted
-        $this->assertNull($this->user->getNemesisToken($expiredToken));
+        $this->assertNotInstanceOf(NemesisToken::class, $this->user->getNemesisToken($expiredToken));
     }

     // ============================================================================
@@ Line 305 @@
         $expiredToken = $this->user->createNemesisToken('Expired Token');
         $expiredTokenModel = $this->user->getNemesisToken($expiredToken);
         $expiredTokenModel->expires_at = now()->subDay();
+        $this->assertInstanceOf(NemesisToken::class, $expiredTokenModel);
         $expiredTokenModel->save();

         // Arrange: Create an old token (100 days old)
@@ Line 312 @@
         $oldTokenModel = $this->user->getNemesisToken($oldToken);
         $oldTokenModel->created_at = now()->subDays(100);
         $oldTokenModel->expires_at = null;
+        $this->assertInstanceOf(NemesisToken::class, $oldTokenModel);
         $oldTokenModel->save();

         // Act: Run command with force flag
@@ Line 321 @@
         $this->assertEquals(0, $exitCode);

         // Assert: Expired token is deleted (always cleaned)
-        $this->assertNull($this->user->getNemesisToken($expiredToken));
+        $this->assertNotInstanceOf(NemesisToken::class, $this->user->getNemesisToken($expiredToken));

         // Assert: Old token is NOT deleted (retention period is 0)
-        $this->assertNotNull($this->user->getNemesisToken($oldToken));
+        $this->assertInstanceOf(NemesisToken::class, $this->user->getNemesisToken($oldToken));
     }

     /**
@@ Line 340 @@
         $oldTokenModel = $this->user->getNemesisToken($oldToken);
         $oldTokenModel->created_at = now()->subDays(100);
         $oldTokenModel->expires_at = null;
+        $this->assertInstanceOf(NemesisToken::class, $oldTokenModel);
         $oldTokenModel->save();

         // Act: Run command with force flag
@@ Line 349 @@
         $this->assertEquals(0, $exitCode);

         // Assert: Old token is NOT deleted (negative retention disables cleanup)
-        $this->assertNotNull($this->user->getNemesisToken($oldToken));
+        $this->assertInstanceOf(NemesisToken::class, $this->user->getNemesisToken($oldToken));
     }

     /**
@@ Line 381 @@
         $expiredOldModel = $this->user->getNemesisToken($expiredOldToken);
         $expiredOldModel->expires_at = now()->subDay();
         $expiredOldModel->created_at = now()->subDays(40);
+        $this->assertInstanceOf(NemesisToken::class, $expiredOldModel);
         $expiredOldModel->save();

         // Act: Run command with force flag
@@ Line 390 @@
         $this->assertEquals(0, $exitCode);

         // Assert: Token is deleted (counted as expired, not double-counted)
-        $this->assertNull($this->user->getNemesisToken($expiredOldToken));
+        $this->assertNotInstanceOf(NemesisToken::class, $this->user->getNemesisToken($expiredOldToken));
     }

     // ============================================================================
@@ Line 412 @@
         $tokenModel = $this->user->getNemesisToken($token);
         $tokenModel->created_at = now()->subDays(40);
         $tokenModel->expires_at = null;
+        $this->assertInstanceOf(NemesisToken::class, $tokenModel);
         $tokenModel->save();

         // Act: Run command with force flag (no option overrides)
@@ Line 421 @@
         $this->assertEquals(0, $exitCode);

         // Assert: Token is kept (40 days < 45 days retention from config)
-        $this->assertNotNull($this->user->getNemesisToken($token));
+        $this->assertInstanceOf(NemesisToken::class, $this->user->getNemesisToken($token));

         // Assert: Output shows config was used
         $output = Artisan::output();
    ----------- end diff -----------

Applied rules:
 * AddInstanceofAssertForNullableInstanceRector
 * AssertEmptyNullableObjectToAssertInstanceofRector


12) /home/andy-kani/pro/sites/packages/laravel-nemesis/tests/Unit/Commands/ListTokensCommandTest.php:20

    ---------- begin diff ----------
@@ Line 20 @@
 final class ListTokensCommandTest extends TestCase
 {
     private TestUser $user;
+
     private TestApiClient $apiClient;

     protected function setUp(): void
@@ Line 103 @@
         // Arrange: Create tokens
         $this->user->createNemesisToken('User Token 1', 'web');
         $this->user->createNemesisToken('User Token 2', 'mobile');
+
         $this->apiClient->createNemesisToken('API Token', 'api');

         // Act: Execute command
@@ Line 160 @@
         // Assert: Output contains basename (TestUser) not full class path
         $output = Artisan::output();
         $this->assertStringContainsString('TestUser', $output);
-        $this->assertStringNotContainsString('Kani\\Nemesis\\Tests\\Support\\TestUser', $output);
+        $this->assertStringNotContainsString(TestUser::class, $output);
     }

     // ============================================================================
@@ Line 241 @@
         $plainToken = $this->user->createNemesisToken(null, 'web');
         $tokenModel = $this->user->getNemesisToken($plainToken);
         $tokenModel->name = null;
+        $this->assertInstanceOf(NemesisToken::class, $tokenModel);
         $tokenModel->save();

         // Act: Execute command
@@ Line 263 @@
         $plainToken = $this->user->createNemesisToken('Test Token', null);
         $tokenModel = $this->user->getNemesisToken($plainToken);
         $tokenModel->source = null;
+        $this->assertInstanceOf(NemesisToken::class, $tokenModel);
         $tokenModel->save();

         // Act: Execute command
@@ Line 285 @@
         $plainToken = $this->user->createNemesisToken('Test Token', 'web');
         $tokenModel = $this->user->getNemesisToken($plainToken);
         $tokenModel->last_used_at = null;
+        $this->assertInstanceOf(NemesisToken::class, $tokenModel);
         $tokenModel->save();

         // Act: Execute command
@@ Line 307 @@
         $plainToken = $this->user->createNemesisToken('Test Token', 'web');
         $tokenModel = $this->user->getNemesisToken($plainToken);
         $tokenModel->expires_at = null;
+        $this->assertInstanceOf(NemesisToken::class, $tokenModel);
         $tokenModel->save();

         // Act: Execute command
@@ Line 329 @@
         $plainToken = $this->user->createNemesisToken('Test Token', 'web');
         $tokenModel = $this->user->getNemesisToken($plainToken);
         $tokenModel->last_used_at = now()->subDays(5);
-        $tokenModel->expires_at = now()->addDays(7); // Changed to 7 days (1 week)
+        $tokenModel->expires_at = now()->addDays(7);
+        $this->assertInstanceOf(NemesisToken::class, $tokenModel); // Changed to 7 days (1 week)
         $tokenModel->save();

         // Act: Execute command
@@ Line 358 @@
         $firstToken = $this->user->createNemesisToken('First Token', 'web');
         $firstTokenModel = $this->user->getNemesisToken($firstToken);
         $firstTokenModel->created_at = now()->subDays(10);
+        $this->assertInstanceOf(NemesisToken::class, $firstTokenModel);
         $firstTokenModel->save();

         Carbon::setTestNow(Carbon::create(2025, 1, 2, 12, 0, 0));
@@ Line 364 @@
         $secondToken = $this->user->createNemesisToken('Second Token', 'web');
         $secondTokenModel = $this->user->getNemesisToken($secondToken);
         $secondTokenModel->created_at = now()->subDays(5);
+        $this->assertInstanceOf(NemesisToken::class, $secondTokenModel);
         $secondTokenModel->save();

         Carbon::setTestNow(Carbon::create(2025, 1, 3, 12, 0, 0));
@@ Line 370 @@
         $thirdToken = $this->user->createNemesisToken('Third Token', 'web');
         $thirdTokenModel = $this->user->getNemesisToken($thirdToken);
         $thirdTokenModel->created_at = now();
+        $this->assertInstanceOf(NemesisToken::class, $thirdTokenModel);
         $thirdTokenModel->save();

         // Reset time
@@ Line 383 @@

         // Assert: Tokens are ordered by latest first (Third, Second, First)
         $output = Artisan::output();
-        $outputLines = explode("\n", $output);

         // Find the table rows
         $thirdTokenPos = strpos($output, 'Third Token');
@@ Line 425 @@
     {
         // Arrange: Create many tokens
         $tokenCount = 50;
-        for ($i = 0; $i < $tokenCount; $i++) {
-            $this->user->createNemesisToken("Token {$i}", 'web');
+        for ($i = 0; $i < $tokenCount; ++$i) {
+            $this->user->createNemesisToken('Token ' . $i, 'web');
         }

         // Act: Execute command
@@ Line 437 @@

         // Assert: Output contains all tokens
         $output = Artisan::output();
-        for ($i = 0; $i < $tokenCount; $i++) {
-            $this->assertStringContainsString("Token {$i}", $output);
+        for ($i = 0; $i < $tokenCount; ++$i) {
+            $this->assertStringContainsString('Token ' . $i, $output);
         }
     }
    ----------- end diff -----------

Applied rules:
 * NewlineBetweenClassLikeStmtsRector
 * NewlineBeforeNewAssignSetRector
 * EncapsedStringsToSprintfRector
 * PostIncDecToPreIncDecRector
 * RemoveUnusedVariableAssignRector
 * AddInstanceofAssertForNullableInstanceRector
 * StringClassNameToClassConstantRector


13) /home/andy-kani/pro/sites/packages/laravel-nemesis/tests/Unit/Helpers/NemesisHelpersTest.php:4

    ---------- begin diff ----------
@@ Line 4 @@

 namespace Kani\Nemesis\Tests\Unit\Helpers;

+use Illuminate\Database\Eloquent\Model;
 use Illuminate\Http\Request;
 use Illuminate\Routing\Route;
 use Kani\Nemesis\Models\NemesisToken;
@@ Line 28 @@
 final class NemesisHelpersTest extends TestCase
 {
     private TestUser $user;
+
     private TestApiClient $apiClient;
+
     private TestCheckPoint $checkpoint;
+
     private TestCustomFormatUser $customUser;
+
     private string $parameterName;
+
     private string $plainToken;
+
     private NemesisToken $tokenModel;

     protected function setUp(): void
@@ Line 90 @@
      */
     private function createFreshRequest(): Request
     {
-        $request = Request::create('/test', 'GET');
-        $route = new Route('GET', '/test', fn() => null);
+        $request = Request::create('/test', \Symfony\Component\HttpFoundation\Request::METHOD_GET);
+        $route = new Route('GET', '/test', fn(): null => null);

-        $request->setRouteResolver(fn() => $route);
+        $request->setRouteResolver(fn(): Route => $route);
         $route->bind($request);

         return $request;
@@ Line 103 @@
      * Simulate an authenticated request with model, token, and formatted data.
      *
      * @param mixed $model The authenticatable model
-     * @param string $token The plain text token
      * @param NemesisToken $tokenModel The token model
      */
-    private function simulateAuthenticatedRequest($model, string $token, NemesisToken $tokenModel): void
+    private function simulateAuthenticatedRequest(TestUser|TestApiClient|TestCheckPoint|TestCustomFormatUser $model, NemesisToken $tokenModel): void
     {
         $request = $this->createFreshRequest();

@@ Line 166 @@
         $newToken = $manager->createToken($this->user, 'New Token', 'web', ['read']);

         $this->assertIsString($newToken);
-        $this->assertEquals(64, strlen($newToken));
+        $this->assertSame(64, strlen($newToken));

         $tokenModel = $this->user->getNemesisToken($newToken);
         $this->assertInstanceOf(NemesisToken::class, $tokenModel);
@@ Line 181 @@

     public function test_current_token_helper_returns_token_model(): void
     {
-        $this->simulateAuthenticatedRequest($this->user, $this->plainToken, $this->tokenModel);
+        $this->simulateAuthenticatedRequest($this->user, $this->tokenModel);

         $token = current_token();

@@ Line 196 @@

         $token = current_token();

-        $this->assertNull($token);
+        $this->assertNotInstanceOf(NemesisToken::class, $token);
     }

     public function test_current_token_helper_can_check_abilities(): void
@@ Line 207 @@
             ['read', 'write', 'delete']
         );
         $tokenModel = $this->user->getNemesisToken($tokenWithAbilities);
+        $this->assertInstanceOf(NemesisToken::class, $tokenModel);

-        $this->simulateAuthenticatedRequest($this->user, $tokenWithAbilities, $tokenModel);
+        $this->simulateAuthenticatedRequest($this->user, $tokenModel);

         $token = current_token();

@@ Line 227 @@
             ['user_agent' => 'Mozilla/5.0', 'ip' => '127.0.0.1']
         );
         $tokenModel = $this->user->getNemesisToken($metadataToken);
+        $this->assertInstanceOf(NemesisToken::class, $tokenModel);

-        $this->simulateAuthenticatedRequest($this->user, $metadataToken, $tokenModel);
+        $this->simulateAuthenticatedRequest($this->user, $tokenModel);

         $token = current_token();

@@ Line 243 @@

     public function test_current_authenticatable_helper_returns_user(): void
     {
-        $this->simulateAuthenticatedRequest($this->user, $this->plainToken, $this->tokenModel);
+        $this->simulateAuthenticatedRequest($this->user, $this->tokenModel);

         $authenticatable = current_authenticatable();

@@ Line 257 @@
     {
         $token = $this->apiClient->createNemesisToken('API Token', 'api');
         $tokenModel = $this->apiClient->getNemesisToken($token);
+        $this->assertInstanceOf(NemesisToken::class, $tokenModel);

-        $this->simulateAuthenticatedRequest($this->apiClient, $token, $tokenModel);
+        $this->simulateAuthenticatedRequest($this->apiClient, $tokenModel);

         $authenticatable = current_authenticatable();

@@ Line 271 @@
     {
         $token = $this->checkpoint->createNemesisToken('Checkpoint Token', 'kiosk');
         $tokenModel = $this->checkpoint->getNemesisToken($token);
+        $this->assertInstanceOf(NemesisToken::class, $tokenModel);

-        $this->simulateAuthenticatedRequest($this->checkpoint, $token, $tokenModel);
+        $this->simulateAuthenticatedRequest($this->checkpoint, $tokenModel);

         $authenticatable = current_authenticatable();

@@ Line 286 @@
     {
         $token = $this->customUser->createNemesisToken('Custom Token', 'web');
         $tokenModel = $this->customUser->getNemesisToken($token);
+        $this->assertInstanceOf(NemesisToken::class, $tokenModel);

-        $this->simulateAuthenticatedRequest($this->customUser, $token, $tokenModel);
+        $this->simulateAuthenticatedRequest($this->customUser, $tokenModel);

         $authenticatable = current_authenticatable();

@@ Line 303 @@

         $authenticatable = current_authenticatable();

-        $this->assertNull($authenticatable);
+        $this->assertNotInstanceOf(Model::class, $authenticatable);
     }

     // ============================================================================
@@ Line 312 @@

     public function test_current_authenticatable_format_helper_returns_formatted_user(): void
     {
-        $this->simulateAuthenticatedRequest($this->user, $this->plainToken, $this->tokenModel);
+        $this->simulateAuthenticatedRequest($this->user, $this->tokenModel);

         $formatted = current_authenticatable_format();

@@ Line 327 @@
     {
         $token = $this->apiClient->createNemesisToken('API Token', 'api');
         $tokenModel = $this->apiClient->getNemesisToken($token);
+        $this->assertInstanceOf(NemesisToken::class, $tokenModel);

-        $this->simulateAuthenticatedRequest($this->apiClient, $token, $tokenModel);
+        $this->simulateAuthenticatedRequest($this->apiClient, $tokenModel);

         $formatted = current_authenticatable_format();

@@ Line 343 @@
     {
         $token = $this->checkpoint->createNemesisToken('Checkpoint Token', 'kiosk');
         $tokenModel = $this->checkpoint->getNemesisToken($token);
+        $this->assertInstanceOf(NemesisToken::class, $tokenModel);

-        $this->simulateAuthenticatedRequest($this->checkpoint, $token, $tokenModel);
+        $this->simulateAuthenticatedRequest($this->checkpoint, $tokenModel);

         $formatted = current_authenticatable_format();

@@ Line 364 @@

         $token = $this->customUser->createNemesisToken('Custom Token', 'web');
         $tokenModel = $this->customUser->getNemesisToken($token);
+        $this->assertInstanceOf(NemesisToken::class, $tokenModel);

-        $this->simulateAuthenticatedRequest($this->customUser, $token, $tokenModel);
+        $this->simulateAuthenticatedRequest($this->customUser, $tokenModel);

         $formatted = current_authenticatable_format();

@@ Line 399 @@

     public function test_all_helpers_work_together_for_user(): void
     {
-        $this->simulateAuthenticatedRequest($this->user, $this->plainToken, $this->tokenModel);
+        $this->simulateAuthenticatedRequest($this->user, $this->tokenModel);

         $manager = nemesis();
         $this->assertInstanceOf(NemesisManager::class, $manager);
@@ Line 424 @@
     {
         $token = $this->checkpoint->createNemesisToken('Checkpoint Token', 'kiosk');
         $tokenModel = $this->checkpoint->getNemesisToken($token);
+        $this->assertInstanceOf(NemesisToken::class, $tokenModel);

-        $this->simulateAuthenticatedRequest($this->checkpoint, $token, $tokenModel);
+        $this->simulateAuthenticatedRequest($this->checkpoint, $tokenModel);

         $manager = nemesis();
         $this->assertTrue($manager->validateToken($this->checkpoint, $token));
@@ Line 447 @@
     {
         $userToken = $this->user->createNemesisToken('User Token', 'web');
         $userTokenModel = $this->user->getNemesisToken($userToken);
-        $this->simulateAuthenticatedRequest($this->user, $userToken, $userTokenModel);
+        $this->assertInstanceOf(NemesisToken::class, $userTokenModel);
+        $this->simulateAuthenticatedRequest($this->user, $userTokenModel);
         $userFormatted = current_authenticatable_format();

         $checkpointToken = $this->checkpoint->createNemesisToken('Checkpoint Token', 'kiosk');
         $checkpointTokenModel = $this->checkpoint->getNemesisToken($checkpointToken);
-        $this->simulateAuthenticatedRequest($this->checkpoint, $checkpointToken, $checkpointTokenModel);
+        $this->assertInstanceOf(NemesisToken::class, $checkpointTokenModel);
+        $this->simulateAuthenticatedRequest($this->checkpoint, $checkpointTokenModel);
         $checkpointFormatted = current_authenticatable_format();

         $this->assertArrayHasKey('email', $userFormatted);
@@ Line 469 @@
     {
         $userToken = $this->user->createNemesisToken('User Token', 'web');
         $userTokenModel = $this->user->getNemesisToken($userToken);
-        $this->simulateAuthenticatedRequest($this->user, $userToken, $userTokenModel);
+        $this->assertInstanceOf(NemesisToken::class, $userTokenModel);
+        $this->simulateAuthenticatedRequest($this->user, $userTokenModel);
         $userFormatted = current_authenticatable_format();

         $customToken = $this->customUser->createNemesisToken('Custom Token', 'web');
         $customTokenModel = $this->customUser->getNemesisToken($customToken);
-        $this->simulateAuthenticatedRequest($this->customUser, $customToken, $customTokenModel);
+        $this->assertInstanceOf(NemesisToken::class, $customTokenModel);
+        $this->simulateAuthenticatedRequest($this->customUser, $customTokenModel);
         $customFormatted = current_authenticatable_format();

         $this->assertArrayHasKey('id', $userFormatted);
    ----------- end diff -----------

Applied rules:
 * NewlineBetweenClassLikeStmtsRector
 * RemoveUnusedPrivateMethodParameterRector
 * AddInstanceofAssertForNullableArgumentRector
 * AssertEmptyNullableObjectToAssertInstanceofRector
 * AssertEqualsToSameRector
 * LiteralGetToRequestClassConstantRector
 * AddArrowFunctionReturnTypeRector
 * AddMethodCallBasedStrictParamTypeRector


14) /home/andy-kani/pro/sites/packages/laravel-nemesis/tests/Unit/Http/Middleware/NemesisAuthTest.php:1488

    ---------- begin diff ----------
@@ Line 1488 @@
                 'custom_user' => $modelClass::create(['name' => 'Custom User', 'email' => 'custom@test.com']),
             };

-            $plainToken = $model->createNemesisToken("{$type} Token", 'test');
+            $plainToken = $model->createNemesisToken($type . ' Token', 'test');

             $this->resetRequest();
             $this->request->headers->set('Authorization', 'Bearer ' . $plainToken);
@@ Line 1502 @@
             $this->middleware->handle($this->request, $next);

             // Assert: Formatted data exists
-            $this->assertTrue($this->request->has($parameterName . 'Format'), "Failed for type: {$type}");
+            $this->assertTrue($this->request->has($parameterName . 'Format'), 'Failed for type: ' . $type);
             $formatted = $this->request->get($parameterName . 'Format');
-            $this->assertIsArray($formatted, "Not an array for type: {$type}");
+            $this->assertIsArray($formatted, 'Not an array for type: ' . $type);

             $this->nextCalled = false;
         }
    ----------- end diff -----------

Applied rules:
 * EncapsedStringsToSprintfRector


 [OK] 14 files would have been changed (dry-run) by Rector                                                              

