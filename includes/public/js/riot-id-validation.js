jQuery(document).ready(function($) {
    // Validazione Riot#ID in tempo reale
    $('#riot_id').on('input', function() {
        const riotId = $(this).val();
        const regex = /^[a-zA-Z0-9]+#[a-zA-Z0-9]+$/;

        // Controlla se il Riot#ID è valido
        if (!regex.test(riotId)) {
            $(this).addClass('eto-invalid');
            if (!$('#riot-id-error').length) {
                $(this).after('<span id="riot-id-error" class="eto-error">Formato non valido. Esempio: Player123#EUW</span>');
            }
        } else {
            $(this).removeClass('eto-invalid');
            $('#riot-id-error').remove();
        }
    });
});