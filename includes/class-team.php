<?php
class ETO_Team {
    // Crea un nuovo team
    public static function create($name, $captain_id, $tournament_id = 0) {
        global $wpdb;

        if (self::user_has_team($captain_id)) {
            return new WP_Error('existing_team', 'Sei già in un team!');
        }

        $result = $wpdb->insert(
            "{$wpdb->prefix}eto_teams",
            [
                'name' => sanitize_text_field($name),
                'captain_id' => absint($captain_id),
                'tournament_id' => absint($tournament_id),
                'status' => 'pending'
            ],
            ['%s', '%d', '%d', '%s']
        );

        if (!$result) return new WP_Error('db_error', 'Errore nel database');

        $team_id = $wpdb->insert_id;
        self::add_member($team_id, $captain_id, true);
        return $team_id;
    }

    // Elimina un team (solo se non in torneo attivo)
    public static function delete($team_id) {
        global $wpdb;

        if (self::is_tournament_active($team_id)) {
            return new WP_Error('active_tournament', 'Impossibile eliminare il team durante un torneo');
        }

        return $wpdb->delete(
            "{$wpdb->prefix}eto_teams",
            ['id' => absint($team_id)],
            ['%d']
        );
    }

    // Aggiungi membro al team
    public static function add_member($team_id, $user_id, $is_captain = false) {
        global $wpdb;

        $riot_id = get_user_meta($user_id, 'riot_id', true);
        if (empty($riot_id)) {
            return new WP_Error('missing_riot_id', 'Il membro deve avere un Riot#ID valido');
        }

        return $wpdb->insert(
            "{$wpdb->prefix}eto_team_members",
            [
                'team_id' => absint($team_id),
                'user_id' => absint($user_id),
                'riot_id' => sanitize_text_field($riot_id),
                'discord_tag' => sanitize_text_field(get_user_meta($user_id, 'discord_tag', true)),
                'nationality' => sanitize_text_field(get_user_meta($user_id, 'nationality', true)),
                'is_captain' => $is_captain ? 1 : 0
            ],
            ['%d', '%d', '%s', '%s', '%s', '%d']
        );
    }

    // Rimuovi membro dal team
    public static function remove_member($team_id, $user_id) {
        global $wpdb;

        if (!self::is_captain(get_current_user_id(), $team_id)) {
            return new WP_Error('not_captain', 'Solo il capitano può rimuovere membri');
        }

        if (self::is_tournament_active($team_id)) {
            return new WP_Error('active_tournament', 'Team bloccato durante il torneo');
        }

        return $wpdb->delete(
            "{$wpdb->prefix}eto_team_members",
            [
                'team_id' => absint($team_id),
                'user_id' => absint($user_id)
            ],
            ['%d', '%d']
        );
    }

    // Ottieni i team di un utente
    public static function get_user_teams($user_id) {
        global $wpdb;
        return $wpdb->get_results(
            $wpdb->prepare(
                "SELECT t.* FROM {$wpdb->prefix}eto_teams t
                INNER JOIN {$wpdb->prefix}eto_team_members m ON t.id = m.team_id
                WHERE m.user_id = %d",
                absint($user_id)
            )
        );
    }

    // Verifica se l'utente è capitano
    public static function is_captain($user_id, $team_id) {
        global $wpdb;
        return (bool) $wpdb->get_var(
            $wpdb->prepare(
                "SELECT COUNT(*) FROM {$wpdb->prefix}eto_team_members 
                WHERE team_id = %d AND user_id = %d AND is_captain = 1",
                absint($team_id), absint($user_id)
            )
        );
    }

    // Registra team a un torneo
    public static function register_for_tournament($team_id, $tournament_id) {
        global $wpdb;

        $min_players = $wpdb->get_var(
            $wpdb->prepare(
                "SELECT min_players FROM {$wpdb->prefix}eto_tournaments 
                WHERE id = %d",
                absint($tournament_id)
            )
        );

        $member_count = $wpdb->get_var(
            $wpdb->prepare(
                "SELECT COUNT(*) FROM {$wpdb->prefix}eto_team_members 
                WHERE team_id = %d",
                absint($team_id)
            )
        );

        if ($member_count < $min_players) {
            return new WP_Error('min_players', sprintf('Il team deve avere almeno %d membri', $min_players));
        }

        return $wpdb->update(
            "{$wpdb->prefix}eto_teams",
            [
                'tournament_id' => absint($tournament_id),
                'status' => 'registered'
            ],
            ['id' => absint($team_id)],
            ['%d', '%s'],
            ['%d']
        );
    }

    // Classifica team (per widget)
    public static function get_leaderboard() {
        global $wpdb;
        return $wpdb->get_results(
            "SELECT t.id, t.name, COUNT(m.id) as wins 
            FROM {$wpdb->prefix}eto_teams t
            LEFT JOIN {$wpdb->prefix}eto_matches m ON t.id = m.winner_id
            GROUP BY t.id ORDER BY wins DESC LIMIT 10"
        );
    }

    // Controlla se un utente ha già un team
    private static function user_has_team($user_id) {
        global $wpdb;
        return (bool) $wpdb->get_var(
            $wpdb->prepare(
                "SELECT COUNT(*) FROM {$wpdb->prefix}eto_team_members 
                WHERE user_id = %d",
                absint($user_id)
            )
        );
    }

    // Verifica se il team è in un torneo attivo
    private static function is_tournament_active($team_id) {
        global $wpdb;
        return $wpdb->get_var(
            $wpdb->prepare(
                "SELECT status FROM {$wpdb->prefix}eto_tournaments 
                WHERE id = (SELECT tournament_id FROM {$wpdb->prefix}eto_teams WHERE id = %d)",
                absint($team_id)
            )
        ) === 'active';
    }
}
