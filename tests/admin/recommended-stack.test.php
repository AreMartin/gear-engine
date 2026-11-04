<?php
/**
 * Recommended stack tests for GeneratePress and GenerateBlocks installer.
 * Run with: php tests/admin/recommended-stack.test.php
 */

define( 'ABSPATH', __DIR__ . '/../../' );
define( 'GEAR_ENGINE_PATH', ABSPATH );
define( 'GEAR_ENGINE_URL', 'http://example.com/wp-content/plugins/gear-engine/' );
define( 'GEAR_ENGINE_VERSION', '1.0.0' );
define( 'GEAR_ENGINE_TESTING', true );
define( 'WP_PLUGIN_DIR', sys_get_temp_dir() . '/wp-plugins-mock-' . uniqid() );

mkdir( WP_PLUGIN_DIR, 0777, true );

$GLOBALS['active_theme']     = '';
$GLOBALS['active_plugins']   = array();
$GLOBALS['installed_themes'] = array();

function esc_html( $s ) { return htmlspecialchars( (string) $s, ENT_QUOTES ); }
function esc_attr( $s ) { return esc_html( $s ); }
function esc_url( $s ) { return esc_html( $s ); }
function esc_html__( $s ) { return esc_html( $s ); }
function __( $s ) { return $s; }
function current_user_can() { return true; }
function is_wp_error( $v ) { return is_object( $v ) && method_exists( $v, 'get_error_message' ); }
function get_template() { return $GLOBALS['active_theme']; }
function get_stylesheet() { return $GLOBALS['active_theme']; }
function is_plugin_active( $file ) { return in_array( $file, $GLOBALS['active_plugins'], true ); }
function get_option( $k, $d = false ) { return 'active_plugins' === $k ? $GLOBALS['active_plugins'] : $d; }
function switch_theme( $slug ) { $GLOBALS['active_theme'] = $slug; }
function activate_plugin( $file ) {
	if ( ! in_array( $file, $GLOBALS['active_plugins'], true ) ) {
		$GLOBALS['active_plugins'][] = $file;
	}
	return null;
}

function wp_get_theme( $slug = '' ) {
	$exists = isset( $GLOBALS['installed_themes'][ $slug ] );
	return new class( $exists, $slug ) {
		private $exists;
		private $slug;
		public function __construct( $exists, $slug ) { $this->exists = $exists; $this->slug = $slug; }
		public function exists() { return $this->exists; }
		public function get( $key ) { return 'Version' === $key ? '3.5.0' : ''; }
	};
}

require_once ABSPATH . 'modules/admin/class-gear-engine-recommended-stack.php';

$failures = 0;
function t( $name, $ok ) {
	global $failures;
	echo ( $ok ? '[PASS] ' : '[FAIL] ' ) . $name . "\n";
	if ( ! $ok ) {
		$failures++;
	}
}

// 1. Initial State: Neither installed
$status = Gear_Engine_Recommended_Stack::get_status();
t( 'Theme not installed initially', false === $status['theme']['installed'] );
t( 'Theme not active initially', false === $status['theme']['active'] );
t( 'Plugin not installed initially', false === $status['plugin']['installed'] );
t( 'Plugin not active initially', false === $status['plugin']['active'] );
t( 'Stack incomplete initially', false === $status['complete'] );

// 2. Install GeneratePress mock
$GLOBALS['installed_themes']['generatepress'] = true;
$status_after_theme_install = Gear_Engine_Recommended_Stack::get_status();
t( 'Theme detected as installed', true === $status_after_theme_install['theme']['installed'] );
t( 'Theme version detected', '3.5.0' === $status_after_theme_install['theme']['version'] );
t( 'Theme not active yet', false === $status_after_theme_install['theme']['active'] );

// 3. Install GenerateBlocks mock
$gb_dir = WP_PLUGIN_DIR . '/generateblocks';
mkdir( $gb_dir, 0777, true );
file_put_contents( $gb_dir . '/plugin.php', "<?php\n/**\n * Plugin Name: GenerateBlocks\n * Version: 2.0.0\n */\n" );

$status_after_plugin_install = Gear_Engine_Recommended_Stack::get_status();
t( 'Plugin detected as installed on disk', true === $status_after_plugin_install['plugin']['installed'] );
t( 'Plugin not active yet', false === $status_after_plugin_install['plugin']['active'] );

// 4. Run install_and_activate() with installed files
$res = Gear_Engine_Recommended_Stack::install_and_activate();
t( 'Activation succeeds without error', true === $res['success'] );
t( 'GeneratePress switched to active', 'generatepress' === $GLOBALS['active_theme'] );
t( 'GenerateBlocks activated', in_array( 'generateblocks/plugin.php', $GLOBALS['active_plugins'], true ) );

// 5. Final Status: Both active
$final_status = Gear_Engine_Recommended_Stack::get_status();
t( 'Final theme is active', true === $final_status['theme']['active'] );
t( 'Final plugin is active', true === $final_status['plugin']['active'] );
t( 'Stack is marked complete', true === $final_status['complete'] );

// Clean up mock directory
@unlink( $gb_dir . '/plugin.php' );
@rmdir( $gb_dir );
@rmdir( WP_PLUGIN_DIR );

if ( $failures > 0 ) {
	echo "\n$failures test(s) failed.\n";
	exit( 1 );
} else {
	echo "\nAll recommended stack tests passed!\n";
	exit( 0 );
}

