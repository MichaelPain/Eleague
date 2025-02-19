public function generate_round($tournament_id) {
    $teams = ETO_Tournament::get_standings($tournament_id);
    $matcher = new SwissPairing($teams);
    return $matcher->pair();
}