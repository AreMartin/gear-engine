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
define( 'GEAR_ENGINE_VERSION', '1.0.0' );
define( 'GEAR_ENGINE_FILE', __FILE__ );
define( 'GEAR_ENGINE_PATH', plugin_dir_path( __FILE__ ) );
define( 'GEAR_ENGINE_URL', plugin_dir_url( __FILE__ ) );

// Deprecated aliases for early third-party integrations.
define( 'GAE_VERSION', GEAR_ENGINE_VERSION );
define( 'GAE_PLUGIN_FILE', GEAR_ENGINE_FILE );
define( 'GAE_PLUGIN_DIR', GEAR_ENGINE_PATH );
define( 'GAE_PLUGIN_URL', GEAR_ENGINE_URL );

// All plugin classes load on demand through this map; no manual requires.
require_once GEAR_ENGINE_PATH . 'includes/class-gear-engine-autoloader.php';
gear_engine_register_autoloader();

/**
 * Register stylesheets shared by several modules.
 *
 * @return void
 */
function gear_engine_register_shared_assets(): void {
	wp_register_style( 'gear-engine-tokens', GEAR_ENGINE_URL . 'assets/css/gear-engine-tokens.css', array(), GEAR_ENGINE_VERSION );
}

/**
 * Default set of enabled modules, in load order.
 *
 * @return string[] Class names instantiated on init.
 */
function gear_engine_default_modules(): array {
	return array(
		// Core.
		'Gear_Engine_Settings',
		'Gear_Engine_CPT',
		'Gear_Engine_Sync_Engine',
		'Gear_Engine_Gutenberg_Block',
		'Gear_Engine_AI_API',
		// Exercise library.
		'Gear_Engine_Exercises_CPT',
		'Gear_Engine_Protocols',
		'Gear_Engine_Protocol_Manager',
		'Gear_Engine_Protocol_Autofill',
		'Gear_Engine_Coverage_Report',
		'Gear_Engine_Exercise_Importer',
		'Gear_Engine_Protocol_Viewer',
		// Feature modules.
		'Gear_Engine_Physio_Chat',
		'Gear_Engine_Triage',
		'Gear_Engine_Gear_Picker',
		'Gear_Engine_Picker_Diagnostics',
		'Gear_Engine_Shortcode_Guide',
		'Gear_Engine_Exercise_Selection',
	);
}

/**
 * Initialize the Gear Affiliate Engine plugin.
 *
 * @return void
 */
function gear_engine_init_plugin(): void {
	// Load textdomain.
	load_plugin_textdomain( 'gear-engine', false, dirname( plugin_basename( __FILE__ ) ) . '/languages' );

	// Instantiate enabled modules. Remove entries via the
	// gear_engine_modules filter to disable a module on a per-site basis.
	$modules = apply_filters( 'gear_engine_modules', gear_engine_default_modules() );
	foreach ( (array) $modules as $module ) {
		if ( is_string( $module ) && class_exists( $module ) ) {
			new $module();
		}
	}

	add_action( 'wp_enqueue_scripts', 'gear_engine_register_shared_assets', 1 );
}
add_action( 'plugins_loaded', 'gear_engine_init_plugin' );

/**
 * Option names that are only ever read by admin screens, WP-Cron sync runs
 * or on-demand REST requests. Keeping them out of the autoload payload stops
 * WordPress from loading them on every front-end page view.
 *
 * @return string[] Option names.
 */
function gear_engine_admin_only_options(): array {
	return array(
		'gae_feeds',
		'gae_adtraction_url',
		'gae_tradetracker_url',
		'gae_google_ai_key',
		'gae_google_places_key',
		'gae_ai_model',
		'gae_physio_custom_prompt',
		'gae_allowed_categories',
		'gae_allowed_merchants',
		'gae_excluded_keywords',
		'gae_excluded_merchants',
		'gae_required_keywords',
		'gae_max_items_per_sync',
		'gae_max_scan_items',
		'gae_max_total_products',
		'gae_min_price',
		'gae_max_price',
		'gae_min_discount_pct',
		'gae_only_in_stock',
	);
}

/**
 * Keep the admin-only options out of the autoload payload permanently.
 *
 * Rows are flipped with a direct autoload update because update_option()
 * short-circuits when the stored value is unchanged. Runs on every admin
 * screen load but costs one small indexed query once the options are clean.
 *
 * @return void
 */
function gear_engine_optimize_option_autoload(): void {
	global $wpdb;

	$names      = gear_engine_admin_only_options();
	$in         = implode( ',', array_fill( 0, count( $names ), '%s' ) );
	$still_hot  = $wpdb->get_col(
		$wpdb->prepare(
			"SELECT option_name FROM {$wpdb->options} WHERE autoload = 'yes' AND option_name IN ($in)",
			...$names
		)
	);

	if ( empty( $still_hot ) ) {
		return;
	}

	foreach ( $still_hot as $name ) {
		$wpdb->update(
			$wpdb->options,
			array( 'autoload' => 'no' ),
			array( 'option_name' => $name )
		);
	}

	wp_cache_delete( 'alloptions', 'options' );
	wp_cache_delete( 'notoptions', 'options' );
}
add_action( 'admin_init', 'gear_engine_optimize_option_autoload', 20 );

/**
 * Plugin activation hook callback.
 *
 * @return void
 */
function gear_engine_activate_plugin(): void {
	// Register post types for initial rewrite rule flush.
	$cpt = new Gear_Engine_CPT();
	$cpt->register_post_type();
	$exercise_cpt = new Gear_Engine_Exercises_CPT();
	$exercise_cpt->register_content_types();
	$protocols = new Gear_Engine_Protocols();
	$protocols->register_taxonomy();
	Gear_Engine_Exercises_CPT::seed_sample_exercises();
	flush_rewrite_rules();

	// Seed 2 sample test products for immediate Gutenberg block testing.
	Gear_Engine_CPT::seed_sample_products();
	update_option( 'gae_sample_products_seeded', 1, false );

	// Schedule daily sync WP-Cron job if not already scheduled.
	if ( ! wp_next_scheduled( 'gae_daily_sync_event' ) ) {
		wp_schedule_event( time(), 'daily', 'gae_daily_sync_event' );
	}
}
register_activation_hook( __FILE__, 'gear_engine_activate_plugin' );

/**
 * Plugin deactivation hook callback.
 *
 * @return void
 */
function gear_engine_deactivate_plugin(): void {
	// Clear scheduled cron events.
	$timestamp = wp_next_scheduled( 'gae_daily_sync_event' );
	if ( $timestamp ) {
		wp_unschedule_event( $timestamp, 'gae_daily_sync_event' );
	}
	wp_clear_scheduled_hook( 'gae_daily_sync_event' );
	wp_clear_scheduled_hook( 'gae_sync_continue_event' );

	flush_rewrite_rules();
}
register_deactivation_hook( __FILE__, 'gear_engine_deactivate_plugin' );
