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
                    <th scope="col"><?php esc_html_e('Nome', 'eto'); ?></th>
                    <th scope="col"><?php esc_html_e('Formato', 'eto'); ?></th>
                    <th scope="col"><?php esc_html_e('Data Inizio', 'eto'); ?></th>
                    <th scope="col"><?php esc_html_e('Stato', 'eto'); ?></th>
                    <th scope="col"><?php esc_html_e('Azioni', 'eto'); ?></th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($tournaments as $tournament) : ?>
                    <tr>
                        <td><?php echo esc_html($tournament->name); ?></td>
                        <td><?php echo esc_html(ucfirst(str_replace('_', ' ', $tournament->format))); ?></td>
                        <td><?php echo esc_html(date_i18n(get_option('date_format'), strtotime($tournament->start_date))); ?></td>
                        <td>
                            <span class="eto-status-<?php echo sanitize_html_class($tournament->status); ?>">
                                <?php echo esc_html(ucfirst($tournament->status)); ?>
                            </span>
                        </td>
                        <td>
                            <a href="<?php echo esc_url(admin_url('admin.php?page=eto-tournaments&action=edit&tournament_id=' . absint($tournament->id) . '&_wpnonce=' . wp_create_nonce('edit_tournament_' . $tournament->id))); ?>" 
                               class="button button-primary">
                                <?php esc_html_e('Modifica', 'eto'); ?>
                            </a>
                            <a href="<?php echo esc_url(wp_nonce_url(admin_url('admin.php?page=eto-tournaments&action=delete&tournament_id=' . absint($tournament->id)), 'delete_tournament_' . $tournament->id)); ?>" 
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
