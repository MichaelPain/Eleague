<?php
class ETO_Uninstaller {
    const DB_OPTION = 'eto_db_version';

    public static function handle_uninstall() {
        global $wpdb;

        // 1. Revoca privilegi installatore
        ETO_Installer::revoke_privileges();

        // 2. Rimozione tabelle del plugin
        $tables = [
            'eto_tournaments',
            'eto_teams',
            'eto_team_members',
            'eto_matches',
            'eto_audit_logs'
        ];

        foreach ($tables as $table) {
            $wpdb->query("DROP TABLE IF EXISTS {$wpdb->prefix}{$table}");
        }

        // 3. Rimozione opzioni
        $options = [
            self::DB_OPTION,
            'eto_riot_api_key',
            'eto_email_settings',
            'eto_plugin_settings',
            ETO_Installer::INSTALLER_META
        ];

        foreach ($options as $option) {
            delete_option($option);
            delete_site_option($option);
        }

        // 4. Rimozione ruoli e capability
        ETO_User_Roles::remove_roles();

        // 5. Pulizia transients
        $wpdb->query("DELETE FROM {$wpdb->options} WHERE option_name LIKE '_transient_eto_%'");
    }

    // Metodo per la disinstallazione via WP-CLI
    public static function cli_uninstall() {
        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        self::handle_uninstall();
        WP_CLI::success('Plugin disinstallato e dati puliti correttamente');
    }
}

// Registrazione comando WP-CLI
if (defined('WP_CLI') && WP_CLI) {
    WP_CLI::add_command('eto uninstall', ['ETO_Uninstaller', 'cli_uninstall']);
}