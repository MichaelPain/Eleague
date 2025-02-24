<?php
/**
 * Classe per la gestione del formato Swiss
 */

class ETO_Swiss {
    const BYE_TEAM_ID = -1;

    /**
     * Genera i round iniziali per un torneo Swiss
     */
    public static function generate_initial_round($tournament_id) {
        global $wpdb;

        // Verifica esistenza tabella teams
        if ($wpdb->get_var("SHOW TABLES LIKE '{$wpdb->prefix}eto_teams'") != "{$wpdb->prefix}eto_teams") {
            return new WP_Error('table_missing', __('Tabella teams non trovata', 'eto'));
        }

        // Recupera team ordinati casualmente
        $teams = $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}eto_teams 
            WHERE tournament_id = %d 
            ORDER BY RAND()",
            $tournament_id
        ));

        // Aggiungi BYE se necessario
        $total_teams = count($teams);
        if ($total_teams % 2 !== 0) {
            $teams[] = (object) ['id' => self::BYE_TEAM_ID];
            $total_teams++;
        }

        // Crea accoppiamenti
        $matches = [];
        for ($i = 0; $i < $total_teams; $i += 2) {
            $team1 = $teams[$i];
            $team2 = $teams[$i + 1] ?? (object) ['id' => self::BYE_TEAM_ID];

            $matches[] = [
                'team1_id' => $team1->id,
                'team2_id' => $team2->id
            ];
        }

        // Salva nel database con transazione
        $wpdb->query('START TRANSACTION');
        try {
            foreach ($matches as $match) {
                $wpdb->insert(
                    "{$wpdb->prefix}eto_matches",
                    [
                        'tournament_id' => $tournament_id,
                        'round' => '1',
                        'team1_id' => $match['team1_id'],
                        'team2_id' => $match['team2_id'],
                        'status' => ($match['team2_id'] == self::BYE_TEAM_ID) ? 'completed' : 'pending'
                    ],
                    ['%d', '%s', '%d', '%d', '%s']
                );
            }
            $wpdb->query('COMMIT');
        } catch (Exception $e) {
            $wpdb->query('ROLLBACK');
            return new WP_Error('db_error', $e->getMessage());
        }

        return $matches;
    }

    /**
     * Aggiorna la classifica dopo un match
     */
    public static function update_standings($tournament_id, $winner_id, $loser_id) {
        global $wpdb;

        // Aggiorna vincitore
        $wpdb->query($wpdb->prepare(
            "UPDATE {$wpdb->prefix}eto_teams
            SET wins = wins + 1,
                points_diff = points_diff + 1
            WHERE id = %d AND tournament_id = %d",
            $winner_id,
            $tournament_id
        ));

        // Aggiorna perdente (se non è un BYE)
        if ($loser_id != self::BYE_TEAM_ID) {
            $wpdb->query($wpdb->prepare(
                "UPDATE {$wpdb->prefix}eto_teams
                SET losses = losses + 1,
                    points_diff = points_diff - 1
                WHERE id = %d AND tournament_id = %d",
                $loser_id,
                $tournament_id
            ));
        }
    }

    /**
     * Genera il prossimo round Swiss
     */
    public static function generate_next_round($tournament_id) {
        global $wpdb;

        // Verifica se esiste la tabella delle classifiche
        if ($wpdb->get_var("SHOW TABLES LIKE '{$wpdb->prefix}eto_teams'") != "{$wpdb->prefix}eto_teams") {
            return new WP_Error('table_missing', __('Tabella teams non trovata', 'eto'));
        }

        // Recupera classifica attuale
        $standings = $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}eto_teams
            WHERE tournament_id = %d
            ORDER BY wins DESC, points_diff DESC",
            $tournament_id
        ));

        // Crea accoppiamenti
        $matches = [];
        $used_teams = [];

        foreach ($standings as $index => $team) {
            if (in_array($team->id, $used_teams)) continue;

            // Trova prossimo avversario disponibile
            for ($i = $index + 1; $i < count($standings); $i++) {
                $opponent = $standings[$i];
                
                if (!in_array($opponent->id, $used_teams)) {
                    $matches[] = [
                        'team1_id' => $team->id,
                        'team2_id' => $opponent->id
                    ];
                    array_push($used_teams, $team->id, $opponent->id);
                    break;
                }
            }
        }

        // Determina numero round
        $current_round = $wpdb->get_var($wpdb->prepare(
            "SELECT MAX(round) FROM {$wpdb->prefix}eto_matches 
            WHERE tournament_id = %d",
            $tournament_id
        )) + 1;

        // Salva i match
        $wpdb->query('START TRANSACTION');
        try {
            foreach ($matches as $match) {
                $wpdb->insert(
                    "{$wpdb->prefix}eto_matches",
                    [
                        'tournament_id' => $tournament_id,
                        'round' => $current_round,
                        'team1_id' => $match['team1_id'],
                        'team2_id' => $match['team2_id']
                    ],
                    ['%d', '%s', '%d', '%d']
                );
            }
            $wpdb->query('COMMIT');
        } catch (Exception $e) {
            $wpdb->query('ROLLBACK');
            return new WP_Error('db_error', $e->getMessage());
        }

        return $matches;
    }
}
