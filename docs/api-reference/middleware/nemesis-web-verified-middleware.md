# NemesisWebVerifiedMiddleware - Référence Technique

## Description

Middleware web qui vérifie que l'utilisateur authentifié via cookie a validé son adresse email. Étend le `NemesisWebMiddleware` en ajoutant une couche de vérification d'email avec redirection.

## Hiérarchie / Implémentations

```
Middleware Laravel
    └── NemesisWebVerifiedMiddleware
```

## Rôle principal

Ce middleware s'assure que seuls les utilisateurs ayant vérifié leur email peuvent accéder aux routes web protégées. Il combine l'authentification par cookie avec une validation du champ `email_verified_at` du modèle. Si l'email n'est pas vérifié, l'utilisateur est redirigé vers une page de vérification.

## Installation

```bash
# Le middleware est automatiquement enregistré via le ServiceProvider
# Alias disponible : 'nemesis.web.verified'
```

## API / Méthodes publiques

### `handle(Request $request, Closure $next): Response`

| Paramètre | Type | Description |
|-----------|------|-------------|
| `$request` | `Request` | La requête HTTP entrante |
| `$next` | `Closure` | Le prochain middleware dans la pipeline |

**Retourne :** `Response` - La réponse HTTP (redirection ou passage au middleware suivant)

**Exceptions :** `abort(500)` si le modèle n'a pas la colonne `email_verified_at`

**Exemple :**
```php
// Dans les routes web
Route::middleware('nemesis.web.verified')->get('/dashboard', function () {
    return view('dashboard');
});
```

## Cas d'utilisation

### Cas 1 : Dashboard utilisateur

```php
// routes/web.php
Route::middleware('nemesis.web.verified')->group(function () {
    Route::get('/dashboard', [DashboardController::class, 'index']);
    Route::get('/profile', [ProfileController::class, 'edit']);
    Route::put('/profile', [ProfileController::class, 'update']);
});
```

### Cas 2 : Espace personnel

```php
// routes/web.php
Route::middleware('nemesis.web.verified')->prefix('account')->group(function () {
    Route::get('/', [AccountController::class, 'index'])->name('account');
    Route::get('/orders', [OrderController::class, 'index'])->name('orders');
    Route::get('/settings', [SettingsController::class, 'index'])->name('settings');
});
```

### Cas 3 : Routes admin avec redirection spécifique

```php
// config/nemesis.php
'web' => [
    'verification_route' => '/verify-email', // Redirection si email non vérifié
    'login_route' => '/login',               // Redirection si non authentifié
],

// routes/web.php
Route::middleware('nemesis.web.verified:admin')->group(function () {
    Route::get('/admin', [AdminController::class, 'dashboard']);
});
```

## Flux d'exécution

```
Requête entrante
    ↓
1. Récupération du token depuis le cookie (CookieTokenStorageInterface)
    ├── Token absent → Redirection vers login_route
    └── Token présent → Continue
    ↓
2. Ajout du token au header Authorization
    ↓
3. Authentification via NemesisAuthenticationInterface
    ├── Échec → Redirection vers login_route
    └── Succès → Continue
    ↓
4. Récupération du token model via NemesisService
    ↓
5. Validation du token
    ├── Token invalide ou expiré → Redirection vers login_route
    └── Token valide → Continue
    ↓
6. Récupération de l'utilisateur (tokenable)
    ├── Utilisateur introuvable → Redirection vers login_route
    └── Utilisateur trouvé → Continue
    ↓
7. Vérification de la colonne email_verified_at (Schema::hasColumn)
    ├── Colonne manquante → abort(500)
    └── Colonne présente → Continue
    ↓
8. Vérification de l'email (email_verified_at)
    ├── Email non vérifié → Redirection vers verification_route
    └── Email vérifié → Continue
    ↓
9. Injection de l'utilisateur et du token dans la requête
    ↓
10. Passage au middleware suivant
```

## Gestion des erreurs

| Situation | Action | Type |
|-----------|--------|------|
| Cookie absent | Redirection vers `login_route` | Redirection 302 |
| Token invalide | Redirection vers `login_route` | Redirection 302 |
| Token expiré | Redirection vers `login_route` | Redirection 302 |
| Utilisateur introuvable | Redirection vers `login_route` | Redirection 302 |
| Colonne `email_verified_at` manquante | `abort(500)` | Erreur serveur |
| Email non vérifié | Redirection vers `verification_route` | Redirection 302 |

## Intégration

### Avec `CookieTokenStorageService`

Le middleware utilise `CookieTokenStorageService::get()` pour récupérer le token du cookie.

### Avec `NemesisAuthenticationService`

Le middleware utilise `NemesisAuthenticationService::authenticate()` pour valider le token.

### Avec `NemesisService`

Le middleware utilise `NemesisService::findByHash()` pour récupérer le modèle du token.

### Avec `Schema` (Laravel)

Le middleware utilise `Schema::hasColumn()` pour vérifier la présence de la colonne `email_verified_at` dans la table du modèle.

### Avec `WebConfigRecord`

Le middleware utilise `webConfig()->login_route` et `webConfig()->verification_route` pour les redirections.

## Configuration requise

```php
// config/nemesis.php
'web' => [
    'login_route' => '/login',              // Redirection si non authentifié
    'dashboard_route' => '/dashboard',      // Redirection si déjà authentifié
    'verification_route' => '/verify-email', // Redirection si email non vérifié
    'cookie_name' => 'nemesis_token',
    'cookie_secure' => true,
    'cookie_httponly' => true,
    'cookie_samesite' => 'lax',
],
```

## Performance

- **Authentification :** Une requête SQL par appel pour récupérer le token
- **Vérification Schema :** `Schema::hasColumn()` est mise en cache par Laravel
- **Complexité :** O(1) - constant

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

// config/nemesis.php
return [
    'web' => [
        'login_route' => '/login',
        'dashboard_route' => '/dashboard',
        'verification_route' => '/verify-email',
        'cookie_name' => 'nemesis_token',
        'cookie_secure' => false, // En développement
        'cookie_httponly' => true,
        'cookie_samesite' => 'lax',
    ],
];

// routes/web.php
use AndyDefer\Nemesis\Http\Middleware\NemesisWebVerifiedMiddleware;

// Routes protégées par vérification email
Route::middleware('nemesis.web.verified')->group(function () {
    Route::get('/dashboard', function () {
        $user = request()->nemesis_auth; // ✅ Utilisateur injecté
        return view('dashboard', [
            'user' => $user,
            'userFormatted' => request()->nemesis_auth_format,
        ]);
    })->name('dashboard');

    Route::get('/profile', function () {
        $user = request()->nemesis_auth;
        return view('profile', ['user' => $user]);
    })->name('profile');
});

// Contrôleur utilisant l'utilisateur injecté
class DashboardController extends Controller
{
    public function index()
    {
        // L'utilisateur est disponible via la requête
        $user = request()->nemesis_auth;
        $token = request()->current_nemesis_token;
        $formatted = request()->nemesis_auth_format;

        return view('dashboard', [
            'user' => $formatted,
            'tokenId' => $token?->id,
            'lastUsed' => $token?->last_used_at,
        ]);
    }
}
```

## Voir aussi

- `NemesisWebMiddleware` - Middleware web de base
- `NemesisApiVerifiedMiddleware` - Version API (Bearer token) de ce middleware
- `CookieTokenStorageService` - Service de gestion des cookies
- `NemesisAuthenticationService` - Service d'authentification utilisé
- `NemesisService` - Service de gestion des tokens