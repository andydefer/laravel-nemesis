# Rector Refactoring Report
*Generated: ven. 24 avril 2026 23:07:50 WAT*


16 files with changes
=====================

1) /home/andy-kani/pro/sites/packages/laravel-nemesis/tests/Unit/Traits/HasNemesisTokensTest.php:171

    ---------- begin diff ----------
@@ Line 171 @@

         // Assert: Token is permanently deleted and method returns true
         $this->assertTrue($result);
-        $this->assertNull($this->testUser->currentNemesisToken());
+        $this->assertNotInstanceOf(NemesisToken::class, $this->testUser->currentNemesisToken());
         $this->assertEquals(0, $this->testUser->nemesisTokens()->withTrashed()->count());
     }

@@ Line 207 @@

         // Assert: Token is soft deleted and method returns true
         $this->assertTrue($result);
-        $this->assertNull($this->testUser->currentNemesisToken());
+        $this->assertNotInstanceOf(NemesisToken::class, $this->testUser->currentNemesisToken());
         $this->assertEquals(1, $this->testUser->nemesisTokens()->withTrashed()->count());
     }

@@ Line 300 @@
         $tokenModel = $this->testUser->getNemesisToken('invalid-token');

         // Assert: Null is returned
-        $this->assertNull($tokenModel);
+        $this->assertNotInstanceOf(NemesisToken::class, $tokenModel);
     }

     /**
@@ Line 317 @@
         $tokenModel->delete();

         // Assert: Without trashed returns null
-        $this->assertNull($this->testUser->getNemesisToken($plainToken));
+        $this->assertNotInstanceOf(NemesisToken::class, $this->testUser->getNemesisToken($plainToken));

         // Assert: With trashed returns the soft deleted token
         $foundToken = $this->testUser->getNemesisToken($plainToken, withTrashed: true);
@@ Line 338 @@
         $plainToken = $this->testUser->createNemesisToken('Test Token');
         $tokenModel = $this->testUser->getNemesisToken($plainToken);
         $tokenModel->expires_at = now()->addDays(10);
+        $this->assertInstanceOf(NemesisToken::class, $tokenModel);
         $tokenModel->save();

         // Assert: Token is valid
@@ Line 362 @@
         $plainToken = $this->testUser->createNemesisToken('Test Token');
         $tokenModel = $this->testUser->getNemesisToken($plainToken);
         $tokenModel->expires_at = now()->addDays(10);
+        $this->assertInstanceOf(NemesisToken::class, $tokenModel);
         $tokenModel->save();
         $tokenModel->delete();

@@ Line 378 @@
         $plainToken = $this->testUser->createNemesisToken('Test Token');
         $tokenModel = $this->testUser->getNemesisToken($plainToken);
         $tokenModel->expires_at = now()->addDays(10);
+        $this->assertInstanceOf(NemesisToken::class, $tokenModel);
         $tokenModel->save();
         $tokenModel->delete();

@@ Line 394 @@
         $plainToken = $this->testUser->createNemesisToken('Test Token');
         $tokenModel = $this->testUser->getNemesisToken($plainToken);
         $tokenModel->expires_at = now()->subDay();
+        $this->assertInstanceOf(NemesisToken::class, $tokenModel);
         $tokenModel->save();
         $tokenModel->delete();

@@ Line 423 @@

         // Assert: Method returns true and last_used_at was updated
         $this->assertTrue($result);
+        $this->assertInstanceOf(NemesisToken::class, $tokenModel);
         $tokenModel->refresh();
         $this->assertNotEquals($originalLastUsed, $tokenModel->last_used_at);
     }
@@ Line 509 @@
         // Arrange: Expire the first token
         $expiredTokenModel = $this->testUser->getNemesisToken($expiredPlainToken);
         $expiredTokenModel->expires_at = now()->subDay();
+        $this->assertInstanceOf(NemesisToken::class, $expiredTokenModel);
         $expiredTokenModel->save();

         // Arrange: Set valid token to future expiration
         $validTokenModel = $this->testUser->getNemesisToken($validPlainToken);
         $validTokenModel->expires_at = now()->addDays(10);
+        $this->assertInstanceOf(NemesisToken::class, $validTokenModel);
         $validTokenModel->save();

         // Act: Revoke expired tokens
@@ Line 542 @@
         $validPlainToken = $this->testUser->createNemesisToken('Valid Token');
         $validTokenModel = $this->testUser->getNemesisToken($validPlainToken);
         $validTokenModel->expires_at = now()->addDays(10);
+        $this->assertInstanceOf(NemesisToken::class, $validTokenModel);
         $validTokenModel->save();

         // Act: Revoke expired tokens
@@ Line 569 @@
         // Arrange: Expire the first token
         $expiredTokenModel = $this->testUser->getNemesisToken($expiredPlainToken);
         $expiredTokenModel->expires_at = now()->subDay();
+        $this->assertInstanceOf(NemesisToken::class, $expiredTokenModel);
         $expiredTokenModel->save();

         // Act: Permanently delete expired tokens
@@ Line 576 @@

         // Assert: Only expired token is permanently deleted
         $this->assertSame(1, $deletedCount);
-        $this->assertNull($this->testUser->getNemesisToken($expiredPlainToken, withTrashed: true));
+        $this->assertNotInstanceOf(NemesisToken::class, $this->testUser->getNemesisToken($expiredPlainToken, withTrashed: true));
         $this->assertInstanceOf(NemesisToken::class, $this->testUser->getNemesisToken($validPlainToken));
     }

@@ Line 596 @@
         // Arrange: Soft delete both tokens
         $tokenModel1 = $this->testUser->getNemesisToken($token1);
         $tokenModel2 = $this->testUser->getNemesisToken($token2);
+        $this->assertInstanceOf(NemesisToken::class, $tokenModel1);
         $tokenModel1->delete();
+        $this->assertInstanceOf(NemesisToken::class, $tokenModel2);
         $tokenModel2->delete();

         // Arrange: Verify they are soft deleted
@@ Line 657 @@

         // Arrange: Verify state: 1 active, 1 soft deleted
         $this->assertInstanceOf(NemesisToken::class, $this->testUser->getNemesisToken($validPlainToken));
-        $this->assertNull($this->testUser->getNemesisToken($revokedPlainToken));
+        $this->assertNotInstanceOf(NemesisToken::class, $this->testUser->getNemesisToken($revokedPlainToken));
         $this->assertInstanceOf(NemesisToken::class, $this->testUser->getNemesisToken($revokedPlainToken, withTrashed: true));
         $this->assertEquals(1, $this->testUser->nemesisTokens()->count());
         $this->assertEquals(1, $this->testUser->nemesisTokens()->onlyTrashed()->count());
@@ Line 691 @@
         $this->testUser->revokeNemesisTokens();

         // Assert: Token is soft deleted
-        $this->assertNull($this->testUser->getNemesisToken($plainToken));
+        $this->assertNotInstanceOf(NemesisToken::class, $this->testUser->getNemesisToken($plainToken));
         $this->assertInstanceOf(NemesisToken::class, $this->testUser->getNemesisToken($plainToken, withTrashed: true));

         // Act: Restore the token
    ----------- end diff -----------

Applied rules:
 * AddInstanceofAssertForNullableInstanceRector
 * AssertEmptyNullableObjectToAssertInstanceofRector


2) /home/andy-kani/pro/sites/packages/laravel-nemesis/src/Commands/ListTokensCommand.php:108

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


3) /home/andy-kani/pro/sites/packages/laravel-nemesis/src/Contracts/MustNemesis.php:4

    ---------- begin diff ----------
@@ Line 4 @@

 namespace Kani\Nemesis\Contracts;

+use Kani\Nemesis\Exceptions\MetadataValidationException;
 use Illuminate\Database\Eloquent\Relations\MorphMany;
 use Kani\Nemesis\Models\NemesisToken;

@@ Line 41 @@
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


4) /home/andy-kani/pro/sites/packages/laravel-nemesis/src/Data/ErrorResponseData.php:98

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


5) /home/andy-kani/pro/sites/packages/laravel-nemesis/src/Enums/ErrorCode.php:162

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


6) /home/andy-kani/pro/sites/packages/laravel-nemesis/src/Exceptions/MetadataValidationException.php:22

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


7) /home/andy-kani/pro/sites/packages/laravel-nemesis/src/Http/Middleware/NemesisAuth.php:52

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


8) /home/andy-kani/pro/sites/packages/laravel-nemesis/src/Models/NemesisToken.php:94

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


9) /home/andy-kani/pro/sites/packages/laravel-nemesis/src/NemesisManager.php:99

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


10) /home/andy-kani/pro/sites/packages/laravel-nemesis/src/Services/TokenMetadataService.php:465

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


11) /home/andy-kani/pro/sites/packages/laravel-nemesis/src/Traits/HasNemesisTokens.php:4

    ---------- begin diff ----------
@@ Line 4 @@

 namespace Kani\Nemesis\Traits;

+use Kani\Nemesis\Exceptions\MetadataValidationException;
 use Illuminate\Database\Eloquent\SoftDeletes;
 use DateTimeInterface;
 use Illuminate\Database\Eloquent\Model;
@@ Line 44 @@
      * @param array<string, mixed>|null $metadata Additional metadata
      * @return string The plain text token (store securely, cannot be retrieved again)
      *
-     * @throws \Kani\Nemesis\Exceptions\MetadataValidationException When metadata is invalid
+     * @throws MetadataValidationException When metadata is invalid
      */
     public function createNemesisToken(
         ?string $name = null,
    ----------- end diff -----------

Applied rules:


12) /home/andy-kani/pro/sites/packages/laravel-nemesis/src/helpers.php:2

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


13) /home/andy-kani/pro/sites/packages/laravel-nemesis/tests/Unit/Commands/CleanTokensCommandTest.php:98

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


14) /home/andy-kani/pro/sites/packages/laravel-nemesis/tests/Unit/Commands/ListTokensCommandTest.php:20

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


15) /home/andy-kani/pro/sites/packages/laravel-nemesis/tests/Unit/Helpers/NemesisHelpersTest.php:4

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
@@ Line 94 @@
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
@@ Line 107 @@
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

@@ Line 192 @@

         // Assert: Token is a 64-character string
         $this->assertIsString($newToken);
-        $this->assertEquals(64, strlen($newToken));
+        $this->assertSame(64, strlen($newToken));

         // Assert: Token is correctly stored in database
         $tokenModel = $this->user->getNemesisToken($newToken);
@@ Line 212 @@
     public function test_current_token_helper_returns_token_model(): void
     {
         // Arrange: Simulate an authenticated request
-        $this->simulateAuthenticatedRequest($this->user, $this->plainToken, $this->tokenModel);
+        $this->simulateAuthenticatedRequest($this->user, $this->tokenModel);

         // Act: Get the current token
         $token = current_token();
@@ Line 235 @@
         $token = current_token();

         // Assert: Null is returned
-        $this->assertNull($token);
+        $this->assertNotInstanceOf(NemesisToken::class, $token);
     }

     /**
@@ Line 250 @@
             ['read', 'write', 'delete']
         );
         $tokenModel = $this->user->getNemesisToken($tokenWithAbilities);
+        $this->assertInstanceOf(NemesisToken::class, $tokenModel);

         // Arrange: Simulate an authenticated request with this token
-        $this->simulateAuthenticatedRequest($this->user, $tokenWithAbilities, $tokenModel);
+        $this->simulateAuthenticatedRequest($this->user, $tokenModel);

         // Act: Get the current token
         $token = current_token();
@@ Line 279 @@
             ['user_agent' => 'Mozilla/5.0', 'ip' => '127.0.0.1']
         );
         $tokenModel = $this->user->getNemesisToken($metadataToken);
+        $this->assertInstanceOf(NemesisToken::class, $tokenModel);

         // Arrange: Simulate an authenticated request with this token
-        $this->simulateAuthenticatedRequest($this->user, $metadataToken, $tokenModel);
+        $this->simulateAuthenticatedRequest($this->user, $tokenModel);

         // Act: Get the current token
         $token = current_token();
@@ Line 302 @@
     public function test_current_authenticatable_helper_returns_user(): void
     {
         // Arrange: Simulate an authenticated request with user
-        $this->simulateAuthenticatedRequest($this->user, $this->plainToken, $this->tokenModel);
+        $this->simulateAuthenticatedRequest($this->user, $this->tokenModel);

         // Act: Get the current authenticatable
         $authenticatable = current_authenticatable();
@@ Line 322 @@
         // Arrange: Create token for API client
         $token = $this->apiClient->createNemesisToken('API Token', 'api');
         $tokenModel = $this->apiClient->getNemesisToken($token);
+        $this->assertInstanceOf(NemesisToken::class, $tokenModel);

         // Arrange: Simulate an authenticated request with API client
-        $this->simulateAuthenticatedRequest($this->apiClient, $token, $tokenModel);
+        $this->simulateAuthenticatedRequest($this->apiClient, $tokenModel);

         // Act: Get the current authenticatable
         $authenticatable = current_authenticatable();
@@ Line 343 @@
         // Arrange: Create token for checkpoint
         $token = $this->checkpoint->createNemesisToken('Checkpoint Token', 'kiosk');
         $tokenModel = $this->checkpoint->getNemesisToken($token);
+        $this->assertInstanceOf(NemesisToken::class, $tokenModel);

         // Arrange: Simulate an authenticated request with checkpoint
-        $this->simulateAuthenticatedRequest($this->checkpoint, $token, $tokenModel);
+        $this->simulateAuthenticatedRequest($this->checkpoint, $tokenModel);

         // Act: Get the current authenticatable
         $authenticatable = current_authenticatable();
@@ Line 365 @@
         // Arrange: Create token for custom user
         $token = $this->customUser->createNemesisToken('Custom Token', 'web');
         $tokenModel = $this->customUser->getNemesisToken($token);
+        $this->assertInstanceOf(NemesisToken::class, $tokenModel);

         // Arrange: Simulate an authenticated request with custom user
-        $this->simulateAuthenticatedRequest($this->customUser, $token, $tokenModel);
+        $this->simulateAuthenticatedRequest($this->customUser, $tokenModel);

         // Act: Get the current authenticatable
         $authenticatable = current_authenticatable();
@@ Line 391 @@
         $authenticatable = current_authenticatable();

         // Assert: Null is returned
-        $this->assertNull($authenticatable);
+        $this->assertNotInstanceOf(Model::class, $authenticatable);
     }

     // ============================================================================
@@ Line 404 @@
     public function test_current_authenticatable_format_helper_returns_formatted_user(): void
     {
         // Arrange: Simulate an authenticated request with user
-        $this->simulateAuthenticatedRequest($this->user, $this->plainToken, $this->tokenModel);
+        $this->simulateAuthenticatedRequest($this->user, $this->tokenModel);

         // Act: Get the formatted authenticatable data
         $formatted = current_authenticatable_format();
@@ Line 425 @@
         // Arrange: Create token for API client
         $token = $this->apiClient->createNemesisToken('API Token', 'api');
         $tokenModel = $this->apiClient->getNemesisToken($token);
+        $this->assertInstanceOf(NemesisToken::class, $tokenModel);

         // Arrange: Simulate an authenticated request with API client
-        $this->simulateAuthenticatedRequest($this->apiClient, $token, $tokenModel);
+        $this->simulateAuthenticatedRequest($this->apiClient, $tokenModel);

         // Act: Get the formatted authenticatable data
         $formatted = current_authenticatable_format();
@@ Line 450 @@
         // Arrange: Create token for checkpoint
         $token = $this->checkpoint->createNemesisToken('Checkpoint Token', 'kiosk');
         $tokenModel = $this->checkpoint->getNemesisToken($token);
+        $this->assertInstanceOf(NemesisToken::class, $tokenModel);

         // Arrange: Simulate an authenticated request with checkpoint
-        $this->simulateAuthenticatedRequest($this->checkpoint, $token, $tokenModel);
+        $this->simulateAuthenticatedRequest($this->checkpoint, $tokenModel);

         // Act: Get the formatted authenticatable data
         $formatted = current_authenticatable_format();
@@ Line 479 @@
         // Arrange: Create token for custom user
         $token = $this->customUser->createNemesisToken('Custom Token', 'web');
         $tokenModel = $this->customUser->getNemesisToken($token);
+        $this->assertInstanceOf(NemesisToken::class, $tokenModel);

         // Arrange: Simulate an authenticated request with custom user
-        $this->simulateAuthenticatedRequest($this->customUser, $token, $tokenModel);
+        $this->simulateAuthenticatedRequest($this->customUser, $tokenModel);

         // Act: Get the formatted authenticatable data
         $formatted = current_authenticatable_format();
@@ Line 530 @@
     public function test_all_helpers_work_together_for_user(): void
     {
         // Arrange: Simulate an authenticated request with user
-        $this->simulateAuthenticatedRequest($this->user, $this->plainToken, $this->tokenModel);
+        $this->simulateAuthenticatedRequest($this->user, $this->tokenModel);

         // Act & Assert: nemesis helper returns manager and validates token
         $manager = nemesis();
@@ Line 564 @@
         // Arrange: Create token for checkpoint
         $token = $this->checkpoint->createNemesisToken('Checkpoint Token', 'kiosk');
         $tokenModel = $this->checkpoint->getNemesisToken($token);
+        $this->assertInstanceOf(NemesisToken::class, $tokenModel);

         // Arrange: Simulate an authenticated request with checkpoint
-        $this->simulateAuthenticatedRequest($this->checkpoint, $token, $tokenModel);
+        $this->simulateAuthenticatedRequest($this->checkpoint, $tokenModel);

         // Act & Assert: nemesis validates token
         $manager = nemesis();
@@ Line 596 @@
         // Arrange: Simulate user authentication
         $userToken = $this->user->createNemesisToken('User Token', 'web');
         $userTokenModel = $this->user->getNemesisToken($userToken);
-        $this->simulateAuthenticatedRequest($this->user, $userToken, $userTokenModel);
+        $this->assertInstanceOf(NemesisToken::class, $userTokenModel);
+        $this->simulateAuthenticatedRequest($this->user, $userTokenModel);
         $userFormatted = current_authenticatable_format();

         // Arrange: Simulate checkpoint authentication
         $checkpointToken = $this->checkpoint->createNemesisToken('Checkpoint Token', 'kiosk');
         $checkpointTokenModel = $this->checkpoint->getNemesisToken($checkpointToken);
-        $this->simulateAuthenticatedRequest($this->checkpoint, $checkpointToken, $checkpointTokenModel);
+        $this->assertInstanceOf(NemesisToken::class, $checkpointTokenModel);
+        $this->simulateAuthenticatedRequest($this->checkpoint, $checkpointTokenModel);
         $checkpointFormatted = current_authenticatable_format();

         // Assert: User has email, CheckPoint does not
@@ Line 626 @@
         // Arrange: Simulate regular user authentication
         $userToken = $this->user->createNemesisToken('User Token', 'web');
         $userTokenModel = $this->user->getNemesisToken($userToken);
-        $this->simulateAuthenticatedRequest($this->user, $userToken, $userTokenModel);
+        $this->assertInstanceOf(NemesisToken::class, $userTokenModel);
+        $this->simulateAuthenticatedRequest($this->user, $userTokenModel);
         $userFormatted = current_authenticatable_format();

         // Arrange: Simulate custom user authentication
         $customToken = $this->customUser->createNemesisToken('Custom Token', 'web');
         $customTokenModel = $this->customUser->getNemesisToken($customToken);
-        $this->simulateAuthenticatedRequest($this->customUser, $customToken, $customTokenModel);
+        $this->assertInstanceOf(NemesisToken::class, $customTokenModel);
+        $this->simulateAuthenticatedRequest($this->customUser, $customTokenModel);
         $customFormatted = current_authenticatable_format();

         // Assert: Regular user uses 'id' and 'email', custom user uses 'user_id' and 'full_name'
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


16) /home/andy-kani/pro/sites/packages/laravel-nemesis/tests/Unit/Http/Middleware/NemesisAuthTest.php:1488

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


 [OK] 16 files would have been changed (dry-run) by Rector                                                              

