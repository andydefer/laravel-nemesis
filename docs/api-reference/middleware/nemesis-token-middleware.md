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

// Mode optionnel - n'échoue pas si le token est absent/invalide
Route::get('/profile', [ProfileController::class, 'show'])
    ->middleware('nemesis.token:optional');
```

## API

### `handle(Request $request, Closure $next, ?string $ability = null): mixed`

| Paramètre | Type | Description |
|-----------|------|-------------|
| `$request` | `Request` | La requête HTTP entrante |
| `$next` | `Closure` | Le prochain middleware dans le pipeline |
| `$ability` | `string|null` | Ability optionnelle requise pour le token. Valeur spéciale `optional` pour désactiver l'échec |

**Retourne :** `mixed` - La réponse HTTP

**Exemple :**
```php
public function handle(Request $request, Closure $next, ?string $ability = null): mixed
{
    $isOptional = $ability === 'optional';
    
    $result = $this->authService->authenticate($request, $isOptional ? null : $ability);
    
    if (! $result->isSuccess()) {
        if ($isOptional) {
            // Continuer sans authentification
            return $this->proceedWithoutAuth($request, $next);
        }
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

### Cas 3 : Mode optionnel (authentification facultative)

```php
Route::get('/api/search', [SearchController::class, 'index'])
    ->middleware('nemesis.token:optional');
```

Dans ce mode, si un token est fourni et valide, l'utilisateur est authentifié. Sinon, la requête continue avec `null` comme utilisateur.

### Cas 4 : Grouper des routes protégées

```php
Route::middleware('nemesis.token')->group(function () {
    Route::get('/profile', [ProfileController::class, 'show']);
    Route::put('/profile', [ProfileController::class, 'update']);
    Route::delete('/profile', [ProfileController::class, 'destroy']);
});
```

### Cas 5 : Protection avec abilities multiples

```php
// Nécessite l'ability 'manage_users' ou 'admin'
Route::get('/api/users', [UserController::class, 'index'])
    ->middleware('nemesis.token:manage_users');
```

### Cas 6 : Routes avec authentification optionnelle

```php
// L'utilisateur peut être authentifié ou non
Route::get('/api/feed', [FeedController::class, 'index'])
    ->middleware('nemesis.token:optional');

// Dans le contrôleur
public function index(Request $request)
{
    $user = $request->input('nemesis_auth');
    
    if ($user) {
        // Contenu personnalisé pour l'utilisateur connecté
        return $this->personalizedFeed($user);
    }
    
    // Contenu public
    return $this->publicFeed();
}
```

## Flux d'exécution

```
Requête entrante
    ↓
Vérifier si mode optional
    ↓
Extraire le Bearer Token du header Authorization
    ↓
Valider le token via NemesisAuthenticationService
    ├── Échec → Si mode optional → continuer sans auth
    │               Sinon → Retourner une erreur (401/403/400)
    └── Succès → Poursuivre
        ↓
Vérifier l'ability (si spécifiée et non optional)
    ├── Non présente → Retourner 403
    └── Présente → Poursuivre
        ↓
Récupérer le modèle authentifiable
    ├── Non trouvé → Si mode optional → continuer sans auth
    │               Sinon → Retourner 401
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

### Mode normal (sans optional)

| Situation | Code HTTP | Message | ErrorCode |
|-----------|-----------|---------|-----------|
| Token manquant | 401 | `Token not provided` | `MISSING_TOKEN` |
| Token invalide | 401 | `Invalid token` | `INVALID_TOKEN` |
| Token expiré | 401 | `Token has expired` | `TOKEN_EXPIRED` |
| Ability manquante | 403 | `Insufficient permissions` | `INSUFFICIENT_PERMISSIONS` |
| Origine non autorisée | 403 | `This origin is not allowed` | `ORIGIN_NOT_ALLOWED` |

### Mode optional

| Situation | Comportement |
|-----------|--------------|
| Token manquant | Continue sans authentification |
| Token invalide | Continue sans authentification |
| Token expiré | Continue sans authentification |
| Token révoqué | Continue sans authentification |
| Token valide | Authentifie l'utilisateur |

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
- Mode optionnel : Pas de surcharge supplémentaire

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
        return response()->json(['message' => 'Admin action performed']);
    }

    public function optionalAction()
    {
        // Cette méthode est protégée par 'nemesis.token:optional'
        $user = $this->helper->getCurrentAuthenticatable();
        
        if ($user) {
            return response()->json(['message' => 'Welcome back, ' . $user->name]);
        }
        
        return response()->json(['message' => 'Welcome, guest']);
    }
}
```

### Utilisation dans les routes

```php
// routes/api.php

use App\Http\Controllers\Api\ProfileController;
use App\Http\Controllers\Api\AdminController;
use App\Http\Controllers\Api\PublicController;
use App\Http\Controllers\Api\FeedController;

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

// Route avec authentification optionnelle
Route::get('/feed', [FeedController::class, 'index'])
    ->middleware('nemesis.token:optional');
```

### Accès aux données dans les contrôleurs

```php
// Via le helper
$user = NemesisHelper::getCurrentAuthenticatable();
$formatted = NemesisHelper::getCurrentAuthenticatableFormat();

// Via la requête
$user = $request->input('nemesis_auth');
$token = $request->input('current_nemesis_token');

// Via l'injection de dépendance
public function show(NemesisHelper $helper)
{
    $user = $helper->getCurrentAuthenticatable();
}
```

### Mode optional - Exemple complet

```php
// Dans le contrôleur FeedController
class FeedController extends Controller
{
    public function __construct(
        private readonly NemesisHelper $helper,
    ) {}

    public function index()
    {
        $user = $this->helper->getCurrentAuthenticatable();
        
        if ($user) {
            // Récupérer le feed personnalisé
            $feed = $this->getPersonalizedFeed($user);
        } else {
            // Récupérer le feed public
            $feed = $this->getPublicFeed();
        }
        
        return response()->json([
            'feed' => $feed,
            'authenticated' => $user !== null,
        ]);
    }
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