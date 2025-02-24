<?php
class ETO_Deactivator {
    public static function handle_deactivation() {
        ETO_Cron::clear_scheduled_events();
        flush_rewrite_rules();
    }
}