<?php
/**
 * Classe per la gestione delle operazioni pianificate
 * @package eSports Tournament Organizer
 * @since 1.1.0
 */

class ETO_Cron {
    const HOURLY_CHECK = 'eto_hourly_tournament_check';
    const DAILY_CLEANUP = 'eto_daily_cleanup';

    /**
     * Inizializza i cron job
     */
    public static function init() {
        add_action(self::HOURLY_CHECK, [__CLASS__, 'check_tournament_statuses']);
        add_action(self::DAILY_CLEANUP, [__CLASS__, 'cleanup_old_data']);
        
        self::schedule_events();
    }

    /**
     * Programma gli eventi ricorrenti
     */
    private static function schedule_events() {
        if (!wp_next_scheduled(self::HOURLY_CHECK)) {
            wp_schedule_event(time(), 'hourly', self::HOURLY_CHECK);
        }

        if (!wp_next_scheduled(self::DAILY_CLEANUP)) {
            wp_schedule_event(time(), 'daily', self::DAILY_CLEANUP);
        }
    }

    /**
     * Disattiva i cron job alla disattivazione del plugin
     */
    public static function deactivate() {
        wp_clear_scheduled_hook(self::HOURLY_CHECK);
        wp_clear_scheduled_hook(self::DAILY_CLEANUP);
    }

    /**
     * Controlla lo stato dei tornei
     */
    public static function check_tournament_statuses() {
        global $wpdb;
        
        // Aggiorna tornei da "pending" a "active"
        $wpdb->query(
            $wpdb->prepare(
                "UPDATE {$wpdb->prefix}eto_tournaments 
                SET status = 'active' 
                WHERE status = 'pending' 
                AND start_date <= %s",
                current_time('mysql')
            )
        );

        // Aggiorna tornei da "active" a "completed"
        $wpdb->query(
            $wpdb->prepare(
                "UPDATE {$wpdb->prefix}eto_tournaments 
                SET status = 'completed' 
                WHERE status = 'active' 
                AND end_date <= %s",
                current_time('mysql')
            )
        );

        // Notifica per tornei in imminente inizio
        $upcoming_tournaments = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT * FROM {$wpdb->prefix}eto_tournaments 
                WHERE status = 'pending' 
                AND start_date BETWEEN %s AND %s",
                current_time('mysql'),
                date('Y-m-d H:i:s', strtotime('+1 hour'))
            )
        );

        foreach ($upcoming_tournaments as $tournament) {
            ETO_Emails::send_tournament_reminder($tournament->id);
        }
    }

    /**
     * Pulizia dati obsoleti
     */
    public static function cleanup_old_data() {
        global $wpdb;

        // Elimina dati più vecchi di 6 mesi
        $cleanup_date = date('Y-m-d H:i:s', strtotime('-6 months'));
        
        // Tabella tornei
        $wpdb->query(
            $wpdb->prepare(
                "DELETE FROM {$wpdb->prefix}eto_tournaments 
                WHERE end_date <= %s 
                AND status = 'completed'",
                $cleanup_date
            )
        );

        // Tabella matches
        $wpdb->query(
            $wpdb->prepare(
                "DELETE FROM {$wpdb->prefix}eto_matches 
                WHERE confirmed_at <= %s",
                $cleanup_date
            )
        );

        // Tabella audit log
        $wpdb->query(
            $wpdb->prepare(
                "DELETE FROM {$wpdb->prefix}eto_audit_logs 
                WHERE created_at <= %s",
                $cleanup_date
            )
        );
    }
}
