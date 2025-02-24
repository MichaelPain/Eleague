<?php
class ETO_Cron {
    const CLEANUP_DAYS = 180; // 6 mesi

    public static function init() {
        add_action('eto_hourly_check', [__CLASS__, 'hourly_tasks']);
        add_action('eto_daily_cleanup', [__CLASS__, 'daily_cleanup']);
    }

    public static function schedule_events() {
        if (!wp_next_scheduled('eto_hourly_check')) {
            wp_schedule_event(
                strtotime('+15 minutes'), 
                'hourly', 
                'eto_hourly_check'
            );
        }

        if (!wp_next_scheduled('eto_daily_cleanup')) {
            wp_schedule_event(
                strtotime('tomorrow 3:00'), 
                'daily', 
                'eto_daily_cleanup'
            );
        }
    }

    public static function clear_scheduled_events() {
        wp_clear_scheduled_hook('eto_hourly_check');
        wp_clear_scheduled_hook('eto_daily_cleanup');
    }

    public static function hourly_tasks() {
        global $wpdb;

        try {
            // 1. Aggiorna stato tornei
            $wpdb->query(
                $wpdb->prepare(
                    "UPDATE {$wpdb->prefix}eto_tournaments
                    SET status = 
                        CASE 
                            WHEN start_date <= %s AND status = 'pending' THEN 'active'
                            WHEN end_date <= %s AND status = 'active' THEN 'completed'
                            ELSE status
                        END",
                    current_time('mysql'),
                    current_time('mysql')
                )
            );

            // 2. Notifiche tornei imminenti
            $upcoming = $wpdb->get_results(
                $wpdb->prepare(
                    "SELECT * FROM {$wpdb->prefix}eto_tournaments
                    WHERE start_date BETWEEN %s AND %s
                    AND status = 'pending'",
                    current_time('mysql'),
                    date('Y-m-d H:i:s', strtotime('+1 hour'))
                )
            );

            foreach ($upcoming as $tournament) {
                if (class_exists('ETO_Emails')) {
                    ETO_Emails::send_tournament_reminder($tournament->id);
                }
            }

            // 3. Aggiorna leaderboard
            if (class_exists('ETO_Swiss')) {
                ETO_Swiss::calculate_tiebreakers_for_active();
            }

            error_log('[ETO] Hourly tasks eseguiti con successo');

        } catch (Exception $e) {
            error_log('[ETO] Errore tasks orari: ' . $e->getMessage());
        }
    }

    public static function daily_cleanup() {
        global $wpdb;
        $cleanup_date = date('Y-m-d H:i:s', strtotime('-' . self::CLEANUP_DAYS . ' days'));

        try {
            // 1. Pulizia tornei completati
            $wpdb->query(
                $wpdb->prepare(
                    "DELETE FROM {$wpdb->prefix}eto_tournaments
                    WHERE end_date <= %s
                    AND status = 'completed'",
                    $cleanup_date
                )
            );

            // 2. Pulizia partite vecchie
            $wpdb->query(
                $wpdb->prepare(
                    "DELETE FROM {$wpdb->prefix}eto_matches
                    WHERE confirmed_at <= %s
                    AND status = 'completed'",
                    $cleanup_date
                )
            );

            // 3. Pulizia log
            $wpdb->query(
                $wpdb->prepare(
                    "DELETE FROM {$wpdb->prefix}eto_audit_logs
                    WHERE created_at <= %s",
                    $cleanup_date
                )
            );

            error_log('[ETO] Pulizia giornaliera completata');

        } catch (Exception $e) {
            error_log('[ETO] Errore pulizia giornaliera: ' . $e->getMessage());
        }
    }

    // Nuovo metodo per tornei Swiss System
    public static function calculate_tiebreakers_for_active() {
        global $wpdb;

        $active_tournaments = $wpdb->get_col(
            $wpdb->prepare(
                "SELECT id FROM {$wpdb->prefix}eto_tournaments
                WHERE status = 'active'
                AND format = %s",
                'swiss'
            )
        );

        foreach ($active_tournaments as $tournament_id) {
            ETO_Swiss::calculate_tiebreakers($tournament_id);
        }
    }
}
