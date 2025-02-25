<?php
class ETO_Settings_Register {
    const CAPABILITY = 'manage_eto_settings';

    public static function init() {
        add_action('admin_init', [__CLASS__, 'register_settings']);
    }

    public static function register_settings() {
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

        if (!current_user_can(self::CAPABILITY)) return;

        add_settings_section(
            'eto_main_section',
            esc_html__('Impostazioni Principali', 'eto'),
            [__CLASS__, 'settings_section_callback'],
            'eto_settings_page'
        );

        add_settings_field(
            'eto_riot_api_key_field',
            esc_html__('API Key Riot', 'eto'),
            [__CLASS__, 'api_key_field_callback'],
            'eto_settings_page',
            'eto_main_section',
            ['label_for' => 'eto_riot_api_key']
        );

        add_settings_field(
            'eto_email_enabled_field',
            esc_html__('Abilita Notifiche Email', 'eto'),
            [__CLASS__, 'email_enabled_field_callback'],
            'eto_settings_page',
            'eto_main_section',
            ['label_for' => 'eto_email_enabled']
        );

        add_settings_field(
            'eto_installer_info',
            esc_html__('Utente Installatore', 'eto'),
            [__CLASS__, 'installer_info_callback'],
            'eto_settings_page',
            'eto_main_section'
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

    public static function installer_info_callback() {
        $installer_id = ETO_Installer::get_original_installer();
        $user = $installer_id ? get_userdata($installer_id) : null;
        
        echo $user ? 
            '<strong>' . esc_html($user->display_name) . '</strong>' : 
            '<em>' . esc_html__('N/A', 'eto') . '</em>';
        
        echo '<p class="description">' . 
             esc_html__('Utente che ha installato originariamente il plugin.', 'eto') . 
             '</p>';
    }

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

ETO_Settings_Register::init();
