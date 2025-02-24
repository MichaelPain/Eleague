<?php
class ETO_Activator {
    public static function handle_activation() {
        // Verifica ordine di inclusione
        if (!class_exists('ETO_User_Roles')) {
            require_once plugin_dir_path(__FILE__) . 'class-user-roles.php';
        }
        
        ETO_User_Roles::setup_roles();
        ETO_Database::install();
        ETO_Cron::schedule_events();
    }
}