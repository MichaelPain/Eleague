<?php
if (!defined('ABSPATH')) exit;

// Verifica nonce globale per tutte le azioni
check_ajax_referer('eto_global_nonce', 'nonce');

// Creazione torneo
add_action('wp_ajax_eto_create_tournament', function() {
    $data = [
        'tournament_name' => sanitize_text_field($_POST['tournament_name']),
        'format' => sanitize_key($_POST['format']),
        'start_date' => sanitize_text_field($_POST['start_date']),
        'end_date' => sanitize_text_field($_POST['end_date']),
        'min_players' => absint($_POST['min_players']),
        'max_players' => absint($_POST['max_players']),
        'checkin_enabled' => isset($_POST['checkin_enabled']) ? 1 : 0,
        '_wpnonce' => sanitize_text_field($_POST['nonce'])
    ];

    $result = ETO_Tournament::create($data);

    if (is_wp_error($result)) {
        eto_redirect_with_message(
            admin_url('admin.php?page=eto-create-tournament'),
            'Errore: ' . esc_html($result->get_error_message()),
            'error'
        );
        exit;
    }

    eto_redirect_with_message(
        admin_url('admin.php?page=eto-tournaments'),
        esc_html__('Torneo creato con successo!', 'eto')
    );
    exit;
});

// Conferma risultato via AJAX
add_action('wp_ajax_eto_confirm_match', function() {
    $match_id = absint($_POST['match_id']);
    check_ajax_referer('eto_global_nonce', 'nonce');
    
    if (!current_user_can('confirm_results')) {
        wp_send_json_error(
            ['message' => esc_html__('Permessi insufficienti', 'eto')],
            403
        );
    }

    $result = ETO_Match::confirm_result($match_id);
    
    if ($result) {
        wp_send_json_success(['message' => esc_html__('Risultato confermato', 'eto')]);
    } else {
        wp_send_json_error(
            ['message' => esc_html__('Errore durante la conferma', 'eto')],
            500
        );
    }
});

// Eliminazione team
add_action('wp_ajax_eto_delete_team', function() {
    $team_id = absint($_POST['team_id']);
    check_ajax_referer('eto_global_nonce', 'nonce');
    
    if (!current_user_can('delete_teams')) {
        wp_send_json_error(
            ['message' => esc_html__('Accesso negato', 'eto')],
            403
        );
    }

    $result = ETO_Team::delete($team_id);
    
    if ($result) {
        wp_send_json_success(['message' => esc_html__('Team eliminato', 'eto')]);
    } else {
        wp_send_json_error(
            ['message' => esc_html__('Eliminazione fallita', 'eto')],
            500
        );
    }
});

// Aggiornamento impostazioni
add_action('admin_post_eto_save_settings', function() {
    check_admin_referer('eto_save_settings', '_wpnonce');
    
    if (!current_user_can('manage_eto_settings')) {
        wp_die(esc_html__('Accesso negato', 'eto'));
    }

    // Crittografia chiave API
    $api_key = sanitize_text_field($_POST['riot_api_key']);
    update_option('eto_riot_api_key', base64_encode($api_key));
    
    update_option('eto_email_enabled', isset($_POST['email_enabled']) ? 1 : 0);

    eto_redirect_with_message(
        admin_url('admin.php?page=eto-settings'),
        esc_html__('Impostazioni salvate!', 'eto')
    );
    exit;
});
