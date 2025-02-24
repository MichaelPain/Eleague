<?php
class ETO_Team {
    const MAX_MEMBERS = 6;
    const MIN_MEMBERS = 3;
    const STATUS_PENDING = 'pending';
    const STATUS_CHECKED_IN = 'checked_in';
    const NATIONALITY_LENGTH = 2;
    const VALID_NATIONALITY_REGEX = '/^[A-Z]{2}$/';

    public static function create($data) {
        global $wpdb;

        try {
            if (!current_user_can('manage_eto_teams')) {
                throw new Exception(__('Permessi insufficienti per creare team', 'eto'));
            }

            $validated = self::validate_team_data($data);
            $current_user_id = get_current_user_id();

            $wpdb->query('START TRANSACTION');

            // Creazione team
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
                    '%d', '%s', '%d', '%s',
                    '%d', '%d', '%d', 
                    '%s', '%s'
                ]
            );

            if (!$team_result) {
                throw new Exception($wpdb->last_error);
            }

            $team_id = $wpdb->insert_id;

            // Aggiunta membri
            foreach ($validated['members'] as $index => $user_id) {
                $is_captain = ($index === 0);
                $member_result = self::add_member($team_id, $user_id, $is_captain);
                
                if (is_wp_error($member_result)) {
                    throw new Exception($member_result->get_error_message());
                }
            }

            ETO_Tournament::update_team_count($validated['tournament_id']);
            $wpdb->query('COMMIT');

            // Audit log
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

    public static function add_member($team_id, $user_id, $is_captain = false) {
        global $wpdb;

        try {
            $team = self::get($team_id);
            if (!$team) {
                throw new Exception(__('Team non trovato', 'eto'));
            }

            if (self::count_members($team_id) >= self::MAX_MEMBERS) {
                throw new Exception(
                    sprintf(__('Limite massimo di %d membri raggiunto', 'eto'), self::MAX_MEMBERS)
                );
            }

            // Fix SQL Injection: Aggiunto prepare()
            if (self::is_user_in_tournament($user_id, $team->tournament_id)) {
                throw new Exception(
                    __('Utente già registrato in un altro team per questo torneo', 'eto')
                );
            }

            $user = get_userdata($user_id);
            if (!$user) {
                throw new Exception(__('ID utente non valido', 'eto'));
            }

            // Sanitizzazione avanzata nazionalità
            $nationality_meta = get_user_meta($user_id, 'nationality', true);
            $nationality = substr(sanitize_text_field($nationality_meta), 0, self::NATIONALITY_LENGTH);
            $nationality = strtoupper($nationality);
            
            if (!preg_match(self::VALID_NATIONALITY_REGEX, $nationality)) {
                throw new Exception(__('Codice nazionale non valido (esempio: IT)', 'eto'));
            }

            $member_data = [
                'team_id' => $team_id,
                'user_id' => $user_id,
                'riot_id' => sanitize_text_field(get_user_meta($user_id, 'riot_id', true)),
                'discord_tag' => sanitize_text_field(get_user_meta($user_id, 'discord_tag', true)),
                'nationality' => $nationality,
                'is_captain' => $is_captain ? 1 : 0,
                'joined_at' => current_time('mysql')
            ];

            $insert_result = $wpdb->insert(
                "{$wpdb->prefix}eto_team_members",
                $member_data,
                ['%d', '%d', '%s', '%s', '%s', '%d', '%s']
            );

            if (!$insert_result) {
                throw new Exception($wpdb->last_error);
            }

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

        ETO_Audit_Log::add([
            'action_type' => 'team_status_changed',
            'object_id' => $team_id,
            'details' => "Nuovo stato: $new_status"
        ]);

        return true;
    }

    private static function validate_team_data($data) {
        global $wpdb;

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

        if (!ETO_Tournament::exists($validated['tournament_id'])) {
            throw new InvalidArgumentException(
                __('Il torneo specificato non esiste', 'eto')
            );
        }

        $member_count = count($validated['members']);
        if ($member_count < self::MIN_MEMBERS || $member_count > self::MAX_MEMBERS) {
            throw new InvalidArgumentException(
                sprintf(__('Il team deve avere tra %d e %d membri', 'eto'), self::MIN_MEMBERS, self::MAX_MEMBERS)
            );
        }

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

    public static function delete($team_id) {
        global $wpdb;

        try {
            $wpdb->query('START TRANSACTION');

            $wpdb->delete(
                "{$wpdb->prefix}eto_team_members",
                ['team_id' => $team_id],
                ['%d']
            );

            $result = $wpdb->delete(
                "{$wpdb->prefix}eto_teams",
                ['id' => $team_id],
                ['%d']
            );

            if (!$result) {
                throw new Exception($wpdb->last_error);
            }

            $wpdb->query('COMMIT');

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
