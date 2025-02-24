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
    ?>
    <div class="wrap">
        <h1>Gestione Tornei eSports</h1>
        
        <div class="eto-dashboard">
            <div class="eto-stats">
                <h2>Statistiche</h2>
                <ul>
                    <li>Tornei attivi: <?php echo ETO_Tournament::count('active'); ?></li>
                    <li>Team registrati: <?php echo ETO_Team::count(); ?></li>
                    <li>Partite in attesa: <?php echo ETO_Match::count_pending(); ?></li>
                </ul>
            </div>
            
            <div class="eto-actions">
                <a href="<?php echo admin_url('admin.php?page=eto-create-tournament'); ?>" class="button-primary">
                    Crea nuovo torneo
                </a>
                <a href="<?php echo admin_url('admin.php?page=eto-pending-matches'); ?>" class="button">
                    Verifica partite
                </a>
            </div>
        </div>
    </div>
    <?php
}

function eto_create_tournament_page() {
    ?>
    <div class="wrap">
        <h1>Crea nuovo torneo</h1>
        <form method="post" action="<?php echo admin_url('admin-post.php'); ?>">
            <input type="hidden" name="action" value="eto_create_tournament">
            <?php wp_nonce_field('eto_create_tournament'); ?>
            
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
                    <td><input type="datetime-local" name="start_date" required></td>
                </tr>
                <tr>
                    <th><label for="end_date">Data fine</label></th>
                    <td><input type="datetime-local" name="end_date" required></td>
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
                <?php foreach ($matches as $match): ?>
                <tr>
                    <td><?php echo $match->id; ?></td>
                    <td><?php echo ETO_Team::get($match->team1_id)->name; ?></td>
                    <td><?php echo $match->team2_id ? ETO_Team::get($match->team2_id)->name : 'BYE'; ?></td>
                    <td>
                        <?php if ($match->screenshot_url): ?>
                        <a href="<?php echo esc_url($match->screenshot_url); ?>" target="_blank">Visualizza</a>
                        <?php else: ?>
                        N/A
                        <?php endif; ?>
                    </td>
                    <td>
                        <button class="button-primary eto-confirm-match" 
                                data-match="<?php echo $match->id; ?>" 
                                data-nonce="<?php echo wp_create_nonce('confirm_match_' . $match->id); ?>">
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
