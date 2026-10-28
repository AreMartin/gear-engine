<?php
/**
 * Triage module PHP tests. Run with: php tests/triage/triage-php.test.php
 *
 * Covers config validation, base path sanitising, region URLs and picker/quiz rendering.
 */

define( 'ABSPATH', __DIR__ . '/../../' );
define( 'GEAR_ENGINE_PATH', ABSPATH );
define( 'GEAR_ENGINE_URL', 'http://example.com/wp-content/plugins/gear-engine/' );
define( 'GEAR_ENGINE_VERSION', '1.0.0' );

$GLOBALS['opts']     = array();
$GLOBALS['enqueued'] = array();

function add_action() {}
function add_shortcode() {}
function apply_filters( $tag, $value ) { return $value; }
function get_option( $k, $d = false ) { return $GLOBALS['opts'][ $k ] ?? $d; }
function home_url( $p = '' ) { return 'http://example.com/' . ltrim( $p, '/' ); }
function trailingslashit( $s ) { return rtrim( $s, '/' ) . '/'; }
function sanitize_key( $k ) { return preg_replace( '/[^a-z0-9_\-]/', '', strtolower( (string) $k ) ); }
function sanitize_title( $t ) { return trim( preg_replace( '/[^a-z0-9]+/', '-', strtolower( (string) $t ) ), '-' ); }
function esc_html( $s ) { return htmlspecialchars( (string) $s, ENT_QUOTES ); }
function esc_attr( $s ) { return esc_html( $s ); }
function esc_url( $s ) { return esc_html( $s ); }
function esc_html_e( $s ) { echo esc_html( $s ); }
function esc_attr_e( $s ) { echo esc_attr( $s ); }
function __( $s ) { return $s; }
function wp_enqueue_style( $h ) { $GLOBALS['enqueued'][] = $h; }
function wp_enqueue_script( $h ) { $GLOBALS['enqueued'][] = $h; }
function wp_json_encode( $d, $f = 0 ) { return json_encode( $d, $f ); }
function current_user_can() { return false; }
function shortcode_atts( $pairs, $atts ) { return array_merge( $pairs, array_intersect_key( $atts, $pairs ) ); }
function is_wp_error( $v ) { return $v instanceof WP_Error; }
class WP_Error {
	public $code;
	public $message;
	public function __construct( $code, $message ) { $this->code = $code; $this->message = $message; }
	public function get_error_message() { return $this->message; }
}

require_once ABSPATH . 'includes/class-gear-engine-config-loader.php';
require_once ABSPATH . 'modules/triage/class-gear-engine-triage.php';

$failures = 0;
function t( $name, $ok ) {
	global $failures;
	echo ( $ok ? '[PASS] ' : '[FAIL] ' ) . $name . "\n";
	if ( ! $ok ) {
		$failures++;
	}
}

t( 'base path default', Gear_Engine_Triage::get_base_path() === 'smertesjekk' );
t( 'base path sanitised', Gear_Engine_Triage::sanitize_base_path( '/Digital Fysio//Tester/' ) === 'digital-fysio/tester' );
t( 'base path empty falls back', Gear_Engine_Triage::sanitize_base_path( ' // ' ) === 'smertesjekk' );
t( 'base path rejects traversal chars', Gear_Engine_Triage::sanitize_base_path( '../x"><script>' ) === 'x-script' );

$GLOBALS['opts'][ Gear_Engine_Triage::OPTION_BASE_PATH ] = 'helse/tester';
t( 'region url uses option', Gear_Engine_Triage::get_region_url( 'rygg' ) === 'http://example.com/helse/tester/rygg/' );
t( 'section url', Gear_Engine_Triage::get_section_url() === 'http://example.com/helse/tester/' );
unset( $GLOBALS['opts'][ Gear_Engine_Triage::OPTION_BASE_PATH ] );

t( 'regions include rygg', in_array( 'rygg', Gear_Engine_Triage::get_regions(), true ) );
t( 'unknown region is error', is_wp_error( Gear_Engine_Triage::get_config( '../../etc/passwd' ) ) );
t( 'invalid config rejected', ! Gear_Engine_Triage::validate_config( array( 'title' => 'x' ) ) );

$cards = Gear_Engine_Triage::get_region_cards();
t( 'cards have label and url', isset( $cards[0] ) && 'Rygg' === $cards[0]['label'] && 'http://example.com/smertesjekk/rygg/' === $cards[0]['url'] );

t( 'icon svg for known key', false !== strpos( Gear_Engine_Triage::get_icon_svg( 'knee' ), '<circle cx="11" cy="12.5"' ) );
t( 'icon falls back', false !== strpos( Gear_Engine_Triage::get_icon_svg( 'nope' ), 'points="22 12 18 12' ) );

$triage = new Gear_Engine_Triage();
$picker = $triage->render_picker();
t( 'picker links to region page', false !== strpos( $picker, 'href="http://example.com/smertesjekk/rygg/"' ) );
t( 'picker renders icon', false !== strpos( $picker, '<svg class="gear-engine-triage-picker__icon"' ) );
t( 'picker enqueues style', in_array( 'gear-engine-triage', $GLOBALS['enqueued'], true ) );

$quiz = $triage->render( array( 'region' => 'rygg' ) );
t( 'quiz renders container and back link', false !== strpos( $quiz, 'data-region="rygg"' ) && false !== strpos( $quiz, 'href="http://example.com/smertesjekk/"' ) );
t( 'quiz config cannot break out of script tag', 1 === substr_count( $quiz, '</script>' ) );
t( 'back link can be disabled', false === strpos( $triage->render( array( 'region' => 'rygg', 'back_link' => 'false' ) ), 'gear-engine-triage__back' ) );
t( 'unknown region renders nothing for visitors', '' === $triage->render( array( 'region' => 'nope' ) ) );

exit( $failures ? 1 : 0 );
