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
if (!defined('ETO_DEBUG')) define('ETO_DEBUG', true);
if (!defined('ETO_PLUGIN_DIR')) {
    define('ETO_PLUGIN_DIR', untrailingslashit(WP_PLUGIN_DIR . '/' . dirname(plugin_basename(__FILE__))));
}
if (!defined('ETO_DEBUG_LOG')) {
    define('ETO_DEBUG_LOG', ETO_PLUGIN_DIR . '/logs/debug.log');
}

// 2. VERIFICA PERMESSI FILE SYSTEM
$required_perms = [
    'directories' => [
        ETO_PLUGIN_DIR . '/logs/' => 0750,  // -rwxr-xr-x
        ETO_PLUGIN_DIR . '/uploads/' => 0750
    ],
    'files' => [
        ETO_PLUGIN_DIR . '/includes/config.php' => 0600, // -rw-------
        ETO_PLUGIN_DIR . '/keys/riot-api.key' => 0600    // -rw-------
    ]
];

foreach ($required_perms['directories'] as $path => $expected_perm) {
    if (!is_dir($path)) {
        add_action('admin_notices', function() use ($path) {
            echo '<div class="notice notice-error"><p>' 
                . sprintf(esc_html__('ERRORE: Directory non creata: %s', 'eto'), esc_html($path)) 
                . '</p></div>';
        });
    } else {
        $current_perm = fileperms($path) & 0777;
        if ($current_perm !== $expected_perm) {
            add_action('admin_notices', function() use ($path, $current_perm, $expected_perm) {
                echo '<div class="notice notice-error"><p>' 
                    . sprintf(esc_html__('ERRORE: Permessi directory %s: %o (richiesti: %o)', 'eto'), 
                        esc_html($path), 
                        $current_perm, 
                        $expected_perm
                    )
                    . '</p></div>';
            });
        }
    }
}

foreach ($required_perms['files'] as $file => $expected_perm) {
    if (!file_exists($file)) {
        add_action('admin_notices', function() use ($file) {
            echo '<div class="notice notice-error"><p>' 
                . sprintf(esc_html__('ERRORE: File mancante: %s', 'eto'), esc_html($file)) 
                . '</p></div>';
        });
    } else {
        $current_perm = fileperms($file) & 0777;
        if ($current_perm !== $expected_perm) {
            add_action('admin_notices', function() use ($file, $current_perm, $expected_perm) {
                echo '<div class="notice notice-error"><p>' 
                    . sprintf(esc_html__('ERRORE: Permessi file %s: %o (richiesti: %o)', 'eto'), 
                        esc_html($file), 
                        $current_perm, 
                        $expected_perm
                    )
                    . '</p></div>';
            });
        }
    }
}


// 3. INCLUSIONI CORE CON VERIFICA
$core_files = [
    'includes/config.php',
    'includes/utilities.php',
    'admin/class-settings-register.php',
    'admin/admin-pages.php',
    'public/shortcodes.php',
    'public/class-checkin.php',
    'public/displays.php'
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

spl_autoload_register(function($class) {
    $prefix = 'ETO_';
    
    if (strpos($class, $prefix) === 0) {
        $class_name = str_replace($prefix, '', $class);
        $file_name = 'class-' . strtolower(str_replace('_', '-', $class_name)) . '.php';
        $file_path = ETO_PLUGIN_DIR . '/includes/' . $file_name;
        
        // Debugging avanzato
        if (defined('ETO_DEBUG') && ETO_DEBUG) {
            error_log("[ETO] Tentativo di caricare: {$file_path}");
        }
        
        if (file_exists($file_path)) {
            require_once $file_path;
        } else {
            if (defined('ETO_DEBUG') && ETO_DEBUG) {
                error_log("[ETO] File mancante per la classe {$class}: {$file_path}");
                add_action('admin_notices', function() use ($class) {
                    echo '<div class="notice notice-error"><p>' 
                        . sprintf(esc_html__('ERRORE: File mancante per la classe %s', 'eto'), esc_html($class)) 
                        . '</p></div>';
                });
            }
        }
    }
});

// 4. GESTIONE ERRORI TORNEO
add_action('admin_notices', function() {
    // Notifica per errori generici
    if ($error = get_transient('eto_max_teams_error')) {
        echo '<div class="notice notice-error"><p>' 
            . esc_html($error) 
            . '</p></div>';
        delete_transient('eto_max_teams_error');
    }
    
    // Gestione centralizzata degli errori
    $error_codes = ['nonce_error', 'permission_error', 'creation_error'];
    foreach ($error_codes as $code) {
        if (isset($_GET[$code])) {
            echo '<div class="notice notice-error"><p>';
            switch ($code) {
                case 'nonce_error':
                    echo esc_html__('Verifica di sicurezza fallita.', 'eto');
                    break;
                case 'permission_error':
                    echo esc_html__('Permessi insufficienti.', 'eto');
                    break;
                case 'creation_error':
                    $form_data = get_transient('eto_form_data');
                    echo '<strong>' . esc_html__('Dati non validi:', 'eto') . '</strong><pre>' 
                        . esc_html(print_r($form_data, true)) 
                        . '</pre>';
                    delete_transient('eto_form_data');
                    break;
            }
            echo '</p></div>';
        }
    }
    
    // Notifica di successo
    if (isset($_GET['created'])) {
        echo '<div class="notice notice-success"><p>' 
            . esc_html__('Torneo creato con successo!', 'eto') 
            . '</p></div>';
    }
});

// 5. HOOK SYSTEM COMPLETO
add_action('plugins_loaded', function() {
    // Caricamento traduzioni
    load_plugin_textdomain('eto', false, basename(ETO_PLUGIN_DIR) . '/languages');    
    if (class_exists('ETO_Activator')) {
        ETO_Activator::check_dependencies();
    }
    if (class_exists('ETO_Shortcodes')) {
        ETO_Shortcodes::init();
    }
    if (class_exists('ETO_Cron')) {
         ETO_Cron::schedule_events();
    }
    if (class_exists('ETO_Multisite')) {
         ETO_Multisite::init();
    }
    if (class_exists('ETO_WPCLI')) {
         ETO_WPCLI::register_commands();
    }
    if (class_exists('ETO_Emails')) {
         ETO_Emails::init();
    }
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

// SHORTCODE:
add_shortcode('eto_tournaments', function() {
    $template_path = ETO_PLUGIN_DIR . '/public/shortcodes.php';
    if (!file_exists($template_path)) {
        return '<div class="error">' . esc_html__('Errore: Template non trovato', 'eto') . '</div>';
    }
    ob_start();
    include $template_path;
    return ob_get_clean();
});