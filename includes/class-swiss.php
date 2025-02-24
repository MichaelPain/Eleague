<?php
class ETO_Swiss {
    // Genera un nuovo round del torneo Swiss
    public static function generate_round($tournament_id, $round_number) {
        global $wpdb;

        // Ottieni tutte le squadre con il loro record
        $teams = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT t.id, 
                (SELECT COUNT(*) FROM {$wpdb->prefix}eto_matches 
                WHERE winner_id = t.id AND tournament_id = %d) as wins,
                (SELECT COUNT(*) FROM {$wpdb->prefix}eto_matches 
                WHERE (team1_id = t.id OR team2_id = t.id) AND tournament_id = %d) as played
                FROM {$wpdb->prefix}eto_teams t
                WHERE t.tournament_id = %d",
                $tournament_id, $tournament_id, $tournament_id
            ),
            OBJECT_K
        );

        // Ordina per vittorie e differenza partite giocate
        usort($teams, function($a, $b) {
            if ($a->wins === $b->wins) {
                return $a->played <=> $b->played;
            }
            return $b->wins <=> $a->wins;
        });

        $pairs = [];
        $skip = [];

        for ($i = 0; $i < count($teams); $i++) {
            if (in_array($i, $skip)) continue;

            for ($j = $i + 1; $j < count($teams); $j++) {
                if (!in_array($j, $skip) && !self::have_met($teams[$i]->id, $teams[$j]->id, $tournament_id)) {
                    $pairs[] = [$teams[$i]->id, $teams[$j]->id];
                    $skip[] = $i;
                    $skip[] = $j;
                    break;
                }
            }
        }

        return $pairs;
    }

    // Verifica se due team si sono già affrontati
    private static function have_met($team1_id, $team2_id, $tournament_id) {
        global $wpdb;
        return (bool) $wpdb->get_var(
            $wpdb->prepare(
                "SELECT COUNT(*) FROM {$wpdb->prefix}eto_matches 
                WHERE tournament_id = %d 
                AND ((team1_id = %d AND team2_id = %d) 
                OR (team1_id = %d AND team2_id = %d))",
                $tournament_id, $team1_id, $team2_id, $team2_id, $team1_id
            )
        );
    }

<?php
    // Aggiorna la classifica dopo un round
    public static function update_standings($tournament_id) {
        global $wpdb;
        
        // Ottieni tutte le partite confermate del torneo
        $matches = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT * FROM {$wpdb->prefix}eto_matches 
                WHERE tournament_id = %d 
                AND confirmed_by IS NOT NULL",
                $tournament_id
            )
        );

        // Reset statistiche
        $wpdb->update(
            "{$wpdb->prefix}eto_teams",
            ['wins' => 0, 'losses' => 0, 'points_diff' => 0],
            ['tournament_id' => $tournament_id],
            ['%d', '%d', '%d'],
            ['%d']
        );

        foreach ($matches as $match) {
            if ($match->winner_id) {
                // Aggiorna il vincitore
                $wpdb->query(
                    $wpdb->prepare(
                        "UPDATE {$wpdb->prefix}eto_teams 
                        SET wins = wins + 1, 
                            points_diff = points_diff + (SELECT points FROM {$wpdb->prefix}eto_matches WHERE id = %d)
                        WHERE id = %d",
                        $match->id, $match->winner_id
                    )
                );

                // Aggiorna il perdente (se non è un BYE)
                if ($match->team1_id != $match->winner_id && $match->team2_id) {
                    $wpdb->query(
                        $wpdb->prepare(
                            "UPDATE {$wpdb->prefix}eto_teams 
                            SET losses = losses + 1 
                            WHERE id = %d",
                            $match->team1_id == $match->winner_id ? $match->team2_id : $match->team1_id
                        )
                    );
                }
            }
        }

        // Salva l'ultimo aggiornamento nel torneo
        $wpdb->update(
            "{$wpdb->prefix}eto_tournaments",
            ['last_updated' => current_time('mysql')],
            ['id' => $tournament_id],
            ['%s'],
            ['%d']
        );
    }
}
