/**
 * Gestione delle funzionalità JavaScript per l'amministrazione
 * @version 2.4.1
 */

jQuery(document).ready(function($) {
    'use strict';

    // Variabili globali
    const ajaxurl = etoAdminData.ajax_url || '/wp-admin/admin-ajax.php';
    const nonce = etoAdminData.nonce;

    // Inizializzazione Datepicker
    function initDatePickers() {
        $('.eto-datepicker').datetimepicker({
            dateFormat: 'yy-mm-dd',
            timeFormat: 'HH:mm',
            controlType: 'select',
            oneLine: true
        });
    }

    // Conferma partita
    $(document).on('click', '.eto-confirm-match', function(e) {
        e.preventDefault();
        
        const button = $(this);
        const matchId = button.data('match-id');
        const nonce = button.data('nonce');

        if (!matchId || !nonce) {
            alert('Dati mancanti per la conferma');
            return;
        }

        button.prop('disabled', true).text('Conferma in corso...');

        $.post(ajaxurl, {
            action: 'eto_confirm_match',
            match_id: matchId,
            nonce: nonce
        }, function(response) {
            if (response.success) {
                button.closest('tr').fadeOut(300, function() {
                    $(this).remove();
                });
            } else {
                alert(response.data.message || 'Errore durante la conferma');
            }
        }).fail(function() {
            alert('Errore di comunicazione con il server');
        }).always(function() {
            button.prop('disabled', false).text('Conferma');
        });
    });

    // Eliminazione torneo
    $(document).on('click', '.eto-delete-tournament', function(e) {
        e.preventDefault();
        
        if (!confirm('Sei sicuro di voler eliminare questo torneo?')) return;

        const tournamentId = $(this).data('tournament-id');
        
        $.post(ajaxurl, {
            action: 'eto_delete_tournament',
            tournament_id: tournamentId,
            nonce: nonce
        }, function(response) {
            if (response.success) {
                window.location.reload();
            } else {
                alert(response.data.message || 'Errore durante l\'eliminazione');
            }
        });
    });

    // Gestione form creazione torneo
    $('#eto-tournament-form').on('submit', function(e) {
        e.preventDefault();
        
        const form = $(this);
        const submitButton = form.find('button[type="submit"]');
        const formData = form.serialize();

        submitButton.prop('disabled', true).text('Salvataggio...');

        $.post(ajaxurl, formData, function(response) {
            if (response.success) {
                window.location.href = response.data.redirect;
            } else {
                alert(response.data.message || 'Errore durante il salvataggio');
            }
        }).fail(function() {
            alert('Errore di comunicazione con il server');
        }).always(function() {
            submitButton.prop('disabled', false).text('Crea torneo');
        });
    });

    // Inizializzazioni al caricamento
    initDatePickers();

    // Gestione errori globale
    $(document).ajaxError(function(event, jqxhr, settings, thrownError) {
        console.error('Errore AJAX:', settings.url, thrownError);
        alert('Si è verificato un errore durante l\'operazione');
    });
});
