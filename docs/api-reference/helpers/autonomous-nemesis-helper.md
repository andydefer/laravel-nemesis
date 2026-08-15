# AutonomousNemesisHelper - Référence Technique

## Description

Helper autonome qui permet d'accéder aux informations du token et de l'utilisateur authentifié sans dépendre d'un middleware. Il lit les tokens directement depuis les cookies si ceux-ci ne sont pas injectés dans la requête.

## Hiérarchie / Implémentations

```
NemesisHelper
    └── AutonomousNemesisHelper
```

**Interfaces implémentées :** `NemesisHelperInterface`

## Rôle principal

Ce helper étend `NemesisHelper` en ajoutant la capacité de lire les tokens directement depuis les cookies. Il suit une **priorité stricte** :

1. **Données injectées par middleware** (via la requête)
2. **Lecture directe depuis le cookie** (si aucune donnée n'est injectée)

Cette approche le rend **autonome** : il fonctionne avec ou sans middleware.

## Installation

```bash
# Le helper est automatiquement enregistré dans le conteneur
$helper = app(AutonomousNemesisHelper::class);

# Ou via l'interface
$helper = app(NemesisHelperInterface::class); // Retourne AutonomousNemesisHelper par défaut
```

## API / Méthodes publiques

### `getCurrentToken(): ?NemesisTokenRecord`

| Paramètre | Type | Description |
|-----------|------|-------------|
| Aucun | - | - |

**Retourne :** `?NemesisTokenRecord` - Le token courant ou `null`

**Exemple :**
```php
$token = $helper->getCurrentToken();

if ($token) {
    echo $token->name; // 'Web Token'
}
```

---

### `getCurrentAuthenticatable(): ?Model`

| Paramètre | Type | Description |
|-----------|------|-------------|
| Aucun | - | - |

**Retourne :** `?Model` - L'utilisateur authentifié ou `null`

**Exemple :**
```php
$user = $helper->getCurrentAuthenticatable();

if ($user) {
    echo $user->name; // 'John Doe'
}
```

---

### `getCurrentAuthenticatableFormat(): ?AbstractData`

| Paramètre | Type | Description |
|-----------|------|-------------|
| Aucun | - | - |

**Retourne :** `?AbstractData` - Les données formatées de l'utilisateur ou `null`

**Exemple :**
```php
$formatted = $helper->getCurrentAuthenticatableFormat();

if ($formatted) {
    return response()->json($formatted);
}
```

---

### `hasCurrentToken(): bool`

| Paramètre | Type | Description |
|-----------|------|-------------|
| Aucun | - | - |

**Retourne :** `bool` - `true` si un token est présent, `false` sinon

**Exemple :**
```php
if (!$helper->hasCurrentToken()) {
    return redirect('/login');
}
```

---

### `hasCurrentAuthenticatable(): bool`

| Paramètre | Type | Description |
|-----------|------|-------------|
| Aucun | - | - |

**Retourne :** `bool` - `true` si un utilisateur est présent, `false` sinon

---

### `getTokenId(): ?int`

| Paramètre | Type | Description |
|-----------|------|-------------|
| Aucun | - | - |

**Retourne :** `?int` - L'ID du token ou `null`

---

### `getTokenName(): ?string`

| Paramètre | Type | Description |
|-----------|------|-------------|
| Aucun | - | - |

**Retourne :** `?string` - Le nom du token ou `null`

---

### `getTokenSource(): ?string`

| Paramètre | Type | Description |
|-----------|------|-------------|
| Aucun | - | - |

**Retourne :** `?string` - La source du token ou `null`

---

### `getTokenAbilities(): ?StringTypedCollection`

| Paramètre | Type | Description |
|-----------|------|-------------|
| Aucun | - | - |

**Retourne :** `?StringTypedCollection` - Les abilities du token ou `null`

**Exemple :**
```php
$abilities = $helper->getTokenAbilities();

if ($abilities && $abilities->contains('admin')) {
    // Action admin
}
```

---

### `tokenHasAbility(string $ability): bool`

| Paramètre | Type | Description |
|-----------|------|-------------|
| `$ability` | `string` | L'ability à vérifier |

**Retourne :** `bool` - `true` si le token possède l'ability, `false` sinon

**Exemple :**
```php
if ($helper->tokenHasAbility('admin')) {
    // Accès admin
}
```

---

### `tokenHasAllAbilities(array $abilities): bool`

| Paramètre | Type | Description |
|-----------|------|-------------|
| `$abilities` | `array` | Liste des abilities à vérifier |

**Retourne :** `bool` - `true` si le token possède toutes les abilities, `false` sinon

**Exemple :**
```php
if ($helper->tokenHasAllAbilities(['read', 'write'])) {
    // Lecture et écriture autorisées
}
```

---

### `isTokenExpired(): bool`

| Paramètre | Type | Description |
|-----------|------|-------------|
| Aucun | - | - |

**Retourne :** `bool` - `true` si le token est expiré, `false` sinon

---

### `isTokenValid(): bool`

| Paramètre | Type | Description |
|-----------|------|-------------|
| Aucun | - | - |

**Retourne :** `bool` - `true` si le token est valide, `false` sinon

---

### `isAuthenticated(): bool`

| Paramètre | Type | Description |
|-----------|------|-------------|
| Aucun | - | - |

**Retourne :** `bool` - `true` si l'utilisateur est authentifié, `false` sinon

**Exemple :**
```php
if ($helper->isAuthenticated()) {
    // Utilisateur connecté
}
```

---

### `isGuest(): bool`

| Paramètre | Type | Description |
|-----------|------|-------------|
| Aucun | - | - |

**Retourne :** `bool` - `true` si l'utilisateur est invité, `false` sinon

---

### `getTokenMetadata(): ?StrictDataObject`

| Paramètre | Type | Description |
|-----------|------|-------------|
| Aucun | - | - |

**Retourne :** `?StrictDataObject` - Les métadonnées du token ou `null`

---

### `getTokenAllowedOrigins(): ?StringTypedCollection`

| Paramètre | Type | Description |
|-----------|------|-------------|
| Aucun | - | - |

**Retourne :** `?StringTypedCollection` - Les origines autorisées ou `null`

---

### `isOriginAllowed(string $origin): bool`

| Paramètre | Type | Description |
|-----------|------|-------------|
| `$origin` | `string` | L'origine à vérifier |

**Retourne :** `bool` - `true` si l'origine est autorisée, `false` sinon

---

### `getTokenExpirationDate(): ?DateTimeVO`

| Paramètre | Type | Description |
|-----------|------|-------------|
| Aucun | - | - |

**Retourne :** `?DateTimeVO` - La date d'expiration du token ou `null`

---

### `getTokenLastUsedAt(): ?DateTimeVO`

| Paramètre | Type | Description |
|-----------|------|-------------|
| Aucun | - | - |

**Retourne :** `?DateTimeVO` - La date de dernière utilisation ou `null`

---

### `clear(): self`

| Paramètre | Type | Description |
|-----------|------|-------------|
| Aucun | - | - |

**Retourne :** `self` - L'instance courante pour le chaînage

**Exemple :**
```php
$helper->clear(); // Vide le cache
```

## Cas d'utilisation

### Cas 1 : Accès à l'utilisateur sans middleware

**Problème** : On veut récupérer l'utilisateur courant dans une route qui n'a pas de middleware.

**Solution** : Utiliser `AutonomousNemesisHelper` qui lit depuis le cookie.

```php
use AndyDefer\Nemesis\Helpers\AutonomousNemesisHelper;

Route::get('/profile', function (AutonomousNemesisHelper $helper) {
    $user = $helper->getCurrentAuthenticatable();
    
    if (!$user) {
        return redirect('/login');
    }
    
    return view('profile', [
        'user' => $helper->getCurrentAuthenticatableFormat(),
    ]);
});
```

### Cas 2 : Vérification des permissions

**Problème** : Vérifier si l'utilisateur a les droits admin.

**Solution** : Utiliser `tokenHasAbility()`.

```php
use AndyDefer\Nemesis\Helpers\AutonomousNemesisHelper;

class AdminController extends Controller
{
    public function __construct(
        private readonly AutonomousNemesisHelper $helper,
    ) {}

    public function dashboard()
    {
        if (!$this->helper->isAuthenticated()) {
            abort(401);
        }

        if (!$this->helper->tokenHasAbility('admin')) {
            abort(403, 'Admin access required');
        }

        return view('admin.dashboard');
    }
}
```

### Cas 3 : Accès aux métadonnées du token

**Problème** : Récupérer des informations contextuelles stockées dans le token.

**Solution** : Utiliser `getTokenMetadata()`.

```php
$metadata = $helper->getTokenMetadata();

if ($metadata) {
    $ip = $metadata->get('ip');
    $userAgent = $metadata->get('user_agent');
    
    Log::info('Request from IP', ['ip' => $ip, 'user_agent' => $userAgent]);
}
```

## Flux d'exécution

```
appel de getCurrentToken()
    ↓
parent::getCurrentToken() (requête injectée par middleware)
    ↓
    ├── Token trouvé → retourne le token
    └── Token non trouvé → continue
    ↓
cookieStorage->get($this->request)
    ↓
    ├── Token non trouvé → retourne null
    └── Token trouvé → continue
    ↓
cookieStorage->getValidatedToken($this->request)
    ↓
    ├── Token invalide → retourne null
    └── Token valide → convertit en NemesisTokenRecord
    ↓
met en cache → retourne le token
```

## Gestion des erreurs

| Situation | Comportement |
|-----------|--------------|
| Token non présent | Retourne `null` |
| Token invalide | Retourne `null` |
| Token expiré | Retourne `null` |
| Utilisateur non trouvé | Retourne `null` |
| Modèle n'implémente pas `MustNemesis` | `getCurrentAuthenticatableFormat()` retourne `null` |

## Intégration

### Avec `CookieTokenStorageInterface`

Le helper utilise `CookieTokenStorage::get()` pour lire le token depuis le cookie.

### Avec `NemesisInterface`

Le helper utilise `NemesisService::find()` pour récupérer le record du token.

### Avec `MustNemesis`

Le helper vérifie que le modèle implémente `MustNemesis` avant d'appeler `nemesisFormat()`.

## Performance

- **Cache intégré** : Les données sont mises en cache après la première lecture
- **Lecture du cookie** : Une seule lecture par requête grâce au cache
- **Validation du token** : Une seule validation par requête
- **Complexité** : O(1) - constant

## Compatibilité

| Version PHP | Support |
|-------------|---------|
| PHP 8.1+ | ✅ Complet |
| PHP 8.0 | ✅ Complet |

| Version Laravel | Support |
|-----------------|---------|
| Laravel 12.x | ✅ Testé |
| Laravel 13.x | ✅ Testé |
| Laravel 14.x | ✅ Testé |
| Laravel 15.x | ✅ Testé |

## Exemple complet

```php
<?php

declare(strict_types=1);

use AndyDefer\Nemesis\Helpers\AutonomousNemesisHelper;
use AndyDefer\Nemesis\Contracts\Services\CookieTokenStorageInterface;
use AndyDefer\Nemesis\Contracts\Services\NemesisInterface;

// 1. Récupérer le helper
$helper = app(AutonomousNemesisHelper::class);

// 2. Vérifier l'authentification
if ($helper->isAuthenticated()) {
    // 3. Récupérer l'utilisateur
    $user = $helper->getCurrentAuthenticatable();
    $formatted = $helper->getCurrentAuthenticatableFormat();
    
    // 4. Récupérer le token
    $token = $helper->getCurrentToken();
    $tokenId = $helper->getTokenId();
    $tokenName = $helper->getTokenName();
    $abilities = $helper->getTokenAbilities();
    $metadata = $helper->getTokenMetadata();
    
    // 5. Vérifier les permissions
    if ($helper->tokenHasAbility('admin')) {
        echo "Admin access granted";
    }
    
    // 6. Vérifier l'expiration
    if ($helper->isTokenExpired()) {
        echo "Token is expired";
    }
    
    // 7. Nettoyer le cache
    $helper->clear();
}
```

## Voir aussi

- `NemesisHelper` - Helper standard (dépend du middleware)
- `CookieTokenStorageInterface` - Service de stockage des cookies
- `NemesisInterface` - Service de gestion des tokens
- `MustNemesis` - Interface des modèles authentifiables