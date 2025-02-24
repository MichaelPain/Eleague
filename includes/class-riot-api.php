<?php
class ETO_Riot_API {
    private $api_key;
    private $region;

    public function __construct($region = 'euw1') {
        $this->api_key = get_option('eto_riot_api_key');
        $this->region = sanitize_text_field($region);
    }

    // Verifica validità Riot#ID
    public function validate_riot_id($game_name, $tag_line) {
        $url = "https://{$this->region}.api.riotgames.com/riot/account/v1/accounts/by-riot-id/{$game_name}/{$tag_line}";
        $response = $this->make_request($url);

        if (is_wp_error($response)) {
            return $response;
        }

        return $response->httpStatus === 200;
    }

    // Ottieni stats giocatore
    public function get_player_stats($puuid) {
        $url = "https://{$this->region}.api.riotgames.com/lol/league/v4/entries/by-summoner/{$puuid}";
        return $this->make_request($url);
    }

    // Request generica
    private function make_request($url) {
        $args = [
            'headers' => ['X-Riot-Token' => $this->api_key],
            'timeout' => 10
        ];

        $response = wp_remote_get($url, $args);

        if (is_wp_error($response)) {
            return $response;
        }

        $status = wp_remote_retrieve_response_code($response);
        $body = json_decode(wp_remote_retrieve_body($response));

        switch ($status) {
            case 200:
                return $body;
            case 403:
                return new WP_Error('invalid_key', 'API Key non valida');
            case 404:
                return new WP_Error('not_found', 'Giocatore non trovato');
            default:
                return new WP_Error('api_error', 'Errore API Riot: ' . ($body->status->message ?? 'Sconosciuto'));
        }
    }

    // Aggiorna API Key
    public static function update_api_key($new_key) {
        update_option('eto_riot_api_key', sanitize_text_field($new_key));
    }
}
