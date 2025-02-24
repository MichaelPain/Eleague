<?php
/**
 * Classe per la gestione completa dei team
 * @package eSports Tournament Organizer
 * @since 1.0.0
 */

class ETO_Team {
    const MIN_TEAM_NAME_LENGTH = 3;
    const MAX_TEAM_NAME_LENGTH = 50;
    const MAX_MEMBERS = 8;

    /**
     * Crea un nuovo team con controlli completi
     * @param array $data Dati del team
     * @return int|WP_Error ID del team o oggetto errore
     */
    public static function create($data) {
        global $wpdb;

        // Verifica permessi utente
        if (!is_user_logged_in()) {
            return new WP_Error('unauthorized', __('Devi essere loggato per creare un team', 'eto'));
        }

        // Validazione campi obbligatori
        $required_fields = [
            'name' => __('Nome team', 'eto'),
            'tournament_id' => __('ID Torneo', 'eto')
        ];

        foreach ($required_fields as $field => $label) {
            if (empty($data[$field])) {
                return new WP_Error('missing_field', 
                    sprintf(__('%s è un campo obbligatorio', 'eto'), $label)
                );
            }
        }

        // Sanitizzazione dati
        $name = substr(sanitize_text_field($data['name']), 0, self::MAX_TEAM_NAME_LENGTH);
        $tournament_id = absint($data['tournament_id']);
        $captain_id = get_current_user_id();
        $status = 'pending';

        // Validazione avanzata
        if (strlen($name) < self::MIN_TEAM_NAME_LENGTH) {
            return new WP_Error('invalid_name_length',
                sprintf(__('Il nome del team deve essere lungo almeno %d caratteri', 'eto'), self::MIN_TEAM_NAME_LENGTH)
            );
        }

        if (self::user_has_team($captain_id, $tournament_id)) {
            return new WP_Error('existing_team',
                __('Sei già capitano di un team in questo torneo', 'eto')
            );
        }

        // Transazione database
        $wpdb->query('START TRANSACTION');

        try {
            $insert_result = $wpdb->insert(
                "{$wpdb->prefix}eto_teams",
                [
                    'name' => $name,
                    'tournament_id' => $tournament_id,
                    'captain_id' => $captain_id,
                    'status' => $status,
                    'wins' => 0,
                    'losses' => 0,
                    'points_diff' => 0,
                    'created_at' => current_time('mysql')
                ],
                [
                    '%s', // name
                    '%d', // tournament_id
                    '%d', // captain_id
                    '%s', // status
                    '%d', // wins
                    '%d', // losses
                    '%d', // points_diff
                    '%s'  // created_at
                ]
            );

            if (!$insert_result) {
                throw new Exception($wpdb->last_error);
            }

            $team_id = $wpdb->insert_id;

            // Aggiungi automaticamente il capitano come membro
            $member_result = self::add_member($team_id, $captain_id, true);
            if (is_wp_error($member_result)) {
                throw new Exception($member_result->get_error_message());
            }

            $wpdb->query('COMMIT');
            return $team_id;

        } catch (Exception $e) {
            $wpdb->query('ROLLBACK');
            return new WP_Error('db_error', 
                __('Errore durante la creazione del team: ', 'eto') . $e->getMessage()
            );
        }
    }

    /**
     * Aggiungi un membro al team
     */
    public static function add_member($team_id, $user_id, $is_captain = false) {
        global $wpdb;

        // Verifica limite membri
        $current_members = self::count_members($team_id);
        if ($current_members >= self::MAX_MEMBERS) {
            return new WP_Error('max_members',
                sprintf(__('Il team ha già raggiunto il limite di %d membri', 'eto'), self::MAX_MEMBERS)
            );
        }

        // Verifica appartenenza ad altri team nello stesso torneo
        $team = self::get($team_id);
        if (self::user_in_tournament($user_id, $team->tournament_id)) {
            return new WP_Error('already_in_tournament',
                __('L\'utente è già registrato in un altro team per questo torneo', 'eto')
            );
        }

        $result = $wpdb->insert(
            "{$wpdb->prefix}eto_team_members",
            [
                'team_id' => $team_id,
                'user_id' => $user_id,
                'riot_id' => sanitize_text_field(get_user_meta($user_id, 'riot_id', true)),
                'discord_tag' => sanitize_text_field(get_user_meta($user_id, 'discord_tag', true)),
                'is_captain' => $is_captain ? 1 : 0,
                'joined_at' => current_time('mysql')
            ],
            ['%d', '%d', '%s', '%s', '%d', '%s']
        );

        return $result ? $wpdb->insert_id : new WP_Error('db_error', __('Errore database', 'eto'));
    }

    /**
     * Ottieni i dettagli completi di un team
     */
    public static function get($team_id) {
        global $wpdb;

        $team = $wpdb->get_row(
            $wpdb->prepare(
                "SELECT * FROM {$wpdb->prefix}eto_teams 
                WHERE id = %d",
                $team_id
            )
        );

        if ($team) {
            $team->members = self::get_members($team_id);
        }

        return $team;
    }

    /**
     * Ottieni tutti i membri del team
     */
    public static function get_members($team_id) {
        global $wpdb;

        return $wpdb->get_results(
            $wpdb->prepare(
                "SELECT * FROM {$wpdb->prefix}eto_team_members 
                WHERE team_id = %d 
                ORDER BY is_captain DESC, joined_at ASC",
                $team_id
            )
        );
    }

    /**
     * Conta i team totali o per torneo
     */
    public static function count($tournament_id = null) {
        global $wpdb;

        $query = "SELECT COUNT(*) FROM {$wpdb->prefix}eto_teams";
        $params = [];

        if ($tournament_id) {
            $query .= " WHERE tournament_id = %d";
            $params[] = $tournament_id;
        }

        return $wpdb->get_var(
            $params ? $wpdb->prepare($query, $params) : $query
        );
    }

    /**
     * Aggiorna lo stato del team
     */
    public static function update_status($team_id, $new_status) {
        global $wpdb;

        $allowed_statuses = ['pending', 'registered', 'checked_in', 'disqualified'];
        $new_status = sanitize_key($new_status);

        if (!in_array($new_status, $allowed_statuses)) {
            return new WP_Error('invalid_status',
                __('Stato del team non valido', 'eto')
            );
        }

        $result = $wpdb->update(
            "{$wpdb->prefix}eto_teams",
            ['status' => $new_status],
            ['id' => $team_id],
            ['%s'],
            ['%d']
        );

        return $result !== false;
    }

    /**
     * Verifica se un utente ha già un team nel torneo
     */
    private static function user_has_team($user_id, $tournament_id) {
        global $wpdb;

        return $wpdb->get_var(
            $wpdb->prepare(
                "SELECT COUNT(*) 
                FROM {$wpdb->prefix}eto_teams 
                WHERE captain_id = %d 
                AND tournament_id = %d",
                $user_id,
                $tournament_id
            )
        ) > 0;
    }

    /**
     * Conta i membri del team
     */
    private static function count_members($team_id) {
        global $wpdb;

        return $wpdb->get_var(
            $wpdb->prepare(
                "SELECT COUNT(*) 
                FROM {$wpdb->prefix}eto_team_members 
                WHERE team_id = %d",
                $team_id
            )
        );
    }

    /**
     * Verifica se un utente è già in un team del torneo
     */
    private static function user_in_tournament($user_id, $tournament_id) {
        global $wpdb;

        return $wpdb->get_var(
            $wpdb->prepare(
                "SELECT COUNT(*) 
                FROM {$wpdb->prefix}eto_team_members tm
                INNER JOIN {$wpdb->prefix}eto_teams t 
                ON tm.team_id = t.id
                WHERE tm.user_id = %d 
                AND t.tournament_id = %d",
                $user_id,
                $tournament_id
            )
        ) > 0;
    }
}
