<?php
/**
 * Template per la visualizzazione del torneo
 */
if (!isset($tournament)) {
    echo '<div class="eto-error">Torneo non trovato.</div>';
    return;
}

$teams = ETO_Team::get_tournament_teams($tournament->id);
$matches = ETO_Match::get_tournament_matches($tournament->id);
?>
<div class="eto-tournament-view">
    <h2><?php echo esc_html($tournament->name); ?></h2>
    <p><strong>Formato:</strong> <?php echo ucfirst(str_replace('_', ' ', $tournament->format)); ?></p>
    <p><strong>Stato:</strong> <?php echo esc_html($tournament->status); ?></p>
    <p><strong>Data Inizio:</strong> <?php echo eto_format_date($tournament->start_date); ?></p>
    <p><strong>Data Fine:</strong> <?php echo eto_format_date($tournament->end_date); ?></p>

    <h3>Team Registrati</h3>
    <?php if (!empty($teams)): ?>
        <ul>
            <?php foreach ($teams as $team): ?>
                <li><?php echo esc_html($team->name); ?> (Capitano: <?php echo esc_html(get_userdata($team->captain_id)->display_name); ?>)</li>
            <?php endforeach; ?>
        </ul>
    <?php else: ?>
        <p>Nessun team registrato.</p>
    <?php endif; ?>

    <h3>Bracket</h3>
    <?php if (!empty($matches)): ?>
        <div class="bracket" data-bracket='<?php echo json_encode(ETO_Tournament::generate_bracket_data($matches)); ?>'></div>
    <?php else: ?>
        <p>Il bracket non è ancora disponibile.</p>
    <?php endif; ?>
</div>
