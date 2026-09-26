# AgentService - Référence Technique

## Description

Service de détection d'agent utilisateur qui encapsule `jenssegers/agent` pour fournir une interface propre et typée pour identifier navigateur, plateforme, type d'appareil, version, et statut robot.

## Hiérarchie / Implémentations

```
AgentServiceInterface
    └── AgentService
```

Dépendance interne :
- `Jenssegers\Agent\Agent` — utilisé comme moteur de parsing.

## Rôle principal

`AgentService` sert de point unique pour extraire les propriétés d'un User-Agent HTTP. Il est utilisé par le package Nemesis pour :

- Enrichir les métadonnées d'un token (navigateur, OS, type d'appareil).
- Alimenter un `AgentPropertiesRecord` typé exposable ou persistable.
- Fournir un contrat stable même si la dépendance `jenssegers/agent` change.

Il normalise les valeurs manquantes en `'unknown'` pour éviter les `false` ou valeurs nulles dans les Records.

## Installation

Ajouter la dépendance à `composer.json` :

```bash
composer require jenssegers/agent
```

Enregistrer le service dans le `NemesisServiceProvider` :

```php
$this->app->singleton(
    abstract: \AndyDefer\Nemesis\Services\AgentService::class,
    concrete: fn () => new \AndyDefer\Nemesis\Services\AgentService(
        new \Jenssegers\Agent\Agent(),
    )
);

$this->app->bind(
    abstract: \AndyDefer\Nemesis\Contracts\Services\AgentServiceInterface::class,
    concrete: \AndyDefer\Nemesis\Services\AgentService::class,
);
```

## API / Méthodes publiques

### `__construct(JenssegersAgent $agent)`

| Paramètre | Type | Description |
|-----------|------|-------------|
| `$agent` | `JenssegersAgent` | Instance sous-jacente de `jenssegers/agent` |

**Retourne :** `void`

**Exceptions :** Aucune.

---

### `browser(): string`

Retourne le nom du navigateur.

**Retourne :** `string` — Nom du navigateur (`Chrome`, `Firefox`, `Safari`, etc.) ou `'unknown'`.

**Exemple :**

```php
$service->browser(); // 'Chrome'
```

---

### `platform(): string`

Retourne le nom de la plateforme / OS.

**Retourne :** `string` — Nom de la plateforme (`Windows`, `OS X`, `Linux`, `iOS`, `Android`, etc.) ou `'unknown'`.

**Exemple :**

```php
$service->platform(); // 'OS X'
```

---

### `deviceType(): string`

Retourne le type d'appareil.

**Retourne :** `string` — Valeur normalisée par `jenssegers/agent` (`desktop`, `phone`, `tablet`, `robot`, etc.).

**Exemple :**

```php
$service->deviceType(); // 'desktop'
```

---

### `isMobile(): bool`

Indique si l'agent est un appareil mobile.

**Retourne :** `bool`

**Exemple :**

```php
$service->isMobile(); // true pour iPhone
```

---

### `isRobot(): bool`

Indique si l'agent est un robot / crawler.

**Retourne :** `bool`

**Exemple :**

```php
$service->isRobot(); // true pour Googlebot
```

---

### `isDesktop(): bool`

Indique si l'agent est un desktop.

**Retourne :** `bool`

---

### `isTablet(): bool`

Indique si l'agent est une tablette.

**Retourne :** `bool`

---

### `version(): string`

Retourne la version du navigateur.

**Retourne :** `string` — Version (ex. `'120.0.0.0'`) ou `'unknown'`.

**Exemple :**

```php
$service->version(); // '120.0.0.0'
```

---

### `platformVersion(): string`

Retourne la version de l'OS.

**Retourne :** `string` — Version (ex. `'10_15_7'`) ou `'unknown'`.

---

### `getUserAgent(): string`

Retourne la chaîne User-Agent brute.

**Retourne :** `string`

---

### `setUserAgent(string $userAgent): self`

Change la chaîne User-Agent analysée. Retourne l'instance pour chaînage.

| Paramètre | Type | Description |
|-----------|------|-------------|
| `$userAgent` | `string` | Nouvelle chaîne User-Agent |

**Retourne :** `self` — L'instance courante.

**Exemple :**

```php
$service
    ->setUserAgent('Mozilla/5.0 ... Chrome/120.0.0.0')
    ->browser(); // 'Chrome'
```

---

### `getProperties(): AgentPropertiesRecord`

Retourne toutes les propriétés détectées sous forme de Record typé.

**Retourne :** `AgentPropertiesRecord` — Record contenant : `browser`, `browser_version`, `platform`, `platform_version`, `device_type`, `is_mobile`, `is_desktop`, `is_tablet`, `is_robot`, `user_agent`.

**Exemple :**

```php
$record = $service->getProperties();

echo $record->browser;      // 'Chrome'
echo $record->platform;     // 'OS X'
echo $record->device_type;  // 'desktop'
```

---

## Cas d'utilisation

### Cas 1 : Enregistrer les métadonnées d'un token Nemesis

Lors de la création d'un token, on enrichit les métadonnées avec les informations du User-Agent.

```php
use AndyDefer\Nemesis\Contracts\Services\AgentServiceInterface;
use AndyDefer\Nemesis\Contracts\Services\NemesisInterface;
use AndyDefer\Nemesis\Records\NemesisTokenRecord;

final class TokenIssuer
{
    public function __construct(
        private readonly NemesisInterface $nemesis,
        private readonly AgentServiceInterface $agent,
    ) {}

    public function issue(User $user): string
    {
        $record = NemesisTokenRecord::from([
            'name' => 'Web Session',
            'source' => 'web',
            'metadata' => $this->agent->getProperties()->toArray(),
        ]);

        [, $plainToken] = $this->nemesis->createWithPlainToken($record, $user);

        return $plainToken;
    }
}
```

### Cas 2 : Bloquer les robots dans un middleware

Empêcher les crawlers d'obtenir un token.

```php
use AndyDefer\Nemesis\Contracts\Services\AgentServiceInterface;

final class RejectRobots
{
    public function __construct(
        private readonly AgentServiceInterface $agent,
    ) {}

    public function handle(Request $request, Closure $next): Response
    {
        if ($this->agent->isRobot()) {
            abort(403, 'Robots are not allowed.');
        }

        return $next($request);
    }
}
```

### Cas 3 : Adapter la réponse selon le type d'appareil

Renvoie une vue différente selon mobile ou desktop.

```php
use AndyDefer\Nemesis\Contracts\Services\AgentServiceInterface;

final class DashboardController
{
    public function __construct(
        private readonly AgentServiceInterface $agent,
    ) {}

    public function __invoke(): Response
    {
        if ($this->agent->isMobile()) {
            return view('dashboard.mobile');
        }

        if ($this->agent->isTablet()) {
            return view('dashboard.tablet');
        }

        return view('dashboard.desktop');
    }
}
```

## Flux d'exécution

```
new AgentService(new JenssegersAgent)
    → browser()
    → platform()
    → deviceType()
    → isMobile() / isDesktop() / isTablet() / isRobot()
    → version() / platformVersion()
    → getUserAgent()
    → getProperties()
        → new AgentPropertiesRecord(...)
```

## Gestion des erreurs

| Situation | Exception | Message |
|-----------|-----------|---------|
| User-Agent vide | Aucune | Retourne `'unknown'` pour `browser()`, `platform()`, `version()`, `platformVersion()` |
| User-Agent inconnu | Aucune | Retourne `'unknown'` |
| Dépendance `jenssegers/agent` manquante | `Error` (au boot) | `Class "Jenssegers\Agent\Agent" not found` |

`AgentService` ne lève pas d'exception métier : il normalise les cas limites en `'unknown'` et `false`.

## Intégration

`AgentService` s'intègre avec :

- `AgentServiceInterface` — contrat injectable.
- `AgentPropertiesRecord` — Record typé de sortie.
- `NemesisServiceProvider` — enregistrement du service.
- `NemesisInterface` — enrichissement des métadonnées de token.
- Middlewares Nemesis — filtrage des robots ou personnalisation des réponses.

## Performance

- `browser()`, `platform()`, `deviceType()` : parsing regex, **O(n)** sur la longueur du User-Agent.
- `getProperties()` : **10 appels** internes au `Jenssegers\Agent`. Chaque appel re-parse le même UA. Coût négligeable pour un UA standard (< 500 caractères).
- Aucun cache interne. Si besoin, appeler `getProperties()` une seule fois et réutiliser le Record.

## Compatibilité

| Version | Support |
|---------|---------|
| PHP 8.1+ | ✅ Complet |
| PHP 8.0 | ✅ Complet |
| `jenssegers/agent` ^2.6 | ✅ Requis |

## Exemple complet

```php
<?php

declare(strict_types=1);

use AndyDefer\Nemesis\Services\AgentService;
use Jenssegers\Agent\Agent as JenssegersAgent;

$agent = new JenssegersAgent();
$agent->setUserAgent(
    'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) '
    . 'AppleWebKit/537.36 (KHTML, like Gecko) '
    . 'Chrome/120.0.0.0 Safari/537.36'
);

$service = new AgentService($agent);

echo $service->browser();          // 'Chrome'
echo $service->version();          // '120.0.0.0'
echo $service->platform();         // 'OS X'
echo $service->deviceType();       // 'desktop'
var_dump($service->isDesktop());   // true
var_dump($service->isMobile());    // false
var_dump($service->isRobot());     // false

$properties = $service->getProperties();

print_r($properties->toArray());
// [
//   'browser' => 'Chrome',
//   'browser_version' => '120.0.0.0',
//   'platform' => 'OS X',
//   'platform_version' => '10_15_7',
//   'device_type' => 'desktop',
//   'is_mobile' => false,
//   'is_desktop' => true,
//   'is_tablet' => false,
//   'is_robot' => false,
//   'user_agent' => 'Mozilla/5.0 ...',
// ]
```

## Voir aussi

- `AgentServiceInterface` — contrat du service.
- `AgentPropertiesRecord` — Record typé de sortie.
- `NemesisInterface` — service qui consomme les métadonnées d'agent.
- `NemesisServiceProvider` — point d'enregistrement dans le conteneur.