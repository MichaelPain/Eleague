<?php
class ETO_Database {
    const DB_VERSION = '3.0.0';
    const DB_OPTION = 'eto_db_version';

    public static function install() {
        global $wpdb;

        try {  // Parentesi graffa aggiunta per delimitare il blocco try
            require_once(ABSPATH . 'wp-admin/includes/upgrade.php');

            $charset_collate = $wpdb->get_charset_collate();

            // **Tabella Tornei (formato corretto senza backticks)**
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
                PRIMARY KEY  (id),
                INDEX tournament_status_idx (status),
                INDEX tournament_dates_idx (start_date, end_date)
            ) ENGINE=InnoDB $charset_collate;";
            dbDelta($sql);

            // **Tabella Team (formattazione corretta)**
            $sql = "CREATE TABLE IF NOT EXISTS {$wpdb->prefix}eto_teams (
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

            // **Tabella Membri Team (formattazione corretta)**
            $sql = "CREATE TABLE IF NOT EXISTS {$wpdb->prefix}eto_team_members (
                id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
                team_id BIGINT(20) UNSIGNED NOT NULL,
                user_id BIGINT(20) UNSIGNED NOT NULL,
                riot_id VARCHAR(255),
                discord_tag VARCHAR(32),
                nationality CHAR(2),
                is_captain BOOLEAN NOT NULL DEFAULT FALSE,
                joined_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                PRIMARY KEY (id),
                FOREIGN KEY (team_id) REFERENCES {$wpdb->prefix}eto_teams(id) ON DELETE CASCADE,
                UNIQUE KEY user_team_unique (user_id, team_id)
            ) ENGINE=InnoDB $charset_collate;";
            dbDelta($sql);

            // **Tabella Partite (formattazione corretta)**
            $sql = "CREATE TABLE IF NOT EXISTS {$wpdb->prefix}eto_matches (
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
                FOREIGN KEY (tournament_id) REFERENCES {$wpdb->prefix}eto_tournaments(id) ON DELETE CASCADE,
                FOREIGN KEY (team1_id) REFERENCES {$wpdb->prefix}eto_teams(id) ON DELETE CASCADE,
                FOREIGN KEY (team2_id) REFERENCES {$wpdb->prefix}eto_teams(id) ON DELETE CASCADE,
                INDEX match_status_idx (status)
            ) ENGINE=InnoDB $charset_collate;";
            dbDelta($sql);

            // **Tabella Audit Log (formattazione corretta)**
            $sql = "CREATE TABLE IF NOT EXISTS {$wpdb->prefix}eto_audit_logs (
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
            ) ENGINE=InnoDB $charset_collate;";
            dbDelta($sql);

            update_option(self::DB_OPTION, self::DB_VERSION);
            self::maybe_update_db();
        } catch (Exception $e) {  // Catch ora correttamente associato al try precedente
            error_log('[ETO] Database Error: ' . $e->getMessage());
            wp_die(__('Errore durante l\'installazione del database.', 'eto'));
        }
    }

    public static function uninstall() {
        global $wpdb;

        // **Eliminazione sicura con prepared statements**
        $tables = $wpdb->get_col($wpdb->prepare("SHOW TABLES LIKE %s", $wpdb->prefix . 'eto_%'));
        foreach ($tables as $table) {
            $wpdb->query($wpdb->prepare("DROP TABLE IF EXISTS %s", $table));
        }

        // **Pulizia opzioni**
        $options = [
            self::DB_OPTION,
            'eto_riot_api_key',
            'eto_email_settings',
            'eto_plugin_settings'
        ];
        foreach ($options as $option) {
            delete_option($option);
            delete_site_option($option);
        }
    }

    public static function maybe_update_db() {
        $current_version = get_option(self::DB_OPTION, '1.0.0');
        if (version_compare($current_version, self::DB_VERSION, '<')) {
            $update_versions = [
                '2.0.0' => 'migrate_to_v2',
                '2.5.0' => 'migrate_to_v2_5',
                '2.5.1' => 'migrate_to_v2_5_1',
                '3.0.0' => 'migrate_to_v3'
            ];
            try {
                foreach ($update_versions as $version => $method) {
                    if (version_compare($current_version, $version, '<')) {
                        call_user_func([self::class, $method]);
                        update_option(self::DB_OPTION, $version);
                    }
                }
                update_option(self::DB_OPTION, self::DB_VERSION);
            } catch (Exception $e) {
                error_log("[ETO] Migration failed: " . $e->getMessage());
            }
        }
    }

    private static function migrate_to_v2() {
        global $wpdb;
        $wpdb->query('START TRANSACTION');
        try {
            if (!$wpdb->get_var("SHOW COLUMNS FROM {$wpdb->prefix}eto_teams LIKE 'tiebreaker'")) {
                $wpdb->query(
                    "ALTER TABLE {$wpdb->prefix}eto_teams
                    ADD tiebreaker MEDIUMINT(8) NOT NULL DEFAULT 0
                    AFTER points_diff"
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
            if (!$wpdb->get_var("SHOW COLUMNS FROM {$wpdb->prefix}eto_tournaments LIKE 'max_teams'")) {
                $wpdb->query(
                    "ALTER TABLE {$wpdb->prefix}eto_tournaments
                    ADD COLUMN max_teams INT(3) NOT NULL DEFAULT 16
                    AFTER max_players"
                );
            }
            $wpdb->query('COMMIT');
        } catch (Exception $e) {
            $wpdb->query('ROLLBACK');
            throw $e;
        }
    }

    private static function migrate_to_v2_5() {}
    private static function migrate_to_v2_5_1() {}
}