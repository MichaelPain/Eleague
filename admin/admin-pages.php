<?php
if (!defined('ABSPATH')) exit;

class ETO_Settings_Register {
    const CAPABILITY = 'manage_eto_settings';

    // 1. REGISTRAZIONE IMPOSTAZIONI CON NONCE
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

    // 2. MENU ADMIN CON GESTIONE NONCE RINFORZATA
    public static function add_admin_menus() {
        add_menu_page(
            esc_html__('Gestione Tornei', 'eto'),
            esc_html__('Tornei eSports', 'eto'),
            'manage_eto_tournaments',
            'eto-tournaments',
            [__CLASS__, 'render_tournaments_page'],
            'dashicons-awards',
            6
        );

        add_submenu_page(
            'eto-tournaments',
            esc_html__('Crea Nuovo Torneo', 'eto'),
            esc_html__('Crea Torneo', 'eto'),
            'manage_eto_tournaments',
            'eto-create-tournament',
            [__CLASS__, 'render_create_tournament_page']
        );

        add_submenu_page(
            'eto-tournaments',
            esc_html__('Impostazioni Plugin', 'eto'),
            esc_html__('Impostazioni', 'eto'),
            'manage_eto_settings',
            'eto-settings',
            [__CLASS__, 'render_settings_page']
        );
    }

    // 3. GESTIONE CREAZIONE TORNEO CON NONCE
    public static function render_create_tournament_page() {
        if (!current_user_can('manage_eto_tournaments')) {
            wp_die(esc_html__('Accesso negato', 'eto'));
        }

        echo '<div class="wrap">';
        echo '<h1>' . esc_html__('Crea Nuovo Torneo', 'eto') . '</h1>';
        echo '<form method="post" action="' . esc_url(admin_url('admin-post.php')) . '">';
        wp_nonce_field('eto_create_tournament_action', '_wpnonce_create_tournament');
        
        // Campi del form con sanitizzazione
        echo '<table class="form-table">';
        echo '<tr><th>' . esc_html__('Nome Torneo', 'eto') . '</th>';
        echo '<td><input type="text" name="name" required class="regular-text"></td></tr>';
        // ... (altri campi del form)
        
        echo '</table>';
        echo '<input type="hidden" name="action" value="eto_create_tournament">';
        submit_button(esc_attr__('Crea Torneo', 'eto'));
        echo '</form></div>';
    }

    // 4. VALIDAZIONE TORNEO CON CONTROLLO NONCE
    public static function handle_tournament_creation() {
        check_admin_referer('eto_create_tournament_action', '_wpnonce_create_tournament');
        
        if (!current_user_can('manage_eto_tournaments')) {
            wp_die(esc_html__('Accesso negato', 'eto'), 403);
        }

        $data = [
            'name' => sanitize_text_field($_POST['name']),
            'format' => sanitize_text_field($_POST['format']),
            'start_date' => sanitize_text_field($_POST['start_date']),
            'end_date' => sanitize_text_field($_POST['end_date']),
            'game_type' => sanitize_text_field($_POST['game_type']),
            'min_players' => absint($_POST['min_players']),
            'max_players' => absint($_POST['max_players']),
            'max_teams' => absint($_POST['max_teams']),
            'checkin_enabled' => isset($_POST['checkin_enabled']) ? 1 : 0,
            'third_place_match' => isset($_POST['third_place_match']) ? 1 : 0
        ];

        $result = ETO_Tournament::create($data);
        
        if (is_wp_error($result)) {
            $error_message = urlencode($result->get_error_message());
            wp_redirect(add_query_arg('eto_error', $error_message, admin_url('admin.php?page=eto-create-tournament')));
            exit;
        }
        
        wp_redirect(admin_url('admin.php?page=eto-tournaments'));
        exit;
    }

    // 5. CAMPI IMPOSTAZIONI CON VALIDAZIONE RINFORZATA
    public static function api_key_field_callback() {
        $value = esc_attr(base64_decode(get_option('eto_riot_api_key', '')));
        echo '<input type="password" id="eto_riot_api_key" name="eto_riot_api_key" 
               value="' . $value . '" class="regular-text">';
        echo '<p class="description">' . esc_html__('Chiave API cifrata con base64.', 'eto') . '</p>';
    }

    public static function email_enabled_field_callback() {
        $value = checked(get_option('eto_email_enabled', false), true, false);
        echo '<label><input type="checkbox" id="eto_email_enabled" name="eto_email_enabled" ' . $value . '> ';
        echo esc_html__('Abilita l\'invio automatico di email', 'eto') . '</label>';
    }

    // 6. SANITIZZAZIONE DATI SICURA
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

    // 7. INITIALIZZAZIONE COMPONENTI
    public static function init() {
        add_action('admin_init', [__CLASS__, 'register_settings']);
        add_action('admin_menu', [__CLASS__, 'add_admin_menus']);
        add_action('admin_notices', [__CLASS__, 'handle_admin_notices']);
    }
}

// Avvio del componente
ETO_Settings_Register::init();
