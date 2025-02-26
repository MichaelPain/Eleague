<?php
/**
 * Plugin Name: Esports Tournament Organizer
 * Description: Organizza tornei multigiocatore con sistema di ranking
 * Version: 3.0.0
 * Author: Fury Gaming
 */

if (!defined('ABSPATH')) exit;

// ==================================================
// 1. DEFINIZIONE COSTANTI (PRIMA DI QUALSIASI INCLUSIONE)
// ==================================================
if (!defined('ETO_PLUGIN_DIR')) {
    define('ETO_PLUGIN_DIR', plugin_dir_path(__FILE__));
}

if (!defined('ETO_PLUGIN_URL')) {
    define('ETO_PLUGIN_URL', plugin_dir_url(__FILE__));
}

if (!defined('ETO_DEBUG_LOG')) {
    define('ETO_DEBUG_LOG', WP_CONTENT_DIR . '/debug-eto.log');
}

if (!defined('ETO_DEBUG_DISPLAY')) {
    define('ETO_DEBUG_DISPLAY', false);
}

if (!defined('ETO_DB_VERSION')) {
    define('ETO_DB_VERSION', '3.0.0');
}

// ==================================================
// 2. INCLUDI FILE CORE CON VERIFICA INTEGRITÀ
// ==================================================
$core_files = [
    // Database e migrazioni
    'includes/class-database.php',
    
    // Sistema di base
    'includes/class-user-roles.php',
    'includes/class-activator.php',
    'includes/class-deactivator.php',
    'includes/class-uninstaller.php',
    
    // Logica core
    'includes/class-tournament.php',
    'includes/class-team.php',
    'includes/class-match.php',
    'includes/class-swiss.php',
    
    // Sistema
    'includes/class-cron.php',
    'includes/class-audit-log.php',
    'includes/class-ajax-handler.php',
    
    // Integrazioni
    'includes/class-riot-api.php',
    'includes/class-emails.php',
    
    // Frontend
    'public/shortcodes.php',
    'public/class-checkin.php',
    
    // Admin
    'admin/admin-pages.php',
    'admin/class-settings-register.php'
];

foreach ($core_files as $file) {
    $path = ETO_PLUGIN_DIR . $file;
    
    if (!file_exists($path)) {
        add_action('admin_notices', function() use ($file) {
            echo '<div class="error notice">';
            printf(
                esc_html__('File core mancante: %s. Reinstalla il plugin.', 'eto'), 
                '<code>' . esc_html($file) . '</code>'
            );
            echo '</div>';
        });
        return;
    }
    
    require_once $path;
}

// ==================================================
// 3. VERIFICA PERMESSI FILE SYSTEM
// ==================================================
$required = [
    'directories' => [
        ETO_PLUGIN_DIR . 'logs/' => 0755,
        ETO_PLUGIN_DIR . 'uploads/' => 0755
    ],
    'files' => [
        ETO_PLUGIN_DIR . 'includes/config.php' => 0600,
        ETO_PLUGIN_DIR . 'keys/riot-api.key' => 0600
    ]
];

foreach ($required['directories'] as $path => $expected) {
    if (file_exists($path)) {
        $current = fileperms($path) & 0777;
        if ($current !== $expected) {
            add_action('admin_notices', function() use ($path, $expected, $current) {
                echo '<div class="error notice">';
                printf(
                    esc_html__('Permessi directory non corretti: %s (Attuali: %o, Richiesti: %o)', 'eto'), 
                    esc_html($path), 
                    $current, 
                    $expected
                );
                echo '</div>';
            });
        }
    } else {
        wp_mkdir_p($path);
        chmod($path, $expected);
    }
}

foreach ($required['files'] as $file => $expected) {
    if (!file_exists($file)) {
        add_action('admin_notices', function() use ($file) {
            echo '<div class="error notice">';
            printf(
                esc_html__('Errore critico: File %s mancante. Reinstalla il plugin.', 'eto'), 
                '<code>' . esc_html(basename($file)) . '</code>'
            );
            echo '</div>';
        });
    } elseif (fileperms($file) !== $expected) {
        chmod($file, $expected);
    }
}

// ==================================================
// 4. GESTIONE ERRORI TORNEO
// ==================================================
add_action('admin_notices', function() {
    if (isset($_GET['eto_error']) && current_user_can('manage_options')) {
        echo '<div class="notice notice-error">';
        echo '<p>' . esc_html(urldecode(sanitize_text_field($_GET['eto_error']))) . '</p>';
        echo '<p>' . esc_html__('Controlla il log errori per maggiori dettagli.', 'eto') . '</p>';
        echo '</div>';
    }
});

// ==================================================
// 5. HOOK SYSTEM (COMPLETA)
// ==================================================
add_action('admin_post_eto_create_tournament', ['ETO_Tournament', 'handle_tournament_creation']);
add_action('admin_post_nopriv_eto_create_tournament', function() {
    wp_die(esc_html__('Accesso non autorizzato', 'eto'), 403);
});

register_activation_hook(__FILE__, ['ETO_Activator', 'handle_activation']);
register_deactivation_hook(__FILE__, ['ETO_Deactivator', 'handle_deactivation']);
register_uninstall_hook(__FILE__, ['ETO_Uninstaller', 'handle_uninstall']);

// ==================================================
// 6. INIZIALIZZAZIONE COMPONENTI (DETTAGLIATA)
// ==================================================
add_action('plugins_loaded', function() {
    load_plugin_textdomain('eto', false, dirname(plugin_basename(__FILE__)) . '/languages/');
    
    // Aggiornamento database
    if (version_compare(get_option('eto_db_version', '1.0.0'), ETO_DB_VERSION, '<')) {
        ETO_Database::maybe_update_db();
    }
    
    // Inizializzazione componenti
    ETO_Settings_Register::init();
    ETO_Shortcodes::init();
    ETO_Cron::schedule_events();
    
    // Supporto multisito
    if (is_multisite()) {
        ETO_Multisite::init();
    }
});

// ==================================================
// 7. GESTIONE DEBUG AVANZATA
// ==================================================
if (defined('WP_DEBUG') && WP_DEBUG) {
    ini_set('display_errors', ETO_DEBUG_DISPLAY ? '1' : '0');
    ini_set('log_errors', '1');
    ini_set('error_log', ETO_DEBUG_LOG);
    
    add_action('admin_notices', function() {
        if (current_user_can('manage_options')) {
            echo '<div class="notice notice-info">';
            printf(
                esc_html__('Debug attivo. Log errori: %s', 'eto'), 
                '<code>' . esc_html(ETO_DEBUG_LOG) . '</code>'
            );
            echo '</div>';
        }
    });
}

// ==================================================
// 8. AVVISO TEAM MASSIMO (REVISIONATO)
// ==================================================
add_action('admin_notices', function() {
    if (current_user_can('manage_eto_tournaments')) {
        $current_teams = ETO_Tournament::get_total_teams();
        $max_teams = ETO_Tournament::MAX_TEAMS;
        
        if ($current_teams > $max_teams) {
            echo '<div class="notice notice-warning">';
            printf(
                esc_html__('Avviso: Numero team superiore al massimo consentito! (%d/%d)', 'eto'),
                $current_teams,
                $max_teams
            );
            echo '</div>';
        }
    }
});

// ==================================================
// 9. INTEGRAZIONE WP-CLI
// ==================================================
if (defined('WP_CLI') && WP_CLI) {
    require_once ETO_PLUGIN_DIR . 'includes/class-wp-cli.php';
    WP_CLI::add_command('eto', 'ETO_WPCLI');
}

// ==================================================
// 10. SUPPORTO MULTISITO (COMPLETO)
// ==================================================
if (is_multisite()) {
    add_action('wpmu_new_blog', ['ETO_Multisite', 'activate_new_site']);
    add_filter('network_admin_plugin_action_links', ['ETO_Multisite', 'network_plugin_links']);
}
