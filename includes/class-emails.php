<?php
class ETO_Emails {
    // Invia email di reminder per check-in
    public static function send_checkin_reminder($team_id, $user_id) {
        $user = get_userdata($user_id);
        $team = ETO_Team::get($team_id);
        $tournament = ETO_Tournament::get($team->tournament_id);

        $subject = sprintf(
            '[%s] Check-in richiesto per il torneo "%s"',
            get_bloginfo('name'),
            $tournament->name
        );

        $message = self::get_template('checkin-reminder', [
            'user_name' => $user->display_name,
            'tournament_name' => $tournament->name,
            'checkin_start' => eto_format_date($tournament->start_date, 'H:i'),
            'team_name' => $team->name,
            'tournament_link' => get_permalink($tournament->post_id)
        ]);

        wp_mail($user->user_email, $subject, $message);
    }

    // Invia notifica risultato confermato
    public static function send_result_confirmed($match_id, $winner_id) {
        $match = ETO_Match::get($match_id);
        $teams = [
            'team1' => ETO_Team::get($match->team1_id),
            'team2' => ETO_Team::get($match->team2_id)
        ];

        foreach ($teams as $team) {
            $captain = get_userdata($team->captain_id);
            $subject = sprintf(
                '[%s] Risultato confermato - %s vs %s',
                get_bloginfo('name'),
                $teams['team1']->name,
                $teams['team2']->name
            );

            $message = self::get_template('result-confirmed', [
                'team_name' => $team->name,
                'opponent_name' => $team->id === $winner_id ? $teams['team2']->name : $teams['team1']->name,
                'result' => $team->id === $winner_id ? 'vittoria' : 'sconfitta',
                'tournament_name' => ETO_Tournament::get($match->tournament_id)->name
            ]);

            wp_mail($captain->user_email, $subject, $message);
        }
    }

    // Carica template email da file
    private static function get_template($name, $data) {
        ob_start();
        include ETO_PLUGIN_DIR . "templates/emails/{$name}.php";
        $template = ob_get_clean();
        
        foreach ($data as $key => $value) {
            $template = str_replace("{{{$key}}}", $value, $template);
        }

        return $template;
    }
}
