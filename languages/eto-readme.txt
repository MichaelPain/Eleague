Questo file è pronto per essere utilizzato con strumenti come Poedit o il comando WP CLI:

bash
wp i18n make-pot . languages/eto.pot --exclude=node_modules,vendor,tests --domain=eto


Utilizzo:

Traduci il file .pot in .po e .mo per la tua lingua (esempio eto-it_IT.po e eto-it_IT.mo).

Posiziona i file tradotti nella directory languages.

Compatibilità:

Il file include tutti i messaggi di testo che utilizzano le funzioni di traduzione di WordPress (__(), _e(), ecc.).