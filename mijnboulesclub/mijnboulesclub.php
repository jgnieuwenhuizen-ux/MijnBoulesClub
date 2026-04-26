<?php
/**
 * Plugin Name:       MijnBoulesClub
 * Plugin URI:        https://github.com/jgnieuwenhuizen-ux/MijnBoulesClub
 * Description:       Plugin voor het beheren van een Jeu de Boules vereniging (leden, competitie, toernooien).
 * Version:           0.3.0
 * Requires at least: 6.0
 * Requires PHP:      8.1
 * Author:            Gerard Nieuwenhuizen
 * Author URI:        https://github.com/jgnieuwenhuizen-ux
 * License:           GPL v2 or later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       mijnboulesclub
 * Domain Path:       /languages
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'MBC_VERSION',  '0.3.0' );
define( 'MBC_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'MBC_PLUGIN_URL', plugin_dir_url( __FILE__ ) );

// Core classes – always loaded.
require_once MBC_PLUGIN_DIR . 'includes/core/class-mbc-database.php';
require_once MBC_PLUGIN_DIR . 'includes/core/class-mbc-loader.php';
require_once MBC_PLUGIN_DIR . 'includes/modules/members/class-mbc-members.php';
require_once MBC_PLUGIN_DIR . 'public/class-mbc-members-shortcode.php';

// Admin classes – only in admin context (keeps frontend lean).
if ( is_admin() ) {
	require_once MBC_PLUGIN_DIR . 'includes/modules/members/class-mbc-members-admin.php';
}

// Create database tables on first activation.
register_activation_hook( __FILE__, static function (): void {
	$db = new MBC_Database();
	$db->create_tables();
} );

// Bootstrap plugin after all plugins are loaded.
add_action( 'plugins_loaded', static function (): void {
	$loader = new MBC_Loader();
	$loader->run();
} );
