<?php
/**
 * Program linking and protocol page tests. Run with: php tests/triage/program-link.test.php
 *
 * Uses fake protocol/exercise classes so the lookup, safety gating, embed whitelist and
 * rendering can be tested without WordPress.
 */

define( 'ABSPATH', __DIR__ . '/../../' );
define( 'GEAR_ENGINE_PATH', ABSPATH );
define( 'GEAR_ENGINE_URL', 'http://example.com/wp-content/plugins/gear-engine/' );
define( 'GEAR_ENGINE_VERSION', '1.0.0' );

$GLOBALS['opts'] = array();
// slug => array of exercises; protocols: slug => [name, codes, status, parent, phases]
$GLOBALS['exercises']  = array();
$GLOBALS['protocols']  = array();

function add_action() {}
function add_shortcode() {}
function register_setting() {}
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
function esc_html__( $s ) { return esc_html( $s ); }
function __( $s ) { return $s; }
function wp_enqueue_style( $h ) {}
function wp_enqueue_script( $h ) {}
function wp_json_encode( $d, $f = 0 ) { return json_encode( $d, $f ); }
function wp_unslash( $v ) { return $v; }
function current_user_can() { return false; }
function shortcode_atts( $pairs, $atts ) { return array_merge( $pairs, array_intersect_key( $atts, $pairs ) ); }
function is_wp_error( $v ) { return $v instanceof WP_Error; }
function get_term_by( $field, $slug ) { return isset( $GLOBALS['protocols'][ $slug ] ) ? (object) array( 'slug' => $slug, 'term_id' => crc32( $slug ), 'name' => $GLOBALS['protocols'][ $slug ]['name'], 'parent' => 0 ) : false; }
function get_terms( $args ) {
	$out = array();
	foreach ( $GLOBALS['protocols'] as $slug => $p ) {
		if ( ( $p['parent_slug'] ?? '' ) === ( $args['parent'] ?? 0 ) || ( 0 === ( $args['parent'] ?? null ) && '' === ( $p['parent_slug'] ?? '' ) ) ) {
			$out[] = (object) array( 'slug' => $slug, 'term_id' => crc32( $slug ), 'name' => $p['name'], 'description' => $p['description'] ?? '', 'parent' => 0 );
		}
	}
	return $out;
}
class WP_Error {
	public function get_error_message() { return ''; }
}

class Gear_Engine_Protocols {
	public const GEAR_ENGINE_TAXONOMY        = 'gear_engine_protocol';
	public const GEAR_ENGINE_STATUS_APPROVED = 'approved';
	public const GEAR_ENGINE_META_STATUS     = '_status';
	public static function is_enabled(): bool { return true; }
	public static function get_status( int $id ): string {
		foreach ( $GLOBALS['protocols'] as $p ) {
			if ( crc32( $p['slug'] ) === $id ) {
				return $p['status'];
			}
		}
		return 'draft';
	}
	public static function find_approved_by_codes( array $codes ): array {
		$out = array();
		foreach ( $GLOBALS['protocols'] as $slug => $p ) {
			if ( 'approved' === $p['status'] && array_intersect( $codes, $p['codes'] ) ) {
				$out[] = self::get_protocol_data( (object) array( 'slug' => $slug, 'term_id' => crc32( $slug ), 'name' => $p['name'] ) );
			}
		}
		return $out;
	}
	public static function get_protocol_data( $term ): array {
		return array( 'term_id' => (int) $term->term_id, 'slug' => $term->slug, 'name' => $term->name, 'summary' => 'Kort <b>oppsummering</b>', 'red_flags' => 'Feber', 'dosage' => '3 ganger i uka' );
	}
}
class Gear_Engine_Exercise_Service {
	public static function search_exercises( array $args = array() ): array {
		return $GLOBALS['exercises'][ $args['protocol'] ?? '' ] ?? array();
	}
}

require_once ABSPATH . 'modules/exercises/class-gear-engine-protocol-viewer.php';
require_once ABSPATH . 'modules/triage/class-gear-engine-triage.php';

$failures = 0;
function t( $name, $ok ) {
	global $failures;
	echo ( $ok ? '[PASS] ' : '[FAIL] ' ) . $name . "\n";
	if ( ! $ok ) {
		$failures++;
	}
}

function ex( $id, $title, $extra = array() ) {
	return $extra + array(
		'id' => $id, 'title' => $title, 'dosage' => '3 x 10', 'description' => 'Beskrivelse <script>x</script>', 'summary' => '',
		'exorlive_id' => '', 'video_culture' => 'nb-NO', 'video_url' => '', 'attribution_text' => '', 'attribution_url' => '',
	);
}

$GLOBALS['protocols'] = array(
	'saps-program'   => array( 'slug' => 'saps-program', 'name' => 'SAPS-program', 'codes' => array( 'M75.4' ), 'status' => 'approved', 'parent_slug' => '' ),
	'empty-program'  => array( 'slug' => 'empty-program', 'name' => 'Tomt', 'codes' => array( 'M75.0' ), 'status' => 'approved', 'parent_slug' => '' ),
	'draft-program'  => array( 'slug' => 'draft-program', 'name' => 'Utkast', 'codes' => array( 'M19.0' ), 'status' => 'draft', 'parent_slug' => '' ),
	'referral-prog'  => array( 'slug' => 'referral-prog', 'name' => 'Henvisning', 'codes' => array( 'R52.9' ), 'status' => 'approved', 'parent_slug' => '' ),
	'neck-program'   => array( 'slug' => 'neck-program', 'name' => 'Nakke', 'codes' => array( 'M54.2' ), 'status' => 'approved', 'parent_slug' => '' ),
);
$GLOBALS['exercises'] = array(
	'saps-program'  => array( ex( 1, 'Strikkeøvelse', array( 'exorlive_id' => '1234' ) ), ex( 2, 'Pendel' ) ),
	'referral-prog' => array( ex( 3, 'Noe' ) ),
	'neck-program'  => array( ex( 4, 'Hake inn' ) ),
);

// Page path and URL.
t( 'page path default', Gear_Engine_Protocol_Viewer::get_page_path() === 'treningsprogram' );
t( 'page path sanitised', Gear_Engine_Protocol_Viewer::sanitize_page_path( '/Min Side//Program/' ) === 'min-side/program' );
t( 'program url', Gear_Engine_Protocol_Viewer::get_program_url( 'saps-program' ) === 'http://example.com/treningsprogram/?program=saps-program' );

// Linking.
$skulder = Gear_Engine_Triage::attach_programs( Gear_Engine_Triage::get_config( 'skulder' ) );
t( 'saps links to matching approved protocol', ( $skulder['diagnoses']['saps']['trainingProgram']['url'] ?? '' ) === 'http://example.com/treningsprogram/?program=saps-program' );
t( 'button text falls back to config title', ( $skulder['diagnoses']['saps']['trainingProgram']['title'] ?? '' ) === 'Start opptrening for SAPS' );
t( 'protocol without exercises is not linked', empty( $skulder['diagnoses']['frozen_shoulder']['trainingProgram'] ) );
t( 'draft protocol is not linked', empty( $skulder['diagnoses']['osteoarthritis']['trainingProgram'] ) );
t( 'high severity never gets a program', empty( $skulder['diagnoses']['medical_referral']['trainingProgram'] ) && empty( $skulder['diagnoses']['acute_cuff_tear']['trainingProgram'] ) );
t( 'programs without a url are stripped', empty( $skulder['diagnoses']['instability']['trainingProgram'] ) );

$custom = array( 'diagnoses' => array(
	'a' => array( 'severity' => 'Lav', 'icd10' => 'ZZZ', 'icpc2' => 'ZZ', 'protocol' => 'neck-program' ),
	'b' => array( 'severity' => 'Lav', 'icd10' => 'M75.4', 'icpc2' => 'L92', 'trainingProgram' => array( 'title' => 'Manuell', 'url' => 'https://example.org/x' ) ),
	'c' => array( 'severity' => 'Lav', 'icd10' => 'M75.4', 'icpc2' => 'L92', 'protocol' => 'draft-program' ),
) );
$custom = Gear_Engine_Triage::attach_programs( $custom );
t( 'explicit protocol override is used', ( $custom['diagnoses']['a']['trainingProgram']['url'] ?? '' ) === 'http://example.com/treningsprogram/?program=neck-program' );
t( 'manual url is kept', ( $custom['diagnoses']['b']['trainingProgram']['url'] ?? '' ) === 'https://example.org/x' );
t( 'unapproved override is not linked, even if codes match', empty( $custom['diagnoses']['c']['trainingProgram'] ) );

// Embed whitelist.
t( 'embed exorlive', Gear_Engine_Protocol_Viewer::get_embed_url( array( 'exorlive_id' => '1234', 'video_culture' => 'nb-NO' ) ) === 'https://exorlive.com/video/?culture=nb-NO&ex=1234&autoplay=true&repeat=true&hd=true' );
t( 'embed exorlive rejects non-numeric id', '' === Gear_Engine_Protocol_Viewer::get_embed_url( array( 'exorlive_id' => '12"><x' ) ) );
t( 'embed vimeo', Gear_Engine_Protocol_Viewer::get_embed_url( array( 'video_url' => 'https://player.vimeo.com/video/123?h=abc' ) ) === 'https://player.vimeo.com/video/123?autoplay=1&h=abc' );
t( 'embed youtube is no-cookie', Gear_Engine_Protocol_Viewer::get_embed_url( array( 'video_url' => 'https://youtu.be/dQw4w9WgXcQ' ) ) === 'https://www.youtube-nocookie.com/embed/dQw4w9WgXcQ?autoplay=1&rel=0' );
t( 'embed rejects other hosts', '' === Gear_Engine_Protocol_Viewer::get_embed_url( array( 'video_url' => 'https://evil.example/video/1' ) ) );
t( 'embed rejects javascript url', '' === Gear_Engine_Protocol_Viewer::get_embed_url( array( 'video_url' => 'javascript:alert(1)' ) ) );

// Rendering.
$viewer = new Gear_Engine_Protocol_Viewer();
$html   = $viewer->render( array( 'slug' => 'saps-program' ) );
t( 'page shows protocol name and exercises', false !== strpos( $html, 'SAPS-program' ) && false !== strpos( $html, 'Strikkeøvelse' ) && false !== strpos( $html, 'Pendel' ) );
t( 'page escapes protocol and exercise text', false === strpos( $html, '<b>' ) && false === strpos( $html, '<script>' ) );
t( 'page shows red flags warning', false !== strpos( $html, 'Feber' ) );
t( 'video is lazy: no iframe server-side', false === strpos( $html, '<iframe' ) && false !== strpos( $html, 'data-embed="https://exorlive.com/video/' ) );
t( 'unknown program shows unavailable message', false !== strpos( $viewer->render( array( 'slug' => 'nope' ) ), 'ikke tilgjengelig' ) );
t( 'draft program is not shown', false !== strpos( $viewer->render( array( 'slug' => 'draft-program' ) ), 'ikke tilgjengelig' ) );
t( 'program without exercises is not shown', false !== strpos( $viewer->render( array( 'slug' => 'empty-program' ) ), 'ikke tilgjengelig' ) );

$_GET['program'] = 'neck-program';
t( 'program slug read from query string', false !== strpos( $viewer->render(), 'Hake inn' ) );
unset( $_GET['program'] );
$index = $viewer->render();
t( 'index lists only approved programs with exercises', false !== strpos( $index, 'SAPS-program' ) && false === strpos( $index, 'Utkast' ) && false === strpos( $index, 'Tomt' ) );
t( 'index links to program url', false !== strpos( $index, 'treningsprogram/?program=saps-program' ) );

exit( $failures ? 1 : 0 );
