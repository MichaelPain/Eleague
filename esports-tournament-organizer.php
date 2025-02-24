<?php
/**
 * Plugin Name: eSports Tournament Organizer
 * Plugin URI: https://github.com/MichaelPain/Eleague
 * Description: Plugin completo per organizzare tornei eSports con gestione team, bracket, check-in e integrazione Riot Games API.
 * Version: 1.1.0
 * Author: Il Tuo Nome
 * Author URI: https://iltuositoweb.com
 * License: GNU General Public License v3.0
 * License URI: http://www.gnu.org/licenses/gpl-3.0.html
 * Text Domain: eto
 * Domain Path: /languages
 * Requires at least: 6.4
 * Requires PHP: 7.4
 * Network: true
 */

// Blocca l'accesso diretto
if (!defined('ABSPATH')) {
    exit;
}

// =====================================================================
// SEZIONE 1: DEFINIZIONE COSTANTI E CONFIGURAZIONI GLOBALI
// =====================================================================
define('ETO_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('ETO_PLUGIN_URL', plugin_dir_url(__FILE__));
define('ETO_DB_VERSION', '1.1.0');
define('ETO_RIOT_API_ENDPOINT', 'https://euw1.api.riotgames.com');
define('ETO_DEBUG', true);
define('ETO_LOG_PATH', WP_CONTENT_DIR . '/eto-debug.log');
define('ETO_MAX_TEAM_MEMBERS', 6);
define('ETO_TOURNAMENT_STATUSES', ['pending', 'active', 'completed', 'cancelled']);

// =====================================================================
// SEZIONE 2: CARICAMENTO CLASSI CORE
// =====================================================================
$required_core_files = [
    // Database e struttura dati
    'includes/class-database.php',
    'includes/class-tournament.php',
    'includes/class-team.php',
    'includes/class-match.php',
    
    // Utilità e logica di gioco
    'includes/class-swiss.php',
    'includes/class-elimination.php',
    
    // Integrazioni esterne
    'includes/class-riot-api.php',
    'includes/class-discord-integration.php',
    
    // Sistema e amministrazione
    'includes/class-user-roles.php',
    'includes/class-cron.php',
    'includes/class-emails.php',
    'includes/class-audit-log.php',
    'includes/class-multisite.php',
    
    // Frontend e UI
    'includes/class-widget-leaderboard.php',
    'includes/class-shortcodes.php'
];

foreach ($required_core_files as $file) {
    $path = ETO_PLUGIN_DIR . $file;
    if (!file_exists($path)) {
        error_log("ETO Error: Missing core file - $path");
        wp_die(sprintf(__('File core mancante: %s. Contatta l\'amministratore.', 'eto'), $file));
    }
    require_once $path;
}

// =====================================================================
// SEZIONE 3: REGISTRAZIONE HOOK PRINCIPALI
// =====================================================================
register_activation_hook(__FILE__, ['ETO_Database', 'install']);
register_deactivation_hook(__FILE__, ['ETO_Cron', 'clear_scheduled_events']);
register_uninstall_hook(__FILE__, ['ETO_Database', 'uninstall']);

// =====================================================================
// SEZIONE 4: INIZIALIZZAZIONE COMPONENTI
// =====================================================================
add_action('plugins_loaded', function() {
    // Internazionalizzazione
    load_plugin_textdomain(
        'eto', 
        false, 
        dirname(plugin_basename(__FILE__)) . '/languages/'
    );

    // Sistema di ruoli
    ETO_User_Roles::setup_roles();
    ETO_User_Roles::setup_capabilities();

    // Sistema di cron
    ETO_Cron::init_scheduled_events();

    // Integrazione multisito
    if (is_multisite()) {
        ETO_Multisite::register_network_hooks();
    }

    // Verifica conflitti
    if (defined('WP_DEBUG') && WP_DEBUG) {
        ETO_Compatibility::check_third_party_plugins();
    }
});

// =================================================
