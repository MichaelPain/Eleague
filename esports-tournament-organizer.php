<?php
/**
 * Plugin Name: eSports Tournament Organizer
 * Plugin URI: https://github.com/TuoUtente/esports-tournament-organizer
 * Description: Plugin completo per organizzare tornei eSports di League of Legends.
 * Version: 1.1.0
 * Author: Il Tuo Nome
 * License: GPLv3
 * Text Domain: eto
 * Domain Path: /languages
 */

defined('ABSPATH') || exit;

// =============================================
// FUNZIONE DI LOGGING
// =============================================
function eto_debug_log($message) {
    $log_file = WP_CONTENT_DIR . '/debug-eto.txt';
    $timestamp = date('Y-m-d H:i:s');
    $log_message = "[{$timestamp}] {$message}" . PHP_EOL;
    file_put_contents($log_file, $log_message, FILE_APPEND | LOCK_EX);
}

// =============================================
// COSTANTI
// =============================================
eto_debug_log('Definizione costanti...');

define('ETO_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('ETO_PLUGIN_URL', plugin_dir_url(__FILE__));
define('ETO_DB_VERSION', '1.1.0');

eto_debug_log('Costanti definite: ETO_PLUGIN_DIR, ETO_PLUGIN_URL, ETO_DB_VERSION');

// =============================================
// CARICAMENTO CLASSI CORE (PRIMA DEGLI HOOK)
// =============================================
eto_debug_log('Inizio inclusione classi core...');

$core_files = [
    'includes/class-database.php',
    'includes/class-tournament.php',
    'includes/class-team.php',
    'includes/class-match.php',
    'includes/class-user-roles.php',
    'includes/utilities.php',
    'includes/class-swiss.php',
    'includes/class-uploads.php',
    'includes/class-riot-api.php',
    'includes/class-cron.php',
    'includes/class-emails.php',
    'includes/class-audit-log.php',
    'includes/class-multisite.php',
    'includes/class-widget-leaderboard.php'
];

foreach ($core_files as $file) {
    $path = ETO_PLUGIN_DIR . $file;
    if (file_exists($path)) {
        require_once $path;
        eto_debug_log("Incluso: {$file}");
    } else {
        $error = "File non trovato: {$file}";
        eto_debug_log("ERRORE: {$error}");
        wp_die($error);
    }
}

// =============================================
// REGISTRAZIONE HOOK (DOPO LE INCLUSIONI)
// =============================================
eto_debug_log('Registrazione hook...');

register_activation_hook(__FILE__, function() {
    eto_debug_log('Hook di attivazione avviato');
    try {
        ETO_Database::install();
        eto_debug_log('ETO_Database::install() completato');
    } catch (Exception $e) {
        eto_debug_log("ERRORE durante installazione: " . $e->getMessage());
        wp_die("Installazione fallita. Controlla debug-eto.txt");
    }
});

register_deactivation_hook(__FILE__, function() {
    eto_debug_log('Hook di disattivazione avviato');
    ETO_Cron::deactivate();
});

register_uninstall_hook(__FILE__, function() {
    eto_debug_log('Hook di disinstallazione avviato');
    ETO_Database::uninstall();
});

// =============================================
// CARICAMENTO MODULI AMMINISTRATIVI E FRONTEND
// =============================================
eto_debug_log('Caricamento moduli admin...');

require_once ETO_PLUGIN_DIR . 'admin/admin-pages.php';
require_once ETO_PLUGIN_DIR . 'admin/admin-ajax.php';
require_once ETO_PLUGIN_DIR . 'admin/class-settings-register.php';

eto_debug_log('Caricamento moduli frontend...');

require_once ETO_PLUGIN_DIR . 'public/shortcodes.php';
require_once ETO_PLUGIN_DIR . 'public/enqueue-scripts.php';
require_once ETO_PLUGIN_DIR . 'public/class-checkin.php';

// =============================================
// LOCALIZZAZIONE (SU INIT)
// =============================================
add_action('init', function() {
    eto_debug_log('Caricamento traduzioni...');
    load_plugin_textdomain('eto', false, dirname(plugin_basename(__FILE__)) . '/languages/');
});

// =============================================
// INIZIALIZZAZIONE COMPONENTI
// =============================================
eto_debug_log('Inizializzazione widget...');

add_action('widgets_init', function() {
    register_widget('ETO_Leaderboard_Widget');
});

if (is_multisite()) {
    eto_debug_log('Inizializzazione multisito...');
    ETO_Multisite::init();
}

eto_debug_log('Avvio cron jobs...');
ETO_Cron::init();

eto_debug_log('Inizializzazione ruoli utente...');
ETO_User_Roles::init();

eto_debug_log('Plugin attivato con successo!');
