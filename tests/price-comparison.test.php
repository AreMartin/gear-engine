<?php
/**
 * Price comparison unit tests.
 * Run with: C:\php\php.exe tests/price-comparison.test.php
 */

define( 'ABSPATH', __DIR__ . '/../' );
define( 'GEAR_ENGINE_PATH', ABSPATH );
define( 'GEAR_ENGINE_URL', 'http://example.com/' );
define( 'GEAR_ENGINE_VERSION', '1.0.0' );

function add_action() {}
function add_shortcode() {}
function wp_register_style() {}
function wp_register_script() {}
function wp_enqueue_style() {}
function wp_enqueue_script() {}
function update_option() {}
function get_option( $k, $d = null ) { return $d; }
function get_transient( $k ) { return false; }
function set_transient() {}
function esc_url_raw( $u ) { return (string) $u; }
function esc_html( $s ) { return htmlspecialchars( (string) $s, ENT_QUOTES ); }
function esc_attr( $s ) { return htmlspecialchars( (string) $s, ENT_QUOTES ); }
function esc_url( $s ) { return (string) $s; }
function esc_html_e( $s ) { echo esc_html( $s ); }
function esc_attr_e( $s ) { echo esc_attr( $s ); }
function __( $s ) { return $s; }
function home_url( $p = '' ) { return 'http://example.com' . $p; }
function sanitize_text_field( $s ) { return trim( strip_tags( (string) $s ) ); }
function absint( $n ) { return abs( (int) $n ); }
function wp_json_encode( $d ) { return json_encode( $d ); }
function shortcode_atts( $pairs, $atts ) { return array_merge( $pairs, (array) $atts ); }
function is_wp_error( $e ) { return false; }

class WP_Error {}
class WP_Query {
	public $posts = array();
	public function have_posts() { return false; }
}

require_once GEAR_ENGINE_PATH . 'includes/class-gear-engine-autoloader.php';
require_once GEAR_ENGINE_PATH . 'includes/class-gear-engine-cpt.php';
require_once GEAR_ENGINE_PATH . 'includes/class-gear-engine-product-service.php';
require_once GEAR_ENGINE_PATH . 'modules/price-comparison/class-gear-engine-price-comparison.php';

$passed = 0;
$failed = 0;

function assert_test( $condition, $msg ) {
	global $passed, $failed;
	if ( $condition ) {
		echo " [PASS] $msg\n";
		$passed++;
	} else {
		echo " [FAIL] $msg\n";
		$failed++;
	}
}

echo "========================================================\n";
echo "Testing Price Comparison Module...\n";
echo "========================================================\n";

// 1. Fallback products
$fallback = Gear_Engine_Price_Comparison::get_fallback_products();
assert_test( is_array( $fallback ) && count( $fallback ) === 7, 'Fallback products returns exactly 7 sample items' );
assert_test( $fallback[0]['brand'] === 'Concept2', 'First fallback product is Concept2' );
assert_test( $fallback[0]['currentPrice'] === 12490, 'Concept2 price is 12490' );
assert_test( $fallback[0]['discount'] === 20, 'Concept2 discount is 20%' );
assert_test( $fallback[0]['otherShops'] === 3, 'Concept2 otherShops is 3' );
assert_test( ! empty( $fallback[0]['image'] ), 'Concept2 has valid image URL' );

// 2. Shortcode registration
assert_test( Gear_Engine_Price_Comparison::SHORTCODE_TAG === 'gear_engine_price_comparison', 'Shortcode tag constant is correct' );
assert_test( Gear_Engine_Price_Comparison::SHORTCODE_ALIAS === 'fysionett_prissammenligning', 'Shortcode alias constant is correct' );

// 3. Render method with fallback data
$module = new Gear_Engine_Price_Comparison();
$html   = $module->render( array() );

assert_test( strpos( $html, 'id="fysionett-prissok-root"' ) !== false, 'Rendered HTML contains #fysionett-prissok-root' );
assert_test( strpos( $html, 'data-feed="' ) !== false, 'Rendered HTML contains encoded data-feed' );
assert_test( strpos( $html, 'ge-price-comp-guide-banner' ) !== false, 'Rendered HTML contains guide cross-link banner' );
assert_test( strpos( $html, '/kjopsveiledere/' ) !== false, 'Rendered HTML guide banner links to /kjopsveiledere/' );

echo "========================================================\n";
if ( $failed === 0 ) {
	echo "ALL PRICE COMPARISON TESTS PASSED ($passed/$passed)\n";
} else {
	echo "TESTS FAILED ($failed failed, $passed passed)\n";
	exit( 1 );
}
echo "========================================================\n";
