<?php
/**
 * Plugin Name: Esports Tournament Organizer
 * Description: Organizza tornei multigiocatore con sistema di ranking
 * Version: 3.0.0
 * Author: Fury Gaming
 */

if (!defined('ABSPATH')) exit;

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
    $path = plugin_dir_path(__FILE__) . $file;
    
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
// 3. DEFINIZIONE COSTANTI
// ==================================================
if (!defined('ETO_DEBUG_LOG')) {
    define('ETO_DEBUG_LOG', true);
}

if (!defined('ETO_DEBUG_DISPLAY')) {
    define('ETO_DEBUG_DISPLAY', false);
}

if (!defined('ETO_DB_VERSION')) {
    define('ETO_DB_VERSION', '3.0.0');
}

if (!defined('ETO_PLUGIN_DIR')) {
    define('ETO_PLUGIN_DIR', plugin_dir_path(__FILE__));
}

if (!defined('ETO_PLUGIN_URL')) {
    define('ETO_PLUGIN_URL', plugin_dir_url(__FILE__));
}

// ==================================================
// 4. VERIFICA PERMESSI FILE SYSTEM
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

// ==================================================
// 5. GESTIONE ERRORI TORNEO
// ==================================================
add_action('admin_notices', function() {
    if (isset($_GET['eto_error']) && current_user_can('manage_options')) {
        echo '<div class="notice notice-error">';
        echo '<p>' . esc_html(urldecode($_GET['eto_error'])) . '</p>';
        echo '<p>' . esc_html__('Controlla il log errori per maggiori dettagli.', 'eto') . '</p>';
        echo '</div>';
    }
});

// ==================================================
// 6. HOOK SYSTEM
// ==================================================
add_action('admin_post_eto_create_tournament', ['ETO_Tournament', 'handle_tournament_creation']);
add_action('admin_post_nopriv_eto_create_tournament', function() {
    wp_die(esc_html__('Accesso non autorizzato', 'eto'), 403);
});

register_deactivation_hook(__FILE__, ['ETO_Deactivator', 'handle_deactivation']);
register_uninstall_hook(__FILE__, ['ETO_Uninstaller', 'handle_uninstall']);

// ==================================================
// 7. INIZIALIZZAZIONE COMPONENTI
// ==================================================
add_action('plugins_loaded', function() {
    load_plugin_textdomain('eto', false, dirname(plugin_basename(__FILE__)) . '/languages/');
    
    if (version_compare(get_option('eto_db_version', '1.0.0'), ETO_DB_VERSION, '<')) {
        ETO_Database::maybe_update_db();
    }
    
    if (is_admin() && !defined('DOING_AJAX')) {
        ETO_Settings_Register::init();
    }
    
    // AGGIUNTA
    require_once ETO_PLUGIN_DIR . 'includes/class-shortcodes.php';
    ETO_Shortcodes::init();
});

// ==================================================
// 8. GESTIONE ERRORI E DEBUG
// ==================================================
if (defined('WP_DEBUG') && WP_DEBUG) {
    ini_set('display_errors', ETO_DEBUG_DISPLAY ? '1' : '0');
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

// ==================================================
// 9. AVVISO TEAM MASSIMO
// ==================================================
add_action('admin_notices', function() {
    if (current_user_can('manage_eto_tournaments')) {
        $current_teams = ETO_Tournament::get_total_teams();
        
        if ($current_teams > ETO_Tournament::MAX_TEAMS) {
            echo '<div class="notice notice-warning">';
            printf(
                esc_html__('Avviso: %d team registrati (massimo consentito: %d)', 'eto'),
                $current_teams,
                ETO_Tournament::MAX_TEAMS
            );
            echo '</div>';
        }
    }
});

// ==================================================
// 10. WP-CLI INTEGRATION (CORRETTA)
// ==================================================
if (defined('WP_CLI') && WP_CLI) {
    require_once ETO_PLUGIN_DIR . 'includes/class-wp-cli.php';
}

// ==================================================
// 11. SUPPORTO MULTISITO (CORRETTA)
// ==================================================
if (is_multisite()) {
    require_once ETO_PLUGIN_DIR . 'includes/class-multisite.php';
    add_action('wpmu_new_blog', ['ETO_Multisite', 'activate_new_site']);
}