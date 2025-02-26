<?php
if (!defined('ABSPATH')) exit;

// ==================================================
// 1. GESTIONE ERRORI E NOTIFICHE (COMPLETA)
// ==================================================
add_action('admin_notices', function() {
    // Gestione errori da URL
    if (isset($_GET['eto_error']) && current_user_can('manage_options')) {
        $error_message = urldecode(sanitize_text_field($_GET['eto_error']));
        echo '<div class="notice notice-error">';
        echo '<p>' . esc_html($error_message) . '</p>';
        echo '<p>' . esc_html__('Controlla il log errori per maggiori dettagli.', 'eto') . '</p>';
        echo '</div>';
    }

    // Gestione notifiche transitorie
    $transient_notice = get_transient('eto_admin_notice');
    if (!empty($transient_notice)) {
        echo '<div class="notice notice-' . esc_attr($transient_notice['type']) . '">';
        echo '<p>' . esc_html($transient_notice['message']) . '</p>';
        echo '</div>';
        delete_transient('eto_admin_notice');
    }

    // Avviso configurazione incompleta
    if (!get_option('eto_initial_config')) {
        echo '<div class="notice notice-warning">';
        echo '<p>' . esc_html__('Configurazione iniziale richiesta. Visita la pagina delle impostazioni.', 'eto') . '</p>';
        echo '</div>';
    }
});

// ==================================================
// 2. REGISTRAZIONE MENU (TUTTI I SOTTOMENU ORIGINALI)
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

    // Sottomenu: Crea torneo
    add_submenu_page(
        'eto-tournaments',
        esc_html__('Crea Nuovo Torneo', 'eto'),
        esc_html__('Crea Torneo', 'eto'),
        'manage_eto_tournaments',
        'eto-create-tournament',
        'eto_render_create_tournament_page'
    );

    // Sottomenu: Gestione Team
    add_submenu_page(
        'eto-tournaments',
        esc_html__('Gestione Team', 'eto'),
        esc_html__('Team', 'eto'),
        'manage_eto_teams',
        'eto-teams',
        'eto_render_teams_page'
    );

    // Sottomenu: Impostazioni
    add_submenu_page(
        'eto-tournaments',
        esc_html__('Impostazioni Plugin', 'eto'),
        esc_html__('Impostazioni', 'eto'),
        'manage_eto_settings',
        'eto-settings',
        'eto_render_settings_page'
    );

    // Sottomenu: Logs (Nascosto)
    add_submenu_page(
        null,
        esc_html__('Logs Audit', 'eto'),
        '',
        'manage_eto_logs',
        'eto-audit-logs',
        'eto_render_audit_logs_page'
    );
});

// ==================================================
// 3. RENDER PAGINE (TUTTE LE FUNZIONALITÀ ORIGINALI)
// ==================================================
function eto_render_tournaments_page() {
    if (!current_user_can('manage_eto_tournaments')) {
        wp_die(esc_html__('Accesso negato', 'eto'), 403);
    }

    global $wpdb;
    $tournaments = $wpdb->get_results(
        $wpdb->prepare(
            "SELECT *, 
            (SELECT COUNT(*) FROM {$wpdb->prefix}eto_teams WHERE tournament_id = t.id) as team_count
            FROM {$wpdb->prefix}eto_tournaments t
            WHERE status != %s
            ORDER BY start_date DESC",
            'deleted'
        )
    );

    include ETO_PLUGIN_DIR . 'admin/views/tournaments-list.php';
}

function eto_render_create_tournament_page() {
    if (!current_user_can('manage_eto_tournaments')) {
        wp_die(esc_html__('Accesso negato', 'eto'), 403);
    }

    $tournament = null;
    if (isset($_GET['tournament_id'])) {
        $tournament = ETO_Tournament::get(absint($_GET['tournament_id']));
    }

    $game_types = ETO_Tournament::get_supported_games();
    $formats = ETO_Tournament::get_tournament_formats();

    include ETO_PLUGIN_DIR . 'admin/views/create-tournament-form.php';
}

function eto_render_teams_page() {
    if (!current_user_can('manage_eto_teams')) {
        wp_die(esc_html__('Accesso negato', 'eto'), 403);
    }

    global $wpdb;
    $teams = $wpdb->get_results(
        $wpdb->prepare(
            "SELECT t.*, COUNT(u.id) as member_count 
            FROM {$wpdb->prefix}eto_teams t
            LEFT JOIN {$wpdb->prefix}eto_team_users u ON t.id = u.team_id
            GROUP BY t.id
            HAVING t.status != %s
            ORDER BY t.created_at DESC",
            'deleted'
        )
    );

    include ETO_PLUGIN_DIR . 'admin/views/teams-list.php';
}

// ==================================================
// 4. GESTIONE AZIONI (COMPLETA CON TUTTI GLI HOOK)
// ==================================================
add_action('admin_post_eto_create_tournament', function() {
    check_admin_referer('eto_tournament_creation', '_wpnonce');
    
    if (!current_user_can('manage_eto_tournaments')) {
        wp_die(esc_html__('Accesso negato', 'eto'), 403);
    }

    $data = [
        'name' => sanitize_text_field($_POST['name']),
        'format' => sanitize_key($_POST['format']),
        'game_type' => sanitize_key($_POST['game_type']),
        'start_date' => sanitize_text_field($_POST['start_date']),
        'end_date' => sanitize_text_field($_POST['end_date']),
        'min_players' => absint($_POST['min_players']),
        'max_players' => absint($_POST['max_players']),
        'max_teams' => absint($_POST['max_teams']),
        'checkin_enabled' => isset($_POST['checkin_enabled']) ? 1 : 0,
        'third_place_match' => isset($_POST['third_place_match']) ? 1 : 0
    ];

    try {
        $result = ETO_Tournament::create($data);
        eto_redirect_with_message(
            admin_url('admin.php?page=eto-tournaments'),
            esc_html__('Torneo creato con successo! ID: ', 'eto') . $result,
            'success'
        );
    } catch (Exception $e) {
        eto_redirect_with_message(
            admin_url('admin.php?page=eto-create-tournament'),
            esc_html__('Errore: ', 'eto') . $e->getMessage(),
            'error'
        );
    }
});

add_action('admin_post_eto_delete_tournament', function() {
    check_admin_referer('eto_tournament_deletion', '_wpnonce');
    
    if (!current_user_can('manage_eto_tournaments')) {
        wp_die(esc_html__('Accesso negato', 'eto'), 403);
    }

    $tournament_id = absint($_POST['tournament_id']);
    $result = ETO_Tournament::delete($tournament_id);

    eto_redirect_with_message(
        admin_url('admin.php?page=eto-tournaments'),
        $result ? esc_html__('Torneo eliminato', 'eto') : esc_html__('Errore eliminazione', 'eto'),
        $result ? 'success' : 'error'
    );
});

// ==================================================
// 5. FUNZIONALITÀ AGGIUNTIVE (PRESERVATE)
// ==================================================
add_filter('plugin_action_links_' . plugin_basename(ETO_PLUGIN_DIR . 'esports-tournament-organizer.php'), function($links) {
    $settings_link = '<a href="' . esc_url(admin_url('admin.php?page=eto-settings')) . '">' 
                   . esc_html__('Impostazioni', 'eto') . '</a>';
    array_unshift($links, $settings_link);
    return $links;
});

function eto_redirect_with_message($url, $message, $type = 'success') {
    $transient = [
        'message' => sanitize_text_field($message),
        'type' => in_array($type, ['success', 'error', 'warning']) ? $type : 'info'
    ];
    set_transient('eto_admin_notice', $transient, 60);
    wp_redirect(esc_url_raw($url));
    exit;
}