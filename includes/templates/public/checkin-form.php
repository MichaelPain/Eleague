<?php
/**
 * Template per il form di check-in
 */
if (!isset($team_id) || !isset($tournament)) {
    echo '<div class="eto-error">Errore: dati mancanti per il check-in.</div>';
    return;
}

// Controlla se il check-in è aperto
if (!eto_checkin_open($tournament->id)) {
    echo '<div class="eto-notice">Il check-in non è attualmente disponibile. Torna più tardi.</div>';
    return;
}
?>
<div class="eto-checkin-form">
    <h2>Check-in per il torneo: <?php echo esc_html($tournament->name); ?></h2>
    <p>Team: <strong><?php echo esc_html(ETO_Team::get($team_id)->name); ?></strong></p>

    <form method="post">
        <?php wp_nonce_field('eto_checkin_action', 'eto_checkin_nonce'); ?>
        <input type="hidden" name="team_id" value="<?php echo esc_attr($team_id); ?>">
        <button type="submit" name="eto_checkin_submit" class="button-primary">Effettua Check-in</button>
    </form>
</div>
