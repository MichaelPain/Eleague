class ETO_User_Roles {
    public function __construct() {
        add_action('init', [$this, 'add_custom_roles']);
        add_action('admin_init', [$this, 'add_capabilities_to_admin']);
    }


    // Aggiungi ruolo "Tournament Admin"
    public function add_custom_roles() {
        add_role(
            'tournament_admin',
            'Tournament Admin',
            [
                'read' => true,
                'edit_posts' => true,
                'manage_tournaments' => true,
                'delete_teams' => true
            ]
        );
    }

    // Assegna capability agli amministratori
    public function add_capabilities_to_admin() {
        $admin_role = get_role('administrator');
        if ($admin_role) {
            $admin_role->add_cap('manage_tournaments');
	    $admin_role = get_role('administrator');  
            $admin_role->add_cap('delete_teams');
        }
    }
}
new ETO_User_Roles();

function add_custom_capabilities() {
    $roles = ['tournament_admin', 'administrator'];
    
    foreach ($roles as $role) {
        $role_obj = get_role($role);
        $role_obj->add_cap('eto_manage_tournaments');
        $role_obj->add_cap('eto_edit_teams');
        // NESSUNA capability per eliminare utenti o modificare impostazioni globali
    }
}

add_user_meta($user_id, 'discord', sanitize_text_field($_POST['discord']));
add_user_meta($user_id, 'nationality', sanitize_text_field($_POST['nationality']));