# NemesisWebMiddleware - Référence Technique

## Description

Middleware pour la protection des routes web via authentification par token stocké dans un cookie.

## Hiérarchie

```
Middleware
    └── NemesisWebMiddleware
```

## Rôle principal

Protège les routes web en vérifiant la présence et la validité d'un token dans le cookie. Si le token est invalide ou absent, l'utilisateur est redirigé vers la page de login configurée. Supporte également la vérification d'abilities.

## Installation

Le middleware est automatiquement enregistré via le ServiceProvider.

```php
// Routes web protégées
Route::middleware('nemesis.web')->group(function () {
    Route::get('/dashboard', function () {
        return inertia('DashboardScreen');
    });
    Route::get('/profile', function () {
        return inertia('ProfileScreen');
    });
});

// Avec vérification d'ability
Route::get('/admin', function () {
    return inertia('AdminScreen');
})->middleware('nemesis.web:admin');
```

## API

### `handle(Request $request, Closure $next, ?string $ability = null): Response`

| Paramètre | Type | Description |
|-----------|------|-------------|
| `$request` | `Request` | La requête HTTP entrante |
| `$next` | `Closure` | Le prochain middleware dans le pipeline |
| `$ability` | `string|null` | Ability optionnelle requise pour le token |

**Retourne :** `Response` - La réponse HTTP (redirection, passage ou abort)

**Exemple :**
```php
public function handle(Request $request, Closure $next, ?string $ability = null): Response
{
    $plainToken = $this->cookieTokenStorage->get($request);
    
    if ($plainToken === null) {
        return redirect()->to($this->config->webConfig()->login_route);
    }
    
    // ... vérification et passage
}
```

## Cas d'utilisation

### Cas 1 : Protéger le tableau de bord

```php
Route::get('/dashboard', function () {
    return inertia('DashboardScreen');
})->middleware('nemesis.web')->name('dashboard');
```

### Cas 2 : Protéger le profil utilisateur

```php
Route::get('/profile', function () {
    return inertia('ProfileScreen');
})->middleware('nemesis.web')->name('profile');
```

### Cas 3 : Protection avec ability

```php
Route::get('/admin/dashboard', function () {
    return inertia('AdminDashboardScreen');
})->middleware('nemesis.web:admin')->name('admin.dashboard');
```

### Cas 4 : Grouper des routes protégées

```php
Route::middleware('nemesis.web')->group(function () {
    Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');
    Route::get('/profile', [ProfileController::class, 'show'])->name('profile');
    Route::get('/settings', [SettingsController::class, 'index'])->name('settings');
});

// Routes admin avec ability
Route::middleware('nemesis.web:admin')->group(function () {
    Route::get('/admin/users', [AdminController::class, 'index']);
    Route::get('/admin/settings', [AdminController::class, 'settings']);
});
```

## Flux d'exécution

```
Requête entrante
    ↓
Vérifier la présence du token dans le cookie
    ├── Non → Rediriger vers login
    └── Oui → Poursuivre
        ↓
Ajouter le token dans le header Authorization
    ↓
Authentifier le token via NemesisAuthenticationService
    ├── Échec → 
    │   ├── INSUFFICIENT_PERMISSIONS → 403 Forbidden
    │   └── Autre erreur → Rediriger vers login
    └── Succès → Poursuivre
        ↓
Ability spécifiée ?
    ├── Non → Passer au middleware suivant
    └── Oui → Vérifier l'ability
        ├── Possède l'ability → Passer au middleware suivant
        └── Ne possède pas l'ability → 403 Forbidden
```

## Gestion des erreurs

| Situation | Action | Code/Status |
|-----------|--------|-------------|
| Token absent | Redirection | 302 → login_route |
| Token invalide | Redirection | 302 → login_route |
| Token expiré | Redirection | 302 → login_route |
| Token révoqué | Redirection | 302 → login_route |
| Ability manquante | Abort | 403 Forbidden |
| Erreur d'authentification | Redirection | 302 → login_route |

## Intégration

Ce middleware s'intègre avec :

- **CookieTokenStorageInterface** : Pour la récupération du token dans le cookie
- **NemesisAuthenticationInterface** : Pour l'authentification du token
- **NemesisConfigInterface** : Pour la configuration (login_route, token_header)
- **ErrorCode** : Énumération des codes d'erreur

## Configuration

```php
// config/nemesis.php

'web' => [
    'login_route' => '/login',        // Route de redirection en cas d'échec
    'dashboard_route' => '/dashboard',
    'cookie_name' => 'nemesis_token',
    'cookie_secure' => true,
    'cookie_httponly' => true,
    'cookie_samesite' => 'lax',
],

'middleware' => [
    'token_header' => 'Authorization', // Header utilisé pour le token
    'security_headers' => true,
    'validate_origin' => true,
],
```

## Performance

- Lecture simple du cookie
- Un appel à `authenticate()` pour valider le token
- Vérification d'ability en O(1)
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

private function registerWebMiddleware(Router $router): void
{
    $this->app->singleton(
        abstract: NemesisWebMiddleware::class,
        concrete: function (Application $app): NemesisWebMiddleware {
            return new NemesisWebMiddleware(
                cookieTokenStorage: $app->make(CookieTokenStorageInterface::class),
                config: $app->make(NemesisConfigInterface::class),
                authService: $app->make(NemesisAuthenticationInterface::class)
            );
        }
    );

    $router->aliasMiddleware(
        name: 'nemesis.web',
        class: NemesisWebMiddleware::class
    );
}
```

### Utilisation dans les routes

```php
// routes/web.php

use App\Actions\Web\DashboardAction;
use App\Actions\Web\ProfileAction;
use App\Actions\Web\AdminAction;
use App\Actions\Web\Authentication\LoginAction;
use App\Actions\Web\Authentication\RegisterAction;

// Routes publiques (invités)
Route::get('/login', action_route(EmptyRequest::class, LoginAction::class))
    ->middleware('nemesis.guest')
    ->name('login.show');

Route::get('/register', action_route(EmptyRequest::class, RegisterAction::class))
    ->middleware('nemesis.guest')
    ->name('register.show');

// Routes protégées (authentification requise)
Route::middleware('nemesis.web')->group(function () {
    Route::get('/dashboard', action_route(EmptyRequest::class, DashboardAction::class))
        ->name('dashboard');
    
    Route::get('/profile', action_route(EmptyRequest::class, ProfileAction::class))
        ->name('profile');
});

// Routes admin (authentification + ability admin)
Route::middleware('nemesis.web:admin')->group(function () {
    Route::get('/admin/dashboard', action_route(EmptyRequest::class, AdminAction::class))
        ->name('admin.dashboard');
    
    Route::get('/admin/users', [AdminController::class, 'index'])
        ->name('admin.users');
});
```

### Utilisation dans une Action

```php
<?php

declare(strict_types=1);

namespace App\Actions\Web;

use AndyDefer\Actions\Actions\AbstractAction;
use AndyDefer\Actions\Http\ResponseFactory;
use AndyDefer\DomainStructures\Abstracts\AbstractRecord;
use AndyDefer\Nemesis\Helpers\NemesisHelper;

final class DashboardAction extends AbstractAction
{
    public function __construct(
        private readonly NemesisHelper $helper,
    ) {}

    protected function handle(AbstractRecord $request): ResponseFactory
    {
        // L'utilisateur est déjà authentifié via le middleware
        $user = $this->helper->getCurrentAuthenticatable();
        $formatted = $this->helper->getCurrentAuthenticatableFormat();

        return ResponseFactory::inertia('DashboardScreen', [
            'user' => $formatted,
        ]);
    }
}
```

### Exemple de flux utilisateur

**Scénario 1 : Utilisateur non authentifié**
```
GET /dashboard
    ↓
Cookie: aucun token
    ↓
→ Redirection vers /login
    ↓
Page de login affichée
```

**Scénario 2 : Utilisateur authentifié**
```
GET /dashboard
    ↓
Cookie: token valide
    ↓
→ Authentification réussie
    ↓
Page dashboard affichée
```

**Scénario 3 : Utilisateur authentifié sans ability**
```
GET /admin/dashboard
    ↓
Cookie: token valide (sans ability admin)
    ↓
→ Authentification réussie
    ↓
→ Vérification ability: Échec
    ↓
→ 403 Forbidden
```

**Scénario 4 : Utilisateur authentifié avec ability**
```
GET /admin/dashboard
    ↓
Cookie: token valide (avec ability admin)
    ↓
→ Authentification réussie
    ↓
→ Vérification ability: Succès
    ↓
Page admin dashboard affichée
```

## Voir aussi

- `NemesisTokenMiddleware` - Version API (Bearer token)
- `NemesisGuestMiddleware` - Version invité (web)
- `NemesisApiGuestMiddleware` - Version invité (API)
- `CookieTokenStorageInterface` - Interface de stockage des cookies
- `NemesisAuthenticationService` - Service d'authentification
- `NemesisHelper` - Helper pour accéder aux données
- `ErrorCode` - Énumération des codes d'erreur
---