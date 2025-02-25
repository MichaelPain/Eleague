<?php
if (!defined('ABSPATH')) exit;

class ETO_Ajax_Handler {
    private static function verify_request() {
        if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'eto_global_nonce')) {
            throw new Exception(__('Verifica di sicurezza fallita', 'eto'), 403);
        }
    }

    public static function handle_create_tournament() {
        try {
            // Verifica permessi utente
            if (!current_user_can('manage_eto_tournaments')) {
                throw new Exception(__('Permessi insufficienti', 'eto'), 403);
            }

            self::verify_request();

            $data = [
                'tournament_name' => sanitize_text_field($_POST['name']),
                'format' => sanitize_key($_POST['format']),
                'game_type' => sanitize_key($_POST['game_type']),
                'start_date' => sanitize_text_field($_POST['start_date']),
                'end_date' => sanitize_text_field($_POST['end_date']),
                'teams' => array_map('absint', (array)$_POST['teams'])
            ];

            $result = ETO_Tournament::create($data);

            wp_send_json_success([
                'message' => __('Torneo creato con successo', 'eto'),
                'tournament_id' => $result
            ]);

        } catch (Exception $e) {
            wp_send_json_error([
                'message' => $e->getMessage(),
                'code' => $e->getCode()
            ], $e->getCode() ?: 400);
        }
    }

    public static function handle_confirm_match() {
        try {
            // Verifica permessi utente
            if (!current_user_can('confirm_results')) {
                throw new Exception(__('Permessi insufficienti', 'eto'), 403);
            }

            self::verify_request();

            $match_id = absint($_POST['match_id']);
            $winner_id = absint($_POST['winner_id']);
            
            $result = ETO_Match::confirm($match_id, $winner_id, $_POST['nonce']);

            wp_send_json_success([
                'message' => __('Risultato confermato', 'eto'),
                'match_id' => $match_id
            ]);

        } catch (Exception $e) {
            wp_send_json_error([
                'message' => $e->getMessage(),
                'code' => $e->getCode()
            ], $e->getCode() ?: 400);
        }
    }

    public static function handle_report_dispute() {
        try {
            // Verifica permessi utente
            if (!current_user_can('manage_eto_disputes')) {
                throw new Exception(__('Permessi insufficienti', 'eto'), 403);
            }

            self::verify_request();

            $match_id = absint($_POST['match_id']);
            $reason = sanitize_textarea_field($_POST['reason']);
            
            $result = ETO_Match::dispute($match_id, $reason, $_POST['nonce']);

            wp_send_json_success([
                'message' => __('Disputa registrata', 'eto'),
                'match_id' => $match_id
            ]);

        } catch (Exception $e) {
            wp_send_json_error([
                'message' => $e->getMessage(),
                'code' => $e->getCode()
            ], $e->getCode() ?: 400);
        }
    }

    public static function handle_get_standings() {
        try {
            // Verifica permessi utente
            if (!current_user_can('view_eto_results')) {
                throw new Exception(__('Accesso negato', 'eto'), 403);
            }

            $tournament_id = absint($_POST['tournament_id']);
            $standings = ETO_Tournament::get_standings($tournament_id);

            wp_send_json_success([
                'standings' => $standings
            ]);

        } catch (Exception $e) {
            wp_send_json_error([
                'message' => $e->getMessage(),
                'code' => $e->getCode()
            ], $e->getCode() ?: 400);
        }
    }
}

// Registrazione degli hook AJAX
add_action('wp_ajax_eto_create_tournament', ['ETO_Ajax_Handler', 'handle_create_tournament']);
add_action('wp_ajax_eto_confirm_match', ['ETO_Ajax_Handler', 'handle_confirm_match']);
add_action('wp_ajax_eto_report_dispute', ['ETO_Ajax_Handler', 'handle_report_dispute']);
add_action('wp_ajax_eto_get_standings', ['ETO_Ajax_Handler', 'handle_get_standings']);