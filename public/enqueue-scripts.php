<?php
defined('ABSPATH') || exit;

// Carica la classe Ajax Handler
require_once ETO_PLUGIN_DIR . 'includes/class-ajax-handler.php';

// Registra script e stili per il frontend
function eto_enqueue_public_assets() {
    // CSS Frontend
    wp_enqueue_style(
        'eto-public',
        plugins_url('assets/css/public.css', ETO_PLUGIN_DIR),
        array(),
        filemtime(ETO_PLUGIN_DIR . 'assets/css/public.css')
    );

    // JS Frontend
    wp_register_script(
        'eto-public',
        plugins_url('assets/js/public.js', ETO_PLUGIN_DIR),
        array('jquery'),
        filemtime(ETO_PLUGIN_DIR . 'assets/js/public.js'),
        true
    );

    // Localizza variabili per JavaScript
    wp_localize_script('eto-public', 'eto_ajax', array(
        'ajaxurl' => admin_url('admin-ajax.php'),
        'nonce' => ETO_Ajax_Handler::generate_nonce()
    ));

    wp_enqueue_script('eto-public');
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
        array('jquery', 'wp-util', 'wp-i18n'),
        filemtime(ETO_PLUGIN_DIR . 'assets/js/admin.js'),
        true
    );

    // Localizza variabili per JavaScript in admin
    wp_localize_script('eto-admin', 'etoAdminVars', array(
        'ajaxurl' => admin_url('admin-ajax.php'),
        'nonce' => ETO_Ajax_Handler::generate_nonce(),
        'confirmDelete' => __('Sei sicuro di voler eliminare questo elemento?', 'eto')
    ));

    // Abilita la traduzione degli script
    wp_set_script_translations('eto-admin', 'eto', ETO_PLUGIN_DIR . 'languages/');
}
add_action('admin_enqueue_scripts', 'eto_enqueue_admin_assets');
