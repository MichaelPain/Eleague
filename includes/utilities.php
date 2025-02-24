<?php
/**
 * Funzioni di utilità globali
 * @package eSports Tournament Organizer
 * @since 2.1.0
 */

if (!function_exists('eto_generate_bracket_html')) {
    /**
     * Genera HTML strutturato per i bracket
     */
    function eto_generate_bracket_html($matches) {
        $html = '<div class="eto-bracket-container">';
        
        foreach ($matches as $match) {
            $team1_name = esc_html($match->team1->name ?? __('Team 1', 'eto'));
            $team2_name = esc_html($match->team2->name ?? __('Team 2', 'eto'));
            $status_class = sanitize_html_class($match->status);

            $html .= sprintf(
                '<div class="match %s" data-match-id="%d">
                    <div class="team">%s</div>
                    <div class="vs">vs</div>
                    <div class="team">%s</div>
                </div>',
                $status_class,
                absint($match->id),
                $team1_name,
                $team2_name
            );
        }

        $html .= '</div>';
        return $html;
    }
}

if (!function_exists('eto_send_json_response')) {
    /**
     * Invia una risposta JSON standardizzata
     */
    function eto_send_json_response($data, $status = 200) {
        status_header($status);
        header('Content-Type: application/json');
        echo json_encode([
            'success' => $status < 400,
            'data' => $data,
            'nonce' => wp_create_nonce('eto_ajax_nonce')
        ]);
        exit;
    }
}

if (!function_exists('eto_redirect')) {
    /**
     * Redirect sicuro con controllo permessi
     */
    function eto_redirect($url, $capability = 'manage_options') {
        if (current_user_can($capability)) {
            wp_safe_redirect(esc_url_raw($url));
            exit;
        }
        wp_die(__('Permessi insufficienti', 'eto'));
    }
}

if (!function_exists('eto_clean_array')) {
    /**
     * Sanitizza array multidimensionale
     */
    function eto_clean_array($data) {
        if (is_array($data)) {
            return array_map('eto_clean_array', $data);
        }
        return is_scalar($data) ? sanitize_text_field($data) : null;
    }
}
