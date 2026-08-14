# CookieTokenStorageService - Référence Technique

## Description

Service de gestion des tokens d'authentification dans les cookies HTTP.

## Hiérarchie

```
CookieTokenStorageInterface
    └── CookieTokenStorageService
```

## Rôle principal

Fournit une abstraction pour stocker, récupérer, valider et supprimer des tokens d'authentification dans les cookies. Utilise le service Nemesis pour la validation des tokens et la configuration pour les paramètres de sécurité.

## Installation

Le service est automatiquement enregistré via le ServiceProvider.

```php
use AndyDefer\Nemesis\Contracts\Services\CookieTokenStorageInterface;

$storage = app(CookieTokenStorageInterface::class);
```

## API

### `store(string $plainToken): void`

| Paramètre | Type | Description |
|-----------|------|-------------|
| `$plainToken` | `string` | Le token en clair à stocker |

**Retourne :** `void`

**Exemple :**
```php
$storage->store('eyJhbGciOiJIUzI1NiIs...');
```

### `get(Request $request): ?string`

| Paramètre | Type | Description |
|-----------|------|-------------|
| `$request` | `Request` | La requête HTTP |

**Retourne :** `?string` - Le token en clair ou null

**Exemple :**
```php
$token = $storage->get($request);
if ($token) {
    // Token trouvé
}
```

### `has(Request $request): bool`

| Paramètre | Type | Description |
|-----------|------|-------------|
| `$request` | `Request` | La requête HTTP |

**Retourne :** `bool` - True si le cookie existe, false sinon

**Exemple :**
```php
if ($storage->has($request)) {
    // Le cookie existe
}
```

### `forget(): void`

| Paramètre | Type | Description |
|-----------|------|-------------|
| Aucun | - | - |

**Retourne :** `void`

**Exemple :**
```php
$storage->forget(); // Supprime le cookie
```

### `getValidatedToken(Request $request): ?NemesisToken`

| Paramètre | Type | Description |
|-----------|------|-------------|
| `$request` | `Request` | La requête HTTP |

**Retourne :** `?NemesisToken` - Le token validé ou null

**Exemple :**
```php
$token = $storage->getValidatedToken($request);
if ($token && $token->isValid()) {
    // Token valide
}
```

### `getAuthenticatable(Request $request): ?Model`

| Paramètre | Type | Description |
|-----------|------|-------------|
| `$request` | `Request` | La requête HTTP |

**Retourne :** `?Model` - Le modèle authentifié ou null

**Exemple :**
```php
$user = $storage->getAuthenticatable($request);
if ($user) {
    echo $user->name;
}
```

### `isValid(Request $request): bool`

| Paramètre | Type | Description |
|-----------|------|-------------|
| `$request` | `Request` | La requête HTTP |

**Retourne :** `bool` - True si un token valide existe, false sinon

**Exemple :**
```php
if ($storage->isValid($request)) {
    // L'utilisateur est authentifié
}
```

## Cas d'utilisation

### Cas 1 : Stocker un token après connexion

```php
class LoginAction extends AbstractAction
{
    public function __construct(
        private readonly CookieTokenStorageInterface $storage,
        private readonly NemesisService $nemesisService,
    ) {}

    protected function handle(AbstractRecord $request): ResponseFactory
    {
        $user = User::where('email', $request->email)->first();
        
        // Créer le token
        $record = NemesisTokenRecord::from([
            'name' => 'Web Session',
            'source' => 'web',
        ]);
        
        [$token, $plainToken] = $this->nemesisService->createWithPlainToken($record, $user);
        
        // Stocker dans le cookie
        $this->storage->store($plainToken);
        
        return ResponseFactory::json(['success' => true]);
    }
}
```

### Cas 2 : Vérifier l'authentification dans un middleware

```php
class AuthMiddleware
{
    public function __construct(
        private readonly CookieTokenStorageInterface $storage,
    ) {}

    public function handle(Request $request, Closure $next)
    {
        if (!$this->storage->isValid($request)) {
            return redirect()->route('login');
        }
        
        return $next($request);
    }
}
```

### Cas 3 : Récupérer l'utilisateur authentifié

```php
class ProfileController
{
    public function __construct(
        private readonly CookieTokenStorageInterface $storage,
    ) {}

    public function show(Request $request)
    {
        $user = $this->storage->getAuthenticatable($request);
        
        if (!$user) {
            return response()->json(['error' => 'Unauthorized'], 401);
        }
        
        return response()->json(['user' => $user]);
    }
}
```

### Cas 4 : Déconnexion

```php
class LogoutAction extends AbstractAction
{
    public function __construct(
        private readonly CookieTokenStorageInterface $storage,
        private readonly NemesisService $nemesisService,
    ) {}

    protected function handle(AbstractRecord $request): ResponseFactory
    {
        $request = request();
        
        // Révoquer le token
        $token = $this->storage->getValidatedToken($request);
        if ($token) {
            $this->nemesisService->revoke($token);
        }
        
        // Supprimer le cookie
        $this->storage->forget();
        
        return ResponseFactory::redirectRoute('login');
    }
}
```

## Flux d'exécution

### Stockage
```
store($plainToken)
    ↓
Récupérer la configuration web
    ↓
Créer un cookie via Cookie::queue()
    ├── Nom: config('nemesis.web.cookie_name')
    ├── Valeur: $plainToken
    ├── Expiration: 0 (pas d'expiration)
    ├── Path: /
    ├── Secure: config('nemesis.web.cookie_secure')
    ├── HttpOnly: config('nemesis.web.cookie_httponly')
    └── SameSite: config('nemesis.web.cookie_samesite')
```

### Validation
```
getValidatedToken($request)
    ↓
Récupérer le token du cookie
    ├── Non → null
    └── Oui → Poursuivre
        ↓
Hasher le token (sha256)
    ↓
Rechercher le token dans la base
    ├── Non → null
    └── Oui → Poursuivre
        ↓
Vérifier la validité du token (isValid())
    ├── False → null
    └── True → Retourner le token
```

## Gestion des erreurs

| Situation | Exception | Message |
|-----------|-----------|---------|
| Token non trouvé | - | Retourne null |
| Token invalide | - | Retourne null |
| Token expiré | - | Retourne null |

*Le service ne lève pas d'exceptions. Il retourne null ou false en cas d'absence de token valide.*

## Intégration

Ce service s'intègre avec :

- **NemesisInterface** : Pour la validation des tokens
- **NemesisConfigInterface** : Pour la configuration du cookie
- **NemesisToken** : Modèle de token
- **Request** : Pour la lecture des cookies
- **Cookie** : Facade Laravel pour la gestion des cookies

## Configuration

```php
// config/nemesis.php

'web' => [
    'cookie_name' => 'nemesis_token',   // Nom du cookie
    'cookie_secure' => true,            // HTTPS uniquement
    'cookie_httponly' => true,          // Inaccessible en JS
    'cookie_samesite' => 'lax',         // Protection CSRF
],
```

## Performance

- Lecture simple du cookie via `$request->cookie()`
- Validation en base de données via `findByHash()`
- Hash du token en SHA-256
- Complexité : O(1) pour la lecture, O(log n) pour la validation

## Compatibilité

| Version PHP | Support |
|-------------|---------|
| PHP 8.1+ | ✅ Complet |
| PHP 8.0 | ✅ Complet |

## Exemple complet

```php
<?php

declare(strict_types=1);

use AndyDefer\Nemesis\Contracts\Services\CookieTokenStorageInterface;
use AndyDefer\Nemesis\Contracts\Services\NemesisInterface;
use AndyDefer\Nemesis\Records\NemesisTokenRecord;

class AuthenticationService
{
    public function __construct(
        private readonly CookieTokenStorageInterface $cookieStorage,
        private readonly NemesisInterface $nemesisService,
    ) {}

    public function authenticate(string $email, string $password): array
    {
        $user = User::where('email', $email)->first();
        
        if (!$user || !Hash::check($password, $user->password)) {
            return ['success' => false, 'message' => 'Invalid credentials'];
        }
        
        // Créer le token
        $record = NemesisTokenRecord::from([
            'name' => 'Web Session',
            'source' => 'web',
            'abilities' => ['*'],
        ]);
        
        [$token, $plainToken] = $this->nemesisService->createWithPlainToken($record, $user);
        
        // Stocker dans le cookie
        $this->cookieStorage->store($plainToken);
        
        return [
            'success' => true,
            'user' => $user,
            'token' => $plainToken,
        ];
    }

    public function getCurrentUser(Request $request): ?User
    {
        return $this->cookieStorage->getAuthenticatable($request);
    }

    public function isAuthenticated(Request $request): bool
    {
        return $this->cookieStorage->isValid($request);
    }

    public function logout(Request $request): void
    {
        $token = $this->cookieStorage->getValidatedToken($request);
        
        if ($token) {
            $this->nemesisService->revoke($token);
        }
        
        $this->cookieStorage->forget();
    }
}

// Utilisation
$auth = app(AuthenticationService::class);

// Connexion
$result = $auth->authenticate('john@example.com', 'password123');

// Vérification
if ($auth->isAuthenticated(request())) {
    $user = $auth->getCurrentUser(request());
    echo "Bonjour {$user->name}";
}

// Déconnexion
$auth->logout(request());
```

## Voir aussi

- `NemesisService` - Service principal de gestion des tokens
- `NemesisConfigInterface` - Interface de configuration
- `CookieTokenStorageInterface` - Interface du service
- `NemesisWebMiddleware` - Middleware utilisant ce service
- `NemesisToken` - Modèle de token
- `NemesisHelper` - Helper pour l'accès aux données
---