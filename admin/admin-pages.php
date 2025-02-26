<?php
if (!defined('ABSPATH')) exit;

require_once ETO_PLUGIN_DIR . 'admin/class-settings-register.php';

// ==================================================
// 1. GESTIONE ERRORI E NOTIFICHE
// ==================================================
add_action('admin_notices', function() {
    // Notifiche da URL
    if (isset($_GET['eto_error']) && current_user_can('manage_options')) {
        echo '<div class="notice notice-error">';
        echo '<p>' . esc_html(urldecode(sanitize_text_field($_GET['eto_error']))) . '</p>';
        echo '<p>' . esc_html__('Controlla il log errori per maggiori dettagli.', 'eto') . '</p>';
        echo '</div>';
    }

    // Notifiche da transiente
    $transient_notice = get_transient('eto_admin_notice');
    if (!empty($transient_notice)) {
        echo '<div class="notice notice-' . esc_attr($transient_notice['type']) . '">';
        echo '<p>' . esc_html($transient_notice['message']) . '</p>';
        echo '</div>';
        delete_transient('eto_admin_notice');
    }
});

// ==================================================
// 2. HOOK PER AZIONI ADMIN
// ==================================================
// Creazione torneo
add_action('admin_post_eto_create_tournament', function() {
    check_admin_referer('eto_create_tournament_action', '_wpnonce_create_tournament');
    
    if (!current_user_can('manage_eto_tournaments')) {
        wp_die(esc_html__('Accesso negato', 'eto'), 403);
    }

    $data = [
        'name' => sanitize_text_field($_POST['name']),
        'format' => sanitize_text_field($_POST['format']),
        'game_type' => sanitize_text_field($_POST['game_type']),
        'start_date' => sanitize_text_field($_POST['start_date']),
        'end_date' => sanitize_text_field($_POST['end_date']),
        'min_players' => absint($_POST['min_players']),
        'max_players' => absint($_POST['max_players']),
        'max_teams' => absint($_POST['max_teams']),
        'checkin_enabled' => isset($_POST['checkin_enabled']) ? 1 : 0,
        'third_place_match' => isset($_POST['third_place_match']) ? 1 : 0
    ];

    $result = ETO_Tournament::create($data);
    
    if (is_wp_error($result)) {
        eto_redirect_with_message(
            admin_url('admin.php?page=eto-create-tournament'),
            $result->get_error_message(),
            'error'
        );
    }
    
    eto_redirect_with_message(
        admin_url('admin.php?page=eto-tournaments'),
        esc_html__('Torneo creato con successo!', 'eto'),
        'success'
    );
});

// Eliminazione torneo
add_action('admin_post_eto_delete_tournament', function() {
    check_admin_referer('eto_delete_tournament_action', '_wpnonce_delete_tournament');
    
    if (!current_user_can('manage_eto_tournaments')) {
        wp_die(esc_html__('Accesso negato', 'eto'), 403);
    }

    $tournament_id = absint($_POST['tournament_id']);
    $result = ETO_Tournament::delete($tournament_id);
    
    if (is_wp_error($result)) {
        eto_redirect_with_message(
            admin_url('admin.php?page=eto-tournaments'),
            $result->get_error_message(),
            'error'
        );
    }
    
    eto_redirect_with_message(
        admin_url('admin.php?page=eto-tournaments'),
        esc_html__('Torneo eliminato con successo!', 'eto'),
        'success'
    );
});

// ==================================================
// 3. REGISTRAZIONE MENU E PAGINE ADMIN
// ==================================================
add_action('admin_menu', function() {
    // Menu principale
    add_menu_page(
        esc_html__('Gestione Tornei', 'eto'),
        esc_html__('Tornei eSports', 'eto'),
        'manage_eto_tournaments',
        'eto-tournaments',
        'eto_render_tournaments_page',
        'dashicons-awards',
        6
    );

    // Sottomenù: Crea nuovo torneo
    add_submenu_page(
        'eto-tournaments',
        esc_html__('Crea Nuovo Torneo', 'eto'),
        esc_html__('Crea Torneo', 'eto'),
        'manage_eto_tournaments',
        'eto-create-tournament',
        'eto_render_create_tournament_page'
    );

    // Sottomenù: Impostazioni
    add_submenu_page(
        'eto-tournaments',
        esc_html__('Impostazioni Plugin', 'eto'),
        esc_html__('Impostazioni', 'eto'),
        'manage_eto_settings',
        'eto-settings',
        'eto_render_settings_page'
    );
});

// ==================================================
// 4. RENDER DELLE PAGINE ADMIN
// ==================================================
function eto_render_tournaments_page() {
    if (!current_user_can('manage_eto_tournaments')) {
        wp_die(esc_html__('Accesso negato', 'eto'));
    }
    
    global $wpdb;
    $tournaments = $wpdb->get_results(
        $wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}eto_tournaments 
            WHERE status != %s 
            ORDER BY start_date DESC",
            'deleted'
        )
    );
    
    include ETO_PLUGIN_DIR . 'admin/views/tournaments-list.php';
}

function eto_render_create_tournament_page() {
    if (!current_user_can('manage_eto_tournaments')) {
        wp_die(esc_html__('Accesso negato', 'eto'));
    }
    
    $tournament = isset($_GET['tournament_id']) ? 
        ETO_Tournament::get(absint($_GET['tournament_id'])) : 
        null;
    
    include ETO_PLUGIN_DIR . 'admin/views/create-tournament-form.php';
}

function eto_render_settings_page() {
    if (!current_user_can('manage_eto_settings')) {
        wp_die(esc_html__('Accesso negato', 'eto'));
    }
    
    include ETO_PLUGIN_DIR . 'admin/views/settings-page.php';
}

// ==================================================
// 5. SHORTCODE E FUNZIONALITÀ FRONTEND
// ==================================================
add_shortcode('eto_leaderboard', function($atts) {
    if (!is_user_logged_in()) return esc_html__('Accedi per visualizzare la classifica', 'eto');
    
    $atts = shortcode_atts([
        'tournament_id' => 0,
        'limit' => 10
    ], $atts);
    
    $leaderboard = ETO_Leaderboard::get(absint($atts['tournament_id']), absint($atts['limit']));
    
    ob_start();
    include ETO_PLUGIN_DIR . 'public/views/leaderboard.php';
    return ob_get_clean();
});