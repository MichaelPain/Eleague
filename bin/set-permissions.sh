#!/bin/bash
WP_ROOT="/var/www/html/wp-content/plugins/esports-tournament-organizer"

# File: 644
find $WP_ROOT -type f -exec chmod 644 {} \;

# Directory: 755
find $WP_ROOT -type d -exec chmod 755 {} \;

# File sensibili
chmod 600 $WP_ROOT/includes/config.php
chmod 600 $WP_ROOT/keys/riot-api.key

echo "Permessi configurati correttamente!"