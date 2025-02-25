<?php
/**
 * Template: Gestione Tornei
 * @package eSports Tournament Organizer
 */

if (!defined('ABSPATH')) exit;

// Controllo permessi
if (!current_user_can('manage_options')) {
    wp_die(__('Accesso negato', 'eto'));
}

// Recupera dati
$tournaments = ETO_Tournament::get_all();
?>

<div class="wrap eto-admin-wrap">
    <h1 class="eto-admin-title"><?php esc_html_e('Gestione Tornei', 'eto'); ?></h1>
    
    <div class="eto-admin-content">
        <?php if (!empty($tournaments)) : ?>
            <table class="wp-list-table widefat fixed striped eto-tournaments-table">
                <thead>
                    <tr>
                        <th><?php esc_html_e('Nome', 'eto'); ?></th>
                        <th><?php esc_html_e('Formato', 'eto'); ?></th>
                        <th><?php esc_html_e('Data Inizio', 'eto'); ?></th>
                        <th><?php esc_html_e('Stato', 'eto'); ?></th>
                        <th><?php esc_html_e('Azioni', 'eto'); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($tournaments as $tournament) : ?>
                        <tr>
                            <td><?php echo esc_html($tournament->name); ?></td>
                            <td><?php echo esc_html(ucfirst(str_replace('_', ' ', $tournament->format))); ?></td>
                            <td><?php echo date_i18n(get_option('date_format'), strtotime($tournament->start_date)); ?></td>
                            <td>
                                <span class="eto-status-badge eto-status-<?php echo esc_attr($tournament->status); ?>">
                                    <?php echo esc_html(ucfirst($tournament->status)); ?>
                                </span>
                            </td>
                            <td>
                                <a href="<?php echo esc_url(admin_url("admin.php?page=eto-tournaments&action=edit&tournament_id={$tournament->id}")); ?>" 
                                   class="button button-secondary">
                                    <?php esc_html_e('Modifica', 'eto'); ?>
                                </a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php else : ?>
            <div class="notice notice-info">
                <p><?php esc_html_e('Nessun torneo trovato.', 'eto'); ?></p>
            </div>
        <?php endif; ?>
    </div>
</div>