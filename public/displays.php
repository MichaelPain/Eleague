<?php
if (!defined('ABSPATH')) exit;

function eto_display_tournament_details($tournament_id) {
    $tournament = ETO_Tournament::get($tournament_id);
    if (!$tournament) return;

    $current_teams = ETO_Team::count($tournament_id);
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
                     style="width: <?php echo esc_attr(($current_teams / $tournament->max_teams) * 100); ?>%">
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
    $tournament = ETO_Tournament::get($tournament_id);
    $max_teams = $tournament->max_teams;
    $teams = ETO_Team::get_all($tournament_id);
    
    echo '<div class="eto-bracket-container">';
    
    if ($tournament->format === 'swiss') {
        ETO_Swiss::display_rounds($tournament_id);
    } else {
        // Logica per eliminazione diretta/doppia
        $max_rounds = log($max_teams, 2);
        for ($i = 1; $i <= $max_rounds; $i++) {
            echo '<div class="eto-round">';
            echo '<h3>' . sprintf(__('Round %d', 'eto'), $i) . '</h3>';
            // Visualizza le partite del round...
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

    ob_start();
    eto_display_tournament_details($atts['id']);
    if ($atts['show_bracket']) {
        eto_display_tournament_bracket($atts['id']);
    }
    return ob_get_clean();
});
