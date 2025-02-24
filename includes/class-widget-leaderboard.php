<?php
/**
 * Widget per visualizzare la classifica dei team.
 */
class ETO_Leaderboard_Widget extends WP_Widget {

    public function __construct() {
        parent::__construct(
            'eto_leaderboard_widget',
            __('Classifica Tornei', 'eto'),
            array('description' => __('Mostra la classifica dei team basata su vittorie e punti differenziali.', 'eto'))
        );
    }

    /**
     * Renderizza il widget nel frontend.
     *
     * @param array $args Parametri e markup predefinito del widget.
     * @param array $instance Le impostazioni del widget.
     */
    public function widget($args, $instance) {
        global $wpdb;
        // Recupera i team ordinati per vittorie e punteggio differenziale
        $teams = $wpdb->get_results(
            "SELECT id, name, wins FROM {$wpdb->prefix}eto_teams ORDER BY wins DESC, points_diff DESC LIMIT 10"
        );
        
        echo $args['before_widget'];
        if (!empty($teams)) {
            echo '<ul class="eto-leaderboard">';
            foreach ($teams as $team) {
                echo '<li>' . esc_html($team->name) . ' - ' . intval($team->wins) . ' vittorie</li>';
            }
            echo '</ul>';
        } else {
            echo '<p>' . __('Nessun team registrato.', 'eto') . '</p>';
        }
        echo $args['after_widget'];
    }

    /**
     * Form del backend per le impostazioni del widget (non necessario in questo caso).
     *
     * @param array $instance Le impostazioni correnti.
     */
    public function form($instance) {
        ?>
        <p><?php _e('Nessuna impostazione necessaria per questo widget.', 'eto'); ?></p>
        <?php
    }

    /**
     * Aggiorna le impostazioni del widget (niente da salvare qui).
     *
     * @param array $new_instance Le nuove impostazioni.
     * @param array $old_instance Le vecchie impostazioni.
     * @return array Le impostazioni aggiornate.
     */
    public function update($new_instance, $old_instance) {
        return $new_instance;
    }
}
?>
