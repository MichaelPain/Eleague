jQuery(document).ready(function($) {
    // Inizializza il bracket per ogni elemento con classe "bracket"
    $('.bracket').each(function() {
        var bracketData = $(this).data('bracket'); // I dati del bracket sono passati tramite data-bracket
        if (bracketData) {
            $(this).bracket({
                init: bracketData,
                // Puoi aggiungere qui ulteriori opzioni se necessario, ad esempio:
                // save: function(data, userData) { console.log(data); }
            });
        }
    });

    // Eventuali ulteriori funzionalità per la pagina del torneo
    // Ad esempio, gestione dinamica di aggiornamenti del bracket o notifiche.
    
    // (Extra) Se esiste un pulsante per aggiornare il bracket, si potrebbe implementare qui:
    /*
    $('#refresh-bracket').on('click', function() {
        location.reload();
    });
    */
});
