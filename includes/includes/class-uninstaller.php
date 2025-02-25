<?php
class ETO_Uninstaller {
    public static function handle_uninstall() {
        // 1. Rimuovi ruoli e capacità
        require_once(plugin_dir_path(__FILE__) . 'class-user-roles.php');
        ETO_User_Roles::remove_roles();

        // 2. Rimuovi dati dal database
        require_once(plugin_dir_path(__FILE__) . 'class-database.php');
        ETO_Database::uninstall();

        // 3. Rimuovi opzioni e transients
        delete_option('eto_plugin_settings');
        delete_transient('eto_leaderboard_cache');
    }
}