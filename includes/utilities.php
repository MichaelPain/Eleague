function eto_team_locked($team_id) {
    global $wpdb;
    return $wpdb->get_var(
        $wpdb->prepare(
            "SELECT status FROM {$wpdb->prefix}eto_tournaments 
            WHERE id = (SELECT tournament_id FROM {$wpdb->prefix}eto_teams WHERE id = %d)",
            $team_id
        )
    ) === 'active';
}