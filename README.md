Voici une version mise à jour de ta documentation **Nemesis** pour prendre en compte la possibilité de passer le token soit dans l’en-tête `Authorization` soit via un paramètre query `token` :

---

# Nemesis — API Guardian

**Nemesis** est un package Laravel de sécurité API inspiré de la déesse de la justice et de la rétribution. Son rôle est de protéger vos APIs contre les abus et les utilisations non autorisées en combinant :

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

## ⚙️ Configuration

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

Ajoutez le middleware Nemesis à vos routes API :

```php
use Kani\Nemesis\Http\Middleware\NemesisMiddleware;

Route::middleware([NemesisMiddleware::class])->group(function () {
    Route::get('/posts', [PostController::class, 'index']);
    Route::get('/profile', [UserController::class, 'show']);
});
```

### Fonctionnement du middleware

1. Vérifie que le token existe et n'est pas bloqué.
2. Vérifie que l'origine (domaine) est autorisée (`allowed_origins`).
3. **Accepte le token soit via l’en-tête `Authorization: Bearer TOKEN`, soit via le paramètre query `?token=TOKEN`.**
4. Incrémente le compteur `requests_count`.
5. Bloque la requête si la limite `max_requests` est atteinte.
6. Répond avec les headers CORS appropriés.

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

## 🔧 Artisan Commands

### 1️⃣ Créer un token

```bash
php artisan nemesis:create --origins=https://mysite.com --max=500
```

**Description :** Crée un nouveau token API avec un quota maximum et des origines autorisées.

**Exemple de sortie :**

```
Nemesis token created: AbC123XyZ...
```

---

### 2️⃣ Réinitialiser tous les quotas

```bash
php artisan nemesis:reset
```

**Description :** Réinitialise `requests_count` et `last_request_at` pour tous les tokens.

**Exemple de sortie :**

```
✅ All Nemesis token quotas have been reset.
```

---

### 3️⃣ Bloquer un token

```bash
php artisan nemesis:block {token}
```

**Description :** Bloque un token en mettant `max_requests=0`.

**Exemple de sortie :**

```
✅ Token AbC123XyZ has been blocked.
```

---

### 4️⃣ Débloquer un token

```bash
php artisan nemesis:unblock {token} --max=1000
```

**Description :** Débloque un token et définit `max_requests` à la valeur souhaitée (par défaut `1000`).

**Exemple de sortie :**

```
✅ Token AbC123XyZ has been unblocked with max_requests=1000.
```

---

## 📌 Exemple concret

Protégeons un endpoint `api/posts` :

```php
Route::middleware(['nemesis.guardian'])->get('/posts', [PostController::class, 'index']);
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

---

Si tu veux, je peux aussi te mettre à jour la section **CORS et token cross-domain** avec un exemple concret pour `localhost:8000 → localhost:8001` pour que ce soit directement testable en local.

Veux que je fasse ça ?
