# Nemesis — Authentification par tokens multi-modèles pour Laravel

![PHP Version](https://img.shields.io/badge/PHP-8.3%2B-blue)
![Laravel Version](https://img.shields.io/badge/Laravel-12%2B-orange)
![License](https://img.shields.io/badge/license-MIT-green)
![Tests](https://img.shields.io/badge/tests-271%20passing-brightgreen)
![Coverage](https://img.shields.io/badge/coverage-92%25-green)

**Nemesis** est un package Laravel complet pour l’authentification par **tokens multi-modèles**. Contrairement à Sanctum ou Passport, Nemesis permet à **n’importe quel modèle Eloquent** (`User`, `CheckPoint`, `ApiClient`, `Admin`, etc.) de générer, valider et gérer ses propres tokens d’API avec une sécurité renforcée : expiration, permissions (abilities), restrictions CORS par origine, métadonnées, soft delete pour révocation, et nettoyage automatique.

---

## 📦 Installation

```bash
composer require andydefer/laravel-nemesis
```

Publier les ressources du package :

```bash
php artisan nemesis:install
```

Ou manuellement :

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

### 1. Ajouter le trait et l’interface à vos modèles

```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Kani\Nemesis\Contracts\MustNemesis;
use Kani\Nemesis\Traits\HasNemesisTokens;

class User extends Model implements MustNemesis
{
    use HasNemesisTokens;

    /**
     * Définir ce qui est exposé par l'API.
     * Cette méthode est OBLIGATOIRE (imposée par l'interface).
     */
    public function nemesisFormat(): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'email' => $this->email,
            'created_at' => $this->created_at->toIso8601String(),
        ];
    }
}

class CheckPoint extends Model implements MustNemesis
{
    use HasNemesisTokens;

    /**
     * Format différent pour les points de contrôle.
     */
    public function nemesisFormat(): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'location' => $this->location,
            'status' => $this->is_active ? 'active' : 'inactive',
            'last_seen' => $this->last_ping_at?->toIso8601String(),
        ];
    }
}
```

### 2. Créer un token

```php
$user = User::find(1);
$token = $user->createNemesisToken(
    name: 'Application Mobile',
    source: 'mobile',
    abilities: ['scan_ticket', 'view_stats'],
    metadata: ['app_version' => '2.1.0']
);

// Afficher le token une seule fois
echo $token; // stocker en clair côté client
```

### 3. Protéger une route

```php
// Dans routes/api.php
Route::middleware(['nemesis.auth'])->group(function () {
    Route::get('/profile', function () {
        // Version formatée (recommandée)
        return response()->json(current_authenticatable_format());
    });
});

// Avec vérification d’une ability
Route::post('/scan', function () {
    // ...
})->middleware('nemesis.auth:scan_ticket');
```

### 4. Utiliser le token

```http
GET /api/profile
Authorization: Bearer <token>
```

### 5. Gérer les tokens dans le contrôleur

```php
public function revokeCurrentToken(Request $request)
{
    $authenticatable = current_authenticatable();
    
    // La méthode retourne un booléen indiquant le succès
    if ($authenticatable->revokeCurrentNemesisToken()) {
        return response()->json(['message' => 'Token révoqué avec succès']);
    }
    
    return response()->json(['error' => 'Aucun token actif trouvé'], 404);
}
```

---

## 🎯 Révocation granulaire des tokens

Nemesis 1.2+ introduit des méthodes puissantes pour révoquer sélectivement les tokens par source, nom ou critères personnalisés.

### Méthodes de révocation

| Méthode | Description | Valeur de retour |
|---------|-------------|------------------|
| `revokeNemesisTokensBySource(string $source, bool $force = false)` | Révoque tous les tokens d'une source spécifique | `int` |
| `revokeNemesisTokensByName(string $name, bool $force = false)` | Révoque tous les tokens avec un nom spécifique | `int` |
| `revokeNemesisTokensBySourceAndName(string $source, string $name, bool $force = false)` | Révoque les tokens correspondant à source ET nom | `int` |
| `revokeAllNemesisTokensExceptSource(string $source, bool $force = false)` | Garde les tokens d'une source, révoque tous les autres | `int` |
| `revokeNemesisTokensWhere(array $criteria, bool $force = false)` | Révoque avec des critères personnalisés (supporte opérateurs) | `int` |

### Formats supportés par `revokeNemesisTokensWhere`

La méthode accepte trois formats différents pour les critères :

```php
// Format 1: Égalité simple
$user->revokeNemesisTokensWhere(['source' => 'web']);

// Format 2: Avec opérateur
$user->revokeNemesisTokensWhere([
    'created_at' => ['<', now()->subDays(30)],
    'last_used_at' => ['>', now()->subDays(90)]
]);

// Format 3: Tableau de conditions
$user->revokeNemesisTokensWhere([
    ['source', '=', 'web'],
    ['created_at', '<', now()->subDays(30)],
    ['name', '!=', 'admin_token']
]);
```

### Cas d'usage concrets

#### 1. Déconnexion de tous les navigateurs (garder l'app mobile active)

```php
// Scénario : L'utilisateur est connecté sur 3 navigateurs et l'app mobile
$user->revokeNemesisTokensBySource('web');

// Résultat : 
// ✅ Les 3 sessions navigateur sont terminées
// ✅ L'application mobile reste connectée
// ✅ Les tokens API restent actifs
```

#### 2. Révocation sélective par type de token

```php
// Révoquer uniquement les tokens de session web
$user->revokeNemesisTokensBySourceAndName('web', 'web_session');

// Révoquer tous les tokens d'administration
$user->revokeNemesisTokensByName('admin_token');
```

#### 3. Garder un type de token actif

```php
// Scénario : Nettoyer tous les tokens sauf l'API
$user->revokeAllNemesisTokensExceptSource('api');

// Résultat :
// ✅ Les tokens API restent fonctionnels
// ❌ Tous les autres tokens (web, mobile, etc.) sont révoqués
```

#### 4. Révocation par critères complexes

```php
// Révoquer les tokens inactifs depuis plus de 30 jours
$user->revokeNemesisTokensWhere([
    'last_used_at' => ['<', now()->subDays(30)]
]);

// Révoquer les tokens créés avant une date spécifique
$user->revokeNemesisTokensWhere([
    'created_at' => ['<', Carbon::create(2025, 1, 1)]
]);

// Conditions multiples avec opérateurs
$user->revokeNemesisTokensWhere([
    ['source', '=', 'web'],
    ['created_at', '<', now()->subMonths(3)],
    ['last_used_at', '<', now()->subMonths(1)]
]);
```

#### 5. Suppression définitive (force delete)

```php
// Suppression permanente (contourne soft delete)
$user->revokeNemesisTokensBySource('web', force: true);
$user->revokeNemesisTokensByName('temp_token', force: true);
$user->revokeAllNemesisTokensExceptSource('mobile', force: true);
```

### Exemple complet dans un contrôleur

```php
<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Carbon\Carbon;

class SessionController extends Controller
{
    // Déconnexion de tous les appareils sauf le mobile
    public function logoutAllBrowsers(Request $request)
    {
        $user = current_authenticatable();
        
        $revokedCount = $user->revokeNemesisTokensBySource('web');
        
        return response()->json([
            'message' => "Déconnecté de {$revokedCount} session(s) navigateur",
            'mobile_active' => true,
            'api_active' => true
        ]);
    }
    
    // Déconnexion complète (tous les appareils)
    public function logoutAllDevices(Request $request)
    {
        $user = current_authenticatable();
        
        $revokedCount = $user->revokeNemesisTokens();
        
        return response()->json([
            'message' => "Déconnecté de tous les appareils ({$revokedCount} sessions)"
        ]);
    }
    
    // Nettoyage des tokens inactifs
    public function cleanupInactiveTokens(Request $request)
    {
        $user = current_authenticatable();
        
        $revokedCount = $user->revokeNemesisTokensWhere([
            'last_used_at' => ['<', now()->subDays(30)]
        ]);
        
        return response()->json([
            'message' => "{$revokedCount} token(s) inactif(s) nettoyé(s)"
        ]);
    }
    
    // Nettoyage des vieux tokens
    public function cleanupOldTokens(Request $request)
    {
        $user = current_authenticatable();
        
        $revokedCount = $user->revokeNemesisTokensWhere([
            ['created_at', '<', Carbon::now()->subMonths(6)],
            ['last_used_at', '<', Carbon::now()->subMonths(3)]
        ]);
        
        return response()->json([
            'message' => "{$revokedCount} ancien(s) token(s) supprimé(s)"
        ]);
    }
    
    // Garder uniquement le token courant
    public function keepOnlyCurrentSession(Request $request)
    {
        $user = current_authenticatable();
        $currentToken = current_token();
        
        if ($currentToken && $currentToken->source === 'web') {
            $user->revokeNemesisTokensWhere([
                ['source', '=', 'web'],
                ['token_hash', '!=', $currentToken->token_hash]
            ]);
        }
        
        return response()->json(['message' => 'Sessions nettoyées']);
    }
}
```

### Utilisation via le Manager

```php
use Kani\Nemesis\Facades\Nemesis;

// Via le manager
Nemesis::revokeTokensBySource($user, 'web');
Nemesis::revokeTokensByName($user, 'web_session');
Nemesis::revokeTokensBySourceAndName($user, 'web', 'web_session');
Nemesis::revokeAllTokensExceptSource($user, 'mobile');
Nemesis::revokeTokensWhere($user, ['created_at' => ['<', now()->subDays(30)]]);
```

### Avantages de la révocation granulaire

| Avantage | Description |
|----------|-------------|
| **UX améliorée** | Les utilisateurs peuvent se déconnecter de tous leurs navigateurs sans affecter l'application mobile |
| **Contrôle granulaire** | Les développeurs peuvent cibler des types de tokens spécifiques |
| **Sécurité renforcée** | Révoquer les tokens suspects par source sans affecter les autres |
| **Flexibilité maximale** | Support des opérateurs (`<`, `>`, `<=`, `>=`, `=`, `!=`) et conditions multiples |
| **Cohérence API** | Toutes les méthodes retournent le nombre de tokens affectés |
| **Backward compatible** | Aucun breaking change, les méthodes existantes restent inchangées |

---

## 🎨 Contrôle total de l’exposition des données (nemesisFormat)

Nemesis **impose** à chaque modèle authentifiable de définir sa propre méthode `nemesisFormat()`. Cela force les développeurs à explicitement choisir quelles données sont exposées via l’API, évitant ainsi les fuites accidentelles d’informations sensibles.

### ❌ Sans Nemesis (dangereux)

```php
// Expose TOUT (password, remember_token, etc.)
return response()->json(auth()->user());
```

### ✅ Avec Nemesis (sécurisé)

```php
// N'expose que ce qui est défini dans nemesisFormat()
return response()->json(current_authenticatable_format());
```

### Exemple concret

```php
// Modèle User
public function nemesisFormat(): array
{
    return [
        'id' => $this->id,
        'name' => $this->name,
        'email' => $this->email,
        'avatar' => $this->avatar_url,
        'roles' => $this->roles->pluck('name'),
    ];
}

// Modèle CheckPoint
public function nemesisFormat(): array
{
    return [
        'id' => $this->id,
        'name' => $this->name,
        'location' => $this->location,
        'status' => $this->is_active ? 'active' : 'inactive',
        'type' => $this->type,
    ];
}
```

---

## 🛡️ Sécurité multi-origines (CORS)

Nemesis permet de restreindre un token à des origines spécifiques, y compris avec des wildcards.

```php
$tokenModel = $user->getNemesisToken($plainToken);
$tokenModel->addAllowedOrigin('https://monapp.com');
$tokenModel->addAllowedOrigin('https://*.example.com'); // wildcard

// Vérification automatique dans le middleware
// Si l’origine n’est pas autorisée → erreur 403
```

---

## 🔑 Système d’abilities (permissions fines)

Chaque token peut avoir une liste d’abilities (ex: `create`, `delete`, `scan_ticket`).

```php
// Création avec abilities
$token = $checkpoint->createNemesisToken(
    name: 'Scanner Billeterie',
    source: 'kiosk',
    abilities: ['scan_ticket', 'validate_entry']
);

// Vérifier une ability
if ($tokenModel->can('scan_ticket')) {
    // autorisé
}
```

Utilisation en middleware :
```php
Route::post('/validate', fn() => ...)
    ->middleware('nemesis.auth:validate_entry');
```

---

## 📦 Métadonnées enrichies

Stockez des informations contextuelles (IP, user-agent, version, etc.) avec validation automatique (taille max 64KB, profondeur max 5, max 100 clés).

```php
$token = $user->createNemesisToken(
    name: 'API Session',
    metadata: [
        'device' => 'iPhone 15',
        'os' => 'iOS 17',
        'location' => 'Paris',
        'preferences' => ['lang' => 'fr']
    ]
);

// Modifier après création
$tokenModel->setMetadata('last_login_ip', '192.168.1.1');
$ip = $tokenModel->getMetadata('last_login_ip');
$tokenModel->mergeMetadata(['new_key' => 'value']);
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

Commande manuelle :
```bash
php artisan nemesis:clean --force
php artisan nemesis:clean --days=15
php artisan nemesis:clean --keep-expired
```

---

## 📋 Commandes disponibles

| Commande | Description |
|----------|-------------|
| `nemesis:install` | Publie config + migrations |
| `nemesis:clean`   | Supprime tokens expirés/vieux |
| `nemesis:list`    | Liste tous les tokens (filtrable par modèle) |

```bash
php artisan nemesis:list --model=App\\Models\\CheckPoint
```

---

## 🧪 Helpers globaux

Nemesis fournit des helpers pour un accès rapide :

```php
// Récupérer le manager
nemesis()->validateToken($user, $token);

// Token actuel
$tokenModel = current_token();
if ($tokenModel && $tokenModel->can('admin')) {
    // ...
}

// Modèle authentifié brut (User, CheckPoint, etc.)
$authenticated = current_authenticatable();

// Version formatée du modèle authentifié (recommandée pour les APIs)
$formatted = current_authenticatable_format();
return response()->json($formatted);
```

---

## 🔗 Scénario concret : Billeterie avec User et CheckPoint

### Modèles

```php
// User (client billetterie)
class User extends Model implements MustNemesis
{
    use HasNemesisTokens;
    
    public function nemesisFormat(): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'email' => $this->email,
        ];
    }
}

// CheckPoint (point de contrôle physique)
class CheckPoint extends Model implements MustNemesis
{
    use HasNemesisTokens;
    
    public function nemesisFormat(): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'location' => $this->location,
            'status' => $this->is_active ? 'active' : 'inactive',
        ];
    }
}
```

### Création des tokens

```php
// Pour un utilisateur (application mobile)
$userToken = $user->createNemesisToken(
    name: 'App Mobile Client',
    source: 'mobile',
    abilities: ['buy_ticket', 'view_tickets']
);

// Pour un point de contrôle (kiosque)
$checkpointToken = $checkpoint->createNemesisToken(
    name: 'Scanner Portique',
    source: 'kiosk',
    abilities: ['scan_ticket', 'validate_entry', 'reject_entry'],
    metadata: ['hardware_id' => 'SCAN-01', 'location' => 'Entrée A']
);
```

### Routes protégées

```php
// Endpoint utilisateur
Route::middleware('nemesis.auth:buy_ticket')->post('/tickets', [TicketController::class, 'buy']);

// Endpoint point de contrôle
Route::middleware('nemesis.auth:scan_ticket')->post('/scan', [ScanController::class, 'validate']);
```

### Dans `ScanController`

```php
public function validate(Request $request)
{
    $checkpoint = current_authenticatable(); // instance de CheckPoint
    $token = current_token();

    if (!$token->can('validate_entry')) {
        return response()->json(['error' => 'Permission refusée'], 403);
    }

    // scanner le billet...
    return response()->json([
        'status' => 'entrée validée',
        'checkpoint' => current_authenticatable_format() // version formatée
    ]);
}
```

### Révocation depuis le point de contrôle

```php
public function logoutCheckPoint()
{
    $checkpoint = current_authenticatable();
    
    // La méthode retourne un booléen indiquant si la révocation a réussi
    if ($checkpoint->revokeCurrentNemesisToken()) {
        return response()->json(['message' => 'Token révoqué avec succès']);
    }
    
    return response()->json(['error' => 'Aucun token actif trouvé'], 404);
}
```

### Gestion des sessions multi-appareils

```php
public function manageSessions(Request $request)
{
    $user = current_authenticatable();
    
    // Afficher toutes les sessions actives
    $sessions = [
        'web' => $user->getNemesisTokensBySource('web')->count(),
        'mobile' => $user->getNemesisTokensBySource('mobile')->count(),
        'api' => $user->getNemesisTokensBySource('api')->count(),
    ];
    
    // Actions possibles
    $action = $request->input('action');
    
    match($action) {
        'logout_web' => $user->revokeNemesisTokensBySource('web'),
        'logout_mobile' => $user->revokeNemesisTokensBySource('mobile'),
        'logout_all' => $user->revokeNemesisTokens(),
        'logout_old' => $user->revokeNemesisTokensWhere([
            'last_used_at' => ['<', now()->subDays(30)]
        ]),
        'keep_only_current' => $user->revokeNemesisTokensWhere([
            ['token_hash', '!=', current_token()->token_hash]
        ]),
        default => null
    };
    
    return response()->json([
        'current_sessions' => $sessions,
        'message' => 'Action effectuée avec succès'
    ]);
}
```

---

## 📊 API complète du modèle (MustNemesis)

| Méthode | Description | Retour |
|---------|-------------|--------|
| `nemesisFormat()` | **OBLIGATOIRE** - Définit les données exposées par l'API | `array` |
| `createNemesisToken()` | Génère un nouveau token (hash stocké) | `string` |
| `deleteNemesisTokens()` | Suppression définitive de tous les tokens | `int` |
| `revokeNemesisTokens()` | Soft delete de tous les tokens | `int` |
| `revokeNemesisTokensBySource()` | Soft delete des tokens par source | `int` |
| `revokeNemesisTokensByName()` | Soft delete des tokens par nom | `int` |
| `revokeNemesisTokensBySourceAndName()` | Soft delete par source ET nom | `int` |
| `revokeAllNemesisTokensExceptSource()` | Garde une source, supprime les autres | `int` |
| `revokeNemesisTokensWhere()` | Soft delete avec critères personnalisés (opérateurs supportés) | `int` |
| `deleteCurrentNemesisToken()` | Supprime définitivement le token courant | `bool` |
| `revokeCurrentNemesisToken()` | Soft delete du token courant | `bool` |
| `currentNemesisToken()` | Récupère le modèle du token courant | `?NemesisToken` |
| `hasNemesisTokens()` | Vérifie l’existence de tokens | `bool` |
| `getNemesisToken()` | Trouve un token par sa valeur brute | `?NemesisToken` |
| `validateNemesisToken()` | Vérifie validité (expiration + non révoqué) | `bool` |
| `touchNemesisToken()` | Met à jour `last_used_at` | `bool` |
| `getNemesisTokensBySource()` | Filtre par source (`web`, `mobile`, etc.) | `iterable` |
| `revokeExpiredNemesisTokens()` | Soft delete des expirés | `int` |
| `forceDeleteExpiredNemesisTokens()` | Suppression définitive des expirés | `int` |
| `restoreNemesisTokens()` | Restaure les tokens soft-deleted | `int` |

---

## 🧰 NemesisManager (facade)

```php
use Kani\Nemesis\Facades\Nemesis;

// Gestion standard
Nemesis::createToken($user, 'API Token', 'api', ['read']);
Nemesis::validateToken($user, $token);
Nemesis::getTokenableModel($token);
Nemesis::deleteToken($user, $token);
Nemesis::revokeExpiredTokens();

// Nouvelles méthodes de révocation granulaire
Nemesis::revokeTokensBySource($user, 'web');
Nemesis::revokeTokensByName($user, 'web_session');
Nemesis::revokeTokensBySourceAndName($user, 'web', 'web_session');
Nemesis::revokeAllTokensExceptSource($user, 'mobile');
Nemesis::revokeTokensWhere($user, ['created_at' => ['<', now()->subDays(30)]]);

// Force delete
Nemesis::revokeTokensBySource($user, 'web', force: true);
```

---

## ⚙️ Configuration (`config/nemesis.php`)

```php
return [
    'token_length' => 64,               // longueur du token en clair
    'hash_algorithm' => 'sha256',       // hash pour stockage
    'expiration' => 60,                 // null = jamais, sinon minutes

    'middleware' => [
        'parameter_name' => 'nemesisAuth',    // nom dans la requête
        'token_header' => 'Authorization',    // ou X-Custom-Token
        'security_headers' => true,           // X-Frame-Options, etc.
        'validate_origin' => true,            // vérification CORS
    ],

    'cors' => [
        'allow_credentials' => true,
        'max_age' => 86400,
        'expose_token_info' => false,
    ],

    'cleanup' => [
        'auto_cleanup' => true,
        'frequency' => 60,                     // minutes
        'keep_expired_for_days' => 30,
    ],
];
```

---

## 📁 Structure des migrations

```php
// Table nemesis_tokens
- id
- token_hash (unique)
- tokenable_type / tokenable_id (polymorphique)
- name, source
- abilities (JSON)
- metadata (JSON)
- allowed_origins (JSON)
- last_used_at, expires_at
- softDeletes, timestamps
```

---

## 🧠 Ce que Nemesis résout concrètement

| Problème | Solution Nemesis |
|----------|------------------|
| Plusieurs modèles doivent s’authentifier (User, CheckPoint) | Polymorphisme `tokenable` |
| Contrôle total des données exposées via API | Méthode obligatoire `nemesisFormat()` |
| Déconnexion sélective (web vs mobile) | `revokeNemesisTokensBySource()` |
| Révocation granulaire par type de token | `revokeNemesisTokensByName()` |
| Nettoyage des tokens inactifs | `revokeNemesisTokensWhere()` |
| Garder certains tokens actifs | `revokeAllNemesisTokensExceptSource()` |
| Révocation avec opérateurs personnalisés | `revokeNemesisTokensWhere()` avec opérateurs |
| Un token ne doit servir que pour certaines origines | `allowed_origins` + validation middleware |
| Une application mobile a moins de droits qu’un admin | `abilities` (ex: `scan_ticket` vs `delete_user`) |
| Besoin de tracer le contexte (IP, device, version) | `metadata` validé et nettoyé |
| Révocation sans perte d’audit | `softDeletes` |
| Nettoyage des tokens obsolètes | Commande schedule + `auto_cleanup` |
| Un token peut expirer après X minutes | `expires_at` + `isExpired()` |
| Savoir si une opération a réussi (suppression/révocation) | Retour `int`/`bool` des méthodes concernées |

---

## 🔄 Comparaison rapide avec Laravel Sanctum

| Fonctionnalité | Sanctum | Nemesis |
|----------------|---------|---------|
| Multi-modèles (User + CheckPoint) | ❌ (seulement User) | ✅ (tout modèle) |
| Contrôle explicite de l'exposition des données | ❌ | ✅ (méthode obligatoire) |
| Révocation granulaire par source/nom | ❌ | ✅ |
| Révocation avec opérateurs (<, >, <=, >=) | ❌ | ✅ |
| Révocation par critères personnalisés | ❌ | ✅ |
| Restrictions CORS par token | ❌ (globale) | ✅ (par token) |
| Métadonnées enrichies | ❌ | ✅ (validation stricte) |
| Soft delete des tokens | ❌ | ✅ |
| Abilities sans user | ❌ | ✅ |
| Nettoyage auto configurable | ❌ | ✅ |
| Retour booléen/int sur les opérations | ❌ | ✅ |

---

## 🤝 Contribution

1. Fork + branche `feature/ma-fonctionnalité`
2. `composer test` (271 tests doivent passer)
3. Pull request vers `main`

---

## 📄 Licence

MIT © [Kani](https://github.com/kani)

---

**Nemesis** – L’authentification par tokens multi-modèles pour Laravel, pensée pour les systèmes complexes où chaque acteur (utilisateur, point de contrôle, API client) a ses propres jetons, droits et origines, avec un **contrôle total sur les données exposées**, une **révocation granulaire** (par source, nom ou critères personnalisés avec opérateurs), et des **retours explicites sur les opérations critiques**. 🔐⚡
