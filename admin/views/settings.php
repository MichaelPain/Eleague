<?php
if (!defined('ABSPATH')) exit;

// Verifica permessi utente
if (!current_user_can('manage_eto_settings')) {
    wp_die(__('Accesso negato', 'eto'));
}

// Caricamento impostazioni
$settings = get_option('eto_riot_api_key', [
    'riot_api' => '',
    'email_enabled' => true
]);

// Salvataggio impostazioni
if (isset($_POST['submit'])) {
    check_admin_referer('eto_save_settings', '_wpnonce');
    
    // **Crittografia chiave API**
    $api_key = sanitize_text_field($_POST['eto_riot_api_key']);
    update_option('eto_riot_api_key', base64_encode($api_key));
    
    // Aggiornamento altre opzioni
    update_option('eto_email_enabled', isset($_POST['email_enabled']) ? 1 : 0);
    
    // **Feedback utente**
    eto_redirect_with_message(
        admin_url('admin.php?page=eto-settings'),
        esc_html__('Impostazioni salvate!', 'eto')
    );
    exit;
}
?>

<div class="eto-settings-container">
    <form method="post" action="<?php echo admin_url('admin-post.php?action=eto_save_settings'); ?>">
        <?php wp_nonce_field('eto_save_settings', '_wpnonce'); ?>
        
        <div class="eto-settings-section">
            <h3><?php esc_html_e('API Riot Games', 'eto'); ?></h3>
            <p><?php esc_html_e('Inserisci la tua API Key Riot Games:', 'eto'); ?></p>
            <input type="password" 
                   name="eto_riot_api_key" 
                   value="<?php echo esc_attr(base64_decode($settings['riot_api'])); ?>" 
                   placeholder="<?php esc_attr_e('Chiave API', 'eto'); ?>">
        </div>

        <div class="eto-settings-section">
            <h3><?php esc_html_e('Notifiche Email', 'eto'); ?></h3>
            <label>
                <input type="checkbox" 
                       name="email_enabled" 
                       <?php checked($settings['email_enabled'], 1); ?>>
                <?php esc_html_e('Abilita notifiche email', 'eto'); ?>
            </label>
        </div>

        <p class="submit">
            <input type="submit" 
                   name="submit" 
                   class="button button-primary" 
                   value="<?php esc_attr_e('Salva Impostazioni', 'eto'); ?>">
        </p>
    </form>
</div>
