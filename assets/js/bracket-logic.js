(function($) {
    // Logica personalizzata per il rendering del bracket
    $(document).ready(function() {
        $('.bracket').each(function() {
            var bracketData = $(this).data('bracket'); // Dati del bracket passati come attributo data
            if (bracketData) {
                $(this).bracket({
                    init: bracketData, // Inizializza il bracket con i dati
                    save: function(data, userData) {
                        // Funzione di callback per salvare eventuali modifiche (se necessario)
                        console.log("Bracket aggiornato:", data);
                    }
                });
            } else {
                console.warn("Nessun dato trovato per il rendering del bracket.");
            }
        });
    });
})(jQuery);
