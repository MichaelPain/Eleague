<?php
/**
 * Plugin Name: eSports Tournament Organizer
 * Plugin URI: https://github.com/TuoUtente/esports-tournament-organizer
 * Description: Plugin completo per organizzare tornei eSports.
 * Version: 1.1.0
 * Author: Il Tuo Nome
 * License: GPLv3
 * Text Domain: eto
 * Domain Path: /languages
 */

defined('ABSPATH') || exit;

// =============================================
// FUNZIONE DI DEBUGGING
// =============================================
function eto_debug_log($message) {
    $log_file = WP_CONTENT_DIR . '/debug-eto.txt';
    file_put_contents($log_file, '['.date('Y-m-d H:i:s').'] '.$message.PHP_EOL, FILE_APPEND);
}

// =============================================
// COSTANTI E INCLUSIONI
// =============================================
try {
    eto_debug_log('=== INIZIO ATTIVAZIONE PLUGIN ===');

    // 1. Definizione costanti
    define('ETO_PLUGIN_DIR', plugin_dir_path(__FILE__));
    define('ETO_PLUGIN_URL', plugin_dir_url(__FILE__));
    define('ETO_DB_VERSION', '1.1.0');
    eto_debug_log('Costanti definite');

    // 2. Caricamento classi core
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
        if (!file_exists($path)) {
            throw new Exception("File mancante: $file");
        }
        require_once $path;
        eto_debug_log("Incluso: $file");
    }

    // 3. Verifica classi essenziali
    if (!class_exists('ETO_Database') || 
        !class_exists('ETO_Tournament') || 
        !class_exists('ETO_Team')) {
        throw new Exception('Classi core mancanti');
    }
    eto_debug_log('Verifica classi superata');

    // =============================================
    // REGISTRAZIONE HOOK
    // =============================================
    eto_debug_log('Registrazione hook...');
    
    // Hook di attivazione/deattivazione
    register_activation_hook(__FILE__, function() {
        eto_debug_log('Esecuzione hook di attivazione');
        ETO_Database::install();
    });
    
    register_deactivation_hook(__FILE__, function() {
        eto_debug_log('Esecuzione hook di disattivazione');
        ETO_Cron::deactivate();
    });
    
    register_uninstall_hook(__FILE__, function() {
        eto_debug_log('Esecuzione hook di disinstallazione');
        ETO_Database::uninstall();
    });

    // 4. Caricamento moduli aggiuntivi
    eto_debug_log('Caricamento moduli admin...');
    require_once ETO_PLUGIN_DIR . 'admin/admin-pages.php';
    require_once ETO_PLUGIN_DIR . 'admin/admin-ajax.php';
    require_once ETO_PLUGIN_DIR . 'admin/class-settings-register.php';

    eto_debug_log('Caricamento moduli frontend...');
    require_once ETO_PLUGIN_DIR . 'public/shortcodes.php';
    require_once ETO_PLUGIN_DIR . 'public/enqueue-scripts.php';
    require_once ETO_PLUGIN_DIR . 'public/class-checkin.php';

    // 5. Inizializzazione componenti
    eto_debug_log('Inizializzazione widget...');
    add_action('widgets_init', function() {
        if (!class_exists('ETO_Leaderboard_Widget')) {
            throw new Exception('Classe widget non trovata');
        }
        register_widget('ETO_Leaderboard_Widget');
    });

    eto_debug_log('Inizializzazione multisito...');
    if (is_multisite()) {
        ETO_Multisite::init();
    }

    eto_debug_log('Avvio cron jobs...');
    ETO_Cron::init();

    eto_debug_log('Inizializzazione ruoli...');
    ETO_User_Roles::init();

    eto_debug_log('=== ATTIVAZIONE COMPLETATA CON SUCCESSO ===');

} catch (Exception $e) {
    eto_debug_log('ERRORE FATALE: ' . $e->getMessage());
    wp_die('Errore attivazione plugin: ' . $e->getMessage());
}
