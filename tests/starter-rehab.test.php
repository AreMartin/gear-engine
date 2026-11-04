<?php
/**
 * Tests for Gear_Engine_Starter_Rehab:
 * Seeding starter protocols and exercises, triage referral resolution, and production cleanup.
 *
 * Run with: php tests/starter-rehab.test.php
 */

define( 'ABSPATH', __DIR__ . '/../' );
define( 'GEAR_ENGINE_PATH', ABSPATH );
define( 'GEAR_ENGINE_URL', 'http://example.com/wp-content/plugins/gear-engine/' );
define( 'GEAR_ENGINE_VERSION', '1.0.0' );
define( 'GEAR_ENGINE_TESTING', true );

// Mock WordPress environment.
$GLOBALS['mock_options']   = array();
$GLOBALS['mock_posts']     = array();
$GLOBALS['mock_post_meta'] = array();
$GLOBALS['mock_terms']     = array();
$GLOBALS['mock_term_meta'] = array();
$GLOBALS['mock_post_id']   = 1000;
$GLOBALS['mock_term_id']   = 2000;

function add_action() {}
function add_filter() {}
function apply_filters( $tag, $value ) { return $value; }
function __( $s ) { return $s; }
function esc_html__( $s ) { return $s; }
function esc_attr__( $s ) { return $s; }
function esc_html( $s ) { return $s; }
function esc_attr( $s ) { return $s; }
function esc_url_raw( $s ) { return (string) $s; }
function trailingslashit( $s ) { return rtrim( (string) $s, '/' ) . '/'; }
function sanitize_key( $k ) { return preg_replace( '/[^a-z0-9_\-]/', '', strtolower( (string) $k ) ); }
function sanitize_title( $t ) { return trim( preg_replace( '/[^a-z0-9]+/', '-', strtolower( (string) $t ) ), '-' ); }
function sanitize_text_field( $s ) { return trim( strip_tags( (string) $s ) ); }
function current_time( $t ) { return '2026-11-04 12:00:00'; }
function is_wp_error( $thing ) { return false; }
function get_current_user_id() { return 1; }
function absint( $x ) { return abs( (int) $x ); }
function wp_strip_all_tags( $s ) { return strip_tags( (string) $s ); }
function wp_parse_url( $u ) { return parse_url( (string) $u ); }

class WP_Post {
	public $ID;
	public $post_type;
	public $post_status;
	public $post_title;
	public $post_excerpt;
	public $post_content;
}

function wp_get_post_terms( $post_id, $taxonomy, $args = array() ) {
	return $GLOBALS['object_terms'][ $post_id ][ $taxonomy ] ?? array();
}

function get_option( $k, $d = false ) {
	return $GLOBALS['mock_options'][ $k ] ?? $d;
}
function update_option( $k, $v, $autoload = null ) {
	$GLOBALS['mock_options'][ $k ] = $v;
	return true;
}
function delete_option( $k ) {
	unset( $GLOBALS['mock_options'][ $k ] );
	return true;
}

function update_post_meta( $id, $k, $v ) {
	$GLOBALS['mock_post_meta'][ $id ][ $k ] = $v;
	return true;
}
function get_post_meta( $id, $k, $single = false ) {
	return $GLOBALS['mock_post_meta'][ $id ][ $k ] ?? ( $single ? '' : array() );
}
function delete_post_meta( $id, $k ) {
	unset( $GLOBALS['mock_post_meta'][ $id ][ $k ] );
	return true;
}

function update_term_meta( $id, $k, $v ) {
	$GLOBALS['mock_term_meta'][ $id ][ $k ] = $v;
	return true;
}
function get_term_meta( $id, $k, $single = false ) {
	return $GLOBALS['mock_term_meta'][ $id ][ $k ] ?? ( $single ? '' : array() );
}
function delete_term_meta( $id, $k ) {
	unset( $GLOBALS['mock_term_meta'][ $id ][ $k ] );
	return true;
}

function wp_insert_post( $args, $wp_error = false ) {
	$id = ++$GLOBALS['mock_post_id'];
	$post = new WP_Post();
	$post->ID           = $id;
	$post->post_type    = $args['post_type'] ?? 'post';
	$post->post_status  = $args['post_status'] ?? 'publish';
	$post->post_title   = $args['post_title'] ?? '';
	$post->post_excerpt = $args['post_excerpt'] ?? '';
	$post->post_content = $args['post_content'] ?? '';
	$GLOBALS['mock_posts'][ $id ] = $post;
	return $id;
}

function wp_delete_post( $id, $force = false ) {
	unset( $GLOBALS['mock_posts'][ $id ] );
	unset( $GLOBALS['mock_post_meta'][ $id ] );
	return true;
}

function get_posts( $args = array() ) {
	$results = array();
	$meta_key   = $args['meta_key'] ?? '';
	$meta_value = $args['meta_value'] ?? null;
	$meta_query = $args['meta_query'] ?? array();

	foreach ( $GLOBALS['mock_posts'] as $id => $post ) {
		if ( isset( $args['post_type'] ) && $post->post_type !== $args['post_type'] ) {
			continue;
		}

		// Simple meta key check.
		if ( '' !== $meta_key ) {
			if ( ! isset( $GLOBALS['mock_post_meta'][ $id ][ $meta_key ] ) ) {
				continue;
			}
			if ( null !== $meta_value && (string) $GLOBALS['mock_post_meta'][ $id ][ $meta_key ] !== (string) $meta_value ) {
				continue;
			}
		}

		// Meta query check.
		if ( ! empty( $meta_query ) ) {
			$match = false;
			foreach ( $meta_query as $clause ) {
				if ( is_array( $clause ) && isset( $clause['key'] ) ) {
					$k   = $clause['key'];
					$cmp = $clause['compare'] ?? '=';
					if ( 'EXISTS' === $cmp && isset( $GLOBALS['mock_post_meta'][ $id ][ $k ] ) ) {
						$match = true;
						break;
					}
					if ( '=' === $cmp && isset( $GLOBALS['mock_post_meta'][ $id ][ $k ] ) && (string) $GLOBALS['mock_post_meta'][ $id ][ $k ] === (string) $clause['value'] ) {
						$match = true;
						break;
					}
				}
			}
			if ( ! $match && ! empty( $meta_query ) && 'OR' === ( $meta_query['relation'] ?? 'AND' ) ) {
				continue;
			}
		}

		$results[] = ( 'ids' === ( $args['fields'] ?? '' ) ) ? $id : $post;
	}

	return $results;
}

function wp_insert_term( $term, $taxonomy, $args = array() ) {
	$slug   = $args['slug'] ?? sanitize_title( $term );
	$parent = $args['parent'] ?? 0;

	foreach ( $GLOBALS['mock_terms'] as $t ) {
		if ( $t->taxonomy === $taxonomy && $t->slug === $slug ) {
			return array( 'term_id' => $t->term_id, 'term_taxonomy_id' => $t->term_id );
		}
	}

	$id = ++$GLOBALS['mock_term_id'];
	$obj = (object) array(
		'term_id'  => $id,
		'name'     => $term,
		'slug'     => $slug,
		'taxonomy' => $taxonomy,
		'parent'   => $parent,
	);
	$GLOBALS['mock_terms'][ $id ] = $obj;
	return array( 'term_id' => $id, 'term_taxonomy_id' => $id );
}

function get_term_by( $field, $value, $taxonomy ) {
	foreach ( $GLOBALS['mock_terms'] as $t ) {
		if ( $t->taxonomy === $taxonomy && ( ( 'slug' === $field && $t->slug === $value ) || ( 'id' === $field && $t->term_id == $value ) ) ) {
			return $t;
		}
	}
	return false;
}

function get_term( $id, $taxonomy = '' ) {
	return $GLOBALS['mock_terms'][ $id ] ?? null;
}

function get_terms( $args = array() ) {
	$tax = $args['taxonomy'] ?? '';
	$parent = isset( $args['parent'] ) ? (int) $args['parent'] : null;
	$meta_query = $args['meta_query'] ?? array();
	$res = array();

	foreach ( $GLOBALS['mock_terms'] as $id => $t ) {
		if ( '' !== $tax && $t->taxonomy !== $tax ) {
			continue;
		}
		if ( null !== $parent && (int) $t->parent !== $parent ) {
			continue;
		}
		if ( isset( $args['meta_key'], $args['meta_value'] ) ) {
			$mk = $args['meta_key'];
			$mv = (string) $args['meta_value'];
			if ( ! isset( $GLOBALS['mock_term_meta'][ $id ][ $mk ] ) || (string) $GLOBALS['mock_term_meta'][ $id ][ $mk ] !== $mv ) {
				continue;
			}
		}
		if ( ! empty( $meta_query ) ) {
			$match = false;
			foreach ( $meta_query as $clause ) {
				if ( is_array( $clause ) && isset( $clause['key'] ) ) {
					$k = $clause['key'];
					$cmp = $clause['compare'] ?? '=';
					if ( 'EXISTS' === $cmp && isset( $GLOBALS['mock_term_meta'][ $id ][ $k ] ) ) {
						$match = true;
						break;
					}
					if ( '=' === $cmp && isset( $GLOBALS['mock_term_meta'][ $id ][ $k ] ) && (string) $GLOBALS['mock_term_meta'][ $id ][ $k ] === (string) $clause['value'] ) {
						$match = true;
						break;
					}
				}
			}
			if ( ! $match ) {
				continue;
			}
		}
		$res[] = $t;
	}
	return $res;
}

function wp_delete_term( $term_id, $taxonomy ) {
	unset( $GLOBALS['mock_terms'][ $term_id ] );
	unset( $GLOBALS['mock_term_meta'][ $term_id ] );
	return true;
}

$GLOBALS['object_terms'] = array();
function wp_set_object_terms( $object_id, $terms, $taxonomy, $append = false ) {
	$terms = (array) $terms;
	if ( ! isset( $GLOBALS['object_terms'][ $object_id ][ $taxonomy ] ) || ! $append ) {
		$GLOBALS['object_terms'][ $object_id ][ $taxonomy ] = array();
	}
	foreach ( $terms as $term ) {
		if ( is_object( $term ) ) {
			$term_str = (string) $term->slug;
		} elseif ( is_numeric( $term ) ) {
			$t = get_term( (int) $term, $taxonomy );
			$term_str = $t ? (string) $t->slug : (string) $term;
		} else {
			$term_str = (string) $term;
		}
		$GLOBALS['object_terms'][ $object_id ][ $taxonomy ][] = $term_str;
	}
	$GLOBALS['object_terms'][ $object_id ][ $taxonomy ] = array_unique( $GLOBALS['object_terms'][ $object_id ][ $taxonomy ] );
	return $terms;
}

class WP_Query {
	public $posts = array();
	public function __construct( $args = array() ) {
		$posts = array();
		$post_type = $args['post_type'] ?? 'post';
		$tax_query = $args['tax_query'] ?? array();
		$meta_query = $args['meta_query'] ?? array();

		foreach ( $GLOBALS['mock_posts'] as $id => $p ) {
			if ( $p->post_type !== $post_type ) {
				continue;
			}
			// tax_query check
			$tax_match = true;
			foreach ( $tax_query as $tq ) {
				if ( is_array( $tq ) && isset( $tq['taxonomy'], $tq['terms'] ) ) {
					$tax = $tq['taxonomy'];
					$allowed = (array) $tq['terms'];
					$has = $GLOBALS['object_terms'][ $id ][ $tax ] ?? array();
					$field = $tq['field'] ?? 'slug';
					$intersect = false;
					foreach ( $allowed as $al ) {
						if ( in_array( (string) $al, $has, true ) ) {
							$intersect = true;
							break;
						}
						// If checking term ID, look up slug
						if ( 'id' === $field || 'term_id' === $field ) {
							$term_obj = get_term( (int) $al, $tax );
							if ( $term_obj && in_array( (string) $term_obj->slug, $has, true ) ) {
								$intersect = true;
								break;
							}
						}
					}
					if ( ! $intersect ) {
						$tax_match = false;
						break;
					}
				}
			}
			if ( ! $tax_match ) {
				continue;
			}

			// meta_query check
			$meta_match = true;
			foreach ( $meta_query as $mq ) {
				if ( is_array( $mq ) && isset( $mq['key'], $mq['value'] ) ) {
					$val = $GLOBALS['mock_post_meta'][ $id ][ $mq['key'] ] ?? '';
					$allowed = (array) $mq['value'];
					if ( ! in_array( $val, $allowed, true ) ) {
						$meta_match = false;
						break;
					}
				}
			}
			if ( ! $meta_match ) {
				continue;
			}

			$posts[] = ( 'ids' === ( $args['fields'] ?? '' ) ) ? $id : $p;
		}
		$this->posts = $posts;
	}
}

// Load required classes
require_once ABSPATH . 'includes/class-gear-engine-config-loader.php';
require_once ABSPATH . 'modules/exercises/class-gear-engine-exercises-cpt.php';
require_once ABSPATH . 'modules/exercises/class-gear-engine-protocols.php';
require_once ABSPATH . 'modules/exercises/class-gear-engine-exercise-service.php';
require_once ABSPATH . 'modules/exercises/class-gear-engine-protocol-viewer.php';
require_once ABSPATH . 'modules/triage/class-gear-engine-triage.php';
require_once ABSPATH . 'modules/exercises/class-gear-engine-starter-rehab.php';

$failed = 0;
$passed = 0;

function assert_test( bool $condition, string $message ): void {
	global $failed, $passed;
	if ( $condition ) {
		echo " [PASS] $message\n";
		$passed++;
	} else {
		echo " [FAIL] $message\n";
		$failed++;
	}
}

echo "========================================================\n";
echo "Testing Gear Engine Starter Rehab (Seeder & Teardown)...\n";
echo "========================================================\n";

// 1. Initial State
assert_test( ! get_option( Gear_Engine_Starter_Rehab::OPTION_SEEDED, false ), 'Starter option initially not set' );

// 2. Seeding Starter Content
$seed_result = Gear_Engine_Starter_Rehab::seed_starter_content();
assert_test( $seed_result['exercises'] === 24, 'Exactly 24 starter exercises seeded' );
assert_test( $seed_result['protocols'] === 9, 'Exactly 9 starter protocols seeded' );
assert_test( get_option( Gear_Engine_Starter_Rehab::OPTION_SEEDED, false ) === 1, 'Starter option set to 1 after seeding' );

// 3. Verify Exercise Properties across Areas
$areas_found = array();
foreach ( $GLOBALS['mock_posts'] as $id => $p ) {
	$area = $GLOBALS['object_terms'][ $id ]['gear_engine_exercise_area'][0] ?? '';
	if ( '' !== $area ) {
		$areas_found[ $area ] = true;
	}
	$risk = $GLOBALS['mock_post_meta'][ $id ]['_gear_engine_exercise_risk'] ?? '';
	$is_sample = $GLOBALS['mock_post_meta'][ $id ]['_gear_engine_is_sample'] ?? 0;
	assert_test( in_array( $risk, array( 'low', 'moderate' ), true ), "Exercise ID $id has valid risk level ($risk)" );
	assert_test( $is_sample === 1, "Exercise ID $id marked as sample" );
}
assert_test( count( $areas_found ) === 6, 'Exercises span all 6 triage areas (kne, rygg, skulder, fot-ankel, hofte, nakke)' );

// 4. Verify Protocol Approvals and Phases
foreach ( Gear_Engine_Starter_Rehab::get_protocol_definitions() as $proto_def ) {
	$term = get_term_by( 'slug', $proto_def['slug'], Gear_Engine_Protocols::GEAR_ENGINE_TAXONOMY );
	assert_test( false !== $term, "Protocol term {$proto_def['slug']} exists" );
	$status = get_term_meta( $term->term_id, Gear_Engine_Protocols::GEAR_ENGINE_META_STATUS, true );
	assert_test( Gear_Engine_Protocols::GEAR_ENGINE_STATUS_APPROVED === $status, "Protocol {$proto_def['slug']} is approved" );
	$codes = get_term_meta( $term->term_id, Gear_Engine_Protocols::GEAR_ENGINE_META_CODES, true );
	assert_test( '' !== $codes, "Protocol {$proto_def['slug']} has clinical codes ($codes)" );
}

// 5. Test Triage Resolution via find_program_slug()
// Kne ITBS -> loperkne
$itbs_dx = array( 'icd10' => 'M76.3', 'icpc2' => 'L96', 'title' => 'Smerte på utsiden av kneet (løperkne)' );
$resolved_itbs = Gear_Engine_Triage::find_program_slug( $itbs_dx );
assert_test( $resolved_itbs === 'loperkne', 'Triage ITBS resolves to approved loperkne protocol' );

// Kne PFPS -> patellofemoralt-smertesyndrom
$pfps_dx = array( 'icd10' => 'M22.2', 'icpc2' => 'L96', 'title' => 'Smerte foran i kneet (PFPS)' );
$resolved_pfps = Gear_Engine_Triage::find_program_slug( $pfps_dx );
assert_test( $resolved_pfps === 'patellofemoralt-smertesyndrom', 'Triage PFPS resolves to approved PFPS protocol' );

// Rygg Lumbago -> akutt-lumbago
$lumbago_dx = array( 'icd10' => 'M54.5', 'icpc2' => 'L03', 'title' => 'Akutt Lumbago (Kink i ryggen)' );
$resolved_lumbago = Gear_Engine_Triage::find_program_slug( $lumbago_dx );
assert_test( in_array( $resolved_lumbago, array( 'akutt-lumbago', 'uspesifikke-korsryggsmerter' ), true ), "Triage Lumbago resolves to back protocol ($resolved_lumbago)" );

// Skulder SAPS -> subakromiale-skuldersmerter
$saps_dx = array( 'icd10' => 'M75.4', 'icpc2' => 'L92', 'title' => 'Seneirritasjon (SAPS)' );
$resolved_saps = Gear_Engine_Triage::find_program_slug( $saps_dx );
assert_test( $resolved_saps === 'subakromiale-skuldersmerter', 'Triage SAPS resolves to subakromiale-skuldersmerter' );

// Fot Plantar Fasciitt -> plantar-fasciitt
$pf_dx = array( 'icd10' => 'M72.2', 'icpc2' => 'L99', 'title' => 'Plantar fasciitt' );
$resolved_pf = Gear_Engine_Triage::find_program_slug( $pf_dx );
assert_test( $resolved_pf === 'plantar-fasciitt', 'Triage Plantar Fasciitt resolves to plantar-fasciitt' );

// Fot Akillestendinopati -> akillestendinopati
$ach_dx = array( 'icd10' => 'M76.6', 'icpc2' => 'L87', 'title' => 'Akillestendinopati' );
$resolved_ach = Gear_Engine_Triage::find_program_slug( $ach_dx );
assert_test( $resolved_ach === 'akillestendinopati', 'Triage Akillestendinopati resolves to akillestendinopati' );

// Hofte GTPS -> trokanterbursitt-gtps
$gtps_dx = array( 'icd10' => 'M70.6', 'icpc2' => 'L89', 'title' => 'GTPS' );
$resolved_gtps = Gear_Engine_Triage::find_program_slug( $gtps_dx );
assert_test( $resolved_gtps === 'trokanterbursitt-gtps', 'Triage GTPS resolves to trokanterbursitt-gtps' );

// Nakke Postural -> belastningsplager-nakke
$nakke_dx = array( 'icd10' => 'M54.2', 'icpc2' => 'L01', 'title' => 'Belastningsplager nakke' );
$resolved_nakke = Gear_Engine_Triage::find_program_slug( $nakke_dx );
assert_test( $resolved_nakke === 'belastningsplager-nakke', 'Triage Nakke resolves to belastningsplager-nakke' );

// 6. Test area_has_auto_exercises() for all 6 areas
$expected_areas = array( 'kne', 'rygg', 'skulder', 'fot-ankel', 'hofte', 'nakke' );
foreach ( $expected_areas as $area ) {
	assert_test( Gear_Engine_Protocol_Viewer::area_has_auto_exercises( $area ), "area_has_auto_exercises('$area') is TRUE" );
}

// 7. Test Teardown / Cleanup for Production
$clean_result = Gear_Engine_Starter_Rehab::remove_all_sample_data();
assert_test( $clean_result['deleted_exercises'] === 24, 'Cleanup removed all 24 sample exercises' );
assert_test( $clean_result['deleted_protocols'] >= 9, 'Cleanup removed all sample protocol terms' );
assert_test( count( $GLOBALS['mock_posts'] ) === 0, 'No sample exercises left in database' );
assert_test( ! get_option( Gear_Engine_Starter_Rehab::OPTION_SEEDED, false ), 'Seeded option removed after cleanup' );

// 8. Re-seed test
$reseed_result = Gear_Engine_Starter_Rehab::seed_starter_content();
assert_test( $reseed_result['exercises'] === 24, 'Re-seeding successfully restores 24 exercises' );
assert_test( $reseed_result['protocols'] === 9, 'Re-seeding successfully restores 9 protocols' );

echo "========================================================\n";
if ( $failed === 0 ) {
	echo "ALL STARTER REHAB TESTS PASSED ($passed/$passed)\n";
	exit( 0 );
} else {
	echo "TESTS FAILED ($failed failed, $passed passed)\n";
	exit( 1 );
}
