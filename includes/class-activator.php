<?php
if (!defined('ABSPATH')) exit;

class ETO_Activator {
    public static function check_dependencies() {
        // Controllo PHP versione
        if (version_compare(PHP_VERSION, '7.4', '<')) {
            wp_die(esc_html__('Richiesta PHP 7.4 o superiore. Versione attuale: ', 'eto') . PHP_VERSION);
        }

        // Controllo estensione PDO MySQL
        if (!extension_loaded('pdo_mysql')) {
            wp_die(esc_html__('Estensione PDO MySQL non installata', 'eto'));
        }
    }

    public static function activate() {
        global $wpdb;
        
        // Creazione directory richieste
        $required_dirs = [
            ETO_PLUGIN_DIR . 'logs/',
            ETO_PLUGIN_DIR . 'uploads/'
        ];
        
        foreach ($required_dirs as $dir) {
            wp_mkdir_p($dir, 0755);
        }

        // Creazione tabelle con transazioni
        $wpdb->query('START TRANSACTION');
        try {
            // Tabella tournaments
            $wpdb->query("CREATE TABLE IF NOT EXISTS {$wpdb->prefix}eto_tournaments (
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
            ) ENGINE=InnoDB DEFAULT CHARSET={$wpdb->charset}");

            // Tabella teams
            $wpdb->query("CREATE TABLE IF NOT EXISTS {$wpdb->prefix}eto_teams (
                id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
                tournament_id BIGINT(20) UNSIGNED NOT NULL,
                name VARCHAR(255) NOT NULL,
                captain_id BIGINT(20) UNSIGNED NOT NULL,
                members TEXT NOT NULL,
                created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                PRIMARY KEY (id),
                FOREIGN KEY (tournament_id) REFERENCES {$wpdb->prefix}eto_tournaments(id) ON DELETE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET={$wpdb->charset}");

            $wpdb->query('COMMIT');
        } catch (Exception $e) {
            $wpdb->query('ROLLBACK');
            error_log('[ETO] Activation Error: ' . $e->getMessage());
            wp_die(esc_html__('Errore durante l\'attivazione del plugin: ', 'eto') . esc_html($e->getMessage()));
        }

        // Registrazione cron job
        require_once(plugin_dir_path(__FILE__) . 'includes/class-cron.php');
        if (!wp_next_scheduled('eto_daily_maintenance')) {
            wp_schedule_event(time(), 'daily', 'eto_daily_maintenance');
        }

        // Timestamp attivazione
        update_option('eto_activation_time', current_time('timestamp'));
    }

    public static function deactivate() {
        // Logica di deattivazione
    }
} // CHIUSURA DELLA CLASSE