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
// 1. DEFINIZIONE COSTANTI E PERCORSI
// ==================================================
define('ETO_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('ETO_PLUGIN_URL', plugin_dir_url(__FILE__));
define('ETO_DB_VERSION', '3.2.1');
define('ETO_TEMPLATE_DIR', ETO_PLUGIN_DIR . 'templates/');

if (!defined('ETO_DEBUG_LOG')) {
    define('ETO_DEBUG_LOG', WP_CONTENT_DIR . '/debug-eto.log');
}

// ==================================================
// 2. GESTIONE PERMESSI
// ==================================================
register_activation_hook(__FILE__, 'eto_set_permissions_on_activation');
add_action('admin_init', 'eto_verify_permissions');

function eto_set_permissions_on_activation() {
    $directories = [
        ETO_PLUGIN_DIR . 'logs/',
        ETO_PLUGIN_DIR . 'uploads/',
        ETO_PLUGIN_DIR . 'keys/'
    ];
    
    foreach ($directories as $dir) {
        if (!file_exists($dir)) {
            wp_mkdir_p($dir);
        }
        @chmod($dir, 0755);
    }

    $files = [
        ETO_PLUGIN_DIR . 'includes/config.php' => 0600,
        ETO_PLUGIN_DIR . 'keys/riot-api.key' => 0600
    ];

    foreach ($files as $file => $perms) {
        if (file_exists($file)) {
            @chmod($file, $perms);
        }
    }
}

function eto_verify_permissions() {
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
                    echo '<div class="notice notice-error"><p>';
                    printf(
                        esc_html__('Permessi directory non corretti: %s (Attuali: %o, Richiesti: %o)', 'eto'),
                        esc_html($path),
                        $current,
                        $expected
                    );
                    echo '</p></div>';
                });
            }
        }
    }

    foreach ($required['files'] as $path => $expected) {
        if (file_exists($path)) {
            $current = fileperms($path) & 0777;
            if ($current !== $expected) {
                add_action('admin_notices', function() use ($path, $expected, $current) {
                    echo '<div class="notice notice-error"><p>';
                    printf(
                        esc_html__('Permessi file non corretti: %s (Attuali: %o, Richiesti: %o)', 'eto'),
                        esc_html($path),
                        $current,
                        $expected
                    );
                    echo '</p></div>';
                });
            }
        }
    }
}

// ==================================================
// 3. INCLUDI FILE CORE
// ==================================================
$core_files = [
    // Database e migrazioni
    'includes/class-database.php',
    'includes/class-user-roles.php',
    'includes/class-installer.php',
    'includes/class-activator.php',
    'includes/class-deactivator.php',
    'includes/class-uninstaller.php',
    
    // Logica core
    'includes/class-tournament.php', 
    'includes/class-team.php',
    'includes/class-match.php',
    'includes/class-swiss.php',
    'includes/class-emails.php',
    'includes/class-shortcodes.php',
    
    // Sistema
    'includes/class-cron.php',
    'includes/class-audit-log.php',
    'includes/class-ajax-handler.php',
    
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
// 4. REGISTRAZIONE HOOK PRINCIPALI
// ==================================================
register_activation_hook(__FILE__, function() {
    try {
        ETO_User_Roles::init();
        ETO_Installer::track_installer();
        ETO_Database::install();
        ETO_Database::maybe_update_db();
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
// 5. INIZIALIZZAZIONE COMPONENTI
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
    ETO_Shortcodes::init();
});

// ==================================================
// 6. GESTIONE ERRORI E DEBUG
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

// Aggiunta codice per avviso massimo team
add_action('admin_notices', function() {
    if (isset($_GET['max_teams_exceeded'])) {
        echo '<div class="notice notice-warning"><p>';
        esc_html_e('Avviso: Numero team superiore al massimo consentito', 'eto');
        echo '</p></div>';
    }
});

// ==================================================
// 7. INTEGRAZIONE WP-CLI
// ==================================================
if (defined('WP_CLI') && WP_CLI) {
    WP_CLI::add_command('eto permissions', function($args) {
        $script_path = ETO_PLUGIN_DIR . 'bin/set-permissions.sh';
        if (file_exists($script_path)) {
            system("bash {$script_path}", $return_val);
            if ($return_val === 0) {
                WP_CLI::success('Permessi configurati correttamente');
            } else {
                WP_CLI::error('Errore durante l\'esecuzione dello script');
            }
        } else {
            WP_CLI::error('Script per i permessi non trovato');
        }
    });
}

// ==================================================
// 8. SUPPORTO MULTISITO
// ==================================================
if (is_multisite()) {
    require_once ETO_PLUGIN_DIR . 'includes/class-multisite.php';
    ETO_Multisite::init();
}

// ==================================================
// 9. FILTRO REGISTRAZIONE TEAM
// ==================================================
add_filter('eto_can_register_team', function($can_register, $tournament_id) {
    $tournament = ETO_Tournament::get($tournament_id);
    $current = ETO_Team::count($tournament_id);
    return $current < $tournament->max_teams;
}, 10, 2);
