#!/bin/bash

# scripts/uninstall.sh
echo "🧹 Nettoyage du cache Laravel avant désinstallation..."

# Supprimer le provider du config/app.php
CONFIG_FILE="config/app.php"
if [ -f "$CONFIG_FILE" ]; then
    sed -i '/Kani\\Nemesis\\NemesisServiceProvider/d' "$CONFIG_FILE"
    echo "✅ Provider supprimé de config/app.php"
fi

# Supprimer TOUS les fichiers de cache bootstrap
CACHE_DIR="bootstrap/cache/"
if [ -d "$CACHE_DIR" ]; then
    find "$CACHE_DIR" -name "*.php" -type f -delete
    echo "✅ Tous les fichiers cache bootstrap supprimés"
fi

# Supprimer le cache Composer
if [ -d "vendor" ]; then
    rm -rf vendor/composer/autoload_*
    echo "✅ Cache autoload Composer supprimé"
fi

# Supprimer le fichier de config publié
if [ -f "config/nemesis.php" ]; then
    rm -f "config/nemesis.php"
    echo "✅ Fichier config/nemesis.php supprimé"
fi

# Supprimer les migrations publiées
MIGRATIONS=$(find database/migrations -name "*nemesis*" -type f 2>/dev/null)
if [ ! -z "$MIGRATIONS" ]; then
    find database/migrations -name "*nemesis*" -type f -delete
    echo "✅ Migrations Nemesis supprimées"
fi

echo "✨ Nettoyage terminé ! Le package peut être désinstallé proprement."