<?php
/**
 * Pagine di Amministrazione
 */

// Verifica che le classi necessarie esistano prima di procedere
if (!class_exists('ETO_Tournament') || !class_exists('ETO_Team') || !class_exists('ETO_Match')) {
    wp_die(__('Classi core del plugin mancanti. Disinstalla e reinstalla il plugin.', 'eto'));
}

function eto_admin_menu() {
    try {
        // Aggiungi menu principale
        add_menu_page(
            'Gestione Tornei eSports',
            'Tornei eSports',
            'manage_options',
            'eto-tournaments',
            'eto_main_admin_page',
            'dashicons-games',
            6
        );

        // Sottopagine
        add_submenu_page(
            'eto-tournaments',
            'Crea Nuovo Torneo',
            'Crea Torneo',
            'manage_options',
            'eto-create-tournament',
            'eto_create_tournament_page'
        );

        add_submenu_page(
            'eto-tournaments',
            'Partite in Sospeso',
            'Partite in Sospeso',
            'manage_options',
            'eto-pending-matches',
            'eto_pending_matches_page'
        );

    } catch (Exception $e) {
        error_log('[ETO] Errore creazione menu: ' . $e->getMessage());
    }
}
add_action('admin_menu', 'eto_admin_menu');

function eto_main_admin_page() {
    // Verifica permessi e nonce
    if (!current_user_can('manage_options')) {
        wp_die(__('Accesso negato', 'eto'));
    }

    // Stili specifici per la pagina
    wp_enqueue_style('eto-admin-styles');

    // Logica dati
    $active_tournaments = ETO_Tournament::count('active');
    $total_teams = ETO_Team::count();
    $pending_matches = ETO_Match::count_pending();

    // Output HTML con escaping
    ?>
    <div class="wrap eto-admin-wrap">
        <h1><?php esc_html_e('Gestione Tornei eSports', 'eto'); ?></h1>
        
        <div class="eto-dashboard">
            <div class="eto-stats-card">
                <h2 class="eto-stats-title"><?php esc_html_e('Statistiche', 'eto'); ?></h2>
                <ul class="eto-stats-list">
                    <li>
                        <span class="stat-label"><?php esc_html_e('Tornei attivi:', 'eto'); ?></span>
                        <span class="stat-value"><?php echo absint($active_tournaments); ?></span>
                    </li>
                    <li>
                        <span class="stat-label"><?php esc_html_e('Team registrati:', 'eto'); ?></span>
                        <span class="stat-value"><?php echo absint($total_teams); ?></span>
                    </li>
                    <li>
                        <span class="stat-label"><?php esc_html_e('Partite in attesa:', 'eto'); ?></span>
                        <span class="stat-value"><?php echo absint($pending_matches); ?></span>
                    </li>
                </ul>
            </div>
            
            <div class="eto-quick-actions">
                <a href="<?php echo esc_url(admin_url('admin.php?page=eto-create-tournament')); ?>" class="button button-primary button-hero">
                    <?php esc_html_e('Crea nuovo torneo', 'eto'); ?>
                </a>
                <a href="<?php echo esc_url(admin_url('admin.php?page=eto-pending-matches')); ?>" class="button button-secondary button-hero">
                    <?php esc_html_e('Gestisci partite', 'eto'); ?>
                </a>
            </div>
        </div>
    </div>
    <?php
}

function eto_create_tournament_page() {
    if (!current_user_can('manage_options')) {
        wp_die(__('Accesso negato', 'eto'));
    }

    // Enqueue datepicker
    wp_enqueue_script('jquery-ui-datepicker');
    wp_enqueue_style('jquery-ui-css', 'https://code.jquery.com/ui/1.12.1/themes/base/jquery-ui.css');

    // Logica form
    $tournament_form_nonce = wp_create_nonce('eto_create_tournament_nonce');
    
    ?>
    <div class="wrap eto-admin-wrap">
        <h1><?php esc_html_e('Crea nuovo torneo', 'eto'); ?></h1>
        
        <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" class="eto-form">
            <input type="hidden" name="action" value="eto_create_tournament">
            <input type="hidden" name="eto_nonce" value="<?php echo esc_attr($tournament_form_nonce); ?>">
            
            <div class="eto-form-section">
                <label for="tournament_name"><?php esc_html_e('Nome torneo:', 'eto'); ?></label>
                <input type="text" name="tournament_name" required class="regular-text">
            </div>

            <div class="eto-form-section">
                <label for="format"><?php esc_html_e('Formato:', 'eto'); ?></label>
                <select name="format" id="eto-format-selector" required>
                    <option value=""><?php esc_html_e('Seleziona formato', 'eto'); ?></option>
                    <option value="single_elimination"><?php esc_html_e('Single Elimination', 'eto'); ?></option>
                    <option value="double_elimination"><?php esc_html_e('Double Elimination', 'eto'); ?></option>
                    <option value="swiss"><?php esc_html_e('Swiss System', 'eto'); ?></option>
                </select>
            </div>

            <div class="eto-form-dates">
                <div class="eto-form-section">
                    <label for="start_date"><?php esc_html_e('Data inizio:', 'eto'); ?></label>
                    <input type="datetime-local" name="start_date" id="eto-start-date" required>
                </div>

                <div class="eto-form-section">
                    <label for="end_date"><?php esc_html_e('Data fine:', 'eto'); ?></label>
                    <input type="datetime-local" name="end_date" id="eto-end-date" required>
                </div>
            </div>

            <div class="eto-form-section">
                <label><?php esc_html_e('Dimensioni team:', 'eto'); ?></label>
                <div class="eto-range-selector">
                    <input type="number" name="min_players" min="1" max="10" placeholder="<?php esc_attr_e('Min', 'eto'); ?>" required>
                    <span><?php esc_html_e('a', 'eto'); ?></span>
                    <input type="number" name="max_players" min="1" max="10" placeholder="<?php esc_attr_e('Max', 'eto'); ?>" required>
                </div>
            </div>

            <div class="eto-form-section">
                <label class="eto-checkbox-label">
                    <input type="checkbox" name="checkin_enabled" value="1">
                    <?php esc_html_e('Check-in obbligatorio prima dell\'inizio', 'eto'); ?>
                </label>
            </div>

            <?php submit_button(__('Crea torneo', 'eto'), 'primary', 'submit', false); ?>
        </form>
    </div>

    <script>
    jQuery(document).ready(function($) {
        // Datepicker integration
        $('#eto-start-date, #eto-end-date').datetimepicker({
            dateFormat: 'yy-mm-dd',
            timeFormat: 'HH:mm'
        });
    });
    </script>
    <?php
}

function eto_pending_matches_page() {
    if (!current_user_can('manage_options')) {
        wp_die(__('Accesso negato', 'eto'));
    }

    // Recupera dati
    $matches = ETO_Match::get_pending();
    $matches_data = array_map(function($match) {
        return [
            'id' => $match->id,
            'team1' => ETO_Team::get($match->team1_id),
            'team2' => $match->team2_id ? ETO_Team::get($match->team2_id) : null,
            'screenshot' => $match->screenshot_url
        ];
    }, $matches);

    ?>
    <div class="wrap eto-admin-wrap">
        <h1><?php esc_html_e('Partite in attesa di conferma', 'eto'); ?></h1>
        
        <div class="eto-matches-table-wrapper">
            <table class="wp-list-table widefat fixed striped eto-matches-table">
                <thead>
                    <tr>
                        <th><?php esc_html_e('ID Partita', 'eto'); ?></th>
                        <th><?php esc_html_e('Team 1', 'eto'); ?></th>
                        <th><?php esc_html_e('Team 2', 'eto'); ?></th>
                        <th><?php esc_html_e('Screenshot', 'eto'); ?></th>
                        <th><?php esc_html_e('Azioni', 'eto'); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($matches_data as $match): ?>
                    <tr>
                        <td><?php echo absint($match['id']); ?></td>
                        <td><?php echo esc_html($match['team1']->name); ?></td>
                        <td><?php echo $match['team2'] ? esc_html($match['team2']->name) : 'BYE'; ?></td>
                        <td>
                            <?php if ($match['screenshot']): ?>
                            <a href="<?php echo esc_url($match['screenshot']); ?>" 
                               class="eto-screenshot-link" 
                               target="_blank"
                               aria-label="<?php esc_attr_e('Visualizza screenshot', 'eto'); ?>">
                                📷
                            </a>
                            <?php else: ?>
                            <span class="eto-no-screenshot"><?php esc_html_e('N/A', 'eto'); ?></span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <button class="button button-primary eto-confirm-match" 
                                    data-match-id="<?php echo absint($match['id']); ?>"
                                    data-nonce="<?php echo esc_attr(wp_create_nonce('confirm_match_' . $match['id'])); ?>">
                                <?php esc_html_e('Conferma', 'eto'); ?>
                            </button>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
    <?php
}

// Registra gli stili admin
function eto_register_admin_styles() {
    wp_register_style(
        'eto-admin-styles',
        plugins_url('css/admin.css', dirname(__FILE__)),
        [],
        filemtime(plugin_dir_path(dirname(__FILE__)) . 'css/admin.css')
    );
}
add_action('admin_enqueue_scripts', 'eto_register_admin_styles');
