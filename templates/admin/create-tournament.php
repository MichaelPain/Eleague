<?php
/**
 * Template per la creazione di un nuovo torneo (Admin)
 */
?>
<div class="wrap">
    <h1>Crea Nuovo Torneo</h1>
    <form method="post" action="<?php echo admin_url('admin-post.php'); ?>">
        <input type="hidden" name="action" value="eto_create_tournament">
        <?php wp_nonce_field('eto_create_tournament'); ?>

        <table class="form-table">
            <tr>
                <th><label for="tournament_name">Nome Torneo</label></th>
                <td><input type="text" name="tournament_name" id="tournament_name" class="regular-text" required></td>
            </tr>
            <tr>
                <th><label for="format">Formato</label></th>
                <td>
                    <select name="format" id="format" required>
                        <option value="single_elimination">Single Elimination</option>
                        <option value="double_elimination">Double Elimination</option>
                        <option value="swiss">Swiss System</option>
                    </select>
                </td>
            </tr>
            <tr>
                <th><label for="start_date">Data Inizio</label></th>
                <td><input type="datetime-local" name="start_date" id="start_date" required></td>
            </tr>
            <tr>
                <th><label for="end_date">Data Fine</label></th>
                <td><input type="datetime-local" name="end_date" id="end_date" required></td>
            </tr>
            <tr>
                <th><label for="min_players">Membri Minimi per Team</label></th>
                <td><input type="number" name="min_players" id="min_players" min="1" max="10" required></td>
            </tr>
            <tr>
                <th><label for="max_players">Membri Massimi per Team</label></th>
                <td><input type="number" name="max_players" id="max_players" min="1" max="10" required></td>
            </tr>
            <tr>
                <th><label for="checkin_enabled">Check-in Obbligatorio</label></th>
                <td><input type="checkbox" name="checkin_enabled" id="checkin_enabled"></td>
            </tr>
        </table>

        <?php submit_button('Crea Torneo'); ?>
    </form>
</div>
