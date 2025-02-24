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

        // Recupera tutti i team iscritti al torneo
        $teams = $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}eto_teams 
            WHERE tournament_id = %d 
            ORDER BY RAND()",
            $tournament_id
        ));

        // Aggiungi un BYE se il numero dei team è dispari
        if (count($teams) % 2 !== 0) {
            $teams[] = (object) ['id' => self::BYE_TEAM_ID];
        }

        // Dividi i team in accoppiamenti
        $matches = [];
        $total_teams = count($teams);
        
        for ($i = 0; $i < $total_teams; $i += 2) {
            $matches[] = [
                'team1_id' => $teams[$i]->id,
                'team2_id' => $teams[$i + 1]->id ?? self::BYE_TEAM_ID
            ];
        }

        // Salva i match nel database
        foreach ($matches as $match) {
            $wpdb->insert(
                "{$wpdb->prefix}eto_matches",
                [
                    'tournament_id' => $tournament_id,
                    'round' => '1',
                    'team1_id' => $match['team1_id'],
                    'team2_id' => $match['team2_id']
                ],
                ['%d', '%s', '%d', '%d']
            );
        }

        return $matches;
    }

    /**
     * Aggiorna le statistiche Swiss dopo un match
     */
    public static function update_standings($tournament_id, $winner_id, $loser_id) {
        global $wpdb;

        // Aggiorna vittorie
        $wpdb->query($wpdb->prepare(
            "UPDATE {$wpdb->prefix}eto_teams
            SET wins = wins + 1,
                points_diff = points_diff + 1
            WHERE id = %d",
            $winner_id
        ));

        // Aggiorna sconfitte
        $wpdb->query($wpdb->prepare(
            "UPDATE {$wpdb->prefix}eto_teams
            SET losses = losses + 1,
                points_diff = points_diff - 1
            WHERE id = %d",
            $loser_id
        ));
    }

    /**
     * Genera il prossimo round Swiss
     */
    public static function generate_next_round($tournament_id) {
        global $wpdb;

        // Recupera la classifica corrente
        $standings = $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}eto_teams
            WHERE tournament_id = %d
            ORDER BY wins DESC, points_diff DESC",
            $tournament_id
        ));

        // Logica di accoppiamento
        $matches = [];
        $skip = [];

        foreach ($standings as $key => $team) {
            if (in_array($team->id, $skip)) continue;

            // Trova il prossimo avversario disponibile
            for ($i = $key + 1; $i < count($standings); $i++) {
                $opponent = $standings[$i];
                
                if (!in_array($opponent->id, $skip)) {
                    $matches[] = [
                        'team1_id' => $team->id,
                        'team2_id' => $opponent->id
                    ];
                    $skip[] = $team->id;
                    $skip[] = $opponent->id;
                    break;
                }
            }
        }

        // Salva i nuovi match
        $current_round = $wpdb->get_var($wpdb->prepare(
            "SELECT MAX(round) FROM {$wpdb->prefix}eto_matches 
            WHERE tournament_id = %d",
            $tournament_id
        )) + 1;

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

        return $matches;
    }
}
