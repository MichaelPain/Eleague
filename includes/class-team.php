// Salva Riot#ID cifrati nel database
public function save_riot_id($user_id, $riot_id) {
    $encrypted = openssl_encrypt(
        $riot_id, 
        'aes-256-cbc', 
        AUTH_KEY, 
        0, 
        substr(AUTH_SALT, 0, 16)
    );
    
    update_user_meta($user_id, 'encrypted_riot_id', $encrypted);
}

// Caricamento sicuro file
public function handle_screenshot_upload($file) {
    $allowed_types = ['image/jpeg', 'image/png'];
    $max_size = 2 * 1024 * 1024; // 2MB
    
    if (!in_array($file['type'], $allowed_types) || $file['size'] > $max_size) {
        return new WP_Error('invalid_file', __('File non valido', 'eto-plugin'));
    }
    
    // Sposta in directory non accessibile pubblicamente
    $upload_dir = wp_upload_dir();
    $safe_path = $upload_dir['basedir'] . '/eto_private/' . sanitize_file_name($file['name']);
    
    move_uploaded_file($file['tmp_name'], $safe_path);
}

// Solo il capitano può eliminare membri
if ($current_user->ID == $captain_id && $tournament_status != 'active') {
    $wpdb->delete($wpdb->prefix.'eto_team_members', ['id' => $member_id]);
}

public function remove_team_member($member_id) {
    global $wpdb, $current_user;

    // Ottieni ID capitano e stato torneo
    $team_id = $wpdb->get_var("SELECT team_id FROM {$wpdb->prefix}eto_team_members WHERE id = $member_id");
    $captain_id = $wpdb->get_var("SELECT captain_id FROM {$wpdb->prefix}eto_teams WHERE id = $team_id");
    $tournament_status = $wpdb->get_var("SELECT status FROM {$wpdb->prefix}eto_tournaments WHERE id = (SELECT tournament_id FROM {$wpdb->prefix}eto_teams WHERE id = $team_id)");

    // Solo il capitano può eliminare membri se il torneo non è attivo
    if ($current_user->ID == $captain_id && $tournament_status != 'active') {
        $wpdb->delete($wpdb->prefix.'eto_team_members', ['id' => $member_id]);
        return true;
    }
    return false;
}

// Esempio in class-team.php
if (!eto_team_locked($team_id)) {
    // Permetti modifiche
}

class ETO_Team {
    public function remove_member($member_id) {
        global $wpdb, $current_user;

        // Query con prepared statement
        $team_id = $wpdb->get_var(
            $wpdb->prepare("SELECT team_id FROM {$wpdb->prefix}eto_team_members WHERE id = %d", $member_id)
        );

        $captain_id = $wpdb->get_var(
            $wpdb->prepare("SELECT captain_id FROM {$wpdb->prefix}eto_teams WHERE id = %d", $team_id)
        );

        $tournament_status = $wpdb->get_var(
            $wpdb->prepare("SELECT status FROM {$wpdb->prefix}eto_tournaments WHERE id = (SELECT tournament_id FROM {$wpdb->prefix}eto_teams WHERE id = %d)", $team_id)
        );

        if ($current_user->ID == $captain_id && $tournament_status != 'active') {
            $wpdb->delete($wpdb->prefix.'eto_team_members', ['id' => $member_id]);
            return true;
        }
        return false;
    }
}