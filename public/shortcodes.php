function eto_riot_id_form() {
    ob_start();
    if (is_user_logged_in()) {
        $user_id = get_current_user_id();
        $current_riot_id = get_user_meta($user_id, 'riot_id', true);
        ?>
        <form method="post">
            <input type="text" name="riot_id" value="<?php echo esc_attr($current_riot_id); ?>" required>
            <?php wp_nonce_field('save_riot_id', 'eto_nonce'); ?>
            <button type="submit">Salva Riot#ID</button>
        </form>
        <?php
        if (isset($_POST['riot_id']) && wp_verify_nonce($_POST['eto_nonce'], 'save_riot_id')) {
            update_user_meta($user_id, 'riot_id', sanitize_text_field($_POST['riot_id']));
            echo '<p>Riot#ID aggiornato!</p>';
        }
    }
    return ob_get_clean();
}
add_shortcode('eto_riot_id', 'eto_riot_id_form');

function eto_create_team_form() {
    ob_start();
    if (is_user_logged_in()) {
        // Verifica se l'utente è già capitano
        $user_teams = ETO_Team::get_user_teams(get_current_user_id());
        if (empty($user_teams)) {
            ?>
            <form method="post">
                <input type="text" name="team_name" required>
                <?php wp_nonce_field('create_team', 'eto_nonce'); ?>
                <button type="submit">Crea Team</button>
            </form>
            <?php
            if (isset($_POST['team_name']) && wp_verify_nonce($_POST['eto_nonce'], 'create_team')) {
                $team_id = ETO_Team::create_team($_POST['team_name'], get_current_user_id());
                echo '<p>Team creato! ID: ' . $team_id . '</p>';
            }
        }
    }
    return ob_get_clean();
}
add_shortcode('eto_create_team', 'eto_create_team_form');

if (isset($_POST['create_tournament'])) {
    // Sanificazione nome torneo
    $tournament_name = sanitize_text_field($_POST['tournament_name']);
    
    // Validazione numerica giocatori
    $min_players = filter_var(
        $_POST['min_team_members'], 
        FILTER_VALIDATE_INT, 
        ['options' => ['min_range' => 1, 'max_range' => 10]]
    );
    
    // Validazione formato data
    if (!DateTime::createFromFormat('Y-m-d\TH:i', $_POST['start_date'])) {
        wp_die(__('Formato data non valido', 'eto-plugin'));
    }
}
function eto_team_locked($team_id) {
    global $wpdb;
    return $wpdb->get_var("SELECT status FROM {$wpdb->prefix}eto_tournaments WHERE id = (SELECT tournament_id FROM {$wpdb->prefix}eto_teams WHERE id = $team_id)") === 'active';
}


add_shortcode('eto_leave_team', function() {
    if (eto_team_locked()) return "Azione bloccata durante torneo!";
    // Logica uscita
});

add_shortcode('eto_upload_screenshot', function($atts) {
    if (!eto_is_captain()) return "Solo il capitano può caricare screenshot!";
    // Form upload
});

// Shortcode [eto_user_profile]
$user_id = get_current_user_id();
$tournaments = $wpdb->get_results(
    "SELECT * FROM {$wpdb->prefix}eto_tournaments 
    WHERE id IN (
        SELECT tournament_id FROM {$wpdb->prefix}eto_teams 
        WHERE id IN (
            SELECT team_id FROM {$wpdb->prefix}eto_team_members 
            WHERE user_id = $user_id
        )
    )"
);

// Visualizza nello shortcode
echo '<div class="eto-tournament-history">';
foreach ($tournaments as $tournament) {
    echo '<div class="tournament-item">' . esc_html($tournament->name) . '</div>';
}
echo '</div>';

// Shortcode [eto_user_profile]
function eto_user_profile_shortcode() {
    global $wpdb;
    $user_id = get_current_user_id();
    
    // Query con prepared statement
    $tournaments = $wpdb->get_results(
        $wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}eto_tournaments 
            WHERE id IN (
                SELECT tournament_id FROM {$wpdb->prefix}eto_teams 
                WHERE id IN (
                    SELECT team_id FROM {$wpdb->prefix}eto_team_members 
                    WHERE user_id = %d
                )
            )",
            $user_id
        )
    );
    
    // Logica visualizzazione...
}

// Shortcode [eto_tournament_bracket id="123"]  
function eto_tournament_bracket_shortcode($atts) {  
    wp_enqueue_script('jquery-bracket');  
    wp_enqueue_style('tournament-bracket');  

    $tournament_id = intval($atts['id']);  
    $bracket_data = ETO_Tournament::generate_bracket_data($tournament_id);  

    // Carica il template  
    ob_start();  
    include ETO_PLUGIN_DIR . 'templates/tournament-bracket.php';  
    return ob_get_clean();  
}  
add_shortcode('eto_tournament_bracket', 'eto_tournament_bracket_shortcode');  