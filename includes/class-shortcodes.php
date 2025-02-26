<?php
/**
 * Shortcode Handler
 */

if (!defined('ABSPATH')) exit;

// Definizione costanti protette
if (!defined('ETO_TEMPLATE_DIR')) {
    define('ETO_TEMPLATE_DIR', ETO_PLUGIN_DIR . 'templates/');
}

class ETO_Shortcodes {
    public static function init() {
        add_shortcode('eto_tournament', [self::class, 'tournament']);
        add_shortcode('eto_leaderboard', [self::class, 'leaderboard']);
    }

    public static function tournament($atts) {
        $atts = shortcode_atts(
            [
                'tournament_id' => 0,
                'view' => 'basic'
            ],
            $atts,
            'tournament'
        );

        ob_start();
        include ETO_TEMPLATE_DIR . 'shortcodes/tournament.php';
        return ob_get_clean();
    }

    public static function leaderboard($atts) {
        $atts = shortcode_atts(
            [
                'tournament_id' => 0,
                'limit' => 10
            ],
            $atts,
            'leaderboard'
        );

        $data = ETO_Leaderboard::get_data($atts['tournament_id'], $atts['limit']);

        ob_start();
        include ETO_TEMPLATE_DIR . 'shortcodes/leaderboard.php';
        return ob_get_clean();
    }
}
