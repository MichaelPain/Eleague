<?php
class ETO_Match {
    // Segnala il risultato di una partita (con screenshot)
    public static function report_result($match_id, $winner_id, $screenshot) {
        global $wpdb;

        $user_id = get_current_user_id();
        $team_id = self::get_user_team_in_match($user_id, $match_id);

        if (!$team_id) {
            return new WP_Error('not_participant', 'Non sei parte di questa partita');
        }

        // Verifica se il capitano ha caricato lo screenshot
        if (!ETO_Team::is_captain($user_id, $team_id)) {
            return new WP_Error('not_captain', 'Solo il capitano può segnalare risultati');
        }

        // Carica lo screenshot
        $upload = ETO_Uploads::handle_screenshot($screenshot);
        if (is_wp_error($upload)) {
            return $upload;
        }

        return $wpdb->update(
            "{$wpdb->prefix}eto_matches",
            [
                'winner_id' => absint($winner_id),
                'screenshot_url' => esc_url_raw($upload),
                'reported_by' => absint($user_id)
            ],
            ['id' => absint($match_id)],
            ['%d', '%s', '%d'],
            ['%d']
        );
    }

    // Conferma il risultato (admin)
    public static function confirm_result($match_id) {
        global $wpdb;

        $result = $wpdb->update(
            "{$wpdb->prefix}eto_matches",
            [
                'confirmed_by' => get_current_user_id(),
                'confirmed_at' => current_time('mysql')
            ],
            ['id' => absint($match_id)],
            ['%d', '%s'],
            ['%d']
        );

        if ($result) {
            $match = self::get($match_id);
            ETO_Tournament::advance_winner($match->tournament_id, $match->winner_id);
        }

        return $result;
    }

    // Ottieni partite in attesa
    public static function get_pending() {
        global $wpdb;
        return $wpdb->get_results(
            "SELECT * FROM {$wpdb->prefix}eto_matches 
            WHERE confirmed_by IS NULL 
            AND screenshot_url IS NOT NULL"
        );
    }

    // Ottieni dettagli partita
    public static function get($match_id) {
        global $wpdb;
        return $wpdb->get_row(
            $wpdb->prepare(
                "SELECT * FROM {$wpdb->prefix}eto_matches 
                WHERE id = %d",
                absint($match_id)
            )
        );
    }

    // Ottieni il team dell'utente nella partita
    private static function get_user_team_in_match($user_id, $match_id) {
        global $wpdb;
        return $wpdb->get_var(
            $wpdb->prepare(
                "SELECT team_id FROM {$wpdb->prefix}eto_team_members 
                WHERE user_id = %d 
                AND team_id IN (
                    SELECT team1_id FROM {$wpdb->prefix}eto_matches WHERE id = %d
                    UNION
                    SELECT team2_id FROM {$wpdb->prefix}eto_matches WHERE id = %d
                )",
                absint($user_id),
                absint($match_id),
                absint($match_id)
            )
        );
    }
}
