<?php
/**
 * Template per il profilo utente
 */
$user_id = get_current_user_id();
$user_teams = ETO_Team::get_user_teams($user_id);
$tournaments = eto_get_user_tournaments($user_id);
?>
<div class="eto-user-profile">
    <h2>Profilo Utente</h2>
    <form method="post">
        <label for="riot_id">Riot ID:</label>
        <input type="text" id="riot_id" name="riot_id" value="<?php echo esc_attr(get_user_meta($user_id, 'riot_id', true)); ?>" required>
        
        <label for="discord_tag">Discord Tag:</label>
        <input type="text" id="discord_tag" name="discord_tag" value="<?php echo esc_attr(get_user_meta($user_id, 'discord_tag', true)); ?>">

        <label for="nationality">Nazionalità:</label>
        <input type="text" id="nationality" name="nationality" value="<?php echo esc_attr(get_user_meta($user_id, 'nationality', true)); ?>">

        <?php wp_nonce_field('eto_update_profile', 'eto_nonce'); ?>
        <button type="submit">Aggiorna Profilo</button>
    </form>

    <h3>Il Tuo Team</h3>
    <?php if (!empty($user_teams)): ?>
        <ul>
            <?php foreach ($user_teams as $team): ?>
                <li>
                    <?php echo esc_html($team->name); ?>
                    <?php if (ETO_Team::is_captain($user_id, $team->id)): ?>
                        <a href="#" class="eto-delete-team" data-team-id="<?php echo esc_attr($team->id); ?>">Elimina Team</a>
                    <?php endif; ?>
                </li>
            <?php endforeach; ?>
        </ul>
    <?php else: ?>
        <p>Non sei in nessun team. Creane uno ora!</p>
    <?php endif; ?>

    <h3>Storico Tornei</h3>
    <?php if (!empty($tournaments)): ?>
        <ul>
            <?php foreach ($tournaments as $tournament): ?>
                <li><?php echo esc_html($tournament->name); ?> (<?php echo esc_html($tournament->status); ?>)</li>
            <?php endforeach; ?>
        </ul>
    <?php else: ?>
        <p>Non hai partecipato a nessun torneo.</p>
    <?php endif; ?>
</div>
