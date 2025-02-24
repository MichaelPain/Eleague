<?php
/**
 * Plugin Name: eSports Tournament Organizer
 * Plugin URI: https://github.com/TuoUtente/esports-tournament-organizer
 * Description: Plugin completo per organizzare tornei eSports di League of Legends, gestendo team, partite, bracket (eliminazione e Swiss), check-in e notifiche.
 * Version: 1.1.0
 * Author: Il Tuo Nome
 * License: GPLv3
 * Text Domain: eto
 * Domain Path: /languages
 */

defined('ABSPATH') || exit;

// Definizione delle costanti
define('ETO_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('ETO_PLUGIN_URL', plugin_dir_url(__FILE__));
define('ETO_DB_VERSION', '1.1.0');
define('ETO_RIOT_API_ENDPOINT', 'https://euw1.api.riotgames.com');

// Carica i file core
require_once ETO_PLUGIN_DIR . 'includes/class-database.php';
require_once ETO_PLUGIN_DIR . 'includes/class-tournament.php';
require_once ETO_PLUGIN_DIR . 'includes/class-team.php';
require_once ETO_PLUGIN_DIR . 'includes/class-match.php';
require_once ETO_PLUGIN_DIR . 'includes/class-user-roles.php';
require_once ETO_PLUGIN_DIR . 'includes/utilities.php';
require_once ETO_PLUGIN_DIR . 'includes/class-swiss.php';
require_once ETO_PLUGIN_DIR . 'includes/class-uploads.php';
require_once ETO_PLUGIN_DIR . 'includes/class-riot-api.php';
require_once ETO_PLUGIN_DIR . 'includes/class-cron.php';
require_once ETO_PLUGIN_DIR . 'includes/class-emails.php';
require_once ETO_PLUGIN_DIR . 'includes/class-audit-log.php';
require_once ETO_PLUGIN_DIR . 'includes/class-multisite.php';
require_once ETO_PLUGIN_DIR . 'includes/class-widget-leaderboard.php';

// Carica i file per l'area admin
require_once ETO_PLUGIN_DIR . 'admin/admin-pages.php';
require_once ETO_PLUGIN_DIR . 'admin/admin-ajax.php';
require_once ETO_PLUGIN_DIR . 'admin/class-settings-register.php';

// Carica i file per il frontend
require_once ETO_PLUGIN_DIR . 'public/shortcodes.php';
require_once ETO_PLUGIN_DIR . 'public/enqueue-scripts.php';
require_once ETO_PLUGIN_DIR . 'public/class-checkin.php';

// Localizzazione
add_action('plugins_loaded', function() {
    load_plugin_textdomain('eto', false, dirname(plugin_basename(__FILE__)) . '/languages/');
});

// Hook di attivazione, deattivazione e disinstallazione
register_activation_hook(__FILE__, ['ETO_Database', 'install']);
register_deactivation_hook(__FILE__, ['ETO_Cron', 'deactivate']);
register_uninstall_hook(__FILE__, ['ETO_Database', 'uninstall']);

// Inizializzazione del Widget (LeaderBoard)
add_action('widgets_init', function() {
    register_widget('ETO_Leaderboard_Widget');
});

// Supporto Multisito
if (is_multisite()) {
    require_once ETO_PLUGIN_DIR . 'includes/class-multisite.php';
    ETO_Multisite::init();
}

// Avvio dei Cron Jobs
ETO_Cron::init();

// Inizializzazione dei ruoli utente e capacità
ETO_User_Roles::init();
?>
