<?php
class ETO_Riot_API {
    const API_BASE_URL = 'https://{region}.api.riotgames.com';
    const CACHE_EXPIRY = 3600; // 1 ora
    private static $api_key;

    public static function init() {
        add_action('admin_init', [__CLASS__, 'verify_api_key']);
    }

    /**
     * Ottieni la chiave API dal database
     */
    private static function get_api_key() {
        if (!self::$api_key) {
            self::$api_key = get_option('eto_riot_api_key', '');
        }
        return self::$api_key;
    }

    /**
     * Verifica validità della chiave API
     */
    public static function verify_api_key() {
        $key = self::get_api_key();
        if (empty($key)) return false;

        $test_url = str_replace('{region}', 'euw1', self::API_BASE_URL) . '/lol/platform/v3/champion-rotations';
        $response = self::make_request($test_url);

        if (is_wp_error($response) || wp_remote_retrieve_response_code($response) !== 200) {
            error_log('[ETO] Riot API Key Verification Failed');
            return false;
        }

        return true;
    }

    /**
     * Richiesta generica con cache e gestione errori
     */
    public static function make_request($url, $region = 'euw1') {
        $url = str_replace('{region}', sanitize_text_field($region), $url);
        $transient_key = 'eto_riot_' . md5($url);
        $cached = get_transient($transient_key);

        if ($cached !== false) {
            return $cached;
        }

        $args = [
            'headers' => [
                'X-Riot-Token' => self::get_api_key(),
                'Content-Type' => 'application/json'
            ],
            'timeout' => 15
        ];

        $response = wp_remote_get(esc_url_raw($url), $args);

        if (is_wp_error($response)) {
            error_log('[ETO] Riot API Error: ' . $response->get_error_message());
            return $response;
        }

        $body = json_decode(wp_remote_retrieve_body($response), true);
        $status_code = wp_remote_retrieve_response_code($response);

        if ($status_code !== 200) {
            error_log("[ETO] Riot API Error {$status_code}: " . print_r($body, true));
            return new WP_Error('riot_api_error', __('Errore API Riot Games', 'eto'), $body);
        }

        set_transient($transient_key, $body, self::CACHE_EXPIRY);
        return $body;
    }

    /**
     * Ottieni dati summoner
     */
    public static function get_summoner($summoner_name, $region = 'euw1') {
        $summoner_name = sanitize_text_field($summoner_name);
        $url = self::API_BASE_URL . '/lol/summoner/v4/summoners/by-name/' . rawurlencode($summoner_name);
        return self::make_request($url, $region);
    }

    /**
     * Ottieni match history
     */
    public static function get_match_history($puuid, $region = 'europe', $count = 20) {
        $puuid = sanitize_key($puuid);
        $count = absint($count);
        $url = "https://{$region}.api.riotgames.com/lol/match/v5/matches/by-puuid/{$puuid}/ids?count={$count}";
        return self::make_request($url, $region);
    }

    /**
     * Ottieni dettagli match
     */
    public static function get_match_details($match_id, $region = 'europe') {
        $match_id = sanitize_text_field($match_id);
        $url = "https://{$region}.api.riotgames.com/lol/match/v5/matches/{$match_id}";
        return self::make_request($url, $region);
    }

    /**
     * Sincronizza dati con il torneo
     */
    public static function sync_tournament_data($tournament_id) {
        $teams = ETO_Team::get_by_tournament(absint($tournament_id));
        
        foreach ($teams as $team) {
            foreach ($team->members as $member) {
                $puuid = self::get_puuid($member->user_id);
                if ($puuid) {
                    // Logica di sincronizzazione migliorata
                }
            }
        }
    }

    /**
     * Ottieni PUUID da meta utente
     */
    private static function get_puuid($user_id) {
        return get_user_meta(absint($user_id), 'riot_puuid', true);
    }

    /**
     * Formatta i dati del match per il plugin
     */
    public static function format_match_data($match_data) {
        return [
            'match_id' => sanitize_text_field($match_data['metadata']['matchId']),
            'participants' => array_map('sanitize_text_field', $match_data['metadata']['participants']),
            'teams' => array_map(function($team) {
                return [
                    'win' => (bool)$team['win'],
                    'objectives' => array_map('absint', $team['objectives'])
                ];
            }, $match_data['info']['teams'])
        ];
    }
}
