<?php
// Verifica che lo script sia chiamato correttamente durante la disinstallazione del plugin
if (!defined('WP_UNINSTALL_PLUGIN')) {
    exit;
}

global $wpdb;

// Elenco delle tabelle da eliminare
$tables = array(
    'eto_tournaments',
    'eto_teams',
    'eto_team_members',
    'eto_matches',
    'eto_audit_logs'
);

// Elimina le tabelle del plugin
foreach ($tables as $table) {
    $wpdb->query("DROP TABLE IF EXISTS {$wpdb->prefix}$table");
}

// Elimina le opzioni salvate dal plugin
delete_option('eto_db_version');
delete_option('eto_riot_api_key');
delete_option('eto_email_enabled');

// Se il plugin è stato usato in un ambiente multisito, elimina i dati da tutti i siti
if (is_multisite()) {
    $blog_ids = $wpdb->get_col("SELECT blog_id FROM {$wpdb->blogs}");
    foreach ($blog_ids as $blog_id) {
        switch_to_blog($blog_id);
        foreach ($tables as $table) {
            $wpdb->query("DROP TABLE IF EXISTS {$wpdb->prefix}$table");
        }
        delete_option('eto_db_version');
        delete_option('eto_riot_api_key');
        delete_option('eto_email_enabled');
        restore_current_blog();
    }
}
?>
