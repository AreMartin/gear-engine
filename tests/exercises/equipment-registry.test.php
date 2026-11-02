<?php
/**
 * Tests for Gear_Engine_Equipment_Registry.
 *
 * @package GearEngine
 */

if ( ! defined( 'ABSPATH' ) ) {
	define( 'ABSPATH', __DIR__ . '/../../' );
}

if ( ! defined( 'GEAR_ENGINE_VERSION' ) ) {
	define( 'GEAR_ENGINE_VERSION', '1.0.0' );
}

if ( ! function_exists( '__' ) ) {
	function __( $text, $domain = 'default' ) {
		return $text;
	}
}

if ( ! function_exists( 'esc_html__' ) ) {
	function esc_html__( $text, $domain = 'default' ) {
		return $text;
	}
}

if ( ! function_exists( 'apply_filters' ) ) {
	function apply_filters( $tag, $value ) {
		return $value;
	}
}

if ( ! function_exists( 'add_query_arg' ) ) {
	function add_query_arg( $args, $url ) {
		return $url . '?' . http_build_query( $args );
	}
}

require_once __DIR__ . '/../../modules/exercises/class-gear-engine-equipment-registry.php';

$failures = 0;
function t( string $name, bool $ok ): void {
	global $failures;
	if ( $ok ) {
		echo "[PASS] {$name}\n";
	} else {
		echo "[FAIL] {$name}\n";
		$failures++;
	}
}

// 1. Registry dictionary integrity
$reg = Gear_Engine_Equipment_Registry::get_registry();
t( 'registry: has strikk', isset( $reg['strikk'] ) );
t( 'registry: has balansepute', isset( $reg['balansepute'] ) );
t( 'registry: has foam_roller', isset( $reg['foam_roller'] ) );
t( 'registry: has manualer', isset( $reg['manualer'] ) );
t( 'registry: strikk has free alternative', ! empty( $reg['strikk']['free_alt'] ) );
t( 'registry: strikk has picker url', false !== strpos( $reg['strikk']['picker_url'], 'rehab-guide' ) );

// 2. Term resolution & alias matching
$miniband = Gear_Engine_Equipment_Registry::resolve_term( 'miniband' );
t( 'resolve: miniband maps to strikk', $miniband && 'strikk' === $miniband['key'] );

$vippebrett = Gear_Engine_Equipment_Registry::resolve_term( 'Vippebrett' );
t( 'resolve: case insensitive Vippebrett maps to balansepute', $vippebrett && 'balansepute' === $vippebrett['key'] );

$roller = Gear_Engine_Equipment_Registry::resolve_term( 'foam-roller' );
t( 'resolve: foam-roller maps to foam_roller', $roller && 'foam_roller' === $roller['key'] );

$bodyweight = Gear_Engine_Equipment_Registry::resolve_term( 'egenvekt' );
t( 'resolve: egenvekt is marked bodyweight', $bodyweight && true === $bodyweight['is_bodyweight'] );

// 3. Equipment list resolution
$terms = array( 'miniband', 'treningsmatte', 'kroppsvekt' );
$resolved_all = Gear_Engine_Equipment_Registry::resolve_equipment_list( $terms, false );
t( 'list: resolves 3 unique items', 3 === count( $resolved_all ) );
t( 'list: includes strikk', isset( $resolved_all['strikk'] ) );
t( 'list: includes matte', isset( $resolved_all['matte'] ) );
t( 'list: includes egenvekt', isset( $resolved_all['egenvekt'] ) );

$resolved_gear_only = Gear_Engine_Equipment_Registry::resolve_equipment_list( $terms, true );
t( 'list: excludes bodyweight when flag is true', 2 === count( $resolved_gear_only ) && ! isset( $resolved_gear_only['egenvekt'] ) );

exit( $failures ? 1 : 0 );

