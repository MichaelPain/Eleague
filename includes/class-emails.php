<?php
if (!defined('ABSPATH')) exit;

class ETO_Emails {
    private static $email_headers = [
        'Content-Type: text/html; charset=UTF-8',
        'From: ' . sanitize_email(get_option('admin_email'))
    ];

    public static function send_privilege_notification($user_id) {
        try {
            $user = get_userdata(absint($user_id));
            if (!$user || !is_email($user->user_email)) {
                throw new Exception(__('Utente o email non validi', 'eto'));
            }

            return self::send(
                sanitize_email($user->user_email),
                esc_html__('Privilegi plugin concessi', 'eto'),
                'privilege-notification',
                [
                    'display_name' => esc_html($user->display_name),
                    'site_name' => esc_html(get_bloginfo('name')),
                    'date' => date_i18n(get_option('date_format'))
                ]
            );
        } catch (Exception $e) {
            error_log('[ETO] Errore notifica privilegi: ' . $e->getMessage());
            return false;
        }
    }

    public static function send_checkin_reminder($tournament_id, $user_id) {
        $tournament = ETO_Tournament::get(absint($tournament_id));
        $user = get_userdata(absint($user_id));
        $team = ETO_Team::get_user_team($user_id, $tournament_id);

        if (!$tournament || !$user || !$team) {
            return false;
        }

        $data = [
            'tournament_name' => esc_html($tournament->name),
            'start_date' => date_i18n(get_option('date_format'), strtotime($tournament->start_date)),
            'start_time' => date_i18n(get_option('time_format'), strtotime($tournament->start_date)),
            'team_name' => esc_html($team->name),
            'checkin_link' => self::generate_checkin_link($tournament_id)
        ];

        return self::send(
            sanitize_email($user->user_email),
            sprintf(__('[Promemoria] Check-in per %s', 'eto'), esc_html($tournament->name)),
            'checkin-reminder',
            $data
        );
    }

    public static function send_result_confirmation($match_id, $winner_id) {
        $match = ETO_Match::get(absint($match_id));
        $tournament = ETO_Tournament::get(absint($match->tournament_id));
        $winner_team = ETO_Team::get(absint($winner_id));

        if (!$match || !$tournament || !$winner_team) {
            return false;
        }

        $captains = self::get_match_captains($match_id);
        $loser_id = ($winner_id == $match->team1_id) ? $match->team2_id : $match->team1_id;
        $loser_team = ETO_Team::get(absint($loser_id));

        $data = [
            'tournament_name' => esc_html($tournament->name),
            'round' => esc_html($match->round),
            'winner_team' => esc_html($winner_team->name),
            'loser_team' => esc_html($loser_team->name),
            'results_link' => self::generate_results_link($tournament->id)
        ];

        foreach ($captains as $email) {
            self::send(
                sanitize_email($email),
                sprintf(__('[Conferma] Risultato partita - %s', 'eto'), esc_html($tournament->name)),
                'result-confirmed',
                $data
            );
        }

        return true;
    }

    private static function get_template($name, $data) {
        $allowed_templates = ['checkin-reminder', 'result-confirmed', 'privilege-notification'];
        $template_file = ETO_PLUGIN_DIR . self::TEMPLATE_PATH . sanitize_file_name($name) . '.php';

        if (!in_array($name, $allowed_templates) || !file_exists($template_file)) {
            throw new Exception(sprintf(__('Template email %s non valido', 'eto'), esc_html($name)));
        }

        ob_start();
        foreach ($data as $key => $value) {
            $$key = is_array($value) ? array_map('esc_html', $value) : esc_html($value);
        }

        include $template_file;
        return ob_get_clean();
    }

    private static function generate_checkin_link($tournament_id) {
        return add_query_arg(
            [
                'action' => 'checkin',
                'tournament_id' => absint($tournament_id),
                'nonce' => wp_create_nonce('eto_checkin_action')
            ],
            home_url('/checkin')
        );
    }

    private static function log_email($to, $subject, $status, $error = '') {
        ETO_Audit_Log::add([
            'action_type' => 'email_sent',
            'object_type' => 'email',
            'details' => [
                'recipient' => sanitize_email($to),
                'subject' => sanitize_text_field($subject),
                'status' => sanitize_key($status),
                'error' => sanitize_textarea_field($error)
            ]
        ]);
    }

    private static function get_match_captains($match_id) {
        global $wpdb;
        return $wpdb->get_col(
            $wpdb->prepare(
                "SELECT u.user_email
                FROM {$wpdb->prefix}users u
                INNER JOIN {$wpdb->prefix}eto_teams t ON t.captain_id = u.ID
                INNER JOIN {$wpdb->prefix}eto_matches m ON m.team1_id = t.id OR m.team2_id = t.id
                WHERE m.id = %d",
                absint($match_id)
            )
        );
    }

    private static function generate_results_link($tournament_id) {
        return esc_url(add_query_arg(
            'tournament_id',
            absint($tournament_id),
            home_url('/tournament-results')
        ));
    }

    private static function send($to, $subject, $template, $data) {
        $body = self::get_template($template, $data);
        $headers = self::$email_headers;

        $sent = wp_mail(
            sanitize_email($to),
            sanitize_text_field($subject),
            $body,
            $headers
        );

        if (!$sent) {
            $error = error_get_last();
            self::log_email($to, $subject, 'failed', $error['message']);
        } else {
            self::log_email($to, $subject, 'sent');
        }

        return $sent;
    }
}