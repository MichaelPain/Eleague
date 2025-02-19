add_action('wp_ajax_eto_confirm_match', 'eto_confirm_match_callback');
function eto_confirm_match_callback() {
    $match_id = intval($_POST['match_id']);
    ETO_Match::confirm_match($match_id);
    wp_send_json_success(['message' => 'Match confermato!']);
}

add_action('wp_ajax_eto_process_checkin', function() {  
    check_ajax_referer('eto_checkin_nonce', 'security');  
    if (!current_user_can('eto_manage_tournaments')) {  
        wp_send_json_error('Accesso negato');  
    }  
    // Logica check-in  
});  