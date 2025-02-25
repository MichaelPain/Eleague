<div class="wrap">
    <h1><?php esc_html_e('Crea Nuovo Torneo', 'eto'); ?></h1>
    
    <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
        <input type="hidden" name="action" value="eto_create_tournament">
        <?php wp_nonce_field('eto_create_tournament_nonce', 'eto_nonce'); ?>
        
        <table class="form-table">
            <tbody>
                <tr>
                    <th scope="row">
                        <label for="tournament_name"><?php esc_html_e('Nome Torneo', 'eto'); ?></label>
                    </th>
                    <td>
                        <input type="text" name="tournament_name" id="tournament_name" 
                               class="regular-text" required>
                    </td>
                </tr>
                
                <tr>
                    <th scope="row">
                        <label for="format"><?php esc_html_e('Formato', 'eto'); ?></label>
                    </th>
                    <td>
                        <select name="format" id="format" class="regular-text" required>
                            <option value="single_elimination"><?php esc_html_e('Eliminazione Diretta', 'eto'); ?></option>
                            <option value="double_elimination"><?php esc_html_e('Doppia Eliminazione', 'eto'); ?></option>
                            <option value="round_robin"><?php esc_html_e('Girone All\'Italiana', 'eto'); ?></option>
                        </select>
                    </td>
                </tr>
                
                <tr>
                    <th scope="row">
                        <label for="start_date"><?php esc_html_e('Data Inizio', 'eto'); ?></label>
                    </th>
                    <td>
                        <input type="datetime-local" name="start_date" id="start_date" 
                               class="regular-text" required>
                    </td>
                </tr>
            </tbody>
        </table>
        
        <?php submit_button(__('Crea Torneo', 'eto')); ?>
    </form>
</div>