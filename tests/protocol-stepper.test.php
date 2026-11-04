<?php
/**
 * Tests for Protocol Viewer Stepper:
 * Verifies interactive step-by-step layout, tabs, video embeds, and view mode switching.
 *
 * Run with: php tests/protocol-stepper.test.php
 */

define( 'ABSPATH', __DIR__ . '/../' );
define( 'GEAR_ENGINE_PATH', ABSPATH );
define( 'GEAR_ENGINE_URL', 'http://example.com/wp-content/plugins/gear-engine/' );
define( 'GEAR_ENGINE_VERSION', '1.0.0' );

// Mock WordPress environment.
function __( $s ) { return $s; }
function esc_html__( $s ) { return $s; }
function esc_attr__( $s ) { return $s; }
function esc_html_e( $s ) { echo $s; }
function esc_attr_e( $s ) { echo esc_attr( $s ); }
function esc_html( $s ) { return htmlspecialchars( (string) $s, ENT_QUOTES, 'UTF-8' ); }
function esc_attr( $s ) { return htmlspecialchars( (string) $s, ENT_QUOTES, 'UTF-8' ); }
function esc_url( $s ) { return (string) $s; }
function esc_url_raw( $s ) { return (string) $s; }
function sanitize_key( $k ) { return preg_replace( '/[^a-z0-9_\-]/', '', strtolower( (string) $k ) ); }
function sanitize_title( $t ) { return trim( preg_replace( '/[^a-z0-9]+/', '-', strtolower( (string) $t ) ), '-' ); }
function wp_parse_url( $u ) { return parse_url( (string) $u ); }
function wp_http_validate_url( $u ) { return filter_var( $u, FILTER_VALIDATE_URL ) ? (string) $u : false; }

require_once GEAR_ENGINE_PATH . 'modules/exercises/class-gear-engine-exercises-cpt.php';
require_once GEAR_ENGINE_PATH . 'modules/exercises/class-gear-engine-exercise-service.php';
require_once GEAR_ENGINE_PATH . 'modules/exercises/class-gear-engine-protocols.php';
require_once GEAR_ENGINE_PATH . 'modules/exercises/class-gear-engine-protocol-viewer.php';

$tests_passed = 0;
function assert_test( $condition, $message ) {
	global $tests_passed;
	if ( ! $condition ) {
		echo " [FAIL] {$message}\n";
		exit( 1 );
	}
	echo " [PASS] {$message}\n";
	$tests_passed++;
}

echo "========================================================\n";
echo "Testing Protocol Viewer Stepper & Video Integration...\n";
echo "========================================================\n";

// Sample test data for 2 phases.
$protocol = array(
	'name'      => 'Test Løperkne Protokoll',
	'summary'   => 'Gradvis opptrening av hofte- og knestabilitet.',
	'dosage'    => '3 ganger i uken',
	'red_flags' => 'Akutt hevelse eller låsning.',
	'slug'      => 'loperkne',
);

$groups = array(
	array(
		'name'        => 'Fase 1: Akutt avlastning',
		'description' => 'Mål: Ro ned vevsirritasjon og start skånsom aktivering.',
		'exercises'   => array(
			array(
				'id'                => 101,
				'title'             => 'Sideliggende benløft',
				'description'       => "Ligg på siden med strake ben.\nLøft det øverste benet rolig opp mot taket.\nHold i 1 sekund og senk kontrollert.",
				'summary'           => 'Aktivering av gluteus medius.',
				'dosage'            => '10–12 repetisjoner × 3 sett',
				'equipment'         => array( 'matte' ),
				'equipment_details' => array(
					array(
						'title'         => 'Treningsmatte / Yogamatte',
						'free_alt'      => 'Et mykt teppe på gulvet',
						'picker_url'    => 'http://example.com/matter/',
						'picker_label'  => 'Se treningsmatter i Styrkeguiden',
						'is_bodyweight' => false,
					),
				),
				'video_url'         => 'https://www.youtube.com/watch?v=B7bV4mK4WbY',
				'exorlive_id'       => '',
				'attribution_text'  => '',
				'attribution_url'   => '',
				'role'              => 'activation',
			),
			array(
				'id'                => 102,
				'title'             => 'Muslingen (Clamshell) med miniband',
				'description'       => 'Ligg på siden med bøyde knær. Åpne det øverste kneet kontrollert som et skjell.',
				'summary'           => 'Målrettet styrke for hoftens utadrotatorer.',
				'dosage'            => '12 repetisjoner × 3 sett',
				'equipment'         => array( 'strikk' ),
				'equipment_details' => array(),
				'video_url'         => 'https://www.youtube.com/watch?v=npkG0n2Q3Qk',
				'exorlive_id'       => '',
				'attribution_text'  => '',
				'attribution_url'   => '',
				'role'              => 'strength',
			),
		),
	),
	array(
		'name'        => 'Fase 2: Oppbygging',
		'description' => 'Mål: Øke styrke og toleranse.',
		'exercises'   => array(
			array(
				'id'                => 103,
				'title'             => 'Knebøy til stol med kontroll',
				'description'       => 'Plasser en stol bak deg. Bøy knærne kontrollert og reis deg opp.',
				'summary'           => 'Funksjonell styrke for forside lår.',
				'dosage'            => '10 repetisjoner × 3 sett',
				'equipment'         => array( 'stol' ),
				'equipment_details' => array(),
				'video_url'         => 'https://www.youtube.com/watch?v=aclHkVaku9U',
				'exorlive_id'       => '',
				'attribution_text'  => '',
				'attribution_url'   => '',
				'role'              => 'strength',
			),
		),
	),
);

$change_url = 'http://example.com/endre-svar/';

// Render protocol view template.
ob_start();
include GEAR_ENGINE_PATH . 'modules/exercises/templates/protocol-view.php';
$html = ob_get_clean();

// 1. Root element has stepper mode attribute.
assert_test( false !== strpos( $html, 'data-active-mode="stepper"' ), 'Program container has data-active-mode="stepper"' );

// 2. Mode switcher buttons exist.
assert_test( false !== strpos( $html, 'data-view-mode="stepper"' ), 'View mode toggle has stepper option' );
assert_test( false !== strpos( $html, 'data-view-mode="list"' ), 'View mode toggle has list option' );

// 3. Phase tabs exist for multi-phase program.
assert_test( false !== strpos( $html, 'gear-engine-program__phase-tabs' ), 'Phase tabs container is rendered' );
assert_test( false !== strpos( $html, 'Fase 1: Akutt avlastning' ), 'Phase 1 tab name is rendered' );
assert_test( false !== strpos( $html, 'Fase 2: Oppbygging' ), 'Phase 2 tab name is rendered' );

// 4. Stepper exercise navigation tabs exist.
assert_test( false !== strpos( $html, 'gear-engine-program__stepper-tabs' ), 'Horizontal stepper exercise tabs exist' );
assert_test( false !== strpos( $html, 'Øvelse 1' ), 'Exercise 1 tab is rendered' );
assert_test( false !== strpos( $html, 'Øvelse 2' ), 'Exercise 2 tab is rendered' );

// 5. Video embed exists and uses privacy-enhanced youtube-nocookie.com.
assert_test( false !== strpos( $html, 'www.youtube-nocookie.com/embed/B7bV4mK4WbY' ), 'Exercise 1 has valid youtube-nocookie embed iframe' );
assert_test( false !== strpos( $html, 'www.youtube-nocookie.com/embed/npkG0n2Q3Qk' ), 'Exercise 2 has valid youtube-nocookie embed iframe' );

// 6. Step counter badge exists.
assert_test( false !== strpos( $html, 'Øvelse 1 av 2' ), 'Step counter badge Øvelse 1 av 2 is rendered' );

// 7. Navigation buttons exist.
assert_test( false !== strpos( $html, 'data-nav="prev"' ), 'Previous exercise button exists' );
assert_test( false !== strpos( $html, 'data-nav="next"' ), 'Next exercise button exists' );
assert_test( false !== strpos( $html, 'data-nav="finish"' ), 'Finish workout button exists' );

// 8. Completion celebration screen exists.
assert_test( false !== strpos( $html, 'gear-engine-program__completion-card' ), 'Completion celebration card is present' );
assert_test( false !== strpos( $html, 'Flott gjennomført!' ), 'Celebration message is present' );

// 9. Equipment recommendation card with free alternative and shop button.
assert_test( false !== strpos( $html, 'Gratis hjemme:' ), 'Gratis hjemme tip is present' );
assert_test( false !== strpos( $html, 'Se treningsmatter i Styrkeguiden' ), 'Equipment buying guide button is present' );

// 10. List view container exists (for print and list toggle).
assert_test( false !== strpos( $html, 'gear-engine-program__list-view' ), 'List view container is present' );
assert_test( false !== strpos( $html, 'data-jump-step' ), 'Start step button from list view is present' );

echo "========================================================\n";
echo "ALL STEPPER TESTS PASSED ({$tests_passed}/{$tests_passed})!\n";
echo "========================================================\n";
