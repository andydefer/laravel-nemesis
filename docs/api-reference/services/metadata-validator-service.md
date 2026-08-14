# MetadataValidatorService - Référence Technique

## Description

Service de validation et d'assainissement des métadonnées structurées avec contraintes de sécurité.

## Hiérarchie

```
MetadataValidatorInterface
    └── MetadataValidatorService
```

## Rôle principal

Valide et assainit les métadonnées avec des contraintes de sécurité strictes :
- Taille maximale : 64KB
- Profondeur d'imbrication : 5 niveaux max
- Nombre de clés : 100 max
- Longueur des clés : 255 caractères max
- Types de clés : string ou int
- Types de valeurs : scalar, array, null

## Installation

Le service est automatiquement enregistré via le ServiceProvider.

```php
use AndyDefer\Nemesis\Contracts\Services\MetadataValidatorInterface;

$validator = app(MetadataValidatorInterface::class);
```

## API

### `validate(?array $metadata): ?array`

| Paramètre | Type | Description |
|-----------|------|-------------|
| `$metadata` | `?array` | Les métadonnées à valider |

**Retourne :** `?array` - Les métadonnées validées ou null

**Exceptions :** `MetadataValidationException` - Si la validation échoue

**Exemple :**
```php
try {
    $validated = $validator->validate($metadata);
    // Métadonnées valides
} catch (MetadataValidationException $e) {
    // Erreur de validation
}
```

### `isValid(?array $metadata): bool`

| Paramètre | Type | Description |
|-----------|------|-------------|
| `$metadata` | `?array` | Les métadonnées à vérifier |

**Retourne :** `bool` - True si les métadonnées sont valides, false sinon

**Exemple :**
```php
if ($validator->isValid($metadata)) {
    // Métadonnées valides
}
```

### `sanitize(?array $metadata): ?array`

| Paramètre | Type | Description |
|-----------|------|-------------|
| `$metadata` | `?array` | Les métadonnées à assainir |

**Retourne :** `?array` - Les métadonnées assainies ou null

**Actions :**
- Supprime les valeurs null
- Supprime les tableaux vides récursivement

**Exemple :**
```php
$clean = $validator->sanitize($metadata);
// Les valeurs null et tableaux vides sont supprimés
```

### `process(?array $metadata): ?array`

| Paramètre | Type | Description |
|-----------|------|-------------|
| `$metadata` | `?array` | Les métadonnées à traiter |

**Retourne :** `?array` - Les métadonnées validées et assainies ou null

**Exemple :**
```php
$processed = $validator->process($metadata);
// Validation + assainissement en une étape
```

### `getSize(?array $metadata): int`

| Paramètre | Type | Description |
|-----------|------|-------------|
| `$metadata` | `?array` | Les métadonnées |

**Retourne :** `int` - La taille en octets

**Exemple :**
```php
$size = $validator->getSize($metadata);
// Taille en octets
```

### `getNestingDepth(array $metadata, int $currentDepth = 1): int`

| Paramètre | Type | Description |
|-----------|------|-------------|
| `$metadata` | `array` | Les métadonnées |
| `$currentDepth` | `int` | Profondeur actuelle (interne) |

**Retourne :** `int` - La profondeur maximale d'imbrication

**Exemple :**
```php
$depth = $validator->getNestingDepth($metadata);
// Profondeur max d'imbrication
```

## Cas d'utilisation

### Cas 1 : Validation des métadonnées d'un token

```php
class NemesisService
{
    public function createWithPlainToken(NemesisTokenRecord $record, Model $tokenable): array
    {
        $validatedMetadata = $this->validateMetadata($record->metadata);
        
        // ... création du token
    }
    
    private function validateMetadata(?StrictDataObject $metadata): ?array
    {
        if ($metadata === null) {
            return null;
        }
        
        return $this->metadataValidator->process($metadata->toArray());
    }
}
```

### Cas 2 : Validation avant stockage

```php
class MetadataController
{
    public function store(Request $request)
    {
        $metadata = $request->input('metadata');
        
        if (!$this->validator->isValid($metadata)) {
            return response()->json(['error' => 'Invalid metadata'], 400);
        }
        
        $validated = $this->validator->process($metadata);
        
        // Stocker les métadonnées validées
    }
}
```

### Cas 3 : Assainissement des métadonnées

```php
class DataProcessor
{
    public function processData(array $data): array
    {
        $metadata = $data['metadata'] ?? null;
        
        // Nettoyer les données
        $cleanMetadata = $this->validator->sanitize($metadata);
        
        return [
            'data' => $data['data'],
            'metadata' => $cleanMetadata,
        ];
    }
}
```

## Flux d'exécution

### Validation
```
validate($metadata)
    ↓
Vérifier si null ou vide
    ├── Oui → Retourner null
    └── Non → Poursuivre
        ↓
Vérifier la taille totale (max 64KB)
    ├── Échec → Exception
    └── Succès → Poursuivre
        ↓
Vérifier la profondeur d'imbrication (max 5)
    ├── Échec → Exception
    └── Succès → Poursuivre
        ↓
Vérifier le nombre de clés (max 100)
    ├── Échec → Exception
    └── Succès → Poursuivre
        ↓
Valider toutes les clés et valeurs
    ├── Type de clé (string ou int)
    ├── Longueur de clé (max 255)
    └── Type de valeur (scalar, array, null)
    ↓
Retourner les métadonnées validées
```

### Assainissement
```
sanitize($metadata)
    ↓
Vérifier si null ou vide
    ├── Oui → Retourner null
    └── Non → Poursuivre
        ↓
Parcourir chaque élément
    ↓
Si valeur est null → Ignorer
    ↓
Si valeur est un tableau
    ├── Assainir récursivement
    └── Si tableau vide → Ignorer
    ↓
Conserver la valeur
    ↓
Retourner les métadonnées assainies
```

## Gestion des erreurs

| Situation | Exception | Message |
|-----------|-----------|---------|
| Taille > 64KB | `MetadataValidationException` | `Metadata size of N bytes exceeds maximum of 65536 bytes` |
| Profondeur > 5 | `MetadataValidationException` | `Metadata nesting depth of N exceeds maximum of 5 levels` |
| Clés > 100 | `MetadataValidationException` | `Metadata contains N keys which exceeds maximum of 100` |
| Clé non string/int | `MetadataValidationException` | `Metadata key must be string or integer, got X` |
| Longueur clé > 255 | `MetadataValidationException` | `Metadata key "X" length N exceeds maximum of 255 characters` |
| Type valeur invalide | `MetadataValidationException` | `Metadata value for key "X" must be scalar, array, or null, got Y` |

## Intégration

Ce service s'intègre avec :

- **NemesisService** : Pour la validation des métadonnées des tokens
- **NemesisInterface** : Interface de gestion des tokens
- **MetadataValidatorInterface** : Interface du service
- **MetadataValidationException** : Exception personnalisée

## Constantes

| Constante | Valeur | Description |
|-----------|--------|-------------|
| `MAX_METADATA_SIZE` | `65536` | 64KB |
| `MAX_NESTING_DEPTH` | `5` | 5 niveaux |
| `MAX_KEYS` | `100` | 100 clés |
| `MAX_KEY_LENGTH` | `255` | 255 caractères |

## Performance

- Validation en O(n) où n est le nombre de clés
- Assainissement en O(n) où n est le nombre d'éléments
- Calcul de taille via `json_encode()` en O(n)
- Complexité : O(n) pour toutes les opérations

## Compatibilité

| Version PHP | Support |
|-------------|---------|
| PHP 8.1+ | ✅ Complet |
| PHP 8.0 | ✅ Complet |

## Exemple complet

```php
<?php

declare(strict_types=1);

use AndyDefer\Nemesis\Contracts\Services\MetadataValidatorInterface;
use AndyDefer\Nemesis\Exceptions\MetadataValidationException;

class MetadataManager
{
    public function __construct(
        private readonly MetadataValidatorInterface $validator,
    ) {}

    public function processMetadata(array $data): array
    {
        $metadata = $data['metadata'] ?? null;
        
        // 1. Validation
        try {
            $validated = $this->validator->validate($metadata);
        } catch (MetadataValidationException $e) {
            throw new \RuntimeException('Invalid metadata: ' . $e->getMessage());
        }
        
        // 2. Assainissement
        $sanitized = $this->validator->sanitize($validated);
        
        // 3. Vérification finale
        if (!$this->validator->isValid($sanitized)) {
            throw new \RuntimeException('Metadata validation failed after sanitization');
        }
        
        return [
            'metadata' => $sanitized,
            'size' => $this->validator->getSize($sanitized),
            'depth' => $this->validator->getNestingDepth($sanitized),
        ];
    }

    public function validateUserMetadata(array $metadata): bool
    {
        // Vérification rapide
        return $this->validator->isValid($metadata);
    }
}

// Utilisation
$manager = new MetadataManager($validator);

// Métadonnées valides
$data = [
    'metadata' => [
        'user_id' => 123,
        'preferences' => [
            'theme' => 'dark',
            'language' => 'fr',
        ],
        'device' => 'mobile',
    ],
];

$result = $manager->processMetadata($data);
// $result['metadata'] est validé et assaini

// Métadonnées invalides (trop grandes)
$largeMetadata = array_fill(0, 200, 'test'); // 200 clés > 100
$data['metadata'] = $largeMetadata;

try {
    $result = $manager->processMetadata($data);
} catch (\RuntimeException $e) {
    echo "Erreur: " . $e->getMessage();
    // "Invalid metadata: Metadata contains 200 keys which exceeds maximum of 100"
}
```

## Voir aussi

- `NemesisService` - Service principal utilisant ce validateur
- `MetadataValidatorInterface` - Interface du service
- `MetadataValidationException` - Exception de validation
- `StrictDataObject` - Objet de données pour les métadonnées
---