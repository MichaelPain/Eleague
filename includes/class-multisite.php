<?php
if (!defined('ABSPATH')) exit;

class ETO_Multisite {
    const NETWORK_ADMIN_CAP = 'manage_network_plugins';

    /**
     * Attiva il plugin su un nuovo sito della rete
     * @param int $blog_id ID del nuovo blog
     */
    public static function activate_new_site($blog_id) {
        switch_to_blog($blog_id);
        
        // Carica la classe database se non è già disponibile
        if (!class_exists('ETO_Database')) {
            require_once ETO_PLUGIN_DIR . 'includes/class-database.php';
        }
        
        // Crea le tabelle necessarie
        ETO_Database::create_tables();
        
        // Ripristina il blog originale
        restore_current_blog();
    }

    /**
     * Aggiunge collegamenti rapidi nella schermata di rete
     * @param array $links Collegamenti esistenti
     * @return array Collegamenti modificati
     */
    public static function network_plugin_links($links) {
        if (!current_user_can(self::NETWORK_ADMIN_CAP)) {
            return $links;
        }

        $new_links = [
            '<a href="' . esc_url(network_admin_url('settings.php?page=eto-network-settings')) . '">' . 
                esc_html__('Impostazioni Rete', 'eto') . '</a>',
            
            '<a href="' . esc_url(network_admin_url('sites.php')) . '">' . 
                esc_html__('Gestione Siti', 'eto') . '</a>'
        ];

        return array_merge($links, $new_links);
    }

    /**
     * Inizializza le funzionalità multisito
     */
    public static function init() {
        // Registra l'hook per i nuovi siti
        add_action('wpmu_new_blog', [__CLASS__, 'activate_new_site']);
        
        // Aggiunge i link alla schermata di rete
        add_filter('network_admin_plugin_action_links_' . plugin_basename(ETO_PLUGIN_DIR . 'esports-tournament-organizer.php'), [__CLASS__, 'network_plugin_links']);
        
        // Carica le traduzioni per la rete
        add_action('admin_init', function() {
            load_plugin_textdomain('eto', false, 'esports-tournament-organizer/languages/');
        });
    }

    /**
     * Verifica se il plugin è attivo sull'intera rete
     */
    public static function is_network_active() {
        if (!is_multisite()) {
            return false;
        }

        $plugins = get_site_option('active_sitewide_plugins');
        return isset($plugins[plugin_basename(ETO_PLUGIN_DIR . 'esports-tournament-organizer.php')]);
    }

    /**
     * Sincronizza i dati principali su tutta la rete
     */
    public static function network_sync() {
        if (!is_main_site()) {
            return;
        }

        $sites = get_sites([
            'number' => 0,
            'fields' => 'ids'
        ]);

        foreach ($sites as $site_id) {
            switch_to_blog($site_id);
            ETO_Database::sync_core_data();
            restore_current_blog();
        }
    }
}

// Avvia il componente multisito
if (is_multisite()) {
    ETO_Multisite::init();
}
