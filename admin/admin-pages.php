function eto_admin_tournaments_page() {
    ?>
    <div class="wrap">
        <h1>Gestione Tornei</h1>
        <table class="wp-list-table widefat fixed striped">
            <thead>
                <tr>
                    <th>Nome</th>
                    <th>Data Inizio</th>
                    <th>Stato</th>
                    <th>Azioni</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach (ETO_Tournament::get_all() as $tournament) : ?>
                <tr>
                    <td><?php echo esc_html($tournament->name); ?></td>
                    <td><?php echo date('d/m/Y H:i', strtotime($tournament->start_date)); ?></td>
                    <td><?php echo ucfirst($tournament->status); ?></td>
                    <td>
                        <a href="<?php echo admin_url('admin.php?page=eto-edit-tournament&id=' . $tournament->id); ?>">Modifica</a>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php
}

function eto_tournaments_admin_page() {
    if (!current_user_can('eto_manage_tournaments')) {
        wp_die(__('Accesso negato', 'eto-plugin'));
    }
    
    // Solo utenti autorizzati vedono questa pagina
}

add_settings_field(
    'enable_third_place',
    'Abilita finale 3°/4° posto',
    'checkbox_callback',
    'eto_tournament_settings',
    'eto_section_general',
    ['label_for' => 'enable_third_place']
);

// File: admin/admin-pages.php
$matches = $wpdb->get_results("SELECT * FROM {$wpdb->prefix}eto_matches WHERE confirmed = 0");
foreach ($matches as $match) {
    echo "<img src='{$match->screenshot_path}' data-match-id='{$match->id}'>";
    echo "<button class='confirm-match' data-nonce='" . wp_create_nonce('confirm_match_' . $match->id) . "'>Conferma</button>";
}

function eto_checkin_admin_page() {  
    echo '<h2>Check-In in Sospeso</h2>';  
    global $wpdb;  
    $checkins = $wpdb->get_results("SELECT * FROM {$wpdb->prefix}eto_checkins WHERE confirmed = 0");  
    // Tabella con richieste check-in  
}  