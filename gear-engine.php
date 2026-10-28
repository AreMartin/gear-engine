<?php
/**
 * Plugin Name:       Gear Affiliate Engine
 * Plugin URI:        https://github.com/AreMartin/gear-engine
 * Description:       Ultimate affiliate marketing engine for exercise equipment with AI personalization.
 * Version:           1.0.0
 * Requires at least: 6.0
 * Requires PHP:      8.0
 * Author:            Elite WP Engineer
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
define( 'GAE_VERSION', '1.0.0' );
define( 'GAE_PLUGIN_FILE', __FILE__ );
define( 'GAE_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'GAE_PLUGIN_URL', plugin_dir_url( __FILE__ ) );

// Backwards-compatible constants matching project conventions.
define( 'GEAR_ENGINE_VERSION', GAE_VERSION );
define( 'GEAR_ENGINE_FILE', GAE_PLUGIN_FILE );
define( 'GEAR_ENGINE_PATH', GAE_PLUGIN_DIR );
define( 'GEAR_ENGINE_URL', GAE_PLUGIN_URL );

// Require core modular classes.
require_once GAE_PLUGIN_DIR . 'includes/class-gae-settings.php';
require_once GAE_PLUGIN_DIR . 'includes/class-gae-cpt.php';
require_once GAE_PLUGIN_DIR . 'includes/class-gae-sync-engine.php';
require_once GAE_PLUGIN_DIR . 'includes/class-gae-gutenberg-block.php';
require_once GAE_PLUGIN_DIR . 'includes/class-gae-ai-api.php';
require_once GAE_PLUGIN_DIR . 'includes/class-gae-product-service.php';
require_once GAE_PLUGIN_DIR . 'modules/exercises/class-gear-engine-exercises-cpt.php';
require_once GAE_PLUGIN_DIR . 'modules/exercises/class-gear-engine-protocols.php';
require_once GAE_PLUGIN_DIR . 'modules/exercises/class-gear-engine-protocol-manager.php';
require_once GAE_PLUGIN_DIR . 'modules/exercises/class-gear-engine-exercise-service.php';
require_once GAE_PLUGIN_DIR . 'modules/exercises/class-gear-engine-exercise-importer.php';

// Require feature modules.
require_once GAE_PLUGIN_DIR . 'modules/physio-chat/class-gae-physio-chat.php';

/**
 * Initialize the Gear Affiliate Engine plugin.
 *
 * @return void
 */
function gae_init_plugin(): void {
	// Load textdomain.
	load_plugin_textdomain( 'gear-engine', false, dirname( plugin_basename( __FILE__ ) ) . '/languages' );

	// Instantiate core modules.
	new GAE_Settings();
	new GAE_CPT();
	new GAE_Sync_Engine();
	new GAE_Gutenberg_Block();
	new GAE_AI_API();
	new Gear_Engine_Exercises_CPT();
	new Gear_Engine_Protocols();
	new Gear_Engine_Protocol_Manager();
	new Gear_Engine_Exercise_Importer();

	// Instantiate feature modules.
	new GAE_Physio_Chat();
	new Gear_Engine_Exercise_Selection();
}
add_action( 'plugins_loaded', 'gae_init_plugin' );

/**
 * Plugin activation hook callback.
 *
 * @return void
 */
function gae_activate_plugin(): void {
	// Register post type for initial rewrite rule flush.
	require_once GAE_PLUGIN_DIR . 'includes/class-gae-cpt.php';
	$cpt = new GAE_CPT();
	$cpt->register_post_type();
	$exercise_cpt = new Gear_Engine_Exercises_CPT();
	$exercise_cpt->register_content_types();
	$protocols = new Gear_Engine_Protocols();
	$protocols->register_taxonomy();
	Gear_Engine_Exercises_CPT::seed_sample_exercises();
	flush_rewrite_rules();

	// Seed 2 sample test products for immediate Gutenberg block testing.
	GAE_CPT::seed_sample_products();
	update_option( 'gae_sample_products_seeded', 1, false );

	// Schedule daily sync WP-Cron job if not already scheduled.
	if ( ! wp_next_scheduled( 'gae_daily_sync_event' ) ) {
		wp_schedule_event( time(), 'daily', 'gae_daily_sync_event' );
	}
}
register_activation_hook( __FILE__, 'gae_activate_plugin' );

/**
 * Plugin deactivation hook callback.
 *
 * @return void
 */
function gae_deactivate_plugin(): void {
	// Clear scheduled cron events.
	$timestamp = wp_next_scheduled( 'gae_daily_sync_event' );
	if ( $timestamp ) {
		wp_unschedule_event( $timestamp, 'gae_daily_sync_event' );
	}
	wp_clear_scheduled_hook( 'gae_daily_sync_event' );

	flush_rewrite_rules();
}
register_deactivation_hook( __FILE__, 'gae_deactivate_plugin' );
