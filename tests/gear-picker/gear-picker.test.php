<?php
/**
 * Gear picker tests. Run with: php tests/gear-picker/gear-picker.test.php
 */

define( 'ABSPATH', __DIR__ . '/../../' );
define( 'GEAR_ENGINE_PATH', ABSPATH );
define( 'GEAR_ENGINE_URL', 'http://example.com/' );
define( 'GEAR_ENGINE_VERSION', '1.0.0' );

function add_action() {}
function apply_filters( $tag, $value ) { return $value; }
function sanitize_key( $k ) { return preg_replace( '/[^a-z0-9_\-]/', '', strtolower( (string) $k ) ); }
function wp_strip_all_tags( $s ) { return strip_tags( (string) $s ); }
class WP_Error {}
class WP_Post {}
class GAE_CPT {
	public const POST_TYPE = 'exercise_gear';
}

require_once ABSPATH . 'modules/gear-picker/class-gear-engine-gear-picker.php';

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

exit( $failures ? 1 : 0 );
