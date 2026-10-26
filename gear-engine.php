<?php
/**
 * Plugin Name:       Gear Engine
 * Plugin URI:        https://github.com/AreMartin/gear-engine
 * Description:       Core engine plugin for WordPress.
 * Version:           1.0.0
 * Requires at least: 6.0
 * Requires PHP:      7.4
 * Author:            Are Martin Kallåk
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       gear-engine
 * Domain Path:       /languages
 *
 * @package GearEngine
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly.
}

// Define plugin constants.
define( 'GEAR_ENGINE_VERSION', '1.0.0' );
define( 'GEAR_ENGINE_FILE', __FILE__ );
define( 'GEAR_ENGINE_PATH', plugin_dir_path( __FILE__ ) );
define( 'GEAR_ENGINE_URL', plugin_dir_url( __FILE__ ) );

/**
 * Initialize the plugin.
 *
 * @return void
 */
function gear_engine_init() {
    load_plugin_textdomain( 'gear-engine', false, dirname( plugin_basename( __FILE__ ) ) . '/languages' );
}
add_action( 'init', 'gear_engine_init' );
