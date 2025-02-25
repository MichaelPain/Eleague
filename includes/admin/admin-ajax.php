<?php
// Gestione creazione torneo
add_action('admin_post_eto_create_tournament', function() {
    check_admin_referer('eto_create_tournament');

    if (!current_user_can('manage_tournaments')) {
        wp_die('Accesso negato');
    }

    $data = [
        'name' => sanitize_text_field($_POST['tournament_name']),
        'format' => sanitize_key($_POST['format']),
        'start_date' => sanitize_text_field($_POST['start_date']),
        'end_date' => sanitize_text_field($_POST['end_date']),
        'min_players' => absint($_POST['min_players']),
        'max_players' => absint($_POST['max_players']),
        'checkin_enabled' => isset($_POST['checkin_enabled']) ? 1 : 0
    ];

    $result = ETO_Tournament::create($data);

    if (is_wp_error($result)) {
        eto_redirect_with_message(
            admin_url('admin.php?page=eto-create-tournament'),
            'Errore: ' . $result->get_error_message(),
            'error'
        );
    }

    eto_redirect_with_message(
        admin_url('admin.php?page=eto-tournaments'),
        'Torneo creato con successo!'
    );
});

// Conferma risultato via AJAX
add_action('wp_ajax_eto_confirm_match', function() {
    $match_id = absint($_POST['match_id']);
    check_ajax_referer('confirm_match_' . $match_id, 'nonce');

    if (!current_user_can('confirm_results')) {
        wp_send_json_error('Permessi insufficienti');
    }

    $result = ETO_Match::confirm_result($match_id);

    if ($result) {
        wp_send_json_success(['message' => 'Risultato confermato']);
    } else {
        wp_send_json_error('Errore durante la conferma');
    }
});

// Eliminazione team
add_action('wp_ajax_eto_delete_team', function() {
    $team_id = absint($_POST['team_id']);
    check_ajax_referer('delete_team_' . $team_id, 'nonce');

    if (!current_user_can('delete_teams')) {
        wp_send_json_error('Accesso negato');
    }

    $result = ETO_Team::delete($team_id);
    wp_send_json($result ? ['success' => true] : ['error' => 'Eliminazione fallita']);
});

// Aggiornamento impostazioni
add_action('admin_post_eto_save_settings', function() {
    check_admin_referer('eto_save_settings');

    if (!current_user_can('manage_eto_settings')) {
        wp_die('Accesso negato');
    }

    update_option('eto_riot_api_key', sanitize_text_field($_POST['riot_api_key']));
    update_option('eto_email_enabled', isset($_POST['email_enabled']) ? 1 : 0);

    eto_redirect_with_message(
        admin_url('admin.php?page=eto-settings'),
        'Impostazioni salvate!'
    );
});
