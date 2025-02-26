<?php
if (!defined('ABSPATH')) exit;

class ETO_Cron {
    const CLEANUP_DAYS = 30;

    public static function schedule_events() {
        if (!wp_next_scheduled('eto_hourly_tasks')) {
            wp_schedule_event(time(), 'hourly', 'eto_hourly_tasks');
        }
        
        if (!wp_next_scheduled('eto_daily_cleanup')) {
            wp_schedule_event(time(), 'daily', 'eto_daily_cleanup');
        }
        
        if (!wp_next_scheduled('eto_daily_maintenance')) {
            wp_schedule_event(time(), 'daily', 'eto_daily_maintenance');
        }
    }

    public static function hourly_tasks() {
        global $wpdb;
        
        try {
            // 1. Aggiornamento stati tornei
            $wpdb->query($wpdb->prepare(
                "UPDATE {$wpdb->prefix}eto_tournaments
                SET status = CASE
                    WHEN start_date <= %s AND status = 'pending' THEN 'active'
                    WHEN end_date <= %s AND status = 'active' THEN 'completed'
                    ELSE status
                END",
                current_time('mysql'),
                current_time('mysql')
            ));

            // 2. Notifiche tornei imminenti
            $upcoming = $wpdb->get_results($wpdb->prepare(
                "SELECT * FROM {$wpdb->prefix}eto_tournaments
                WHERE start_date BETWEEN %s AND %s
                AND status = 'pending'",
                current_time('mysql'),
                date('Y-m-d H:i:s', strtotime('+1 hour'))
            ));

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
            $wpdb->query($wpdb->prepare(
                "DELETE FROM {$wpdb->prefix}eto_tournaments
                WHERE end_date <= %s AND status = 'completed'",
                $cleanup_date
            ));

            // 2. Pulizia partite vecchie
            $wpdb->query($wpdb->prepare(
                "DELETE FROM {$wpdb->prefix}eto_matches
                WHERE confirmed_at <= %s AND status = 'completed'",
                $cleanup_date
            ));

            // 3. Pulizia log
            $wpdb->query($wpdb->prepare(
                "DELETE FROM {$wpdb->prefix}eto_audit_logs
                WHERE created_at <= %s",
                $cleanup_date
            ));

            error_log('[ETO] Pulizia giornaliera completata');
        } catch (Exception $e) {
            error_log('[ETO] Errore pulizia giornaliera: ' . $e->getMessage());
        }
    }

    public static function daily_maintenance() {
        try {
            $installer_id = get_site_option(ETO_Installer::INSTALLER_META);
            if ($installer_id && !get_user_by('id', $installer_id)) {
                ETO_Installer::revoke_privileges();
                error_log('[ETO] Privilegi installatore revocati');
            }

            error_log('[ETO] Manutenzione giornaliera completata');
        } catch (Exception $e) {
            error_log('[ETO] Errore manutenzione: ' . $e->getMessage());
        }
    }

    public static function calculate_tiebreakers_for_active() {
        global $wpdb;
        
        $active_tournaments = $wpdb->get_col($wpdb->prepare(
            "SELECT id FROM {$wpdb->prefix}eto_tournaments
            WHERE status = 'active' AND format = %s",
            'swiss'
        ));

        foreach ($active_tournaments as $tournament_id) {
            ETO_Swiss::calculate_tiebreakers(absint($tournament_id));
        }
    }

    public static function add_custom_schedules($schedules) {
        $schedules['every_6h'] = [
            'interval' => 6 * HOUR_IN_SECONDS,
            'display' => __('Ogni 6 ore', 'eto')
        ];
        return $schedules;
    }
}

// Registra gli hook per gli eventi cron
add_filter('cron_schedules', ['ETO_Cron', 'add_custom_schedules']);
add_action('eto_hourly_tasks', ['ETO_Cron', 'hourly_tasks']);
add_action('eto_daily_cleanup', ['ETO_Cron', 'daily_cleanup']);
add_action('eto_daily_maintenance', ['ETO_Cron', 'daily_maintenance']);