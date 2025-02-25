<?php
if (!defined('WP_UNINSTALL_PLUGIN')) {
    exit;
}

global $wpdb;

// Disabilita i vincoli di foreign key
$wpdb->query('SET FOREIGN_KEY_CHECKS = 0');

// Ordine corretto per eliminazione tabelle (dalle dipendenze alle tabelle principali)
$tables = [
    'eto_audit_logs',
    'eto_team_members',    // Dipende da eto_teams
    'eto_matches',         // Dipende da eto_teams/tournaments
    'eto_teams',           // Dipende da eto_tournaments
    'eto_tournaments'      // Tabella principale
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