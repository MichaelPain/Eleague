<?php  
if (!defined('ABSPATH')) exit;
wp_nonce_field('eto_global_nonce');
global $wpdb;

$tournament = null;
$form_data = get_transient('eto_form_data');

if (isset($_GET['tournament_id'])) {
    $tournament = ETO_Tournament::get(absint($_GET['tournament_id']));
    if ($tournament) {
        $form_data = [
            'name' => $tournament->name,
            'format' => $tournament->format,
            'min_players' => $tournament->min_players,
            'max_players' => $tournament->max_players,
            'max_teams' => $tournament->max_teams,
            'checkin_enabled' => $tournament->checkin_enabled,
            'third_place_match' => $tournament->third_place_match,
            'start_date' => $tournament->start_date,
            'end_date' => $tournament->end_date,
            'game_type' => $tournament->game_type
        ];
    }
}

?>

<div class="wrap">
    <h1><?php esc_html_e('Crea Nuovo Torneo', 'eto'); ?></h1>
    
    <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
        <input type="hidden" name="action" value="eto_create_tournament">
        <?php wp_nonce_field('eto_create_tournament_action', '_eto_create_nonce'); ?>
        
        <div class="card">
            <div class="card-body">
                <div class="row">
                    <div class="col-md-6 mb-3">
                        <label for="name"><?php esc_html_e('Nome Torneo', 'eto'); ?></label>
                        <input type="text" class="form-control" id="name" name="name" required
                            value="<?php echo esc_attr($form_data['name'] ?? ''); ?>">
                    </div>
                    
                    <div class="col-md-6 mb-3">
                        <label><?php esc_html_e('Formato', 'eto'); ?></label>
                        <select class="form-control" id="format" name="format" required>
                            <option value="single_elimination" <?php selected($form_data['format'] ?? '', 'single_elimination'); ?>>
                                <?php esc_html_e('Eliminazione Singola', 'eto'); ?>
                            </option>
                            <option value="double_elimination" <?php selected($form_data['format'] ?? '', 'double_elimination'); ?>>
                                <?php esc_html_e('Eliminazione Doppia', 'eto'); ?>
                            </option>
                            <option value="swiss" <?php selected($form_data['format'] ?? '', 'swiss'); ?>>
                                <?php esc_html_e('Sistema Svizzero', 'eto'); ?>
                            </option>
                        </select>
                    </div>
                    
                    <div class="col-md-4 mb-3">
                        <label><?php esc_html_e('Giocatori per Team', 'eto'); ?></label>
                        <div class="input-group">
                            <input type="number" class="form-control" id="min_players" name="min_players" min="2" max="32" required
                                value="<?php echo esc_attr($form_data['min_players'] ?? ''); ?>">
                            <div class="input-group-append">
                                <span class="input-group-text">-</span>
                            </div>
                            <input type="number" class="form-control" id="max_players" name="max_players" min="2" max="32" required
                                value="<?php echo esc_attr($form_data['max_players'] ?? ''); ?>">
                        </div>
                    </div>
                    
                    <div class="col-md-4 mb-3">
                        <label><?php esc_html_e('Team Massimi', 'eto'); ?></label>
                        <input type="number" class="form-control" id="max_teams" name="max_teams" min="2" max="64" required
                            value="<?php echo esc_attr($form_data['max_teams'] ?? ''); ?>">
                    </div>
                    
                    <div class="col-md-4 mb-3">
                        <label><?php esc_html_e('Tipo di Gioco', 'eto'); ?></label>
                        <select class="form-control" id="game_type" name="game_type" required>
                            <option value="csgo" <?php selected($form_data['game_type'] ?? '', 'csgo'); ?>>
                                <?php esc_html_e('Counter-Strike: Global Offensive', 'eto'); ?>
                            </option>
                            <option value="lol" <?php selected($form_data['game_type'] ?? '', 'lol'); ?>>
                                <?php esc_html_e('League of Legends', 'eto'); ?>
                            </option>
                            <option value="dota2" <?php selected($form_data['game_type'] ?? '', 'dota2'); ?>>
                                <?php esc_html_e('Dota 2', 'eto'); ?>
                            </option>
                        </select>
                    </div>
                    
                    <div class="col-md-6 mb-3">
                        <label><?php esc_html_e('Data Inizio', 'eto'); ?></label>
                        <input type="datetime-local" class="form-control" id="start_date" name="start_date" required
                            value="<?php echo esc_attr($form_data['start_date'] ?? ''); ?>">
                    </div>
                    
                    <div class="col-md-6 mb-3">
                        <label><?php esc_html_e('Data Fine', 'eto'); ?></label>
                        <input type="datetime-local" class="form-control" id="end_date" name="end_date" required
                            value="<?php echo esc_attr($form_data['end_date'] ?? ''); ?>">
                    </div>
                    
                    <div class="col-md-6 mb-3">
                        <div class="form-check">
                            <input type="checkbox" class="form-check-input" id="checkin_enabled" name="checkin_enabled"
                                <?php checked($form_data['checkin_enabled'] ?? 0, 1); ?>>
                            <label class="form-check-label" for="checkin_enabled">
                                <?php esc_html_e('Abilita Check-in', 'eto'); ?>
                            </label>
                        </div>
                    </div>
                    
                    <div class="col-md-6 mb-3">
                        <div class="form-check">
                            <input type="checkbox" class="form-check-input" id="third_place_match" name="third_place_match"
                                <?php checked($form_data['third_place_match'] ?? 0, 1); ?>>
                            <label class="form-check-label" for="third_place_match">
                                <?php esc_html_e('Includi Finale 3°/4° Posto', 'eto'); ?>
                            </label>
                        </div>
                    </div>
                </div>
                
                <div class="row">
                    <div class="col-md-12">
                        <?php submit_button(__('Crea Torneo', 'eto')); ?>
                    </div>
                </div>
            </div>
        </form>
    </div>
</div>
