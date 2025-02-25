<?php
class ETO_Shortcodes {
    public static function init() {
        add_shortcode('tournament_view', [__CLASS__, 'tournament_view']);
        add_shortcode('leaderboard', [__CLASS__, 'leaderboard']);
    }

    public static function tournament_view($atts) {
        $atts = shortcode_atts([
            'id' => 0,
            'type' => 'bracket'
        ], $atts, 'tournament_view');

        ob_start();
        include ETO_TEMPLATE_DIR . 'shortcodes/tournament.php';
        return ob_get_clean();
    }

    public static function leaderboard($atts) {
        $atts = shortcode_atts([
            'tournament_id' => 0,
            'limit' => 10
        ], $atts, 'leaderboard');

        $data = ETO_Leaderboard::get_data($atts['tournament_id'], $atts['limit']);
        
        ob_start();
        include ETO_TEMPLATE_DIR . 'shortcodes/leaderboard.php';
        return ob_get_clean();
    }
}