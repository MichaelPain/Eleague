<?php
/**
 * Template: Gestione Tornei
 * @package eSports Tournament Organizer
 */

// Blocca l'accesso diretto
if (!defined('ABSPATH')) {
    exit;
}

// Carica le dipendenze di WordPress necessarie
if (!function_exists('wp_get_current_user')) {
    require_once(ABSPATH . WPINC . '/pluggable.php');
}

// Controllo permessi con capacità specifica del plugin
if (!current_user_can('manage_eto_tournaments')) {
    wp_die(__('Accesso negato: Non hai i permessi necessari per visualizzare questa pagina.', 'eto'));
}

// Recupera dati con controllo degli errori
try {
    $tournaments = ETO_Tournament::get_all();
} catch (Exception $e) {
    $tournaments = [];
    echo '<div class="notice notice-error"><p>' . esc_html__('Errore nel caricamento dei tornei:', 'eto') . ' ' . esc_html($e->getMessage()) . '</p></div>';
}
?>

<div class="wrap eto-admin-wrap">
    <h1 class="eto-admin-title">
        <?php esc_html_e('Gestione Tornei', 'eto'); ?>
        <a href="<?php echo esc_url(admin_url('admin.php?page=eto-create-tournament')); ?>" class="page-title-action">
            <?php esc_html_e('Aggiungi Nuovo', 'eto'); ?>
        </a>
    </h1>
    
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
                    <?php foreach ($tournaments as $tournament) : 
                        $start_date = strtotime($tournament->start_date);
                        $status_label = ucfirst($tournament->status);
                        $status_class = str_replace(' ', '-', strtolower($status_label));
                    ?>
                        <tr>
                            <td><?php echo esc_html($tournament->name); ?></td>
                            <td><?php echo esc_html(ucfirst(str_replace('_', ' ', $tournament->format))); ?></td>
                            <td><?php echo ($start_date ? date_i18n(get_option('date_format'), $start_date) : 'N/A'); ?></td>
                            <td>
                                <span class="eto-status-badge eto-status-<?php echo esc_attr($status_class); ?>">
                                    <?php echo esc_html($status_label); ?>
                                </span>
                            </td>
                            <td>
                                <div class="eto-actions">
                                    <a href="<?php echo esc_url(admin_url("admin.php?page=eto-tournaments&action=edit&tournament_id={$tournament->id}")); ?>" 
                                       class="button button-primary">
                                        <?php esc_html_e('Modifica', 'eto'); ?>
                                    </a>
                                    <button class="button eto-delete-tournament" 
                                            data-tournament-id="<?php echo absint($tournament->id); ?>"
                                            data-nonce="<?php echo esc_attr(wp_create_nonce('delete_tournament_' . $tournament->id)); ?>">
                                        <?php esc_html_e('Elimina', 'eto'); ?>
                                    </button>
                                </div>
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

<style>
.eto-status-badge {
    padding: 4px 8px;
    border-radius: 3px;
    font-size: 12px;
}

.eto-status-pending { background: #f0f0f0; color: #555; }
.eto-status-active { background: #d8f2e5; color: #1a4d32; }
.eto-status-completed { background: #e5f2f8; color: #1a3d4d; }
.eto-status-cancelled { background: #f8e5e5; color: #4d1a1a; }

.eto-actions {
    display: flex;
    gap: 4px;
    flex-wrap: wrap;
}
</style>
