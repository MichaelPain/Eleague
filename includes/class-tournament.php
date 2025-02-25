<?php
class ETO_Tournament {
    const MIN_PLAYERS = 2;
    const MAX_PLAYERS = 32;
    const FORMAT_SINGLE_ELIMINATION = 'single_elimination';
    const FORMAT_DOUBLE_ELIMINATION = 'double_elimination';
    const FORMAT_SWISS = 'swiss';
    const ALLOWED_GAME_TYPES = ['csgo', 'lol', 'dota2', 'valorant'];

    public static function create($data) {
        global $wpdb;

        $required_fields = [
            'name' => __('Nome torneo', 'eto'),
            'format' => __('Formato torneo', 'eto'),
            'start_date' => __('Data inizio', 'eto'),
            'end_date' => __('Data fine', 'eto'),
            'game_type' => __('Tipo di gioco', 'eto'),
            'min_players' => __('Giocatori min per team', 'eto'),
            'max_players' => __('Giocatori max per team', 'eto'),
            'max_teams' => __('Numero massimo team', 'eto')
        ];

        foreach ($required_fields as $field => $label) {
            if (empty($data[$field])) {
                return new WP_Error('missing_field',
                    sprintf(__('%s è un campo obbligatorio', 'eto'), $label)
                );
            }
        }

        $name = sanitize_text_field($data['name']);
        $format = self::sanitize_format($data['format']);
        $start_date = self::sanitize_date($data['start_date']);
        $end_date = self::sanitize_date($data['end_date']);
        $game_type = self::sanitize_game_type($data['game_type']);
        $min_players = absint($data['min_players']);
        $max_players = absint($data['max_players']);
        $max_teams = absint($data['max_teams']);
        $checkin_enabled = isset($data['checkin_enabled']) ? 1 : 0;
        $third_place_match = isset($data['third_place_match']) ? 1 : 0;

        if ($format instanceof WP_Error) return $format;
        if ($start_date instanceof WP_Error) return $start_date;
        if ($end_date instanceof WP_Error) return $end_date;
        if ($game_type instanceof WP_Error) return $game_type;

        if ($min_players < self::MIN_PLAYERS || $min_players > self::MAX_PLAYERS) {
            return new WP_Error('invalid_min_players',
                sprintf(__('I giocatori minimi devono essere tra %d e %d', 'eto'), 
                self::MIN_PLAYERS, self::MAX_PLAYERS)
            );
        }

        if ($max_players < $min_players || $max_players > self::MAX_PLAYERS) {
            return new WP_Error('invalid_max_players',
                sprintf(__('I giocatori massimi devono essere tra %d e %d', 'eto'), 
                $min_players, self::MAX_PLAYERS)
            );
        }

        if ($max_teams < 2 || $max_teams > 64) {
            return new WP_Error('invalid_max_teams',
                __('Il numero massimo di team deve essere tra 2 e 64', 'eto')
            );
        }

        if ($start_date >= $end_date) {
            return new WP_Error('invalid_dates',
                __('La data di fine deve essere successiva alla data di inizio', 'eto')
            );
        }

        $wpdb->query('START TRANSACTION');

        try {
            $insert_result = $wpdb->insert(
                "{$wpdb->prefix}eto_tournaments",
                [
                    'name' => $name,
                    'format' => $format,
                    'game_type' => $game_type,
                    'start_date' => $start_date->format('Y-m-d H:i:s'),
                    'end_date' => $end_date->format('Y-m-d H:i:s'),
                    'min_players' => $min_players,
                    'max_players' => $max_players,
                    'max_teams' => $max_teams,
                    'checkin_enabled' => $checkin_enabled,
                    'third_place_match' => $third_place_match,
                    'status' => 'pending',
                    'created_at' => current_time('mysql')
                ],
                [
                    '%s', '%s', '%s', '%s', '%s',
                    '%d', '%d', '%d', '%d', '%d',
                    '%s', '%s'
                ]
            );

            if (!$insert_result) {
                throw new Exception($wpdb->last_error);
            }

            $tournament_id = $wpdb->insert_id;
            $bracket_result = self::generate_initial_bracket($tournament_id);

            if (is_wp_error($bracket_result)) {
                throw new Exception($bracket_result->get_error_message());
            }

            $wpdb->query('COMMIT');
            return $tournament_id;

        } catch (Exception $e) {
            $wpdb->query('ROLLBACK');
            return new WP_Error('db_error', 
                __('Errore durante la creazione del torneo: ', 'eto') . $e->getMessage()
            );
        }
    }

    public static function handle_tournament_creation() {
        try {
            if (!isset($_POST['_eto_tournament_nonce']) || 
                !wp_verify_nonce($_POST['_eto_tournament_nonce'], 'eto_tournament_management')) {
                throw new Exception(__('Verifica di sicurezza fallita', 'eto'));
            }

            if (!current_user_can('manage_eto_tournaments')) {
                throw new Exception(__('Permessi insufficienti', 'eto'));
            }

            $data = [
                'name' => sanitize_text_field($_POST['tournament_name']),
                'format' => sanitize_key($_POST['format']),
                'start_date' => sanitize_text_field($_POST['start_date']),
                'end_date' => sanitize_text_field($_POST['end_date']),
                'game_type' => sanitize_key($_POST['game_type']),
                'min_players' => absint($_POST['min_players']),
                'max_players' => absint($_POST['max_players']),
                'max_teams' => absint($_POST['max_teams']),
                'checkin_enabled' => isset($_POST['checkin_enabled']) ? 1 : 0,
                'third_place_match' => isset($_POST['third_place_match']) ? 1 : 0
            ];

            $result = self::create($data);

            if (is_wp_error($result)) {
                throw new Exception($result->get_error_message());
            }

            wp_redirect(admin_url('admin.php?page=eto-tournaments&created=1'));
            exit;

        } catch (Exception $e) {
            error_log('[ETO] Errore creazione torneo: ' . $e->getMessage());
            set_transient('eto_form_data', $_POST, 45);
            add_settings_error(
                'eto_tournament_errors',
                'creation_failed',
                $e->getMessage(),
                'error'
            );
            wp_redirect(wp_get_referer());
            exit;
        }
    }

    // Resto del codice invariato...
}

add_action('admin_post_eto_create_tournament', ['ETO_Tournament', 'handle_tournament_creation']);
