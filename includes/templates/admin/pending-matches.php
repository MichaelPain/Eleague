<?php
/**
 * Template per la gestione delle partite in attesa di conferma (Admin)
 */
$pending_matches = ETO_Match::get_pending();
?>
<div class="wrap">
    <h1>Partite in Attesa di Conferma</h1>
    <?php if (!empty($pending_matches)): ?>
        <table class="wp-list-table widefat fixed striped">
            <thead>
                <tr>
                    <th>ID Partita</th>
                    <th>Team 1</th>
                    <th>Team 2</th>
                    <th>Screenshot</th>
                    <th>Azioni</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($pending_matches as $match): ?>
                    <tr>
                        <td><?php echo esc_html($match->id); ?></td>
                        <td><?php echo esc_html(ETO_Team::get($match->team1_id)->name); ?></td>
                        <td><?php echo $match->team2_id ? esc_html(ETO_Team::get($match->team2_id)->name) : 'BYE'; ?></td>
                        <td>
                            <?php if ($match->screenshot_url): ?>
                                <a href="<?php echo esc_url($match->screenshot_url); ?>" target="_blank">Visualizza</a>
                            <?php else: ?>
                                N/A
                            <?php endif; ?>
                        </td>
                        <td>
                            <button class="button-primary eto-confirm-match"
                                    data-match-id="<?php echo esc_attr($match->id); ?>"
                                    data-nonce="<?php echo wp_create_nonce('confirm_match_' . $match->id); ?>">
                                Conferma
                            </button>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    <?php else: ?>
        <p>Nessuna partita in attesa di conferma.</p>
    <?php endif; ?>
</div>

<script type="text/javascript">
    jQuery(document).ready(function($) {
        $('.eto-confirm-match').on('click', function() {
            const button = $(this);
            const matchId = button.data('match-id');
            const nonce = button.data('nonce');

            if (confirm('Sei sicuro di voler confermare questa partita?')) {
                $.ajax({
                    url: ajaxurl,
                    type: 'POST',
                    data: {
                        action: 'eto_confirm_match',
                        match_id: matchId,
                        nonce: nonce
                    },
                    success: function(response) {
                        if (response.success) {
                            alert('Partita confermata con successo!');
                            button.closest('tr').fadeOut();
                        } else {
                            alert('Errore: ' + response.data.message);
                        }
                    },
                    error: function() {
                        alert('Errore durante la richiesta. Riprova più tardi.');
                    }
                });
            }
        });
    });
</script>
