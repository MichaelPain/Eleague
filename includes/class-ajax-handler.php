<?php
class ETO_Ajax_Handler {
    const NONCE_ACTION = 'eto_ajax_nonce';

    public static function init() {
        add_action('wp_ajax_eto_create_tournament', [__CLASS__, 'handle_create_tournament']);
        add_action('wp_ajax_eto_confirm_match', [__CLASS__, 'handle_confirm_match']);
        add_action('wp_ajax_eto_report_dispute', [__CLASS__, 'handle_report_dispute']);
        add_action('wp_ajax_nopriv_eto_get_standings', [__CLASS__, 'handle_get_standings']);
    }

    public static function generate_nonce() {
        return wp_create_nonce(self::NONCE_ACTION);
    }

    private static function verify_request($action = '') {
        $nonce = $_REQUEST['nonce'] ?? '';
        if (!wp_verify_nonce($nonce, self::NONCE_ACTION)) {
            wp_send_json_error(__('Verifica di sicurezza fallita', 'eto'), 403);
        }

        if (!current_user_can('manage_eto_tournaments')) {
            wp_send_json_error(__('Permessi insufficienti', 'eto'), 403);
        }
    }

    public static function handle_create_tournament() {
        try {
            self::verify_request();
            
            // Validazione dati
            $data = [
                'name' => sanitize_text_field($_POST['name']),
                'format' => sanitize_key($_POST['format']),
                'game_type' => sanitize_key($_POST['game_type']),
                'start_date' => sanitize_text_field($_POST['start_date']),
                'end_date' => sanitize_text_field($_POST['end_date']),
                'teams' => array_map('absint', $_POST['teams'])
            ];

            $result = ETO_Tournament::create($data);
            
            wp_send_json_success([
                'message' => __('Torneo creato con successo', 'eto'),
                'tournament_id' => $result
            ]);

        } catch (Exception $e) {
            wp_send_json_error($e->getMessage(), 400);
        }
    }

    public static function handle_confirm_match() {
        try {
            self::verify_request();

            $match_id = absint($_POST['match_id']);
            $winner_id = absint($_POST['winner_id']);

            $result = ETO_Match::confirm($match_id, $winner_id, $_POST['nonce']);
            
            wp_send_json_success([
                'message' => __('Risultato confermato', 'eto'),
                'match_id' => $match_id
            ]);

        } catch (Exception $e) {
            wp_send_json_error($e->getMessage(), 400);
        }
    }

    public static function handle_report_dispute() {
        try {
            self::verify_request();

            $match_id = absint($_POST['match_id']);
            $reason = sanitize_textarea_field($_POST['reason']);

            $result = ETO_Match::dispute($match_id, $reason, $_POST['nonce']);
            
            wp_send_json_success([
                'message' => __('Disputa registrata', 'eto'),
                'match_id' => $match_id
            ]);

        } catch (Exception $e) {
            wp_send_json_error($e->getMessage(), 400);
        }
    }

    public static function handle_get_standings() {
        try {
            $tournament_id = absint($_POST['tournament_id']);
            $standings = ETO_Tournament::get_standings($tournament_id);
            
            wp_send_json_success([
                'standings' => $standings
            ]);

        } catch (Exception $e) {
            wp_send_json_error($e->getMessage(), 400);
        }
    }
}
