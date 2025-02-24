<?php
class ETO_Database {
    public static function install() {
        global $wpdb;
        eto_debug_log('Inizio installazione database');
        
        try {
            // Preparazione charset e inclusione dbDelta
            $charset_collate = $wpdb->get_charset_collate();
            require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
            eto_debug_log('Charset e dbDelta pronti: ' . $charset_collate);

            // 1. Tabella Tornei  
            eto_debug_log('Creazione tabella tornei...');
            $table_tournaments = "CREATE TABLE {$wpdb->prefix}eto_tournaments (
                id MEDIUMINT(9) NOT NULL AUTO_INCREMENT,
                name VARCHAR(255) NOT NULL,
                format VARCHAR(20) NOT NULL DEFAULT 'single_elimination',
                start_date DATETIME NOT NULL,
                end_date DATETIME NOT NULL,
                min_players INT(3) NOT NULL,
                max_players INT(3) NOT NULL,
                checkin_enabled TINYINT(1) NOT NULL DEFAULT 0,
                status VARCHAR(20) NOT NULL DEFAULT 'pending',
                PRIMARY KEY  (id)
            ) $charset_collate;";
            
            dbDelta($table_tournaments);
            eto_debug_log('Tabella tornei creata. Risultato: ' . print_r(dbDelta($table_tournaments), true));

            // 2. Tabella Team  
            eto_debug_log('Creazione tabella team...');
            $table_teams = "CREATE TABLE {$wpdb->prefix}eto_teams (
                id MEDIUMINT(9) NOT NULL AUTO_INCREMENT,
                name VARCHAR(255) NOT NULL,
                captain_id BIGINT(20) UNSIGNED NOT NULL,
                tournament_id MEDIUMINT(9),
                status VARCHAR(20) NOT NULL DEFAULT 'pending',
                wins INT(3) UNSIGNED DEFAULT 0,
                losses INT(3) UNSIGNED DEFAULT 0,
                points_diff INT(6) DEFAULT 0,
                created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                PRIMARY KEY  (id)
            ) $charset_collate;";
            
            dbDelta($table_teams);
            eto_debug_log('Tabella team creata. Risultato: ' . print_r(dbDelta($table_teams), true));

            // 3. Tabella Membri Team  
            eto_debug_log('Creazione tabella membri team...');
            $table_members = "CREATE TABLE {$wpdb->prefix}eto_team_members (
                id MEDIUMINT(9) NOT NULL AUTO_INCREMENT,
                team_id MEDIUMINT(9) NOT NULL,
                user_id BIGINT(20) UNSIGNED NOT NULL,
                riot_id VARCHAR(255) NOT NULL,
                discord_tag VARCHAR(32),
                nationality VARCHAR(2),
                is_captain TINYINT(1) NOT NULL DEFAULT 0,
                joined_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                PRIMARY KEY  (id),
                UNIQUE KEY user_team_unique (user_id, team_id)
            ) $charset_collate;";
            
            dbDelta($table_members);
            eto_debug_log('Tabella membri team creata. Risultato: ' . print_r(dbDelta($table_members), true));

            // 4. Tabella Partite  
            eto_debug_log('Creazione tabella partite...');
            $table_matches = "CREATE TABLE {$wpdb->prefix}eto_matches (
                id MEDIUMINT(9) NOT NULL AUTO_INCREMENT,
                tournament_id MEDIUMINT(9) NOT NULL,
                round VARCHAR(50) NOT NULL,
                team1_id MEDIUMINT(9) NOT NULL,
                team2_id MEDIUMINT(9),
                winner_id MEDIUMINT(9),
                screenshot_url VARCHAR(512),
                reported_by BIGINT(20) UNSIGNED,
                confirmed_by BIGINT(20) UNSIGNED,
                confirmed_at DATETIME,
                PRIMARY KEY  (id)
            ) $charset_collate;";
            
            dbDelta($table_matches);
            eto_debug_log('Tabella partite creata. Risultato: ' . print_r(dbDelta($table_matches), true));

            // 5. Tabella Audit Log  
            eto_debug_log('Creazione tabella audit log...');
            $table_audit = "CREATE TABLE {$wpdb->prefix}eto_audit_logs (
                id MEDIUMINT(9) NOT NULL AUTO_INCREMENT,
                user_id BIGINT(20) UNSIGNED NOT NULL,
                action_type VARCHAR(50) NOT NULL,
                details TEXT,
                ip_address VARCHAR(45) NOT NULL,
                created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                PRIMARY KEY  (id)
            ) $charset_collate;";
            
            dbDelta($table_audit);
            eto_debug_log('Tabella audit log creata. Risultato: ' . print_r(dbDelta($table_audit), true));

            // Aggiornamento versione DB
            update_option('eto_db_version', ETO_DB_VERSION);
            eto_debug_log('Installazione completata con successo!');

        } catch (Exception $e) {
            eto_debug_log('ERRORE CRITICO: ' . $e->getMessage());
            throw new Exception('Errore durante la creazione delle tabelle: ' . $e->getMessage());
        }
    }

    public static function uninstall() {
        global $wpdb;
        eto_debug_log('Avvio disinstallazione database...');
        
        $tables = [
            'eto_audit_logs',
            'eto_matches',
            'eto_team_members',
            'eto_teams',
            'eto_tournaments'
        ];

        try {
            foreach ($tables as $table) {
                $full_table_name = $wpdb->prefix . $table;
                eto_debug_log("Tentativo di eliminare tabella: {$full_table_name}");
                $wpdb->query("DROP TABLE IF EXISTS {$full_table_name}");
                eto_debug_log("Tabella eliminata: {$full_table_name}");
            }

            delete_option('eto_db_version');
            delete_option('eto_riot_api_key');
            delete_option('eto_email_enabled');
            eto_debug_log('Disinstallazione database completata');

        } catch (Exception $e) {
            eto_debug_log('ERRORE durante disinstallazione: ' . $e->getMessage());
        }
    }
}
