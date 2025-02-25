<?php
class ETO_Installer {
    const INSTALLER_META = '_eto_super_installer';
    private static $caps = [
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

    public static function get_original_installer() {
        return get_site_option(self::INSTALLER_META);
    }

    public static function revoke_privileges() {
        $user_id = get_site_option(self::INSTALLER_META);
        if ($user_id) {
            $user = get_user_by('id', absint($user_id));
            foreach (self::$caps as $cap) {
                $user->remove_cap($cap);
            }
            delete_site_option(self::INSTALLER_META);
        }
    }

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
            foreach (self::$caps as $cap) {
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
            foreach (self::$caps as $cap) {
                $admin_role->remove_cap($cap);
            }
        }
    }

    public static function track_installer() {
        if (is_user_logged_in() && current_user_can('activate_plugins')) {
            $user_id = get_current_user_id();
            update_site_option(self::INSTALLER_META, $user_id);
            self::grant_privileges($user_id);
        }
    }

    private static function grant_privileges($user_id) {
        $user = get_user_by('id', absint($user_id));
        foreach (self::$caps as $cap) {
            $user->add_cap($cap);
        }
        do_action('eto_privileges_granted', $user_id);
    }
}

if (!defined('WP_UNINSTALL_PLUGIN')) {
    add_action('plugins_loaded', [ETO_Installer::class, 'init'], 9999);
}
