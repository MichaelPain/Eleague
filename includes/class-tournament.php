<?php
class ETO_Tournament {
    // Crea un nuovo torneo
    public static function create($data) {
        global $wpdb;

        // Sanitizzazione input
        $name = sanitize_text_field($data['name']);
        $format = sanitize_key($data['format']);
        $start_date = sanitize_text_field($data['start_date']);
        $end_date = sanitize_text_field($data['end_date']);
        $min_players = absint($data['min_players']);
        $max_players = absint($data['max_players']);
        $checkin_enabled = isset($data['checkin_enabled']) ? 1 : 0;
        $third_place_match = isset($data['third_place_match']) ? 1 : 0;

        // Validazione
        if (!in_array($format, ['single_elimination', 'double_elimination', 'swiss'])) {
            return new WP_Error('invalid_format', 'Formato torneo non valido');
        }

        if (strtotime($start_date) >= strtotime($end_date)) {
            return new WP_Error('invalid_dates', 'La data di fine deve essere successiva all\'inizio');
        }

        $result = $wpdb->insert(
            "{$wpdb->prefix}eto_tournaments",
            [
                'name' => $name,
                'format' => $format,
                'start_date' => $start_date,
                'end_date' => $end_date,
                'min_players' => $min_players,
                'max_players' => $max_players,
                'checkin_enabled' => $checkin_enabled,
                'third_place_match' => $third_place_match
            ],
            ['%s', '%s', '%s', '%s', '%d', '%d', '%d', '%d']
        );

        return $result ? $wpdb->insert_id : new WP_Error('db_error', 'Errore nel database');
    }

    // Genera il bracket per un torneo
    public static function generate_bracket($tournament_id) {
        global $wpdb;
        
        $teams = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT id FROM {$wpdb->prefix}eto_teams 
                WHERE tournament_id = %d AND status = 'checked_in'",
                $tournament_id
            ),
            ARRAY_A
        );

        $team_ids = array_column($teams, 'id');
        $bracket = [];

        switch (self::get_format($tournament_id)) {
            case 'single_elimination':
                $bracket = self::generate_single_elimination_bracket($team_ids);
                break;
            case 'double_elimination':
                $bracket = self::generate_double_elimination_bracket($team_ids);
                break;
            case 'swiss':
                require_once ETO_PLUGIN_DIR . 'includes/class-swiss.php';
                $bracket = ETO_Swiss::generate_round($tournament_id, 1);
                break;
        }

        // Salva nel database
        foreach ($bracket as $round => $matches) {
            foreach ($matches as $match) {
                $wpdb->insert(
                    "{$wpdb->prefix}eto_matches",
                    [
                        'tournament_id' => $tournament_id,
                        'round' => $round,
                        'team1_id' => $match[0],
                        'team2_id' => !empty($match[1]) ? $match[1] : null
                    ],
                    ['%d', '%s', '%d', '%d']
                );
            }
        }

        return $bracket;
    }

    // Ottieni formato torneo
    public static function get_format($tournament_id) {
        global $wpdb;
        return $wpdb->get_var(
            $wpdb->prepare(
                "SELECT format FROM {$wpdb->prefix}eto_tournaments 
                WHERE id = %d",
                $tournament_id
            )
        );
    }

    // Logica per Single Elimination
    private static function generate_single_elimination_bracket($teams) {
        $bracket = [];
        $total_teams = count($teams);
        $rounds = log($total_teams, 2);
        
        for ($i = 0; $i < $rounds; $i++) {
            $matches = [];
            foreach (array_chunk($teams, 2) as $pair) {
                $matches[] = [$pair[0], isset($pair[1]) ? $pair[1] : 'BYE'];
            }
            $bracket["Round " . ($i + 1)] = $matches;
            $teams = array_fill(0, $total_teams / 2, null); // Simula vincitori
        }

        return $bracket;
    }
}
