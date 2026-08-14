# NemesisService - Référence Technique

## Description

Service principal de gestion des tokens d'authentification Nemesis.

## Hiérarchie

```
NemesisInterface
    └── NemesisService
```

## Rôle principal

Fournit la gestion complète du cycle de vie des tokens incluant la création, la validation, la révocation, la gestion des métadonnées et des capacités. Tous les tokens sont associés à un modèle authentifiable (User, ApiClient, etc.) et supportent le soft delete, l'expiration et les restrictions d'origine.

## Installation

Le service est automatiquement enregistré via le ServiceProvider.

```php
use AndyDefer\Nemesis\Contracts\Services\NemesisInterface;

$nemesisService = app(NemesisInterface::class);
```

## API

### Création de tokens

#### `create(NemesisTokenRecord $record, Model $tokenable): NemesisToken`

| Paramètre | Type | Description |
|-----------|------|-------------|
| `$record` | `NemesisTokenRecord` | Configuration du token |
| `$tokenable` | `Model` | Modèle associé au token |

**Retourne :** `NemesisToken` - Le token créé

**Exemple :**
```php
$record = NemesisTokenRecord::from([
    'name' => 'API Token',
    'source' => 'api',
    'abilities' => ['read', 'write'],
]);

$token = $nemesisService->create($record, $user);
```

#### `createWithPlainToken(NemesisTokenRecord $record, Model $tokenable): array`

| Paramètre | Type | Description |
|-----------|------|-------------|
| `$record` | `NemesisTokenRecord` | Configuration du token |
| `$tokenable` | `Model` | Modèle associé au token |

**Retourne :** `array[NemesisToken, string]` - [Token, PlainToken]

**Exemple :**
```php
[$token, $plainToken] = $nemesisService->createWithPlainToken($record, $user);
// $plainToken est le token en clair à retourner au client
```

### Récupération

#### `find(int $tokenId): ?NemesisToken`

| Paramètre | Type | Description |
|-----------|------|-------------|
| `$tokenId` | `int` | ID du token |

**Retourne :** `?NemesisToken` - Token trouvé ou null

#### `findByHash(string $tokenHash): ?NemesisToken`

| Paramètre | Type | Description |
|-----------|------|-------------|
| `$tokenHash` | `string` | Hash du token |

**Retourne :** `?NemesisToken` - Token trouvé ou null

#### `findByFilters(NemesisTokenFilterRecord $filters, ?int $limit = null, ?SortColumns $sortBy = null, array $columns = ['*']): Collection`

| Paramètre | Type | Description |
|-----------|------|-------------|
| `$filters` | `NemesisTokenFilterRecord` | Filtres de recherche |
| `$limit` | `?int` | Limite de résultats |
| `$sortBy` | `?SortColumns` | Tri |
| `$columns` | `array` | Colonnes à sélectionner |

**Retourne :** `Collection<int, NemesisToken>` - Collection de tokens

### Mise à jour

#### `update(int $tokenId, NemesisTokenRecord $record): NemesisToken`

| Paramètre | Type | Description |
|-----------|------|-------------|
| `$tokenId` | `int` | ID du token |
| `$record` | `NemesisTokenRecord` | Nouvelles données |

**Retourne :** `NemesisToken` - Token mis à jour

### Suppression

#### `delete(int $tokenId): bool` - Soft delete
#### `forceDelete(int $tokenId): bool` - Suppression définitive
#### `restore(int $tokenId): bool` - Restauration

### Révocation par modèle

#### `revokeTokensBySource(Model $tokenable, string $source, bool $force = false): int`
#### `revokeTokensByName(Model $tokenable, string $name, bool $force = false): int`
#### `revokeTokensBySourceAndName(Model $tokenable, string $source, string $name, bool $force = false): int`
#### `revokeAllTokensExceptSource(Model $tokenable, string $source, bool $force = false): int`

### Validation et vérification

#### `can(NemesisToken $token, string $ability): bool`
#### `canAll(NemesisToken $token, array $abilities): bool`
#### `canUseFromOrigin(NemesisToken $token, ?string $origin): bool`

### Métadonnées

#### `getMetadata(NemesisToken $token, string $key, mixed $default = null): mixed`
#### `setMetadata(NemesisToken $token, string $key, mixed $value): NemesisToken`
#### `mergeMetadata(NemesisToken $token, array $metadata): NemesisToken`
#### `clearMetadata(NemesisToken $token): NemesisToken`

### Origines autorisées

#### `addAllowedOrigin(NemesisToken $token, string $origin): NemesisToken`
#### `removeAllowedOrigin(NemesisToken $token, string $origin): NemesisToken`
#### `setAllowedOrigins(NemesisToken $token, ?array $origins): NemesisToken`

## Cas d'utilisation

### Cas 1 : Création d'un token pour un utilisateur

```php
$record = NemesisTokenRecord::from([
    'name' => 'Mobile App Token',
    'source' => 'mobile',
    'abilities' => ['scan_ticket', 'view_stats'],
    'metadata' => ['device' => 'iPhone 15', 'app_version' => '2.1.0'],
]);

[$token, $plainToken] = $nemesisService->createWithPlainToken($record, $user);

// Retourner $plainToken au client
```

### Cas 2 : Vérification des permissions

```php
$token = $nemesisService->findByHash($hashedToken);

if ($nemesisService->can($token, 'admin')) {
    // Action admin
}

if ($nemesisService->canAll($token, ['read', 'write'])) {
    // Lecture et écriture autorisées
}
```

### Cas 3 : Révocation sélective

```php
// Révoquer tous les tokens web d'un utilisateur
$count = $nemesisService->revokeTokensBySource($user, 'web');

// Révoquer un token spécifique
$nemesisService->revoke($token);

// Révoquer tous les tokens sauf ceux de l'app mobile
$count = $nemesisService->revokeAllTokensExceptSource($user, 'mobile');
```

### Cas 4 : Gestion des métadonnées

```php
$token = $nemesisService->find($tokenId);

// Ajouter des métadonnées
$nemesisService->setMetadata($token, 'last_ip', $request->ip());
$nemesisService->mergeMetadata($token, [
    'session_id' => $sessionId,
    'user_agent' => $request->userAgent(),
]);

// Récupérer une métadonnée
$device = $nemesisService->getMetadata($token, 'device', 'unknown');
```

### Cas 5 : Restrictions d'origine

```php
// Ajouter une origine autorisée
$nemesisService->addAllowedOrigin($token, 'https://monapp.com');
$nemesisService->addAllowedOrigin($token, 'https://*.example.com');

// Vérifier l'origine
if ($nemesisService->canUseFromOrigin($token, $origin)) {
    // Origine autorisée
}
```

## Flux d'exécution

```
Création de token
    ↓
Validation des métadonnées
    ↓
Génération du token en clair (Str::random)
    ↓
Hash du token
    ↓
Création du record
    ↓
Persistance en base (repository)
    ↓
Retour du token et du plainToken
```

## Gestion des erreurs

| Situation | Exception | Message |
|-----------|-----------|---------|
| Métadonnées invalides | `MetadataValidationException` | Message d'erreur de validation |
| Token non trouvé | - | Retourne null |

## Intégration

Ce service s'intègre avec :

- **NemesisTokenRepository** : Persistance des tokens
- **NemesisConfigInterface** : Configuration
- **MetadataValidatorService** : Validation des métadonnées
- **NemesisTokenFilterRecord** : Filtres de recherche
- **StrictDataObject** : Objets de données

## Performance

- Recherche par hash : O(log n)
- Création de token : O(1)
- Révocation en masse : O(n) avec requêtes optimisées
- Utilise les index de la base de données

## Compatibilité

| Version PHP | Support |
|-------------|---------|
| PHP 8.1+ | ✅ Complet |
| PHP 8.0 | ✅ Complet |

## Exemple complet

```php
<?php

declare(strict_types=1);

use AndyDefer\Nemesis\Contracts\Services\NemesisInterface;
use AndyDefer\Nemesis\Records\NemesisTokenFilterRecord;
use AndyDefer\Nemesis\Records\NemesisTokenRecord;

class TokenManager
{
    public function __construct(
        private readonly NemesisInterface $nemesisService,
    ) {}

    public function createUserToken(User $user, array $abilities = []): string
    {
        $record = NemesisTokenRecord::from([
            'name' => 'User Token',
            'source' => 'web',
            'abilities' => $abilities,
            'metadata' => [
                'user_id' => $user->id,
                'created_at' => now()->toIso8601String(),
            ],
        ]);

        [$token, $plainToken] = $this->nemesisService->createWithPlainToken($record, $user);

        return $plainToken;
    }

    public function validateToken(string $plainToken, User $user): bool
    {
        return $this->nemesisService->validateToken($plainToken, $user);
    }

    public function revokeAllUserTokens(User $user): int
    {
        return $this->nemesisService->revokeAllTokens($user);
    }

    public function getActiveTokens(string $tokenableType): Collection
    {
        $filters = NemesisTokenFilterRecord::from([
            'tokenable_type' => $tokenableType,
            'is_expired' => false,
            'is_revoked' => false,
        ]);

        return $this->nemesisService->findByFilters($filters);
    }

    public function cleanupExpiredTokens(string $tokenableType): int
    {
        $filters = NemesisTokenFilterRecord::from([
            'tokenable_type' => $tokenableType,
            'is_expired' => true,
        ]);

        return $this->nemesisService->forceDeleteBulk($filters);
    }
}

// Utilisation
$manager = new TokenManager($nemesisService);

// Créer un token
$plainToken = $manager->createUserToken($user, ['read', 'write']);

// Valider un token
$isValid = $manager->validateToken($plainToken, $user);

// Révoquer tous les tokens
$count = $manager->revokeAllUserTokens($user);

// Nettoyer les tokens expirés
$deleted = $manager->cleanupExpiredTokens('App\Models\User');
```

## Voir aussi

- `NemesisToken` - Modèle de token
- `NemesisTokenRecord` - Record de token
- `NemesisTokenFilterRecord` - Filtres de recherche
- `NemesisTokenRepository` - Repository de tokens
- `MetadataValidatorService` - Validation des métadonnées
- `NemesisConfigInterface` - Configuration
---