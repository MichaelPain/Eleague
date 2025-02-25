<?php
// Verifica permessi prima di qualsiasi operazione
if (!current_user_can('manage_eto_tournaments')) {
    wp_die(__('Accesso negato.', 'eto'));
}

// Gestione eliminazione torneo
if (isset($_GET['action']) && $_GET['action'] === 'delete' && !empty($_GET['tournament_id'])) {
    check_admin_referer('delete_tournament_' . $_GET['tournament_id']);
    
    $tournament_id = absint($_GET['tournament_id']);
    ETO_Tournament::delete($tournament_id);
    
    wp_redirect(admin_url('admin.php?page=eto-tournaments&deleted=1'));
    exit;
}

global $wpdb;

// Query sicura con prepared statement
$tournaments = $wpdb->get_results(
    $wpdb->prepare("SELECT * FROM {$wpdb->prefix}eto_tournaments WHERE status != %s ORDER BY start_date DESC", 'deleted')
);

// Recupera tutte le partite con prepared statement
$matches = $wpdb->get_results(
    $wpdb->prepare("SELECT * FROM {$wpdb->prefix}eto_matches WHERE tournament_id = %d", $tournament->id)
);
?>

<div class="wrap">
    <h1 class="wp-heading-inline">Gestione Tornei</h1>
    <a href="<?php echo esc_url(admin_url('admin.php?page=eto-create-tournament')); ?>" class="page-title-action">Aggiungi Nuovo</a>
    
    <hr class="wp-header-end">

    <!-- Tabella Tornei -->
    <table class="wp-list-table widefat fixed striped">
        <thead>
            <tr>
                <th scope="col">Nome</th>
                <th scope="col">Formato</th>
                <th scope="col">Data Inizio</th>
                <th scope="col">Stato</th>
                <th scope="col">Azioni</th>
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
                        <a href="<?php echo esc_url(admin_url("admin.php?page=eto-tournaments&action=edit&tournament_id=" . absint($tournament->id) . "&_wpnonce=" . wp_create_nonce('edit_tournament_' . $tournament->id))); ?>" 
                           class="button button-primary">
                            <?php esc_html_e('Modifica', 'eto'); ?>
                        </a>
                        <a href="<?php echo esc_url(wp_nonce_url(admin_url("admin.php?page=eto-tournaments&action=delete&tournament_id=" . absint($tournament->id)), 'delete_tournament_' . $tournament->id)); ?>" 
                           class="button button-danger" 
                           onclick="return confirm('<?php esc_attr_e('Eliminare definitivamente questo torneo?', 'eto'); ?>')">
                            <?php esc_html_e('Elimina', 'eto'); ?>
                        </a>
                    </td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>

    <!-- Dettagli Partite -->
    <?php if (!empty($matches)) : ?>
        <h2>Partite Recenti</h2>
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
                    $team1 = ETO_Team::get(absint($match->team1_id));
                    $team2 = $match->team2_id ? ETO_Team::get(absint($match->team2_id)) : null;
                ?>
                    <tr>
                        <td><?php echo absint($match->id); ?></td>
                        <td><?php echo $team1 ? esc_html($team1->name) : 'N/A'; ?></td>
                        <td><?php echo $team2 ? esc_html($team2->name) : 'BYE'; ?></td>
                        <td>
                            <?php if ($match->screenshot_url) : ?>
                                <a href="<?php echo esc_url($match->screenshot_url); ?>" target="_blank" rel="noopener noreferrer">
                                    <?php esc_html_e('Visualizza', 'eto'); ?>
                                </a>
                            <?php else : ?>
                                <?php esc_html_e('N/A', 'eto'); ?>
                            <?php endif; ?>
                        </td>
                        <td>
                            <a href="<?php echo esc_url(admin_url("admin.php?page=eto-matches&action=edit&match_id=" . absint($match->id) . "&_wpnonce=" . wp_create_nonce('edit_match_' . $match->id))); ?>" 
                               class="button button-primary">
                                <?php esc_html_e('Gestisci', 'eto'); ?>
                            </a>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>
</div>
