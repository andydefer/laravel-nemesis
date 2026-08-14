# NemesisHelper - Référence Technique

## Description

Helper pour accéder facilement aux informations du token et de l'utilisateur authentifié via les middlewares Nemesis.

## Hiérarchie

```
NemesisHelperInterface
    └── NemesisHelper
```

## Rôle principal

Fournit une interface unifiée pour récupérer le token courant, l'utilisateur authentifié et ses métadonnées après le passage du middleware Nemesis. Utilise le cache pour optimiser les accès multiples.

## Installation

Le helper est automatiquement disponible après l'installation du package.

```php
use AndyDefer\Nemesis\Helpers\NemesisHelper;

$helper = app(NemesisHelper::class);
```

## API

### `getCurrentToken(): ?NemesisTokenRecord`

| Paramètre | Type | Description |
|-----------|------|-------------|
| Aucun | - | - |

**Retourne :** `?NemesisTokenRecord` - Le token courant ou null

**Exemple :**
```php
$token = $helper->getCurrentToken();
if ($token) {
    echo $token->name;
}
```

### `getCurrentAuthenticatable(): ?Model`

| Paramètre | Type | Description |
|-----------|------|-------------|
| Aucun | - | - |

**Retourne :** `?Model` - Le modèle authentifié (User, Admin, etc.) ou null

**Exemple :**
```php
$user = $helper->getCurrentAuthenticatable();
if ($user) {
    echo $user->name;
}
```

### `getCurrentAuthenticatableFormat(): ?AbstractData`

| Paramètre | Type | Description |
|-----------|------|-------------|
| Aucun | - | - |

**Retourne :** `?AbstractData` - Le modèle formaté via `nemesisFormat()` ou null

**Exemple :**
```php
$formatted = $helper->getCurrentAuthenticatableFormat();
if ($formatted) {
    return response()->json($formatted);
}
```

### `hasCurrentToken(): bool`

| Paramètre | Type | Description |
|-----------|------|-------------|
| Aucun | - | - |

**Retourne :** `bool` - True si un token est présent, false sinon

### `hasCurrentAuthenticatable(): bool`

| Paramètre | Type | Description |
|-----------|------|-------------|
| Aucun | - | - |

**Retourne :** `bool` - True si un modèle authentifié est présent, false sinon

### `getTokenId(): ?int`

| Paramètre | Type | Description |
|-----------|------|-------------|
| Aucun | - | - |

**Retourne :** `?int` - L'ID du token ou null

### `getTokenableId(): ?int`

| Paramètre | Type | Description |
|-----------|------|-------------|
| Aucun | - | - |

**Retourne :** `?int` - L'ID du modèle associé ou null

### `getTokenableType(): ?string`

| Paramètre | Type | Description |
|-----------|------|-------------|
| Aucun | - | - |

**Retourne :** `?string` - Le type du modèle associé (ex: App\Models\User) ou null

### `getTokenName(): ?string`

| Paramètre | Type | Description |
|-----------|------|-------------|
| Aucun | - | - |

**Retourne :** `?string` - Le nom du token ou null

### `getTokenSource(): ?string`

| Paramètre | Type | Description |
|-----------|------|-------------|
| Aucun | - | - |

**Retourne :** `?string` - La source du token (web, mobile, api, etc.) ou null

### `getTokenAbilities(): ?StringTypedCollection`

| Paramètre | Type | Description |
|-----------|------|-------------|
| Aucun | - | - |

**Retourne :** `?StringTypedCollection` - Les abilities du token ou null

**Exemple :**
```php
$abilities = $helper->getTokenAbilities();
if ($abilities && $abilities->contains('admin')) {
    // L'utilisateur a la permission admin
}
```

### `isTokenExpired(): bool`

| Paramètre | Type | Description |
|-----------|------|-------------|
| Aucun | - | - |

**Retourne :** `bool` - True si le token est expiré, false sinon

### `isTokenValid(): bool`

| Paramètre | Type | Description |
|-----------|------|-------------|
| Aucun | - | - |

**Retourne :** `bool` - True si le token est valide (non expiré), false sinon

### `getTokenMetadata(): ?StrictDataObject`

| Paramètre | Type | Description |
|-----------|------|-------------|
| Aucun | - | - |

**Retourne :** `?StrictDataObject` - Les métadonnées du token ou null

**Exemple :**
```php
$metadata = $helper->getTokenMetadata();
if ($metadata) {
    $device = $metadata->device ?? 'unknown';
}
```

### `getTokenAllowedOrigins(): ?StringTypedCollection`

| Paramètre | Type | Description |
|-----------|------|-------------|
| Aucun | - | - |

**Retourne :** `?StringTypedCollection` - Les origines autorisées pour ce token ou null

### `getTokenExpirationDate(): ?DateTimeVO`

| Paramètre | Type | Description |
|-----------|------|-------------|
| Aucun | - | - |

**Retourne :** `?DateTimeVO` - La date d'expiration du token ou null

### `getTokenLastUsedAt(): ?DateTimeVO`

| Paramètre | Type | Description |
|-----------|------|-------------|
| Aucun | - | - |

**Retourne :** `?DateTimeVO` - La dernière date d'utilisation du token ou null

### `isAuthenticated(): bool`

| Paramètre | Type | Description |
|-----------|------|-------------|
| Aucun | - | - |

**Retourne :** `bool` - True si l'utilisateur est authentifié (token valide + modèle présent), false sinon

**Exemple :**
```php
if ($helper->isAuthenticated()) {
    // L'utilisateur est connecté
}
```

### `isGuest(): bool`

| Paramètre | Type | Description |
|-----------|------|-------------|
| Aucun | - | - |

**Retourne :** `bool` - True si l'utilisateur n'est pas authentifié, false sinon

### `tokenHasAbility(string $ability): bool`

| Paramètre | Type | Description |
|-----------|------|-------------|
| `$ability` | `string` | L'ability à vérifier |

**Retourne :** `bool` - True si le token possède l'ability spécifiée, false sinon

**Exemple :**
```php
if ($helper->tokenHasAbility('admin')) {
    // L'utilisateur a la permission admin
}
```

### `tokenHasAllAbilities(array $abilities): bool`

| Paramètre | Type | Description |
|-----------|------|-------------|
| `$abilities` | `array` | Les abilities à vérifier |

**Retourne :** `bool` - True si le token possède toutes les abilities spécifiées, false sinon

### `isOriginAllowed(string $origin): bool`

| Paramètre | Type | Description |
|-----------|------|-------------|
| `$origin` | `string` | L'origine à vérifier |

**Retourne :** `bool` - True si l'origine est autorisée pour ce token, false sinon

### `clear(): self`

| Paramètre | Type | Description |
|-----------|------|-------------|
| Aucun | - | - |

**Retourne :** `self` - L'instance du helper pour le chaînage

**Exemple :**
```php
$helper->clear(); // Vide le cache et les données de la requête
```

## Cas d'utilisation

### Cas 1 : Récupérer l'utilisateur connecté dans un contrôleur

```php
<?php

namespace App\Http\Controllers;

use AndyDefer\Nemesis\Helpers\NemesisHelper;

class ProfileController extends Controller
{
    public function __construct(
        private readonly NemesisHelper $helper,
    ) {}

    public function show()
    {
        $user = $this->helper->getCurrentAuthenticatable();
        
        if (!$user) {
            return response()->json(['error' => 'Unauthorized'], 401);
        }

        return response()->json([
            'user' => $this->helper->getCurrentAuthenticatableFormat(),
        ]);
    }
}
```

### Cas 2 : Vérifier les permissions

```php
class AdminController extends Controller
{
    public function __construct(
        private readonly NemesisHelper $helper,
    ) {}

    public function index()
    {
        if (!$this->helper->tokenHasAbility('admin')) {
            abort(403, 'Admin access required');
        }

        // Logique admin
    }
}
```

### Cas 3 : Récupérer les métadonnées du token

```php
class AnalyticsController extends Controller
{
    public function track(Request $request)
    {
        $metadata = $this->helper->getTokenMetadata();
        
        if ($metadata) {
            Log::info('Request from device', [
                'device' => $metadata->device ?? 'unknown',
                'source' => $this->helper->getTokenSource(),
            ]);
        }
    }
}
```

### Cas 4 : Vérifier l'état d'authentification

```php
class HomeController extends Controller
{
    public function index()
    {
        if ($this->helper->isAuthenticated()) {
            $user = $this->helper->getCurrentAuthenticatable();
            return view('dashboard', ['user' => $user]);
        }

        return view('welcome');
    }
}
```

### Cas 5 : Vérifier les origines autorisées

```php
class CorsController extends Controller
{
    public function handle(Request $request)
    {
        $origin = $request->header('Origin');
        
        if ($this->helper->isOriginAllowed($origin)) {
            return response()->json(['message' => 'Allowed']);
        }

        return response()->json(['error' => 'Origin not allowed'], 403);
    }
}
```

## Flux d'exécution

```
Requête HTTP
    ↓
Middleware Nemesis exécuté
    ↓
Token et Authenticatable stockés dans la requête
    ↓
NemesisHelper appelé
    ↓
Vérifier le cache
    ├── Hit → Retourner les données en cache
    └── Miss → Lire depuis la requête
        ↓
        Stocker dans le cache
        ↓
        Retourner les données
```

## Gestion des erreurs

| Situation | Exception | Message |
|-----------|-----------|---------|
| Aucune | - | - |

*Le helper ne lève pas d'exceptions. Il retourne null ou false si les données ne sont pas disponibles.*

## Intégration

Ce helper s'intègre avec :

- **NemesisConfigInterface** : Pour lire la configuration du middleware
- **NemesisTokenRecord** : Pour les données du token
- **AbstractData** : Pour les données formatées de l'utilisateur
- **Model** : Pour les modèles Eloquent authentifiés
- **Request** : Pour accéder aux données injectées par le middleware

## Performance

- Utilise un cache interne pour éviter les accès multiples à la requête
- Les méthodes sont légères et optimisées
- Complexité : O(1) pour toutes les méthodes

## Compatibilité

| Version PHP | Support |
|-------------|---------|
| PHP 8.1+ | ✅ Complet |
| PHP 8.0 | ✅ Complet |

## Exemple complet

```php
<?php

declare(strict_types=1);

use AndyDefer\Nemesis\Helpers\NemesisHelper;

class UserController extends Controller
{
    public function __construct(
        private readonly NemesisHelper $helper,
    ) {}

    public function profile(): JsonResponse
    {
        // Vérifier l'authentification
        if (!$this->helper->isAuthenticated()) {
            return response()->json(['error' => 'Unauthorized'], 401);
        }

        // Récupérer l'utilisateur
        $user = $this->helper->getCurrentAuthenticatable();
        $formatted = $this->helper->getCurrentAuthenticatableFormat();

        // Récupérer les informations du token
        $tokenInfo = [
            'id' => $this->helper->getTokenId(),
            'name' => $this->helper->getTokenName(),
            'source' => $this->helper->getTokenSource(),
            'abilities' => $this->helper->getTokenAbilities(),
            'expired' => $this->helper->isTokenExpired(),
            'last_used' => $this->helper->getTokenLastUsedAt(),
        ];

        return response()->json([
            'user' => $formatted,
            'token' => $tokenInfo,
            'metadata' => $this->helper->getTokenMetadata(),
        ]);
    }

    public function adminOnly(): JsonResponse
    {
        // Vérifier la permission admin
        if (!$this->helper->tokenHasAbility('admin')) {
            return response()->json(['error' => 'Admin access required'], 403);
        }

        return response()->json(['message' => 'Welcome admin']);
    }

    public function checkOrigin(): JsonResponse
    {
        $origin = request()->header('Origin');
        
        if ($origin && $this->helper->isOriginAllowed($origin)) {
            return response()->json(['allowed' => true]);
        }

        return response()->json(['allowed' => false], 403);
    }
}
```

## Voir aussi

- `NemesisTokenMiddleware` - Middleware qui injecte les données
- `NemesisTokenRecord` - Record du token
- `NemesisConfigInterface` - Configuration du package
- `MustNemesis` - Interface pour les modèles authentifiables
- `AbstractData` - Classe de base pour les données formatées
---