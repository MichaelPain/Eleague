<?php
/**
 * Gestione avanzata dei team e membri
 * @package eSports Tournament Organizer
 * @since 1.0.0
 */

class ETO_Team {
    const MAX_MEMBERS = 6;
    const MIN_MEMBERS = 3;
    const STATUS_PENDING = 'pending';
    const STATUS_CHECKED_IN = 'checked_in';
    const ROLE_CAPTAIN = 'captain';
    const ROLE_MEMBER = 'member';

    /**
     * Crea un nuovo team con controlli completi
     */
    public static function create($data) {
        global $wpdb;

        try {
            // Verifica permessi utente
            if (!current_user_can('manage_eto_teams')) {
                throw new Exception(__('Permessi insufficienti per creare team', 'eto'));
            }

            // Validazione dati
            $validated = self::validate_team_data($data);
            $current_user_id = get_current_user_id();

            // Transazione database
            $wpdb->query('START TRANSACTION');

            // Crea il team
            $team_result = $wpdb->insert(
                "{$wpdb->prefix}eto_teams",
                [
                    'tournament_id' => $validated['tournament_id'],
                    'name' => $validated['name'],
                    'captain_id' => $current_user_id,
                    'status' => self::STATUS_PENDING,
                    'wins' => 0,
                    'losses' => 0,
                    'points_diff' => 0,
                    'created_at' => current_time('mysql'),
                    'updated_at' => current_time('mysql')
                ],
                [
                    '%d', // tournament_id
                    '%s', // name
                    '%d', // captain_id
                    '%s', // status
                    '%d', // wins
                    '%d', // losses
                    '%d', // points_diff
                    '%s', // created_at
                    '%s'  // updated_at
                ]
            );

            if (!$team_result) {
                throw new Exception($wpdb->last_error);
            }

            $team_id = $wpdb->insert_id;

            // Aggiungi membri
            foreach ($validated['members'] as $index => $user_id) {
                $is_captain = ($index === 0); // Primo membro = capitano
                $member_result = self::add_member($team_id, $user_id, $is_captain);

                if (is_wp_error($member_result)) {
                    throw new Exception($member_result->get_error_message());
                }
            }

            // Aggiorna conteggio team nel torneo
            ETO_Tournament::update_team_count($validated['tournament_id']);

            $wpdb->query('COMMIT');

            // Registra audit log
            ETO_Audit_Log::add([
                'action_type' => 'team_created',
                'object_id' => $team_id,
                'details' => json_encode([
                    'tournament_id' => $validated['tournament_id'],
                    'member_count' => count($validated['members'])
                ])
            ]);

            return $team_id;

        } catch (Exception $e) {
            $wpdb->query('ROLLBACK');
            return new WP_Error('team_error', $e->getMessage());
        }
    }

    /**
     * Aggiungi membro al team con controlli avanzati
     */
    public static function add_member($team_id, $user_id, $is_captain = false) {
        global $wpdb;

        try {
            // Verifica esistenza team
            $team = self::get($team_id);
            if (!$team) {
                throw new Exception(__('Team non trovato', 'eto'));
            }

            // Verifica numero massimo membri
            $current_members = self::count_members($team_id);
            if ($current_members >= self::MAX_MEMBERS) {
                throw new Exception(
                    sprintf(__('Limite massimo di %d membri raggiunto', 'eto'), self::MAX_MEMBERS)
                );
            }

            // Verifica appartenenza ad altri team nello stesso torneo
            if (self::is_user_in_tournament($user_id, $team->tournament_id)) {
                throw new Exception(
                    __('Utente già registrato in un altro team per questo torneo', 'eto')
                );
            }

            // Verifica esistenza utente
            $user = get_userdata($user_id);
            if (!$user) {
                throw new Exception(__('ID utente non valido', 'eto'));
            }

            // Preparazione dati membro
            $member_data = [
                'team_id' => $team_id,
                'user_id' => $user_id,
                'riot_id' => sanitize_text_field(get_user_meta($user_id, 'riot_id', true)),
                'discord_tag' => sanitize_text_field(get_user_meta($user_id, 'discord_tag', true)),
                'nationality' => substr(sanitize_text_field(get_user_meta($user_id, 'nationality', true)), 0, 2),
                'is_captain' => $is_captain ? 1 : 0,
                'joined_at' => current_time('mysql')
            ];

            // Inserimento nel database
            $insert_result = $wpdb->insert(
                "{$wpdb->prefix}eto_team_members",
                $member_data,
                [
                    '%d', // team_id
                    '%d', // user_id
                    '%s', // riot_id
                    '%s', // discord_tag
                    '%s', // nationality
                    '%d', // is_captain
                    '%s'  // joined_at
                ]
            );

            if (!$insert_result) {
                throw new Exception($wpdb->last_error);
            }

            // Aggiorna ruolo utente se capitano
            if ($is_captain) {
                $user->add_role('eto_captain');
                ETO_Audit_Log::add([
                    'action_type' => 'captain_assigned',
                    'object_id' => $team_id,
                    'details' => "User ID: $user_id"
                ]);
            }

            return $wpdb->insert_id;

        } catch (Exception $e) {
            return new WP_Error('member_error', $e->getMessage());
        }
    }

    /**
     * Ottieni dettagli completi del team
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
            $team->tournament = ETO_Tournament::get($team->tournament_id);
        }

        return $team;
    }

    /**
     * Ottieni tutti i membri con dettagli utente
     */
    public static function get_members($team_id) {
        global $wpdb;

        return $wpdb->get_results(
            $wpdb->prepare(
                "SELECT 
                    tm.*,
                    u.user_login,
                    u.user_email,
                    u.display_name,
                    m1.meta_value as riot_id,
                    m2.meta_value as discord_tag
                FROM {$wpdb->prefix}eto_team_members tm
                INNER JOIN {$wpdb->prefix}users u ON tm.user_id = u.ID
                LEFT JOIN {$wpdb->prefix}usermeta m1 ON u.ID = m1.user_id AND m1.meta_key = 'riot_id'
                LEFT JOIN {$wpdb->prefix}usermeta m2 ON u.ID = m2.user_id AND m2.meta_key = 'discord_tag'
                WHERE tm.team_id = %d
                ORDER BY tm.is_captain DESC, tm.joined_at ASC",
                $team_id
            )
        );
    }

    /**
     * Aggiorna lo stato del team
     */
    public static function update_status($team_id, $new_status) {
        global $wpdb;

        $allowed_statuses = [self::STATUS_PENDING, self::STATUS_CHECKED_IN, 'disqualified'];
        if (!in_array($new_status, $allowed_statuses)) {
            return new WP_Error('invalid_status', __('Stato del team non valido', 'eto'));
        }

        $result = $wpdb->update(
            "{$wpdb->prefix}eto_teams",
            [
                'status' => $new_status,
                'updated_at' => current_time('mysql')
            ],
            ['id' => $team_id],
            ['%s', '%s'],
            ['%d']
        );

        if ($result === false) {
            return new WP_Error('db_error', $wpdb->last_error);
        }

        // Registra audit log
        ETO_Audit_Log::add([
            'action_type' => 'team_status_changed',
            'object_id' => $team_id,
            'details' => "Nuovo stato: $new_status"
        ]);

        return true;
    }

    /**
     * Validazione dati team
     */
    private static function validate_team_data($data) {
        global $wpdb;

        // Campi obbligatori
        $required_fields = ['tournament_id', 'name', 'members'];
        foreach ($required_fields as $field) {
            if (empty($data[$field])) {
                throw new InvalidArgumentException(
                    sprintf(__('Campo obbligatorio mancante: %s', 'eto'), $field)
                );
            }
        }

        $validated = [
            'tournament_id' => absint($data['tournament_id']),
            'name' => substr(sanitize_text_field($data['name']), 0, 100),
            'members' => array_unique(array_map('absint', (array)$data['members']))
        ];

        // Verifica esistenza torneo
        if (!ETO_Tournament::exists($validated['tournament_id'])) {
            throw new InvalidArgumentException(
                __('Il torneo specificato non esiste', 'eto')
            );
        }

        // Verifica numero membri
        $member_count = count($validated['members']);
        if ($member_count < self::MIN_MEMBERS || $member_count > self::MAX_MEMBERS) {
            throw new InvalidArgumentException(
                sprintf(__('Il team deve avere tra %d e %d membri', 'eto'), self::MIN_MEMBERS, self::MAX_MEMBERS)
            );
        }

        // Verifica unicità nome team nel torneo
        $existing = $wpdb->get_var(
            $wpdb->prepare(
                "SELECT COUNT(*) FROM {$wpdb->prefix}eto_teams
                WHERE tournament_id = %d AND name = %s",
                $validated['tournament_id'],
                $validated['name']
            )
        );

        if ($existing > 0) {
            throw new InvalidArgumentException(
                __('Nome team già utilizzato in questo torneo', 'eto')
            );
        }

        return $validated;
    }

    /**
     * Verifica se un utente è già in un team del torneo
     */
    private static function is_user_in_tournament($user_id, $tournament_id) {
        global $wpdb;

        return $wpdb->get_var(
            $wpdb->prepare(
                "SELECT COUNT(*) FROM {$wpdb->prefix}eto_team_members tm
                INNER JOIN {$wpdb->prefix}eto_teams t ON tm.team_id = t.id
                WHERE tm.user_id = %d AND t.tournament_id = %d",
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
                "SELECT COUNT(*) FROM {$wpdb->prefix}eto_team_members 
                WHERE team_id = %d",
                $team_id
            )
        );
    }

    /**
     * Elimina un team e i relativi membri
     */
    public static function delete($team_id) {
        global $wpdb;

        try {
            $wpdb->query('START TRANSACTION');

            // Elimina membri
            $wpdb->delete(
                "{$wpdb->prefix}eto_team_members",
                ['team_id' => $team_id],
                ['%d']
            );

            // Elimina team
            $result = $wpdb->delete(
                "{$wpdb->prefix}eto_teams",
                ['id' => $team_id],
                ['%d']
            );

            if (!$result) {
                throw new Exception($wpdb->last_error);
            }

            $wpdb->query('COMMIT');

            // Registra audit log
            ETO_Audit_Log::add([
                'action_type' => 'team_deleted',
                'object_id' => $team_id
            ]);

            return true;

        } catch (Exception $e) {
            $wpdb->query('ROLLBACK');
            return new WP_Error('delete_error', $e->getMessage());
        }
    }
}
