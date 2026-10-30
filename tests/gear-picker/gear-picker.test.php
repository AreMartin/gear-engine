<?php
/**
 * Gear picker tests. Run with: php tests/gear-picker/gear-picker.test.php
 */

define( 'ABSPATH', __DIR__ . '/../../' );
define( 'GEAR_ENGINE_PATH', ABSPATH );
define( 'GEAR_ENGINE_URL', 'http://example.com/' );
define( 'GEAR_ENGINE_VERSION', '1.0.0' );

function add_action() {}
$GLOBALS['_test_filters'] = array();
function add_filter( $tag, $callback, $priority = 10, $accepted_args = 1 ) {
	$GLOBALS['_test_filters'][ $tag ][] = $callback;
}
function apply_filters( $tag, $value, ...$args ) {
	if ( ! empty( $GLOBALS['_test_filters'][ $tag ] ) ) {
		foreach ( $GLOBALS['_test_filters'][ $tag ] as $callback ) {
			$value = call_user_func_array( $callback, array_merge( array( $value ), $args ) );
		}
	}
	return $value;
}
function sanitize_key( $k ) { return preg_replace( '/[^a-z0-9_\-]/', '', strtolower( (string) $k ) ); }
function wp_strip_all_tags( $s ) { return strip_tags( (string) $s ); }
function __( $s ) { return $s; }
function _n( $a, $b, $n ) { return 1 === $n ? $a : $b; }
function number_format_i18n( $n ) { return (string) $n; }
function esc_url_raw( $url ) { return (string) $url; }
function has_post_thumbnail( $id ) { return false; }
function get_the_title( $post ) { return $post->post_title ?? ''; }
$GLOBALS['_test_post_meta'] = array();
function get_post_meta( $id, $key, $single = false ) {
	return $GLOBALS['_test_post_meta'][ $id ][ $key ] ?? '';
}
function wp_get_post_terms( $id, $tax, $args = array() ) { return array(); }

class WP_Error {}
function is_wp_error( $thing ) { return $thing instanceof WP_Error; }
class WP_Post {
	public $ID = 0;
	public $post_title = '';
	public $post_excerpt = '';
	public $post_content = '';
}
class Gear_Engine_CPT {
	public const POST_TYPE          = 'exercise_gear';
	public const TAXONOMY           = 'gear_category';
	public const META_EAN           = '_product_ean';
	public const META_PRICE         = '_affiliate_price';
	public const META_REGULAR_PRICE = '_regular_price';
	public const META_IN_STOCK      = '_in_stock';
	public const META_AFFILIATE_URL = '_affiliate_url';
	public const META_MERCHANT      = '_merchant_name';
	public const META_IMAGE_URL     = '_product_image_url';
	public const META_CURRENCY      = '_currency';
	public const META_LAST_SYNCED   = '_last_synced';
	public const META_SPEC_PREFIX   = '_gear_engine_spec_';
}

require_once ABSPATH . 'includes/class-gear-engine-config-loader.php';
require_once ABSPATH . 'modules/gear-picker/class-gear-engine-gear-picker.php';
require_once ABSPATH . 'modules/gear-picker/class-gear-engine-picker-diagnostics.php';

$failures = 0;
function t( $name, $ok ) {
	global $failures;
	echo ( $ok ? '[PASS] ' : '[FAIL] ' ) . $name . "\n";
	if ( ! $ok ) {
		$failures++;
	}
}

$types = Gear_Engine_Gear_Picker::get_types();
t( 'treadmill config is listed', in_array( 'treadmill', $types, true ) );
$config = Gear_Engine_Gear_Picker::get_config( 'treadmill' );
t( 'treadmill config loads', is_array( $config ) );
t( 'unknown type is an error', Gear_Engine_Gear_Picker::get_config( 'nope' ) instanceof WP_Error );
t( 'client config hides extraction rules', ! isset( Gear_Engine_Gear_Picker::get_client_config( $config )['specs'] ) && isset( Gear_Engine_Gear_Picker::get_client_config( $config )['steps'] ) );

$all_patterns = array();
foreach ( $config['specs'] as $def ) {
	$all_patterns = array_merge( $all_patterns, $def['patterns'] ?? array() );
}
$all_patterns = array_merge( $all_patterns, $config['match']['exclude_patterns'] );
t( 'every config regex compiles', ! array_filter( $all_patterns, function ( $p ) { return false === @preg_match( $p, '' ); } ) );

$match = $config['match'];
t( 'treadmill title matches', Gear_Engine_Gear_Picker::is_match( 'Titan Tredemølle X5', array(), $match ) );
t( 'category name matches', Gear_Engine_Gear_Picker::is_match( 'Titan X5', array( 'Løpebånd' ), $match ) );
t( 'unrelated product rejected', ! Gear_Engine_Gear_Picker::is_match( 'Yogamatte', array( 'Trening' ), $match ) );
t( 'accessory rejected', ! Gear_Engine_Gear_Picker::is_match( 'Underlag til tredemølle', array(), $match ) );
t( 'spare part pattern rejected', ! Gear_Engine_Gear_Picker::is_match( 'Motor til tredemølle', array(), $match ) );
t( 'excluded category rejected', ! Gear_Engine_Gear_Picker::is_match( 'Titan Tredemølle', array( 'Reservedeler' ), $match ) );
t( 'title-only words do not reject on category', Gear_Engine_Gear_Picker::is_match( 'Titan Tredemølle', array( 'Sykkel og tredemølle' ), $match ) );
t( 'scope skips include keywords', Gear_Engine_Gear_Picker::is_match( 'Titan X5', array(), $match, true ) );
t( 'scope still applies excludes', ! Gear_Engine_Gear_Picker::is_match( 'Spray for tredemølle', array(), $match, true ) );

$text = '<p>Tredemølle med hastighet opptil 18 km/t. Brukervekt maks 150 kg. Stigning opptil 12 %. Sammenleggbar, Bluetooth og 10 tommer skjerm.</p>';
$read = Gear_Engine_Gear_Picker::extract_specs( $text, $config['specs'], 9000 );
$specs = $read['specs'];
t( 'speed', 18.0 === $specs['speed'] );
t( 'weight', 150.0 === $specs['weight'] );
t( 'incline level', 12.0 === $specs['incline'] );
t( 'flags', $specs['foldable'] && $specs['app'] && $specs['screen'] && $specs['motor_incline'] && $specs['cushion'] === false && ! $specs['rehab'] );
t( 'text values are not estimates', ! array_intersect( array( 'speed', 'weight', 'app', 'motor_incline' ), $read['est'] ) );

$cheap = Gear_Engine_Gear_Picker::extract_specs( 'Enkel mølle', $config['specs'], 5000 );
t( 'cheap: speed and weight estimated', 16 === $cheap['specs']['speed'] && 120 === $cheap['specs']['weight'] && array( 'speed', 'weight' ) === $cheap['est'] );
t( 'cheap: no incline guess', null === $cheap['specs']['incline'] && false === $cheap['specs']['motor_incline'] && false === $cheap['specs']['app'] );
$pricey = Gear_Engine_Gear_Picker::extract_specs( 'Enkel mølle', $config['specs'], 20000 );
t( 'expensive: estimates scale with price', 20 === $pricey['specs']['speed'] && 150 === $pricey['specs']['weight'] && true === $pricey['specs']['motor_incline'] && true === $pricey['specs']['app'] && true === $pricey['specs']['cushion'] );
t( 'estimate threshold is exclusive for gt', 16 === Gear_Engine_Gear_Picker::extract_specs( '', $config['specs'], 16000 )['specs']['speed'] );
t( 'estimate threshold is inclusive for gte', true === Gear_Engine_Gear_Picker::extract_specs( '', $config['specs'], 8500 )['specs']['motor_incline'] );

$decimal = Gear_Engine_Gear_Picker::extract_specs( 'Topphastighet 12,5 km/t', $config['specs'], 5000 );
t( 'decimal comma', 12.5 === $decimal['specs']['speed'] );
$range = Gear_Engine_Gear_Picker::extract_specs( 'Brukervekt 30 kg', $config['specs'], 5000 );
t( 'out of range ignored (falls back to estimate)', 120 === $range['specs']['weight'] );

// Every bundled config must load and have compiling regexes and consistent references.
foreach ( $types as $type ) {
	$c = Gear_Engine_Gear_Picker::get_config( $type );
	t( "$type: loads", is_array( $c ) );
	if ( ! is_array( $c ) ) {
		continue;
	}
	$patterns = $c['match']['exclude_patterns'] ?? array();
	foreach ( $c['specs'] as $def ) {
		$patterns = array_merge( $patterns, $def['patterns'] ?? array() );
	}
	t( "$type: regexes compile", ! array_filter( $patterns, function ( $p ) { return false === @preg_match( $p, '' ); } ) );
	$slider_ids = array_column( array_filter( $c['steps'], function ( $st ) { return 'slider' === $st['type']; } ), 'id' );
	t( "$type: scoring refs are sliders", in_array( $c['scoring']['price_margin']['ref'], $slider_ids, true ) );
	$fields = array_merge( array_keys( $c['specs'] ), array( 'price' ) );
	$used   = array_column( $c['pills'], 'field' );
	t( "$type: pills use defined specs", ! array_diff( $used, $fields ) );
	t( "$type: has base scoring", isset( $c['scoring']['base'], $c['scoring']['fallback_score'] ) );
}

// Diagnostics helpers.
$D     = 'Gear_Engine_Picker_Diagnostics';
$match = array( 'include' => array( 'tredemølle' ), 'exclude' => array( 'reservedel' ), 'exclude_title' => array( 'matte' ) );
t( 'reason: matches', '' === Gear_Engine_Gear_Picker::match_reason( 'Tredemølle X', array(), $match ) );
t( 'reason: other type', 'other_type' === Gear_Engine_Gear_Picker::match_reason( 'Romaskin', array(), $match ) );
t( 'reason: excluded', 'excluded' === Gear_Engine_Gear_Picker::match_reason( 'Tredemølle matte', array(), $match ) );
t( 'reason: scope skips include', '' === Gear_Engine_Gear_Picker::match_reason( 'Romaskin', array(), $match, true ) );

t( 'types: default treadmill', array( 'treadmill' ) === $D::shortcode_types_in( '[gear_engine_gear_picker]' ) );
t( 'types: explicit + unique', array( 'romaskin', 'treadmill' ) === $D::shortcode_types_in( '[gear_engine_gear_picker type="romaskin"] [gear_engine_gear_picker type=treadmill] [gear_engine_gear_picker type="romaskin" category="x"]' ) );
t( 'types: none', array() === $D::shortcode_types_in( 'hello [gallery]' ) );

$terms = array( array( 'name' => 'Tredemøller', 'slug' => 'tredemoller', 'count' => 3 ), array( 'name' => 'Yoga', 'slug' => 'yoga', 'count' => 1 ) );
t( 'categories: suggests match', 'tredemoller' === ( $D::suggest_categories( $terms, array( 'tredemøll' ) )[0]['slug'] ?? '' ) );
t( 'categories: none', array() === $D::suggest_categories( $terms, array( 'romaskin' ) ) );

$base = array( 'total' => 20, 'qualified' => 10, 'reasons' => array(), 'samples' => array(), 'specs' => array( 'speed' => array( 'type' => 'number', 'found' => 8, 'guessed' => 2, 'missing' => 0 ) ) );
t( 'advice: healthy is ready', 'ready' === $D::status( $D::advise( $base, array(), 1 ) ) );
t( 'advice: no gear is broken', 'broken' === $D::status( $D::advise( array( 'total' => 0, 'qualified' => 0, 'reasons' => array(), 'specs' => array() ) ) ) );
$none = array_merge( $base, array( 'qualified' => 0, 'reasons' => array( 'other_type' => 20 ) ) );
t( 'advice: zero qualified is broken', 'broken' === $D::status( $D::advise( $none, array(), 1 ) ) );
$adv = $D::advise( $none, array( array( 'name' => 'T', 'slug' => 'tredemoller', 'count' => 3 ) ), 1 );
t( 'advice: suggests category slug', false !== strpos( json_encode( $adv ), 'category=\"tredemoller\"' ) );
t( 'advice: few products needs attention', 'attention' === $D::status( $D::advise( array_merge( $base, array( 'qualified' => 3 ) ), array(), 1 ) ) );
$weak = $base;
$weak['specs']['speed']['found'] = 2;
t( 'advice: weak spec coverage needs attention', 'attention' === $D::status( $D::advise( $weak, array(), 1 ) ) );
t( 'advice: unused is only a tip', 'ready' === $D::status( $D::advise( $base, array(), 0 ) ) );

// Test manual spec overrides & gear_engine_picker_item filter in evaluate_post.
$post = new WP_Post();
$post->ID = 101;
$post->post_title = 'Sole F85 Tredemølle';
$post->post_excerpt = 'Solid tredemølle for løping i toppklasse.';
$post->post_content = 'Kraftig motor og god ramme.';

$GLOBALS['_test_post_meta'][101] = array(
	Gear_Engine_CPT::META_IN_STOCK      => '1',
	Gear_Engine_CPT::META_PRICE         => 24990,
	Gear_Engine_CPT::META_REGULAR_PRICE => 29990,
	Gear_Engine_CPT::META_AFFILIATE_URL => 'https://example.com/sole-f85',
	Gear_Engine_CPT::META_MERCHANT      => 'Treningspartner',
);

$res_auto = Gear_Engine_Gear_Picker::evaluate_post( $post, $config );
t( 'evaluate_post: qualifies', null !== $res_auto['item'] );
t( 'evaluate_post: speed estimated before override', in_array( 'speed', $res_auto['item']['est'], true ) );

// Apply manual overrides.
$GLOBALS['_test_post_meta'][101]['_gear_engine_spec_speed']   = '22.5';
$GLOBALS['_test_post_meta'][101]['_gear_engine_spec_cushion'] = '1';
$res_over = Gear_Engine_Gear_Picker::evaluate_post( $post, $config );

t( 'override: speed override value applied', 22.5 === $res_over['item']['specs']['speed'] );
t( 'override: speed removed from estimates', ! in_array( 'speed', $res_over['item']['est'], true ) );
t( 'override: cushion flag forced to true', true === $res_over['item']['specs']['cushion'] );

// Test filter modification.
add_filter( 'gear_engine_picker_item', function( $item, $post, $cfg ) {
	if ( 101 === $item['id'] ) {
		$item['custom_badge'] = 'Editor Pick';
	}
	return $item;
}, 10, 3 );
$res_filtered = Gear_Engine_Gear_Picker::evaluate_post( $post, $config );
t( 'filter: gear_engine_picker_item mutates item', 'Editor Pick' === ( $res_filtered['item']['custom_badge'] ?? '' ) );

// Test filter rejection.
add_filter( 'gear_engine_picker_item', function( $item ) {
	return null;
} );
$res_rejected = Gear_Engine_Gear_Picker::evaluate_post( $post, $config );
t( 'filter: gear_engine_picker_item can reject item', null === $res_rejected['item'] && 'filtered' === $res_rejected['reason'] );

exit( $failures ? 1 : 0 );
