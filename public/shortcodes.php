<?php
if (!defined('ABSPATH')) exit;

class ETO_Shortcodes {
    const ALLOWED_ROLES = ['subscriber', 'editor', 'administrator'];

    // ==================================================
    // 1. REGISTRAZIONE SHORTCODE E HOOK
    // ==================================================
    public static function init() {
        add_shortcode('eto_leaderboard', [__CLASS__, 'render_leaderboard']);
        add_shortcode('eto_tournament', [__CLASS__, 'render_tournament']);
        add_shortcode('eto_checkin', [__CLASS__, 'render_checkin_form']);
        add_shortcode('eto_player_stats', [__CLASS__, 'render_player_stats']);
        add_shortcode('eto_upcoming_matches', [__CLASS__, 'render_upcoming_matches']);

        // Aggiunta sezione AJAX
        add_action('wp_ajax_eto_checkin', [__CLASS__, 'handle_checkin_ajax']);
        add_action('admin_ajax_eto_checkin', [__CLASS__, 'handle_checkin_ajax']);
    }

    // ==================================================
    // 2. SHORTCODE CLASSIFICA (XSS PROTECTED)
    // ==================================================
    public static function render_leaderboard($atts) {
        if (!is_user_logged_in()) {
            return '<div class="eto-alert">' . esc_html__('Accedi per visualizzare la classifica', 'eto') . '</div>';
        }

        $atts = shortcode_atts([
            'tournament_id' => 0,
            'limit' => 10,
            'show_avatar' => true,
            'show_rank' => false,
            'style' => 'table'
        ], $atts, 'eto_leaderboard');

        $tournament = ETO_Tournament::get(absint($atts['tournament_id']));

        if (!$tournament || $tournament->status === 'deleted') {
            return '<div class="eto-error">' . esc_html__('Torneo non trovato', 'eto') . '</div>';
        }

        $leaderboard = ETO_Leaderboard::generate(
            absint($tournament->id),
            absint($atts['limit']),
            (bool)$atts['show_avatar'],
            (bool)$atts['show_rank']
        );

        // Sanitizzazione dati
        $clean_data = [
            'tournament_name' => esc_html($tournament->name),
            'entries' => array_map(function($entry) {
                return [
                    'player' => esc_html($entry['player']),
                    'points' => absint($entry['points']),
                    'avatar' => esc_url($entry['avatar'])
                ];
            }, $leaderboard)
        ];

        ob_start();
        include ETO_PLUGIN_DIR . 'public/views/leaderboard-' . sanitize_file_name($atts['style']) . '.php';
        return ob_get_clean();
    }

    // ==================================================
    // 3. SHORTCODE DETTAGLI TORNEO (FULLY ESCAPED)
    // ==================================================
    public static function render_tournament($atts) {
        $atts = shortcode_atts([
            'id' => 0,
            'show_teams' => true,
            'show_bracket' => false,
            'show_schedule' => false,
            'show_rules' => true
        ], $atts, 'eto_tournament');

        $tournament = ETO_Tournament::get(absint($atts['id']));

        if (!$tournament || $tournament->status === 'deleted') {
            return '<div class="eto-error">' . esc_html__('Torneo non trovato', 'eto') . '</div>';
        }

        $safe_output = [
            'name' => esc_html($tournament->name),
            'description' => wp_kses_post($tournament->description),
            'rules' => wp_kses_post($tournament->rules),
            'teams' => [],
            'schedule' => [],
            'bracket' => ETO_Bracket::generate(absint($tournament->id))
        ];

        if ($atts['show_teams']) {
            $teams = ETO_Team::get_by_tournament(absint($tournament->id));
            foreach ($teams as $team) {
                $safe_output['teams'][] = [
                    'name' => esc_html($team->name),
                    'captain' => esc_html(get_userdata($team->captain_id)->display_name),
                    'members' => array_map('esc_html', $team->members)
                ];
            }
        }

        if ($atts['show_schedule']) {
            $schedule = ETO_Scheduler::get_schedule(absint($tournament->id));
            foreach ($schedule as $match) {
                $safe_output['schedule'][] = [
                    'date' => esc_html(date_i18n('j F Y', strtotime($match->date))),
                    'teams' => [
                        esc_html(ETO_Team::get($match->team1_id)->name),
                        esc_html(ETO_Team::get($match->team2_id)->name)
                    ]
                ];
            }
        }

        ob_start();
        include ETO_PLUGIN_DIR . 'public/views/tournament-details.php';
        return ob_get_clean();
    }

    // ==================================================
    // 4. GESTIONE AJAX (REVISIONATA E SICURA)
    // ==================================================
    public static function handle_checkin_ajax() {
        check_ajax_referer('eto_checkin_action', 'nonce');
        if (!current_user_can('read')) {
            wp_die(esc_html__('Accesso negato', 'eto'));
        }

        $tournament_id = absint($_POST['tournament_id']);
        $team_id = absint($_POST['team_id']);
        
        $result = ETO_Checkin::process_checkin($tournament_id, $team_id);
        wp_send_json_success($result);
    }

    // ==================================================
    // 5. SALVATAGGIO IMPOSTAZIONI (SICURO)
    // ==================================================
    public static function save_settings($settings) {
        if (!current_user_can('manage_options')) {
            wp_die(esc_html__('Accesso negato', 'eto'));
        }

        $sanitized_settings = [
            'email_notifications' => isset($settings['email_notifications']),
            'checkin_duration' => absint($settings['checkin_duration']),
            'max_retries' => absint($settings['max_retries'])
        ];

        update_option('eto_plugin_settings', $sanitized_settings);
        return $sanitized_settings;
    }

    // ==================================================
    // 6. CREAZIONE TORNEO (COMPLETA)
    // ==================================================
    public static function create_tournament() {
        if (!current_user_can('manage_eto_tournaments')) {
            wp_die(esc_html__('Accesso negato', 'eto'));
        }

        $data = [
            'name' => sanitize_text_field($_POST['name']),
            'game_type' => sanitize_key($_POST['game_type']),
            'max_teams' => absint($_POST['max_teams']),
            'start_date' => sanitize_text_field($_POST['start_date']),
            'end_date' => sanitize_text_field($_POST['end_date']),
            'checkin_enabled' => isset($_POST['checkin_enabled']) ? 1 : 0
        ];

        try {
            $tournament_id = ETO_Tournament::create($data);
            wp_send_json_success(['message' => 'Torneo creato con successo']);
        } catch (Exception $e) {
            wp_send_json_error(['message' => $e->getMessage()]);
        }
    }

    // ==================================================
    // 7. SHORTCODE CHECK-IN (SECURE)
    // ==================================================
    public static function render_checkin_form($atts) {
        if (!is_user_logged_in()) {
            return '<div class="eto-alert">' . esc_html__('Accedi per effettuare il check-in', 'eto') . '</div>';
        }

        $atts = shortcode_atts([
            'tournament_id' => 0,
            'team_id' => 0,
            'show_history' => false
        ], $atts, 'eto_checkin');

        $user_id = get_current_user_id();
        $team = ETO_Team::get(absint($atts['team_id']));

        if (!$team || !ETO_Team::is_member(absint($team->id), $user_id)) {
            return '<div class="eto-alert">' . esc_html__('Accesso non autorizzato', 'eto') . '</div>';
        }

        // Logica form...
        ob_start();
        include ETO_PLUGIN_DIR . 'public/views/checkin-form.php';
        return ob_get_clean();
    }

    // ==================================================
    // 8. SHORTCODE STATISTICHE (ORIGINALE + CORREZIONI)
    // ==================================================
    public static function render_player_stats($atts) {
        if (!is_user_logged_in()) {
            return '<div class="eto-alert">' . esc_html__('Accedi per visualizzare le statistiche', 'eto') . '</div>';
        }

        $atts = shortcode_atts([
            'user_id' => 0,
            'show_matches' => true,
            'show_rank' => false
        ], $atts, 'eto_player_stats');

        $user_id = $atts['user_id'] ? absint($atts['user_id']) : get_current_user_id();
        $stats = ETO_Stats::get_player_stats($user_id);

        // Sanitizzazione dati
        $clean_stats = [
            'win_rate' => round(floatval($stats['win_rate']), 2),
            'total_matches' => absint($stats['total_matches']),
            'kda_ratio' => round(floatval($stats['kda_ratio']), 2),
            'matches' => array_map(function($match) {
                return [
                    'date' => esc_html(date_i18n('j M Y', strtotime($match->date))),
                    'result' => esc_html($match->result),
                    'score' => esc_html($match->score)
                ];
            }, $stats['recent_matches'])
        ];

        ob_start();
        include ETO_PLUGIN_DIR . 'public/views/player-stats.php';
        return ob_get_clean();
    }

    // ==================================================
    // 9. SHORTCODE PROSSIMI MATCH (ORIGINALE + CORREZIONI)
    // ==================================================
    public static function render_upcoming_matches($atts) {
        $atts = shortcode_atts([
            'tournament_id' => 0,
            'limit' => 5,
            'show_teams' => true
        ], $atts, 'eto_upcoming_matches');

        $matches = ETO_Scheduler::get_upcoming_matches(
            absint($atts['tournament_id']),
            absint($atts['limit'])
        );

        $clean_matches = [];
        foreach ($matches as $match) {
            $clean_matches[] = [
                'date' => esc_html(date_i18n('j F H:i', strtotime($match->date))),
                'team1' => esc_html(ETO_Team::get($match->team1_id)->name),
                'team2' => esc_html(ETO_Team::get($match->team2_id)->name),
                'round' => esc_html($match->round)
            ];
        }

        ob_start();
        include ETO_PLUGIN_DIR . 'public/views/upcoming-matches.php';
        return ob_get_clean();
    }
}