<?php
class ETO_Activator {
    public static function handle_activation() {
        ETO_Database::install();
        ETO_User_Roles::setup_roles();
        ETO_Cron::schedule_events();
    }
}