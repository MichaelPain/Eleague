<?php
/**
 * Plugin Name: eSports Tournament Organizer
 * Plugin URI: https://github.com/MichaelPain/Eleague
 * Description: Plugin completo per organizzare tornei eSports con bracket, team e integrazione API.
 * Version: 2.5.0
 * Author: Il Tuo Nome
 * Author URI: https://example.com
 * License: GPLv3
 * Text Domain: eto
 * Domain Path: /languages
 * Requires at least: 6.4
 * Requires PHP: 7.4
 */

defined('ABSPATH') || exit;

// ==================================================
// 1. COSTANTI GLOBALI E CONFIGURAZIONI
// ==================================================
define('ETO_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('ETO_PLUGIN_URL', plugin_dir_url(__FILE__));
define('ETO_DB_VERSION', '2.5.0');
define('ETO_DEBUG_LOG', WP_CONTENT_DIR . '/debug-eto.log');

// ==================================================
// 2. INCLUDI FILE CORE CON CONTROLLO ERRORI
// ==================================================
$core_files = [
    'includes/class-database.php',
    'includes/class-tournament.php',
    'includes/class-team.php',
    'includes/class-match.php',
    'includes/class-swiss.php',
    'includes/class-user-roles.php',
    'includes/class-cron.php',
    'includes/class-emails.php',
    'includes/class-audit-log.php',
    'includes/class-ajax-handler.php',
    'includes/class-shortcodes.php'
];

foreach ($core_files as $file) {
    $path = ETO_PLUGIN_DIR . $file;
    if (!file_exists($path)) {
        error_log("[ETO] File core mancante: $path");
        wp_die(sprintf(__('Errore critico: File %s mancante.', 'eto'), $file));
    }
    require_once $path;
}

// ==================================================
// 3. REGISTRAZIONE HOOK PRINCIPALI
// ==================================================
register_activation_hook(__FILE__, function() {
    ETO_Database::install();
    ETO_User_Roles::setup_roles();
    ETO_Cron::schedule_events();
});

register_deactivation_hook(__FILE__, function() {
    ETO_Cron::clear_scheduled_events();
    flush_rewrite_rules();
});

register_uninstall_hook(__FILE__, function() {
    ETO_Database::uninstall();
    ETO_User_Roles::remove_roles();
});

// ==================================================
// 4. INIZIALIZZAZIONE COMPONENTI
// ==================================================
add_action('plugins_loaded', function() {
    // Traduzioni
    load_plugin_textdomain(
        'eto',
        false,
        dirname(plugin_basename(__FILE__)) . '/languages/'
    );

    // Aggiornamento database
    ETO_Database::maybe_update_db();

    // Caricamento interfaccia admin
    if (is_admin()) {
        require_once ETO_PLUGIN_DIR . 'admin/admin-pages.php';
    }

    // Shortcode e funzionalità frontend
    ETO_Shortcodes::init();
    require_once ETO_PLUGIN_DIR . 'public/class-checkin.php';
});

// ==================================================
// 5. GESTIONE ERRORI E DEBUG
// ==================================================
if (defined('WP_DEBUG') && WP_DEBUG) {
    ini_set('error_log', ETO_DEBUG_LOG);
    add_action('admin_notices', function() {
        echo '<div class="notice notice-warning"><p>';
        printf(
            __('Debug attivo. Log errori: %s', 'eto'),
            '<code>' . ETO_DEBUG_LOG . '</code>'
        );
        echo '</p></div>';
    });
}
