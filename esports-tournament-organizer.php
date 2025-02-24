<?php
/**
 * Gestione del database del plugin
 * @package eSports Tournament Organizer
 * @since 2.0.0
 */

class ETO_Database {
    const DB_VERSION = '2.0.0';
    const DB_OPTION = 'eto_db_version';

    /**
     * Crea o aggiorna le tabelle del database
     */
    public static function install() {
        global $wpdb;
        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');

        $charset_collate = $wpdb->get_charset_collate();

        // 1. Tabella Tornei
        $sql = "CREATE TABLE {$wpdb->prefix}eto_tournaments (
            id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            name VARCHAR(255) NOT NULL,
            format ENUM('single_elimination','double_elimination','swiss') NOT NULL DEFAULT 'single_elimination',
            game_type VARCHAR(50) NOT NULL DEFAULT 'lol',
            start_date DATETIME NOT NULL,
            end_date DATETIME NOT NULL,
            min_players TINYINT(3) UNSIGNED NOT NULL DEFAULT 3,
            max_players TINYINT(3) UNSIGNED NOT NULL DEFAULT 5,
            checkin_enabled BOOLEAN NOT NULL DEFAULT FALSE,
            third_place_match BOOLEAN NOT NULL DEFAULT FALSE,
            status ENUM('pending','active','completed','cancelled') NOT NULL DEFAULT 'pending',
            created_by BIGINT(20) UNSIGNED NOT NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY  (id),
            INDEX tournament_status_idx (status),
            INDEX tournament_dates_idx (start_date, end_date)
        ) ENGINE=InnoDB {$charset_collate};";
        dbDelta($sql);

        // 2. Tabella Team
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
            PRIMARY KEY  (id),
            FOREIGN KEY (tournament_id) REFERENCES {$wpdb->prefix}eto_tournaments(id) ON DELETE CASCADE,
            INDEX team_tournament_idx (tournament_id),
            INDEX team_status_idx (status),
            INDEX team_ranking_idx (wins DESC, points_diff DESC, tiebreaker DESC)
        ) ENGINE=InnoDB {$charset_collate};";
        dbDelta($sql);

        // 3. Tabella Membri Team
        $sql = "CREATE TABLE {$wpdb->prefix}eto_team_members (
            id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            team_id BIGINT(20) UNSIGNED NOT NULL,
            user_id BIGINT(20) UNSIGNED NOT NULL,
            riot_id VARCHAR(255),
            discord_tag VARCHAR(32),
            nationality CHAR(2),
            is_captain BOOLEAN NOT NULL DEFAULT FALSE,
            joined_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY  (id),
            FOREIGN KEY (team_id) REFERENCES {$wpdb->prefix}eto_teams(id) ON DELETE CASCADE,
            UNIQUE KEY user_team_unique (user_id, team_id),
            INDEX member_team_idx (team_id)
        ) ENGINE=InnoDB {$charset_collate};";
        dbDelta($sql);

        // 4. Tabella Partite
        $sql = "CREATE TABLE {$wpdb->prefix}eto_matches (
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
            PRIMARY KEY  (id),
            FOREIGN KEY (tournament_id) REFERENCES {$wpdb->prefix}eto_tournaments(id) ON DELETE CASCADE,
            FOREIGN KEY (team1_id) REFERENCES {$wpdb->prefix}eto_teams(id) ON DELETE CASCADE,
            FOREIGN KEY (team2_id) REFERENCES {$wpdb->prefix}eto_teams(id) ON DELETE CASCADE,
            INDEX match_round_idx (round),
            INDEX match_status_idx (status),
            INDEX match_teams_idx (team1_id, team2_id)
        ) ENGINE=InnoDB {$charset_collate};";
        dbDelta($sql);

        // 5. Tabella Audit Log
        $sql = "CREATE TABLE {$wpdb->prefix}eto_audit_logs (
            id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            user_id BIGINT(20) UNSIGNED NOT NULL,
            action_type VARCHAR(50) NOT NULL,
            object_type VARCHAR(50) NOT NULL,
            object_id BIGINT(20) UNSIGNED,
            details TEXT,
            ip_address VARCHAR(45) NOT NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY  (id),
            INDEX log_action_idx (action_type),
            INDEX log_object_idx (object_type, object_id),
            INDEX log_user_idx (user_id)
        ) ENGINE=InnoDB {$charset_collate};";
        dbDelta($sql);

        // Aggiorna versione database
        update_option(self::DB_OPTION, self::DB_VERSION);

        // Aggiungi la colonna tiebreaker se mancante
        if (!$wpdb->get_var("SHOW COLUMNS FROM {$wpdb->prefix}eto_teams LIKE 'tiebreaker'")) {
            $wpdb->query("ALTER TABLE {$wpdb->prefix}eto_teams ADD tiebreaker MEDIUMINT(8) NOT NULL DEFAULT 0 AFTER points_diff");
        }
    }

    /**
     * Disinstalla il plugin rimuovendo tutte le tabelle
     */
    public static function uninstall() {
        global $wpdb;
        
        $tables = [
            'eto_audit_logs',
            'eto_matches',
            'eto_team_members',
            'eto_teams',
            'eto_tournaments'
        ];

        foreach ($tables as $table) {
            $wpdb->query("DROP TABLE IF EXISTS {$wpdb->prefix}{$table}");
        }

        delete_option(self::DB_OPTION);
        delete_option('eto_riot_api_key');
        delete_option('eto_email_settings');
    }

    /**
     * Aggiorna lo schema del database se necessario
     */
    public static function maybe_update_db() {
        $current_version = get_option(self::DB_OPTION, '1.0.0');
        
        if (version_compare($current_version, self::DB_VERSION, '<')) {
            self::install();
            
            // Migrazioni specifiche per versione
            if (version_compare($current_version, '2.0.0', '<')) {
                self::migrate_to_v2();
            }
        }
    }

    /**
     * Migrazione per la versione 2.0.0
     */
    private static function migrate_to_v2() {
        global $wpdb;
        
        // Aggiungi colonna tiebreaker
        if (!$wpdb->get_var("SHOW COLUMNS FROM {$wpdb->prefix}eto_teams LIKE 'tiebreaker'")) {
            $wpdb->query("ALTER TABLE {$wpdb->prefix}eto_teams ADD tiebreaker MEDIUMINT(8) NOT NULL DEFAULT 0 AFTER points_diff");
        }

        // Aggiungi nuovi indici
        $wpdb->query("CREATE INDEX team_ranking_idx ON {$wpdb->prefix}eto_teams (wins DESC, points_diff DESC, tiebreaker DESC)");
    }
}
