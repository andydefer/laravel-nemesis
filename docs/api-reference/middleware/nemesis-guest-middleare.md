# NemesisGuestMiddleware - Référence Technique

## Description

Middleware pour les routes web réservées aux invités. Redirige les utilisateurs authentifiés vers le tableau de bord.

## Hiérarchie

```
Middleware
    └── NemesisGuestMiddleware
```

## Rôle principal

Empêche les utilisateurs authentifiés d'accéder à des pages web qui devraient être réservées aux invités (login, register, etc.). Si un token valide est présent dans le cookie, le middleware redirige vers le tableau de bord configuré.

## Installation

Le middleware est automatiquement enregistré via le ServiceProvider.

```php
// Routes web
Route::get('/login', function () {
    return inertia('Auth/LoginScreen');
})->middleware('nemesis.guest');

// Avec vérification d'ability
Route::get('/register', function () {
    return inertia('Auth/RegisterScreen');
})->middleware('nemesis.guest:admin');
```

## API

### `handle(Request $request, Closure $next, ?string $ability = null): Response`

| Paramètre | Type | Description |
|-----------|------|-------------|
| `$request` | `Request` | La requête HTTP entrante |
| `$next` | `Closure` | Le prochain middleware dans le pipeline |
| `$ability` | `string|null` | Ability optionnelle à vérifier (si l'utilisateur la possède, il est redirigé) |

**Retourne :** `Response` - La réponse HTTP (redirection ou passage)

**Exceptions :** Aucune

**Exemple :**
```php
public function handle(Request $request, Closure $next, ?string $ability = null): Response
{
    $plainToken = $this->cookieTokenStorage->get($request);
    
    if ($plainToken === null) {
        return $next($request);
    }
    
    // ... vérification et redirection
}
```

## Cas d'utilisation

### Cas 1 : Protéger la page de login

```php
Route::get('/login', function () {
    return inertia('Auth/LoginScreen');
})->middleware('nemesis.guest')->name('login.show');
```

### Cas 2 : Protéger la page d'inscription

```php
Route::get('/register', function () {
    return inertia('Auth/RegisterScreen');
})->middleware('nemesis.guest')->name('register.show');
```

### Cas 3 : Bloquer les utilisateurs avec une ability spécifique

```php
// Redirige les admins vers le dashboard
Route::get('/user-only-page', function () {
    return inertia('UserOnlyPage');
})->middleware('nemesis.guest:admin');
```

### Cas 4 : Protéger la page de réinitialisation de mot de passe

```php
Route::get('/forgot-password', function () {
    return inertia('Auth/ForgotPasswordScreen');
})->middleware('nemesis.guest')->name('password.request');
```

## Flux d'exécution

```
Requête entrante
    ↓
Vérifier la présence du token dans le cookie
    ├── Non → Passer au middleware suivant
    └── Oui → Poursuivre
        ↓
Ajouter le token dans le header Authorization
    ↓
Authentifier le token
    ├── Échec → Passer au middleware suivant
    └── Succès → Poursuivre
        ↓
Ability spécifiée ?
    ├── Non → Rediriger vers le dashboard
    └── Oui → Vérifier l'ability
        ├── Possède l'ability → Rediriger vers le dashboard
        └── Ne possède pas l'ability → Passer au middleware suivant
```

## Gestion des erreurs

| Situation | Action | Destination |
|-----------|--------|-------------|
| Token valide présent | Redirection | `dashboard_route` (config) |
| Token valide avec ability interdite | Redirection | `dashboard_route` (config) |
| Token invalide | Passage | Page demandée |
| Pas de token | Passage | Page demandée |

## Intégration

Ce middleware s'intègre avec :

- **CookieTokenStorageInterface** : Pour la récupération du token dans le cookie
- **NemesisAuthenticationInterface** : Pour l'authentification du token
- **NemesisConfigInterface** : Pour la configuration (dashboard_route, token_header)
- **NemesisInterface** : Pour les opérations sur les tokens

## Configuration

```php
// config/nemesis.php

'web' => [
    'login_route' => '/login',
    'dashboard_route' => '/dashboard', // Route de redirection
    'cookie_name' => 'nemesis_token',
    'cookie_secure' => true,
    'cookie_httponly' => true,
    'cookie_samesite' => 'lax',
],
```

## Performance

- Lecture simple du cookie
- Un appel à `authenticate()` pour valider le token
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

private function registerGuestMiddleware(Router $router): void
{
    $this->app->singleton(
        abstract: NemesisGuestMiddleware::class,
        concrete: function (Application $app): NemesisGuestMiddleware {
            return new NemesisGuestMiddleware(
                cookieTokenStorage: $app->make(CookieTokenStorageInterface::class),
                authService: $app->make(NemesisAuthenticationInterface::class),
                config: $app->make(NemesisConfigInterface::class)
            );
        }
    );

    $router->aliasMiddleware(
        name: 'nemesis.guest',
        class: NemesisGuestMiddleware::class
    );
}
```

### Utilisation dans les routes

```php
// routes/web.php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\PasswordController;
use App\Http\Controllers\UserController;
use App\Actions\Web\Authentication\LoginAction;
use App\Actions\Web\Authentication\RegisterAction;

// Routes publiques (réservées aux invités)
Route::get('/login', action_route(EmptyRequest::class, LoginAction::class))
    ->middleware('nemesis.guest')
    ->name('login.show');

Route::get('/register', action_route(EmptyRequest::class, RegisterAction::class))
    ->middleware('nemesis.guest')
    ->name('register.show');

Route::get('/forgot-password', function () {
    return inertia('Auth/ForgotPasswordScreen');
})->middleware('nemesis.guest')->name('password.request');

// Route qui redirige les admins
Route::get('/user-only', function () {
    return inertia('UserOnlyPage');
})->middleware('nemesis.guest:admin')->name('user.only');

// Routes protégées (nécessitent une authentification)
Route::middleware('nemesis.web')->group(function () {
    Route::get('/dashboard', function () {
        return inertia('DashboardScreen');
    })->name('dashboard');
});
```

### Exemple de flux utilisateur

**Scénario 1 : Utilisateur non authentifié**
```
GET /login
    ↓
Cookie: aucun token
    ↓
→ Passer le middleware
    ↓
Page de login affichée
```

**Scénario 2 : Utilisateur authentifié**
```
GET /login
    ↓
Cookie: token valide
    ↓
→ Redirection vers /dashboard
    ↓
Page dashboard affichée
```

**Scénario 3 : Utilisateur authentifié sans ability**
```
GET /user-only (middleware: nemesis.guest:admin)
    ↓
Cookie: token valide (sans ability admin)
    ↓
→ Vérification: token ne possède pas admin
    ↓
→ Passer le middleware
    ↓
Page user-only affichée
```

**Scénario 4 : Utilisateur authentifié avec ability**
```
GET /user-only (middleware: nemesis.guest:admin)
    ↓
Cookie: token valide (avec ability admin)
    ↓
→ Vérification: token possède admin
    ↓
→ Redirection vers /dashboard
    ↓
Page dashboard affichée
```

## Voir aussi

- `NemesisApiGuestMiddleware` - Version API (retourne 400)
- `NemesisWebMiddleware` - Protection des routes web
- `NemesisTokenMiddleware` - Protection des routes API
- `NemesisHelper` - Helper pour accéder aux données du token
- `CookieTokenStorageInterface` - Interface de stockage des cookies
- `NemesisConfigInterface` - Interface de configuration
---