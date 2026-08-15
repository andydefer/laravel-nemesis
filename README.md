# Nemesis - Authentification par tokens multi-modèles pour Laravel

**Un package d'authentification complet pour Laravel permettant à n'importe quel modèle Eloquent de générer, valider et gérer ses propres tokens d'API avec sécurité renforcée.**

[![PHP Version](https://img.shields.io/badge/PHP-8.2%2B-blue)](https://php.net)
[![Laravel Version](https://img.shields.io/badge/Laravel-12.x%20%7C%2013.x%20%7C%2014.x%20%7C%2015.x-blue)](https://laravel.com)
[![License](https://img.shields.io/badge/License-MIT-green)](LICENSE)

---

## Table des matières

1. [Installation](#installation)
2. [Pourquoi Nemesis ?](#pourquoi-nemesis-)
3. [Architecture et concepts clés](#architecture-et-concepts-clés)
4. [Préparation du modèle](#préparation-du-modèle)
5. [Les middlewares](#les-middlewares)
6. [Les services](#les-services)
7. [Les directives CLI](#les-directives-cli)
8. [Le Helper](#le-helper)
9. [Les repositories](#les-repositories)
10. [Cas d'usage concrets](#cas-dusage-concrets)
11. [Configuration](#configuration)
12. [Bonnes pratiques](#bonnes-pratiques)
13. [Référence des commandes](#référence-des-commandes)
14. [Licence](#licence)

---

## Installation

```bash
composer require andydefer/laravel-nemesis

# Installation automatique
./vendor/bin/directive nemesis:install --force

# Ou manuellement
php artisan vendor:publish --tag=nemesis-config
php artisan vendor:publish --tag=nemesis-migrations
php artisan migrate
```

**Prérequis :** PHP 8.2+ | Laravel 12.x, 13.x, 14.x ou 15.x

---

## Pourquoi Nemesis ?

**Le problème :** Laravel Sanctum ne permet d'authentifier que le modèle `User`. Si vous avez plusieurs types d'utilisateurs (`User`, `CheckPoint`, `Admin`, `ApiClient`, `Shop`), vous devez réécrire la même logique pour chacun.

**La solution :** Nemesis. Un seul système d'authentification pour tous vos modèles.

```php
// ✅ Un seul système pour tous vos modèles

// Authentifier un utilisateur
$record = NemesisTokenRecord::from(['name' => 'User Token']);
[$token, $plainToken] = $nemesisService->createWithPlainToken($record, $user);

// Authentifier un point de contrôle
$record = NemesisTokenRecord::from(['name' => 'CheckPoint Token']);
[$token, $plainToken] = $nemesisService->createWithPlainToken($record, $checkpoint);

// Authentifier un administrateur
$record = NemesisTokenRecord::from(['name' => 'Admin Token']);
[$token, $plainToken] = $nemesisService->createWithPlainToken($record, $admin);
```

### Comparatif rapide

| Fonctionnalité | Sanctum | Nemesis |
|----------------|---------|---------|
| Multi-modèles (User + CheckPoint) | ❌ | ✅ |
| Contrôle explicite des données exposées | ❌ | ✅ (méthode obligatoire) |
| Révocation granulaire par source/nom | ❌ | ✅ |
| Restrictions CORS par token | ❌ | ✅ |
| Métadonnées enrichies | ❌ | ✅ |
| Soft delete des tokens | ❌ | ✅ |
| CLI avec Directives (pas Artisan) | ❌ | ✅ |
| Abilities sans user | ❌ | ✅ |

---

## Architecture et concepts clés

### Structure du package

```
Nemesis
├── Directives/           # Commandes CLI
│   ├── CleanTokensDirective.php
│   ├── InstallNemesisDirective.php
│   └── ListTokensDirective.php
├── Helpers/              # Helper pour accéder aux données
│   └── NemesisHelper.php
├── Http/
│   └── Middleware/       # 6 middlewares
│       ├── NemesisTokenMiddleware.php
│       ├── NemesisWebMiddleware.php
│       ├── NemesisGuestMiddleware.php
│       ├── NemesisApiGuestMiddleware.php
│       ├── NemesisApiVerifiedMiddleware.php      # ✅ NOUVEAU
│       └── NemesisWebVerifiedMiddleware.php      # ✅ NOUVEAU
├── Models/               # Modèle Eloquent
│   └── NemesisToken.php
├── Repositories/         # Repository
│   └── NemesisTokenRepository.php
└── Services/             # 5 services
    ├── NemesisService.php
    ├── NemesisAuthenticationService.php
    ├── CookieTokenStorageService.php
    ├── HttpHeaderService.php
    └── MetadataValidatorService.php
```

### Les middlewares

Le package fournit **6 middlewares** pour différents cas d'usage :

| Middleware | Alias | Rôle |
|------------|-------|------|
| `NemesisTokenMiddleware` | `nemesis.token` | Protège les routes API (Bearer token) |
| `NemesisWebMiddleware` | `nemesis.web` | Protège les routes web (cookie) |
| `NemesisGuestMiddleware` | `nemesis.guest` | Redirige les utilisateurs authentifiés (web) |
| `NemesisApiGuestMiddleware` | `nemesis.api.guest` | Bloque les utilisateurs authentifiés (API) |
| `NemesisApiVerifiedMiddleware` | `nemesis.api.verified` | Protège les routes API + vérification email |
| `NemesisWebVerifiedMiddleware` | `nemesis.web.verified` | Protège les routes web + vérification email |

### Les services

Le package expose **5 services** :

| Service | Interface | Rôle |
|---------|-----------|------|
| `NemesisService` | `NemesisInterface` | Gestion complète des tokens (CRUD, métadonnées, abilities) |
| `NemesisAuthenticationService` | `NemesisAuthenticationInterface` | Authentification des requêtes |
| `CookieTokenStorageService` | `CookieTokenStorageInterface` | Stockage des tokens dans les cookies |
| `HttpHeaderService` | `HttpHeaderInterface` | Application des en-têtes de sécurité et CORS |
| `MetadataValidatorService` | `MetadataValidatorInterface` | Validation des métadonnées |

---

## Préparation du modèle

### 1. Implémenter l'interface `MustNemesis`

```php
<?php

namespace App\Models;

use AndyDefer\Nemesis\Contracts\MustNemesis;
use AndyDefer\DomainStructures\Abstracts\AbstractData;
use App\Datas\UserData;

class User extends Model implements MustNemesis
{
    // ✅ Méthode OBLIGATOIRE
    public function nemesisFormat(): AbstractData
    {
        return new UserData(
            id: $this->id,
            name: $this->name,
            email: $this->email,
            emailVerifiedAt: $this->email_verified_at?->toIso8601String(),
            createdAt: $this->created_at?->toIso8601String(),
            updatedAt: $this->updated_at?->toIso8601String(),
        );
    }
}
```

### 2. Créer le Data Object

```php
<?php

namespace App\Datas;

use AndyDefer\DomainStructures\Abstracts\AbstractData;

class UserData extends AbstractData
{
    public function __construct(
        public readonly int $id,
        public readonly string $name,
        public readonly string $email,
        public readonly ?string $emailVerifiedAt,
        public readonly ?string $createdAt,
        public readonly ?string $updatedAt,
    ) {}
}
```

### 3. Exemple avec un modèle Shop

```php
<?php

namespace App\Models;

use AndyDefer\Nemesis\Contracts\MustNemesis;
use App\Datas\ShopData;

class Shop extends Model implements MustNemesis
{
    public function nemesisFormat(): AbstractData
    {
        return new ShopData(
            id: $this->id,
            name: $this->name,
            email: $this->email,
            ownerName: $this->owner_name,
            siret: $this->siret,
            isActive: $this->is_active,
        );
    }
}
```

---

## Les middlewares

### 1. `nemesis.token` - API Token Middleware

Protège les routes API avec Bearer token.

**Utilisation :**

```php
// routes/api.php
Route::middleware('nemesis.token')->group(function () {
    Route::get('/profile', [ProfileController::class, 'show']);
    Route::post('/update', [ProfileController::class, 'update']);
});

// Avec vérification d'ability
Route::post('/admin', [AdminController::class, 'action'])
    ->middleware('nemesis.token:admin');
```

**Comportement :**
- Extrait le token du header `Authorization: Bearer {token}`
- Valide le token
- Injecte l'utilisateur dans la requête via le paramètre configuré (défaut: `nemesis_auth`)
- Injecte le token dans la requête via `current_nemesis_token`
- Injecte les données formatées via `{param_name}_format`

**Réponse en cas d'erreur :**

```json
{
    "errorCode": "MISSING_TOKEN",
    "message": "Token not provided",
    "status": 401,
    "details": null
}
```

**Accès aux données dans les contrôleurs :**

```php
use AndyDefer\Nemesis\Helpers\NemesisHelper;

class ProfileController
{
    public function __construct(
        private readonly NemesisHelper $helper,
    ) {}

    public function show()
    {
        $user = $this->helper->getCurrentAuthenticatable();
        $formatted = $this->helper->getCurrentAuthenticatableFormat();
        $token = $this->helper->getCurrentToken();
        
        return response()->json([
            'user' => $formatted,
            'token_id' => $token?->id,
        ]);
    }
}
```

---

### 2. `nemesis.web` - Web Middleware

Protège les routes web avec token stocké dans un cookie.

**Utilisation :**

```php
// routes/web.php
Route::middleware('nemesis.web')->group(function () {
    Route::get('/dashboard', function () {
        return inertia('DashboardScreen');
    });
});

// Avec ability
Route::get('/admin', function () {
    return inertia('AdminScreen');
})->middleware('nemesis.web:admin');
```

**Comportement :**
- Récupère le token du cookie configuré (`config('nemesis.web.cookie_name')`)
- Ajoute le token dans le header `Authorization`
- Valide le token via `NemesisAuthenticationService`
- Si le token est invalide ou absent, redirection vers `config('nemesis.web.login_route')`
- Si l'ability est manquante, retourne 403 Forbidden

**Configuration :**

```php
// config/nemesis.php
'web' => [
    'login_route' => '/login',
    'cookie_name' => 'nemesis_token',
    'cookie_secure' => true,
    'cookie_httponly' => true,
    'cookie_samesite' => 'lax',
],
```

---

### 3. `nemesis.guest` - Web Guest Middleware

Protège les routes web réservées aux invités (login, register).

**Utilisation :**

```php
Route::get('/login', function () {
    return inertia('Auth/LoginScreen');
})->middleware('nemesis.guest')->name('login.show');

// Avec ability : les utilisateurs avec l'ability 'admin' sont redirigés
Route::get('/user-only', function () {
    return inertia('UserOnlyPage');
})->middleware('nemesis.guest:admin');
```

**Comportement :**
- Vérifie si un token valide existe dans le cookie
- Si l'utilisateur est authentifié, redirection vers `config('nemesis.web.dashboard_route')`
- Si une ability est spécifiée, redirige uniquement si l'utilisateur la possède
- Si l'utilisateur n'est pas authentifié, accès autorisé

---

### 4. `nemesis.api.guest` - API Guest Middleware

Protège les endpoints API réservés aux invités (login, register).

**Utilisation :**

```php
Route::post('/api/login', [AuthController::class, 'login'])
    ->middleware('nemesis.api.guest');

Route::post('/api/register', [AuthController::class, 'register'])
    ->middleware('nemesis.api.guest:admin');
```

**Comportement :**
- Vérifie si un Bearer token valide est présent
- Si l'utilisateur est authentifié, retourne une erreur 400
- Si une ability est spécifiée, bloque uniquement si l'utilisateur la possède
- Si l'utilisateur n'est pas authentifié, accès autorisé

**Réponse en cas de blocage :**

```json
{
    "errorCode": "ALREADY_AUTHENTICATED",
    "message": "Already authenticated",
    "status": 400,
    "details": null
}
```

---

### 5. `nemesis.api.verified` - API Verified Middleware ✅ NOUVEAU

Protège les routes API avec Bearer token ET vérifie que l'email de l'utilisateur est validé.

**Utilisation :**

```php
// routes/api.php
Route::middleware('nemesis.api.verified')->group(function () {
    Route::get('/profile', [ProfileController::class, 'show']);
    Route::post('/orders', [OrderController::class, 'store']);
});

// Sans ability
Route::middleware('nemesis.api.verified')->get('/dashboard', [DashboardController::class, 'index']);
```

**Comportement :**
- Extrait le token du header `Authorization: Bearer {token}`
- Valide le token
- Vérifie que le modèle a la colonne `email_verified_at` via `Schema::hasColumn()`
- Vérifie que `email_verified_at` n'est pas `null`
- Injecte l'utilisateur dans la requête via le paramètre configuré (défaut: `nemesis_auth`)
- Injecte le token dans la requête via `current_nemesis_token`
- Injecte les données formatées via `{param_name}_format`

**Réponse en cas d'erreur :**

```json
{
    "errorCode": "EMAIL_NOT_VERIFIED",
    "message": "Email not verified. Please verify your email address.",
    "status": 403,
    "details": null
}
```

**Réponse si la colonne email_verified_at est manquante :**

```json
{
    "errorCode": "MODEL_MISSING_EMAIL_VERIFIED_AT",
    "message": "Model must have email_verified_at field",
    "status": 500,
    "details": null
}
```

---

### 6. `nemesis.web.verified` - Web Verified Middleware ✅ NOUVEAU

Protège les routes web avec cookie ET vérifie que l'email de l'utilisateur est validé.

**Utilisation :**

```php
// routes/web.php
Route::middleware('nemesis.web.verified')->group(function () {
    Route::get('/dashboard', function () {
        return inertia('DashboardScreen');
    });
    Route::get('/profile', [ProfileController::class, 'edit']);
});
```

**Comportement :**
- Récupère le token du cookie configuré (`config('nemesis.web.cookie_name')`)
- Ajoute le token dans le header `Authorization`
- Valide le token via `NemesisAuthenticationService`
- Vérifie que le modèle a la colonne `email_verified_at` via `Schema::hasColumn()`
- Vérifie que `email_verified_at` n'est pas `null`
- Si le token est invalide ou absent, redirection vers `config('nemesis.web.login_route')`
- Si l'email n'est pas vérifié, redirection vers `config('nemesis.web.verification_route')`

**Configuration :**

```php
// config/nemesis.php
'web' => [
    'login_route' => '/login',
    'dashboard_route' => '/dashboard',
    'verification_route' => '/verify-email',  // ✅ NOUVEAU
    'cookie_name' => 'nemesis_token',
    'cookie_secure' => true,
    'cookie_httponly' => true,
    'cookie_samesite' => 'lax',
],
```

---

## Les services

### 1. `NemesisService` - Gestion des tokens

Service principal pour la gestion complète des tokens.

**Injection :**

```php
use AndyDefer\Nemesis\Contracts\Services\NemesisInterface;

class TokenManager
{
    public function __construct(
        private readonly NemesisInterface $nemesisService,
    ) {}
}
```

**Création de token :**

```php
use AndyDefer\Nemesis\Records\NemesisTokenRecord;

$record = NemesisTokenRecord::from([
    'name' => 'API Token',
    'source' => 'api',
    'abilities' => ['read', 'write'],
    'metadata' => ['device' => 'iPhone 15'],
]);

// Création avec token généré automatiquement
[$token, $plainToken] = $nemesisService->createWithPlainToken($record, $user);

// Création avec hash existant
$token = $nemesisService->create($record, $user);
```

**Récupération :**

```php
// Par ID
$token = $nemesisService->find($tokenId);

// Par hash
$token = $nemesisService->findByHash($hashedToken);

// Par filtres
$filters = NemesisTokenFilterRecord::from([
    'tokenable_type' => 'App\Models\User',
    'source' => 'web',
]);
$tokens = $nemesisService->findByFilters($filters, 10);
```

**Mise à jour :**

```php
$record = NemesisTokenRecord::from([
    'name' => 'Updated Token Name',
    'abilities' => ['read', 'write', 'admin'],
]);

$token = $nemesisService->update($tokenId, $record);
```

**Suppression :**

```php
// Soft delete
$nemesisService->delete($tokenId);

// Suppression définitive
$nemesisService->forceDelete($tokenId);

// Restauration
$nemesisService->restore($tokenId);
```

**Révocation sélective :**

```php
// Révoquer tous les tokens web d'un utilisateur
$count = $nemesisService->revokeTokensBySource($user, 'web');

// Révoquer tous les tokens avec un nom spécifique
$count = $nemesisService->revokeTokensByName($user, 'Mobile Token');

// Révoquer par source ET nom
$count = $nemesisService->revokeTokensBySourceAndName($user, 'web', 'Session Token');

// Révoquer tous les tokens sauf une source
$count = $nemesisService->revokeAllTokensExceptSource($user, 'mobile');
```

**Révocation en masse :**

```php
$filters = NemesisTokenFilterRecord::from([
    'tokenable_type' => 'App\Models\User',
    'is_expired' => true,
]);

// Soft delete en masse
$count = $nemesisService->deleteBulk($filters);

// Suppression définitive en masse
$count = $nemesisService->forceDeleteBulk($filters);
```

**Gestion des métadonnées :**

```php
// Ajouter une métadonnée
$nemesisService->setMetadata($token, 'last_ip', $request->ip());

// Fusionner des métadonnées
$nemesisService->mergeMetadata($token, [
    'session_id' => $sessionId,
    'user_agent' => $request->userAgent(),
]);

// Récupérer une métadonnée
$device = $nemesisService->getMetadata($token, 'device', 'unknown');

// Récupérer toutes les métadonnées
$allMetadata = $nemesisService->getAllMetadata($token);

// Supprimer une métadonnée
$nemesisService->removeMetadata($token, 'temporary_key');

// Supprimer toutes les métadonnées
$nemesisService->clearMetadata($token);
```

**Gestion des origines autorisées :**

```php
// Ajouter une origine
$nemesisService->addAllowedOrigin($token, 'https://monapp.com');
$nemesisService->addAllowedOrigin($token, 'https://*.example.com');

// Supprimer une origine
$nemesisService->removeAllowedOrigin($token, 'https://ancien-site.com');

// Remplacer toutes les origines
$nemesisService->setAllowedOrigins($token, ['https://nouveau-site.com']);
```

**Vérification des permissions :**

```php
// Vérifier une ability
if ($nemesisService->can($token, 'admin')) {
    // Action admin
}

// Vérifier toutes les abilities
if ($nemesisService->canAll($token, ['read', 'write'])) {
    // Lecture et écriture autorisées
}

// Vérifier l'origine
if ($nemesisService->canUseFromOrigin($token, $origin)) {
    // Origine autorisée
}

// Vérifier l'origine de la requête courante
if ($nemesisService->canUseFromCurrentRequest($token, $request)) {
    // Origine autorisée
}
```

**Validation :**

```php
// Valider un token
$isValid = $nemesisService->validateToken($plainToken, $user);

// Valider avec inclusion des tokens révoqués
$isValid = $nemesisService->validateToken($plainToken, $user, true);

// Toucher le token (mettre à jour last_used_at)
$nemesisService->touchToken($plainToken, $user);

// Compter les tokens correspondant aux filtres
$count = $nemesisService->count($filters);

// Vérifier l'existence
$exists = $nemesisService->exists($filters);
```

**Expiration :**

```php
// Expirer un token immédiatement
$nemesisService->forceExpire($token);

// Expirer un token dans X minutes
$nemesisService->forceExpireByMinutes($token, 30);

// Révoquer tous les tokens expirés d'un modèle
$count = $nemesisService->revokeExpiredTokens($user);

// Supprimer définitivement tous les tokens expirés d'un modèle
$count = $nemesisService->forceDeleteExpiredTokens($user);

// Révoquer tous les tokens expirés globalement
$count = $nemesisService->revokeAllExpiredTokensGlobally();

// Supprimer définitivement tous les tokens expirés globalement
$count = $nemesisService->forceDeleteAllExpiredTokensGlobally();
```

**Recherche :**

```php
// Trouver tous les tokens actifs
$activeTokens = $nemesisService->findAllActive();

// Trouver tous les tokens expirés
$expiredTokens = $nemesisService->findAllExpired();

// Trouver tous les tokens révoqués
$revokedTokens = $nemesisService->findAllRevoked();
```

---

### 2. `NemesisAuthenticationService` - Authentification

Service d'authentification des requêtes.

**Injection :**

```php
use AndyDefer\Nemesis\Contracts\Services\NemesisAuthenticationInterface;

class AuthMiddleware
{
    public function __construct(
        private readonly NemesisAuthenticationInterface $authService,
    ) {}
}
```

**Authentification :**

```php
// Authentification standard
$result = $this->authService->authenticate($request);

// Avec vérification d'ability
$result = $this->authService->authenticate($request, 'admin');

// Avec token fourni (au lieu de l'extraire de la requête)
$result = $this->authService->authenticate($request, null, $plainToken);

if ($result->isSuccess()) {
    $user = $result->getAuthenticatable();
    $token = $result->getTokenRecord();
    $formatted = $this->authService->getFormattedAuthenticatable($user);
} else {
    $errorCode = $result->getErrorCode();
    // MISSING_TOKEN, INVALID_TOKEN, TOKEN_EXPIRED, 
    // INSUFFICIENT_PERMISSIONS, ORIGIN_NOT_ALLOWED
}
```

**Authentification vers Record :**

```php
$record = $this->authService->authenticateToRecord($request, 'admin');

if ($record->success) {
    $user = $record->authenticatable;
    $token = $record->token_record;
}
```

**Formatage :**

```php
$formatted = $this->authService->getFormattedAuthenticatable($user);
if ($formatted) {
    return response()->json($formatted);
}
```

---

### 3. `CookieTokenStorageService` - Stockage en cookie

Gestion des tokens dans les cookies.

**Injection :**

```php
use AndyDefer\Nemesis\Contracts\Services\CookieTokenStorageInterface;

class AuthService
{
    public function __construct(
        private readonly CookieTokenStorageInterface $cookieStorage,
    ) {}
}
```

**Stockage :**

```php
// Stocker un token
$this->cookieStorage->store($plainToken);
```

**Récupération :**

```php
// Récupérer le token en clair
$plainToken = $this->cookieStorage->get($request);

// Récupérer le token validé
$token = $this->cookieStorage->getValidatedToken($request);

// Récupérer l'utilisateur
$user = $this->cookieStorage->getAuthenticatable($request);
```

**Vérification :**

```php
// Vérifier si le cookie existe
if ($this->cookieStorage->has($request)) {
    // Cookie présent
}

// Vérifier si le token est valide
if ($this->cookieStorage->isValid($request)) {
    // L'utilisateur est authentifié
}
```

**Suppression :**

```php
// Supprimer le cookie
$this->cookieStorage->forget();
```

---

### 4. `HttpHeaderService` - En-têtes HTTP

Application des en-têtes de sécurité et CORS.

**Injection :**

```php
use AndyDefer\Nemesis\Contracts\Services\HttpHeaderInterface;

class ResponseHandler
{
    public function __construct(
        private readonly HttpHeaderInterface $headerService,
    ) {}
}
```

**En-têtes de sécurité :**

```php
$response = $this->headerService->applySecurityHeaders($response);
```

**En-têtes appliqués :**
- `X-Frame-Options: DENY`
- `X-XSS-Protection: 1; mode=block`
- `X-Content-Type-Options: nosniff`
- `Referrer-Policy: strict-origin-when-cross-origin`
- `Strict-Transport-Security` (production uniquement)

**En-têtes CORS :**

```php
$response = $this->headerService->applyCorsHeaders($response, $request);
```

**En-têtes CORS sur les erreurs :**

```php
$errorResponse = $this->headerService->addCorsToErrorResponse($errorResponse, $request);
```

**Configuration :**

```php
// config/nemesis.php
'middleware' => [
    'security_headers' => true,
    'validate_origin' => true,
],
'cors' => [
    'allow_credentials' => true,
    'max_age' => 86400,
    'expose_token_info' => false,
],
```

---

### 5. `MetadataValidatorService` - Validation des métadonnées

Validation des métadonnées avec contraintes de sécurité.

**Injection :**

```php
use AndyDefer\Nemesis\Contracts\Services\MetadataValidatorInterface;

class TokenService
{
    public function __construct(
        private readonly MetadataValidatorInterface $validator,
    ) {}
}
```

**Validation :**

```php
try {
    $validated = $this->validator->validate($metadata);
    // Métadonnées valides
} catch (MetadataValidationException $e) {
    // Erreur de validation
}

if ($this->validator->isValid($metadata)) {
    // Métadonnées valides
}
```

**Assainissement :**

```php
// Supprime les valeurs null et tableaux vides
$clean = $this->validator->sanitize($metadata);
```

**Validation + Assainissement :**

```php
$processed = $this->validator->process($metadata);
// Validation + assainissement en une étape
```

**Métadonnées :**

```php
// Taille en octets
$size = $this->validator->getSize($metadata);

// Profondeur d'imbrication
$depth = $this->validator->getNestingDepth($metadata);
```

**Contraintes :**

| Contrainte | Limite |
|------------|--------|
| Taille totale | 64KB |
| Profondeur d'imbrication | 5 niveaux |
| Nombre de clés | 100 |
| Longueur des clés | 255 caractères |

---

## Les directives CLI

Les directives sont exécutables via `./vendor/bin/directive` ou `./bin/directive` selon votre configuration.

### 1. `nemesis:install` - Installation

Installe le package, publie la configuration et les migrations, exécute les migrations.

```bash
# Installation standard avec confirmation
./vendor/bin/directive nemesis:install

# Installation forcée (écrase les fichiers existants)
./vendor/bin/directive nemesis:install --force

# Alias
./vendor/bin/directive nemesis-install --force
./vendor/bin/directive setup-nemesis --force
```

**Ce que fait la directive :**
1. Vérifie que le package existe dans `vendor/andydefer/laravel-nemesis`
2. Copie la configuration vers `config/nemesis.php`
3. Copie la migration vers `database/migrations/`
4. Exécute les migrations via `php artisan migrate`
5. Vérifie que la table `nemesis_tokens` a été créée
6. Affiche la configuration chargée
7. Affiche les prochaines étapes

**Sortie typique :**

```bash
$ ./vendor/bin/directive nemesis:install --force

🔐 Nemesis Installation
📦 Checking package files...
  ✓ Package found
📄 Publishing configuration...
  ✓ Config published to config/nemesis.php
🗄️ Publishing migration...
  ✓ Migration published
🗄️ Running migrations...
  ✓ Migrations executed
✅ Verifying database table...
  ✓ Table "nemesis_tokens" exists
⚙️ Configuration loaded:
  token_length      64
  hash_algorithm    sha256
  expiration        60
  validate_origin   true

------------------------------------------------------------
📋 Installation summary:

Package verified                 andydefer/laravel-nemesis found
Configuration published           config/nemesis.php
Migration published               2024_01_01_000001_create_nemesis_tokens_table.php
Migrations executed               Table nemesis_tokens created
Configuration validated           Token length: 64

============================================================
✨ Nemesis package installed successfully!

📝 Next steps:
  1. Implement MustNemesis on your models
  2. Define nemesisFormat()
  3. Create tokens: [$token, $plainToken] = $nemesisService->createWithPlainToken($record, $user)
  4. Protect routes: Route::middleware(["nemesis.token"])->group(...)
  5. Use NemesisHelper: $helper->getCurrentAuthenticatable()

[READY] ✅
```

---

### 2. `nemesis:clean-tokens` - Nettoyage

Nettoie les tokens expirés et anciens.

```bash
# Nettoyage standard avec confirmation
./vendor/bin/directive nemesis:clean-tokens

# Nettoyage forcé sans confirmation
./vendor/bin/directive nemesis:clean-tokens --force

# Avec période de rétention personnalisée (15 jours)
./vendor/bin/directive nemesis:clean-tokens 15 --force

# Garder les tokens expirés (ne supprimer que les anciens)
./vendor/bin/directive nemesis:clean-tokens 30 --keep-expired --force

# Alias
./vendor/bin/directive nemesis-tc --force
./vendor/bin/directive nemesis-ce --force
```

**Ce que fait la directive :**
1. Demande confirmation (sauf si `--force`)
2. Supprime les tokens expirés (sauf si `--keep-expired`)
3. Supprime les tokens anciens (créés avant la date limite)
4. Affiche les statistiques de nettoyage
5. Affiche le résumé de configuration

**Filtres appliqués :**
- `is_expired` = true (pour les tokens expirés)
- `created_before` = now() - days (pour les tokens anciens)

**Sortie typique :**

```bash
$ ./vendor/bin/directive nemesis:clean-tokens --force
Using retention period from config: 30 days
Deleted 5 expired tokens
Deleted 12 old tokens (older than 30 days)

═══════════════════════════════════════════════════════
🧹 TOKEN CLEANUP COMPLETED
═══════════════════════════════════════════════════════
┌─────────────────────────┬─────────┐
│ Metric                  │ Count   │
├─────────────────────────┼─────────┤
│ Expired tokens deleted  │ 5       │
│ Old tokens deleted      │ 12      │
│ ━━━━━━━━━━━━━━━━━━━━━   │ ━━━━━━━ │
│ Total tokens deleted    │ 17      │
└─────────────────────────┴─────────┘

✅ Cleanup completed successfully!

📋 Current Configuration:
   • Auto cleanup: ✅ Enabled
   • Cleanup frequency: 60 minutes
   • Retention period: 30 days
   • Validate origin: ✅ Enabled
   • Expired tokens: ✅ Removed
```

---

### 3. `nemesis:list-tokens` - Liste des tokens

Liste tous les tokens du système.

```bash
# Affiche les 50 premiers tokens (défaut)
./vendor/bin/directive nemesis:list-tokens

# Affiche 20 tokens
./vendor/bin/directive nemesis:list-tokens 20

# Filtrer par modèle (correspondance partielle)
./vendor/bin/directive nemesis:list-tokens 30 --model=User

# Alias
./vendor/bin/directive tokens-list 15
./vendor/bin/directive nemesis-tokens 25 --model=Admin
```

**Sortie typique :**

```bash
$ ./vendor/bin/directive nemesis:list-tokens 10

Filtering by model: User

┌────┬────────────────┬──────────────┬──────────────┬────────┬────────────────┬──────────────────┐
│ ID │ Tokenable Type │ Tokenable ID │ Name         │ Source │ Last Used      │ Expires At       │
├────┼────────────────┼──────────────┼──────────────┼────────┼────────────────┼──────────────────┤
│ 1  │ User           │ 42           │ Web Session  │ web    │ 2 hours ago    │ 1 day from now   │
│ 2  │ User           │ 42           │ Mobile Token │ mobile │ 5 minutes ago  │ 2 hours from now │
│ 3  │ ApiClient      │ 15           │ API Key      │ api    │ Never          │ Never            │
│ 4  │ Admin          │ 3            │ Admin Token  │ admin  │ 3 days ago     │ Expired 2 days   │
│ 5  │ User           │ 100          │ Session      │ web    │ 1 hour ago     │ 1 day from now   │
└────┴────────────────┴──────────────┴──────────────┴────────┴────────────────┴──────────────────┘

Total tokens: 5
```

---

# Les Helpers

Nemesis fournit deux helpers pour accéder facilement aux informations du token et de l'utilisateur authentifié.

## 1. `NemesisHelper` - Helper standard

Ce helper dépend des middlewares pour fonctionner. Il lit les données **uniquement** si elles ont été injectées dans la requête par un middleware (`nemesis.token`, `nemesis.web`, etc.).

**Injection :**

```php
use AndyDefer\Nemesis\Helpers\NemesisHelper;

class ProfileController
{
    public function __construct(
        private readonly NemesisHelper $helper,
    ) {}

    public function show()
    {
        $user = $this->helper->getCurrentAuthenticatable();
        // ...
    }
}
```

**⚠️ Important :** Ce helper ne fonctionne que si un middleware a été exécuté avant.

```php
// ✅ Fonctionne
Route::middleware('nemesis.token')->get('/profile', function () {
    $helper = app(NemesisHelper::class);
    $user = $helper->getCurrentAuthenticatable(); // ✅ Utilisateur trouvé
});

// ❌ Ne fonctionne pas
Route::get('/profile', function () {
    $helper = app(NemesisHelper::class);
    $user = $helper->getCurrentAuthenticatable(); // ❌ null
});
```

---

## 2. `AutonomousNemesisHelper` - Helper autonome (Recommandé)

Ce helper est **autonome**. Il peut fonctionner **avec ou sans middleware** en lisant directement le token depuis le cookie.

**Injection :**

```php
use AndyDefer\Nemesis\Helpers\AutonomousNemesisHelper;

class ProfileController
{
    public function __construct(
        private readonly AutonomousNemesisHelper $helper,
    ) {}

    public function show()
    {
        $user = $this->helper->getCurrentAuthenticatable();
        // ...
    }
}
```

**Fonctionnement :**

1. Il vérifie d'abord si les données sont injectées par un middleware
2. Si ce n'est pas le cas, il lit directement le token depuis le cookie
3. Il valide le token et récupère l'utilisateur associé

**Exemples :**

```php
// ✅ Avec middleware
Route::middleware('nemesis.token')->get('/profile', function () {
    $helper = app(AutonomousNemesisHelper::class);
    $user = $helper->getCurrentAuthenticatable(); // ✅ Utilisateur trouvé
});

// ✅ Sans middleware (lecture directe du cookie)
Route::get('/profile', function () {
    $helper = app(AutonomousNemesisHelper::class);
    $user = $helper->getCurrentAuthenticatable(); // ✅ Utilisateur trouvé via cookie
});
```

---

## Comparaison

| Fonctionnalité | NemesisHelper | AutonomousNemesisHelper |
|----------------|---------------|------------------------|
| Dépend d'un middleware | ✅ Oui | ❌ Non (autonome) |
| Lit depuis le cookie | ❌ Non | ✅ Oui |
| Lecture depuis la requête | ✅ Oui | ✅ Oui |
| Cache intégré | ✅ Oui | ✅ Oui |
| Utilisation recommandée | ⚠️ Avec middleware | ✅ Toujours |

---

## Méthodes disponibles (les deux helpers)

### Récupération des données

```php
// Récupérer l'utilisateur
$user = $helper->getCurrentAuthenticatable();
$formatted = $helper->getCurrentAuthenticatableFormat();

// Récupérer le token
$token = $helper->getCurrentToken();
$tokenId = $helper->getTokenId();
$tokenName = $helper->getTokenName();
$tokenSource = $helper->getTokenSource();
$tokenableId = $helper->getTokenableId();
$tokenableType = $helper->getTokenableType();

// Récupérer les abilities
$abilities = $helper->getTokenAbilities();

// Récupérer les métadonnées
$metadata = $helper->getTokenMetadata();

// Récupérer les origines autorisées
$origins = $helper->getTokenAllowedOrigins();

// Dates
$lastUsed = $helper->getTokenLastUsedAt();
$expiresAt = $helper->getTokenExpirationDate();
```

### Vérifications

```php
// Vérifier si authentifié
if ($helper->isAuthenticated()) {
    // Utilisateur connecté
}

// Vérifier si invité
if ($helper->isGuest()) {
    // Utilisateur non connecté
}

// Vérifier la présence du token
if ($helper->hasCurrentToken()) {
    // Token présent
}

// Vérifier la présence de l'utilisateur
if ($helper->hasCurrentAuthenticatable()) {
    // Utilisateur présent
}

// Vérifier la validité du token
if ($helper->isTokenValid()) {
    // Token valide
}

// Vérifier l'expiration
if ($helper->isTokenExpired()) {
    // Token expiré
}

// Vérifier une ability
if ($helper->tokenHasAbility('admin')) {
    // A la permission admin
}

// Vérifier toutes les abilities
if ($helper->tokenHasAllAbilities(['read', 'write'])) {
    // A toutes les permissions
}

// Vérifier une origine
if ($helper->isOriginAllowed($origin)) {
    // Origine autorisée
}
```

### Nettoyage

```php
// Vider le cache du helper
$helper->clear();
```

---

## Exemple complet

```php
use AndyDefer\Nemesis\Helpers\AutonomousNemesisHelper;

class DashboardController extends Controller
{
    public function __construct(
        private readonly AutonomousNemesisHelper $helper,
    ) {}

    public function index()
    {
        // Vérifier l'authentification
        if (!$this->helper->isAuthenticated()) {
            return redirect('/login');
        }

        // Récupérer l'utilisateur
        $user = $this->helper->getCurrentAuthenticatable();
        $formatted = $this->helper->getCurrentAuthenticatableFormat();

        // Vérifier les permissions
        $isAdmin = $this->helper->tokenHasAbility('admin');

        // Vérifier l'expiration proche
        $expiresAt = $this->helper->getTokenExpirationDate();
        $expiringSoon = $expiresAt && $expiresAt->diffInMinutes(now()) < 10;

        return view('dashboard', [
            'user' => $formatted,
            'isAdmin' => $isAdmin,
            'expiringSoon' => $expiringSoon,
        ]);
    }
}
```

---

## Quelle helper utiliser ?

| Cas d'usage | Helper recommandé |
|-------------|-------------------|
| Vous utilisez les middlewares Nemesis | Les deux fonctionnent |
| Vous ne voulez pas dépendre des middlewares | `AutonomousNemesisHelper` |
| Vous voulez la solution la plus robuste | `AutonomousNemesisHelper` |
| Vous voulez la solution la plus simple | `AutonomousNemesisHelper` |

> 💡 **Recommandation :** Utilisez **toujours** `AutonomousNemesisHelper`. Il est plus flexible et fonctionne dans tous les cas.

---

## Les repositories

`NemesisTokenRepository` fournit une abstraction pour les opérations de base de données sur les tokens.

**Injection :**

```php
use AndyDefer\Nemesis\Contracts\Repositories\NemesisTokenRepositoryInterface;

class TokenRepositoryService
{
    public function __construct(
        private readonly NemesisTokenRepositoryInterface $repository,
    ) {}
}
```

**Recherche :**

```php
use AndyDefer\Nemesis\Records\NemesisTokenFilterRecord;

// Recherche avec soft delete
$tokens = $this->repository->findWithTrashedByFilters($filters);

// Vérification d'existence avec soft delete
$exists = $this->repository->existsWithTrashed($filters);

// Restauration en masse
$count = $this->repository->restoreBulkForTokenable('App\Models\User', 42);
```

**Filtres disponibles :**

| Filtre | Type | Description |
|--------|------|-------------|
| `token_hash` | `string|null` | Hash exact du token |
| `tokenable_type` | `string|null` | Type du modèle (exact) |
| `tokenable_id` | `int|null` | ID du modèle |
| `name` | `string|null` | Nom (recherche LIKE) |
| `source` | `string|null` | Source (exact) |
| `is_expired` | `bool|null` | Token expiré ou non |
| `is_revoked` | `bool|null` | Soft-deleted ou non |
| `created_before` | `DateTimeVO|null` | Créé avant cette date |

---

## Cas d'usage concrets

### 1. Billeterie avec User et CheckPoint

**Modèles :**

```php
// User (client)
class User extends Model implements MustNemesis
{
    public function nemesisFormat(): AbstractData
    {
        return new UserData(
            id: $this->id,
            name: $this->name,
            email: $this->email,
            tickets_count: $this->tickets()->count(),
        );
    }
}

// CheckPoint (point de contrôle)
class CheckPoint extends Model implements MustNemesis
{
    public function nemesisFormat(): AbstractData
    {
        return new CheckPointData(
            id: $this->id,
            name: $this->name,
            location: $this->location,
            status: $this->is_active ? 'active' : 'inactive',
        );
    }
}
```

**Création des tokens :**

```php
use AndyDefer\Nemesis\Contracts\Services\NemesisInterface;
use AndyDefer\Nemesis\Records\NemesisTokenRecord;

class TicketAuthService
{
    public function __construct(
        private readonly NemesisInterface $nemesisService,
    ) {}

    public function createUserToken(User $user): string
    {
        $record = NemesisTokenRecord::from([
            'name' => 'App Mobile Client',
            'source' => 'mobile',
            'abilities' => ['buy_ticket', 'view_tickets'],
            'metadata' => ['device' => 'iPhone 15', 'app_version' => '2.1.0'],
        ]);

        [$token, $plainToken] = $this->nemesisService->createWithPlainToken($record, $user);
        return $plainToken;
    }

    public function createCheckPointToken(CheckPoint $checkpoint): string
    {
        $record = NemesisTokenRecord::from([
            'name' => 'Scanner Portique',
            'source' => 'kiosk',
            'abilities' => ['scan_ticket', 'validate_entry', 'reject_entry'],
            'metadata' => ['hardware_id' => 'SCAN-01', 'location' => 'Entrée A'],
        ]);

        [$token, $plainToken] = $this->nemesisService->createWithPlainToken($record, $checkpoint);
        return $plainToken;
    }
}
```

**Routes protégées :**

```php
// routes/api.php
Route::middleware('nemesis.token:buy_ticket')->post('/tickets', [TicketController::class, 'buy']);
Route::middleware('nemesis.token:scan_ticket')->post('/scan', [ScanController::class, 'validate']);
Route::middleware('nemesis.token:validate_entry')->post('/validate', [ScanController::class, 'entry']);
```

**Contrôleur :**

```php
use AndyDefer\Nemesis\Helpers\NemesisHelper;

class ScanController extends Controller
{
    public function __construct(
        private readonly NemesisHelper $helper,
        private readonly NemesisInterface $nemesisService,
    ) {}

    public function validate(Request $request)
    {
        $checkpoint = $this->helper->getCurrentAuthenticatable();
        $token = $this->helper->getCurrentToken();

        if (!$this->nemesisService->can($token, 'scan_ticket')) {
            return response()->json(['error' => 'Permission refusée'], 403);
        }

        // Scanner le ticket...
        return response()->json([
            'status' => 'entrée validée',
            'checkpoint' => $this->helper->getCurrentAuthenticatableFormat()
        ]);
    }
}
```

---

### 2. Multi-modèles avec endpoints distincts

```php
// routes/api.php
Route::middleware('nemesis.token')->get('/profile', function () {
    $helper = app(NemesisHelper::class);
    $user = $helper->getCurrentAuthenticatable();
    $formatted = $helper->getCurrentAuthenticatableFormat();

    if ($user instanceof User) {
        return response()->json([
            'type' => 'user',
            'data' => $formatted,
            'role' => $user->role,
        ]);
    }

    if ($user instanceof Admin) {
        return response()->json([
            'type' => 'admin',
            'data' => $formatted,
            'permissions' => $user->permissions,
        ]);
    }

    if ($user instanceof ApiClient) {
        return response()->json([
            'type' => 'api_client',
            'data' => $formatted,
            'rate_limit' => $user->rate_limit,
        ]);
    }

    return response()->json(['error' => 'Unknown user type'], 400);
});
```

---

### 3. Gestion des sessions avec déconnexion sélective

```php
class SessionManager
{
    public function __construct(
        private readonly NemesisInterface $nemesisService,
    ) {}

    // Déconnexion de tous les navigateurs (garder l'app mobile)
    public function logoutAllBrowsers(User $user): void
    {
        $this->nemesisService->revokeTokensBySource($user, 'web');
    }

    // Déconnexion d'un device spécifique
    public function logoutDevice(User $user, string $deviceName): void
    {
        $this->nemesisService->revokeTokensByName($user, $deviceName);
    }

    // Garder uniquement l'app mobile active
    public function keepOnlyMobile(User $user): void
    {
        $this->nemesisService->revokeAllTokensExceptSource($user, 'mobile');
    }

    // Nettoyer les tokens expirés
    public function cleanExpiredTokens(User $user): void
    {
        $this->nemesisService->revokeExpiredTokens($user);
    }

    // Liste des sessions actives
    public function getActiveSessions(User $user): Collection
    {
        return $this->nemesisService->getTokensFor($user);
    }
}
```

---

### 4. Tokens avec restrictions d'origine

```php
class ApiTokenService
{
    public function __construct(
        private readonly NemesisInterface $nemesisService,
    ) {}

    public function createRestrictedToken(User $user, array $origins): string
    {
        $record = NemesisTokenRecord::from([
            'name' => 'API Token',
            'source' => 'api',
            'abilities' => ['read', 'write'],
            'allowed_origins' => $origins,
        ]);

        [$token, $plainToken] = $this->nemesisService->createWithPlainToken($record, $user);
        return $plainToken;
    }

    public function validateOrigin(string $plainToken, string $origin): bool
    {
        $hashedToken = hash('sha256', $plainToken);
        $token = $this->nemesisService->findByHash($hashedToken);

        if (!$token) {
            return false;
        }

        return $this->nemesisService->canUseFromOrigin($token, $origin);
    }
}
```

**Utilisation :**

```php
// Création d'un token restreint
$plainToken = $apiTokenService->createRestrictedToken($user, [
    'https://monapp.com',
    'https://*.example.com', // Wildcard
]);

// Vérification dans le middleware
if (!$apiTokenService->validateOrigin($plainToken, $request->header('Origin'))) {
    return response()->json(['error' => 'Origin not allowed'], 403);
}
```

---

### 5. API avec métadonnées de tracking

```php
class TrackingService
{
    public function __construct(
        private readonly NemesisInterface $nemesisService,
        private readonly NemesisHelper $helper,
    ) {}

    public function trackRequest(Request $request)
    {
        $token = $this->helper->getCurrentToken();
        
        if (!$token) {
            return;
        }

        // Ajouter des métadonnées de tracking
        $this->nemesisService->mergeMetadata($token, [
            'last_request' => now()->toIso8601String(),
            'ip' => $request->ip(),
            'user_agent' => $request->userAgent(),
            'route' => $request->route()?->getName(),
            'method' => $request->method(),
        ]);

        // Incrémenter le compteur de requêtes
        $requestCount = $this->nemesisService->getMetadata($token, 'request_count', 0);
        $this->nemesisService->setMetadata($token, 'request_count', $requestCount + 1);
    }

    public function getTokenStats(NemesisToken $token): array
    {
        return [
            'device' => $this->nemesisService->getMetadata($token, 'device', 'unknown'),
            'ip' => $this->nemesisService->getMetadata($token, 'ip', 'unknown'),
            'requests' => $this->nemesisService->getMetadata($token, 'request_count', 0),
            'last_used' => $token->last_used_at?->toIso8601String(),
            'created' => $token->created_at->toIso8601String(),
        ];
    }
}
```

---

## Configuration

```php
// config/nemesis.php

<?php

declare(strict_types=1);

return [
    /*
    |--------------------------------------------------------------------------
    | Token Length
    |--------------------------------------------------------------------------
    | Longueur des tokens générés. Recommandé: 64 ou plus.
    */
    'token_length' => 64,

    /*
    |--------------------------------------------------------------------------
    | Hash Algorithm
    |--------------------------------------------------------------------------
    | Algorithme de hashage. Supporté: 'sha256', 'sha512'
    */
    'hash_algorithm' => 'sha256',

    /*
    |--------------------------------------------------------------------------
    | Token Expiration (in minutes)
    |--------------------------------------------------------------------------
    | Expiration des tokens en minutes. null = jamais.
    */
    'expiration' => 60,

    /*
    |--------------------------------------------------------------------------
    | Middleware Configuration
    |--------------------------------------------------------------------------
    */
    'middleware' => [
        'parameter_name' => 'nemesis_auth',
        'token_header' => 'Authorization',
        'security_headers' => true,
        'validate_origin' => true,
    ],

    /*
    |--------------------------------------------------------------------------
    | CORS Configuration
    |--------------------------------------------------------------------------
    */
    'cors' => [
        'allow_credentials' => true,
        'max_age' => 86400, // 24 heures
        'expose_token_info' => false,
    ],

    /*
    |--------------------------------------------------------------------------
    | Cleanup Configuration
    |--------------------------------------------------------------------------
    | Nettoyage automatique des tokens expirés.
    */
    'cleanup' => [
        'auto_cleanup' => true,
        'frequency' => 60, // Toutes les heures
        'keep_expired_for_days' => 30,
    ],

    /*
    |--------------------------------------------------------------------------
    | Web Configuration
    |--------------------------------------------------------------------------
    | Configuration pour les routes web.
    */
    'web' => [
        'login_route' => '/login',
        'dashboard_route' => '/dashboard',
        'verification_route' => '/verify-email', // ✅ NOUVEAU
        'cookie_name' => 'nemesis_token',
        'cookie_secure' => true,
        'cookie_httponly' => true,
        'cookie_samesite' => 'lax',
    ],
];
```

---

## Bonnes pratiques

### ✅ Toujours implémenter `nemesisFormat()`

```php
// BON
public function nemesisFormat(): AbstractData
{
    return new UserData(
        id: $this->id,
        name: $this->name,
        email: $this->email,
        // ✅ Pas de données sensibles
    );
}

// ÉVITER
public function nemesisFormat(): AbstractData
{
    return new UserData(
        id: $this->id,
        password: $this->password, // ❌ Données sensibles exposées
        credit_card: $this->credit_card, // ❌ Données sensibles exposées
    );
}
```

### ✅ Utiliser `NemesisHelper` avec injection de dépendance

```php
// BON
class ProfileController
{
    public function __construct(
        private readonly NemesisHelper $helper,
    ) {}

    public function show()
    {
        $user = $this->helper->getCurrentAuthenticatable();
        // ...
    }
}

// ÉVITER
class ProfileController
{
    public function show()
    {
        $user = app(NemesisHelper::class)->getCurrentAuthenticatable(); // ❌
    }
}
```

### ✅ Vérifier les abilities

```php
// BON - Dans la route
Route::get('/admin', function () {
    // ...
})->middleware('nemesis.token:admin');

// BON - Dans le contrôleur
if ($this->helper->tokenHasAbility('admin')) {
    // Action admin
}

// BON - Via le service
if ($this->nemesisService->can($token, 'admin')) {
    // Action admin
}
```

### ✅ Utiliser les métadonnées pour le contexte

```php
$record = NemesisTokenRecord::from([
    'name' => 'Session',
    'metadata' => [
        'ip' => $request->ip(),
        'user_agent' => $request->userAgent(),
        'device' => $request->header('Device'),
        'location' => $request->header('CF-IPCountry'),
    ],
]);
```

### ✅ Nettoyer les tokens expirés

```bash
# Configurer le nettoyage automatique
'cleanup' => [
    'auto_cleanup' => true,
    'frequency' => 60,
    'keep_expired_for_days' => 30,
]

# Ou manuellement
./vendor/bin/directive nemesis:clean-tokens --force
```

### ✅ Utiliser les bons middlewares

| Cas d'usage | Middleware |
|-------------|------------|
| API avec Bearer token | `nemesis.token` |
| API avec Bearer token + ability | `nemesis.token:ability` |
| API avec Bearer token + vérification email | `nemesis.api.verified` |
| Web avec cookie | `nemesis.web` |
| Web avec cookie + ability | `nemesis.web:ability` |
| Web avec cookie + vérification email | `nemesis.web.verified` |
| Page de login/register (web) | `nemesis.guest` |
| Page de login/register avec ability | `nemesis.guest:ability` |
| Endpoint API login/register | `nemesis.api.guest` |
| Endpoint API login/register avec ability | `nemesis.api.guest:ability` |

---

## Référence des commandes

| Commande | Alias | Description |
|----------|-------|-------------|
| `nemesis:install` | `nemesis-install`, `setup-nemesis` | Installation du package |
| `nemesis:clean-tokens` | `nemesis-tc`, `nemesis-ce` | Nettoyage des tokens expirés |
| `nemesis:list-tokens` | `tokens-list`, `nemesis-tokens` | Liste tous les tokens |

---

## Référence des middlewares

| Alias | Description | Utilisation |
|-------|-------------|-------------|
| `nemesis.token` | API Token | `Route::middleware('nemesis.token')` |
| `nemesis.token:ability` | API Token avec ability | `Route::middleware('nemesis.token:admin')` |
| `nemesis.web` | Web (cookie) | `Route::middleware('nemesis.web')` |
| `nemesis.web:ability` | Web avec ability | `Route::middleware('nemesis.web:admin')` |
| `nemesis.guest` | Web invité | `Route::middleware('nemesis.guest')` |
| `nemesis.guest:ability` | Web invité avec ability | `Route::middleware('nemesis.guest:admin')` |
| `nemesis.api.guest` | API invité | `Route::middleware('nemesis.api.guest')` |
| `nemesis.api.guest:ability` | API invité avec ability | `Route::middleware('nemesis.api.guest:admin')` |
| `nemesis.api.verified` | API + vérification email | `Route::middleware('nemesis.api.verified')` |
| `nemesis.web.verified` | Web + vérification email | `Route::middleware('nemesis.web.verified')` |

---

## Référence des services

| Service | Interface | Rôle |
|---------|-----------|------|
| `NemesisService` | `NemesisInterface` | Gestion complète des tokens |
| `NemesisAuthenticationService` | `NemesisAuthenticationInterface` | Authentification des requêtes |
| `CookieTokenStorageService` | `CookieTokenStorageInterface` | Stockage en cookie |
| `HttpHeaderService` | `HttpHeaderInterface` | En-têtes HTTP |
| `MetadataValidatorService` | `MetadataValidatorInterface` | Validation des métadonnées |

---

## Codes d'erreur

| Code | HTTP | Description |
|------|------|-------------|
| `MISSING_TOKEN` | 401 | Token non fourni |
| `INVALID_TOKEN` | 401 | Token invalide |
| `TOKEN_EXPIRED` | 401 | Token expiré |
| `AUTHENTICATABLE_NOT_FOUND` | 401 | Utilisateur non trouvé |
| `INSUFFICIENT_PERMISSIONS` | 403 | Permissions insuffisantes |
| `ORIGIN_NOT_ALLOWED` | 403 | Origine non autorisée |
| `EMAIL_NOT_VERIFIED` | 403 | Email non vérifié |
| `ALREADY_AUTHENTICATED` | 400 | Déjà authentifié |
| `INVALID_AUTHENTICATABLE_MODEL` | 500 | Modèle non valide |
| `MODEL_MISSING_EMAIL_VERIFIED_AT` | 500 | Colonne email_verified_at manquante |

---

## Licence

MIT © [Andy Defer](https://github.com/andydefer)