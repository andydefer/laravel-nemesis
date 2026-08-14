# ListTokensDirective - Référence Technique

## Description

Commande CLI pour lister tous les tokens d'authentification présents dans le système avec leurs métadonnées.

## Hiérarchie

```
AbstractDirective
    └── ListTokensDirective
```

## Rôle principal

Permet de visualiser l'ensemble des tokens stockés dans la base de données, avec la possibilité de filtrer par modèle et de limiter le nombre de résultats.

## Installation

La directive est automatiquement disponible après l'installation du package Nemesis.

```bash
# Lister tous les tokens
./bin/nemesis nemesis:list-tokens

# Limiter l'affichage
./bin/nemesis nemesis:list-tokens 20

# Filtrer par modèle
./bin/nemesis nemesis:list-tokens 30 --model=User
```

## API

### `getSignature(): string`

| Paramètre | Type | Description |
|-----------|------|-------------|
| Aucun | - | - |

**Retourne :** `string` - La signature de la commande

**Exemple :**
```php
public function getSignature(): string
{
    return 'nemesis:list-tokens 
                {limit=50}#"Maximum number of tokens to display" 
                {model=?}#"Filter by model name (partial match allowed)"';
}
```

### `getDescription(): string`

**Retourne :** `string` - Description de la commande

### `getAliases(): StringTypedCollection`

**Retourne :** `StringTypedCollection` - Collection des alias

**Exemple :**
```php
public function getAliases(): StringTypedCollection
{
    return StringTypedCollection::from(['tokens-list', 'nemesis-tokens']);
}
```

### `execute(): ExitCode`

**Retourne :** `ExitCode` - Code de sortie de la commande

## Cas d'utilisation

### Cas 1 : Lister tous les tokens

```bash
# Affiche les 50 premiers tokens (limite par défaut)
./bin/nemesis nemesis:list-tokens
```

### Cas 2 : Limiter le nombre de tokens affichés

```bash
# Affiche uniquement les 10 premiers tokens
./bin/nemesis nemesis:list-tokens 10
```

### Cas 3 : Filtrer par modèle

```bash
# Affiche les tokens liés au modèle User
./bin/nemesis nemesis:list-tokens 20 --model=User
```

### Cas 4 : Filtrer par modèle avec correspondance partielle

```bash
# Affiche les tokens liés à tout modèle contenant "Admin"
./bin/nemesis nemesis:list-tokens 30 --model=Admin
```

### Cas 5 : Utiliser un alias

```bash
# Premier alias
./bin/nemesis tokens-list 15

# Deuxième alias
./bin/nemesis nemesis-tokens 25 --model=User
```

## Flux d'exécution

```
Démarrage
    ↓
Récupérer la limite (défaut: 50)
    ↓
Vérifier si un filtre de modèle est présent
    ├── Oui → Filtrer les tokens par tokenable_type (LIKE %model%)
    └── Non → Récupérer tous les tokens
    ↓
Application de la limite
    ↓
Tokens trouvés ?
    ├── Non → Afficher "No tokens found"
    └── Oui → Afficher le tableau
        ├── ID
        ├── Tokenable Type
        ├── Tokenable ID
        ├── Name
        ├── Source
        ├── Last Used (diffForHumans)
        └── Expires At (diffForHumans)
    ↓
Afficher le nombre total de tokens
```

## Gestion des erreurs

| Situation | Exception | Message |
|-----------|-----------|---------|
| Aucune | - | - |

*La directive ne lève pas d'exceptions. Elle gère les cas sans tokens de manière silencieuse.*

## Intégration

Cette directive s'intègre avec :

- **NemesisService** : Pour les opérations de récupération via filtres
- **NemesisToken** : Modèle Eloquent pour les requêtes directes
- **AdaptiveTable** : Pour l'affichage en tableau adaptatif
- **NemesisTokenFilterRecord** : Pour les filtres avancés

## Performance

- Les requêtes utilisent `limit()` pour limiter les résultats
- Les filtres utilisent `LIKE` avec `%` pour les correspondances partielles
- Utilisation de `Collection` pour la manipulation des données
- Complexité : O(n) où n est le nombre de tokens affichés

## Compatibilité

| Version PHP | Support |
|-------------|---------|
| PHP 8.1+ | ✅ Complet |
| PHP 8.0 | ✅ Complet |

## Exemple complet

### En ligne de commande

```bash
# 1. Lister les tokens (limite par défaut: 50)
./bin/nemesis nemesis:list-tokens

# 2. Afficher 20 tokens
./bin/nemesis nemesis:list-tokens 20

# 3. Filtrer par modèle User
./bin/nemesis nemesis:list-tokens 15 --model=User

# 4. Filtrer par modèle partiel
./bin/nemesis nemesis:list-tokens 25 --model=Admin

# 5. Utiliser un alias
./bin/nemesis tokens-list 30

# 6. Utiliser le deuxième alias
./bin/nemesis nemesis-tokens 40 --model=ApiClient
```

### Sortie typique

```bash
$ ./bin/nemesis nemesis:list-tokens 10

┌────┬────────────────┬──────────────┬──────────────┬────────┬────────────────┬──────────────────┐
│ ID │ Tokenable Type │ Tokenable ID │ Name         │ Source │ Last Used      │ Expires At       │
├────┼────────────────┼──────────────┼──────────────┼────────┼────────────────┼──────────────────┤
│ 1  │ User           │ 42           │ Web Session  │ web    │ 2 hours ago    │ 1 day ago        │
│ 2  │ User           │ 42           │ Mobile Token │ mobile │ 5 minutes ago  │ 2 hours from now │
│ 3  │ ApiClient      │ 15           │ API Key      │ api    │ Never          │ Never            │
│ 4  │ Admin          │ 3            │ Admin Token  │ admin  │ 3 days ago     │ Expired 2 days   │
│ 5  │ User           │ 100          │ Session      │ web    │ 1 hour ago     │ 1 day from now   │
│ 6  │ CheckPoint     │ 8            │ Kiosk Token  │ kiosk  │ 10 minutes ago │ 3 hours from now │
│ 7  │ ApiClient      │ 22           │ External API │ api    │ Never          │ Never            │
│ 8  │ User           │ 55          │ Remember Me  │ web    │ 2 weeks ago    │ Expired 1 week   │
│ 9  │ Admin          │ 1            │ Admin Session│ admin  │ 4 hours ago    │ 8 hours from now │
│ 10 │ CheckPoint     │ 12           │ Scanner      │ kiosk  │ 1 day ago      │ 2 days from now  │
└────┴────────────────┴──────────────┴──────────────┴────────┴────────────────┴──────────────────┘

Total tokens: 10
```

### En PHP (exécution programmatique)

```php
<?php

declare(strict_types=1);

use AndyDefer\Directive\DirectiveKernel;
use AndyDefer\Directive\Enums\ExitCode;

// Dans un contrôleur ou un service
$kernel = app(DirectiveKernel::class);

// Lister les tokens
$exitCode = $kernel->runSignature('nemesis:list-tokens 20 --model=User');

if ($exitCode === ExitCode::SUCCESS) {
    echo "✅ Liste affichée avec succès\n";
} else {
    echo "❌ Erreur lors de l'affichage\n";
}
```

### Exemple avec des données

```bash
$ ./bin/nemesis nemesis:list-tokens 5 --model=User

Filtering by model: User

┌────┬────────────────┬──────────────┬──────────────┬────────┬─────────────┬─────────────────┐
│ ID │ Tokenable Type │ Tokenable ID │ Name         │ Source │ Last Used   │ Expires At      │
├────┼────────────────┼──────────────┼──────────────┼────────┼─────────────┼─────────────────┤
│ 1  │ User           │ 42           │ Web Session  │ web    │ 2 hours ago │ 1 day from now  │
│ 2  │ User           │ 42           │ Mobile Token │ mobile │ 5 min ago   │ 2 hours from now│
│ 5  │ User           │ 100          │ Session      │ web    │ 1 hour ago  │ 1 day from now  │
│ 8  │ User           │ 55           │ Remember Me  │ web    │ 2 weeks ago │ Expired 1 week  │
│ 10 │ User           │ 99           │ Reset Token  │ web    │ Never       │ 1 hour from now │
└────┴────────────────┴──────────────┴──────────────┴────────┴─────────────┴─────────────────┘

Total tokens: 5
```

## Voir aussi

- `CleanTokensDirective` - Nettoyage des tokens expirés
- `InstallNemesisDirective` - Installation du package
- `NemesisService` - Service de gestion des tokens
- `NemesisToken` - Modèle de token
- `NemesisTokenFilterRecord` - Filtres pour la recherche de tokens
---