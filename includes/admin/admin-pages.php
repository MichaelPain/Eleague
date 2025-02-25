<?php
/**
 * Pagine di Amministrazione
 */

function eto_admin_menu() {
    add_menu_page(
        'Gestione Tornei eSports', 
        'Tornei eSports', 
        'manage_options', 
        'eto-tournaments', 
        'eto_main_admin_page', 
        'dashicons-games', 
        6
    );

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
}
add_action('admin_menu', 'eto_admin_menu');

function eto_main_admin_page() {
    if (!current_user_can('manage_options')) {
        wp_die(__('Permessi insufficienti', 'eto'));
    }
    
    global $wpdb;
    $tournaments = $wpdb->get_results("SELECT * FROM {$wpdb->prefix}eto_tournaments");
    
    ?>
    <div class="wrap">
        <h1>Gestione Tornei eSports</h1>
        
        <div class="eto-dashboard">
            <div class="eto-stats-card">
                <h2>Statistiche</h2>
                <ul>
                    <li>Tornei attivi: <?php echo ETO_Tournament::count('active'); ?></li>
                    <li>Team registrati: <?php echo ETO_Team::count(); ?></li>
                    <li>Partite in attesa: <?php echo ETO_Match::count_pending(); ?></li>
                </ul>
            </div>
            
            <table class="wp-list-table widefat fixed striped">
                <thead>
                    <tr>
                        <th>Nome</th>
                        <th>Formato</th>
                        <th>Data Inizio</th>
                        <th>Stato</th>
                        <th>Azioni</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($tournaments as $tournament) : ?>
                    <tr>
                        <td><?php echo esc_html($tournament->name); ?></td>
                        <td><?php echo ucfirst(str_replace('_', ' ', $tournament->format)); ?></td>
                        <td><?php echo date_i18n('j F Y H:i', strtotime($tournament->start_date)); ?></td>
                        <td><span class="eto-status eto-status-<?php echo $tournament->status; ?>"><?php echo ucfirst($tournament->status); ?></span></td>
                        <td>
                            <a href="<?php echo admin_url("admin.php?page=eto-tournaments&action=edit&id={$tournament->id}"); ?>" class="button button-primary">Modifica</a>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
    <?php
}

function eto_create_tournament_page() {
    if (!current_user_can('manage_options')) {
        wp_die(__('Permessi insufficienti', 'eto'));
    }
    
    wp_enqueue_script('jquery-ui-datepicker');
    wp_enqueue_style('jquery-ui-css', 'https://code.jquery.com/ui/1.12.1/themes/base/jquery-ui.css');
    
    ?>
    <div class="wrap">
        <h1>Crea nuovo torneo</h1>
        <form method="post" action="<?php echo admin_url('admin-post.php'); ?>">
            <input type="hidden" name="action" value="eto_create_tournament">
            <?php wp_nonce_field('eto_create_tournament_nonce'); ?>
            
            <table class="form-table">
                <tr>
                    <th><label for="tournament_name">Nome torneo</label></th>
                    <td><input type="text" name="tournament_name" required class="regular-text"></td>
                </tr>
                <tr>
                    <th><label for="format">Formato</label></th>
                    <td>
                        <select name="format" id="format" required>
                            <option value="single_elimination">Single Elimination</option>
                            <option value="double_elimination">Double Elimination</option>
                            <option value="swiss">Swiss System</option>
                        </select>
                    </td>
                </tr>
                <tr>
                    <th><label for="start_date">Data inizio</label></th>
                    <td><input type="datetime-local" name="start_date" id="eto-start-date" required></td>
                </tr>
                <tr>
                    <th><label for="end_date">Data fine</label></th>
                    <td><input type="datetime-local" name="end_date" id="eto-end-date" required></td>
                </tr>
                <tr>
                    <th><label for="min_players">Membri minimi per team</label></th>
                    <td><input type="number" name="min_players" min="1" max="10" required></td>
                </tr>
                <tr>
                    <th><label for="max_players">Membri massimi per team</label></th>
                    <td><input type="number" name="max_players" min="1" max="10" required></td>
                </tr>
                <tr>
                    <th><label for="checkin_enabled">Check-in obbligatorio</label></th>
                    <td><input type="checkbox" name="checkin_enabled" value="1"></td>
                </tr>
            </table>
            <?php submit_button('Crea torneo'); ?>
        </form>
    </div>
    <?php
}

function eto_pending_matches_page() {
    if (!current_user_can('manage_options')) {
        wp_die(__('Permessi insufficienti', 'eto'));
    }
    
    $matches = ETO_Match::get_pending();
    
    ?>
    <div class="wrap">
        <h1>Partite in attesa di conferma</h1>
        <table class="wp-list-table widefat fixed striped">
            <thead>
                <tr>
                    <th>ID Partita</th>
                    <th>Team 1</th>
                    <th>Team 2</th>
                    <th>Screenshot</th>
                    <th>Azioni</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($matches as $match) : 
                    $team1 = ETO_Team::get($match->team1_id);
                    $team2 = $match->team2_id ? ETO_Team::get($match->team2_id) : null;
                ?>
                <tr>
                    <td><?php echo absint($match->id); ?></td>
                    <td><?php echo esc_html($team1->name); ?></td>
                    <td><?php echo $team2 ? esc_html($team2->name) : 'BYE'; ?></td>
                    <td>
                        <?php if ($match->screenshot_url) : ?>
                            <a href="<?php echo esc_url($match->screenshot_url); ?>" target="_blank">Visualizza</a>
                        <?php else : ?>
                            N/A
                        <?php endif; ?>
                    </td>
                    <td>
                        <button class="button button-primary eto-confirm-match" 
                                data-match-id="<?php echo absint($match->id); ?>"
                                data-nonce="<?php echo esc_attr(wp_create_nonce('confirm_match_' . $match->id)); ?>">
                            Conferma
                        </button>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php
}

add_action('admin_enqueue_scripts', function($hook) {
    if (strpos($hook, 'eto-') !== false) {
        wp_enqueue_style(
            'eto-admin-css',
            plugins_url('assets/css/admin.css', dirname(__FILE__)),
            [],
            filemtime(plugin_dir_path(dirname(__FILE__)) . 'assets/css/admin.css')
        );
        
        wp_enqueue_script(
            'eto-admin-js',
            plugins_url('assets/js/admin.js', dirname(__FILE__)),
            ['jquery', 'jquery-ui-datepicker'],
            filemtime(plugin_dir_path(dirname(__FILE__)) . 'assets/js/admin.js'),
            true
        );
    }
});
