<?php
/**
 * Plugin Name: Esports Tournament Organizer
 * Description: Organizza tornei multigiocatore con sistema di ranking
 * Version: 3.0.0
 * Author: Fury Gaming
 */

if (!defined('ABSPATH')) exit;

// Include classi core PRIMA di qualsiasi hook
require_once plugin_dir_path(__FILE__) . 'admin/class-settings-register.php';
require_once plugin_dir_path(__FILE__) . 'includes/class-tournament.php';
require_once plugin_dir_path(__FILE__) . 'includes/class-team.php';
require_once plugin_dir_path(__FILE__) . 'includes/class-match.php';
require_once plugin_dir_path(__FILE__) . 'includes/class-database.php';

// Definizione costanti protette
if (!defined('ETO_DEBUG_LOG')) {
    define('ETO_DEBUG_LOG', true);
}

if (!defined('ETO_DEBUG_DISPLAY')) {
    define('ETO_DEBUG_DISPLAY', false);
}

// Definizione percorsi plugin (AGGIUNTA ETO_PLUGIN_URL)
if (!defined('ETO_PLUGIN_DIR')) {
    define('ETO_PLUGIN_DIR', plugin_dir_path(__FILE__));
}

if (!defined('ETO_PLUGIN_URL')) {
    define('ETO_PLUGIN_URL', plugin_dir_url(__FILE__));
}

// Ristorante directory e file system
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
    }
}

// Gestione errori durante la creazione del torneo
add_action('admin_notices', function() {
    if (isset($_GET['eto_error']) && current_user_can('manage_options')) {
        echo '<div class="notice notice-error">';
        echo '<p>' . esc_html(urldecode($_GET['eto_error'])) . '</p>';
        echo '<p>' . esc_html__('Controlla il log errori per maggiori dettagli.', 'eto') . '</p>';
        echo '</div>';
    }
});

// Hook system
add_action('admin_post_eto_create_tournament', ['ETO_Tournament', 'handle_tournament_creation']);
add_action('admin_post_nopriv_eto_create_tournament', function() {
    wp_die(esc_html__('Accesso non autorizzato', 'eto'), 403);
});

register_deactivation_hook(__FILE__, ['ETO_Deactivator', 'handle_deactivation']);
register_uninstall_hook(__FILE__, ['ETO_Uninstaller', 'handle_uninstall']);

// ==================================================
// INIZIALIZZAZIONE COMPONENTI
// ==================================================
add_action('plugins_loaded', function() {
    load_plugin_textdomain('eto', false, dirname(plugin_basename(__FILE__)) . '/languages/');
    
    if (version_compare(get_option('eto_db_version', '1.0.0'), ETO_DB_VERSION, '<')) {
        ETO_Database::maybe_update_db();
    }
    
    if (is_admin() && !defined('DOING_AJAX')) {
        // La classe è già stata inclusa all'inizio
        ETO_Settings_Register::init();
    }
    
    ETO_Shortcodes::init();
});

// ==================================================
// GESTIONE ERRORI E DEBUG
// ==================================================
if (defined('WP_DEBUG') && WP_DEBUG) {
    ini_set('display_errors', '0');
    ini_set('log_errors', '1');
    ini_set('error_log', ETO_DEBUG_LOG);
    
    add_action('admin_notices', function() {
        if (current_user_can('manage_options') && file_exists(ETO_DEBUG_LOG)) {
            echo '<div class="notice notice-info">';
            printf(
                esc_html__('Debug attivo. Log errori: %s', 'eto'), 
                '<code>' . esc_html(ETO_DEBUG_LOG) . '</code>'
            );
            echo '</div>';
        }
    });
}

// Avviso team massimo
add_action('admin_notices', function() {
    if (current_user_can('manage_eto_tournaments') && ETO_Tournament::count_teams() > ETO_Tournament::MAX_TEAMS) {
        echo '<div class="notice notice-warning">';
        esc_html_e('Avviso: Numero team superiore al massimo consentito!', 'eto');
        echo '</div>';
    }
});