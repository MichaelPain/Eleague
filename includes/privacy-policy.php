<?php
/**
 * Template per la Privacy Policy del plugin eSports Tournament Organizer.
 * Questo file mostra la politica della privacy a supporto del GDPR.
 */

/**
 * Aggiunge la sezione della privacy policy del plugin al contenuto della pagina di default di WordPress.
 *
 * @param string $content Il contenuto della privacy policy di WordPress.
 * @return string Il contenuto aggiornato con la sezione relativa al plugin.
 */
function eto_privacy_policy_content($content) {
    $plugin_content  = '<h2>Privacy Policy - eSports Tournament Organizer</h2>';
    $plugin_content .= '<p>Il plugin eSports Tournament Organizer raccoglie e gestisce dati relativi ai tornei e agli utenti, inclusi:</p>';
    $plugin_content .= '<ul>';
    $plugin_content .= '<li><strongDati personali:</strong> Nome utente, email, Riot ID, Discord Tag.</li>';
    $plugin_content .= '<li><strongDati relativi ai team:</strong> Nome del team, membri registrati, stato del check-in.</li>';
    $plugin_content .= '<li><strongDati relativi ai tornei:</strong> Risultati delle partite, screenshot caricati per verificare i risultati.</li>';
    $plugin_content .= '</ul>';
    $plugin_content .= '<p>I dati raccolti sono utilizzati esclusivamente per il funzionamento del plugin e non vengono condivisi con terze parti, salvo obblighi di legge.</p>';
    $plugin_content .= '<p>Gli utenti possono richiedere la cancellazione dei propri dati contattando l\'amministratore del sito.</p>';

    return $content . $plugin_content;
}
add_filter('wp_get_default_privacy_policy_content', 'eto_privacy_policy_content');
?>
