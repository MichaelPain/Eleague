<?php
class ETO_Activator {
    public static function handle_activation() {
        // 1. Setup ruoli e capacità
        require_once(plugin_dir_path(__FILE__) . 'class-user-roles.php');
        ETO_User_Roles::setup_roles();

        // 2. Forza l'aggiornamento delle capability per l'amministratore
        $admin_role = get_role('administrator');
        if ($admin_role) {
            foreach (ETO_User_Roles::ADMIN_CAPS as $cap) {
                if (!$admin_role->has_cap($cap)) {
                    $admin_role->add_cap($cap);
                }
            }
        }

        // 3. Setup database
        require_once(plugin_dir_path(__FILE__) . 'class-database.php');
        ETO_Database::install();

        // 4. Pianifica eventi cron
        require_once(plugin_dir_path(__FILE__) . 'class-cron.php');
        ETO_Cron::schedule_events();
    }
}