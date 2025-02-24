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
    const MAX_SCREENSHOT_SIZE = 5242880; // 5MB

    /**
     * Registra una nuova partita con controlli completi
     */
    public static function create($match_data) {
        global $wpdb;

        // Validazione campi obbligatori
        $required_fields = ['tournament_id', 'round', 'team1_id'];
        foreach ($required_fields as $field) {
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
            'created_at' => current_time('mysql'),
            'updated_at' => current_time('mysql')
        ];

        // Validazioni avanzate
        if ($data['team1_id'] === $data['team2_id']) {
            return new WP_Error('same_teams', 
                __('Una partita non può avere lo stesso team su entrambi i lati', 'eto')
            );
        }

        if (!self::valid_teams($data['team1_id'], $data['team2_id'], $data['tournament_id'])) {
            return new WP_Error('invalid_teams', 
                __('Uno o più team non appartengono a questo torneo', 'eto')
            );
        }

        // Transazione database
        $wpdb->query('START TRANSACTION');

        try {
            $result = $wpdb->insert(
                "{$wpdb->prefix}eto_matches",
                $data,
                [
                    '%d', '%s', '%d', '%d', '%d',
                    '%s', '%d', '%s', '%s', '%s'
                ]
            );

            if (!$result) {
                throw new Exception($wpdb->last_error);
            }

            $match_id = $wpdb->insert_id;

            // Aggiorna statistiche se c'è un vincitore
            if ($data['winner_id']) {
                $update_result = self::update_team_stats(
                    $data['winner_id'], 
                    $data['tournament_id'], 
                    'win'
                );
                
                if (is_wp_error($update_result)) {
                    throw new Exception($update_result->get_error_message());
                }
            }

            // Registra audit log
            ETO_Audit_Log::add([
                'action_type' => 'match_created',
                'object_id' => $match_id,
                'details' => json_encode($data)
            ]);

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
            return new WP_Error('invalid_winner', 
                __('Il team vincitore non partecipa a questa partita', 'eto')
            );
        }

        // Transazione database
        $wpdb->query('START TRANSACTION');

        try {
            // Aggiorna partita
            $result = $wpdb->update(
                "{$wpdb->prefix}eto_matches",
                [
                    'winner_id' => $winner_id,
                    'confirmed_by' => get_current_user_id(),
                    'confirmed_at' => current_time('mysql'),
                    'status' => self::STATUS_COMPLETED,
                    'updated_at' => current_time('mysql')
                ],
                ['id' => $match_id],
                ['%d', '%d', '%s', '%s', '%s'],
                ['%d']
            );

            if ($result === false) {
                throw new Exception($wpdb->last_error);
            }

            // Aggiorna statistiche team
            $loser_id = ($winner_id == $match->team1_id) ? $match->team2_id : $match->team1_id;
            
            self::update_team_stats($winner_id, $match->tournament_id, 'win');
            
            if ($loser_id !== ETO_Swiss::BYE_TEAM_ID) {
                self::update_team_stats($loser_id, $match->tournament_id, 'loss');
            }

            // Registra audit log
            ETO_Audit_Log::add([
                'action_type' => 'match_confirmed',
                'object_id' => $match_id,
                'details' => "Winner: $winner_id, Loser: $loser_id"
            ]);

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
     * Ottieni dettagli completi della partita
     */
    public static function get($match_id) {
        global $wpdb;

        $match = $wpdb->get_row(
            $wpdb->prepare(
                "SELECT * FROM {$wpdb->prefix}eto_matches 
                WHERE id = %d",
                $match_id
            )
        );

        if ($match) {
            $match->team1 = ETO_Team::get($match->team1_id);
            $match->team2 = $match->team2_id ? ETO_Team::get($match->team2_id) : null;
            $match->tournament = ETO_Tournament::get($match->tournament_id);
        }

        return $match;
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

        $matches = $wpdb->get_results($query);

        foreach ($matches as $match) {
            $match->team1 = ETO_Team::get($match->team1_id);
            $match->team2 = $match->team2_id ? ETO_Team::get($match->team2_id) : null;
        }

        return $matches;
    }

    /**
     * Aggiorna le statistiche di un team
     */
    private static function update_team_stats($team_id, $tournament_id, $outcome = 'win') {
        global $wpdb;

        $field = ($outcome === 'win') ? 'wins' : 'losses';
        $points = ($outcome === 'win') ? 1 : -1;

        $result = $wpdb->query(
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

        if ($result === false) {
            return new WP_Error('db_error', $wpdb->last_error);
        }

        return true;
    }

    /**
     * Verifica validità dei team per il torneo
     */
    private static function valid_teams($team1_id, $team2_id, $tournament_id) {
        global $wpdb;

        // Gestione BYE (team2_id = 0)
        if ($team2_id === 0 || $team2_id === null) return true;

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

        // Validazione motivo
        if (empty(trim($reason))) {
            return new WP_Error('invalid_reason', 
                __('È necessario specificare un motivo per la disputa', 'eto')
            );
        }

        $result = $wpdb->update(
            "{$wpdb->prefix}eto_matches",
            [
                'status' => self::STATUS_DISPUTED,
                'dispute_reason' => sanitize_textarea_field($reason),
                'dispute_reported_at' => current_time('mysql'),
                'updated_at' => current_time('mysql')
            ],
            ['id' => $match_id],
            ['%s', '%s', '%s', '%s'],
            ['%d']
        );

        if ($result === false) {
            return new WP_Error('db_error', $wpdb->last_error);
        }

        // Registra audit log
        ETO_Audit_Log::add([
            'action_type' => 'match_disputed',
            'object_id' => $match_id,
            'details' => substr($reason, 0, 500)
        ]);

        return true;
    }

    /**
     * Elabora screenshot caricato
     */
    public static function process_screenshot($file) {
        // Verifica errori di upload
        if ($file['error'] !== UPLOAD_ERR_OK) {
            return new WP_Error('upload_error', 
                __('Errore nel caricamento del file', 'eto')
            );
        }

        // Verifica dimensione
        if ($file['size'] > self::MAX_SCREENSHOT_SIZE) {
            return new WP_Error('file_size', 
                __('La dimensione del file non può superare 5MB', 'eto')
            );
        }

        // Verifica tipo file
        $allowed_types = ['image/jpeg', 'image/png', 'image/gif'];
        if (!in_array($file['type'], $allowed_types)) {
            return new WP_Error('file_type', 
                __('Formato file non supportato. Usa JPEG, PNG o GIF', 'eto')
            );
        }

        // Sposta il file nella directory uploads
        $upload_dir = wp_upload_dir();
        $target_path = $upload_dir['path'] . '/' . basename($file['name']);

        if (!move_uploaded_file($file['tmp_name'], $target_path)) {
            return new WP_Error('move_error', 
                __('Errore nel salvataggio del file', 'eto')
            );
        }

        return $upload_dir['url'] . '/' . basename($file['name']);
    }
}
