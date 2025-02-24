<?php
// Registra script e stili per il frontend
function eto_enqueue_public_assets() {
    // CSS Frontend
    wp_enqueue_style(
        'eto-public',
        plugins_url('assets/css/frontend.css', ETO_PLUGIN_DIR),
        array(),
        filemtime(ETO_PLUGIN_DIR . 'assets/css/frontend.css')
    );

    // JS Frontend principale
    wp_enqueue_script(
        'eto-public',
        plugins_url('assets/js/tournament-public.js', ETO_PLUGIN_DIR),
        array('jquery'),
        filemtime(ETO_PLUGIN_DIR . 'assets/js/tournament-public.js'),
        true
    );

    // Includi la libreria jQuery Bracket (minificata)
    wp_enqueue_script(
        'jquery-bracket',
        plugins_url('assets/js/jquery.bracket.min.js', ETO_PLUGIN_DIR),
        array('jquery'),
        '2.0.0', // Aggiorna con la versione corretta se necessario
        true
    );

    // Includi il file di logica personalizzata per il bracket
    wp_enqueue_script(
        'bracket-logic',
        plugins_url('assets/js/bracket-logic.js', ETO_PLUGIN_DIR),
        array('jquery', 'jquery-bracket'),
        '1.0.0', // Versione personalizzata
        true
    );

    // Localizza variabili per JavaScript nel frontend
    wp_localize_script('eto-public', 'etoVars', array(
        'ajaxurl' => admin_url('admin-ajax.php'),
        'nonce'   => wp_create_nonce('eto_public_nonce')
    ));
}
add_action('wp_enqueue_scripts', 'eto_enqueue_public_assets');


// Registra script e stili per l'area admin
function eto_enqueue_admin_assets($hook) {
    // Carica gli asset solo nelle pagine del plugin (es. con prefisso "eto-")
    if (strpos($hook, 'eto-') === false) {
        return;
    }

    // CSS Admin
    wp_enqueue_style(
        'eto-admin',
        plugins_url('assets/css/admin.css', ETO_PLUGIN_DIR),
        array(),
        filemtime(ETO_PLUGIN_DIR . 'assets/css/admin.css')
    );

    // JS Admin
    wp_enqueue_script(
        'eto-admin',
        plugins_url('assets/js/admin.js', ETO_PLUGIN_DIR),
        array('jquery', 'wp-util'),
        filemtime(ETO_PLUGIN_DIR . 'assets/js/admin.js'),
        true
    );

    // Localizza variabili per JavaScript in admin
    wp_localize_script('eto-admin', 'etoAdminVars', array(
        'ajaxurl' => admin_url('admin-ajax.php'),
        'confirmDelete' => __('Sei sicuro di voler eliminare questo elemento?', 'eto')
    ));
}
add_action('admin_enqueue_scripts', 'eto_enqueue_admin_assets');
?>
