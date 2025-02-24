<?php
class ETO_Cron {
    // Inizializza il sistema di cron
    public static function init() {
        add_action('eto_daily_maintenance', [self::class, 'daily_tasks']);
        
        if (!wp_next_scheduled('eto_daily_maintenance')) {
            wp_schedule_event(time(), 'daily', 'eto_daily_maintenance');
        }
    }

    // Attività giornaliere automatizzate
    public static function daily_tasks() {
        self::update_tournament_statuses();
        self::send_reminder_notifications();
    }

    // Aggiorna stati tornei
    private static function update_tournament_statuses() {
        global $wpdb;

        // Aggiorna a 'active' se la data inizio è passata
        $wpdb->query(
            "UPDATE {$wpdb->prefix}eto_tournaments 
            SET status = 'active' 
            WHERE status = 'pending' 
            AND start_date <= UTC_TIMESTAMP()"
        );

        // Aggiorna a 'completed' se la data fine è passata
        $wpdb->query(
            "UPDATE {$wpdb->prefix}eto_tournaments 
            SET status = 'completed' 
            WHERE status = 'active' 
            AND end_date <= UTC_TIMESTAMP()"
        );
    }

    // Invia notifiche per check-in imminenti
    private static function send_reminder_notifications() {
        global $wpdb;
        
        $tournaments = $wpdb->get_results(
            "SELECT * FROM {$wpdb->prefix}eto_tournaments 
            WHERE checkin_enabled = 1 
            AND status = 'pending' 
            AND start_date BETWEEN UTC_TIMESTAMP() AND DATE_ADD(UTC_TIMESTAMP(), INTERVAL 1 HOUR)"
        );

        foreach ($tournaments as $tournament) {
            $teams = $wpdb->get_col(
                $wpdb->prepare(
                    "SELECT id FROM {$wpdb->prefix}eto_teams 
                    WHERE tournament_id = %d",
                    $tournament->id
                )
            );

            foreach ($teams as $team_id) {
                $captain_id = $wpdb->get_var(
                    $wpdb->prepare(
                        "SELECT captain_id FROM {$wpdb->prefix}eto_teams 
                        WHERE id = %d",
                        $team_id
                    )
                );
                ETO_Emails::send_checkin_reminder($team_id, $captain_id);
            }
        }
    }

    // Disattiva il cron alla disinstallazione
    public static function deactivate() {
        wp_clear_scheduled_hook('eto_daily_maintenance');
    }
}
