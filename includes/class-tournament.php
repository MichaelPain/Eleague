<?php
/**
 * Classe per la gestione completa dei tornei
 * @package eSports Tournament Organizer
 * @since 1.0.0
 */

class ETO_Tournament {
    const FORMAT_SINGLE_ELIMINATION = 'single_elimination';
    const FORMAT_DOUBLE_ELIMINATION = 'double_elimination';
    const FORMAT_SWISS = 'swiss';
    const MIN_PLAYERS = 2;
    const MAX_PLAYERS = 20;

    /**
     * Crea un nuovo torneo con controlli completi
     * @param array $data Dati del torneo
     * @return int|WP_Error ID del torneo o oggetto errore
     */
    public static function create($data) {
        global $wpdb;

        // Verifica permessi utente
        if (!current_user_can('manage_options')) {
            return new WP_Error('unauthorized', __('Permessi insufficienti', 'eto'));
        }

        // Validazione campi obbligatori
        $required_fields = [
            'name' => __('Nome torneo', 'eto'),
            'format' => __('Formato torneo', 'eto'),
            'start_date' => __('Data inizio', 'eto'),
            'end_date' => __('Data fine', 'eto')
        ];

        foreach ($required_fields as $field => $label) {
            if (empty($data[$field])) {
                return new WP_Error('missing_field', 
                    sprintf(__('%s è un campo obbligatorio', 'eto'), $label)
                );
            }
        }

        // Sanitizzazione dati
        $name = sanitize_text_field($data['name']);
        $format = self::sanitize_format($data['format']);
        $start_date = self::sanitize_date($data['start_date']);
        $end_date = self::sanitize_date($data['end_date']);
        $min_players = isset($data['min_players']) ? absint($data['min_players']) : self::MIN_PLAYERS;
        $max_players = isset($data['max_players']) ? absint($data['max_players']) : self::MAX_PLAYERS;
        $checkin_enabled = isset($data['checkin_enabled']) ? 1 : 0;
        $third_place_match = isset($data['third_place_match']) ? 1 : 0;

        // Validazioni avanzate
        if ($format instanceof WP_Error) return $format;
        if ($start_date instanceof WP_Error) return $start_date;
        if ($end_date instanceof WP_Error) return $end_date;

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

        if ($start_date >= $end_date) {
            return new WP_Error('invalid_dates',
                __('La data di fine deve essere successiva alla data di inizio', 'eto')
            );
        }

        // Transazione database
        $wpdb->query('START TRANSACTION');

        try {
            $insert_result = $wpdb->insert(
                "{$wpdb->prefix}eto_tournaments",
                [
                    'name' => $name,
                    'format' => $format,
                    'start_date' => $start_date->format('Y-m-d H:i:s'),
                    'end_date' => $end_date->format('Y-m-d H:i:s'),
                    'min_players' => $min_players,
                    'max_players' => $max_players,
                    'checkin_enabled' => $checkin_enabled,
                    'third_place_match' => $third_place_match,
                    'status' => 'pending',
                    'created_at' => current_time('mysql')
                ],
                [
                    '%s', // name
                    '%s', // format
                    '%s', // start_date
                    '%s', // end_date
                    '%d', // min_players
                    '%d', // max_players
                    '%d', // checkin_enabled
                    '%d', // third_place_match
                    '%s', // status
                    '%s'  // created_at
                ]
            );

            if (!$insert_result) {
                throw new Exception($wpdb->last_error);
            }

            $tournament_id = $wpdb->insert_id;

            // Creazione automatica del bracket per alcuni formati
            if (in_array($format, [self::FORMAT_SINGLE_ELIMINATION, self::FORMAT_DOUBLE_ELIMINATION])) {
                $bracket_result = self::generate_initial_bracket($tournament_id);
                if (is_wp_error($bracket_result)) {
                    throw new Exception($bracket_result->get_error_message());
                }
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

    /**
     * Genera il bracket iniziale per il torneo
     */
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

        switch ($tournament->format) {
            case self::FORMAT_SINGLE_ELIMINATION:
                $bracket = self::generate_single_elimination_bracket($team_ids);
                break;

            case self::FORMAT_DOUBLE_ELIMINATION:
                $bracket = self::generate_double_elimination_bracket($team_ids);
                break;

            case self::FORMAT_SWISS:
                if (!class_exists('ETO_Swiss')) {
                    require_once ETO_PLUGIN_DIR . 'includes/class-swiss.php';
                }
                $bracket = ETO_Swiss::generate_initial_round($tournament_id);
                break;

            default:
                return new WP_Error('invalid_format', 
                    __('Formato del torneo non supportato', 'eto')
                );
        }

        // Salvataggio matches
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

    /**
     * Ottieni dettagli torneo
     */
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

    /**
     * Genera bracket per Single Elimination
     */
    private static function generate_single_elimination_bracket($team_ids) {
        $count = count($team_ids);
        $nearest_power = 2 ** ceil(log($count, 2));
        $byes = $nearest_power - $count;

        // Aggiungi BYE se necessario
        for ($i = 0; $i < $byes; $i++) {
            $team_ids[] = ETO_Swiss::BYE_TEAM_ID;
        }

        shuffle($team_ids);
        $matches = [];
        $total_rounds = log($nearest_power, 2);

        for ($round = 1; $round <= $total_rounds; $round++) {
            $round_matches = [];
            $chunk_size = count($team_ids) / 2;

            foreach (array_chunk($team_ids, $chunk_size) as $pair) {
                $round_matches[] = [
                    'team1_id' => $pair[0],
                    'team2_id' => $pair[1] ?? ETO_Swiss::BYE_TEAM_ID
                ];
            }

            $matches["Round {$round}"] = $round_matches;
            $team_ids = array_fill(0, $chunk_size, null);
        }

        return $matches;
    }

    /**
     * Sanitizza e valida il formato del torneo
     */
    private static function sanitize_format($format) {
        $allowed_formats = [
            self::FORMAT_SINGLE_ELIMINATION,
            self::FORMAT_DOUBLE_ELIMINATION,
            self::FORMAT_SWISS
        ];

        $clean_format = sanitize_key($format);
        
        if (!in_array($clean_format, $allowed_formats)) {
            return new WP_Error('invalid_format',
                sprintf(
                    __('Formato non valido. Scegli tra: %s', 'eto'),
                    implode(', ', $allowed_formats)
                )
            );
        }

        return $clean_format;
    }

    /**
     * Sanitizza e valida una data
     */
    private static function sanitize_date($date_string) {
        try {
            $date = new DateTime($date_string);
            $now = new DateTime('now', new DateTimeZone(wp_timezone_string()));
            
            if ($date < $now) {
                return new WP_Error('past_date',
                    __('La data non può essere nel passato', 'eto')
                );
            }

            return $date;
        } catch (Exception $e) {
            return new WP_Error('invalid_date',
                __('Formato data non valido. Usa YYYY-MM-DD HH:MM', 'eto')
            );
        }
    }

    /**
     * Conta i tornei per stato
     */
    public static function count($status = null) {
        global $wpdb;

        $query = "SELECT COUNT(*) FROM {$wpdb->prefix}eto_tournaments";
        $params = [];

        if ($status) {
            $query .= " WHERE status = %s";
            $params[] = $status;
        }

        return $wpdb->get_var(
            $params ? $wpdb->prepare($query, $params) : $query
        );
    }

    /**
     * Aggiorna lo stato di un torneo
     */
    public static function update_status($tournament_id, $new_status) {
        global $wpdb;

        $allowed_statuses = ['pending', 'active', 'completed', 'cancelled'];
        $new_status = sanitize_key($new_status);

        if (!in_array($new_status, $allowed_statuses)) {
            return new WP_Error('invalid_status',
                __('Stato del torneo non valido', 'eto')
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
}
