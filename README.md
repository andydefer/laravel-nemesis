# Nemesis — API Guardian

**Nemesis** est un package Laravel de sécurité API inspiré de la déesse de la justice et de la rétribution. Son rôle est de protéger vos APIs contre les abus et les utilisations non autorisées en combinant :

* 🔑 **Gestion des tokens** associés à des domaines spécifiques.
* 🌍 **Contrôle CORS par token** (chaque token est lié à un ou plusieurs domaines).
* 📊 **Quota d’appels** avec suivi en base de données.
* 🚨 **Blocage automatique** si un token dépasse sa limite d’utilisation.

Nemesis agit comme un **gardien implacable** de vos endpoints.

---

## 🚀 Installation

Ajoutez le package à votre projet Laravel :

```bash
composer require andykani/nemesis
```

Publiez les fichiers de configuration et les migrations :

```bash
php artisan vendor:publish --provider="Nemesis\NemesisServiceProvider"
php artisan migrate
```

---

## ⚙️ Configuration

Un fichier `config/nemesis.php` sera disponible après la publication.

Exemple :

```php
return [
    'default_quota' => 1000, // nombre maximum d'appels par token
    'reset_period' => 'daily', // peut être 'daily', 'weekly', 'monthly'
    'block_response' => [
        'message' => 'Accès refusé : quota dépassé ou domaine non autorisé.',
        'status' => 429,
    ],
];
```

---

## 🗄️ Migration

La migration crée une table `nemesis_tokens` avec :

* `id`
* `token` (string unique)
* `domains` (json : liste des domaines autorisés)
* `calls_made` (integer : nombre d’appels effectués)
* `quota` (integer : limite d’appels)
* `blocked` (boolean : état du token)
* `expires_at` (datetime : expiration du token)
* timestamps

---

## 🛡️ Middleware

Ajoutez le middleware Nemesis à vos routes API :

```php
use Nemesis\Middleware\NemesisGuardian;

Route::middleware([NemesisGuardian::class])->group(function () {
    Route::get('/posts', [PostController::class, 'index']);
    Route::get('/profile', [UserController::class, 'show']);
});
```

Le middleware :

1. Vérifie que le token existe et n’est pas bloqué.
2. Vérifie que l’origine (domaine) est autorisée.
3. Incrémente le compteur d’appels.
4. Bloque la requête si la limite est atteinte.

---

## 🔧 Artisan Commands

### Créer un token :

```bash
php artisan nemesis:generate mysite.com --quota=500
```

### Réinitialiser les quotas :

```bash
php artisan nemesis:reset
```

### Bloquer un token :

```bash
php artisan nemesis:block {token}
```

### Débloquer un token :

```bash
php artisan nemesis:unblock {token}
```

---

## 📌 Exemple concret

Protégeons un endpoint `api/posts` :

```php
Route::middleware(['nemesis.guardian'])->get('/posts', [PostController::class, 'index']);
```

### Requête avec un header valide

```http
GET /api/posts HTTP/1.1
Host: api.monsite.com
Authorization: Bearer VOTRE_TOKEN
Origin: https://monsite.com
```

✅ Résultat : accès autorisé.

### Requête depuis un autre domaine

```http
GET /api/posts HTTP/1.1
Host: api.monsite.com
Authorization: Bearer VOTRE_TOKEN
Origin: https://sitepirate.com
```

❌ Résultat : `429 Accès refusé : quota dépassé ou domaine non autorisé.`

---

## 🛠️ Bonnes pratiques

* ✅ Utilisez un quota adapté à chaque client.
* 🔄 Activez un reset automatique des quotas.
* 🔐 Ne communiquez jamais vos tokens côté client sans contrôle (utilisez un proxy sécurisé si nécessaire).
* 📊 Surveillez les logs Nemesis pour détecter les abus.

---

## 🔒 Sécurité

* Les tokens sont stockés **hachés** en base de données (comme les mots de passe).
* Nemesis empêche toute utilisation d’un token depuis un domaine non autorisé.
* Les tentatives échouées sont loguées pour suivi.

---

## 👤 Auteur

Développé par **André Kani** — Inspiré de la justice implacable de **Némésis**.

---

## 📜 Licence

Ce package est distribué sous licence MIT. Vous êtes libre de l’utiliser et de le modifier.
