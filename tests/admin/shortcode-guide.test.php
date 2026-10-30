<?php
/**
 * Shortcode guide tests. Run with: php tests/admin/shortcode-guide.test.php
 */

define( 'ABSPATH', __DIR__ . '/../../' );
define( 'GEAR_ENGINE_PATH', ABSPATH );
define( 'GEAR_ENGINE_URL', 'http://example.com/wp-content/plugins/gear-engine/' );
define( 'GEAR_ENGINE_VERSION', '1.0.0' );

$GLOBALS['opts']  = array();
$GLOBALS['pages'] = array();
$GLOBALS['posts'] = array();

function add_action() {}
function add_shortcode() {}
function register_setting() {}
function add_options_page() {}
function apply_filters( $tag, $value ) { return $value; }
function get_option( $k, $d = false ) { return $GLOBALS['opts'][ $k ] ?? $d; }
function home_url( $p = '' ) { return 'http://example.com/' . ltrim( $p, '/' ); }
function admin_url( $p = '' ) { return 'http://example.com/wp-admin/' . ltrim( $p, '/' ); }
function trailingslashit( $s ) { return rtrim( $s, '/' ) . '/'; }
function sanitize_key( $k ) { return preg_replace( '/[^a-z0-9_\-]/', '', strtolower( (string) $k ) ); }
function sanitize_title( $t ) { return trim( preg_replace( '/[^a-z0-9]+/', '-', strtolower( (string) $t ) ), '-' ); }
function esc_html( $s ) { return htmlspecialchars( (string) $s, ENT_QUOTES ); }
function esc_attr( $s ) { return esc_html( $s ); }
function esc_url( $s ) { return esc_html( $s ); }
function esc_html_e( $s ) { echo esc_html( $s ); }
function esc_html__( $s ) { return esc_html( $s ); }
function __( $s ) { return $s; }
function current_user_can() { return true; }
function is_wp_error( $v ) { return $v instanceof WP_Error; }
function get_page_by_path( $path ) { return $GLOBALS['pages'][ $path ] ?? null; }
function has_shortcode( $content, $tag ) { return false !== strpos( $content, '[' . $tag ); }
function get_posts( $args ) { return $GLOBALS['posts']; }
function get_edit_post_link( $id ) { return 'http://example.com/wp-admin/post.php?post=' . $id . '&action=edit'; }
function add_query_arg( $args, $url ) { return $url . '?' . http_build_query( $args ); }
class WP_Error {
	public function get_error_message() { return ''; }
}
class Gear_Engine_Protocols {}
class Gear_Engine_Exercise_Service {}

require_once ABSPATH . 'includes/class-gear-engine-config-loader.php';
require_once ABSPATH . 'modules/exercises/class-gear-engine-exercises-cpt.php';
require_once ABSPATH . 'modules/exercises/class-gear-engine-protocol-viewer.php';
require_once ABSPATH . 'modules/triage/class-gear-engine-triage.php';
require_once ABSPATH . 'modules/gear-picker/class-gear-engine-gear-picker.php';
require_once ABSPATH . 'modules/admin/class-gear-engine-shortcode-guide.php';

$failures = 0;
function t( $name, $ok ) {
	global $failures;
	echo ( $ok ? '[PASS] ' : '[FAIL] ' ) . $name . "\n";
	if ( ! $ok ) {
		$failures++;
	}
}
function page( $id, $content, $status = 'publish' ) {
	return (object) array( 'ID' => $id, 'post_content' => $content, 'post_status' => $status );
}

// Shortcode list.
$list = Gear_Engine_Shortcode_Guide::get_shortcodes();
$tags = array_column( $list, 'tag' );
foreach ( array( 'gae_physio_chat', 'gear_engine_triage_picker', 'gear_engine_triage', 'gear_engine_protocol', 'gear_engine_gear_picker', 'gear_product' ) as $tag ) {
	t( "lists $tag", in_array( $tag, $tags, true ) );
}
t( 'every example starts with its own tag', ! array_filter( $list, function ( $r ) { return 0 !== strpos( $r['example'], '[' . $r['tag'] ); } ) );

// Region shortcodes follow the configs.
$regions = Gear_Engine_Shortcode_Guide::get_region_shortcodes();
t( 'one region shortcode per config', count( $regions ) === count( Gear_Engine_Triage::get_regions() ) && count( $regions ) >= 6 );
$examples = array_column( $regions, 'example', 'path' );
t( 'region shortcode and path', ( $examples['smertesjekk/rygg'] ?? '' ) === '[gear_engine_triage region="rygg"]' );

// Page status.
t( 'missing page', 'missing' === Gear_Engine_Shortcode_Guide::get_page_status( 'smertesjekk', 'gear_engine_triage_picker' )['state'] );
$GLOBALS['pages']['smertesjekk'] = page( 5, 'Hei [gear_engine_triage_picker]' );
t( 'page with shortcode is ok', 'ok' === Gear_Engine_Shortcode_Guide::get_page_status( '/smertesjekk/', 'gear_engine_triage_picker' )['state'] );
$GLOBALS['pages']['smertesjekk'] = page( 5, 'tomt' );
t( 'page without shortcode is flagged', 'no_shortcode' === Gear_Engine_Shortcode_Guide::get_page_status( 'smertesjekk', 'gear_engine_triage_picker' )['state'] );
$GLOBALS['pages']['smertesjekk'] = page( 5, '[gear_engine_triage_picker]', 'draft' );
t( 'draft page is flagged', 'unpublished' === Gear_Engine_Shortcode_Guide::get_page_status( 'smertesjekk', 'gear_engine_triage_picker' )['state'] );

// Page map.
$GLOBALS['pages']['smertesjekk']      = page( 5, '[gear_engine_triage_picker]' );
$GLOBALS['pages']['smertesjekk/rygg'] = page( 6, '[gear_engine_triage region="rygg"]' );
$map = Gear_Engine_Shortcode_Guide::get_page_map();
$by  = array();
foreach ( $map as $row ) {
	$by[ $row['path'] ?: $row['tag'] ] = $row;
}
t( 'picker row ok', 'ok' === $by['smertesjekk']['state'] );
t( 'rygg row ok', 'ok' === $by['smertesjekk/rygg']['state'] );
t( 'other region page missing', 'missing' === $by['smertesjekk/kne']['state'] );
t( 'program page row uses configured path', isset( $by['treningsprogram'] ) && 'missing' === $by['treningsprogram']['state'] );
t( 'chat row missing when no page uses it', 'missing' === $by['gae_physio_chat']['state'] );
$GLOBALS['posts'] = array( page( 9, 'Chat: [gae_physio_chat height="80vh"]' ) );
$chat = array_values( array_filter( Gear_Engine_Shortcode_Guide::get_page_map(), function ( $r ) { return 'gae_physio_chat' === $r['tag']; } ) )[0];
t( 'chat found on any page', 'ok' === $chat['state'] && 9 === $chat['post_id'] );

$GLOBALS['opts']['gear_engine_triage_base_path']  = 'pain';
$GLOBALS['opts']['gear_engine_program_page_path'] = 'programs';
$paths = array_column( Gear_Engine_Shortcode_Guide::get_page_map(), 'path' );
t( 'map follows configured paths', in_array( 'pain', $paths, true ) && in_array( 'pain/rygg', $paths, true ) && in_array( 'programs', $paths, true ) );

// Links and rendering.
list( $url, $label ) = Gear_Engine_Shortcode_Guide::get_action_link( array( 'post_id' => 6, 'title' => 'x', 'example' => 'y' ) );
t( 'existing page links to edit', false !== strpos( $url, 'post=6' ) && 'Edit page' === $label );
list( $url, $label ) = Gear_Engine_Shortcode_Guide::get_action_link( array( 'post_id' => 0, 'title' => 'Smertesjekk: Kne', 'example' => '[gear_engine_triage region="kne"]' ) );
t( 'missing page links to create with content', false !== strpos( $url, 'post-new.php' ) && false !== strpos( $url, 'post_type=page' ) && false !== strpos( $url, 'post_content=' ) && 'Create page' === $label );

$shortcodes = $list;
$page_map   = Gear_Engine_Shortcode_Guide::get_page_map();
ob_start();
include ABSPATH . 'modules/admin/templates/shortcode-guide.php';
$html = ob_get_clean();
t( 'template renders copy buttons', substr_count( $html, 'gear-engine-guide__copy' ) >= count( $list ) + count( $page_map ) );
t( 'template escapes shortcode quotes', false !== strpos( $html, '[gear_engine_triage region=&quot;rygg&quot;]' ) );
t( 'template shows status labels', false !== strpos( $html, 'Page missing' ) && false !== strpos( $html, 'gear-engine-guide__state--ok' ) );

exit( $failures ? 1 : 0 );
