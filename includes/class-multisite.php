<?php
/**
 * Classe per il supporto Multisito
 */
class ETO_Multisite {
    /**
     * Inizializza il supporto per ambienti multisito.
     */
    public static function init() {
        if (!is_multisite()) {
            return;
        }
        // Quando viene creato un nuovo sito all'interno della rete,
        // esegue l'installazione delle tabelle necessarie per il plugin.
        add_action('wp_initialize_site', [__CLASS__, 'on_new_site']);
    }

    /**
     * Configura il nuovo sito all'interno del network multisito.
     *
     * @param object $site L'oggetto sito che contiene i dettagli del nuovo sito.
     */
    public static function on_new_site($site) {
        // Passa al nuovo sito e installa le tabelle del plugin
        switch_to_blog($site->blog_id);
        if (method_exists('ETO_Database', 'install')) {
            ETO_Database::install();
        }
        restore_current_blog();
    }
}
?>
