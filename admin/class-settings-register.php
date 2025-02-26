<?php
if (!defined('ABSPATH')) exit;

if (!class_exists('ETO_Settings_Register')) {

class ETO_Settings_Register {
    const CAPABILITY = 'manage_eto_settings';

    // 1. REGISTRAZIONE IMPOSTAZIONI
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
    }

    // 2. MENU ADMIN
    public static function add_admin_menus() {
        add_menu_page(
            esc_html__('Esports Tournament Organizer', 'eto'),
            esc_html__('ETO Settings', 'eto'),
            self::CAPABILITY,
            'eto-settings',
            [__CLASS__, 'render_settings_page'],
            'dashicons-admin-generic'
        );
    }

    // 3. RENDER PAGINA IMPOSTAZIONI
    public static function render_settings_page() {
        if (!current_user_can(self::CAPABILITY)) {
            wp_die(esc_html__('Accesso negato', 'eto'));
        }
        ?>
        <div class="wrap">
            <h1><?php esc_html_e('Impostazioni del Plugin', 'eto'); ?></h1>
            <form method="post" action="options.php">
                <?php 
                settings_fields('eto_settings_group');
                do_settings_sections('eto_settings_page');
                submit_button(esc_attr__('Salva Impostazioni', 'eto')); 
                ?>
            </form>
        </div>
        <?php
    }

    // 4. CALLBACKS CAMPI
    public static function settings_section_callback() {
        echo '<p>' . esc_html__('Configura le impostazioni principali del plugin.', 'eto') . '</p>';
    }

    public static function api_key_field_callback() {
        $value = esc_attr(base64_decode(get_option('eto_riot_api_key', '')));
        echo '<input type="password" id="eto_riot_api_key" name="eto_riot_api_key" 
               value="' . $value . '" class="regular-text">';
        echo '<p class="description">' . esc_html__('Chiave API cifrata con base64.', 'eto') . '</p>';
    }

    public static function email_enabled_field_callback() {
        $checked = checked(get_option('eto_email_enabled', false), true, false);
        echo '<label><input type="checkbox" id="eto_email_enabled" name="eto_email_enabled" ' . $checked . '> ';
        echo esc_html__('Abilita l\'invio automatico di email', 'eto') . '</label>';
    }

    // 5. SANITIZZAZIONE (REVISIONATA)
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

    // 6. GESTIONE NOTIFICHE
    public static function handle_notices() {
        if (isset($_GET['settings-updated'])) {
            $message = $_GET['settings-updated'] ? 
                esc_html__('Impostazioni aggiornate con successo!', 'eto') : 
                esc_html__('Errore durante il salvataggio!', 'eto');
            
            $type = $_GET['settings-updated'] ? 'success' : 'error';
            echo '<div class="notice notice-' . esc_attr($type) . '">';
            echo '<p>' . esc_html($message) . '</p>';
            echo '</div>';
        }

        if (!empty($_GET['eto_error'])) {
            echo '<div class="notice notice-error">';
            echo '<p>' . esc_html(urldecode(sanitize_text_field($_GET['eto_error']))) . '</p>';
            echo '</div>';
        }
    }

    // 7. INIZIALIZZAZIONE
    public static function init() {
        add_action('admin_init', [__CLASS__, 'register_settings']);
        add_action('admin_menu', [__CLASS__, 'add_admin_menus']);
        add_action('admin_notices', [__CLASS__, 'handle_notices']);
    }
}

ETO_Settings_Register::init();

} // Fine controllo class_exists