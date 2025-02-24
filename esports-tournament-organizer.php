<?php
/**
 * Plugin Name: eSports Tournament Organizer
 * Plugin URI: https://github.com/MichaelPain/Eleague
 * Description: Gestione avanzata tornei eSports con bracket, team e integrazioni.
 * Version: 3.1.0
 * Author: MichaelPain
 * License: GPLv3
 * Text Domain: eto
 * Domain Path: /languages
 * Requires at least: 6.4
 * Requires PHP: 7.4
 */

defined('ABSPATH') || exit;

// ==================================================
// 1. DEFINIZIONE COSTANTI E PERCORSI
// ==================================================
define('ETO_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('ETO_PLUGIN_URL', plugin_dir_url(__FILE__));
define('ETO_DB_VERSION', '3.1.0');
define('ETO_TEMPLATE_DIR', ETO_PLUGIN_DIR . 'templates/');
define('ETO_DEBUG_LOG', WP_CONTENT_DIR . '/debug-eto.log');

// ==================================================
// 2. INCLUDI FILE CORE CON VERIFICA INTEGRITÀ
// ==================================================
$core_files = [
    // Database e migrazioni
    'includes/class-database.php',
    
    // Logica core
    'includes/class-tournament.php',
    'includes/class-team.php',
    'includes/class-match.php',
    'includes/class-swiss.php',
    'includes/class-emails.php',
    'includes/class-multisite.php',
    'includes/class-riot-api.php',
    'includes/class-uploads.php',
    'includes/utilities.php',
    
    // Sistema
    'includes/class-user-roles.php',
    'includes/class-cron.php',
    'includes/class-audit-log.php',
    'includes/class-ajax-handler.php',
    'includes/class-widget-leaderboard.php',
    
    // Frontend
    'public/shortcodes.php',
    'public/class-checkin.php',
    'public/enqueue-scripts.php',
    
    // Admin
    'admin/admin-pages.php',
    'admin/admin-ajax.php',
    'admin/class-settings-register.php'
];

foreach ($core_files as $file) {
    $path = ETO_PLUGIN_DIR . $file;
    if (!file_exists($path)) {
        error_log("[ETO] File mancante: $path");
        wp_die(sprintf(__('Errore critico: File %s mancante. Reinstalla il plugin.', 'eto'), basename($file)));
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
    
    if (is_multisite()) {
        require_once ETO_PLUGIN_DIR . 'includes/class-multisite.php';
        ETO_Multisite::network_activate();
    }
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

    // Caricamento admin
    if (is_admin()) {
        ETO_Settings_Register::init();
    }

    // Widget
    add_action('widgets_init', function() {
        register_widget('ETO_Leaderboard_Widget');
    });

    // Shortcode e assets frontend
    add_action('init', function() {
        ETO_Shortcodes::init();
        ETO_Enqueue_Scripts::init();
    });
});

// ==================================================
// 5. GESTIONE ERRORI E DEBUG
// ==================================================
if (defined('WP_DEBUG') && WP_DEBUG) {
    ini_set('error_log', ETO_DEBUG_LOG);
    add_action('admin_notices', function() {
        if (file_exists(ETO_DEBUG_LOG)) {
            echo '<div class="notice notice-warning"><p>';
            printf(
                __('Debug attivo. Log errori: %s', 'eto'),
                '<code>' . ETO_DEBUG_LOG . '</code>'
            );
            echo '</p></div>';
        }
    });
}

// ==================================================
// 6. REGISTRAZIONE TEMPLATE
// ==================================================
add_filter('template_include', function($template) {
    $custom_templates = [
        'tournament-view' => 'public/tournament-view.php',
        'user-profile' => 'public/user-profile.php'
    ];

    foreach ($custom_templates as $page => $file) {
        if (is_page($page)) {
            return ETO_TEMPLATE_DIR . $file;
        }
    }
    return $template;
});
