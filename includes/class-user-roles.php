<?php
class ETO_User_Roles {
    // Aggiungi ruoli e capacità all'attivazione
    public static function init() {
        add_action('init', [self::class, 'add_roles']);
        add_action('admin_init', [self::class, 'add_capabilities']);
    }

    // Crea ruolo 'tournament_organizer'
    public static function add_roles() {
        add_role(
            'tournament_organizer',
            'Organizzatore Tornei',
            [
                'read' => true,
                'edit_posts' => true,
                'delete_posts' => false,
                'upload_files' => true
            ]
        );
    }

    // Assegna capacità agli amministratori e organizzatori
    public static function add_capabilities() {
        $roles = ['administrator', 'tournament_organizer'];
        
        foreach ($roles as $role_name) {
            $role = get_role($role_name);
            if ($role) {
                $role->add_cap('manage_tournaments');
                $role->add_cap('edit_tournaments');
                $role->add_cap('delete_teams');
                $role->add_cap('confirm_results');
            }
        }

        // Capacità aggiuntive solo per admin
        $admin_role = get_role('administrator');
        $admin_role->add_cap('manage_eto_settings');
    }
}

// Avvia la configurazione dei ruoli
ETO_User_Roles::init();