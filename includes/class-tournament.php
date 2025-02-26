<?php
if (!defined('ABSPATH')) exit;

class ETO_Tournament {
    const FORMAT_SINGLE_ELIMINATION = 'single_elimination';
    const FORMAT_DOUBLE_ELIMINATION = 'double_elimination';
    const FORMAT_SWISS = 'swiss';
    const MIN_PLAYERS = 1;
    const MAX_PLAYERS = 10;
    const MAX_TEAMS = 64;
    const ALLOWED_GAME_TYPES = ['lol', 'csgo', 'dota2', 'valorant', 'overwatch'];

    public static function get_supported_games() {
        return [
            'lol' => esc_html__('League of Legends', 'eto'),
            'csgo' => esc_html__('Counter-Strike: Global Offensive', 'eto'),
            'dota2' => esc_html__('Dota 2', 'eto'),
            'valorant' => esc_html__('Valorant', 'eto'),
            'overwatch' => esc_html__('Overwatch', 'eto')
        ];
    }

    public static function create($data) {
        global $wpdb;
        
        $defaults = [
            'name' => '',
            'format' => self::FORMAT_SINGLE_ELIMINATION,
            'game_type' => 'lol',
            'start_date' => current_time('mysql'),
            'end_date' => date('Y-m-d H:i:s', strtotime('+1 week')),
            'min_players' => self::MIN_PLAYERS,
            'max_players' => self::MAX_PLAYERS,
            'max_teams' => self::MAX_TEAMS,
            'checkin_enabled' => 0,
            'third_place_match' => 0,
            'status' => 'pending',
            'created_by' => get_current_user_id()
        ];

        $data = wp_parse_args($data, $defaults);

        // Validazione campi obbligatori con escaping
        $required_fields = [
            'name' => esc_html__('Nome', 'eto'),
            'format' => esc_html__('Formato', 'eto'),
            'game_type' => esc_html__('Tipo di gioco', 'eto'),
            'start_date' => esc_html__('Data inizio', 'eto'),
            'end_date' => esc_html__('Data fine', 'eto'),
            'min_players' => esc_html__('Giocatori min per team', 'eto'),
            'max_players' => esc_html__('Giocatori max per team', 'eto'),
            'max_teams' => esc_html__('Numero massimo team', 'eto')
        ];

        foreach ($required_fields as $field => $label) {
            if (empty($data[$field])) {
                return new WP_Error(
                    'missing_field', 
                    sprintf(esc_html__('Il campo %s è obbligatorio', 'eto'), $label)
                );
            }
        }

        // Sanitizzazione avanzata con controllo errori
        $clean_data = [
            'name' => sanitize_text_field($data['name']),
            'format' => self::sanitize_format($data['format']),
            'game_type' => self::sanitize_game_type($data['game_type']),
            'start_date' => self::sanitize_date($data['start_date']),
            'end_date' => self::sanitize_date($data['end_date']),
            'min_players' => absint($data['min_players']),
            'max_players' => absint($data['max_players']),
            'max_teams' => absint($data['max_teams']),
            'checkin_enabled' => (int)$data['checkin_enabled'],
            'third_place_match' => (int)$data['third_place_match'],
            'status' => 'pending',
            'created_at' => current_time('mysql'),
            'updated_at' => current_time('mysql')
        ];

        foreach ($clean_data as $key => $value) {
            if (is_wp_error($value)) {
                return $value;
            }
        }

        // Transazione database atomica
        $wpdb->query('START TRANSACTION');
        try {
            $insert_result = $wpdb->insert(
                "{$wpdb->prefix}eto_tournaments",
                $clean_data,
                [
                    '%s', '%s', '%s', '%s', '%s',
                    '%d', '%d', '%d', '%d', '%d',
                    '%s', '%s', '%s'
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
            return new WP_Error(
                'db_error', 
                esc_html__('Errore durante la creazione del torneo: ', 'eto') . $e->getMessage()
            );
        }
    }

    public static function update_status($tournament_id, $new_status) {
        global $wpdb;
        $allowed_statuses = ['pending', 'active', 'completed', 'cancelled'];
        
        if (!in_array($new_status, $allowed_statuses)) {
            return new WP_Error(
                'invalid_status', 
                esc_html__('Stato non valido', 'eto')
            );
        }

        $result = $wpdb->update(
            "{$wpdb->prefix}eto_tournaments",
            ['status' => $new_status],
            ['id' => $tournament_id],
            ['%s'],
            ['%d']
        );

        return $result !== false;
    }

    public static function generate_initial_bracket($tournament_id) {
        global $wpdb;
        $tournament = self::get($tournament_id);
        
        if (!$tournament || $tournament->status === 'deleted') {
            return new WP_Error(
                'invalid_tournament', 
                esc_html__('Torneo non trovato', 'eto')
            );
        }

        $teams = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT id FROM {$wpdb->prefix}eto_teams 
                WHERE tournament_id = %d AND status = 'checked_in'",
                $tournament_id
            ),
            ARRAY_A
        );

        if (count($teams) < 2) {
            return new WP_Error(
                'insufficient_teams',
                esc_html__('Sono necessari almeno 2 team per generare il bracket', 'eto')
            );
        }

        $team_ids = array_column($teams, 'id');
        $bracket = [];
        $max_teams = $tournament->max_teams;

        switch ($tournament->format) {
            case self::FORMAT_SINGLE_ELIMINATION:
                $bracket = self::generate_single_elimination_bracket($team_ids, $max_teams);
                break;
                
            case self::FORMAT_DOUBLE_ELIMINATION:
                $bracket = self::generate_double_elimination_bracket($team_ids, $max_teams);
                break;
                
            case self::FORMAT_SWISS:
                if (!class_exists('ETO_Swiss')) {
                    require_once ETO_PLUGIN_DIR . 'includes/class-swiss.php';
                }
                $bracket = ETO_Swiss::generate_initial_round($tournament_id);
                break;
                
            default:
                return new WP_Error(
                    'invalid_format', 
                    esc_html__('Formato del torneo non supportato', 'eto')
                );
        }

        foreach ($bracket as $round => $matches) {
            foreach ($matches as $match) {
                $wpdb->insert(
                    "{$wpdb->prefix}eto_matches",
                    [
                        'tournament_id' => $tournament_id,
                        'round' => $round,
                        'team1_id' => $match['team1_id'],
                        'team2_id' => $match['team2_id'],
                        'status' => 'pending'
                    ],
                    ['%d', '%s', '%d', '%d', '%s']
                );
            }
        }
        return true;
    }

    private static function generate_single_elimination_bracket($team_ids, $max_teams) {
        $count = count($team_ids);
        $next_power = 2 ** ceil(log($max_teams, 2));
        $byes = $next_power - $count;
        for ($i = 0; $i < $byes; $i++) {
            $team_ids[] = 0;
        }
        shuffle($team_ids);
        $matches = [];
        $total_rounds = log($next_power, 2);
        
        for ($round = 1; $round <= $total_rounds; $round++) {
            $round_matches = [];
            $chunk_size = count($team_ids) / 2;
            foreach (array_chunk($team_ids, $chunk_size) as $pair) {
                $round_matches[] = [
                    'team1_id' => $pair[0],
                    'team2_id' => $pair[1] ?? 0
                ];
            }
            $matches["Round $round"] = $round_matches;
            $team_ids = array_fill(0, $chunk_size, null);
        }
        return $matches;
    }

    private static function generate_double_elimination_bracket($team_ids, $max_teams) {
        $total_rounds = log($max_teams, 2);
        $winners_bracket = self::generate_single_elimination_bracket($team_ids, $max_teams);
        $losers_bracket = [];
        $losers_pool = [];

        for ($round = 1; $round < $total_rounds; $round++) {
            $current_round = $winners_bracket["Round $round"];
            $losers_pool = array_merge(
                $losers_pool,
                array_filter($current_round, function($match) {
                    return empty($match['team2_id']);
                })
            );
        }

        $remaining_teams = count($losers_pool);
        $chunk_size = $remaining_teams / 2;
        for ($round = 1; $round <= $total_rounds - 1; $round++) {
            $round_matches = [];
            foreach (array_chunk($losers_pool, $chunk_size) as $pair) {
                $round_matches[] = [
                    'team1_id' => $pair[0],
                    'team2_id' => $pair[1] ?? 0
                ];
            }
            $losers_bracket["Losers Round $round"] = $round_matches;
            $losers_pool = array_fill(0, $chunk_size, null);
        }

        $winners_finalist = $winners_bracket["Round $total_rounds"][0]['team1_id'];
        $losers_finalist = reset($losers_pool) ?? null;
        $losers_bracket["Grand Finals"] = [
            [
                'team1_id' => $winners_finalist,
                'team2_id' => $losers_finalist
            ]
        ];
        return array_merge($winners_bracket, $losers_bracket);
    }

    private static function sanitize_format($format) {
        $allowed = [
            self::FORMAT_SINGLE_ELIMINATION,
            self::FORMAT_DOUBLE_ELIMINATION,
            self::FORMAT_SWISS
        ];
        $clean_format = sanitize_key($format);
        if (!in_array($clean_format, $allowed)) {
            return new WP_Error(
                'invalid_format',
                sprintf(
                    esc_html__('Formato non valido. Scegli tra: %s', 'eto'),
                    implode(', ', $allowed)
                )
            );
        }
        return $clean_format;
    }

    private static function sanitize_date($date_string) {
        try {
            $date = new DateTime($date_string, new DateTimeZone(wp_timezone_string()));
            if ($date < new DateTime('now')) {
                return new WP_Error(
                    'past_date',
                    esc_html__('Non puoi programmare tornei nel passato', 'eto')
                );
            }
            return $date;
        } catch (Exception $e) {
            return new WP_Error(
                'invalid_date',
                esc_html__('Formato data non valido', 'eto')
            );
        }
    }

    public static function count($status = null) {
        global $wpdb;
        $query = "SELECT COUNT(*) FROM {$wpdb->prefix}eto_tournaments";
        $params = [];
        if ($status) {
            $query .= " WHERE status = %s";
            $params[] = $status;
        }
        return $wpdb->get_var($params ? $wpdb->prepare($query, $params) : $query);
    }

    public static function get($tournament_id) {
        global $wpdb;
        return $wpdb->get_row(
            $wpdb->prepare(
                "SELECT * FROM {$wpdb->prefix}eto_tournaments 
                WHERE id = %d",
                $tournament_id
            )
        );
    }

    public static function get_all() {
        global $wpdb;
        return $wpdb->get_results(
            $wpdb->prepare(
                "SELECT * FROM {$wpdb->prefix}eto_tournaments 
                WHERE status != %s 
                ORDER BY start_date DESC",
                'deleted'
            )
        );
    }

    public static function handle_tournament_creation() {
        global $wpdb;
        try {
            // 1. Verifica Nonce
            if (!isset($_POST['_eto_create_nonce']) || 
                !wp_verify_nonce($_POST['_eto_create_nonce'], 'eto_create_tournament_action')) {
                throw new Exception(
                    esc_html__('Verifica di sicurezza fallita.', 'eto'), 
                    1001
                );
            }

            // 2. Verifica Permessi
            if (!current_user_can('manage_eto_tournaments')) {
                throw new Exception(
                    esc_html__('Permessi insufficienti per creare tornei', 'eto'), 
                    1002
                );
            }

            // 3. Elaborazione Dati
            $data = [
                'name' => sanitize_text_field($_POST['name']),
                'format' => sanitize_key($_POST['format']),
                'min_players' => absint($_POST['min_players']),
                'max_players' => absint($_POST['max_players']),
                'max_teams' => absint($_POST['max_teams']),
                'start_date' => sanitize_text_field($_POST['start_date']),
                'end_date' => sanitize_text_field($_POST['end_date']),
                'game_type' => sanitize_key($_POST['game_type']),
                'checkin_enabled' => isset($_POST['checkin_enabled']) ? 1 : 0,
                'third_place_match' => isset($_POST['third_place_match']) ? 1 : 0
            ];

            // 4. Creazione Torneo
            $result = self::create($data);
            if (is_wp_error($result)) {
                throw new Exception(
                    $result->get_error_message(), 
                    $result->get_error_code()
                );
            }

            // 5. Redirect con successo
            wp_redirect(admin_url('admin.php?page=eto-tournaments&created=1'));
            exit;

        } catch (Exception $e) {
            set_transient('eto_form_data', $_POST, 45);
            
            $error_code = $e->getCode();
            $error_query = match($error_code) {
                1001 => 'nonce_error',
                1002 => 'permission_error',
                default => 'creation_error'
            };

            wp_redirect(add_query_arg($error_query, 1, wp_get_referer()));
            exit;
        }
    }
    public static function handle_tournament_creation() {
        global $wpdb;
        try {
            // 1. Verifica Nonce
            if (!isset($_POST['_eto_create_nonce']) || 
                !wp_verify_nonce($_POST['_eto_create_nonce'], 'eto_create_tournament_action')) {
                throw new Exception(
                    esc_html__('Verifica di sicurezza fallita.', 'eto'), 
                    1001
                );
            }

            // 2. Verifica Permessi
            if (!current_user_can('manage_eto_tournaments')) {
                throw new Exception(
                    esc_html__('Permessi insufficienti per creare tornei', 'eto'), 
                    1002
                );
            }

            // 3. Elaborazione Dati
            $data = [
                'name' => sanitize_text_field($_POST['name']),
                'format' => sanitize_key($_POST['format']),
                'min_players' => absint($_POST['min_players']),
                'max_players' => absint($_POST['max_players']),
                'max_teams' => absint($_POST['max_teams']),
                'start_date' => sanitize_text_field($_POST['start_date']),
                'end_date' => sanitize_text_field($_POST['end_date']),
                'game_type' => sanitize_key($_POST['game_type']),
                'checkin_enabled' => isset($_POST['checkin_enabled']) ? 1 : 0,
                'third_place_match' => isset($_POST['third_place_match']) ? 1 : 0
            ];

            // 4. Creazione Torneo
            $result = self::create($data);
            if (is_wp_error($result)) {
                throw new Exception(
                    $result->get_error_message(), 
                    $result->get_error_code()
                );
            }

            // 5. Redirect con successo
            wp_redirect(admin_url('admin.php?page=eto-tournaments&created=1'));
            exit;

        } catch (Exception $e) {
            set_transient('eto_form_data', $_POST, 45);
            
            $error_code = $e->getCode();
            $error_query = match($error_code) {
                1001 => 'nonce_error',
                1002 => 'permission_error',
                default => 'creation_error'
            };

            wp_redirect(add_query_arg($error_query, 1, wp_get_referer()));
            exit;
        }
    }

    public static function admin_notices() {
        if ($error = get_transient('eto_max_teams_error')) {
            echo '<div class="notice notice-error">';
            echo esc_html($error);
            echo '</div>';
            delete_transient('eto_max_teams_error');
        }

        if (isset($_GET['nonce_error'])) {
            echo '<div class="notice notice-error">';
            echo esc_html__('Errore di sicurezza durante la creazione del torneo', 'eto');
            echo '</div>';
        }

        if (isset($_GET['permission_error'])) {
            echo '<div class="notice notice-error">';
            echo esc_html__('Permessi insufficienti per creare tornei', 'eto');
            echo '</div>';
        }

        if (isset($_GET['creation_error'])) {
            $form_data = get_transient('eto_form_data');
            echo '<div class="notice notice-error">';
            echo '<h3>' . esc_html__('Errore creazione torneo', 'eto') . '</h3>';
            echo '<pre>' . esc_html(print_r(array_map('sanitize_text_field', $form_data), true)) . '</pre>';
            echo '</div>';
            delete_transient('eto_form_data');
        }

        if (isset($_GET['created'])) {
            echo '<div class="notice notice-success">';
            echo esc_html__('Torneo creato con successo!', 'eto');
            echo '</div>';
        }
    }
}

    private static function sanitize_game_type($game_type) {
        $clean_type = sanitize_key($game_type);
        if (!in_array($clean_type, self::ALLOWED_GAME_TYPES)) {
            return new WP_Error(
                'invalid_game_type',
                sprintf(
                    esc_html__('Tipo di gioco non valido. Scegli tra: %s', 'eto'),
                    implode(', ', array_keys(self::get_supported_games()))
                )
            );
        }
        return $clean_type;
    }

    public static function ajax_get_tournament_details() {
        $tournament_id = absint($_POST['tournament_id']);
        $tournament = self::get($tournament_id);
        
        if (!$tournament) {
            wp_send_json_error(__('Torneo non trovato', 'eto'));
        }

        $details = [
            'name' => $tournament->name,
            'format' => $tournament->format,
            'game_type' => self::get_supported_games()[$tournament->game_type],
            'start_date' => $tournament->start_date,
            'end_date' => $tournament->end_date,
            'max_teams' => $tournament->max_teams
        ];

        wp_send_json_success($details);
    }
}

// Registrazione hook finali
add_action('admin_notices', ['ETO_Tournament', 'admin_notices']);
add_action('admin_post_eto_create_tournament', ['ETO_Tournament', 'handle_tournament_creation']);
add_action('wp_ajax_eto_get_tournament_details', ['ETO_Tournament', 'ajax_get_tournament_details']);