/*
Plugin Name: eSports Tournament Organizer
Description: Gestione completa di tornei eSports per League of Legends.
Version: 1.0
Author: Il Tuo Nome
*/

if (!defined('ABSPATH')) exit;

// Definisci costanti
define('ETO_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('ETO_PLUGIN_URL', plugin_dir_url(__FILE__));

// Carica i file necessari
require_once ETO_PLUGIN_DIR . 'includes/class-database.php';
require_once ETO_PLUGIN_DIR . 'includes/class-tournament.php';
require_once ETO_PLUGIN_DIR . 'includes/class-team.php';
require_once ETO_PLUGIN_DIR . 'includes/class-match.php';
require_once ETO_PLUGIN_DIR . 'includes/utilities.php';
require_once ETO_PLUGIN_DIR . 'admin/admin-pages.php';
require_once ETO_PLUGIN_DIR . 'admin/admin-ajax.php';
require_once ETO_PLUGIN_DIR . 'public/shortcodes.php';
require_once ETO_PLUGIN_DIR . 'includes/class-user-roles.php';
require_once ETO_PLUGIN_DIR . 'public/class-checkin.php';
require_once ETO_PLUGIN_DIR . 'includes/class-swiss.php';
require_once ETO_PLUGIN_DIR . 'templates/tournament-bracket.php';

// Registra hook di attivazione
register_activation_hook(__FILE__, ['ETO_Database', 'install_tables']);