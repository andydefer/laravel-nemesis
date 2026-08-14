# NemesisTokenMiddleware - Référence Technique

## Description

Middleware principal pour l'authentification des routes API via Bearer token. Extrait, valide le token et attache le modèle authentifié à la requête.

## Hiérarchie

```
Middleware
    └── NemesisTokenMiddleware
```

## Rôle principal

Protège les routes API en vérifiant la présence et la validité d'un token Bearer dans le header `Authorization`. En cas de succès, injecte le modèle authentifié et ses données formatées dans la requête pour une utilisation ultérieure.

## Installation

Le middleware est automatiquement enregistré via le ServiceProvider.

```php
// Routes API protégées
Route::middleware('nemesis.token')->group(function () {
    Route::get('/profile', [ProfileController::class, 'show']);
    Route::post('/update', [ProfileController::class, 'update']);
});

// Avec vérification d'ability
Route::post('/admin', [AdminController::class, 'action'])
    ->middleware('nemesis.token:admin');
```

## API

### `handle(Request $request, Closure $next, ?string $ability = null): mixed`

| Paramètre | Type | Description |
|-----------|------|-------------|
| `$request` | `Request` | La requête HTTP entrante |
| `$next` | `Closure` | Le prochain middleware dans le pipeline |
| `$ability` | `string|null` | Ability optionnelle requise pour le token |

**Retourne :** `mixed` - La réponse HTTP

**Exemple :**
```php
public function handle(Request $request, Closure $next, ?string $ability = null): mixed
{
    $result = $this->authService->authenticate($request, $ability);
    
    if (! $result->isSuccess()) {
        // Retourner une erreur
    }
    
    // Attacher l'utilisateur à la requête
    $request->merge([
        'nemesis_auth' => $authenticatable,
        'current_nemesis_token' => $tokenRecord,
    ]);
    
    return $next($request);
}
```

## Cas d'utilisation

### Cas 1 : Protéger un endpoint de profil

```php
Route::get('/api/profile', [ProfileController::class, 'show'])
    ->middleware('nemesis.token');
```

### Cas 2 : Protéger avec vérification d'ability

```php
Route::post('/api/admin/users', [AdminController::class, 'store'])
    ->middleware('nemesis.token:admin');
```

### Cas 3 : Grouper des routes protégées

```php
Route::middleware('nemesis.token')->group(function () {
    Route::get('/profile', [ProfileController::class, 'show']);
    Route::put('/profile', [ProfileController::class, 'update']);
    Route::delete('/profile', [ProfileController::class, 'destroy']);
});
```

### Cas 4 : Protection avec abilities multiples

```php
// Nécessite l'ability 'manage_users' ou 'admin'
Route::get('/api/users', [UserController::class, 'index'])
    ->middleware('nemesis.token:manage_users');
```

## Flux d'exécution

```
Requête entrante
    ↓
Extraire le Bearer Token du header Authorization
    ↓
Valider le token via NemesisAuthenticationService
    ├── Échec → Retourner une erreur (401/403/400)
    └── Succès → Poursuivre
        ↓
Vérifier l'ability (si spécifiée)
    ├── Non présente → Retourner 403
    └── Présente → Poursuivre
        ↓
Récupérer le modèle authentifiable
    ├── Non trouvé → Retourner 401
    └── Trouvé → Poursuivre
        ↓
Attacher le modèle et le token à la requête
    ├── $request->nemesis_auth = $authenticatable
    └── $request->current_nemesis_token = $tokenRecord
    ↓
Appliquer les headers de sécurité
    ↓
Appliquer les headers CORS
    ↓
Passer au middleware suivant
```

## Gestion des erreurs

| Situation | Code HTTP | Message | ErrorCode |
|-----------|-----------|---------|-----------|
| Token manquant | 401 | `Token not provided` | `MISSING_TOKEN` |
| Token invalide | 401 | `Invalid token` | `INVALID_TOKEN` |
| Token expiré | 401 | `Token has expired` | `TOKEN_EXPIRED` |
| Ability manquante | 403 | `Insufficient permissions` | `INSUFFICIENT_PERMISSIONS` |
| Origine non autorisée | 403 | `This origin is not allowed` | `ORIGIN_NOT_ALLOWED` |

**Format de la réponse d'erreur :**
```json
{
    "errorCode": "MISSING_TOKEN",
    "message": "Token not provided",
    "status": 401,
    "details": null
}
```

## Intégration

Ce middleware s'intègre avec :

- **NemesisAuthenticationInterface** : Pour l'authentification du token
- **NemesisConfigInterface** : Pour la configuration (parameter_name)
- **HttpHeaderInterface** : Pour l'application des headers (CORS, sécurité)
- **MustNemesis** : Interface pour le formatage des modèles
- **ResponseFactory** : Pour la génération des réponses JSON
- **ErrorResponseData** : Pour le format standardisé des erreurs

## Configuration

```php
// config/nemesis.php

'middleware' => [
    'parameter_name' => 'nemesis_auth', // Nom du paramètre dans la requête
    'token_header' => 'Authorization',  // Header contenant le token
    'security_headers' => true,         // Application des headers de sécurité
    'validate_origin' => true,          // Validation des origines CORS
],
```

## Performance

- Validation du token en O(1)
- Mise en cache automatique des résultats
- Application des headers avec impact minimal
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

private function registerApiMiddleware(Router $router): void
{
    $this->app->singleton(
        abstract: NemesisTokenMiddleware::class,
        concrete: function (Application $app): NemesisTokenMiddleware {
            return new NemesisTokenMiddleware(
                config: $app->make(NemesisConfigInterface::class),
                authService: $app->make(NemesisAuthenticationInterface::class),
                headerService: $app->make(HttpHeaderInterface::class)
            );
        }
    );

    $router->aliasMiddleware(
        name: 'nemesis.token',
        class: NemesisTokenMiddleware::class
    );
}
```

### Utilisation dans un contrôleur

```php
<?php

namespace App\Http\Controllers\Api;

use AndyDefer\Nemesis\Helpers\NemesisHelper;
use App\Http\Controllers\Controller;

class ProfileController extends Controller
{
    public function __construct(
        private readonly NemesisHelper $helper,
    ) {}

    public function show()
    {
        // Récupérer l'utilisateur authentifié via le helper
        $user = $this->helper->getCurrentAuthenticatable();
        $formatted = $this->helper->getCurrentAuthenticatableFormat();

        return response()->json([
            'user' => $formatted,
            'token' => [
                'id' => $this->helper->getTokenId(),
                'name' => $this->helper->getTokenName(),
                'source' => $this->helper->getTokenSource(),
            ],
        ]);
    }

    public function adminAction()
    {
        // Cette méthode est protégée par 'nemesis.token:admin'
        // L'utilisateur a déjà l'ability 'admin'
        return response()->json(['message' => 'Admin action performed']);
    }
}
```

### Utilisation dans les routes

```php
// routes/api.php

use App\Http\Controllers\Api\ProfileController;
use App\Http\Controllers\Api\AdminController;
use App\Http\Controllers\Api\PublicController;

// Routes publiques
Route::post('/login', [PublicController::class, 'login']);
Route::post('/register', [PublicController::class, 'register']);

// Routes protégées (authentification requise)
Route::middleware('nemesis.token')->group(function () {
    Route::get('/profile', [ProfileController::class, 'show']);
    Route::put('/profile', [ProfileController::class, 'update']);
    Route::delete('/profile', [ProfileController::class, 'destroy']);
});

// Routes avec permissions spécifiques
Route::middleware('nemesis.token:admin')->group(function () {
    Route::get('/admin/users', [AdminController::class, 'index']);
    Route::post('/admin/users', [AdminController::class, 'store']);
    Route::delete('/admin/users/{id}', [AdminController::class, 'destroy']);
});

// Route avec ability 'manage_posts'
Route::post('/posts', [PostController::class, 'store'])
    ->middleware('nemesis.token:manage_posts');
```

### Accès aux données dans les contrôleurs

```php
// Via le helper
$user = NemesisHelper::getCurrentAuthenticatable();
$formatted = NemesisHelper::getCurrentAuthenticatableFormat();

// Via la requête
$user = $request->nemesis_auth;
$token = $request->current_nemesis_token;

// Via l'injection de dépendance
public function show(NemesisHelper $helper)
{
    $user = $helper->getCurrentAuthenticatable();
}
```

## Voir aussi

- `NemesisApiGuestMiddleware` - Version pour les routes invitées (API)
- `NemesisWebMiddleware` - Version pour les routes web (cookie)
- `NemesisGuestMiddleware` - Version pour les routes invitées (web)
- `NemesisAuthenticationService` - Service d'authentification
- `NemesisHelper` - Helper pour accéder aux données
- `MustNemesis` - Interface pour les modèles authentifiables
- `ErrorResponseData` - Structure des réponses d'erreur
---