<?php
/**
 * Plugin Name: eSports Tournament Organizer
 * Plugin URI: https://github.com/MichaelPain/Eleague
 * Description: Gestione avanzata tornei eSports con bracket, team e integrazioni
 * Version: 3.2.1
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
// 1. DEFINIZIONE COSTANTI E PERCORSI (Aggiornata)
// ==================================================
define('ETO_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('ETO_PLUGIN_URL', plugin_dir_url(__FILE__));
define('ETO_DB_VERSION', '3.2.1');
define('ETO_TEMPLATE_DIR', ETO_PLUGIN_DIR . 'templates/');
define('ETO_DEBUG_LOG', WP_CONTENT_DIR . '/debug-eto.log');

// ==================================================
// 2. INCLUDI FILE CORE CON VERIFICA INTEGRITÀ (Migliorata)
// ==================================================
$core_files = [
    // Database e migrazioni
    'includes/class-activator.php',
    'includes/class-deactivator.php',
    'includes/class-uninstaller.php',
    'includes/class-database.php',
    'includes/class-user-roles.php',
    'includes/class-installer.php',

    // Logica core
    'includes/class-tournament.php',
    'includes/class-team.php',
    'includes/class-match.php',
    'includes/class-swiss.php',
    'includes/class-emails.php',
    'includes/class-riot-api.php',

    // Sistema
    'includes/class-cron.php',
    'includes/class-audit-log.php',
    'includes/class-ajax-handler.php',
    'includes/class-shortcodes.php',

    // Frontend
    'public/shortcodes.php',
    'public/class-checkin.php',

    // Admin
    'admin/admin-ajax.php',
    'admin/admin-pages.php',
    'admin/class-settings-register.php',
];

foreach ($core_files as $file) {
    $path = ETO_PLUGIN_DIR . $file;
    if (!file_exists($path)) {
        error_log("[ETO] File mancante: $path");
        add_action('admin_notices', function() use ($file) {
            if (current_user_can('activate_plugins')) {
                echo '<div class="notice notice-error"><p>';
                printf(
                    esc_html__('Errore critico: File %s mancante. Reinstalla il plugin.', 'eto'),
                    '<code>' . esc_html(basename($file)) . '</code>'
                );
                echo '</p></div>';
            }
        });
        return;
    }
    require_once $path;
}

// ==================================================
// 3. REGISTRAZIONE HOOK PRINCIPALI (Revisionata)
// ==================================================
register_activation_hook(__FILE__, function() {
    try {
        // 1. Setup iniziale
        ETO_User_Roles::init();
        ETO_Installer::track_installer();
        
        // 2. Installazione database
        ETO_Database::install();
        ETO_Database::maybe_update_db();
        
        // 3. Aggiornamento capacità admin
        $admin = get_role('administrator');
        foreach (ETO_User_Roles::SUPER_CAPS as $cap) {
            if (!$admin->has_cap($cap)) {
                $admin->add_cap($cap);
            }
        }

    } catch (Exception $e) {
        error_log('[ETO] Activation Error: ' . $e->getMessage());
        wp_die(
            '<h1>' . esc_html__('Errore attivazione plugin', 'eto') . '</h1>' .
            '<p>' . esc_html($e->getMessage()) . '</p>' .
            '<p>' . esc_html__('Controlla il log errori per maggiori dettagli.', 'eto') . '</p>'
        );
    }
});

register_deactivation_hook(__FILE__, ['ETO_Deactivator', 'handle_deactivation']);
register_uninstall_hook(__FILE__, ['ETO_Uninstaller', 'handle_uninstall']);

// ==================================================
// 4. INIZIALIZZAZIONE COMPONENTI (Sicurezza migliorata)
// ==================================================
add_action('plugins_loaded', function() {
    // Traduzioni
    load_plugin_textdomain(
        'eto',
        false,
        dirname(plugin_basename(__FILE__)) . '/languages/'
    );

    // Aggiornamento DB
    if (version_compare(get_option('eto_db_version', '1.0.0'), ETO_DB_VERSION, '<')) {
        ETO_Database::maybe_update_db();
    }

    // Caricamento admin
    if (is_admin() && !defined('DOING_AJAX')) {
        ETO_Settings_Register::init();
    }

    // Shortcode
    add_action('init', ['ETO_Shortcodes', 'init']);
});

// ==================================================
// 5. GESTIONE ERRORI E DEBUG (Aggiornata)
// ==================================================
if (defined('WP_DEBUG') && WP_DEBUG) {
    ini_set('display_errors', '0');
    ini_set('log_errors', '1');
    ini_set('error_log', ETO_DEBUG_LOG);
    
    add_action('admin_notices', function() {
        if (current_user_can('manage_options') && file_exists(ETO_DEBUG_LOG)) {
            echo '<div class="notice notice-info"><p>';
            printf(
                esc_html__('Debug attivo. Log errori: %s', 'eto'),
                '<code>' . esc_html(ETO_DEBUG_LOG) . '</code>'
            );
            echo '</p></div>';
        }
    });
}

// ==================================================
// 6. SUPPORTO MULTISITO (Corretta integrazione)
// ==================================================
if (is_multisite()) {
    require_once ETO_PLUGIN_DIR . 'includes/class-multisite.php';
    ETO_Multisite::init();
}
