<?php
if (!defined('ABSPATH')) exit;

// Verifica permessi e nonce
if (!current_user_can('manage_options')) {
    wp_die(__('Accesso negato.', 'eto'));
}

$options = get_option('eto_plugin_settings');
?>

<div class="wrap">
    <h1><?php esc_html_e('Impostazioni Plugin Tornei', 'eto'); ?></h1>
    
    <form method="post" action="options.php">
        <?php 
        settings_fields('eto_settings_group');
        do_settings_sections('eto_settings_page');
        wp_nonce_field('eto_settings_action', '_eto_settings_nonce');
        submit_button(); 
        ?>
    </form>

    <!-- Sezione avanzata per API -->
    <div class="card">
        <h2><?php esc_html_e('Configurazione API', 'eto'); ?></h2>
        <table class="form-table">
            <tr>
                <th><label for="eto_riot_api"><?php esc_html_e('Riot API Key', 'eto'); ?></label></th>
                <td>
                    <input type="password" id="eto_riot_api" name="eto_plugin_settings[riot_api]" 
                        value="<?php echo esc_attr(base64_decode($options['riot_api'] ?? '')); ?>" 
                        class="regular-text">
                    <p class="description"><?php esc_html_e('Usa una chiave valida cifrata in base64', 'eto'); ?></p>
                </td>
            </tr>
        </table>
    </div>
</div>