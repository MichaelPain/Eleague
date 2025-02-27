<?php
if (!defined('ABSPATH')) exit;

class ETO_WPCLI {
    /**
     * Gestione completa dei tornei via WP-CLI
     * 
     * ## ESEMPI
     * wp eto tournament create --name="Torneo Test" --game_type=lol --max_teams=16
     * wp eto sync-teams --tournament_id=5
     * wp eto list-tournaments --status=active
     */
    public static function register_commands() {
        if (defined('WP_CLI') && WP_CLI) {
            WP_CLI::add_command('eto', new self());
        }
    }


    public function tournament($args, $assoc_args) {
        $action = $args[0] ?? 'help';
        
        switch ($action) {
            case 'create':
                $this->create_tournament($assoc_args);
                break;
                
            case 'sync-teams':
                $this->sync_teams($assoc_args);
                break;
                
            case 'list-tournaments':
                $this->list_tournaments($assoc_args);
                break;
                
            default:
                $this->show_help();
                break;
        }
    }

    /**
     * Crea un nuovo torneo
     */
    private function create_tournament($assoc_args) {
        $data = [
            'name' => sanitize_text_field($assoc_args['name'] ?? 'Nuovo Torneo'),
            'game_type' => sanitize_key($assoc_args['game_type'] ?? 'lol'),
            'max_teams' => absint($assoc_args['max_teams'] ?? 16),
            'start_date' => isset($assoc_args['start_date']) ? 
                sanitize_text_field($assoc_args['start_date']) : 
                current_time('mysql'),
            'status' => 'pending'
        ];

        try {
            $tournament_id = ETO_Tournament::create($data);
            WP_CLI::success("✅ Torneo creato con ID: $tournament_id");
        } catch (Exception $e) {
            WP_CLI::error("❌ Errore creazione torneo: " . $e->getMessage());
        }
    }

    /**
     * Sincronizza i team con le API Riot
     */
    private function sync_teams($assoc_args) {
        $tournament_id = isset($assoc_args['tournament_id']) ? 
            absint($assoc_args['tournament_id']) : 
            0;

        try {
            $count = 0;
            $tournaments = $tournament_id ? 
                [ETO_Tournament::get($tournament_id)] : 
                ETO_Tournament::get_active();

            foreach ($tournaments as $tournament) {
                $teams = ETO_Team::get_by_tournament($tournament->id);
                
                foreach ($teams as $team) {
                    $result = ETO_Riot_API::sync_team_data($team->id);
                    if ($result) $count++;
                }
            }
            
            WP_CLI::success("✅ Sincronizzati $count team");
        } catch (Exception $e) {
            WP_CLI::error("❌ Errore sincronizzazione: " . $e->getMessage());
        }
    }

    /**
     * Elenca tutti i tornei
     */
    private function list_tournaments($assoc_args) {
        $format = sanitize_key($assoc_args['format'] ?? 'table');
        $status = sanitize_key($assoc_args['status'] ?? 'all');
        
        try {
            $tournaments = ETO_Tournament::get_all($status);
            $items = [];
            
            foreach ($tournaments as $t) {
                $items[] = [
                    'ID' => $t->id,
                    'Nome' => $t->name,
                    'Gioco' => strtoupper($t->game_type),
                    'Team' => $t->team_count,
                    'Stato' => ucfirst($t->status),
                    'Inizio' => date_i18n('d/m/Y H:i', strtotime($t->start_date))
                ];
            }
            
            WP_CLI\Utils\format_items($format, $items, ['ID', 'Nome', 'Gioco', 'Team', 'Stato', 'Inizio']);
        } catch (Exception $e) {
            WP_CLI::error("❌ Errore recupero tornei: " . $e->getMessage());
        }
    }

    /**
     * Mostra guida comandi
     */
    private function show_help() {
        WP_CLI::line("📖 Comandi disponibili:\n");
        
        $commands = [
            'tournament create' => [
                '--name="Nome Torneo"',
                '--game_type=lol|dota|cs',
                '--max_teams=32',
                '[--start_date=YYYY-MM-DD]'
            ],
            'tournament sync-teams' => [
                '[--tournament_id=ID]'
            ],
            'tournament list-tournaments' => [
                '[--status=pending|active|completed]',
                '[--format=table|json|csv]'
            ]
        ];

        foreach ($commands as $cmd => $params) {
            WP_CLI::line("▪ wp eto $cmd");
            foreach ($params as $param) {
                WP_CLI::line("   -- $param");
            }
            WP_CLI::line('');
        }
    }
}

// Registra i comandi solo in ambiente WP-CLI
if (defined('WP_CLI') && WP_CLI) {
    WP_CLI::add_command('eto', 'ETO_WPCLI');
}
ETO_WPCLI::register_commands();
