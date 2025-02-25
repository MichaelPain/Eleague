jQuery(document).ready(function($) {
    // Verifica se l'utente è il capitano prima di consentire l'azione
    $('.eto-upload-result').on('submit', function(e) {
        e.preventDefault();

        const form = $(this);
        const teamId = form.find('input[name="team_id"]').val();

        // Effettua la chiamata AJAX per verificare se l'utente è il capitano
        $.ajax({
            url: etoVars.ajaxurl,
            type: 'POST',
            data: {
                action: 'eto_verify_captain',
                team_id: teamId,
                nonce: etoVars.nonce
            },
            success: function(response) {
                if (response.success && response.data.is_captain) {
                    // Se l'utente è il capitano, consente l'invio del form
                    form.off('submit').submit();
                } else {
                    alert('Solo il capitano può caricare i risultati!');
                }
            },
            error: function() {
                alert('Errore durante la verifica. Riprova più tardi.');
            }
        });
    });
});
