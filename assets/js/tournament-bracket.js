jQuery(document).ready(function($) {
    // Inizializzazione bracket con controllo dati
    if (window.etoBracketData) {
        $('#bracket').bracket({
            init: window.etoBracketData
        });
    } else {
        console.error('Dati bracket non trovati');
    }
});

// Aggiunta logica di aggiornamento dinamico
const updateInterval = setInterval(() => {
    fetch('/wp-json/eto/v1/bracket/' + window.etoBracketId)
        .then(response => response.json())
        .then(data => {
            if (data && Array.isArray(data.matches)) {
                $('#bracket').bracket({ init: data });
            } else {
                console.warn('Dati bracket non validi durante aggiornamento');
            }
        })
        .catch(error => console.error('Errore aggiornamento bracket:', error));
}, 30000);
