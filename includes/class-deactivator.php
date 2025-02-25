class ETO_Deactivator {
    public static function handle_deactivation() {
        // Rimuovi gli eventi schedulati
        ETO_Cron::clear_scheduled_events();

        // Altri passaggi di deattivazione
        flush_rewrite_rules();
    }
}