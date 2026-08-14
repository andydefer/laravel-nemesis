# InstallNemesisDirective - Référence Technique

## Description

Commande CLI pour installer le package Nemesis. Publie automatiquement la configuration, les migrations, exécute les migrations et vérifie l'installation.

## Hiérarchie

```
AbstractDirective
    └── InstallNemesisDirective
```

## Rôle principal

Automatise l'installation complète du package Nemesis en une seule commande. Vérifie la présence du package, copie les fichiers nécessaires, exécute les migrations et valide l'installation.

## Installation

La directive est automatiquement disponible après l'installation du package.

```bash
# Installation standard
./bin/nemesis nemesis:install

# Installation forcée (écrase les fichiers existants)
./bin/nemesis nemesis:install --force
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
    return 'nemesis:install {--force}#"Force overwrite existing files"';
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
    return StringTypedCollection::from(['nemesis-install', 'setup-nemesis']);
}
```

### `execute(): ExitCode`

**Retourne :** `ExitCode` - Code de sortie de la commande

## Cas d'utilisation

### Cas 1 : Installation standard

```bash
# Installation avec confirmation
./bin/nemesis nemesis:install
```

### Cas 2 : Installation forcée

```bash
# Écrase les fichiers existants (config, migration)
./bin/nemesis nemesis:install --force
```

### Cas 3 : Installation via alias

```bash
# Alias court
./bin/nemesis nemesis-install --force

# Deuxième alias disponible
./bin/nemesis setup-nemesis --force
```

### Cas 4 : Installation en CI/CD

```bash
# Installation non interactive pour les pipelines
./bin/nemesis nemesis:install --force --no-interaction
```

## Flux d'exécution

```
Démarrage
    ↓
Vérifier --force ou confirmer l'installation
    ├── Non → Annulation
    └── Oui → Poursuivre
    ↓
1. Vérifier que le package existe
    ├── Non → Erreur (composer require)
    └── Oui → Poursuivre
    ↓
2. Publier la configuration
    ├── Fichier existe et pas --force → Ignorer
    └── Sinon → Copier config/nemesis.php
    ↓
3. Publier la migration
    ├── Fichier existe et pas --force → Ignorer
    └── Sinon → Copier migration
    ↓
4. Exécuter les migrations (php artisan migrate)
    ├── Échec → Erreur
    └── Succès → Poursuivre
    ↓
5. Vérifier que la table a été créée
    ├── Non → Erreur
    └── Oui → Poursuivre
    ↓
6. Afficher la configuration
    ↓
7. Afficher le résumé d'installation
    ↓
8. Afficher les prochaines étapes
```

## Gestion des erreurs

| Situation | Exception | Message |
|-----------|-----------|---------|
| Package non trouvé | - | `Package not found at: {path}` |
| Config source manquante | - | `Config source not found: {path}` |
| Migration source manquante | - | `Migration source not found: {path}` |
| Échec des migrations | - | `Failed to run migrations. Output: {output}` |
| Table non créée | - | `Table 'nemesis_tokens' not found.` |

## Intégration

Cette directive s'intègre avec :

- **FileSystemInterface** : Pour la manipulation des fichiers
- **DatabaseManager** : Pour les opérations de base de données
- **NemesisConfigInterface** : Pour valider la configuration
- **Application** : Pour accéder aux chemins de l'application
- **Timeline** : Pour l'affichage des étapes

## Performance

- Les opérations sont I/O-bound (lecture/écriture de fichiers, migrations)
- Utilise `exec()` pour lancer les migrations
- Complexité : O(1) - dépend du nombre de fichiers à copier

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

# 2. Installation standard
./bin/nemesis nemesis:install

# 3. Installation forcée (écrase les fichiers)
./bin/nemesis nemesis:install --force

# 4. Installation via alias
./bin/nemesis nemesis-install --force

# 5. Installation non interactive
./bin/nemesis nemesis:install --force --no-interaction
```

### Sortie typique

```bash
$ ./bin/nemesis nemesis:install --force

🔐 Nemesis Installation
📦 Checking package files...
  ✓ Package found
📄 Publishing configuration...
  ✓ Config published to config/nemesis.php
🗄️ Publishing migration...
  ✓ Migration published
🗄️ Running migrations...
  ✓ Migrations executed
✅ Verifying database table...
  ✓ Table "nemesis_tokens" exists
⚙️ Configuration loaded:
  token_length      64
  hash_algorithm    sha256
  expiration        60
  validate_origin   true

------------------------------------------------------------
📋 Installation summary:

Package verified                 andydefer/laravel-nemesis found
Configuration published           config/nemesis.php
Migration published               2024_01_01_000001_create_nemesis_tokens_table.php
Migrations executed               Table nemesis_tokens created
Configuration validated           Token length: 64

============================================================
✨ Nemesis package installed successfully!

📝 Next steps:
  1. Implement MustNemesis on your models: class User extends Model implements MustNemesis
  2. Define nemesisFormat(): public function nemesisFormat(): AbstractData { return new UserData(...); }
  3. Create tokens: [$token, $plainToken] = $nemesisService->createWithPlainToken($record, $user);
  4. Protect routes: Route::middleware(["nemesis.token"])->group(...);
  5. Use NemesisHelper: NemesisHelper::getCurrentAuthenticatable()

[READY] ✅
```

### En PHP (exécution programmatique)

```php
<?php

declare(strict_types=1);

use AndyDefer\Directive\DirectiveKernel;
use AndyDefer\Directive\Enums\ExitCode;

// Installation via le kernel
$kernel = app(DirectiveKernel::class);
$exitCode = $kernel->runSignature('nemesis:install --force');

if ($exitCode === ExitCode::SUCCESS) {
    echo "✅ Nemesis installé avec succès\n";
} else {
    echo "❌ Échec de l'installation\n";
}
```

## Voir aussi

- `CleanTokensDirective` - Nettoyage des tokens expirés
- `ListTokensDirective` - Liste des tokens
- `NemesisConfigInterface` - Configuration du package
- `MustNemesis` - Interface à implémenter sur les modèles
- `NemesisHelper` - Helper pour les routes web
- `NemesisService` - Service principal de gestion des tokens
---