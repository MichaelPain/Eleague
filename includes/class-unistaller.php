<?php
class ETO_Uninstaller {
    public static function handle_uninstall() {
        ETO_Database::uninstall();
        ETO_User_Roles::remove_roles();
        
        // Pulizia aggiuntiva se necessaria
        delete_option('eto_plugin_settings');
    }
}