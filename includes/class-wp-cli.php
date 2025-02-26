<?php
if (!defined('ABSPATH')) exit;

if (!class_exists('WP_CLI')) return;

/**
 * Gestione comandi WP-CLI per Esports Tournament Organizer
 */
class ETO_WPCLI {
    /**
     * Registra i comandi WP-CLI
     */
    public static function register_commands() {
        WP_CLI::add_command('eto', [__CLASS__, 'handle']);
    }

    /**
     * Gestore principale dei comandi
     * @param array $args Argomenti posizionali
     * @param array $assoc_args Argomenti associativi
     */
    public static function handle($args, $assoc_args) {
        if (empty($args)) {
            WP_CLI::error('Specificare un comando valido. Usare "wp eto help" per la lista.');
            return;
        }

        switch ($args[0]) {
            case 'create-tournament':
                self::create_tournament(array_slice($args, 1), $assoc_args);
                break;
            
            case 'sync-teams':
                self::sync_teams($assoc_args);
                break;
            
            case 'list-tournaments':
                self::list_tournaments($assoc_args);
                break;
            
            case 'help':
                self::show_help();
                break;
            
            default:
                WP_CLI::error("Comando non riconosciuto: {$args[0]}");
        }
    }

    /**
     * Crea un nuovo torneo via CLI
     */
    private static function create_tournament($args, $assoc_args) {
        $required = ['name', 'game_type', 'max_teams'];
        
        foreach ($required as $param) {
            if (!isset($assoc_args[$param])) {
                WP_CLI::error("Parametro mancante: --{$param}");
                return;
            }
        }

        $data = [
            'name' => sanitize_text_field($assoc_args['name']),
            'game_type' => sanitize_key($assoc_args['game_type']),
            'max_teams' => absint($assoc_args['max_teams']),
            'start_date' => isset($assoc_args['start_date']) ? sanitize_text_field($assoc_args['start_date']) : current_time('mysql'),
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
    private static function sync_teams($assoc_args) {
        $tournament_id = isset($assoc_args['tournament_id']) ? absint($assoc_args['tournament_id']) : 0;
        
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
    private static function list_tournaments($assoc_args) {
        $format = isset($assoc_args['format']) ? $assoc_args['format'] : 'table';
        $status = isset($assoc_args['status']) ? sanitize_key($assoc_args['status']) : 'all';

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
    }

    /**
     * Mostra guida comandi
     */
    private static function show_help() {
        WP_CLI::line("📖 Comandi disponibili:\n");

        $commands = [
            'create-tournament' => [
                '--name="Nome Torneo"',
                '--game_type=lol|dota|cs',
                '--max_teams=32',
                '[--start_date=YYYY-MM-DD]'
            ],
            'sync-teams' => [
                '[--tournament_id=ID]'
            ],
            'list-tournaments' => [
                '[--status=pending|active|completed]',
                '[--format=table|json|csv]'
            ]
        ];

        foreach ($commands as $cmd => $params) {
            WP_CLI::line("▪ wp eto $cmd");
            foreach ($params as $param) {
                WP_CLI::line("   └ $param");
            }
            WP_CLI::line('');
        }
    }
}

// Registra i comandi
WP_CLI::add_command('eto', 'ETO_WPCLI');
