<?php
/**
 * Classe per la gestione delle comunicazioni email
 * @package eSports Tournament Organizer
 * @since 1.1.0
 */

class ETO_Emails {
    const TEMPLATE_PATH = 'templates/emails/';
    
    /**
     * Invia una notifica generica
     */
    public static function send($to, $subject, $template, $data = []) {
        try {
            // Verifica parametri obbligatori
            if (!is_email($to) || empty($subject) || empty($template)) {
                throw new Exception(__('Parametri email non validi', 'eto'));
            }

            // Carica il template
            $headers = ['Content-Type: text/html; charset=UTF-8'];
            $body = self::get_template($template, $data);
            
            // Invia l'email
            $result = wp_mail($to, $subject, $body, $headers);
            
            // Registra l'invio nel log
            if ($result) {
                self::log_email($to, $subject, 'success');
            } else {
                throw new Exception(__('Invio email fallito', 'eto'));
            }

            return $result;

        } catch (Exception $e) {
            self::log_email($to, $subject, 'error', $e->getMessage());
            return false;
        }
    }

    /**
     * Notifica promemoria check-in
     */
    public static function send_checkin_reminder($tournament_id, $user_id) {
        $tournament = ETO_Tournament::get($tournament_id);
        $user = get_userdata($user_id);
        $team = ETO_Team::get_user_team($user_id, $tournament_id);

        $data = [
            'tournament_name' => esc_html($tournament->name),
            'start_date'      => date_i18n(get_option('date_format'), strtotime($tournament->start_date)),
            'start_time'      => date_i18n(get_option('time_format'), strtotime($tournament->start_date)),
            'team_name'       => esc_html($team->name),
            'checkin_link'    => self::generate_checkin_link($tournament_id)
        ];

        return self::send(
            $user->user_email,
            sprintf(__('[Promemoria] Check-in per %s', 'eto'), $tournament->name),
            'checkin-reminder',
            $data
        );
    }

    /**
     * Conferma risultato della partita
     */
    public static function send_result_confirmation($match_id, $winner_id) {
        $match = ETO_Match::get($match_id);
        $tournament = ETO_Tournament::get($match->tournament_id);
        $winner_team = ETO_Team::get($winner_id);
        
        // Ottieni i capitani dei team
        $captains = self::get_match_captains($match_id);

        $data = [
            'tournament_name' => esc_html($tournament->name),
            'round'           => esc_html($match->round),
            'winner_team'     => esc_html($winner_team->name),
            'loser_team'      => ($winner_id == $match->team1_id) ? 
                                  ETO_Team::get($match->team2_id)->name : 
                                  ETO_Team::get($match->team1_id)->name,
            'results_link'    => self::generate_results_link($tournament->id)
        ];

        // Invia a entrambi i capitani
        foreach ($captains as $email) {
            self::send(
                $email,
                sprintf(__('[Conferma] Risultato partita - %s', 'eto'), $tournament->name),
                'result-confirmed',
                $data
            );
        }
    }

    /**
     * Carica un template email
     */
    private static function get_template($name, $data) {
        $template_file = ETO_PLUGIN_DIR . self::TEMPLATE_PATH . $name . '.php';
        
        if (!file_exists($template_file)) {
            throw new Exception(sprintf(__('Template email %s non trovato', 'eto'), $name));
        }

        ob_start();
        extract($data);
        include $template_file;
        return ob_get_clean();
    }

    /**
     * Genera link per il check-in
     */
    private static function generate_checkin_link($tournament_id) {
        return add_query_arg(
            [
                'action' => 'checkin',
                'tournament_id' => $tournament_id,
                'nonce' => wp_create_nonce('checkin_nonce')
            ],
            home_url('/checkin')
        );
    }

    /**
     * Registra l'attività email
     */
    private static function log_email($to, $subject, $status, $error = '') {
        $log_data = [
            'type' => 'email',
            'details' => [
                'recipient' => $to,
                'subject' => $subject,
                'status' => $status,
                'error' => $error
            ]
        ];

        ETO_Audit_Log::add($log_data);
    }

    /**
     * Ottieni i capitani dei team della partita
     */
    private static function get_match_captains($match_id) {
        global $wpdb;
        
        return $wpdb->get_col(
            $wpdb->prepare(
                "SELECT u.user_email 
                FROM {$wpdb->prefix}users u
                INNER JOIN {$wpdb->prefix}eto_teams t ON t.captain_id = u.ID
                INNER JOIN {$wpdb->prefix}eto_matches m ON m.team1_id = t.id OR m.team2_id = t.id
                WHERE m.id = %d",
                $match_id
            )
        );
    }
}