<?php
if (!defined('ABSPATH')) exit;

class ETO_WPCLI {
    public static function handle($args) {
        switch (array_shift($args)) {
            case 'create':
                self::create_tournament($args);
                break;
            case 'update':
                self::update_tournament($args);
                break;
            default:
                WP_CLI::error('Comando non valido');
        }
    }

    private static function create_tournament($args) {
        $tournament = new ETO_Tournament();
        $tournament->set_name($args[0]);
        $tournament->set_type($args[1]);
        $tournament->save();
        WP_CLI::success('Torneo creato con ID: ' . $tournament->get_id());
    }
}