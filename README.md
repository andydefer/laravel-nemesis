# Nemesis — API Guardian

**Nemesis** est un package Laravel de sécurité API et son rôle est de protéger vos APIs contre les abus et les utilisations non autorisées en combinant :

* 🔑 **Gestion des tokens** associés à des domaines spécifiques.
* 🌍 **Contrôle CORS par token** (chaque token est lié à un ou plusieurs domaines).
* 📊 **Quota d'appels** avec suivi en base de données.
* 🚨 **Blocage automatique** si un token dépasse sa limite d'utilisation.

Nemesis agit comme un **gardien implacable** de vos endpoints.

---

## 🚀 Installation

Ajoutez le package à votre projet Laravel :

```bash
composer require kani/laravel-nemesis
```

Publiez les fichiers de configuration et les migrations :

```bash
php artisan vendor:publish --provider="Kani\Nemesis\NemesisServiceProvider"
php artisan migrate
```

---

## ⚙️ CONFIGURATION IMPORTANTE POUR LES PROJETS LARAVEL

### 1. Installation de l'API Laravel

**IMPERATIF** : Pour éviter les problèmes CORS (Cross-Origin) lors des appels depuis un frontend web, vous devez installer le système d'API Laravel :

```bash
php artisan install:api
```

Cette commande installe Laravel Sanctum et crée le fichier `routes/api.php` nécessaire pour les routes stateless.

### 2. Configuration des routes API

**TOUTES LES ROUTES PROTÉGÉES PAR NEMESIS DOIVENT ÊTRE DÉFINIES DANS `routes/api.php`** :

```php
// routes/api.php
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::middleware(['nemesis'])->group(function () {
    Route::get('/posts', function (Request $request) {
        return response()->json(['data' => 'Posts list']);
    });

    Route::get('/profile', function (Request $request) {
        return response()->json(['data' => 'User profile']);
    });
});
```

### 3. Configuration du middleware dans bootstrap/app.php

Ajoutez l'alias du middleware Nemesis dans votre fichier `bootstrap/app.php` :

```php
// bootstrap/app.php
use Kani\Nemesis\Http\Middleware\NemesisMiddleware;

->withMiddleware(function (Middleware $middleware) {
    $middleware->alias([
        'nemesis' => NemesisMiddleware::class,
        // autres middlewares...
    ]);
})

// Optionnel : changement du préfixe API
->withRouting(
    api: __DIR__.'/../routes/api.php',
    apiPrefix: 'api/admin', // ou conservez 'api' par défaut
    // ...
)
```

---

## ⚙️ Configuration du package

Après publication, le fichier `config/nemesis.php` est disponible :

```php
return [
    'default_max_requests' => 1000, // nombre maximum d'appels par token
    'reset_period' => 'daily',      // peut être 'daily', 'weekly', 'monthly'
    'block_response' => [
        'message' => 'Accès refusé : quota dépassé ou domaine non autorisé.',
        'status' => 429,
    ],
];
```

💡 **Astuce** : `default_max_requests` centralise les quotas par défaut, pour ne pas répéter la valeur dans toutes les commandes.

---

## 🗄️ Migration

La migration crée une table `nemesis_tokens` avec les colonnes suivantes :

* `id`
* `token` (string unique)
* `allowed_origins` (json : liste des domaines autorisés)
* `max_requests` (integer : limite d'appels)
* `requests_count` (integer : nombre d'appels effectués)
* `last_request_at` (datetime : date du dernier appel)
* `created_at`, `updated_at` (timestamps)

---

## 🛡️ Middleware

### Utilisation du middleware

Le middleware Nemesis est maintenant automatiquement enregistré par le package. Vous pouvez l'utiliser directement avec son alias `nemesis` :

```php
// routes/api.php
Route::middleware('nemesis')->group(function () {
    Route::get('/posts', [PostController::class, 'index']);
    Route::get('/profile', [UserController::class, 'show']);
});
```

### Transmission du token

Le token peut être transmis de deux manières :

#### 1. Via l'en-tête Authorization (Bearer)
```javascript
const API_TOKEN = 'crrxnjbAucrzMl8FvlRDQHwJSmvET05ncqcX3LuO';

fetch('http://localhost:8000/api/posts', {
  method: 'GET',
  headers: {
    'Authorization': `Bearer ${API_TOKEN}`,
    'Content-Type': 'application/json',
  },
})
```

#### 2. Via le paramètre de query string
```javascript
const API_TOKEN = 'crrxnjbAucrzMl8FvlRDQHwJSmvET05ncqcX3LuO';

fetch(`http://localhost:8000/api/posts?token=${API_TOKEN}`, {
  method: 'GET',
  headers: {
    'Content-Type': 'application/json',
  },
})
```

### Fonctionnement du middleware

1. Si l'origine (`Origin`) est **identique au domaine de l'application** ou absente, la requête passe **sans vérification du token**.
2. Vérifie que le **token existe** et n'est pas bloqué.
3. Vérifie que l'**origine** (domaine) est autorisée (`allowed_origins`) pour ce token.
4. **Accepte le token soit via l'en-tête `Authorization: Bearer TOKEN`, soit via le paramètre query `?token=TOKEN`.**
5. Vérifie le quota et **incrémente le compteur** `requests_count`.
6. Bloque la requête si la limite `max_requests` est atteinte.
7. Répond avec les **headers CORS appropriés**, y compris la gestion des requêtes `OPTIONS` (preflight).

💡 **Astuce** : si votre frontend est sur le même domaine que l'API, vous n'avez **pas besoin de token** pour les requêtes internes.

**Flux simplifié :**

```
┌─────────────┐
│ Requête API │
└─────┬───────┘
      │
      ▼
┌─────────────┐
│ Récupère    │
│ token       │
│ (header ou  │
│ query)      │
└─────┬───────┘
      │
      ▼
┌─────────────┐
│ Vérif token │
│ existe et   │
│ non bloqué  │
└─────┬───────┘
      │
      ▼
┌─────────────┐
│ Vérif orig. │
│ autorisée ? │
└─────┬───────┘
      │
      ▼
┌─────────────┐
│ Compteur    │
│ incrémenté  │
└─────┬───────┘
      │
      ▼
┌─────────────┐
│ Limite OK ? │
└─────┬───────┘
      │
      ▼
┌─────────────┐
│ Réponse API │
│ + CORS      │
└─────────────┘
```

---

# 🔧 Commandes Artisan Nemesis

## 📋 Liste des Commandes Disponibles

### 1️⃣ `nemesis:create` - Créer un nouveau token API
```bash
php artisan nemesis:create [--origins=*] [--max=] [--name=]
```

**Paramètres :**
- `--origins` : (Optionnel, multiple) Domaines autorisés à utiliser ce token
  - Format : `--origins=https://site1.com --origins=https://site2.com`
  - Par défaut : `['*']` (tous les domaines autorisés)
- `--max` : (Optionnel) Nombre maximum de requêtes autorisées
  - Par défaut : valeur définie dans `config/nemesis.php` (généralement 1000)
- `--name` : (Optionnel) Nom descriptif pour identifier le token

**Exemples :**
```bash
# Créer un token avec des domaines spécifiques
php artisan nemesis:create --origins=https://monsite.com --origins=https://api.monsite.com

# Créer un token avec une limite personnalisée
php artisan nemesis:create --max=5000 --origins=https://client-site.com

# Créer un token avec un nom descriptif
php artisan nemesis:create --name="Token pour application mobile"

# Créer un token avec tous les paramètres
php artisan nemesis:create --origins=https://production.com --max=10000 --name="Token production"
```

**Exemple de sortie :**
```
✅ Nemesis token created successfully!

Token: AbC123XyZdef456Uvw789Ghi012Jkl345Mno678Pqr901Stu234Vwx567Yza
Max Requests: 5000
Allowed Origins: ["https://monsite.com","https://api.monsite.com"]
Name: Token pour application mobile

⚠️  Important: Save this token securely as it cannot be retrieved later!
```

---

### 2️⃣ `nemesis:reset` - Réinitialiser les quotas d'utilisation
```bash
php artisan nemesis:reset [--token=] [--force]
```

**Paramètres :**
- `--token` : (Optionnel) Réinitialiser uniquement un token spécifique
- `--force` : (Optionnel) Forcer la réinitialisation sans confirmation

**Fonctionnement :**
- Réinitialise le compteur `requests_count` à 0
- Remet à null la date `last_request_at`
- Affecte tous les tokens si aucun token spécifique n'est précisé

**Exemples :**
```bash
# Réinitialiser tous les tokens (avec confirmation)
php artisan nemesis:reset

# Réinitialiser tous les tokens sans confirmation
php artisan nemesis:reset --force

# Réinitialiser un token spécifique
php artisan nemesis:reset --token=AbC123XyZdef456Uvw789Ghi012Jkl345Mno678Pqr901Stu234Vwx567Yza
```

**Exemple de sortie :**
```
Are you sure you want to reset all token quotas? (yes/no) [no]:
> yes

✅ Successfully reset quotas for 15 tokens.
```

---

### 3️⃣ `nemesis:block` - Bloquer un token
```bash
php artisan nemesis:block {token} [--reason=]
```

**Paramètres :**
- `token` : (Requis) Le token à bloquer
- `--reason` : (Optionnel) Raison du blocage pour documentation

**Fonctionnement :**
- Met la valeur de `max_requests` à 0
- Le token ne peut plus être utilisé pour des appels API
- Le blocage est réversible avec la commande `nemesis:unblock`

**Exemples :**
```bash
# Bloquer un token
php artisan nemesis:block AbC123XyZdef456Uvw789Ghi012Jkl345Mno678Pqr901Stu234Vwx567Yza

# Bloquer un token avec une raison
php artisan nemesis:block AbC123XyZdef456Uvw789Ghi012Jkl345Mno678Pqr901Stu234Vwx567Yza --reason="Abuse detected"
```

**Exemple de sortie :**
```
✅ Token AbC123XyZ... has been blocked successfully.
Reason: Abuse detected
```

---

### 4️⃣ `nemesis:unblock` - Débloquer un token
```bash
php artisan nemesis:unblock {token} [--max=] [--reason=]
```

**Paramètres :**
- `token` : (Requis) Le token à débloquer
- `--max` : (Optionnel) Nouvelle limite de requêtes
  - Par défaut : valeur définie dans `config/nemesis.php`
- `--reason` : (Optionnel) Raison du déblocage

**Exemples :**
```bash
# Débloquer un token avec la limite par défaut
php artisan nemesis:unblock AbC123XyZdef456Uvw789Ghi012Jkl345Mno678Pqr901Stu234Vwx567Yza

# Débloquer avec une limite personnalisée
php artisan nemesis:unblock AbC123XyZdef456Uvw789Ghi012Jkl345Mno678Pqr901Stu234Vwx567Yza --max=2000

# Débloquer avec une raison
php artisan nemesis:unblock AbC123XyZdef456Uvw789Ghi012Jkl345Mno678Pqr901Stu234Vwx567Yza --reason="Issue resolved"
```

**Exemple de sortie :**
```
✅ Token AbC123XyZ... has been unblocked successfully.
New max requests: 2000
Reason: Issue resolved
```

---

### 5️⃣ `nemesis:list` - Lister tous les tokens (Nouvelle commande)
```bash
php artisan nemesis:list [--status=]
```

**Paramètres :**
- `--status` : (Optionnel) Filtrer par status: `active`, `blocked`, `all`

**Exemples :**
```bash
# Lister tous les tokens
php artisan nemesis:list

# Lister seulement les tokens actifs
php artisan nemesis:list --status=active

# Lister seulement les tokens bloqués
php artisan nemesis:list --status=blocked
```

**Exemple de sortie :**
```
📋 Nemesis Tokens List (Showing 3 of 15 tokens)

┌──────────────┬────────────────────────────────────────────┬─────────────┬────────┬──────────────┐
│ Name         │ Token (truncated)                         │ Status      │ Usage  │ Last Used    │
├──────────────┼────────────────────────────────────────────┼─────────────┼────────┼──────────────┤
│ Mobile App   │ AbC123XyZ...                              │ ✅ Active   │ 250/1K │ 2 hours ago  │
│ Production   │ Def456Uvw...                              │ ✅ Active   │ 980/10K│ 5 minutes ago│
│ Test Client  │ Ghi012Jkl...                              │ 🚫 Blocked  │ 0/0    │ Never        │
└──────────────┴────────────────────────────────────────────┴─────────────┴────────┴──────────────┘
```

---

## 🎯 Bonnes Pratiques pour les Commandes

1. **Sécurité des Tokens** :
   - Les tokens sont affichés une seule fois à la création
   - Stockez-les dans un gestionnaire de mots de passe sécurisé
   - Utilisez des variables d'environnement en production

2. **Gestion des Quotas** :
   - Planifiez la réinitialisation régulière avec `php artisan nemesis:reset`
   - Utilisez `php artisan schedule:run` pour l'automatisation

3. **Surveillance** :
   - Utilisez régulièrement `nemesis:list` pour monitorer l'utilisation
   - Bloquez rapidement les tokens suspects

4. **Documentation** :
   - Utilisez le paramètre `--name` pour identifier clairement chaque token
   - Documentez les raisons de blocage/déblocage avec `--reason`

## ⚙️ Intégration avec la Planification Laravel

Ajoutez à votre `app/Console/Kernel.php` pour automatiser les tâches :

```php
protected function schedule(Schedule $schedule)
{
    // Réinitialiser les quotas tous les jours à minuit
    $schedule->command('nemesis:reset --force')->daily();

    // Lister l'état des tokens chaque lundi
    $schedule->command('nemesis:list --status=active')->weeklyOn(1, '8:00');
}
```

Ces commandes offrent une gestion complète de vos tokens API Nemesis, permettant un contrôle précis de l'accès et des quotas d'utilisation.

---

## 📌 Exemple concret

Protégeons un endpoint `api/posts` dans `routes/api.php` :

```php
// routes/api.php
Route::middleware(['nemesis'])->get('/posts', [PostController::class, 'index']);
```

### Requête avec token valide (header)

```http
GET /api/posts HTTP/1.1
Host: api.monsite.com
Authorization: Bearer VOTRE_TOKEN
Origin: https://monsite.com
```

✅ Résultat : accès autorisé.

### Requête avec token valide (query param)

```http
GET /api/posts?token=VOTRE_TOKEN HTTP/1.1
Host: api.monsite.com
Origin: https://monsite.com
```

✅ Résultat : accès autorisé.

### Requête depuis un autre domaine

```http
GET /api/posts?token=VOTRE_TOKEN HTTP/1.1
Host: api.monsite.com
Origin: https://sitepirate.com
```

❌ Résultat : `429 Accès refusé : quota dépassé ou domaine non autorisé.`

---

## 🛠️ Bonnes pratiques

* ✅ **IMPERATIF** : Définissez vos routes protégées dans `routes/api.php`
* ✅ Utilisez un quota adapté pour chaque client.
* 🔄 Activez un reset automatique des quotas.
* 🔐 Ne communiquez jamais vos tokens côté client sans contrôle.
* 📊 Surveillez les logs Nemesis pour détecter les abus.

---

## 🔒 Sécurité

* Les tokens sont **hachés** en base de données.
* Les tokens ne peuvent être utilisés que depuis les origines autorisées.
* Les tentatives échouées sont loguées pour suivi.

---

## 🚨 Dépannage

### Erreur CORS persistante

**Solution :** Vérifiez que :
1. Vous avez bien exécuté `php artisan install:api`
2. Vos routes sont bien définies dans `routes/api.php`
3. Le middleware est bien aliasé dans `bootstrap/app.php`

### Erreur lors de la désinstallation

```bash
Class "Kani\Nemesis\NemesisServiceProvider" not found
```

**Solution :**

```bash
# Supprimer les caches
rm -f bootstrap/cache/*.php
php artisan config:clear

# Supprimer le provider
sed -i '/Kani\\Nemesis\\NemesisServiceProvider/d' config/app.php

# Supprimer config publié
rm -f config/nemesis.php

# Vider tous les caches Laravel
php artisan optimize:clear

# Désinstaller le package
composer remove kani/laravel-nemesis
```

### Pour Windows

```cmd
del /Q bootstrap\cache\*.php
php artisan optimize:clear
composer remove kani/laravel-nemesis
```

---

## 👤 Auteur

Développé par **André Kani** — Inspiré de la justice implacable de **Némésis**.

---

## 📜 Licence

MIT. Libre d'utilisation et de modification.