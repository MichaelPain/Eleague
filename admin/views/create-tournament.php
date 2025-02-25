<?php
if (!defined('ABSPATH')) exit;

global $wpdb;

$tournament_id = isset($_GET['tournament_id']) ? absint($_GET['tournament_id']) : 0;
$tournament = $tournament_id ? ETO_Tournament::get($tournament_id) : null;
$form_data = get_transient('eto_form_data');
delete_transient('eto_form_data');

$defaults = [
    'tournament_name' => '',
    'format' => 'single_elimination',
    'min_players' => 1,
    'max_players' => 5,
    'max_teams' => 16,
    'third_place_match' => 0,
    'start_date' => date('Y-m-d\TH:i', strtotime('+1 day')),
    'end_date' => date('Y-m-d\TH:i', strtotime('+8 days'))
];
?>

<div class="wrap">
    <h1 class="wp-heading-inline">
        <?php echo $tournament_id ? esc_html__('Modifica Torneo', 'eto') : esc_html__('Crea Nuovo Torneo', 'eto'); ?>
    </h1>

    <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
        <input type="hidden" name="action" value="eto_<?php echo $tournament_id ? 'update' : 'create'; ?>_tournament">
        <input type="hidden" name="tournament_id" value="<?php echo absint($tournament_id); ?>">
        
        <?php wp_nonce_field('eto_tournament_management', '_eto_tournament_nonce'); ?>

        <table class="form-table">
            <tbody>
                <!-- Nome Torneo -->
                <tr>
                    <th scope="row">
                        <label for="tournament_name"><?php esc_html_e('Nome Torneo', 'eto'); ?></label>
                    </th>
                    <td>
                        <input type="text" name="tournament_name" id="tournament_name" 
                            class="regular-text" required
                            value="<?php echo esc_attr($tournament->name ?? $form_data['tournament_name'] ?? $defaults['tournament_name']); ?>">
                    </td>
                </tr>

                <!-- Formato Torneo -->
                <tr>
                    <th scope="row">
                        <label for="format"><?php esc_html_e('Formato', 'eto'); ?></label>
                    </th>
                    <td>
                        <select name="format" id="format" class="regular-text" required>
                            <option value="single_elimination" <?php selected($tournament->format ?? $form_data['format'] ?? $defaults['format'], 'single_elimination'); ?>>
                                <?php esc_html_e('Eliminazione Diretta', 'eto'); ?>
                            </option>
                            <option value="double_elimination" <?php selected($tournament->format ?? $form_data['format'] ?? $defaults['format'], 'double_elimination'); ?>>
                                <?php esc_html_e('Doppia Eliminazione', 'eto'); ?>
                            </option>
                            <option value="swiss" <?php selected($tournament->format ?? $form_data['format'] ?? $defaults['format'], 'swiss'); ?>>
                                <?php esc_html_e('Sistema Svizzero', 'eto'); ?>
                            </option>
                        </select>
                    </td>
                </tr>

                <!-- Configurazione Team -->
                <tr>
                    <th scope="row"><?php esc_html_e('Configurazione Team', 'eto'); ?></th>
                    <td>
                        <fieldset>
                            <label for="min_players">
                                <?php esc_html_e('Giocatori min per team:', 'eto'); ?>
                                <input type="number" name="min_players" id="min_players" 
                                    min="1" max="10" required
                                    value="<?php echo esc_attr($tournament->min_players ?? $form_data['min_players'] ?? $defaults['min_players']); ?>">
                            </label>

                            <label for="max_players">
                                <?php esc_html_e('Giocatori max per team:', 'eto'); ?>
                                <input type="number" name="max_players" id="max_players" 
                                    min="1" max="10" required
                                    value="<?php echo esc_attr($tournament->max_players ?? $form_data['max_players'] ?? $defaults['max_players']); ?>">
                            </label>

                            <label for="max_teams">
                                <?php esc_html_e('Numero massimo team:', 'eto'); ?>
                                <input type="number" name="max_teams" id="max_teams" 
                                    min="2" max="64" required
                                    value="<?php echo esc_attr($tournament->max_teams ?? $form_data['max_teams'] ?? $defaults['max_teams']); ?>">
                            </label>
                        </fieldset>
                    </td>
                </tr>

                <!-- Date Torneo -->
                <tr>
                    <th scope="row"><?php esc_html_e('Date Torneo', 'eto'); ?></th>
                    <td>
                        <label for="start_date">
                            <?php esc_html_e('Data inizio:', 'eto'); ?>
                            <input type="datetime-local" name="start_date" id="start_date" 
                                required
                                value="<?php echo esc_attr($tournament->start_date ?? $form_data['start_date'] ?? $defaults['start_date']); ?>">
                        </label>

                        <label for="end_date">
                            <?php esc_html_e('Data fine:', 'eto'); ?>
                            <input type="datetime-local" name="end_date" id="end_date" 
                                required
                                value="<?php echo esc_attr($tournament->end_date ?? $form_data['end_date'] ?? $defaults['end_date']); ?>">
                        </label>
                    </td>
                </tr>

                <!-- Opzioni Avanzate -->
                <tr>
                    <th scope="row"><?php esc_html_e('Opzioni', 'eto'); ?></th>
                    <td>
                        <label for="third_place_match">
                            <input type="checkbox" name="third_place_match" id="third_place_match" 
                                <?php checked($tournament->third_place_match ?? $form_data['third_place_match'] ?? $defaults['third_place_match'], 1); ?>>
                            <?php esc_html_e('Disputa finale 3°/4° posto', 'eto'); ?>
                        </label>
                    </td>
                </tr>
            </tbody>
        </table>

        <?php submit_button($tournament_id ? __('Aggiorna Torneo', 'eto') : __('Crea Torneo', 'eto'), 'primary', 'submit', true); ?>
    </form>
</div>