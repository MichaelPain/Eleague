<?php
class ETO_Tournament {
    const MIN_PLAYERS = 2;
    const MAX_PLAYERS = 32;
    const MAX_TEAMS = 64;
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

        // Controllo errori principale
        if ($format instanceof WP_Error) return $format;
        if ($start_date instanceof WP_Error) return $start_date;
        if ($end_date instanceof WP_Error) return $end_date;
        if ($game_type instanceof WP_Error) return $game_type;

        // Validazione giocatori per team
        if ($min_players < self::MIN_PLAYERS || $min_players > self::MAX_PLAYERS) {
            return new WP_Error('invalid_min_players',
                sprintf(__('I giocatori minimi devono essere tra %d e %d', 'eto'), self::MIN_PLAYERS, self::MAX_PLAYERS)
            );
        }

        if ($max_players < $min_players || $max_players > self::MAX_PLAYERS) {
            return new WP_Error('invalid_max_players',
                sprintf(__('I giocatori massimi devono essere tra %d e %d', 'eto'), $min_players, self::MAX_PLAYERS)
            );
        }

        // Validazione numero team
        if ($max_teams < 2 || $max_teams > self::MAX_TEAMS) {
            set_transient('eto_max_teams_error', 
                sprintf(__('Il numero massimo di team deve essere tra %d e %d', 'eto'), 2, self::MAX_TEAMS),
                45
            );
            return new WP_Error('invalid_max_teams',
                sprintf(__('Il numero massimo di team deve essere tra %d e %d', 'eto'), 2, self::MAX_TEAMS)
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
            return new WP_Error('db_error', __('Errore durante la creazione del torneo: ', 'eto') . $e->getMessage());
        }
    }

    public static function update_status($tournament_id, $new_status) {
        global $wpdb;

        $exists = $wpdb->get_var(
            $wpdb->prepare(
                "SELECT COUNT(*) FROM {$wpdb->prefix}eto_tournaments
                WHERE id = %d",
                $tournament_id
            )
        );

        if (!$exists) {
            return new WP_Error('invalid_tournament', __('Il torneo specificato non esiste', 'eto'));
        }

        $allowed_statuses = ['pending', 'active', 'completed', 'cancelled'];
        if (!in_array($new_status, $allowed_statuses)) {
            return new WP_Error('invalid_status', __('Stato del torneo non valido', 'eto'));
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

    private static function sanitize_game_type($game_type) {
        $clean_type = sanitize_key($game_type);
        if (!in_array($clean_type, self::ALLOWED_GAME_TYPES)) {
            return new WP_Error('invalid_game_type',
                sprintf(__('Tipo di gioco non valido. Scegli tra: %s', 'eto'),
                implode(', ', self::ALLOWED_GAME_TYPES))
            );
        }
        return $clean_type;
    }

    public static function generate_initial_bracket($tournament_id) {
        global $wpdb;
        $tournament = self::get($tournament_id);
        
        if (!$tournament) {
            return new WP_Error('invalid_tournament', __('Torneo non trovato', 'eto'));
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
            return new WP_Error('insufficient_teams',
                __('Sono necessari almeno 2 team per generare il bracket', 'eto')
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
                $bracket = ETO_Swiss::generate_initial_round($tournament_id, $max_teams);
                break;
            
            default:
                return new WP_Error('invalid_format',
                    __('Formato del torneo non supportato', 'eto')
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
            $team_ids[] = 0; // Team fantasma
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

        // Distribuzione dei perdenti del winners bracket nel losers bracket
        for ($round = 1; $round < $total_rounds; $round++) {
            $current_round = $winners_bracket["Round $round"];
            $losers_pool = array_merge(
                $losers_pool,
                array_filter($current_round, function($match) {
                    return empty($match['team2_id']);
                })
            );
        }

        // Generazione round per i perdenti
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

        // Aggiunta finale tra vincitore winners e vincitore losers
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
            return new WP_Error('invalid_format',
                sprintf(__('Formato non valido. Scegli tra: %s', 'eto'), implode(', ', $allowed))
            );
        }
        
        return $clean_format;
    }

    private static function sanitize_date($date_string) {
        try {
            $date = new DateTime($date_string, new DateTimeZone(wp_timezone_string()));
            
            if ($date < new DateTime('now')) {
                return new WP_Error('past_date',
                    __('Non puoi programmare tornei nel passato', 'eto')
                );
            }
            
            return $date;
        } catch (Exception $e) {
            return new WP_Error('invalid_date',
                __('Formato data non valido', 'eto')
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
                __('Verifica di sicurezza fallita.', 'eto'),
                1001 // Codice errore custom
            );
        }

            // Verifica Permessi
            if (!current_user_can('manage_eto_tournaments')) {
                throw new Exception(__('Permessi insufficienti per creare tornei', 'eto'), 1002);
            }

            // Elaborazione Dati
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

            // Creazione Torneo
            $result = self::create($data);

            if (is_wp_error($result)) {
                throw new Exception($result->get_error_message(), $result->get_error_code());
            }

            // Redirect con successo
            wp_redirect(admin_url('admin.php?page=eto-tournaments&created=1'));
            exit;

        } catch (Exception $e) {
            // Setta transient con dati del form
            set_transient('eto_form_data', $_POST, 45);

            // Redirect con codice errore
            switch ($e->getCode()) {
                case 1001:
                    wp_redirect(add_query_arg('nonce_error', 1, wp_get_referer()));
                    break;
                case 1002:
                    wp_redirect(add_query_arg('permission_error', 1, wp_get_referer()));
                    break;
                default:
                    wp_redirect(add_query_arg('creation_error', 1, wp_get_referer()));
            }
            exit;
        }
    }
}

// Gestione notifiche admin
add_action('admin_notices', function() {
    if ($error = get_transient('eto_max_teams_error')) {
        echo '<div class="notice notice-warning is-dismissible"><p>';
        echo esc_html($error);
        echo '</p></div>';
        delete_transient('eto_max_teams_error');
    }
});