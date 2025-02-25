<?php

if (!class_exists('ETO_Settings_Register')) {
class ETO_Settings_Register {
    const CAPABILITY = 'manage_eto_settings';

    public static function init() {
        add_action('admin_init', [__CLASS__, 'register_settings']);
    }

    public static function register_settings() {
        // Registrazione impostazioni con validazione rinforzata
        register_setting('eto_settings_group', 'eto_riot_api_key', [
            'type' => 'string',
            'sanitize_callback' => [__CLASS__, 'sanitize_api_key'],
            'default' => '',
            'show_in_rest' => false
        ]);

        register_setting('eto_settings_group', 'eto_email_enabled', [
            'type' => 'boolean',
            'sanitize_callback' => [__CLASS__, 'sanitize_boolean'],
            'default' => false,
            'show_in_rest' => false
        ]);

        // Aggiunta sezione con controllo capacità
        if (!current_user_can(self::CAPABILITY)) return;

        add_settings_section(
            'eto_main_section', 
            esc_html__('Impostazioni Principali', 'eto'), 
            [__CLASS__, 'settings_section_callback'], 
            'eto_settings_page'
        );

        // Campo API Key con protezione aggiuntiva
        add_settings_field(
            'eto_riot_api_key_field',
            esc_html__('API Key Riot', 'eto'),
            [__CLASS__, 'api_key_field_callback'],
            'eto_settings_page',
            'eto_main_section',
            ['label_for' => 'eto_riot_api_key']
        );

        // Campo Notifiche Email con validazione
        add_settings_field(
            'eto_email_enabled_field',
            esc_html__('Abilita Notifiche Email', 'eto'),
            [__CLASS__, 'email_enabled_field_callback'],
            'eto_settings_page',
            'eto_main_section',
            ['label_for' => 'eto_email_enabled']
        );
    }

    public static function settings_section_callback() {
        echo '<p>' . esc_html__('Inserisci le impostazioni del plugin eSports Tournament Organizer.', 'eto') . '</p>';
    }

    public static function api_key_field_callback() {
        $value = get_option('eto_riot_api_key', '');
        echo '<input type="password" 
                    class="regular-text" 
                    name="eto_riot_api_key" 
                    id="eto_riot_api_key" 
                    value="' . esc_attr(base64_decode($value)) . '"
                    autocomplete="new-password">';
        echo '<p class="description">' . esc_html__('Chiave API cifrata con base64.', 'eto') . '</p>';
    }

    public static function email_enabled_field_callback() {
        $value = (bool) get_option('eto_email_enabled', false);
        echo '<input type="checkbox" 
                    name="eto_email_enabled" 
                    id="eto_email_enabled" 
                    value="1" ' . checked(1, $value, false) . '>';
        echo '<label for="eto_email_enabled">' . esc_html__('Abilita l\'invio automatico di notifiche via email', 'eto') . '</label>';
    }

    // Validatori avanzati
    public static function sanitize_api_key($input) {
        if (!current_user_can(self::CAPABILITY)) {
            add_settings_error(
                'eto_riot_api_key',
                'forbidden',
                esc_html__('Permessi insufficienti per modificare questa impostazione.', 'eto')
            );
            return get_option('eto_riot_api_key');
        }

        return base64_encode(sanitize_text_field($input));
    }

    public static function sanitize_boolean($input) {
        return (bool) absint($input);
    }
}
}

ETO_Settings_Register::init();
