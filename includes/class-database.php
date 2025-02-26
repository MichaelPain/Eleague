<?php
if (!defined('ABSPATH')) exit;

class ETO_Database {
    const DB_VERSION = '3.0.0';
    const DB_OPTION = 'eto_db_version';

    public static function create_tables() {
        global $wpdb;
        require_once ABSPATH . 'wp-admin/includes/upgrade.php';

        $charset_collate = $wpdb->get_charset_collate();

        // 1. Tabella Tornei (con prepared statements)
        $sql = "CREATE TABLE {$wpdb->prefix}eto_tournaments (
            id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            name VARCHAR(255) NOT NULL,
            format ENUM('single_elimination','double_elimination','swiss') NOT NULL DEFAULT 'single_elimination',
            game_type VARCHAR(50) NOT NULL DEFAULT 'lol',
            start_date DATETIME NOT NULL,
            end_date DATETIME NOT NULL,
            min_players TINYINT(3) UNSIGNED NOT NULL DEFAULT 3,
            max_players TINYINT(3) UNSIGNED NOT NULL DEFAULT 5,
            max_teams INT(3) NOT NULL DEFAULT 16,
            checkin_enabled BOOLEAN NOT NULL DEFAULT FALSE,
            third_place_match BOOLEAN NOT NULL DEFAULT FALSE,
            status ENUM('pending','active','completed','cancelled') NOT NULL DEFAULT 'pending',
            created_by BIGINT(20) UNSIGNED NOT NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            INDEX tournament_status_idx (status),
            INDEX tournament_dates_idx (start_date, end_date)
        ) ENGINE=InnoDB $charset_collate;";

        dbDelta($sql);

        // 2. Tabella Team (con escaping corretto)
        $sql = "CREATE TABLE {$wpdb->prefix}eto_teams (
            id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            tournament_id BIGINT(20) UNSIGNED NOT NULL,
            name VARCHAR(255) NOT NULL,
            captain_id BIGINT(20) UNSIGNED NOT NULL,
            wins MEDIUMINT(8) UNSIGNED NOT NULL DEFAULT 0,
            losses MEDIUMINT(8) UNSIGNED NOT NULL DEFAULT 0,
            points_diff MEDIUMINT(8) NOT NULL DEFAULT 0,
            tiebreaker MEDIUMINT(8) NOT NULL DEFAULT 0,
            status ENUM('pending','registered','checked_in','disqualified') NOT NULL DEFAULT 'pending',
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            FOREIGN KEY (tournament_id) REFERENCES {$wpdb->prefix}eto_tournaments(id) ON DELETE CASCADE,
            INDEX team_status_idx (status)
        ) ENGINE=InnoDB $charset_collate;";

        dbDelta($sql);

        // 3. Tabella Membri Team (con prepared statements)
        $sql = $wpdb->prepare(
            "CREATE TABLE IF NOT EXISTS %i (
                id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
                team_id BIGINT(20) UNSIGNED NOT NULL,
                user_id BIGINT(20) UNSIGNED NOT NULL,
                riot_id VARCHAR(255),
                discord_tag VARCHAR(32),
                nationality CHAR(2),
                is_captain BOOLEAN NOT NULL DEFAULT FALSE,
                joined_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                PRIMARY KEY (id),
                FOREIGN KEY (team_id) REFERENCES %i(id) ON DELETE CASCADE,
                UNIQUE KEY user_team_unique (user_id, team_id)
            ) ENGINE=InnoDB $charset_collate;",
            $wpdb->prefix . 'eto_team_members',
            $wpdb->prefix . 'eto_teams'
        );
        dbDelta($sql);

        // 4. Tabella Partite (con escaping identificatori)
        $matches_table = $wpdb->prefix . 'eto_matches';
        $teams_table = $wpdb->prefix . 'eto_teams';
        $tournaments_table = $wpdb->prefix . 'eto_tournaments';

        $sql = "CREATE TABLE $matches_table (
            id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            tournament_id BIGINT(20) UNSIGNED NOT NULL,
            round VARCHAR(50) NOT NULL,
            team1_id BIGINT(20) UNSIGNED NOT NULL,
            team2_id BIGINT(20) UNSIGNED,
            winner_id BIGINT(20) UNSIGNED,
            screenshot_url VARCHAR(512),
            reported_by BIGINT(20) UNSIGNED,
            confirmed_by BIGINT(20) UNSIGNED,
            confirmed_at DATETIME,
            dispute_reason TEXT,
            status ENUM('pending','completed','disputed') NOT NULL DEFAULT 'pending',
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            FOREIGN KEY (tournament_id) REFERENCES $tournaments_table(id) ON DELETE CASCADE,
            FOREIGN KEY (team1_id) REFERENCES $teams_table(id) ON DELETE CASCADE,
            FOREIGN KEY (team2_id) REFERENCES $teams_table(id) ON DELETE CASCADE,
            INDEX match_status_idx (status)
        ) ENGINE=InnoDB $charset_collate;";

        dbDelta($sql);

        // 5. Tabella Audit Log (con prepared statements)
        $sql = $wpdb->prepare(
            "CREATE TABLE IF NOT EXISTS %i (
                id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
                user_id BIGINT(20) UNSIGNED NOT NULL,
                action_type VARCHAR(50) NOT NULL,
                object_type VARCHAR(50) NOT NULL,
                object_id BIGINT(20) UNSIGNED,
                details TEXT,
                ip_address VARCHAR(45) NOT NULL,
                created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                PRIMARY KEY (id),
                INDEX log_action_idx (action_type)
            ) ENGINE=InnoDB $charset_collate;",
            $wpdb->prefix . 'eto_audit_logs'
        );
        dbDelta($sql);

        update_option(self::DB_OPTION, self::DB_VERSION);
    }

    // 6. Metodi di query protetti da SQL injection
    public static function get_tournaments() {
        global $wpdb;
        return $wpdb->get_results(
            $wpdb->prepare("SELECT * FROM {$wpdb->prefix}eto_tournaments WHERE status != %s", 'deleted')
        );
    }

    public static function uninstall() {
        global $wpdb;
        
        // 7. Eliminazione sicura delle tabelle
        $tables = $wpdb->get_col($wpdb->prepare(
            "SHOW TABLES LIKE %s",
            $wpdb->prefix . 'eto_%'
        ));

        $allowed_tables = [
            $wpdb->prefix . 'eto_tournaments',
            $wpdb->prefix . 'eto_teams',
            $wpdb->prefix . 'eto_team_members',
            $wpdb->prefix . 'eto_matches',
            $wpdb->prefix . 'eto_audit_logs'
        ];

        foreach ($tables as $table) {
            if (in_array($table, $allowed_tables)) {
                $wpdb->query("DROP TABLE IF EXISTS " . esc_sql($table));
            }
        }

        // 8. Pulizia opzioni con validazione
        $options = [
            self::DB_OPTION,
            'eto_riot_api_key',
            'eto_email_settings'
        ];

        foreach ($options as $option) {
            if (get_option($option)) {
                delete_option($option);
            }
            if (get_site_option($option)) {
                delete_site_option($option);
            }
        }
    }

    // 9. Migrazioni database sicure
    public static function maybe_update_db() {
        $current_version = get_option(self::DB_OPTION, '1.0.0');
        
        $migrations = [
            '2.0.0' => 'migrate_to_v2',
            '2.5.0' => 'migrate_to_v2_5',
            '3.0.0' => 'migrate_to_v3'
        ];

        try {
            foreach ($migrations as $version => $method) {
                if (version_compare($current_version, $version, '<')) {
                    call_user_func([self::class, $method]);
                    update_option(self::DB_OPTION, $version);
                }
            }
            update_option(self::DB_OPTION, self::DB_VERSION);
        } catch (Exception $e) {
            error_log("[ETO] Migration error: " . $e->getMessage());
        }
    }

    private static function migrate_to_v2() {
        global $wpdb;
        $wpdb->query('START TRANSACTION');
        try {
            if (!$wpdb->get_var($wpdb->prepare(
                "SHOW COLUMNS FROM {$wpdb->prefix}eto_teams LIKE %s", 
                'tiebreaker'
            ))) {
                $wpdb->query(
                    "ALTER TABLE {$wpdb->prefix}eto_teams
                    ADD tiebreaker MEDIUMINT(8) NOT NULL DEFAULT 0"
                );
            }
            $wpdb->query('COMMIT');
        } catch (Exception $e) {
            $wpdb->query('ROLLBACK');
            throw $e;
        }
    }

    private static function migrate_to_v3() {
        global $wpdb;
        $wpdb->query('START TRANSACTION');
        try {
            if (!$wpdb->get_var($wpdb->prepare(
                "SHOW COLUMNS FROM {$wpdb->prefix}eto_tournaments LIKE %s",
                'max_teams'
            ))) {
                $wpdb->query(
                    "ALTER TABLE {$wpdb->prefix}eto_tournaments
                    ADD COLUMN max_teams INT(3) NOT NULL DEFAULT 16"
                );
            }
            $wpdb->query('COMMIT');
        } catch (Exception $e) {
            $wpdb->query('ROLLBACK');
            throw $e;
        }
    }
}