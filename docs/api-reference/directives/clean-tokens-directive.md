# CleanTokensDirective - Référence Technique

## Description

Commande CLI pour nettoyer les tokens expirés et anciens de la base de données, avec possibilité de configurer la période de rétention.

## Hiérarchie

```
AbstractDirective
    └── CleanTokensDirective
```

## Rôle principal

Supprime automatiquement les tokens qui ont expiré (date d'expiration dépassée) ainsi que les tokens anciens (créés avant une date limite configurable). Cette directive est essentielle pour maintenir une base de données propre et performante.

## Installation

La directive est automatiquement disponible après l'installation du package Nemesis.

```bash
./bin/nemesis nemesis:clean-tokens --force
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
    return 'nemesis:clean-tokens 
                {days=?}#"Nombre de jours à conserver" 
                {--force}#"Forcer sans confirmation" 
                {--keep-expired}#"Garder les tokens expirés"';
}
```

### `getDescription(): string`

| Paramètre | Type | Description |
|-----------|------|-------------|
| Aucun | - | - |

**Retourne :** `string` - Description de la commande

### `getAliases(): StringTypedCollection`

**Retourne :** `StringTypedCollection` - Collection des alias

**Exemple :**
```php
public function getAliases(): StringTypedCollection
{
    return StringTypedCollection::from(['nemesis-tc', 'nemesis-ce']);
}
```

### `execute(): ExitCode`

**Retourne :** `ExitCode` - Code de sortie de la commande

**Exemple :**
```php
public function execute(): ExitCode
{
    $config = $this->getApplication()->make(NemesisConfigInterface::class);
    $service = $this->getApplication()->make(NemesisService::class);

    if (! $this->shouldProceed()) {
        return ExitCode::SUCCESS;
    }

    $statistics = $this->performCleanup($config, $service);
    $this->displayResults($statistics, $config);

    return ExitCode::SUCCESS;
}
```

## Cas d'utilisation

### Cas 1 : Nettoyage standard avec confirmation

```bash
# Lance le nettoyage avec demande de confirmation
./bin/nemesis nemesis:clean-tokens
```

### Cas 2 : Nettoyage forcé sans confirmation

```bash
# Supprime tous les tokens expirés sans demander confirmation
./bin/nemesis nemesis:clean-tokens --force
```

### Cas 3 : Nettoyage avec période de rétention personnalisée

```bash
# Supprime les tokens de plus de 15 jours
./bin/nemesis nemesis:clean-tokens 15 --force
```

### Cas 4 : Nettoyage en gardant les tokens expirés

```bash
# Supprime uniquement les tokens anciens (pas les expirés)
./bin/nemesis nemesis:clean-tokens 30 --keep-expired --force
```

### Cas 5 : Utilisation d'un alias

```bash
# Alias court pour le nettoyage
./bin/nemesis nemesis-tc --force

# Deuxième alias disponible
./bin/nemesis nemesis-ce --force
```

## Flux d'exécution

```
Démarrage
    ↓
Vérifier si --force est présent
    ├── Oui → Poursuivre
    └── Non → Demander confirmation
        ├── Oui → Poursuivre
        └── Non → Arrêter
    ↓
Nettoyer les tokens expirés
    ├── --keep-expired présent → Ignorer
    └── Sinon → Supprimer tous les tokens expirés
    ↓
Nettoyer les tokens anciens
    ├── Récupérer la période de rétention
    │   ├── Argument 'days' → Utiliser cette valeur
    │   └── Sinon → Utiliser la config
    ├── Date limite = maintenant - période
    └── Supprimer les tokens créés avant cette date
    ↓
Afficher les statistiques
    ├── Tokens expirés supprimés
    ├── Tokens anciens supprimés
    └── Total supprimés
    ↓
Afficher le résumé de configuration
```

## Gestion des erreurs

| Situation | Exception | Message |
|-----------|-----------|---------|
| Aucune | - | - |

*La directive ne lève pas d'exceptions. Elle gère les erreurs de manière silencieuse et affiche les résultats.*

## Intégration

Cette directive s'intègre avec :

- **NemesisConfigInterface** : Pour lire la configuration de nettoyage
- **NemesisService** : Pour effectuer les opérations de suppression
- **DirectiveTestingService** : Pour les tests unitaires

## Performance

- Les requêtes de suppression utilisent des filtres optimisés
- Les tokens sont supprimés en batch via `forceDeleteBulk()`
- Complexité : O(n) où n est le nombre de tokens supprimés

## Compatibilité

| Version PHP | Support |
|-------------|---------|
| PHP 8.1+ | ✅ Complet |
| PHP 8.0 | ✅ Complet |

## Exemple complet

### En ligne de commande

```bash
# 1. Lister les directives disponibles
./bin/nemesis --list

# 2. Nettoyer les tokens de plus de 30 jours avec confirmation
./bin/nemesis nemesis:clean-tokens

# 3. Nettoyage forcé de 15 jours
./bin/nemesis nemesis:clean-tokens 15 --force

# 4. Nettoyer uniquement les anciens tokens, garder les expirés
./bin/nemesis nemesis:clean-tokens 20 --keep-expired --force

# 5. Utiliser un alias
./bin/nemesis nemesis-tc --force
```

### En PHP (exécution programmatique)

```php
<?php

declare(strict_types=1);

use AndyDefer\Directive\DirectiveKernel;
use AndyDefer\Directive\Enums\ExitCode;

// Dans un contrôleur ou un service
$kernel = app(DirectiveKernel::class);

// Exécuter la directive
$exitCode = $kernel->runSignature('nemesis:clean-tokens 30 --force');

if ($exitCode === ExitCode::SUCCESS) {
    echo "✅ Nettoyage terminé avec succès\n";
} else {
    echo "❌ Erreur lors du nettoyage\n";
}
```

### Sortie typique

```bash
$ ./bin/nemesis nemesis:clean-tokens --force
Using retention period from config: 30 days
Deleted 5 expired tokens
Deleted 12 old tokens (older than 30 days)

═══════════════════════════════════════════════════════
🧹 TOKEN CLEANUP COMPLETED
═══════════════════════════════════════════════════════
┌─────────────────────────┬─────────┐
│ Metric                  │ Count   │
├─────────────────────────┼─────────┤
│ Expired tokens deleted  │ 5       │
│ Old tokens deleted      │ 12      │
│ ━━━━━━━━━━━━━━━━━━━━━   │ ━━━━━━━ │
│ Total tokens deleted    │ 17      │
└─────────────────────────┴─────────┘

✅ Cleanup completed successfully!

📋 Current Configuration:
   • Auto cleanup: ✅ Enabled
   • Cleanup frequency: 60 minutes
   • Retention period: 30 days
   • Validate origin: ✅ Enabled
   • Expired tokens: ✅ Removed
```

## Voir aussi

- `NemesisService` - Service de gestion des tokens
- `NemesisConfigInterface` - Interface de configuration
- `DirectiveTestingService` - Service de test des directives
- `ListTokensDirective` - Directive pour lister les tokens
---