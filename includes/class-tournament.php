class ETO_Tournament {
    public function __construct() {
        add_action('init', [$this, 'register_post_type']);
    }

    public function register_post_type() {
        register_post_type('tournament', [
            'labels' => [
                'name' => 'Tornei',
                'singular_name' => 'Torneo'
            ],
            'public' => true,
            'has_archive' => true,
            'supports' => ['title', 'editor', 'custom-fields']
        ]);
    }
}
new ETO_Tournament();

public function generate_bracket($teams) {
    $bracket = [];
    $rounds = log(count($teams), 2);
    for ($i = 0; $i < $rounds; $i++) {
        $matches = [];
        foreach (array_chunk($teams, 2) as $pair) {
            $matches[] = [
                'team1' => $pair[0],
                'team2' => $pair[1] ?? 'BYE'
            ];
        }
        $bracket[] = $matches;
        $teams = array_fill(0, count($teams)/2, null); // Simula vincitori
    }
    return $bracket;
}