class ETO_Checkin_System {
    public function __construct() {
        add_shortcode('eto_checkin', [$this, 'checkin_form_shortcode']);
        add_action('wp_ajax_eto_process_checkin', [$this, 'process_checkin']);
    }

    // Shortcode per il form di check-in
    public function checkin_form_shortcode($atts) {
        if (!is_user_logged_in()) return 'Accedi per effettuare il check-in.';

        $tournament_id = intval($atts['tournament_id']);
        $checkin_open = $this->is_checkin_open($tournament_id);

        ob_start();
        ?>
        <div class="eto-checkin-form">
            <?php if ($checkin_open) : ?>
                <button class="checkin-btn" data-tournament="<?php echo $tournament_id; ?>">
                    Effettua Check-In
                </button>
                <div class="checkin-status"></div>
            <?php else : ?>
                <p>Check-in chiuso o non disponibile.</p>
            <?php endif; ?>
        </div>
        <?php
        return ob_get_clean();
    }

    // Verifica se il check-in è attivo
    private function is_checkin_open($tournament_id) {
        $start_time = get_post_meta($tournament_id, 'start_date', true);
        return (time() >= (strtotime($start_time) - 3600)) && (time() <= strtotime($start_time));
    }

    // Gestione AJAX
    public function process_checkin() {
        check_ajax_referer('eto_checkin_nonce', 'security');
        $tournament_id = intval($_POST['tournament_id']);
        $user_id = get_current_user_id();

        // Logica di salvataggio nel database
        global $wpdb;
        $wpdb->insert($wpdb->prefix . 'eto_checkins', [
            'user_id' => $user_id,
            'tournament_id' => $tournament_id,
            'checked_in_at' => current_time('mysql')
        ]);

        wp_send_json_success(['message' => 'Check-in effettuato!']);
    }
}
new ETO_Checkin_System();