<?php
class ETO_Uninstaller {
    const DB_OPTION = 'eto_db_version';
    const CLEANUP_TABLES = [
        'eto_audit_logs',
        'eto_team_members',
        'eto_matches',
        'eto_teams',
        'eto_tournaments'
    ];

    public static function handle_uninstall() {
        global $wpdb;

        if (!defined('WP_UNINSTALL_PLUGIN')) {
            return;
        }

        // 1. Disabilita i vincoli di foreign key
        $wpdb->query('SET FOREIGN_KEY_CHECKS = 0');

        // 2. Rimozione tabelle in ordine inverso alle dipendenze
        foreach (self::CLEANUP_TABLES as $table) {
            $table_name = $wpdb->prefix . $table;
            if ($wpdb->get_var($wpdb->prepare(
                "SHOW TABLES LIKE %s", 
                $wpdb->esc_like($table_name)
            )) === $table_name) {
                $wpdb->query($wpdb->prepare("DROP TABLE IF EXISTS %i", $table_name));
            }
        }

        // 3. Riabilita i vincoli
        $wpdb->query('SET FOREIGN_KEY_CHECKS = 1');

        // 4. Rimozione opzioni
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

        // 5. Rimozione ruoli e capability
        if (class_exists('ETO_User_Roles')) {
            ETO_User_Roles::remove_roles();
        }

        // 6. Pulizia transients
        $wpdb->query($wpdb->prepare(
            "DELETE FROM {$wpdb->options} WHERE option_name LIKE %s",
            $wpdb->esc_like('_transient_eto_') . '%'
        ));
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
