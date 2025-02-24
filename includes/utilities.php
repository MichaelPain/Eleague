<?php
// Funzioni di utilità globali per il plugin

// Verifica se un team è bloccato (in un torneo attivo)
function eto_team_locked($team_id) {
    global $wpdb;
    return $wpdb->get_var(
        $wpdb->prepare(
            "SELECT status FROM {$wpdb->prefix}eto_tournaments 
            WHERE id = (SELECT tournament_id FROM {$wpdb->prefix}eto_teams WHERE id = %d)",
            $team_id
        )
    ) === 'active';
}

// Verifica validità Riot#ID
function eto_validate_riot_id($riot_id) {
    return preg_match('/^[a-zA-Z0-9]+#[a-zA-Z0-9]+$/', $riot_id);
}

// Ottieni i tornei di un utente
function eto_get_user_tournaments($user_id) {
    global $wpdb;
    return $wpdb->get_results(
        $wpdb->prepare(
            "SELECT t.* FROM {$wpdb->prefix}eto_tournaments t
            INNER JOIN {$wpdb->prefix}eto_teams tm ON t.id = tm.tournament_id
            INNER JOIN {$wpdb->prefix}eto_team_members m ON tm.id = m.team_id
            WHERE m.user_id = %d",
            $user_id
        )
    );
}

// Formatta la data per il frontend
function eto_format_date($date_string, $format = 'j F Y H:i') {
    $timestamp = strtotime($date_string);
    return date_i18n($format, $timestamp);
}

// Controlla se il check-in è aperto per un torneo
function eto_checkin_open($tournament_id) {
    $tournament = ETO_Tournament::get($tournament_id);
    $start_time = strtotime($tournament->start_date);
    return time() >= ($start_time - 3600) && time() <= $start_time;
}

// Genera un link sicuro per azioni (es. elimina team)
function eto_generate_action_url($action, $params = []) {
    $base_url = admin_url('admin-post.php');
    $params = array_merge(
        ['action' => $action, 'nonce' => wp_create_nonce($action)],
        $params
    );
    return add_query_arg($params, $base_url);
}

// Redirect dopo azione con messaggio
function eto_redirect_with_message($url, $message, $type = 'success') {
    set_transient('eto_action_message', [
        'message' => $message,
        'type' => $type
    ], 30);
    wp_redirect($url);
    exit;
}

// Mostra messaggi dopo redirect
function eto_display_action_messages() {
    if ($message = get_transient('eto_action_message')) {
        echo '<div class="notice notice-' . esc_attr($message['type']) . '"><p>' . esc_html($message['message']) . '</p></div>';
        delete_transient('eto_action_message');
    }
}
add_action('admin_notices', 'eto_display_action_messages');
