<?php
if (!class_exists('ETO_Deactivator')) {

    class ETO_Deactivator {
        public static function handle_deactivation() {
            // Verifica se la classe Cron esiste
            if (class_exists('ETO_Cron')) {
                ETO_Cron::clear_scheduled_events();
            }
            
            // Altri passaggi di deattivazione
            flush_rewrite_rules();
        }
    }

}