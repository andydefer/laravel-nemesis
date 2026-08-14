# NemesisAuthenticationService - Référence Technique

## Description

Service d'authentification des requêtes utilisant les tokens Nemesis.

## Hiérarchie

```
NemesisAuthenticationInterface
    └── NemesisAuthenticationService
```

## Rôle principal

Fournit les capacités d'authentification complètes incluant l'extraction des tokens, la validation, la vérification d'expiration, les restrictions d'origine, les permissions et la gestion des métadonnées de tracking.

## Installation

Le service est automatiquement enregistré via le ServiceProvider.

```php
use AndyDefer\Nemesis\Contracts\Services\NemesisAuthenticationInterface;

$authService = app(NemesisAuthenticationInterface::class);
```

## API

### `authenticate(Request $request, ?string $requiredAbility = null, ?string $token = null): AuthenticationResultVO`

| Paramètre | Type | Description |
|-----------|------|-------------|
| `$request` | `Request` | La requête HTTP |
| `$requiredAbility` | `string|null` | Ability optionnelle requise |
| `$token` | `string|null` | Token optionnel (utilisé à la place de l'extraction) |

**Retourne :** `AuthenticationResultVO` - Résultat de l'authentification

**Exemple :**
```php
$result = $authService->authenticate($request, 'admin');
if ($result->isSuccess()) {
    $user = $result->getAuthenticatable();
}
```

### `authenticateToRecord(Request $request, ?string $requiredAbility = null): AuthenticationResultRecord`

| Paramètre | Type | Description |
|-----------|------|-------------|
| `$request` | `Request` | La requête HTTP |
| `$requiredAbility` | `string|null` | Ability optionnelle requise |

**Retourne :** `AuthenticationResultRecord` - Résultat de l'authentification sous forme de record

**Exemple :**
```php
$record = $authService->authenticateToRecord($request);
if ($record->success) {
    // Authentifié
}
```

### `getFormattedAuthenticatable(mixed $authenticatable): ?AbstractData`

| Paramètre | Type | Description |
|-----------|------|-------------|
| `$authenticatable` | `mixed` | Le modèle authentifié |

**Retourne :** `?AbstractData` - Le modèle formaté ou null

**Exemple :**
```php
$formatted = $authService->getFormattedAuthenticatable($user);
// Retourne les données formatées via nemesisFormat()
```

## Cas d'utilisation

### Cas 1 : Authentification avec Bearer Token

```php
class AuthMiddleware
{
    public function handle(Request $request, Closure $next)
    {
        $result = $this->authService->authenticate($request);
        
        if (!$result->isSuccess()) {
            return response()->json(['error' => 'Unauthorized'], 401);
        }
        
        $request->merge([
            'user' => $result->getAuthenticatable(),
            'token' => $result->getTokenRecord(),
        ]);
        
        return $next($request);
    }
}
```

### Cas 2 : Authentification avec vérification d'ability

```php
class AdminMiddleware
{
    public function handle(Request $request, Closure $next)
    {
        $result = $this->authService->authenticate($request, 'admin');
        
        if (!$result->isSuccess()) {
            abort(403, 'Admin access required');
        }
        
        return $next($request);
    }
}
```

### Cas 3 : Authentification avec token fourni

```php
class TokenController
{
    public function validate(Request $request)
    {
        $plainToken = $request->input('token');
        
        $result = $this->authService->authenticate($request, null, $plainToken);
        
        return response()->json([
            'valid' => $result->isSuccess(),
            'user' => $result->getAuthenticatable(),
        ]);
    }
}
```

## Flux d'exécution

```
authenticate($request, $ability, $token)
    ↓
Extraire le token (ou utiliser celui fourni)
    ├── Non → MISSING_TOKEN
    └── Oui → Poursuivre
        ↓
Rechercher le token dans la base
    ├── Non → INVALID_TOKEN
    └── Oui → Poursuivre
            ↓
Vérifier l'expiration
    ├── Expiré → TOKEN_EXPIRED
    └── Valide → Poursuivre
                ↓
Vérifier les restrictions d'origine
    ├── Non autorisé → ORIGIN_NOT_ALLOWED
    └── Autorisé → Poursuivre
                    ↓
Vérifier l'ability (si spécifiée)
    ├── Non présente → INSUFFICIENT_PERMISSIONS
    └── Présente → Poursuivre
                    ↓
Récupérer le modèle authentifiable
    ├── Non trouvé → INVALID_TOKEN
    └── Trouvé → Poursuivre
                ↓
Vérifier l'implémentation de MustNemesis
    ├── Non → INVALID_AUTHENTICATABLE_MODEL
    └── Oui → Poursuivre
            ↓
Mettre à jour l'utilisation du token
    ├── last_used_at
    └── Métadonnées de tracking
    ↓
Convertir en record
    ↓
Retourner AuthenticationResultVO
```

## Gestion des erreurs

| Code d'erreur | HTTP | Description |
|---------------|------|-------------|
| `MISSING_TOKEN` | 401 | Token non fourni |
| `INVALID_TOKEN` | 401 | Token invalide |
| `TOKEN_EXPIRED` | 401 | Token expiré |
| `INSUFFICIENT_PERMISSIONS` | 403 | Permissions insuffisantes |
| `ORIGIN_NOT_ALLOWED` | 403 | Origine non autorisée |
| `INVALID_AUTHENTICATABLE_MODEL` | 500 | Modèle non valide |

## Intégration

Ce service s'intègre avec :

- **NemesisConfigInterface** : Configuration du système
- **NemesisInterface** : Service de gestion des tokens
- **DatabaseManager** : Requêtes sur les modèles
- **MetadataValidatorService** : Validation des métadonnées
- **MustNemesis** : Interface pour les modèles
- **NemesisToken** : Modèle de token
- **AuthenticationResultVO** : Value Object de résultat

## Métadonnées de tracking

| Clé | Description |
|-----|-------------|
| `last_auth_ip` | Dernière IP d'authentification |
| `last_auth_ua` | Dernier User-Agent |
| `auth_count` | Nombre d'authentifications |

## Performance

- Recherche de token en O(log n)
- Requête de modèle en O(1) avec index
- Mise à jour des métadonnées en O(1)
- Complexité : O(log n)

## Compatibilité

| Version PHP | Support |
|-------------|---------|
| PHP 8.1+ | ✅ Complet |
| PHP 8.0 | ✅ Complet |

## Exemple complet

```php
<?php

declare(strict_types=1);

use AndyDefer\Nemesis\Contracts\Services\NemesisAuthenticationInterface;
use AndyDefer\Nemesis\Enums\ErrorCode;

class AuthenticationManager
{
    public function __construct(
        private readonly NemesisAuthenticationInterface $authService,
    ) {}

    public function authenticateRequest(Request $request, ?string $ability = null): array
    {
        $result = $this->authService->authenticate($request, $ability);
        
        if (!$result->isSuccess()) {
            $errorCode = $result->getErrorCode();
            $statusCode = $errorCode->getHttpStatusCode()->value;
            
            return [
                'success' => false,
                'error' => $errorCode->message(),
                'status' => $statusCode,
                'code' => $errorCode->value,
            ];
        }
        
        $authenticatable = $result->getAuthenticatable();
        $formatted = $this->authService->getFormattedAuthenticatable($authenticatable);
        
        return [
            'success' => true,
            'user' => $authenticatable,
            'formatted' => $formatted,
            'token' => $result->getTokenRecord(),
        ];
    }

    public function validateToken(string $plainToken): bool
    {
        $request = new Request();
        $result = $this->authService->authenticate($request, null, $plainToken);
        
        return $result->isSuccess();
    }
}

// Utilisation
$auth = app(AuthenticationManager::class);

// Authentification standard
$result = $auth->authenticateRequest($request);

// Authentification avec ability admin
$result = $auth->authenticateRequest($request, 'admin');

// Validation de token
$isValid = $auth->validateToken('eyJhbGciOiJIUzI1NiIs...');
```

## Voir aussi

- `NemesisService` - Service de gestion des tokens
- `NemesisConfigInterface` - Configuration du système
- `NemesisToken` - Modèle de token
- `MustNemesis` - Interface des modèles
- `AuthenticationResultVO` - Value Object de résultat
- `ErrorCode` - Codes d'erreur
---