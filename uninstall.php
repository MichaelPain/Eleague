<?php
if (!defined('WP_UNINSTALL_PLUGIN')) {
    exit;
}
if (!defined('ABSPATH')) { 
    exit;
}

global $wpdb;

// Carica le dipendenze necessarie
$plugin_path = plugin_dir_path(__FILE__);
require_once $plugin_path . 'includes/class-installer.php';
require_once $plugin_path . 'includes/class-user-roles.php';
require_once $plugin_path . 'includes/class-cron.php';

// Disabilita i vincoli di foreign key
$wpdb->query('SET FOREIGN_KEY_CHECKS = 0');

// Ordine corretto per eliminazione tabelle
$tables = [
    'eto_audit_logs',
    'eto_team_members',
    'eto_matches',
    'eto_teams',
    'eto_tournaments'
];

// Funzione per pulizia dati
$cleanup = function() use ($wpdb, $tables) {
    // Elimina tabelle
    foreach ($tables as $table) {
        $table_name = $wpdb->prefix . $table;
        $wpdb->query("DROP TABLE IF EXISTS `$table_name`");
    }

    // Elimina opzioni
    $options = [
        'eto_db_version',
        'eto_riot_api_key',
        'eto_email_enabled',
        'eto_plugin_settings',
        ETO_Installer::INSTALLER_META
    ];

    foreach ($options as $option) {
        delete_option($option);
        delete_site_option($option);
    }

    // Elimina transients
    $wpdb->query("DELETE FROM {$wpdb->options} WHERE option_name LIKE '_transient_eto_%'");
    $wpdb->query("DELETE FROM {$wpdb->options} WHERE option_name LIKE '_site_transient_eto_%'");
};

// Pulizia per multisite
if (is_multisite()) {
    $blog_ids = $wpdb->get_col("SELECT blog_id FROM {$wpdb->blogs}");
    foreach ($blog_ids as $blog_id) {
        switch_to_blog($blog_id);
        $cleanup();
        restore_current_blog();
    }
} else {
    $cleanup();
}

// Riabilita i vincoli di foreign key
$wpdb->query('SET FOREIGN_KEY_CHECKS = 1');

// Rimuovi ruoli e capability
if (class_exists('ETO_User_Roles')) {
    ETO_User_Roles::remove_roles();
}

// Rimuovi cron jobs
if (class_exists('ETO_Cron')) {
    ETO_Cron::clear_scheduled_events();
}

// Revoca privilegi installatore
if (class_exists('ETO_Installer')) {
    ETO_Installer::revoke_privileges();
}