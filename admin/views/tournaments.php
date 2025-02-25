<?php
if (!defined('ABSPATH')) exit;

global $wpdb;
$tournaments = $wpdb->get_results(
    $wpdb->prepare("SELECT * FROM {$wpdb->prefix}eto_tournaments WHERE status != %s ORDER BY start_date DESC", 'deleted')
);
?>

<div class="wrap">
    <h1 class="wp-heading-inline"><?php esc_html_e('Gestione Tornei', 'eto'); ?></h1>
    <a href="<?php echo esc_url(admin_url('admin.php?page=eto-create-tournament')); ?>" class="page-title-action">
        <?php esc_html_e('Aggiungi Nuovo', 'eto'); ?>
    </a>
    
    <hr class="wp-header-end">

    <?php if (!empty($tournaments)) : ?>
        <table class="wp-list-table widefat fixed striped">
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
                <?php foreach ($tournaments as $t) : ?>
                    <tr>
                        <td><?php echo esc_html($t->name); ?></td>
                        <td><?php echo esc_html(ucfirst(str_replace('_', ' ', $t->format))); ?></td>
                        <td><?php echo esc_html(date_i18n(get_option('date_format'), strtotime($t->start_date))); ?></td>
                        <td><span class="eto-status-badge"><?php echo esc_html(ucfirst($t->status)); ?></span></td>
                        <td>
                            <a href="<?php echo esc_url(admin_url(
                                'admin.php?page=eto-tournaments&action=edit&id=' . absint($t->id) . 
                                '&_wpnonce=' . wp_create_nonce('edit_tourni_' . $t->id)
                            )); ?>" class="button">
                                <?php esc_html_e('Modifica', 'eto'); ?>
                            </a>
                            <a href="<?php echo esc_url(wp_nonce_url(
                                admin_url('admin.php?page=eto-tournaments&action=delete&id=' . absint($t->id)), 
                                'delete_tourni_' . $t->id
                            )); ?>" class="button button-danger" 
                              onclick="return confirm('<?php esc_attr_e('Eliminare il torneo? Azione irreversibile!', 'eto'); ?>')">
                                <?php esc_html_e('Elimina', 'eto'); ?>
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
