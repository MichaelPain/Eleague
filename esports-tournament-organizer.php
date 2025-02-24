<?php
/**
 * Plugin Name: eSports Tournament Organizer
 * Plugin URI: https://github.com/MichaelPain/Eleague
 * Description: Plugin completo per organizzare tornei eSports con gestione team, bracket, check-in e integrazione API.
 * Version: 2.3.0
 * Author: Il Tuo Nome
 * Author URI: https://example.com
 * License: GNU General Public License v3.0
 * License URI: http://www.gnu.org/licenses/gpl-3.0.html
 * Text Domain: eto
 * Domain Path: /languages
 * Requires at least: 6.4
 * Requires PHP: 7.4
 * Network: true
 */

defined('ABSPATH') || exit;

// ==================================================
// 1. DEFINIZIONE COSTANTI E CONFIGURAZIONI GLOBALI
// ==================================================
define('ETO_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('ETO_PLUGIN_URL', plugin_dir_url(__FILE__));
define('ETO_DB_VERSION', '2.3.0');
define('ETO_DEBUG', true);
define('ETO_LOG_PATH', WP_CONTENT_DIR . '/eto-debug.log');

// ==================================================
// 2. INCLUDI FILE CORE
// ==================================================
$core_files = [
    // Database e struttura base
    'includes/class-database.php' => 'ETO_Database',
    'includes/class-tournament.php' => 'ETO_Tournament',
    'includes/class-team.php' => 'ETO_Team',
    'includes/class-match.php' => 'ETO_Match',
    
    // Logica torneo
    'includes/class-swiss.php' => 'ETO_Swiss',
    
    // Integrazioni esterne
    'includes/class-riot-api.php' => 'ETO_Riot_API',
    'includes/class-discord-integration.php' => 'ETO_Discord_Integration',
    
    // Sistema
    'includes/class-user-roles.php' => 'ETO_User_Roles',
    'includes/class-cron.php' => 'ETO_Cron',
    'includes/class-emails.php' => 'ETO_Emails',
    'includes/class-audit-log.php' => 'ETO_Audit_Log',
    'includes/class-ajax-handler.php' => 'ETO_Ajax_Handler',
    'includes/class-shortcodes.php' => 'ETO_Shortcodes',
];

foreach ($core_files as $file => $class) {
    $path = ETO_PLUGIN_DIR . $file;
    if (!file_exists($path)) {
        error_log("ETO Error: Missing core file - $file");
        wp_die(sprintf(__('File core mancante: %s. Contatta l\'amministratore.', 'eto'), $file));
    }
    require_once $path;
    
    if (!class_exists($class)) {
        error_log("ETO Error: Class $class not found in $file");
        wp_die(sprintf(__('Classe %s non trovata. Disinstalla e reinstalla il plugin.', 'eto'), $class));
    }
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
    // Localizzazione
    load_plugin_textdomain(
        'eto',
        false,
        dirname(plugin_basename(__FILE__)) . '/languages/'
    );

    // Verifica aggiornamenti database
    ETO_Database::maybe_update_db();

    // Caricamento moduli admin
    if (is_admin()) {
        require_once ETO_PLUGIN_DIR . 'admin/admin-pages.php';
        require_once ETO_PLUGIN_DIR . 'admin/class-settings.php';
    }

    // Caricamento frontend
    add_action('init', function() {
        ETO_Shortcodes::init();
        require_once ETO_PLUGIN_DIR . 'public/class-checkin.php';
    });

    // Caricamento widget
    add_action('widgets_init', function() {
        register_widget('ETO_Leaderboard_Widget');
    });
}, 20);

// ==================================================
// 5. GESTIONE ERRORI E DEBUG
// ==================================================
if (ETO_DEBUG) {
    ini_set('display_errors', 0);
    ini_set('log_errors', 1);
    ini_set('error_log', ETO_LOG_PATH);

    add_action('admin_notices', function() {
        if (file_exists(ETO_LOG_PATH)) {
            echo '<div class="notice notice-warning"><p>';
            printf(
                __('Debug attivo. Log path: %s', 'eto'),
                '<code>' . ETO_LOG_PATH . '</code>'
            );
            echo '</p></div>';
        }
    });
}
