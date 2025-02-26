<?php
/*
Plugin Name: ETO - Esports Tournament Organizer
Description: Organizza tornei e competizioni gaming con vari formati
Version: 1.6.0
Author: Fury Gaming Team
Author URI: https://www.furygaming.net
Text Domain: eto
*/

if (!defined('ABSPATH')) exit;

// 1. DEFINIZIONE COSTANTI
define('ETO_DEBUG', true);
define('ETO_PLUGIN_DIR', untrailingslashit(WP_PLUGIN_DIR . '/' . dirname(plugin_basename(__FILE__))));
define('ETO_DEBUG_LOG', ETO_PLUGIN_DIR . '/logs/debug.log');

// 2. VERIFICA PERMESSI FILE SYSTEM
$required_perms = [
    'directories' => [
        ETO_PLUGIN_DIR . 'logs/' => 0755,
        ETO_PLUGIN_DIR . 'uploads/' => 0755
    ],
    'files' => [
        ETO_PLUGIN_DIR . 'includes/config.php' => 0600,
        ETO_PLUGIN_DIR . 'keys/riot-api.key' => 0600
    ]
];

foreach ($required_perms['directories'] as $path => $perm) {
    if (!is_dir($path)) {
        add_action('admin_notices', function() use ($path) {
            echo '<div class="notice notice-error">';
            echo '<p>' . sprintf(esc_html__('Directory non creata: %s', 'eto'), esc_html($path)) . '</p>';
            echo '</div>';
        });
    } else if ((fileperms($path) & 0777) !== $perm) {
        add_action('admin_notices', function() use ($path, $perm) {
            echo '<div class="notice notice-error">';
            echo '<p>' . sprintf(esc_html__('Permesso directory errato: %o per %s', 'eto'), $perm, esc_html($path)) . '</p>';
            echo '</div>';
        });
    }
}

foreach ($required_perms['files'] as $path => $perm) {
    if (!file_exists($path)) {
        add_action('admin_notices', function() use ($path) {
            echo '<div class="notice notice-error">';
            echo '<p>' . sprintf(esc_html__('File non trovato: %s', 'eto'), esc_html($path)) . '</p>';
            echo '</div>';
        });
    } else if ((fileperms($path) & 0777) !== $perm) {
        add_action('admin_notices', function() use ($path, $perm) {
            echo '<div class="notice notice-error">';
            echo '<p>' . sprintf(esc_html__('Permesso file errato: %o per %s', 'eto'), $perm, esc_html($path)) . '</p>';
            echo '</div>';
        });
    }
}

// 3. INCLUSIONI CORE CON VERIFICA
$core_files = [
    'includes/config.php',
    'includes/utilities.php',
    'admin/admin-pages.php',
    'public/shortcodes.php'
];

foreach ($core_files as $file) {
    $full_path = plugin_dir_path(__FILE__) . $file;
    if (!file_exists($full_path)) {
        add_action('admin_notices', function() use ($file) {
            echo '<div class="notice notice-error">';
            echo '<p>' . sprintf(esc_html__('File core mancante: %s', 'eto'), esc_html($file)) . '</p>';
            echo '</div>';
        });
    } else {
        require_once $full_path;
    }
}

// 4. GESTIONE ERRORI TORNEO
add_action('admin_notices', function() {
    if (isset($_GET['tournament_error'])) {
        echo '<div class="notice notice-error">';
        echo '<p>' . esc_html(__('Errore durante la creazione del torneo', 'eto')) . '</p>';
        echo '</div>';
    }
});

// 5. HOOK SYSTEM COMPLETO
add_action('init', function() {
    ETO_Activator::check_dependencies();
    ETO_Cron::schedule_events();
    ETO_Multisite::init();
    ETO_WPCLI::register_commands();
    ETO_Shortcodes::init();
    ETO_Emails::init();
});

// 6. INIZIALIZZAZIONE COMPONENTI
add_action('plugins_loaded', function() {
    // Verifica classi essenziali
    $required_classes = [
        'ETO_Tournament',
        'ETO_Swiss',
        'ETO_Team',
        'ETO_Match',
        'ETO_Emails'
    ];
    
    foreach ($required_classes as $class) {
        if (!class_exists($class)) {
            add_action('admin_notices', function() use ($class) {
                echo '<div class="notice notice-error">';
                echo '<p>' . sprintf(esc_html__('Classe mancante: %s', 'eto'), esc_html($class)) . '</p>';
                echo '</div>';
            });
        }
    }
});

// 7. DEBUG ADVANCED
if (ETO_DEBUG) {
    add_action('admin_notices', function() {
        error_log('[ETO] Debug mode enabled');
    });
}

// 8. AVVISO TEAM MASSIMO
add_action('admin_notices', function() {
    if (ETO_Tournament::get_total_teams() >= ETO_Tournament::MAX_TEAMS) {
        echo '<div class="notice notice-warning">';
        echo '<p>' . esc_html(__('Raggiunto il numero massimo di team', 'eto')) . '</p>';
        echo '</div>';
    }
});

// 9. INTEGRAZIONE WP-CLI
add_action('admin_init', function() {
    if (class_exists('WP_CLI')) {
        ETO_WPCLI::register_commands();
    }
});

// 10. SUPPORTO MULTISITO
add_action('network_admin_edit-site', function($site_id) {
    ETO_Multisite::network_site_settings($site_id);
});

add_action('admin_menu', function() {
    add_menu_page(
        'Multisite Settings',
        'Multisite',
        'manage_network',
        'eto-multisite',
        function() {
            require_once ETO_PLUGIN_DIR . '/includes/class-multisite.php';
            ETO_Multisite::admin_page();
        }
    );
});
