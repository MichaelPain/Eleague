<?php
class ETO_User_Roles {
    const SUPER_CAPS = [
        'manage_eto_full',
        'manage_eto_tournaments',
        'edit_eto_tournaments',
        'delete_eto_tournaments',
        'manage_eto_teams',
        'edit_eto_teams',
        'delete_eto_teams',
        'manage_eto_settings'
    ];

    const ORGANIZER_CAPS = [
        'read' => true,
        'edit_posts' => true,
        'delete_posts' => false,
        'upload_files' => true,
        'manage_eto_tournaments' => true,
        'edit_eto_tournaments' => true
    ];

    public static function init() {
        add_action('admin_init', function() {
            self::add_roles();
            self::add_admin_capabilities();
        }, 9999);
    }

    public static function add_roles() {
        if (!get_role('tournament_organizer')) {
            add_role(
                'tournament_organizer',
                __('Organizzatore Tornei', 'eto'),
                self::ORGANIZER_CAPS
            );
        }
    }

    public static function add_admin_capabilities() {
        $admin_role = get_role('administrator');
        if ($admin_role) {
            foreach (self::SUPER_CAPS as $cap) {
                if (!$admin_role->has_cap($cap)) {
                    $admin_role->add_cap($cap);
                }
            }
        }
    }

    public static function remove_roles() {
        if (get_role('tournament_organizer')) {
            remove_role('tournament_organizer');
        }

        $admin_role = get_role('administrator');
        if ($admin_role) {
            foreach (self::SUPER_CAPS as $cap) {
                $admin_role->remove_cap($cap);
            }
        }
    }
}

if (!defined('WP_UNINSTALL_PLUGIN')) {
    add_action('plugins_loaded', [ETO_User_Roles::class, 'init'], 9999);
}
