<?php
if (!defined('ABSPATH')) exit;

class ETO_Shortcodes {
    public static function init() {
        add_shortcode('eto_leaderboard', [self::class, 'render_leaderboard']);
        add_shortcode('eto_tournament', [self::class, 'render_tournament']);
        add_shortcode('eto_checkin', [self::class, 'render_checkin_form']);
    }

    // ==================================================
    // 1. SHORTCODE CLASSIFICA
    // ==================================================
    public static function render_leaderboard($atts) {
        if (!is_user_logged_in()) {
            return '<div class="eto-alert">' . esc_html__('Accedi per visualizzare la classifica', 'eto') . '</div>';
        }

        $atts = shortcode_atts([
            'tournament_id' => 0,
            'limit' => 10
        ], $atts);

        $tournament = ETO_Tournament::get(absint($atts['tournament_id']));
        $leaderboard = ETO_Leaderboard::generate($tournament->id, absint($atts['limit']));

        ob_start();
        include ETO_PLUGIN_DIR . 'public/views/leaderboard.php';
        return ob_get_clean();
    }

    // ==================================================
    // 2. SHORTCODE DETTAGLI TORNEO
    // ==================================================
    public static function render_tournament($atts) {
        $atts = shortcode_atts([
            'id' => 0,
            'show_teams' => true,
            'show_bracket' => false
        ], $atts);

        $tournament = ETO_Tournament::get(absint($atts['id']));
        if (!$tournament || $tournament->status === 'deleted') {
            return '<div class="eto-error">' . esc_html__('Torneo non trovato', 'eto') . '</div>';
        }

        ob_start();
        include ETO_PLUGIN_DIR . 'public/views/tournament-details.php';
        return ob_get_clean();
    }

    // ==================================================
    // 3. SHORTCODE CHECK-IN (CORRETTO)
    // ==================================================
    public static function render_checkin_form($atts) {
        if (!is_user_logged_in()) {
            return '<div class="eto-alert">' . esc_html__('Accedi per effettuare il check-in', 'eto') . '</div>';
        }

        $atts = shortcode_atts([
            'tournament_id' => 0,
            'team_id' => 0
        ], $atts);

        // Verifica permessi utente
        if (!current_user_can('eto_team_captain')) {
            return '<div class="eto-alert">' . esc_html__('Solo i capitani possono effettuare il check-in', 'eto') . '</div>';
        }

        // Gestione form check-in
        if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['eto_checkin_submit'])) {
            check_admin_referer('eto_checkin_action', 'eto_checkin_nonce');

            $tournament_id = absint($_POST['tournament_id']);
            $team_id = absint($_POST['team_id']);

            if (ETO_Checkin::process_checkin($tournament_id, $team_id)) {
                echo '<div class="eto-success">' . esc_html__('Check-in effettuato con successo!', 'eto') . '</div>';
            } else {
                echo '<div class="eto-error">' . esc_html__('Errore durante il check-in', 'eto') . '</div>';
            }
        }

        ob_start();
        include ETO_PLUGIN_DIR . 'public/views/checkin-form.php';
        return ob_get_clean();
    }
}

// ==================================================
// 4. GESTIONE AJAX (REVISIONATA E SICURA)
// ==================================================
add_action('wp_ajax_eto_confirm_match', function() {
    check_ajax_referer('eto_global_nonce', 'nonce');
    
    $match_id = absint($_POST['match_id']);
    $user_id = get_current_user_id();

    if (!current_user_can('confirm_results') || !ETO_Match::is_referee($match_id, $user_id)) {
        wp_send_json_error([
            'message' => esc_html__('Permessi insufficienti', 'eto')
        ], 403);
    }

    try {
        $result = ETO_Match::confirm_result(
            $match_id,
            absint($_POST['team1_score']),
            absint($_POST['team2_score'])
        );
        
        wp_send_json_success([
            'message' => esc_html__('Risultato confermato con successo', 'eto'),
            'html' => ETO_Match::render_match($match_id)
        ]);
    } catch (Exception $e) {
        wp_send_json_error([
            'message' => esc_html($e->getMessage())
        ]);
    }
});

add_action('wp_ajax_eto_delete_team', function() {
    check_ajax_referer('eto_global_nonce', 'nonce');
    
    $team_id = absint($_POST['team_id']);
    $user_id = get_current_user_id();

    if (!current_user_can('delete_teams') || !ETO_Team::is_owner($team_id, $user_id)) {
        wp_send_json_error([
            'message' => esc_html__('Accesso negato', 'eto')
        ], 403);
    }

    try {
        $result = ETO_Team::delete($team_id);
        wp_send_json_success([
            'message' => esc_html__('Team eliminato con successo', 'eto')
        ]);
    } catch (Exception $e) {
        wp_send_json_error([
            'message' => esc_html($e->getMessage())
        ]);
    }
});

// ==================================================
// 5. SALVATAGGIO IMPOSTAZIONI (SICURO)
// ==================================================
add_action('admin_post_eto_save_settings', function() {
    check_admin_referer('eto_settings_nonce', 'eto_settings_nonce');
    
    if (!current_user_can('manage_eto_settings')) {
        wp_die(esc_html__('Accesso negato', 'eto'), 403);
    }

    $settings = [
        'riot_api_key' => sanitize_text_field($_POST['riot_api_key']),
        'email_enabled' => isset($_POST['email_enabled']) ? 1 : 0,
        'max_teams' => absint($_POST['max_teams'])
    ];

    foreach ($settings as $key => $value) {
        update_option("eto_$key", $value);
    }

    eto_redirect_with_message(
        admin_url('admin.php?page=eto-settings'),
        esc_html__('Impostazioni salvate con successo!', 'eto'),
        'success'
    );
});

// ==================================================
// 6. CREAZIONE TORNEO (COMPLETA)
// ==================================================
add_action('admin_post_eto_create_tournament', function() {
    check_admin_referer('eto_create_tournament', 'eto_tournament_nonce');
    
    if (!current_user_can('manage_eto_tournaments')) {
        wp_die(esc_html__('Permessi insufficienti', 'eto'), 403);
    }

    $data = [
        'name' => sanitize_text_field($_POST['tournament_name']),
        'format' => sanitize_key($_POST['format']),
        'start_date' => sanitize_text_field($_POST['start_date']),
        'end_date' => sanitize_text_field($_POST['end_date']),
        'min_players' => absint($_POST['min_players']),
        'max_players' => absint($_POST['max_players']),
        'checkin_enabled' => isset($_POST['checkin_enabled']) ? 1 : 0,
        'third_place_match' => isset($_POST['third_place_match']) ? 1 : 0
    ];

    try {
        $tournament_id = ETO_Tournament::create($data);
        eto_redirect_with_message(
            admin_url('admin.php?page=eto-tournaments'),
            esc_html__('Torneo creato con successo! ID: ', 'eto') . $tournament_id,
            'success'
        );
    } catch (Exception $e) {
        eto_redirect_with_message(
            admin_url('admin.php?page=eto-create-tournament'),
            esc_html__('Errore: ', 'eto') . $e->getMessage(),
            'error'
        );
    }
});