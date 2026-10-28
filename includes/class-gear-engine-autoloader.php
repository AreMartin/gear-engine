<?php
/**
 * Class autoloader for Gear Engine.
 *
 * Maps every plugin class to its file so feature classes are loaded on
 * demand instead of through manual require chains with implicit ordering.
 *
 * @package GearEngine
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

/**
 * Build the class-to-file map for the whole plugin.
 *
 * @return array<string,string> Map of class name to absolute file path.
 */
function gear_engine_class_map(): array {
	$base = GEAR_ENGINE_PATH;

	$map = array(
		'Gear_Engine_Settings'           => 'includes/class-gear-engine-settings.php',
		'Gear_Engine_CPT'                => 'includes/class-gear-engine-cpt.php',
		'Gear_Engine_Sync_Engine'        => 'includes/class-gear-engine-sync-engine.php',
		'Gear_Engine_Sync_Rules'         => 'includes/class-gear-engine-sync-rules.php',
		'Gear_Engine_Product_Upscaler'   => 'includes/class-gear-engine-product-upscaler.php',
		'Gear_Engine_Gutenberg_Block'    => 'includes/class-gear-engine-gutenberg-block.php',
		'Gear_Engine_AI_API'             => 'includes/class-gear-engine-ai-api.php',
		'Gear_Engine_Product_Service'    => 'includes/class-gear-engine-product-service.php',
		'Gear_Engine_Config_Loader'      => 'includes/class-gear-engine-config-loader.php',
		'Gear_Engine_Physio_Chat'        => 'modules/physio-chat/class-gear-engine-physio-chat.php',
		'Gear_Engine_Chat_Agent'         => 'modules/physio-chat/class-gear-engine-chat-agent.php',
		'Gear_Engine_Exercise_Selection' => 'modules/physio-chat/class-gear-engine-exercise-selection.php',
		'Gear_Engine_Exercises_CPT'      => 'modules/exercises/class-gear-engine-exercises-cpt.php',
		'Gear_Engine_Protocols'          => 'modules/exercises/class-gear-engine-protocols.php',
		'Gear_Engine_Protocol_Manager'   => 'modules/exercises/class-gear-engine-protocol-manager.php',
		'Gear_Engine_Exercise_Service'   => 'modules/exercises/class-gear-engine-exercise-service.php',
		'Gear_Engine_Exercise_Importer'  => 'modules/exercises/class-gear-engine-exercise-importer.php',
		'Gear_Engine_Protocol_Viewer'    => 'modules/exercises/class-gear-engine-protocol-viewer.php',
		'Gear_Engine_Triage'             => 'modules/triage/class-gear-engine-triage.php',
		'Gear_Engine_Gear_Picker'        => 'modules/gear-picker/class-gear-engine-gear-picker.php',
		'Gear_Engine_Picker_Diagnostics' => 'modules/gear-picker/class-gear-engine-picker-diagnostics.php',
		'Gear_Engine_Shortcode_Guide'    => 'modules/admin/class-gear-engine-shortcode-guide.php',
	);

	// Legacy GAE_ aliases resolve to the same files; each class file creates
	// the alias itself, so third-party code referencing old names keeps working.
	$aliases = array(
		'GAE_Settings'        => 'Gear_Engine_Settings',
		'GAE_CPT'             => 'Gear_Engine_CPT',
		'GAE_Sync_Engine'     => 'Gear_Engine_Sync_Engine',
		'GAE_Gutenberg_Block' => 'Gear_Engine_Gutenberg_Block',
		'GAE_AI_API'          => 'Gear_Engine_AI_API',
		'GAE_Product_Service' => 'Gear_Engine_Product_Service',
		'GAE_Physio_Chat'     => 'Gear_Engine_Physio_Chat',
		'GAE_Chat_Agent'      => 'Gear_Engine_Chat_Agent',
	);

	$resolved = array();
	foreach ( $map as $class => $file ) {
		$resolved[ $class ] = $base . $file;
	}
	foreach ( $aliases as $alias => $class ) {
		$resolved[ $alias ] = $map[ $class ] ? $base . $map[ $class ] : '';
	}

	return $resolved;
}

/**
 * Register the plugin class autoloader.
 *
 * @return void
 */
function gear_engine_register_autoloader(): void {
	spl_autoload_register(
		static function ( string $class ): void {
			static $map = null;
			if ( null === $map ) {
				$map = gear_engine_class_map();
			}
			if ( isset( $map[ $class ] ) && is_readable( $map[ $class ] ) ) {
				require_once $map[ $class ];
			}
		}
	);
}
