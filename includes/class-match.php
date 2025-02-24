<?php
/**
 * Classe per la gestione avanzata delle partite
 * @package eSports Tournament Organizer
 * @since 1.0.0
 */

class ETO_Match {
    const STATUS_PENDING = 'pending';
    const STATUS_COMPLETED = 'completed';
    const STATUS_DISPUTED = 'disputed';

    /**
     * Registra una nuova partita
     * @param array $match_data Dati della partita
     * @return int|WP_Error ID della partita o errore
     */
    public static function create($match_data) {
        global $wpdb;

        // Validazione dati obbligatori
        $required = ['tournament_id', 'round', 'team1_id'];
        foreach ($required as $field) {
            if (empty($match_data[$field])) {
                return new WP_Error('missing_field', 
                    sprintf(__('Campo obbligatorio mancante: %s', 'eto'), $field)
                );
            }
        }

        // Sanitizzazione dati
        $data = [
            'tournament_id' => absint($match_data['tournament_id']),
            'round' => sanitize_text_field($match_data['round']),
            'team1_id' => absint($match_data['team1_id']),
            'team2_id' => isset($match_data['team2_id']) ? absint($match_data['team2_id']) : null,
            'winner_id' => isset($match_data['winner_id']) ? absint($match_data['winner_id']) : null,
            'screenshot_url' => esc_url_raw($match_data['screenshot_url'] ?? ''),
            'reported_by' => get_current_user_id(),
            'status' => self::STATUS_PENDING,
            'created_at' => current_time('mysql')
        ];

        // Validazioni avanzate
        if ($data['team1_id'] === $data['team2_id']) {
            return new WP_Error('same_teams', __('Una partita non può avere lo stesso team su entrambi i lati', 'eto'));
        }

        if (!self::valid_teams($data['team1_id'], $data['team2_id'], $data['tournament_id'])) {
            return new WP_Error('invalid_teams', __('Uno o più team non appartengono a questo torneo', 'eto'));
        }

        // Inserimento nel database con transazione
        $wpdb->query('START TRANSACTION');

        try {
            $result = $wpdb->insert(
                "{$wpdb->prefix}eto_matches",
                $data,
                [
                    '%d', // tournament_id
                    '%s', // round
                    '%d', // team1_id
                    '%d', // team2_id
                    '%d', // winner_id
                    '%s', // screenshot_url
                    '%d', // reported_by
                    '%s', // status
                    '%s'  // created_at
                ]
            );

            if (!$result) {
                throw new Exception($wpdb->last_error);
            }

            $match_id = $wpdb->insert_id;

            // Aggiorna statistiche team se c'è un vincitore
            if ($data['winner_id']) {
                $update_result = self::update_team_stats($data['winner_id'], $data['tournament_id']);
                if (is_wp_error($update_result)) {
                    throw new Exception($update_result->get_error_message());
                }
            }

            $wpdb->query('COMMIT');
            return $match_id;

        } catch (Exception $e) {
            $wpdb->query('ROLLBACK');
            return new WP_Error('db_error', 
                __('Errore durante la creazione della partita: ', 'eto') . $e->getMessage()
            );
        }
    }

    /**
     * Conferma una partita con controlli completi
     */
    public static function confirm($match_id, $winner_id) {
        global $wpdb;

        // Verifica esistenza partita
        $match = self::get($match_id);
        if (!$match) {
            return new WP_Error('not_found', __('Partita non trovata', 'eto'));
        }

        // Verifica validità vincitore
        if (!in_array($winner_id, [$match->team1_id, $match->team2_id])) {
            return new WP_Error('invalid_winner', __('Il team vincitore non partecipa a questa partita', 'eto'));
        }

        // Aggiornamento con transazione
        $wpdb->query('START TRANSACTION');

        try {
            // Aggiorna partita
            $result = $wpdb->update(
                "{$wpdb->prefix}eto_matches",
                [
                    'winner_id' => $winner_id,
                    'confirmed_by' => get_current_user_id(),
                    'confirmed_at' => current_time('mysql'),
                    'status' => self::STATUS_COMPLETED
                ],
                ['id' => $match_id],
                ['%d', '%d', '%s', '%s'],
                ['%d']
            );

            if ($result === false) {
                throw new Exception($wpdb->last_error);
            }

            // Aggiorna statistiche team
            $loser_id = ($winner_id == $match->team1_id) ? $match->team2_id : $match->team1_id;
            
            self::update_team_stats($winner_id, $match->tournament_id, 'win');
            self::update_team_stats($loser_id, $match->tournament_id, 'loss');

            $wpdb->query('COMMIT');
            return true;

        } catch (Exception $e) {
            $wpdb->query('ROLLBACK');
            return new WP_Error('db_error', 
                __('Errore durante la conferma della partita: ', 'eto') . $e->getMessage()
            );
        }
    }

    /**
     * Ottieni i dettagli di una partita
     */
    public static function get($match_id) {
        global $wpdb;

        return $wpdb->get_row(
            $wpdb->prepare(
                "SELECT * FROM {$wpdb->prefix}eto_matches 
                WHERE id = %d",
                $match_id
            )
        );
    }

    /**
     * Ottieni tutte le partite di un torneo
     */
    public static function get_by_tournament($tournament_id, $status = null) {
        global $wpdb;

        $query = $wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}eto_matches 
            WHERE tournament_id = %d",
            $tournament_id
        );

        if ($status) {
            $query .= $wpdb->prepare(" AND status = %s", $status);
        }

        return $wpdb->get_results($query);
    }

    /**
     * Aggiorna le statistiche di un team
     */
    private static function update_team_stats($team_id, $tournament_id, $outcome = 'win') {
        global $wpdb;

        $field = ($outcome === 'win') ? 'wins' : 'losses';
        $points = ($outcome === 'win') ? 1 : -1;

        return $wpdb->query(
            $wpdb->prepare(
                "UPDATE {$wpdb->prefix}eto_teams 
                SET {$field} = {$field} + 1, 
                    points_diff = points_diff + %d 
                WHERE id = %d AND tournament_id = %d",
                $points,
                $team_id,
                $tournament_id
            )
        );
    }

    /**
     * Verifica validità dei team per il torneo
     */
    private static function valid_teams($team1_id, $team2_id, $tournament_id) {
        global $wpdb;

        $query = $wpdb->prepare(
            "SELECT COUNT(*) FROM {$wpdb->prefix}eto_teams 
            WHERE tournament_id = %d 
            AND id IN (%d, %d)",
            $tournament_id,
            $team1_id,
            $team2_id
        );

        return $wpdb->get_var($query) === 2;
    }

    /**
     * Conta le partite in sospeso
     */
    public static function count_pending() {
        global $wpdb;

        return $wpdb->get_var(
            "SELECT COUNT(*) FROM {$wpdb->prefix}eto_matches 
            WHERE status = 'pending'"
        );
    }

    /**
     * Segnala una disputa per la partita
     */
    public static function dispute($match_id, $reason) {
        global $wpdb;

        return $wpdb->update(
            "{$wpdb->prefix}eto_matches",
            [
                'status' => self::STATUS_DISPUTED,
                'dispute_reason' => sanitize_textarea_field($reason),
                'dispute_reported_at' => current_time('mysql')
            ],
            ['id' => $match_id],
            ['%s', '%s', '%s'],
            ['%d']
        );
    }
}
