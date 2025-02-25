<?php
if (!defined('ABSPATH')) exit;

global $wpdb;

$tournament_id = isset($_GET['tournament_id']) ? absint($_GET['tournament_id']) : 0;
$tournament = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$wpdb->prefix}eto_tournaments WHERE id = %d", $tournament_id));
$form_data = get_transient('eto_form_data');
delete_transient('eto_form_data');
?>

<div class="wrap">
    <h1 class="wp-heading-inline"><?php echo $tournament_id ? esc_html__('Modifica Torneo', 'eto') : esc_html__('Crea Nuovo Torneo', 'eto'); ?></h1>
    
    <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
        <input type="hidden" name="action" value="eto_<?php echo $tournament_id ? 'update' : 'create'; ?>_tournament">
        <input type="hidden" name="tournament_id" value="<?php echo absint($tournament_id); ?>">
        
        <?php wp_nonce_field('eto_tournament_management', '_eto_tournament_nonce'); ?>

        <div class="form-field">
            <label for="tournament_name"><?php esc_html_e('Nome Torneo', 'eto'); ?></label>
            <input type="text" name="tournament_name" id="tournament_name" 
                   value="<?php echo $tournament ? esc_attr($tournament->name) : ($form_data['tournament_name'] ?? ''); ?>"
                   class="regular-text" required>
        </div>

        <div class="form-field">
            <label for="format"><?php esc_html_e('Formato Torneo', 'eto'); ?></label>
            <select name="format" id="format" class="regular-text" required>
                <option value="single_elimination" <?php selected(($tournament->format ?? $form_data['format'] ?? ''), 'single_elimination'); ?>>
                    <?php esc_html_e('Eliminazione Diretta', 'eto'); ?>
                </option>
                <option value="swiss" <?php selected(($tournament->format ?? $form_data['format'] ?? ''), 'swiss'); ?>>
                    <?php esc_html_e('Sistema Svizzero', 'eto'); ?>
                </option>
            </select>
        </div>

        <div class="form-field">
            <label for="game_type"><?php esc_html_e('Tipo di Gioco', 'eto'); ?></label>
            <select name="game_type" id="game_type" class="regular-text" required>
                <option value="csgo" <?php selected(($tournament->game_type ?? $form_data['game_type'] ?? ''), 'csgo'); ?>>
                    <?php esc_html_e('CS:GO', 'eto'); ?>
                </option>
                <option value="lol" <?php selected(($tournament->game_type ?? $form_data['game_type'] ?? ''), 'lol'); ?>>
                    <?php esc_html_e('League of Legends', 'eto'); ?>
                </option>
            </select>
        </div>

        <div class="form-field">
            <label for="start_date"><?php esc_html_e('Data e Ora Inizio', 'eto'); ?></label>
            <input type="datetime-local" name="start_date" id="start_date" 
                   value="<?php echo $tournament ? esc_attr(date('Y-m-d\TH:i', strtotime($tournament->start_date))) : ($form_data['start_date'] ?? ''); ?>"
                   class="regular-text" required>
        </div>

        <?php if ($tournament_id) : ?>
            <input type="hidden" name="original_status" value="<?php echo esc_attr($tournament->status); ?>">
            <div class="form-field">
                <label for="status"><?php esc_html_e('Stato Torneo', 'eto'); ?></label>
                <select name="status" id="status" class="regular-text">
                    <option value="pending" <?php selected($tournament->status, 'pending'); ?>><?php esc_html_e('In attesa', 'eto'); ?></option>
                    <option value="active" <?php selected($tournament->status, 'active'); ?>><?php esc_html_e('Attivo', 'eto'); ?></option>
                    <option value="completed" <?php selected($tournament->status, 'completed'); ?>><?php esc_html_e('Completato', 'eto'); ?></option>
                </select>
            </div>
        <?php endif; ?>

        <?php submit_button($tournament_id ? __('Aggiorna Torneo', 'eto') : __('Crea Torneo', 'eto')); ?>
    </form>
</div>
