<?php
if (!defined('ABSPATH')) exit;

function eto_display_tournament_details($tournament_id) {
    $tournament = ETO_Tournament::get(absint($tournament_id));
    if (!$tournament) return;

    $current_teams = ETO_Team::count($tournament_id);
    $max_teams = max(1, $tournament->max_teams); // Previene divisione per zero
    $progress_width = ($current_teams / $max_teams) * 100;
    $registration_status = $current_teams < $tournament->max_teams 
        ? __('Apertura Registrazioni', 'eto') 
        : __('Registrazioni Chiuse', 'eto');
    ?>
    
    <div class="eto-tournament-details">
        <!-- Sezione Metadati -->
        <div class="eto-meta-section">
            <div class="eto-meta-box">
                <h3><?php esc_html_e('Configurazione Torneo', 'eto'); ?></h3>
                <ul>
                    <li>
                        <strong><?php esc_html_e('Formato:', 'eto'); ?></strong>
                        <span><?php echo esc_html(ucfirst(str_replace('_', ' ', $tournament->format))); ?></span>
                    </li>
                    <li>
                        <strong><?php esc_html_e('Team Registrati:', 'eto'); ?></strong>
                        <span><?php printf(__('%d / %d', 'eto'), $current_teams, $tournament->max_teams); ?></span>
                    </li>
                    <li>
                        <strong><?php esc_html_e('Giocatori per Team:', 'eto'); ?></strong>
                        <span><?php printf(__('%d-%d', 'eto'), $tournament->min_players, $tournament->max_players); ?></span>
                    </li>
                </ul>
            </div>

            <div class="eto-meta-box">
                <h3><?php esc_html_e('Date Importanti', 'eto'); ?></h3>
                <ul>
                    <li>
                        <strong><?php esc_html_e('Inizio:', 'eto'); ?></strong>
                        <span><?php echo esc_html(date_i18n(get_option('date_format'), strtotime($tournament->start_date))); ?></span>
                    </li>
                    <li>
                        <strong><?php esc_html_e('Fine:', 'eto'); ?></strong>
                        <span><?php echo esc_html(date_i18n(get_option('date_format'), strtotime($tournament->end_date))); ?></span>
                    </li>
                </ul>
            </div>
        </div>

        <!-- Barra Progresso Registrazioni -->
        <div class="eto-registration-progress">
            <div class="progress-label">
                <?php echo esc_html($registration_status); ?>
                <span class="progress-count">
                    (<?php printf(__('%d/%d team', 'eto'), $current_teams, $tournament->max_teams); ?>)
                </span>
            </div>
            <div class="progress-bar">
                <div class="progress-fill" 
                     style="width: <?php echo esc_attr($progress_width); ?>%">
                </div>
            </div>
        </div>

        <!-- Avviso Registrazioni -->
        <?php if ($current_teams >= $tournament->max_teams) : ?>
            <div class="eto-alert eto-alert-warning">
                <?php esc_html_e('Registrazioni chiuse - Posti esauriti!', 'eto'); ?>
            </div>
        <?php endif; ?>
    </div>
    
    <?php
}

function eto_display_tournament_bracket($tournament_id) {
    $tournament = ETO_Tournament::get(absint($tournament_id));
    if (!$tournament) return;

    $matches = ETO_Match::get_all($tournament_id);
    echo '<div class="eto-bracket-container">';
    
    if ($tournament->format === 'swiss') {
        ETO_Swiss::display_rounds($tournament_id);
    } else {
        // Logica per eliminazione diretta/doppia
        $max_rounds = ceil(log(max(1, $tournament->max_teams), 2));
        for ($i = 1; $i <= $max_rounds; $i++) {
            echo '<div class="eto-round">';
            echo '<h3>' . sprintf(__('Round %d', 'eto'), $i) . '</h3>';
            $round_matches = array_filter($matches, function($m) use ($i) {
                return $m->round == "Round $i";
            });
            foreach ($round_matches as $match) {
                echo '<div class="eto-match">' . esc_html($match->team1_name) . ' vs ' . esc_html($match->team2_name) . '</div>';
            }
            echo '</div>';
        }
    }
    echo '</div>';
}

// Shortcode per visualizzazione torneo
add_shortcode('tournament_view', function($atts) {
    $atts = shortcode_atts([
        'id' => 0,
        'show_bracket' => true
    ], $atts);

    if (!ETO_Tournament::exists(absint($atts['id']))) {
        return '<div class="eto-error">' . esc_html__('Torneo non trovato', 'eto') . '</div>';
    }

    ob_start();
    eto_display_tournament_details(absint($atts['id']));
    if ($atts['show_bracket']) {
        eto_display_tournament_bracket(absint($atts['id']));
    }
    return ob_get_clean();
});
