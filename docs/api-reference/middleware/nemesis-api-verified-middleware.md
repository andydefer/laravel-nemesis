# NemesisApiVerifiedMiddleware - Référence Technique

## Description

Middleware d'API qui vérifie que l'utilisateur authentifié a validé son adresse email. Étend le `NemesisTokenMiddleware` en ajoutant une couche de vérification d'email.

## Hiérarchie / Implémentations

```
Middleware Laravel
    └── NemesisApiVerifiedMiddleware
```

## Rôle principal

Ce middleware s'assure que seuls les utilisateurs ayant vérifié leur email peuvent accéder aux routes API protégées. Il combine l'authentification par token Bearer avec une validation du champ `email_verified_at` du modèle.

## Installation

```bash
# Le middleware est automatiquement enregistré via le ServiceProvider
# Alias disponible : 'nemesis.api.verified'
```

## API / Méthodes publiques

### `handle(Request $request, Closure $next): Response`

| Paramètre | Type | Description |
|-----------|------|-------------|
| `$request` | `Request` | La requête HTTP entrante |
| `$next` | `Closure` | Le prochain middleware dans la pipeline |

**Retourne :** `Response` - La réponse HTTP

**Exceptions :** Aucune exception levée directement, mais retourne des réponses d'erreur structurées

**Exemple :**
```php
// Dans les routes
Route::middleware('nemesis.api.verified')->get('/profile', function () {
    return response()->json(auth()->user());
});
```

## Cas d'utilisation

### Cas 1 : Protection d'un profil utilisateur

```php
// routes/api.php
Route::middleware('nemesis.api.verified')->group(function () {
    Route::get('/profile', [ProfileController::class, 'show']);
    Route::put('/profile', [ProfileController::class, 'update']);
});
```

### Cas 2 : Accès à des fonctionnalités sensibles

```php
// routes/api.php
Route::middleware('nemesis.api.verified')->post('/transfer', [BankController::class, 'transfer']);
```

### Cas 3 : Combinaison avec d'autres middlewares

```php
// routes/api.php
Route::middleware(['throttle:60,1', 'nemesis.api.verified'])->group(function () {
    Route::post('/orders', [OrderController::class, 'store']);
});
```

## Flux d'exécution

```
Requête entrante
    ↓
1. Authentification via Bearer token (NemesisAuthenticationInterface)
    ↓
2. Vérification du token
    ├── Échec → Retourne 401
    └── Succès → Continue
    ↓
3. Récupération du token model
    ↓
4. Validation du token
    ├── Token invalide ou expiré → Retourne 401
    └── Token valide → Continue
    ↓
5. Récupération de l'utilisateur (tokenable)
    ├── Utilisateur introuvable → Retourne 401
    └── Utilisateur trouvé → Continue
    ↓
6. Vérification de la colonne email_verified_at (Schema::hasColumn)
    ├── Colonne manquante → Retourne 500
    └── Colonne présente → Continue
    ↓
7. Vérification de l'email (email_verified_at)
    ├── Email non vérifié → Retourne 403
    └── Email vérifié → Continue
    ↓
8. Injection de l'utilisateur et du token dans la requête
    ↓
9. Passage au middleware suivant
```

## Gestion des erreurs

| Situation | Code HTTP | ErrorCode | Message |
|-----------|-----------|-----------|---------|
| Token absent | 401 | `MISSING_TOKEN` | `Token not provided` |
| Token invalide | 401 | `INVALID_TOKEN` | `Invalid token` |
| Token expiré | 401 | `TOKEN_EXPIRED` | `Token has expired` |
| Token non valide (révoqué, expiré) | 401 | `INVALID_TOKEN` | `Invalid token` |
| Utilisateur introuvable | 401 | `AUTHENTICATABLE_NOT_FOUND` | `User not found` |
| Colonne email_verified_at manquante | 500 | `MODEL_MISSING_EMAIL_VERIFIED_AT` | `Model must have email_verified_at field` |
| Email non vérifié | 403 | `EMAIL_NOT_VERIFIED` | `Email not verified. Please verify your email address.` |

## Réponses d'erreur

Toutes les erreurs retournent un JSON structuré :

```json
{
    "errorCode": "EMAIL_NOT_VERIFIED",
    "message": "Email not verified. Please verify your email address.",
    "status": 403,
    "details": null
}
```

## Intégration

### Avec `NemesisAuthenticationService`

Le middleware utilise `NemesisAuthenticationService::authenticate()` pour valider le token Bearer.

### Avec `NemesisService`

Le middleware utilise `NemesisService::findByHash()` pour récupérer le modèle du token.

### Avec `Schema` (Laravel)

Le middleware utilise `Schema::hasColumn()` pour vérifier la présence de la colonne `email_verified_at` dans la table du modèle.

### Avec `ErrorCode` enum

Tous les codes d'erreur sont centralisés dans l'énumération `ErrorCode`.

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

// routes/api.php
use AndyDefer\Nemesis\Http\Middleware\NemesisApiVerifiedMiddleware;

// Protection d'un groupe de routes
Route::middleware(NemesisApiVerifiedMiddleware::class)->group(function () {
    Route::get('/dashboard', function () {
        $user = request()->nemesis_auth;
        return response()->json([
            'user' => $user->nemesisFormat(),
            'message' => 'Welcome to your dashboard',
        ]);
    });

    Route::post('/settings', function (Request $request) {
        $user = request()->nemesis_auth;
        $user->update($request->validated());
        return response()->json(['message' => 'Settings updated']);
    });
});

// Utilisation avec l'alias
Route::middleware('nemesis.api.verified')->get('/profile', [ProfileController::class, 'show']);

// Controller utilisant l'utilisateur injecté
class ProfileController extends Controller
{
    public function show()
    {
        $user = request()->nemesis_auth; // ✅ L'utilisateur est injecté
        $token = request()->current_nemesis_token;
        $formatted = request()->nemesis_auth_format;

        return response()->json([
            'user' => $formatted,
            'token_id' => $token?->id,
        ]);
    }
}
```

## Voir aussi

- `NemesisTokenMiddleware` - Middleware d'authentification API de base
- `NemesisWebVerifiedMiddleware` - Version web (cookie-based) de ce middleware
- `NemesisAuthenticationService` - Service d'authentification utilisé
- `NemesisService` - Service de gestion des tokens
- `ErrorCode` - Enumération des codes d'erreur