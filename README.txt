# eSports Tournament Organizer (ELeague) 🏆

**Versione Attuale:** 2.5.1  
**Compatibilità:** WordPress 6.4+ | PHP 7.4+  
**Licenza:** GPLv3  
[![Code Status](https://img.shields.io/badge/Stabilità-Alpha-orange)]()

---

## 📌 Funzionalità Principali
- Creazione e gestione tornei (Eliminazione singola/doppia, Sistema Svizzero)
- Gestione team con limite di membri (3-6 giocatori)
- Sistema di matchmaking automatico
- Audit log per tutte le azioni amministrative
- Integrazione API Riot Games (in sviluppo)

---

## 🛠 Installazione
1. **Scarica l'ultima versione** dalla [sezione Releases](https://github.com/MichaelPain/Eleague/releases)
2. **Installa via WordPress Admin**
   - Vai in *Plugin > Aggiungi Nuovo > Carica Plugin*
   - Carica il file ZIP
3. **Attiva** il plugin

**Installazione Manuale:**
cd wp-content/plugins
git clone https://github.com/MichaelPain/Eleague.git
chmod -R 755 Eleague

---

## 📦 Changelog (v2.5.1)

### 🚀 Nuove Funzionalità
- Supporto iniziale per tornei a doppia eliminazione
- Widget leaderboard base

### 🐛 Fix Critici
- **Database Schema:**
  - Aggiunto `ENGINE=InnoDB` a tutte le tabelle
  - Corretti indici mancanti su `eto_matches`
- **Sicurezza:**
  - Sanitizzazione input in `class-tournament.php`
  - Aggiunti nonce check per le azioni AJAX

### ⚠️ Note Importanti
- Il sistema Svizzero è in fase beta (max 5 round)
- L'integrazione con Riot API richiede chiave sviluppatore

---

## 🔜 Roadmap 2025

### Priorità Alta 🔴
- **Sicurezza:**
  - Implementazione completa di `$wpdb->prepare()`
  - Aggiunta controlli CSRF in tutte le form
- **Funzionalità Core:**
  - Sistema di tiebreaker per tornei Swiss
  - Notifiche email per check-in giocatori

### Priorità Media 🟡
- Multisite support
- Shortcode avanzati per visualizzazione bracket
- Esportazione risultati in CSV/JSON

---

## 🛡 Troubleshooting

**Problemi Comuni:**
// Abilita debug in wp-config.php
define('WP_DEBUG', true);
define('WP_DEBUG_LOG', true);


**Errori Database:**
1. Disinstalla il plugin
2. Cancella manualmente le tabelle:
DROP TABLE wp_eto_tournaments, wp_eto_teams, wp_eto_matches, wp_eto_audit_logs;
3. Reinstalla

---

## 🤝 Contributi

**Linee Guida:**
1. Fork del repository
2. Crea branch dedicato (`feature/nome-feature`)
3. Esegui test via [WP Local Docker](https://github.com/10up/wp-local-docker)
4. Invia PR con:
- Descrizione dettagliata
- Screenshot delle modifiche
- Test effettuati

**Struttura Cartelle:**
/Eleague
├──/admin # Funzionalità backend
├──/includes # Logica core
├──/public # Frontend e assets
├──/tests # Test suite (in sviluppo)
└──/templates # Template personalizzati

---

## 📄 Licenza
Questo plugin è rilasciato sotto licenza **GPLv3**. Per uso commerciale o personalizzazioni avanzate, contattare lo sviluppatore.

[![Licenza GPLv3](https://img.shields.io/badge/License-GPLv3-blue.svg)](https://www.gnu.org/licenses/gpl-3.0)

---

> **Nota per gli Sviluppatori:**  
> Prima di aggiornare il plugin in produzione, testare SEMPRE le migrazioni del database in ambiente staging.