<?php
class ETO_Checkin {
    /**
     * Processa il check-in per un team
     *
     * @param int $team_id L'ID del team che effettua il check-in.
     * @return true|WP_Error True in caso di successo, WP_Error in caso di errore.
     */
    public static function process_checkin($team_id) {
        global $wpdb;

        // Verifica che il team esista
        $team = $wpdb->get_row(
            $wpdb->prepare("SELECT * FROM {$wpdb->prefix}eto_teams WHERE id = %d", $team_id)
        );
        if (!$team) {
            return new WP_Error('team_not_found', 'Team non trovato.');
        }

        // Controlla se il team è bloccato (es. in corso di torneo attivo)
        if (eto_team_locked($team_id)) {
            return new WP_Error('tournament_locked', 'Non è possibile effettuare il check-in: il torneo è già iniziato.');
        }

        // Aggiorna lo status del team a "checked_in"
        $updated = $wpdb->update(
            "{$wpdb->prefix}eto_teams",
            ['status' => 'checked_in'],
            ['id' => $team_id],
            ['%s'],
            ['%d']
        );

        if ($updated === false) {
            return new WP_Error('db_update_failed', 'Aggiornamento del check-in fallito.');
        }

        return true;
    }

    /**
     * Shortcode per visualizzare il form di check-in.
     * Utilizzo: [eto_checkin tournament_id="123"]
     *
     * @param array $atts Attributi passati allo shortcode.
     * @return string HTML del form di check-in o messaggio di errore.
     */
    public static function checkin_form_shortcode($atts) {
        if (!is_user_logged_in()) {
            return '<div class="eto-notice">Accedi per effettuare il check-in.</div>';
        }

        $atts = shortcode_atts(['tournament_id' => 0], $atts);
        $user_id = get_current_user_id();

        // Ottieni il team dell'utente nel torneo; si assume che ETO_Team implementi questo metodo.
        if (!method_exists('ETO_Team', 'get_user_team_in_tournament')) {
            return '<div class="eto-error">Metodo per ottenere il team non disponibile.</div>';
        }
        $team_id = ETO_Team::get_user_team_in_tournament($user_id, absint($atts['tournament_id']));
        if (!$team_id) {
            return '<div class="eto-error">Il tuo team non è registrato a questo torneo.</div>';
        }

        ob_start();
        ?>
        <form method="post" class="eto-checkin-form">
            <?php wp_nonce_field('eto_checkin_action', 'eto_checkin_nonce'); ?>
            <input type="hidden" name="team_id" value="<?php echo esc_attr($team_id); ?>">
            <button type="submit" name="eto_checkin_submit">Effettua Check-in</button>
        </form>
        <?php
        return ob_get_clean();
    }

    /**
     * Handler per processare i dati del form di check-in.
     * (Viene eseguito se viene inviato il form di check-in.)
     */
    public static function handle_checkin_submission() {
        if (!isset($_POST['eto_checkin_submit'])) {
            return;
        }

        if (!isset($_POST['eto_checkin_nonce']) || !wp_verify_nonce($_POST['eto_checkin_nonce'], 'eto_checkin_action')) {
            wp_die('Nonce non valido');
        }

        $team_id = absint($_POST['team_id']);
        $result = self::process_checkin($team_id);

        if (is_wp_error($result)) {
            wp_die($result->get_error_message());
        } else {
            // Redirect alla pagina precedente aggiungendo un parametro di successo
            $redirect_url = add_query_arg('checkin', 'success', wp_get_referer());
            wp_redirect($redirect_url);
            exit;
        }
    }
}
// Esegui l'handle del form al momento dell'inizializzazione
add_action('init', ['ETO_Checkin', 'handle_checkin_submission']);
