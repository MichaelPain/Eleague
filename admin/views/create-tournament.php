<?php
if (!defined('ABSPATH')) exit;

// 1. VERIFICA PERMESSI UTENTE CON CAPABILITY
if (!current_user_can('manage_eto_tournaments')) {
    wp_die(esc_html__('Accesso negato', 'eto'), 403);
}

global $wpdb;

// 2. LOGICA DI EDITING TORNEO ESISTENTE
$tournament = null;
if (isset($_GET['tournament_id'])) {
    $tournament_id = absint($_GET['tournament_id']);
    $tournament = ETO_Tournament::get($tournament_id);
}

// 3. GESTIONE FORM CON DATI DINAMICI
$allowed_formats = [
    'single_elimination' => __('Eliminazione Diretta', 'eto'),
    'double_elimination' => __('Doppia Eliminazione', 'eto'),
    'swiss' => __('Sistema Svizzero', 'eto')
];

$allowed_games = [
    'lol' => 'League of Legends',
    'dota' => 'Dota 2',
    'cs' => 'Counter-Strike'
];

// 4. GESTIONE SUBMIT CON VALIDAZIONE AVANZATA
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit'])) {
    check_admin_referer('eto_create_tournament_action', '_wpnonce_create_tournament');

    // Raccolta e sanitizzazione dati
    $data = [
        'name' => sanitize_text_field($_POST['name']),
        'format' => array_key_exists($_POST['format'], $allowed_formats) ? $_POST['format'] : 'single_elimination',
        'game_type' => array_key_exists($_POST['game_type'], $allowed_games) ? $_POST['game_type'] : 'lol',
        'start_date' => sanitize_text_field($_POST['start_date']),
        'end_date' => sanitize_text_field($_POST['end_date']),
        'min_players' => min(max(absint($_POST['min_players']), ETO_Tournament::MIN_PLAYERS), ETO_Tournament::MAX_PLAYERS),
        'max_players' => min(max(absint($_POST['max_players']), ETO_Tournament::MIN_PLAYERS), ETO_Tournament::MAX_PLAYERS),
        'max_teams' => min(absint($_POST['max_teams']), ETO_Tournament::MAX_TEAMS),
        'checkin_enabled' => isset($_POST['checkin_enabled']) ? 1 : 0,
        'third_place_match' => isset($_POST['third_place_match']) ? 1 : 0
    ];

    // 5. LOGICA DI UPDATE/CREAZIONE
    try {
        if ($tournament) {
            $result = ETO_Tournament::update($tournament->id, $data);
        } else {
            $result = ETO_Tournament::create($data);
        }

        if (is_wp_error($result)) {
            throw new Exception($result->get_error_message());
        }

        eto_redirect_with_message(
            admin_url('admin.php?page=eto-tournaments'),
            $tournament ? __('Torneo aggiornato!', 'eto') : __('Torneo creato!', 'eto'),
            'success'
        );

    } catch (Exception $e) {
        eto_redirect_with_message(
            admin_url('admin.php?page=eto-create-tournament' . ($tournament ? '&tournament_id=' . $tournament->id : '')),
            $e->getMessage(),
            'error'
        );
    }
}

// 6. CARICAMENTO TEMPLATE DINAMICO
$template_path = ETO_PLUGIN_DIR . 'admin/views/create-tournament-form.php';
if (file_exists($template_path)) {
    include $template_path;
} else {
    wp_die(esc_html__('Errore nel sistema dei template', 'eto'));
}
