# NemesisApiGuestMiddleware - Référence Technique

## Description

Middleware pour les routes API réservées aux invités. Bloque les requêtes des utilisateurs authentifiés et retourne une erreur 400.

## Hiérarchie

```
Middleware
    └── NemesisApiGuestMiddleware
```

## Rôle principal

Empêche les utilisateurs authentifiés d'accéder à des endpoints API qui devraient être réservés aux invités (login, register, etc.). Si un token valide est présent dans le header `Authorization`, le middleware bloque la requête avec un code 400.

## Installation

Le middleware est automatiquement enregistré via le ServiceProvider.

```php
// Routes API
Route::post('/login', function () {
    // ...
})->middleware('nemesis.api.guest');

// Avec vérification d'ability
Route::post('/register', function () {
    // ...
})->middleware('nemesis.api.guest:admin');
```

## API

### `handle(Request $request, Closure $next, ?string $ability = null): Response`

| Paramètre | Type | Description |
|-----------|------|-------------|
| `$request` | `Request` | La requête HTTP entrante |
| `$next` | `Closure` | Le prochain middleware dans le pipeline |
| `$ability` | `string|null` | Ability optionnelle à vérifier (si l'utilisateur la possède, il est bloqué) |

**Retourne :** `Response` - La réponse HTTP

**Exceptions :** Aucune

**Exemple :**
```php
// Dans le middleware
public function handle(Request $request, Closure $next, ?string $ability = null): Response
{
    $bearerToken = $request->bearerToken();
    
    if ($bearerToken === null) {
        return $next($request);
    }
    
    // ... vérification et blocage
}
```

## Cas d'utilisation

### Cas 1 : Protéger un endpoint de login

```php
Route::post('/api/login', [AuthController::class, 'login'])
    ->middleware('nemesis.api.guest');
```

### Cas 2 : Protéger un endpoint de registration

```php
Route::post('/api/register', [AuthController::class, 'register'])
    ->middleware('nemesis.api.guest');
```

### Cas 3 : Bloquer les utilisateurs avec une ability spécifique

```php
// Bloque les utilisateurs qui ont l'ability 'admin'
Route::post('/api/user-only', [UserController::class, 'store'])
    ->middleware('nemesis.api.guest:admin');
```

### Cas 4 : Protéger un endpoint de réinitialisation de mot de passe

```php
Route::post('/api/forgot-password', [PasswordController::class, 'forgot'])
    ->middleware('nemesis.api.guest');
```

## Flux d'exécution

```
Requête entrante
    ↓
Vérifier la présence du Bearer Token
    ├── Non → Passer au middleware suivant
    └── Oui → Poursuivre
        ↓
Authentifier le token
    ├── Échec → Passer au middleware suivant
    └── Succès → Poursuivre
        ↓
Ability spécifiée ?
    ├── Non → Bloquer avec 400
    └── Oui → Vérifier l'ability
        ├── Possède l'ability → Bloquer avec 400
        └── Ne possède pas l'ability → Passer au middleware suivant
```

## Gestion des erreurs

| Situation | Code HTTP | Message | ErrorCode |
|-----------|-----------|---------|-----------|
| Token valide présent | 400 | `Already authenticated` | `ALREADY_AUTHENTICATED` |
| Token valide avec ability interdite | 400 | `Already authenticated` | `ALREADY_AUTHENTICATED` |

**Format de la réponse d'erreur :**
```json
{
    "errorCode": "ALREADY_AUTHENTICATED",
    "message": "Already authenticated",
    "status": 400,
    "details": null
}
```

## Intégration

Ce middleware s'intègre avec :

- **NemesisAuthenticationInterface** : Pour l'authentification du token
- **NemesisConfigInterface** : Pour la configuration du système
- **NemesisInterface** : Pour les opérations sur les tokens
- **ResponseFactory** : Pour la génération des réponses JSON
- **ErrorResponseData** : Pour le format standardisé des erreurs

## Performance

- Vérification légère : un appel à `authenticate()`
- Blocage rapide des requêtes authentifiées
- Complexité : O(1)

## Compatibilité

| Version PHP | Support |
|-------------|---------|
| PHP 8.1+ | ✅ Complet |
| PHP 8.0 | ✅ Complet |

## Exemple complet

### Enregistrement dans le ServiceProvider

```php
// src/NemesisServiceProvider.php

private function registerApiGuestMiddleware(Router $router): void
{
    $this->app->singleton(
        abstract: NemesisApiGuestMiddleware::class,
        concrete: function (Application $app): NemesisApiGuestMiddleware {
            return new NemesisApiGuestMiddleware(
                authService: $app->make(NemesisAuthenticationInterface::class),
                config: $app->make(NemesisConfigInterface::class)
            );
        }
    );

    $router->aliasMiddleware(
        name: 'nemesis.api.guest',
        class: NemesisApiGuestMiddleware::class
    );
}
```

### Utilisation dans les routes

```php
// routes/api.php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\PasswordController;
use App\Http\Controllers\UserController;

// Routes publiques (réservées aux invités)
Route::post('/login', [AuthController::class, 'login'])
    ->middleware('nemesis.api.guest')
    ->name('api.login');

Route::post('/register', [AuthController::class, 'register'])
    ->middleware('nemesis.api.guest')
    ->name('api.register');

Route::post('/forgot-password', [PasswordController::class, 'forgot'])
    ->middleware('nemesis.api.guest')
    ->name('api.forgot-password');

// Route qui bloque les admins
Route::post('/user-only', [UserController::class, 'store'])
    ->middleware('nemesis.api.guest:admin')
    ->name('api.user-only');
```

### Réponse en cas de blocage

```json
{
    "errorCode": "ALREADY_AUTHENTICATED",
    "message": "Already authenticated",
    "status": 400,
    "details": null
}
```

### Réponse en cas de succès (utilisateur invité)

```json
{
    "message": "Login successful",
    "token": "eyJhbGciOiJIUzI1NiIs..."
}
```

## Voir aussi

- `NemesisGuestMiddleware` - Version web (redirection)
- `NemesisTokenMiddleware` - Protection des routes API
- `NemesisWebMiddleware` - Protection des routes web
- `ErrorCode` - Énumération des codes d'erreur
- `ErrorResponseData` - Structure des réponses d'erreur
- `ResponseFactory` - Factory pour les réponses HTTP
---