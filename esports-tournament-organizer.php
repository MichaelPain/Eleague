<?php
/**
 * Plugin Name: eSports Tournament Organizer
 * Plugin URI: https://github.com/MichaelPain/Eleague
 * Description: Gestione avanzata tornei eSports con bracket, team e integrazioni.
 * Version: 3.1.2
 * Author: MichaelPain
 * License: GPLv3
 * Text Domain: eto
 * Domain Path: /languages
 * Requires at least: 6.4
 * Requires PHP: 7.4
 * Network: true
 */

defined('ABSPATH') || exit;

// ==================================================
// 1. DEFINIZIONE COSTANTI E PERCORSI
// ==================================================
define('ETO_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('ETO_PLUGIN_URL', plugin_dir_url(__FILE__));
define('ETO_DB_VERSION', '3.1.2');
define('ETO_TEMPLATE_DIR', ETO_PLUGIN_DIR . 'templates/');
define('ETO_DEBUG_LOG', WP_CONTENT_DIR . '/debug-eto.log');

// ==================================================
// 2. INCLUDI FILE CORE CON VERIFICA INTEGRITÀ
// ==================================================
$core_files = [
    // Core fondamentale
    'includes/class-database.php',
    'includes/class-user-roles.php',
    'includes/class-activator.php',
    'includes/class-deactivator.php',
    'includes/class-uninstaller.php',

    // Logica tornei
    'includes/class-tournament.php',
    'includes/class-team.php',
    'includes/class-match.php',
    'includes/class-swiss.php',

    // Sistema
    'includes/class-cron.php',
    'includes/class-audit-log.php',
    'includes/class-ajax-handler.php',

    // Frontend
    'public/shortcodes.php',
    'public/class-checkin.php',
];

foreach ($core_files as $file) {
    $path = ETO_PLUGIN_DIR . $file;
    if (!file_exists($path)) {
        error_log("[ETO] File mancante: $path");
        add_action('admin_notices', function() use ($file) {
            if (current_user_can('activate_plugins')) {
                echo '<div class="notice notice-error"><p>';
                printf(
                    __('Errore critico: Il file <code>%s</code> è mancante. Disinstalla e reinstalla il plugin.', 'eto'),
                    esc_html($file)
                );
                echo '</p></div>';
            }
        });
        return;
    }
    require_once $path;
}

// ==================================================
// 3. REGISTRAZIONE HOOK PRINCIPALI (REVISIONATI)
// ==================================================
register_activation_hook(__FILE__, function() {
    ETO_Activator::handle_activation();
    ETO_User_Roles::setup_roles();
});

register_deactivation_hook(__FILE__, ['ETO_Deactivator', 'handle_deactivation']);

register_uninstall_hook(__FILE__, ['ETO_Uninstaller', 'handle_uninstall']);

// ==================================================
// 4. INIZIALIZZAZIONE COMPONENTI (CORRETTA)
// ==================================================
add_action('init', function() {
    // Caricamento traduzioni (corretto timing)
    load_plugin_textdomain(
        'eto',
        false,
        dirname(plugin_basename(__FILE__)) . '/languages/'
    );

    // Caricamento condizionale admin
    if (is_admin() && !defined('DOING_AJAX')) {
        require_once ETO_PLUGIN_DIR . 'admin/admin-pages.php';
    }

    // Registrazione componenti frontend
    if (class_exists('ETO_Shortcodes')) {
        ETO_Shortcodes::init();
    }
    
    // Aggiornamento database
    if (get_option('eto_db_version') !== ETO_DB_VERSION) {
        ETO_Database::maybe_update_db();
    }
}, 5); // Priority 5 per garantire ordine corretto

// ==================================================
// 5. GESTIONE ERRORI E DEBUG (OTTIMIZZATA)
// ==================================================
if (defined('WP_DEBUG') && WP_DEBUG) {
    ini_set('display_errors', '0');
    ini_set('log_errors', '1');
    ini_set('error_log', ETO_DEBUG_LOG);
    
    add_action('admin_notices', function() {
        if (current_user_can('manage_options') && file_exists(ETO_DEBUG_LOG)) {
            echo '<div class="notice notice-info"><p>';
            printf(
                __('Debug attivo. Log degli errori disponibile in: %s', 'eto'),
                '<code>' . ETO_DEBUG_LOG . '</code>'
            );
            echo '</p></div>';
        }
    });
}

// ==================================================
// 6. SUPPORTO MULTISITO (AGGIUNTO)
// ==================================================
if (is_multisite()) {
    require_once ETO_PLUGIN_DIR . 'includes/class-multisite.php';
    ETO_Multisite::init();
}