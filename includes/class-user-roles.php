<?php
class ETO_User_Roles {
    // Aggiunta costante per le capability
    const ADMIN_CAPS = [
        'manage_tournaments',
        'edit_tournaments', 
        'delete_teams',
        'confirm_results',
        'manage_eto_settings'
    ];

    public static function setup_roles() {
        self::add_roles();
        self::add_admin_capabilities(); // Metodo modificato
    }

    public static function init() {
        add_action('init', [__CLASS__, 'add_roles']);
        add_action('admin_init', [__CLASS__, 'add_admin_capabilities']); // Modificato
    }

    private static function add_roles() {
        if (!get_role('tournament_organizer')) {
            add_role(
                'tournament_organizer',
                __('Organizzatore Tornei', 'eto'),
                [
                    'read' => true,
                    'edit_posts' => true,
                    'delete_posts' => false,
                    'upload_files' => true,
                    'manage_tournaments' => true,
                    'edit_tournaments' => true
                ]
            );
        }
    }

    // Nuovo metodo dedicato per admin
    private static function add_admin_capabilities() {
        $admin_role = get_role('administrator');
        
        if ($admin_role) {
            foreach (self::ADMIN_CAPS as $cap) {
                if (!$admin_role->has_cap($cap)) {
                    $admin_role->add_cap($cap);
                }
            }
        }
    }

    // Metodo di rimozione aggiornato
    public static function remove_roles() {
        if (get_role('tournament_organizer')) {
            remove_role('tournament_organizer');
        }

        $admin_role = get_role('administrator');
        if ($admin_role) {
            foreach (self::ADMIN_CAPS as $cap) {
                $admin_role->remove_cap($cap);
            }
        }
    }
}

// Avvio condizionale per evitare conflitti
if (!defined('WP_UNINSTALL_PLUGIN')) {
    ETO_User_Roles::init();
}
