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

        try {
            // Verifica permessi
            if (!current_user_can('manage_eto_matches')) {
                throw new Exception(__('Permessi insufficienti', 'eto'));
            }

            // Validazione campi obbligatori
            $required_fields = ['tournament_id', 'round', 'team1_id'];
            foreach ($required_fields as $field) {
                if (empty($match_data[$field])) {
                    throw new Exception(
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
                throw new Exception(__('Una partita non può avere lo stesso team su entrambi i lati', 'eto'));
            }

            if (!self::valid_teams($data['team1_id'], $data['team2_id'], $data['tournament_id'])) {
                throw new Exception(__('Uno o più team non appartengono a questo torneo', 'eto'));
            }

            // Transazione database
            $wpdb->query('START TRANSACTION');

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
                    '%s', // created_at
                    '%s'  // updated_at
                ]
            );

            if (!$result) {
                error_log('[ETO] Errore DB: ' . $wpdb->last_error);
                throw new Exception(__('Errore durante la creazione della partita', 'eto'));
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
            error_log("[ETO] Partita #$match_id creata con successo");
            return $match_id;

        } catch (Exception $e) {
            $wpdb->query('ROLLBACK');
            error_log('[ETO] Errore creazione partita: ' . $e->getMessage());
            return new WP_Error('db_error', $e->getMessage());
        }
    }

    /**
     * Conferma una partita con controlli completi
     */
    public static function confirm($match_id, $winner_id) {
        global $wpdb;

        try {
            error_log("[ETO] Tentativo conferma partita #$match_id");

            $match = self::get($match_id);
            if (!$match) {
                throw new Exception(__('Partita non trovata', 'eto'));
            }

            if (!in_array($winner_id, [$match->team1_id, $match->team2_id])) {
                throw new Exception(__('Il team vincitore non partecipa a questa partita', 'eto'));
            }

            $wpdb->query('START TRANSACTION');

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
                error_log("[ETO] Errore aggiornamento: " . $wpdb->last_error);
                throw new Exception(__('Errore durante la conferma', 'eto'));
            }

            // Aggiorna statistiche
            $loser_id = ($winner_id == $match->team1_id) ? $match->team2_id : $match->team1_id;
            
            self::update_team_stats($winner_id, $match->tournament_id, 'win');
            
            if ($loser_id !== 0) { // 0 = BYE
                self::update_team_stats($loser_id, $match->tournament_id, 'loss');
            }

            ETO_Audit_Log::add([
                'action_type' => 'match_confirmed',
                'object_id' => $match_id,
                'details' => "Vincitore: $winner_id, Sconfitto: $loser_id"
            ]);

            $wpdb->query('COMMIT');
            error_log("[ETO] Partita #$match_id confermata");
            return true;

        } catch (Exception $e) {
            $wpdb->query('ROLLBACK');
            error_log("[ETO] Errore conferma partita: " . $e->getMessage());
            return new WP_Error('match_error', $e->getMessage());
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
     * Aggiorna le statistiche dei team
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
            error_log("[ETO] Errore aggiornamento statistiche team $team_id: " . $wpdb->last_error);
            return new WP_Error('db_error', $wpdb->last_error);
        }

        return true;
    }

    /**
     * Verifica validità team per il torneo
     */
    private static function valid_teams($team1_id, $team2_id, $tournament_id) {
        global $wpdb;

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
     * Segnala una disputa per la partita
     */
    public static function dispute($match_id, $reason) {
        global $wpdb;

        try {
            if (empty(trim($reason))) {
                throw new Exception(__('Specificare un motivo per la disputa', 'eto'));
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
                throw new Exception($wpdb->last_error);
            }

            ETO_Audit_Log::add([
                'action_type' => 'match_disputed',
                'object_id' => $match_id,
                'details' => substr($reason, 0, 500)
            ]);

            error_log("[ETO] Disputa registrata per partita #$match_id");
            return true;

        } catch (Exception $e) {
            error_log("[ETO] Errore disputa partita: " . $e->getMessage());
            return new WP_Error('dispute_error', $e->getMessage());
        }
    }

    /**
     * Processa screenshot con controlli completi
     */
    public static function process_screenshot($file) {
        try {
            if ($file['error'] !== UPLOAD_ERR_OK) {
                throw new Exception(__('Errore nel caricamento del file', 'eto'));
            }

            if ($file['size'] > self::MAX_SCREENSHOT_SIZE) {
                throw new Exception(__('Dimensione massima consentita: 5MB', 'eto'));
            }

            $file_info = wp_check_filetype($file['name']);
            if (!in_array($file_info['type'], ['image/jpeg', 'image/png', 'image/gif'])) {
                throw new Exception(__('Formato file non supportato', 'eto'));
            }

            $upload_dir = wp_upload_dir();
            $target_path = $upload_dir['path'] . '/' . sanitize_file_name($file['name']);

            if (!move_uploaded_file($file['tmp_name'], $target_path)) {
                throw new Exception(__('Errore nel salvataggio del file', 'eto'));
            }

            error_log("[ETO] Screenshot salvato: " . $target_path);
            return $upload_dir['url'] . '/' . basename($file['name']);

        } catch (Exception $e) {
            error_log("[ETO] Errore processamento screenshot: " . $e->getMessage());
            return new WP_Error('screenshot_error', $e->getMessage());
        }
    }
}
