// In public/enqueue-scripts.php
wp_enqueue_script('jquery-bracket', ETO_PLUGIN_URL . 'assets/js/jquery.bracket.min.js', ['jquery'], '0.11.0', true);

function eto_enqueue_public_scripts() {
    // Bracket
    wp_enqueue_script(
        'jquery-bracket',
        ETO_PLUGIN_URL . 'assets/js/jquery.bracket.min.js',
        ['jquery'],
        '0.11.0',
        true
    );

    wp_enqueue_script(  
        'eto-checkin',  
        ETO_PLUGIN_URL . 'assets/js/checkin.js',  
        ['jquery'],  
        '1.0',  
        true  
    );  
    wp_localize_script('eto-checkin', 'etoCheckin', [  
        'ajaxurl' => admin_url('admin-ajax.php'),  
        'nonce' => wp_create_nonce('eto_checkin_nonce')  
    ]);  

    // Stili custom
    wp_enqueue_style(
        'eto-frontend',
        ETO_PLUGIN_URL . 'assets/css/frontend.css',
        [],
        '1.0'
    ); 
    wp_register_style(  
        'tournament-bracket',  
        ETO_PLUGIN_URL . 'assets/css/tournament-bracket.css',  
        [],  
        '1.0'  
    );  
}  
}
add_action('wp_enqueue_scripts', 'eto_enqueue_public_scripts');
