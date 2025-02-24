jQuery(document).ready(function($) {
    // Conferma risultato partita
    $('.eto-confirm-match').on('click', function(e) {
        e.preventDefault();

        const button = $(this);
        const matchId = button.data('match-id');
        const nonce = button.data('nonce');

        if (confirm('Sei sicuro di voler confermare questa partita?')) {
            $.ajax({
                url: etoAdminVars.ajaxurl,
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
                        alert('Errore: ' + (response.data ? response.data.message : 'Impossibile completare l\'azione.'));
                    }
                },
                error: function() {
                    alert('Errore durante la richiesta. Riprova più tardi.');
                }
            });
        }
    });

    // Elimina team
    $('.eto-delete-team').on('click', function(e) {
        e.preventDefault();

        const button = $(this);
        const teamId = button.data('team-id');
        const nonce = etoAdminVars.nonce;

        if (confirm(etoAdminVars.confirmDelete)) {
            $.ajax({
                url: etoAdminVars.ajaxurl,
                type: 'POST',
                data: {
                    action: 'eto_delete_team',
                    team_id: teamId,
                    nonce: nonce
                },
                success: function(response) {
                    if (response.success) {
                        alert('Team eliminato con successo!');
                        button.closest('tr').fadeOut();
                    } else {
                        alert('Errore: ' + (response.data ? response.data.message : 'Impossibile completare l\'azione.'));
                    }
                },
                error: function() {
                    alert('Errore durante la richiesta. Riprova più tardi.');
                }
            });
        }
    });
});
