public function log_match_error($error) {
    if (WP_DEBUG === true) {
        error_log('[ETO Match Error] ' . $error);
    } else {
        // Nascondi dettagli agli utenti finali
        wp_die(__('Errore di sistema. Contatta l\'amministratore.', 'eto-plugin'));
    }
}