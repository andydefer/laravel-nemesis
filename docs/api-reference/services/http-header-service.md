# HttpHeaderService - Référence Technique

## Description

Service pour l'application des en-têtes HTTP (sécurité, CORS) sur les réponses.

## Hiérarchie

```
HttpHeaderInterface
    └── HttpHeaderService
```

## Rôle principal

Applique les en-têtes de sécurité et CORS sur les réponses HTTP. Service pur sans logique métier, uniquement la manipulation des en-têtes HTTP.

## Installation

Le service est automatiquement enregistré via le ServiceProvider.

```php
use AndyDefer\Nemesis\Contracts\Services\HttpHeaderInterface;

$headerService = app(HttpHeaderInterface::class);
```

## API

### `applySecurityHeaders(mixed $response): mixed`

| Paramètre | Type | Description |
|-----------|------|-------------|
| `$response` | `mixed` | La réponse HTTP |

**Retourne :** `mixed` - La réponse modifiée

**En-têtes appliqués :**
- `X-Frame-Options: DENY`
- `X-XSS-Protection: 1; mode=block`
- `X-Content-Type-Options: nosniff`
- `Referrer-Policy: strict-origin-when-cross-origin`
- `Strict-Transport-Security` (en production uniquement)

**Exemple :**
```php
$response = $headerService->applySecurityHeaders($response);
```

### `applyCorsHeaders(mixed $response, Request $request): mixed`

| Paramètre | Type | Description |
|-----------|------|-------------|
| `$response` | `mixed` | La réponse HTTP |
| `$request` | `Request` | La requête HTTP |

**Retourne :** `mixed` - La réponse modifiée

**En-têtes appliqués :**
- `Access-Control-Allow-Origin: {origin}`
- `Access-Control-Allow-Credentials` (si configuré)
- `Access-Control-Allow-Methods` (pour OPTIONS)
- `Access-Control-Allow-Headers` (pour OPTIONS)
- `Access-Control-Max-Age` (pour OPTIONS)
- `Access-Control-Expose-Headers` (si configuré)

**Exemple :**
```php
$response = $headerService->applyCorsHeaders($response, $request);
```

### `addCorsToErrorResponse(JsonResponse $response, Request $request): JsonResponse`

| Paramètre | Type | Description |
|-----------|------|-------------|
| `$response` | `JsonResponse` | La réponse d'erreur |
| `$request` | `Request` | La requête HTTP |

**Retourne :** `JsonResponse` - La réponse modifiée

**Exemple :**
```php
$errorResponse = ResponseFactory::json($errorData, 401)->toResponse();
$errorResponse = $headerService->addCorsToErrorResponse($errorResponse, $request);
```

## Cas d'utilisation

### Cas 1 : Application des en-têtes de sécurité

```php
class NemesisTokenMiddleware
{
    public function handle(Request $request, Closure $next)
    {
        // ... authentification ...
        
        $response = $next($request);
        
        // Application des en-têtes de sécurité
        $response = $this->headerService->applySecurityHeaders($response);
        
        return $response;
    }
}
```

### Cas 2 : Application des en-têtes CORS

```php
class ApiMiddleware
{
    public function handle(Request $request, Closure $next)
    {
        // ... traitement ...
        
        $response = $next($request);
        
        // Application des en-têtes CORS
        $response = $this->headerService->applyCorsHeaders($response, $request);
        
        return $response;
    }
}
```

### Cas 3 : CORS sur les erreurs

```php
// Dans un middleware
if ($authError) {
    $errorResponse = ResponseFactory::json([
        'error' => 'Unauthorized',
    ], 401)->toResponse();
    
    return $this->headerService->addCorsToErrorResponse($errorResponse, $request);
}
```

## Flux d'exécution

### Sécurité
```
applySecurityHeaders($response)
    ↓
Vérifier si security_headers est activé
    ├── Non → Retourner la réponse
    └── Oui → Poursuivre
        ↓
Vérifier si la réponse a une méthode header()
    ├── Non → Retourner la réponse
    └── Oui → Poursuivre
        ↓
Ajouter les en-têtes de sécurité
    ├── X-Frame-Options: DENY
    ├── X-XSS-Protection: 1; mode=block
    ├── X-Content-Type-Options: nosniff
    └── Referrer-Policy: strict-origin-when-cross-origin
    ↓
Vérifier l'environnement
    ├── Production → Ajouter HSTS
    └── Non-production → Ignorer
    ↓
Retourner la réponse
```

### CORS
```
applyCorsHeaders($response, $request)
    ↓
Vérifier si validate_origin est activé
    ├── Non → Retourner la réponse
    └── Oui → Poursuivre
        ↓
Vérifier si la réponse a une méthode header()
    ├── Non → Retourner la réponse
    └── Oui → Poursuivre
        ↓
Récupérer l'origine de la requête
    ├── Non → Retourner la réponse
    └── Oui → Poursuivre
        ↓
Ajouter Access-Control-Allow-Origin
    ↓
Ajouter Access-Control-Allow-Credentials (si configuré)
    ↓
Si méthode OPTIONS → Ajouter les en-têtes préflight
    ├── Access-Control-Allow-Methods
    ├── Access-Control-Allow-Headers
    └── Access-Control-Max-Age
    ↓
Ajouter Access-Control-Expose-Headers (si configuré)
    ↓
Retourner la réponse
```

## Gestion des erreurs

| Situation | Comportement |
|-----------|--------------|
| security_headers désactivé | Retourne la réponse inchangée |
| validate_origin désactivé | Retourne la réponse inchangée |
| Pas de méthode header() | Retourne la réponse inchangée |
| Pas d'origine | Retourne la réponse inchangée |

*Le service ne lève pas d'exceptions. Il gère les cas d'absence de manière silencieuse.*

## Intégration

Ce service s'intègre avec :

- **NemesisConfigInterface** : Pour la configuration
- **Application** : Pour la détection de l'environnement
- **JsonResponse** : Pour les réponses JSON
- **Response** : Pour les réponses HTTP standard
- **Request** : Pour la lecture des origines

## Configuration

```php
// config/nemesis.php

'middleware' => [
    'security_headers' => true,        // Activer les en-têtes de sécurité
    'validate_origin' => true,         // Activer la validation CORS
],

'cors' => [
    'allow_credentials' => true,       // Autoriser les credentials
    'max_age' => 86400,                // Cache préflight (24h)
    'expose_token_info' => false,      // Exposer les infos token
],
```

## En-têtes appliqués

### Sécurité

| En-tête | Valeur | Description |
|---------|--------|-------------|
| `X-Frame-Options` | `DENY` | Empêche le clickjacking |
| `X-XSS-Protection` | `1; mode=block` | Protection XSS |
| `X-Content-Type-Options` | `nosniff` | Empêche le MIME sniffing |
| `Referrer-Policy` | `strict-origin-when-cross-origin` | Politique de referer |
| `Strict-Transport-Security` | `max-age=31536000; includeSubDomains` | HSTS (production) |

### CORS

| En-tête | Valeur | Description |
|---------|--------|-------------|
| `Access-Control-Allow-Origin` | `{origin}` | Origine autorisée |
| `Access-Control-Allow-Credentials` | `true` | Credentials autorisés |
| `Access-Control-Allow-Methods` | `GET, POST, PUT, PATCH, DELETE, OPTIONS` | Méthodes autorisées |
| `Access-Control-Allow-Headers` | `Content-Type, Authorization, X-Requested-With` | Headers autorisés |
| `Access-Control-Max-Age` | `86400` | Cache préflight |
| `Access-Control-Expose-Headers` | `X-Token-Expires-At, X-Token-Abilities` | Headers exposés |

## Performance

- Opérations O(1) sur les en-têtes
- Pas de logique métier complexe
- Pas de cache nécessaire
- Complexité : O(1)

## Compatibilité

| Version PHP | Support |
|-------------|---------|
| PHP 8.1+ | ✅ Complet |
| PHP 8.0 | ✅ Complet |

## Exemple complet

```php
<?php

declare(strict_types=1);

use AndyDefer\Nemesis\Contracts\Services\HttpHeaderInterface;
use AndyDefer\Nemesis\Services\HttpHeaderService;

class ResponseHandler
{
    public function __construct(
        private readonly HttpHeaderInterface $headerService,
    ) {}

    public function handleResponse(Request $request, $data, int $status = 200)
    {
        $response = response()->json($data, $status);
        
        // Appliquer les en-têtes de sécurité
        $response = $this->headerService->applySecurityHeaders($response);
        
        // Appliquer les en-têtes CORS
        $response = $this->headerService->applyCorsHeaders($response, $request);
        
        return $response;
    }

    public function handleError(Request $request, string $message, int $status = 400)
    {
        $response = response()->json([
            'error' => $message,
            'status' => $status,
        ], $status);
        
        // Appliquer CORS sur l'erreur
        return $this->headerService->addCorsToErrorResponse($response, $request);
    }
}

// Utilisation
$handler = new ResponseHandler($headerService);

// Succès
$response = $handler->handleResponse($request, ['data' => 'success']);

// Erreur
$response = $handler->handleError($request, 'Unauthorized', 401);
```

## Voir aussi

- `NemesisTokenMiddleware` - Middleware utilisant ce service
- `NemesisConfigInterface` - Configuration des en-têtes
- `HttpHeaderInterface` - Interface du service
- `ResponseFactory` - Factory pour les réponses
- `JsonResponse` - Réponse JSON Laravel
---