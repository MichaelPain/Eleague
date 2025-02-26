<?php
if (!defined('ABSPATH')) exit;

class ETO_Riot_API {
    const API_BASE_URL = 'https://{region}.api.riotgames.com';
    const CACHE_EXPIRY = 3600; // 1 ora

    public static function get_api_key() {
        $stored_key = get_option('eto_riot_api_key', '');
        return $stored_key ? base64_decode($stored_key) : '';
    }

    private static function make_request($url, $region = 'euw1') {
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
            return new WP_Error('riot_api_error', __('Errore API Riot Games', 'eto'), $response->get_error_data());
        }

        $status_code = wp_remote_retrieve_response_code($response);
        $body = json_decode(wp_remote_retrieve_body($response), true);

        if ($status_code !== 200) {
            error_log("[ETO] Riot API Error {$status_code}: " . print_r($body, true));
            return new WP_Error(
                'riot_api_error',
                sprintf(__('Errore API (Code %d): %s', 'eto'), $status_code, wp_remote_retrieve_response_message($response)),
                $body
            );
        }

        set_transient($transient_key, $body, self::CACHE_EXPIRY);
        return $body;
    }

    public static function get_summoner($summoner_name, $region = 'euw1') {
        $summoner_name = sanitize_text_field($summoner_name);
        $url = self::API_BASE_URL . '/lol/summoner/v4/summoners/by-name/' . rawurlencode($summoner_name);
        return self::make_request(str_replace('{region}', $region, $url), $region);
    }

    public static function get_match_history($puuid, $region = 'europe', $count = 20) {
        $puuid = sanitize_key($puuid);
        $count = absint($count);
        $url = "https://{$region}.api.riotgames.com/lol/match/v5/matches/by-puuid/{$puuid}/ids?count={$count}";
        return self::make_request($url, $region);
    }

    public static function get_match_details($match_id, $region = 'europe') {
        $match_id = sanitize_text_field($match_id);
        $url = "https://{$region}.api.riotgames.com/lol/match/v5/matches/{$match_id}";
        return self::make_request($url, $region);
    }

    public static function sync_tournament_data($tournament_id) {
        $teams = ETO_Team::get_by_tournament(absint($tournament_id));
        
        foreach ($teams as $team) {
            foreach ($team->members as $member) {
                $puuid = self::get_puuid($member->user_id);
                
                if (!$puuid) {
                    error_log("[ETO] Nessun PUUID trovato per l'utente {$member->user_id}");
                    continue;
                }

                try {
                    $matches = self::get_match_history($puuid);
                    foreach ($matches as $match_id) {
                        $match_data = self::get_match_details($match_id);
                        ETO_Match::sync($tournament_id, self::format_match_data($match_data));
                    }
                } catch (Exception $e) {
                    error_log("[ETO] Errore sincronizzazione match: " . $e->getMessage());
                }
            }
        }
    }

    private static function get_puuid($user_id) {
        return sanitize_key(get_user_meta(absint($user_id), 'riot_puuid', true));
    }

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