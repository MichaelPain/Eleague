<?php
/**
 * Classe per registrare le impostazioni del plugin nell'area Admin
 *
 * Questo file gestisce la registrazione e la visualizzazione dei campi di impostazione
 * per il plugin eSports Tournament Organizer.
 */

class ETO_Settings_Register {

    /**
     * Inizializza la registrazione delle impostazioni del plugin.
     */
    public static function init() {
        add_action('admin_init', array(__CLASS__, 'register_settings'));
    }

    /**
     * Registra le impostazioni e definisce le sezioni e i campi nel form delle impostazioni.
     */
    public static function register_settings() {
        // Registra le impostazioni
        register_setting('eto_settings_group', 'eto_riot_api_key', array(
            'type'              => 'string',
            'sanitize_callback' => 'sanitize_text_field',
            'default'           => '',
        ));
        register_setting('eto_settings_group', 'eto_email_enabled', array(
            'type'              => 'boolean',
            'sanitize_callback' => 'rest_sanitize_boolean',
            'default'           => false,
        ));

        // Aggiungi una sezione per le impostazioni del plugin
        add_settings_section(
            'eto_main_section',
            __('Impostazioni Principali', 'eto'),
            array(__CLASS__, 'settings_section_callback'),
            'eto_settings_page'
        );

        // Aggiungi il campo per la API Key di Riot
        add_settings_field(
            'eto_riot_api_key_field',
            __('API Key Riot', 'eto'),
            array(__CLASS__, 'api_key_field_callback'),
            'eto_settings_page',
            'eto_main_section'
        );

        // Aggiungi il campo per abilitare/disabilitare le notifiche email
        add_settings_field(
            'eto_email_enabled_field',
            __('Abilita Notifiche Email', 'eto'),
            array(__CLASS__, 'email_enabled_field_callback'),
            'eto_settings_page',
            'eto_main_section'
        );
    }

    /**
     * Callback della sezione principale delle impostazioni.
     */
    public static function settings_section_callback() {
        echo '<p>' . __('Inserisci le impostazioni del plugin eSports Tournament Organizer.', 'eto') . '</p>';
    }

    /**
     * Callback per il campo API Key Riot.
     */
    public static function api_key_field_callback() {
        $value = get_option('eto_riot_api_key', '');
        echo '<input type="text" id="eto_riot_api_key" name="eto_riot_api_key" value="' . esc_attr($value) . '" class="regular-text">';
    }

    /**
     * Callback per il campo "Abilita Notifiche Email".
     */
    public static function email_enabled_field_callback() {
        $value = get_option('eto_email_enabled', false);
        echo '<input type="checkbox" id="eto_email_enabled" name="eto_email_enabled" value="1" ' . checked(1, $value, false) . '>';
    }
}

// Inizializza la registrazione delle impostazioni
ETO_Settings_Register::init();
