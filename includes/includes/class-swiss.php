<?php
class ETO_Swiss {
    const BYE_TEAM_ID = 0;
    const MAX_ROUNDS = 10; // Limite massimo di sicurezza
    const MIN_TEAMS = 4;
    const MAX_REMATCH_ATTEMPTS = 3; // Tentativi massimi per evitare rematch

    public static function generate_initial_round($tournament_id) {
        global $wpdb;

        try {
            error_log("[ETO] Generazione round iniziale Swiss per torneo $tournament_id");

            $tournament = ETO_Tournament::get($tournament_id);
            if (!$tournament || $tournament->format !== 'swiss') {
                throw new Exception(__('Torneo Swiss non valido', 'eto'));
            }

            // Verifica limite massimo round
            if (self::get_current_round($tournament_id) >= self::MAX_ROUNDS) {
                throw new Exception(__('Raggiunto il numero massimo di round', 'eto'));
            }

            $teams = $wpdb->get_results(
                $wpdb->prepare(
                    "SELECT id, wins, points_diff 
                    FROM {$wpdb->prefix}eto_teams 
                    WHERE tournament_id = %d 
                    AND status = 'checked_in' 
                    ORDER BY RAND()",
                    $tournament_id
                ),
                ARRAY_A
            );

            if (count($teams) < self::MIN_TEAMS) {
                throw new Exception(
                    sprintf(__('Minimo %d team richiesti per il formato Swiss', 'eto'), self::MIN_TEAMS)
                );
            }

            if (count($teams) % 2 !== 0) {
                $teams[] = ['id' => self::BYE_TEAM_ID, 'wins' => 0, 'points_diff' => 0];
                error_log("[ETO] Aggiunto BYE al torneo $tournament_id");
            }

            $matches = [];
            $total_teams = count($teams);
            
            for ($i = 0; $i < $total_teams; $i += 2) {
                $team1 = $teams[$i];
                $team2 = $teams[$i + 1] ?? ['id' => self::BYE_TEAM_ID];

                $matches[] = [
                    'team1_id' => $team1['id'],
                    'team2_id' => $team2['id']
                ];
            }

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

                if ($status === 'completed') {
                    ETO_Match::update_team_stats($match['team1_id'], $tournament_id, 'win');
                    error_log("[ETO] Assegnata vittoria BYE al team {$match['team1_id']}");
                }
            }

            $wpdb->query('COMMIT');
            error_log("[ETO] Creati " . count($matches) . " match per il round iniziale");
            return $matches;

        } catch (Exception $e) {
            $wpdb->query('ROLLBACK');
            error_log("[ETO] Errore generazione round: " . $e->getMessage());
            return new WP_Error('swiss_error', $e->getMessage());
        }
    }

    public static function generate_next_round($tournament_id) {
        global $wpdb;

        try {
            error_log("[ETO] Generazione nuovo round Swiss per torneo $tournament_id");

            // Verifica limite massimo round
            $current_round = self::get_current_round($tournament_id);
            if ($current_round >= self::MAX_ROUNDS) {
                throw new Exception(__('Raggiunto il numero massimo di round', 'eto'));
            }

            $standings = $wpdb->get_results(
                $wpdb->prepare(
                    "SELECT id, wins, points_diff 
                    FROM {$wpdb->prefix}eto_teams 
                    WHERE tournament_id = %d 
                    ORDER BY wins DESC, points_diff DESC",
                    $tournament_id
                ),
                ARRAY_A
            );

            if (count($standings) % 2 !== 0) {
                $standings[] = ['id' => self::BYE_TEAM_ID, 'wins' => 0, 'points_diff' => 0];
                error_log("[ETO] Aggiunto BYE per bilanciare i team");
            }

            $matches = [];
            $used_teams = [];
            $total_teams = count($standings);

            for ($i = 0; $i < $total_teams; $i++) {
                if (in_array($standings[$i]['id'], $used_teams)) continue;

                $team1 = $standings[$i];
                $found_match = false;
                $attempts = 0;

                for ($j = $i + 1; $j < $total_teams && $attempts < self::MAX_REMATCH_ATTEMPTS; $j++) {
                    if (in_array($standings[$j]['id'], $used_teams)) continue;

                    if (!self::have_played_before($team1['id'], $standings[$j]['id'], $tournament_id)) {
                        $team2 = $standings[$j];
                        $found_match = true;
                        break;
                    }
                    $attempts++;
                }

                if (!$found_match) {
                    // Fallback: accoppia con il prossimo disponibile
                    for ($j = $i + 1; $j < $total_teams; $j++) {
                        if (!in_array($standings[$j]['id'], $used_teams)) {
                            $team2 = $standings[$j];
                            error_log("[ETO] Rematch inevitabile tra {$team1['id']} e {$team2['id']}");
                            break;
                        }
                    }
                }

                $matches[] = [
                    'team1_id' => $team1['id'],
                    'team2_id' => $team2['id'] ?? self::BYE_TEAM_ID
                ];

                $used_teams[] = $team1['id'];
                $used_teams[] = $team2['id'] ?? self::BYE_TEAM_ID;
            }

            $new_round = $current_round + 1;
            
            $wpdb->query('START TRANSACTION');
            foreach ($matches as $match) {
                $wpdb->insert(
                    "{$wpdb->prefix}eto_matches",
                    [
                        'tournament_id' => $tournament_id,
                        'round' => $new_round,
                        'team1_id' => $match['team1_id'],
                        'team2_id' => $match['team2_id'],
                        'status' => 'pending',
                        'created_at' => current_time('mysql')
                    ],
                    ['%d', '%s', '%d', '%d', '%s', '%s']
                );
            }
            $wpdb->query('COMMIT');

            error_log("[ETO] Creato round $new_round con " . count($matches) . " partite");
            return $matches;

        } catch (Exception $e) {
            $wpdb->query('ROLLBACK');
            error_log("[ETO] Errore generazione round: " . $e->getMessage());
            return new WP_Error('swiss_error', $e->getMessage());
        }
    }

    private static function have_played_before($team1_id, $team2_id, $tournament_id) {
        global $wpdb;

        return $wpdb->get_var(
            $wpdb->prepare(
                "SELECT COUNT(*) FROM {$wpdb->prefix}eto_matches 
                WHERE tournament_id = %d 
                AND (
                    (team1_id = %d AND team2_id = %d) OR
                    (team1_id = %d AND team2_id = %d)
                )",
                $tournament_id,
                $team1_id, $team2_id,
                $team2_id, $team1_id
            )
        ) > 0;
    }

    public static function calculate_tiebreakers($tournament_id) {
        global $wpdb;

        try {
            error_log("[ETO] Calcolo tiebreaker per torneo $tournament_id");

            $wpdb->query(
                $wpdb->prepare(
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
                )
            );

            error_log("[ETO] Tiebreaker calcolati con successo");
            return true;

        } catch (Exception $e) {
            error_log("[ETO] Errore calcolo tiebreaker: " . $e->getMessage());
            return new WP_Error('tiebreaker_error', $e->getMessage());
        }
    }

    private static function get_current_round($tournament_id) {
        global $wpdb;
        
        return (int)$wpdb->get_var(
            $wpdb->prepare(
                "SELECT MAX(round) FROM {$wpdb->prefix}eto_matches 
                WHERE tournament_id = %d",
                $tournament_id
            )
        );
    }
}
