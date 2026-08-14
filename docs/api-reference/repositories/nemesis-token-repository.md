# NemesisTokenRepository - Référence Technique

## Description

Repository pour la gestion des tokens d'authentification dans la base de données.

## Hiérarchie

```
AbstractRepository
    └── NemesisTokenRepository
```

## Rôle principal

Fournit une abstraction pour les opérations de base de données sur les tokens. Gère la persistance, la récupération, les filtres et les opérations en masse avec support du soft delete.

## Installation

Le repository est automatiquement enregistré via le ServiceProvider.

```php
use AndyDefer\Nemesis\Contracts\Repositories\NemesisTokenRepositoryInterface;

$repository = app(NemesisTokenRepositoryInterface::class);
```

## API

### `findWithTrashedByFilters(NemesisTokenFilterRecord $filters): Collection`

| Paramètre | Type | Description |
|-----------|------|-------------|
| `$filters` | `NemesisTokenFilterRecord` | Filtres à appliquer |

**Retourne :** `Collection<int, NemesisToken>` - Collection des tokens trouvés (incluant les soft-deleted)

**Exemple :**
```php
$filters = NemesisTokenFilterRecord::from([
    'tokenable_type' => 'App\Models\User',
    'source' => 'web',
]);

$tokens = $repository->findWithTrashedByFilters($filters);
```

### `existsWithTrashed(NemesisTokenFilterRecord $filters): bool`

| Paramètre | Type | Description |
|-----------|------|-------------|
| `$filters` | `NemesisTokenFilterRecord` | Filtres à appliquer |

**Retourne :** `bool` - True si au moins un token correspond (incluant les soft-deleted)

**Exemple :**
```php
$filters = NemesisTokenFilterRecord::from([
    'tokenable_id' => 42,
]);

if ($repository->existsWithTrashed($filters)) {
    // Le modèle a des tokens
}
```

### `restoreBulkForTokenable(string $tokenableType, int $tokenableId): int`

| Paramètre | Type | Description |
|-----------|------|-------------|
| `$tokenableType` | `string` | Le type du modèle (ex: App\Models\User) |
| `$tokenableId` | `int` | L'ID du modèle |

**Retourne :** `int` - Le nombre de tokens restaurés

**Exemple :**
```php
$count = $repository->restoreBulkForTokenable('App\Models\User', 42);
// Restaure tous les tokens de l'utilisateur 42
```

## Cas d'utilisation

### Cas 1 : Récupérer les tokens d'un modèle avec trashed

```php
$filters = NemesisTokenFilterRecord::from([
    'tokenable_type' => 'App\Models\User',
    'tokenable_id' => 42,
]);

// Récupère tous les tokens, y compris les soft-deleted
$tokens = $repository->findWithTrashedByFilters($filters);
```

### Cas 2 : Filtrer par source et expiration

```php
$filters = NemesisTokenFilterRecord::from([
    'source' => 'web',
    'is_expired' => true,
]);

$expiredWebTokens = $repository->findWithTrashedByFilters($filters);
```

### Cas 3 : Vérifier l'existence de tokens

```php
$filters = NemesisTokenFilterRecord::from([
    'tokenable_type' => 'App\Models\User',
    'tokenable_id' => 42,
]);

if ($repository->exists($filters)) {
    // L'utilisateur a des tokens actifs
}
```

### Cas 4 : Restaurer tous les tokens d'un modèle

```php
$count = $repository->restoreBulkForTokenable('App\Models\User', 42);
echo "{$count} tokens restaurés";
```

## Flux d'exécution

```
Requête du repository
    ↓
Création de la query Eloquent
    ↓
Application des filtres
    ├── token_hash → where('token_hash', $value)
    ├── tokenable_type → where('tokenable_type', $value)
    ├── tokenable_id → where('tokenable_id', $value)
    ├── name → where('name', 'like', '%$value%')
    ├── source → where('source', $value)
    ├── is_expired → where(...) / orWhere(...)
    ├── is_revoked → withTrashed() / withoutTrashed()
    └── created_before → where('created_at', '<', $value)
    ↓
Exécution de la requête
    ↓
Retour des résultats (Collection ou bool)
```

## Gestion des erreurs

| Situation | Exception | Message |
|-----------|-----------|---------|
| Aucune | - | - |

*Le repository ne lève pas d'exceptions. Il retourne des collections vides ou false en cas d'absence de résultats.*

## Intégration

Ce repository s'intègre avec :

- **AbstractRepository** : Classe de base pour les repositories
- **NemesisToken** : Modèle Eloquent
- **NemesisTokenRecord** : Record pour les données
- **NemesisTokenFilterRecord** : Filtres pour les requêtes
- **NemesisTokenRepositoryInterface** : Interface du repository

## Filtres disponibles

| Filtre | Type | Description |
|--------|------|-------------|
| `token_hash` | `string|null` | Hash exact du token |
| `tokenable_type` | `string|null` | Type du modèle (exact) |
| `tokenable_id` | `int|null` | ID du modèle (exact) |
| `name` | `string|null` | Nom (recherche LIKE partielle) |
| `source` | `string|null` | Source (exact) |
| `is_expired` | `bool|null` | True = expiré, False = non expiré |
| `is_revoked` | `bool|null` | True = soft-deleted, False = actif |
| `created_before` | `DateTimeVO|null` | Créé avant cette date |

## Performance

- Utilise les index de la base de données (`tokenable_type`, `tokenable_id`, `expires_at`)
- Les requêtes LIKE sur `name` peuvent être lentes sur de grands volumes
- Utilise `limit()` pour les requêtes volumineuses
- Complexité : O(log n) pour les requêtes indexées

## Compatibilité

| Version PHP | Support |
|-------------|---------|
| PHP 8.1+ | ✅ Complet |
| PHP 8.0 | ✅ Complet |

## Exemple complet

```php
<?php

declare(strict_types=1);

use AndyDefer\Nemesis\Contracts\Repositories\NemesisTokenRepositoryInterface;
use AndyDefer\Nemesis\Records\NemesisTokenFilterRecord;
use AndyDefer\PhpVo\ValueObjects\DateTimeVO;

class TokenManagementService
{
    public function __construct(
        private readonly NemesisTokenRepositoryInterface $repository,
    ) {}

    public function getActiveTokensForUser(int $userId): Collection
    {
        $filters = NemesisTokenFilterRecord::from([
            'tokenable_type' => 'App\Models\User',
            'tokenable_id' => $userId,
            'is_expired' => false,
            'is_revoked' => false,
        ]);

        return $this->repository->findBy($filters);
    }

    public function cleanupOldTokens(int $days): int
    {
        $cutoffDate = new DateTimeVO(now()->subDays($days)->toIso8601String());
        
        $filters = NemesisTokenFilterRecord::from([
            'created_before' => $cutoffDate,
            'is_expired' => true,
        ]);

        return $this->repository->forceDeleteBulk($filters);
    }

    public function restoreUserTokens(int $userId): int
    {
        return $this->repository->restoreBulkForTokenable(
            'App\Models\User',
            $userId
        );
    }

    public function getTokensBySource(string $source): Collection
    {
        $filters = NemesisTokenFilterRecord::from([
            'source' => $source,
        ]);

        return $this->repository->findBy($filters);
    }

    public function hasActiveTokens(int $userId): bool
    {
        $filters = NemesisTokenFilterRecord::from([
            'tokenable_type' => 'App\Models\User',
            'tokenable_id' => $userId,
            'is_revoked' => false,
        ]);

        return $this->repository->exists($filters);
    }
}

// Utilisation
$service = app(TokenManagementService::class);

// Récupérer les tokens actifs d'un utilisateur
$tokens = $service->getActiveTokensForUser(42);

// Nettoyer les tokens de plus de 30 jours
$deleted = $service->cleanupOldTokens(30);

// Restaurer tous les tokens d'un utilisateur
$restored = $service->restoreUserTokens(42);
```

## Voir aussi

- `AbstractRepository` - Classe de base des repositories
- `NemesisToken` - Modèle Eloquent
- `NemesisTokenRecord` - Record des données
- `NemesisTokenFilterRecord` - Filtres de recherche
- `NemesisService` - Service de gestion des tokens
---