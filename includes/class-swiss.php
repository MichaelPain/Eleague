<?php
/**
 * Classe per la gestione del formato Swiss
 * @package eSports Tournament Organizer
 * @since 1.1.0
 */

class ETO_Swiss {
    const BYE_TEAM_ID = -1;
    const MAX_ROUNDS = 5;
    const MIN_TEAMS = 4;

    /**
     * Genera i round iniziali per un torneo Swiss
     */
    public static function generate_initial_round($tournament_id) {
        global $wpdb;

        try {
            // Verifica esistenza tabella teams
            if ($wpdb->get_var("SHOW TABLES LIKE '{$wpdb->prefix}eto_teams'") != "{$wpdb->prefix}eto_teams") {
                throw new Exception(__('Tabella teams non trovata', 'eto'));
            }

            // Recupera team iscritti
            $teams = $wpdb->get_results($wpdb->prepare(
                "SELECT id, name, wins, points_diff 
                FROM {$wpdb->prefix}eto_teams 
                WHERE tournament_id = %d 
                AND status = 'checked_in'",
                $tournament_id
            ));

            // Verifica numero minimo team
            if (count($teams) < self::MIN_TEAMS) {
                throw new Exception(
                    sprintf(__('Minimo %d team richiesti per il formato Swiss', 'eto'), self::MIN_TEAMS)
                );
            }

            // Aggiungi BYE se dispari
            if (count($teams) % 2 !== 0) {
                $teams[] = (object) [
                    'id' => self::BYE_TEAM_ID,
                    'name' => 'BYE',
                    'wins' => 0,
                    'points_diff' => 0
                ];
            }

            // Mescola i team mantenendo il seeding
            usort($teams, function($a, $b) {
                return rand(-1, 1);
            });

            // Crea accoppiamenti
            $matches = [];
            $total_teams = count($teams);
            
            for ($i = 0; $i < $total_teams; $i += 2) {
                $team1 = $teams[$i];
                $team2 = $teams[$i + 1] ?? (object) ['id' => self::BYE_TEAM_ID];

                $matches[] = [
                    'team1_id' => $team1->id,
                    'team2_id' => $team2->id,
                    'team1_data' => $team1,
                    'team2_data' => $team2
                ];
            }

            // Salvataggio transazionale
            $wpdb->query('START TRANSACTION');
            
            foreach ($matches as $match) {
                $status = ($match['team2_id'] === self::BYE_TEAM_ID) ? 'completed' : 'pending';
                
                $wpdb->insert(
                    "{$wpdb->prefix}eto_matches",
                    [
                        'tournament_id' => $tournament_id,
                        'round' => 1,
                        'team1_id' => $match['team1_id'],
                        'team2_id' => $match['team2_id'],
                        'status' => $status,
                        'created_at' => current_time('mysql')
                    ],
                    ['%d', '%s', '%d', '%d', '%s', '%s']
                );

                // Aggiorna BYE automaticamente
                if ($status === 'completed') {
                    self::update_standings($tournament_id, $match['team1_id'], self::BYE_TEAM_ID);
                }
            }

            $wpdb->query('COMMIT');
            return $matches;

        } catch (Exception $e) {
            $wpdb->query('ROLLBACK');
            return new WP_Error('swiss_error', $e->getMessage());
        }
    }

    /**
     * Genera il prossimo round Swiss con accoppiamenti bilanciati
     */
    public static function generate_next_round($tournament_id) {
        global $wpdb;

        try {
            // Recupera classifica corrente con punti
            $standings = $wpdb->get_results($wpdb->prepare(
                "SELECT id, name, wins, points_diff 
                FROM {$wpdb->prefix}eto_teams 
                WHERE tournament_id = %d 
                ORDER BY wins DESC, points_diff DESC",
                $tournament_id
            ));

            // Verifica numero team pari
            if (count($standings) % 2 !== 0) {
                $standings[] = (object) [
                    'id' => self::BYE_TEAM_ID,
                    'name' => 'BYE',
                    'wins' => 0,
                    'points_diff' => 0
                ];
            }

            // Crea accoppiamenti con algoritmo Burstein
            $matches = [];
            $used_teams = [];
            $total_teams = count($standings);

            for ($i = 0; $i < $total_teams; $i += 2) {
                $team1 = $standings[$i];
                $team2 = $standings[$i + 1];

                // Evita rematch
                if (self::have_played_before($team1->id, $team2->id, $tournament_id)) {
                    // Trova il prossimo team disponibile
                    for ($j = $i + 2; $j < $total_teams; $j++) {
                        if (!in_array($standings[$j]->id, $used_teams)) {
                            $team2 = $standings[$j];
                            break;
                        }
                    }
                }

                $matches[] = [
                    'team1_id' => $team1->id,
                    'team2_id' => $team2->id
                ];

                $used_teams[] = $team1->id;
                $used_teams[] = $team2->id;
            }

            // Determina numero round
            $current_round = $wpdb->get_var($wpdb->prepare(
                "SELECT MAX(round) FROM {$wpdb->prefix}eto_matches 
                WHERE tournament_id = %d",
                $tournament_id
            )) + 1;

            // Salva i nuovi match
            $wpdb->query('START TRANSACTION');
            foreach ($matches as $match) {
                $wpdb->insert(
                    "{$wpdb->prefix}eto_matches",
                    [
                        'tournament_id' => $tournament_id,
                        'round' => $current_round,
                        'team1_id' => $match['team1_id'],
                        'team2_id' => $match['team2_id'],
                        'status' => 'pending',
                        'created_at' => current_time('mysql')
                    ],
                    ['%d', '%s', '%d', '%d', '%s', '%s']
                );
            }
            $wpdb->query('COMMIT');

            return $matches;

        } catch (Exception $e) {
            $wpdb->query('ROLLBACK');
            return new WP_Error('swiss_error', $e->getMessage());
        }
    }

    /**
     * Aggiorna le classifiche dopo un match
     */
    public static function update_standings($tournament_id, $winner_id, $loser_id) {
        global $wpdb;

        try {
            // Aggiorna vincitore
            $wpdb->query($wpdb->prepare(
                "UPDATE {$wpdb->prefix}eto_teams
                SET wins = wins + 1,
                    points_diff = points_diff + 1
                WHERE id = %d AND tournament_id = %d",
                $winner_id,
                $tournament_id
            ));

            // Aggiorna perdente (se non BYE)
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

            // Registra audit log
            ETO_Audit_Log::add([
                'action_type' => 'swiss_standings_updated',
                'object_id' => $tournament_id,
                'details' => "Vincitore: $winner_id, Perdente: $loser_id"
            ]);

            return true;

        } catch (Exception $e) {
            return new WP_Error('db_error', $e->getMessage());
        }
    }

    /**
     * Verifica se due team si sono già affrontati
     */
    private static function have_played_before($team1_id, $team2_id, $tournament_id) {
        global $wpdb;

        return $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) 
            FROM {$wpdb->prefix}eto_matches 
            WHERE tournament_id = %d 
            AND (
                (team1_id = %d AND team2_id = %d) OR
                (team1_id = %d AND team2_id = %d)
            )",
            $tournament_id,
            $team1_id, $team2_id,
            $team2_id, $team1_id
        )) > 0;
    }

    /**
     * Ottieni lo storico dei match per un team
     */
    public static function get_team_history($team_id, $tournament_id) {
        global $wpdb;

        return $wpdb->get_results($wpdb->prepare(
            "SELECT * 
            FROM {$wpdb->prefix}eto_matches 
            WHERE tournament_id = %d 
            AND (team1_id = %d OR team2_id = %d)
            ORDER BY round ASC",
            $tournament_id,
            $team_id,
            $team_id
        ));
    }

    /**
     * Calcola i tiebreaker per i team a parità di punti
     */
    public static function calculate_tiebreakers($tournament_id) {
        global $wpdb;

        // Calcola Buchholz system
        $wpdb->query($wpdb->prepare(
            "UPDATE {$wpdb->prefix}eto_teams t
            JOIN (
                SELECT team_id, SUM(points) AS buchholz
                FROM (
                    SELECT m.team1_id AS team_id, t2.points_diff AS points
                    FROM {$wpdb->prefix}eto_matches m
                    JOIN {$wpdb->prefix}eto_teams t2 ON m.team2_id = t2.id
                    WHERE m.tournament_id = %d
                    
                    UNION ALL
                    
                    SELECT m.team2_id AS team_id, t1.points_diff AS points
                    FROM {$wpdb->prefix}eto_matches m
                    JOIN {$wpdb->prefix}eto_teams t1 ON m.team1_id = t1.id
                    WHERE m.tournament_id = %d
                ) AS opp
                GROUP BY team_id
            ) AS b ON t.id = b.team_id
            SET t.tiebreaker = b.buchholz
            WHERE t.tournament_id = %d",
            $tournament_id,
            $tournament_id,
            $tournament_id
        ));
    }
}
