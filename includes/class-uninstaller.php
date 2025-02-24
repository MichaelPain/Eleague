<?php
class ETO_Uninstaller {
    public static function handle_uninstall() {
        // Carica le dipendenze necessarie
        if (!class_exists('ETO_User_Roles') || !class_exists('ETO_Database')) {
            require_once plugin_dir_path(__FILE__) . 'class-user-roles.php';
            require_once plugin_dir_path(__FILE__) . 'class-database.php';
        }

        ETO_Database::uninstall();
        ETO_User_Roles::remove_roles();
        delete_option('eto_plugin_settings');
    }
}