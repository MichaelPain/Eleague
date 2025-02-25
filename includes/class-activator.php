<?php
class ETO_Activator {
    public static function activate() {
        global $wpdb;

        // 1. Verifica permessi
        if (!current_user_can('activate_plugins')) {
            wp_die(__('Permessi insufficienti per attivare il plugin', 'eto'));
        }

        // 2. Configurazione ruoli utente
        $admin_role = get_role('administrator');
        $capabilities = [
            'manage_eto_tournaments',
            'edit_eto_teams',
            'delete_eto_matches',
            'confirm_eto_results'
        ];

        foreach ($capabilities as $cap) {
            if (!$admin_role->has_cap($cap)) {
                $admin_role->add_cap($cap);
            }
        }

        // 3. Setup database con transazioni
        $wpdb->query('START TRANSACTION');
        try {
            require_once(plugin_dir_path(__FILE__) . 'class-database.php');
            
            // Creazione tabelle con nomi sanitizzati
            $tables = [
                'tournaments' => "CREATE TABLE IF NOT EXISTS {$wpdb->prefix}eto_tournaments (
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
                ) ENGINE=InnoDB DEFAULT CHARSET={$wpdb->charset};",

                'teams' => "CREATE TABLE IF NOT EXISTS {$wpdb->prefix}eto_teams (...)"
            ];

            foreach ($tables as $table_sql) {
                if (false === $wpdb->query($table_sql)) {
                    throw new Exception($wpdb->last_error);
                }
            }

            $wpdb->query('COMMIT');
            ETO_Database::maybe_update_db();

        } catch (Exception $e) {
            $wpdb->query('ROLLBACK');
            error_log('[ETO] Activation Error: ' . $e->getMessage());
            wp_die(__('Errore durante l\'attivazione del plugin: ', 'eto') . $e->getMessage());
        }

        // 4. Pianificazione eventi cron
        require_once(plugin_dir_path(__FILE__) . 'class-cron.php');
        if (!wp_next_scheduled('eto_daily_maintenance')) {
            wp_schedule_event(time(), 'daily', 'eto_daily_maintenance');
        }

        // 5. Registrazione attivazione
        update_option('eto_activation_time', current_time('timestamp'));
    }
}
