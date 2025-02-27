<?php
if (!defined('ABSPATH')) exit;

class ETO_Activator {
    public static function activate() {
        global $wpdb;
        
        // 1. PROBLEMA: Mancato uso di dbDelta per la creazione tabelle
        // 2. PROBLEMA: Transazioni non atomiche
        // 3. PROBLEMA: Foreign key senza verifica esistenza tabelle

        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        
        $charset_collate = $wpdb->get_charset_collate();
        $sql = [];

        // Creazione tabella tournaments (corretta)
        $sql[] = "CREATE TABLE {$wpdb->prefix}eto_tournaments (
            id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            name VARCHAR(255) NOT NULL,
            format ENUM('single_elimination','double_elimination','swiss') NOT NULL DEFAULT 'single_elimination',
            game_type VARCHAR(50) NOT NULL DEFAULT 'lol',
            start_date DATETIME NOT NULL,
            end_date DATETIME NOT NULL,
            min_players TINYINT(3) UNSIGNED NOT NULL DEFAULT 1,
            max_players TINYINT(3) UNSIGNED NOT NULL DEFAULT 10,
            max_teams INT(3) NOT NULL DEFAULT 64,
            checkin_enabled BOOLEAN NOT NULL DEFAULT FALSE,
            third_place_match BOOLEAN NOT NULL DEFAULT FALSE,
            status ENUM('pending','active','completed','cancelled') NOT NULL DEFAULT 'pending',
            created_by BIGINT(20) UNSIGNED NOT NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            INDEX tournament_status_idx (status),
            INDEX tournament_dates_idx (start_date, end_date)
        ) $charset_collate;";

        // Creazione tabella teams (corretta)
        $sql[] = "CREATE TABLE {$wpdb->prefix}eto_teams (
            id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            tournament_id BIGINT(20) UNSIGNED NOT NULL,
            name VARCHAR(255) NOT NULL,
            captain_id BIGINT(20) UNSIGNED NOT NULL,
            members TEXT NOT NULL,
            status ENUM('active','pending','deleted') NOT NULL DEFAULT 'pending',
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            FOREIGN KEY (tournament_id) REFERENCES {$wpdb->prefix}eto_tournaments(id) ON DELETE CASCADE
        ) $charset_collate;";

        // 4. AGGIUNTA: Tabella matches mancante
        $sql[] = "CREATE TABLE {$wpdb->prefix}eto_matches (
            id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            tournament_id BIGINT(20) UNSIGNED NOT NULL,
            round VARCHAR(50) NOT NULL,
            team1_id BIGINT(20) UNSIGNED NOT NULL,
            team2_id BIGINT(20) UNSIGNED NOT NULL,
            status ENUM('pending','completed','canceled') NOT NULL DEFAULT 'pending',
            result TEXT,
            PRIMARY KEY (id),
            FOREIGN KEY (tournament_id) REFERENCES {$wpdb->prefix}eto_tournaments(id) ON DELETE CASCADE
        ) $charset_collate;";

        // Esecuzione transazionale
        $wpdb->query('START TRANSACTION');
        try {
            foreach ($sql as $query) {
                dbDelta($query);
            }
            $wpdb->query('COMMIT');
        } catch (Exception $e) {
            $wpdb->query('ROLLBACK');
            error_log('[ETO] Database Error: ' . $e->getMessage());
            wp_die(esc_html__('Errore durante la creazione delle tabelle: ', 'eto') . esc_html($e->getMessage()));
        }

        // 5. CORREZIONE: Registrazione cron job spostata qui
        if (!wp_next_scheduled('eto_daily_maintenance')) {
            wp_schedule_event(time(), 'daily', 'eto_daily_maintenance');
        }

        update_option('eto_activation_time', time());
    }

    public static function deactivate() {
        // 6. AGGIUNTA: Pulizia eventi programmati
        wp_clear_scheduled_hook('eto_daily_maintenance');
        delete_option('eto_activation_time');
        
        // 7. OPZIONALE: Mantenimento dati per disinstallazione pulita
        // wp_clear_scheduled_hook('eto_hourly_tasks');
        // wp_clear_scheduled_hook('eto_daily_cleanup');
    }
}