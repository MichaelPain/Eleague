<?php
if (!defined('ABSPATH')) exit;

global $wpdb;

// 1. Verifica permessi utente
if (!current_user_can('manage_eto_tournaments')) {
    wp_die(__('Accesso negato', 'eto'));
}

// 2. Recupero tornei con prepared statement
$tournaments = $wpdb->get_results(
    $wpdb->prepare(
        "SELECT * FROM {$wpdb->prefix}eto_tournaments 
        WHERE status != %s 
        ORDER BY start_date DESC",
        'deleted'
    )
);

// 3. Gestione eliminazione torneo
if (isset($_GET['action']) && $_GET['action'] === 'delete' && isset($_GET['id'])) {
    check_admin_referer('delete_tournament_' . $_GET['id']);
    ETO_Tournament::delete(absint($_GET['id']));
    eto_redirect_with_message(
        admin_url('admin.php?page=eto-tournaments'),
        __('Torneo eliminato con successo', 'eto')
    );
}
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
                    <th><?php esc_html_e('Team Registrati', 'eto'); ?></th>
                    <th><?php esc_html_e('Azioni', 'eto'); ?></th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($tournaments as $t) : ?>
                    <tr>
                        <td><?php echo esc_html($t->name); ?></td>
                        <td><?php echo esc_html(ucfirst(str_replace('_', ' ', $t->format))); ?></td>
                        <td><?php echo esc_html(date_i18n(get_option('date_format'), strtotime($t->start_date))); ?></td>
                        <td><?php printf(__('%d/%d', 'eto'), absint(ETO_Team::count($t->id)), absint($t->max_teams)); ?></td>
                        <td>
                            <a href="<?php echo esc_url(admin_url('admin.php?page=eto-create-tournament&tournament_id=' . absint($t->id))); ?>" 
                               class="button">
                                <?php esc_html_e('Modifica', 'eto'); ?>
                            </a>
                            
                            <a href="<?php echo esc_url(wp_nonce_url(
                                admin_url('admin.php?page=eto-tournaments&action=delete&id=' . absint($t->id)),
                                'delete_tournament_' . $t->id
                            )); ?>" 
                               class="button button-danger" 
                               onclick="return confirm('<?php esc_attr_e('Eliminare definitivamente questo torneo?', 'eto'); ?>')">
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