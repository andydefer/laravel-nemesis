# Nemesis — Authentification par tokens multi-modèles pour Laravel

![PHP Version](https://img.shields.io/badge/PHP-8.2%2B-blue)
![Laravel Version](https://img.shields.io/badge/Laravel-12.x%20%7C%2013.x%20%7C%2014.x%20%7C%2015.x-orange)
![License](https://img.shields.io/badge/license-MIT-green)
![Tests](https://img.shields.io/badge/tests-246%20passing-brightgreen)

**Nemesis** est un package Laravel complet pour l’authentification par **tokens multi-modèles** utilisant le système de **Directives** (CLI flexible). Contrairement à Sanctum ou Passport, Nemesis permet à **n’importe quel modèle Eloquent** (`User`, `CheckPoint`, `ApiClient`, `Admin`, etc.) de générer, valider et gérer ses propres tokens d’API avec une sécurité renforcée : expiration, permissions (abilities), restrictions CORS par origine, métadonnées, soft delete pour révocation, et nettoyage automatique.

---

## 📦 Installation

```bash
composer require andydefer/laravel-nemesis
```

### Vérification de l'installation

```bash
# Lister les directives disponibles
./vendor/bin/directive --list

# Installer le package
./vendor/bin/directive install-nemesis --force
```

### Publication manuelle des ressources (alternative)

```bash
# Configuration
php artisan vendor:publish --tag=nemesis-config

# Migrations
php artisan vendor:publish --tag=nemesis-migrations

# Exécuter les migrations
php artisan migrate
```

---

## 🚀 Démarrage rapide

### 1. Ajouter l’interface à vos modèles

```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use AndyDefer\Nemesis\Contracts\MustNemesis;
use AndyDefer\DomainStructures\Abstracts\AbstractData;

class User extends Model implements MustNemesis
{
    /**
     * Définir ce qui est exposé par l'API.
     * Cette méthode est OBLIGATOIRE (imposée par l'interface).
     */
    public function nemesisFormat(): AbstractData
    {
        return new UserData(
            id: $this->id,
            name: $this->name,
            email: $this->email,
            createdAt: $this->created_at,
        );
    }
}

class CheckPoint extends Model implements MustNemesis
{
    /**
     * Format différent pour les points de contrôle.
     */
    public function nemesisFormat(): AbstractData
    {
        return new CheckPointData(
            id: $this->id,
            name: $this->name,
            location: $this->location,
            status: $this->is_active ? 'active' : 'inactive',
            lastSeen: $this->last_ping_at,
        );
    }
}
```

### 2. Créer un token

```php
use AndyDefer\Nemesis\Records\NemesisTokenRecord;

$user = User::find(1);

$record = NemesisTokenRecord::from([
    'name' => 'Application Mobile',
    'source' => 'mobile',
    'abilities' => ['scan_ticket', 'view_stats'],
    'metadata' => ['app_version' => '2.1.0'],
]);

[$token, $plainToken] = $nemesisService->createWithPlainToken($record, $user);

// Afficher le token une seule fois
echo $plainToken; // stocker en clair côté client
```

### 3. Protéger une route

```php
// Dans routes/api.php
use AndyDefer\Nemesis\Facades\NemesisHelper;

Route::middleware(['nemesis.token'])->group(function () {
    Route::get('/profile', function () {
        // Version formatée via le Facade
        return response()->json(NemesisHelper::getCurrentAuthenticatableFormat());
    });
});

// Avec vérification d’une ability
Route::post('/scan', function () {
    // ...
})->middleware('nemesis.token:scan_ticket');
```

### 4. Utiliser le token

```http
GET /api/profile
Authorization: Bearer <token>
```

### 5. Utilisation du Facade `NemesisHelper`

```php
use AndyDefer\Nemesis\Facades\NemesisHelper;

// Récupérer le token actuel
$token = NemesisHelper::getCurrentToken();

// Récupérer le modèle authentifié (User, CheckPoint, etc.)
$authenticatable = NemesisHelper::getCurrentAuthenticatable();

// Récupérer la version formatée (recommandée pour les APIs)
$formatted = NemesisHelper::getCurrentAuthenticatableFormat();

// Vérifier si authentifié
if (NemesisHelper::hasCurrentAuthenticatable()) {
    // ...
}

// Vérifier si un token est présent
if (NemesisHelper::hasCurrentToken()) {
    // ...
}
```

---

## 🎯 Révocation granulaire des tokens

Nemesis permet de révoquer sélectivement les tokens par source, nom ou critères personnalisés via le service `NemesisService`.

### Méthodes de révocation

| Méthode | Description | Valeur de retour |
|---------|-------------|------------------|
| `revokeTokensBySource(Model $tokenable, string $source, bool $force = false)` | Révoque tous les tokens d'une source spécifique | `int` |
| `revokeTokensByName(Model $tokenable, string $name, bool $force = false)` | Révoque tous les tokens avec un nom spécifique | `int` |
| `revokeTokensBySourceAndName(Model $tokenable, string $source, string $name, bool $force = false)` | Révoque les tokens correspondant à source ET nom | `int` |
| `revokeAllTokensExceptSource(Model $tokenable, string $source, bool $force = false)` | Garde les tokens d'une source, révoque tous les autres | `int` |
| `deleteAllTokens(Model $tokenable, bool $force = false)` | Supprime tous les tokens d'un modèle | `int` |
| `deleteBulk(NemesisTokenFilterRecord $filters)` | Supprime en masse selon filtres | `int` |
| `forceDeleteBulk(NemesisTokenFilterRecord $filters)` | Suppression définitive selon filtres | `int` |

### Filtres disponibles

```php
$filters = new NemesisTokenFilterRecord(
    tokenable_type: 'App\\Models\\User',
    tokenable_id: 1,
    source: 'web',
    name: 'session_token',
    is_expired: true,
    is_revoked: false,
    created_before: DateTimeVO::from(now()->subDays(30)->toIso8601String()),
);
```

### Cas d'usage concrets

#### 1. Déconnexion de tous les navigateurs (garder l'app mobile active)

```php
$nemesisService->revokeTokensBySource($user, 'web');
```

#### 2. Révocation sélective par type de token

```php
$nemesisService->revokeTokensBySourceAndName($user, 'web', 'web_session');
$nemesisService->revokeTokensByName($user, 'admin_token');
```

#### 3. Garder un type de token actif

```php
$nemesisService->revokeAllTokensExceptSource($user, 'api');
```

#### 4. Suppression définitive (force delete)

```php
$nemesisService->revokeTokensBySource($user, 'web', force: true);
$nemesisService->deleteAllTokens($user, force: true);
```

#### 5. Nettoyage par filtres avancés

```php
$filter = new NemesisTokenFilterRecord(
    created_before: DateTimeVO::from(now()->subDays(30)->toIso8601String()),
);
$nemesisService->forceDeleteBulk($filter);
```

---

## 🛡️ Sécurité multi-origines (CORS)

Nemesis permet de restreindre un token à des origines spécifiques.

```php
$nemesisService->addAllowedOrigin($token, 'https://monapp.com');
$nemesisService->addAllowedOrigin($token, 'https://*.example.com'); // wildcard

// Vérification
if ($nemesisService->canUseFromOrigin($token, 'https://monapp.com')) {
    // origine autorisée
}
```

---

## 🔑 Système d’abilities (permissions fines)

Chaque token peut avoir une liste d’abilities.

```php
// Création avec abilities
$record = NemesisTokenRecord::from([
    'name' => 'Scanner Billeterie',
    'source' => 'kiosk',
    'abilities' => ['scan_ticket', 'validate_entry'],
]);

// Vérifier une ability
if ($nemesisService->can($token, 'scan_ticket')) {
    // autorisé
}
```

Utilisation en middleware :
```php
Route::post('/validate', fn() => ...)
    ->middleware('nemesis.token:validate_entry');
```

---

## 📦 Métadonnées enrichies

Stockez des informations contextuelles avec validation automatique (taille max 64KB, profondeur max 5, max 100 clés).

```php
$record = NemesisTokenRecord::from([
    'name' => 'API Session',
    'metadata' => [
        'device' => 'iPhone 15',
        'os' => 'iOS 17',
        'location' => 'Paris',
        'preferences' => ['lang' => 'fr']
    ],
]);

// Modifier après création
$nemesisService->setMetadata($token, 'last_login_ip', '192.168.1.1');
$ip = $nemesisService->getMetadata($token, 'last_login_ip');
$nemesisService->mergeMetadata($token, ['new_key' => 'value']);
```

---

## 🧹 Nettoyage automatique des tokens expirés

Configuration dans `config/nemesis.php` :

```php
'cleanup' => [
    'auto_cleanup' => true,       // nettoyage auto par schedule
    'frequency' => 60,            // toutes les heures
    'keep_expired_for_days' => 30, // garder 30 jours pour audit
],
```

Directive manuelle :
```bash
./vendor/bin/directive clean-tokens --force
./vendor/bin/directive clean-tokens --days=15
./vendor/bin/directive clean-tokens --keep-expired
./vendor/bin/directive nemesis-clean --force  # alias
```

---

## 📋 Directives disponibles

| Commande | Alias | Description |
|----------|-------|-------------|
| `install-nemesis` | `nemesis-install`, `setup-nemesis` | Installation du package |
| `list-tokens` | `tokens-list`, `nemesis-tokens` | Liste tous les tokens |
| `clean-tokens` | `tokens-clean`, `token-clean`, `clean-expired` | Nettoie les tokens expirés |
| `nemesis-clean` | `token-clean`, `tokens-clean` | Alias de clean-tokens |

```bash
# Lister les directives Nemesis
./vendor/bin/directive --list | grep -E "nemesis|token|clean"

# Aide sur une directive
./vendor/bin/directive help clean-tokens
```

---

## 🔗 Scénario concret : Billeterie avec User et CheckPoint

### Modèles

```php
// User (client billetterie)
class User extends Model implements MustNemesis
{
    public function nemesisFormat(): UserData
    {
        return new UserData(
            id: $this->id,
            name: $this->name,
            email: $this->email,
        );
    }
}

// CheckPoint (point de contrôle physique)
class CheckPoint extends Model implements MustNemesis
{
    public function nemesisFormat(): CheckPointData
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

### Création des tokens

```php
// Pour un utilisateur (application mobile)
$record = NemesisTokenRecord::from([
    'name' => 'App Mobile Client',
    'source' => 'mobile',
    'abilities' => ['buy_ticket', 'view_tickets'],
]);
[$token, $plainToken] = $nemesisService->createWithPlainToken($record, $user);

// Pour un point de contrôle (kiosque)
$record = NemesisTokenRecord::from([
    'name' => 'Scanner Portique',
    'source' => 'kiosk',
    'abilities' => ['scan_ticket', 'validate_entry', 'reject_entry'],
    'metadata' => ['hardware_id' => 'SCAN-01', 'location' => 'Entrée A'],
]);
[$token, $plainToken] = $nemesisService->createWithPlainToken($record, $checkpoint);
```

### Routes protégées

```php
Route::middleware('nemesis.token:buy_ticket')->post('/tickets', [TicketController::class, 'buy']);
Route::middleware('nemesis.token:scan_ticket')->post('/scan', [ScanController::class, 'validate']);
```

### Dans `ScanController`

```php
use AndyDefer\Nemesis\Facades\NemesisHelper;

public function validate(Request $request)
{
    $checkpoint = NemesisHelper::getCurrentAuthenticatable(); // instance de CheckPoint
    $token = NemesisHelper::getCurrentToken();

    if (!$token || !$nemesisService->can($token, 'validate_entry')) {
        return response()->json(['error' => 'Permission refusée'], 403);
    }

    // scanner le billet...
    return response()->json([
        'status' => 'entrée validée',
        'checkpoint' => NemesisHelper::getCurrentAuthenticatableFormat()
    ]);
}
```

### Révocation depuis le point de contrôle

```php
use AndyDefer\Nemesis\Facades\NemesisHelper;

public function logoutCheckPoint()
{
    $token = NemesisHelper::getCurrentToken();
    
    if ($token && $nemesisService->revoke($token)) {
        return response()->json(['message' => 'Token révoqué avec succès']);
    }
    
    return response()->json(['error' => 'Aucun token actif trouvé'], 404);
}
```

---

## 📊 API complète du service

### NemesisService

| Méthode | Description | Retour |
|---------|-------------|--------|
| `create(NemesisTokenRecord $record, Model $tokenable)` | Crée un token avec hash existant | `NemesisToken` |
| `createWithPlainToken(NemesisTokenRecord $record, Model $tokenable)` | Génère un nouveau token | `array[NemesisToken, string]` |
| `findByHash(string $tokenHash)` | Trouve un token par son hash | `?NemesisToken` |
| `updateLastUsed(NemesisToken $token)` | Met à jour `last_used_at` | `NemesisToken` |
| `revoke(NemesisToken $token)` | Soft delete du token | `bool` |
| `restoreToken(NemesisToken $token)` | Restaure un token soft-deleted | `bool` |
| `forceExpire(NemesisToken $token)` | Expire immédiatement le token | `NemesisToken` |
| `forceDelete(NemesisToken $token)` | Suppression définitive | `bool` |
| `can(NemesisToken $token, string $ability)` | Vérifie une ability | `bool` |
| `canAll(NemesisToken $token, array $abilities)` | Vérifie toutes les abilities | `bool` |
| `canUseFromOrigin(NemesisToken $token, ?string $origin)` | Vérifie l'origine CORS | `bool` |
| `deleteBulk(NemesisTokenFilterRecord $filters)` | Soft delete en masse | `int` |
| `forceDeleteBulk(NemesisTokenFilterRecord $filters)` | Suppression définitive en masse | `int` |
| `count(NemesisTokenFilterRecord $filters)` | Compte les tokens | `int` |
| `exists(NemesisTokenFilterRecord $filters)` | Vérifie l'existence | `bool` |
| `findByFilters(NemesisTokenFilterRecord $filters, ?int $limit = null, ?string $sortBy = null, array $columns = ['*'])` | Recherche avancée | `Collection` |
| `getMetadata(NemesisToken $token, string $key, mixed $default = null)` | Récupère une métadonnée | `mixed` |
| `setMetadata(NemesisToken $token, string $key, mixed $value)` | Définit une métadonnée | `NemesisToken` |
| `mergeMetadata(NemesisToken $token, array $metadata)` | Fusionne des métadonnées | `NemesisToken` |
| `clearMetadata(NemesisToken $token)` | Supprime toutes les métadonnées | `NemesisToken` |
| `addAllowedOrigin(NemesisToken $token, string $origin)` | Ajoute une origine CORS | `NemesisToken` |
| `removeAllowedOrigin(NemesisToken $token, string $origin)` | Supprime une origine CORS | `NemesisToken` |

---

### NemesisHelper (Facade)

| Méthode | Description | Retour |
|---------|-------------|--------|
| `getCurrentToken()` | Récupère le token actuel | `?NemesisTokenRecord` |
| `getCurrentAuthenticatable()` | Récupère le modèle authentifié | `?Model` |
| `getCurrentAuthenticatableFormat()` | Récupère la version formatée | `?AbstractData` |
| `hasCurrentToken()` | Vérifie si un token est présent | `bool` |
| `hasCurrentAuthenticatable()` | Vérifie si authentifié | `bool` |

---

## ⚙️ Configuration (`config/nemesis.php`)

```php
return [
    // Génération des tokens
    'token_length' => 64,
    'hash_algorithm' => 'sha256',
    'expiration' => 60, // null = jamais, sinon minutes

    // Middleware
    'middleware' => [
        'parameter_name' => 'nemesis_auth',
        'token_header' => 'Authorization',
        'security_headers' => true,
        'validate_origin' => true,
    ],

    // CORS
    'cors' => [
        'allow_credentials' => true,
        'max_age' => 86400,
        'expose_token_info' => false,
    ],

    // Nettoyage
    'cleanup' => [
        'auto_cleanup' => true,
        'frequency' => 60,
        'keep_expired_for_days' => 30,
    ],
];
```

---

## 📁 Structure des migrations

```sql
CREATE TABLE nemesis_tokens (
    id BIGINT PRIMARY KEY,
    token_hash VARCHAR(255) UNIQUE NOT NULL,
    tokenable_type VARCHAR(255) NOT NULL,
    tokenable_id BIGINT NOT NULL,
    name VARCHAR(255) NULL,
    source VARCHAR(255) NULL,
    abilities TEXT NULL,           -- JSON
    metadata TEXT NULL,            -- JSON
    allowed_origins TEXT NULL,     -- JSON
    last_used_at TIMESTAMP NULL,
    expires_at TIMESTAMP NULL,
    deleted_at TIMESTAMP NULL,     -- Soft delete
    created_at TIMESTAMP NULL,
    updated_at TIMESTAMP NULL,
    
    INDEX idx_tokenable (tokenable_type, tokenable_id),
    INDEX idx_expires_at (expires_at),
    INDEX idx_source (source),
    INDEX idx_last_used (last_used_at),
    UNIQUE INDEX idx_token_hash (token_hash)
);
```

---

## 🧠 Ce que Nemesis résout concrètement

| Problème | Solution Nemesis |
|----------|------------------|
| Plusieurs modèles doivent s’authentifier (User, CheckPoint) | Polymorphisme `tokenable` |
| Contrôle total des données exposées via API | Méthode obligatoire `nemesisFormat()` retournant `AbstractData` |
| Déconnexion sélective (web vs mobile) | `revokeTokensBySource()` |
| Révocation granulaire par type de token | `revokeTokensByName()` |
| Nettoyage des tokens inactifs | `deleteBulk()` avec filtres |
| Garder certains tokens actifs | `revokeAllTokensExceptSource()` |
| Un token ne doit servir que pour certaines origines | `allowed_origins` + validation middleware |
| Une application mobile a moins de droits qu’un admin | `abilities` (ex: `scan_ticket` vs `delete_user`) |
| Besoin de tracer le contexte (IP, device, version) | `metadata` validé et nettoyé |
| Révocation sans perte d’audit | `softDeletes` |
| Nettoyage des tokens obsolètes | Commande schedule + `auto_cleanup` |
| Un token peut expirer après X minutes | `expires_at` + `isExpired()` |

---

## 🔄 Comparaison rapide avec Laravel Sanctum

| Fonctionnalité | Sanctum | Nemesis |
|----------------|---------|---------|
| Multi-modèles (User + CheckPoint) | ❌ (seulement User) | ✅ (tout modèle) |
| CLI avec Directives (pas Artisan) | ❌ | ✅ |
| Contrôle explicite de l'exposition des données | ❌ | ✅ (méthode obligatoire) |
| Révocation granulaire par source/nom | ❌ | ✅ |
| Révocation par filtres avancés | ❌ | ✅ |
| Restrictions CORS par token | ❌ (globale) | ✅ (par token) |
| Métadonnées enrichies | ❌ | ✅ (validation stricte) |
| Soft delete des tokens | ❌ | ✅ |
| Abilities sans user | ❌ | ✅ |
| Nettoyage auto configurable | ❌ | ✅ |
| Tests en environnement isolé | ❌ | ✅ (DirectiveTestingService) |

---

## 🧪 Tests

```bash
# Exécuter tous les tests
./vendor/bin/phpunit

# Exécuter les tests d'une directive
./vendor/bin/phpunit --filter CleanTokensDirectiveTest

# Exécuter les tests en mode debug
./vendor/bin/phpunit --debug --filter Unit
```

---

## 🤝 Contribution

1. Fork + branche `feature/ma-fonctionnalité`
2. `composer test` (246 tests doivent passer)
3. Pull request vers `main`

---

## 📄 Licence

MIT © [andydefer](https://github.com/andydefer)
```

---