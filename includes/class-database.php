class ETO_Database {
    public static function install_tables() {
        global $wpdb;
        $charset_collate = $wpdb->get_charset_collate();
        
        // Tabella Tornei
        $table_tournaments = $wpdb->prefix . 'eto_tournaments';
        $sql = "CREATE TABLE $table_tournaments (
            id MEDIUMINT(9) NOT NULL AUTO_INCREMENT,
            name VARCHAR(255) NOT NULL,
            start_date DATETIME NOT NULL,
            end_date DATETIME NOT NULL,
            format VARCHAR(50) NOT NULL,
            status VARCHAR(20) DEFAULT 'pending',
            min_players INT(3) NOT NULL,
            max_players INT(3) NOT NULL,
            PRIMARY KEY (id)
        ) $charset_collate;";

        // Tabella Teams (altre tabelle simili)
        // ... [codice per teams, team_members, matches]
$table_checkins = $wpdb->prefix . 'eto_checkins';
$sql = "CREATE TABLE $table_checkins (
    id MEDIUMINT(9) NOT NULL AUTO_INCREMENT,
    user_id BIGINT(20) NOT NULL,
    tournament_id MEDIUMINT(9) NOT NULL,
    checked_in_at DATETIME NOT NULL,
    PRIMARY KEY (id)
) $charset_collate;";
dbDelta($sql);
        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql);
    }
}

public function get_team_members($team_id) {
    global $wpdb;
    
    return $wpdb->get_results(
        $wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}eto_team_members 
            WHERE team_id = %d 
            AND is_captain = 0",
            $team_id
        )
    );
}