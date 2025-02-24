<?php
/**
 * Widget per visualizzare la classifica dei team.
 */

class ETO_Leaderboard_Widget extends WP_Widget {

    public function __construct() {
        parent::__construct(
            'eto_leaderboard_widget',
            __('Classifica Tornei', 'eto'),
            array(
                'description' => __('Mostra la classifica dei team basata su vittorie e punti differenziali.', 'eto'),
                'classname'   => 'eto-leaderboard-widget'
            )
        );
    }

    /**
     * Renderizza il widget nel frontend
     */
    public function widget($args, $instance) {
        global $wpdb;

        // Verifica esistenza tabella
        $table_name = $wpdb->prefix . 'eto_teams';
        if ($wpdb->get_var("SHOW TABLES LIKE '$table_name'") != $table_name) {
            eto_debug_log('Tabella teams non trovata durante il rendering del widget');
            return;
        }

        // Recupera i dati
        $teams = $wpdb->get_results(
            "SELECT id, name, wins, points_diff 
            FROM {$wpdb->prefix}eto_teams 
            ORDER BY wins DESC, points_diff DESC 
            LIMIT 10"
        );

        echo $args['before_widget'];
        
        // Titolo widget
        $title = apply_filters('widget_title', $instance['title']);
        if (!empty($title)) {
            echo $args['before_title'] . esc_html($title) . $args['after_title'];
        }

        // Contenuto
        if (!empty($teams)) {
            echo '<table class="eto-leaderboard-table">';
            echo '<thead><tr>';
            echo '<th>' . __('Posizione', 'eto') . '</th>';
            echo '<th>' . __('Team', 'eto') . '</th>';
            echo '<th>' . __('Vittorie', 'eto') . '</th>';
            echo '<th>' . __('Differenza Punti', 'eto') . '</th>';
            echo '</tr></thead>';
            
            echo '<tbody>';
            $position = 1;
            foreach ($teams as $team) {
                echo '<tr>';
                echo '<td>' . $position++ . '</td>';
                echo '<td>' . esc_html($team->name) . '</td>';
                echo '<td>' . intval($team->wins) . '</td>';
                echo '<td>' . intval($team->points_diff) . '</td>';
                echo '</tr>';
            }
            echo '</tbody></table>';
        } else {
            echo '<p>' . __('Nessun team registrato.', 'eto') . '</p>';
        }

        echo $args['after_widget'];
    }

    /**
     * Form impostazioni nel backend
     */
    public function form($instance) {
        $title = !empty($instance['title']) ? $instance['title'] : __('Classifica', 'eto');
        ?>
        <p>
            <label for="<?php echo $this->get_field_id('title'); ?>">
                <?php _e('Titolo:', 'eto'); ?>
            </label>
            <input class="widefat" 
                   id="<?php echo $this->get_field_id('title'); ?>" 
                   name="<?php echo $this->get_field_name('title'); ?>" 
                   type="text" 
                   value="<?php echo esc_attr($title); ?>">
        </p>
        <p>
            <input type="checkbox" 
                   id="<?php echo $this->get_field_id('show_points'); ?>" 
                   name="<?php echo $this->get_field_name('show_points'); ?>" 
                   <?php checked(isset($instance['show_points']) ? $instance['show_points'] : 0); ?>>
            <label for="<?php echo $this->get_field_id('show_points'); ?>">
                <?php _e('Mostra differenza punti', 'eto'); ?>
            </label>
        </p>
        <?php
    }

    /**
     * Aggiornamento impostazioni
     */
    public function update($new_instance, $old_instance) {
        $instance = array();
        $instance['title'] = (!empty($new_instance['title'])) 
                            ? strip_tags($new_instance['title']) 
                            : '';
        $instance['show_points'] = isset($new_instance['show_points']) ? 1 : 0;
        
        return $instance;
    }
}
?>