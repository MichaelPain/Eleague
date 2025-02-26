#!/bin/bash

# Ottieni il percorso del plugin dinamicamente
WP_ROOT="$(dirname "$(dirname "$(realpath "$0")")")"

# Imposta permessi per tutti i file (644)
find "$WP_ROOT" -type f -exec chmod 644 {} \;

# Imposta permessi per tutte le cartelle (755)
find "$WP_ROOT" -type d -exec chmod 755 {} \;

# Permessi speciali per file sensibili
chmod 600 "$WP_ROOT/includes/config.php" 2>/dev/null
chmod 600 "$WP_ROOT/keys/riot-api.key" 2>/dev/null

echo "Permessi configurati correttamente per: $WP_ROOT"
