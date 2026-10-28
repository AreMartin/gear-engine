<?php
/**
 * Architecture guard tests (run: php tests/architecture.test.php).
 *
 * Enforces the plugin's core boundary rules:
 * 1. Feature modules read shared content (gear products, exercise library)
 *    through the core services, never by querying the CPTs directly.
 * 2. Every concrete plugin class is present in the autoloader class map.
 *
 * @package GearEngine
 */

$root = dirname( __DIR__ );

$failures = 0;
function t( $name, $ok ) {
	global $failures;
	echo ( $ok ? '[PASS] ' : '[FAIL] ' ) . $name . "\n";
	if ( ! $ok ) {
		$failures++;
	}
}

// ---------------------------------------------------------------------------
// Rule 1: feature modules must not query the shared CPTs directly.
// ---------------------------------------------------------------------------
$consumer_dirs = array(
	'modules/triage',
	'modules/physio-chat',
	'modules/gear-picker',
	'modules/admin',
);

$forbidden = "/([\"'])post_type\1\s*=>\s*(?:Gear_Engine_CPT::POST_TYPE|GAE_CPT::POST_TYPE|Gear_Engine_Exercises_CPT::GEAR_ENGINE_POST_TYPE|([\"'])exercise_gear\3|([\"'])gear_engine_exercise\4)/";

$violations = array();
foreach ( $consumer_dirs as $dir ) {
	$iterator = new RecursiveIteratorIterator( new RecursiveDirectoryIterator( $root . DIRECTORY_SEPARATOR . $dir, FilesystemIterator::SKIP_DOTS ) );
	foreach ( $iterator as $file ) {
		if ( 'php' !== strtolower( $file->getExtension() ) ) {
			continue;
		}
		$code = (string) file_get_contents( $file->getPathname() );
		if ( preg_match( $forbidden, $code ) ) {
			$violations[] = str_replace( $root . DIRECTORY_SEPARATOR, '', $file->getPathname() );
		}
	}
}

t(
	'architecture: feature modules read shared CPTs only through core services' . ( $violations ? ' -> ' . implode( ', ', $violations ) : '' ),
	empty( $violations )
);

// ---------------------------------------------------------------------------
// Rule 2: the autoloader class map covers every concrete plugin class.
// ---------------------------------------------------------------------------
define( 'ABSPATH', $root . DIRECTORY_SEPARATOR );
define( 'GEAR_ENGINE_PATH', $root . DIRECTORY_SEPARATOR );
require $root . '/includes/class-gear-engine-autoloader.php';

$class_map = gear_engine_class_map();

$unmapped  = array();
$scan_dirs = array( 'includes', 'modules' );
foreach ( $scan_dirs as $dir ) {
	$iterator = new RecursiveIteratorIterator( new RecursiveDirectoryIterator( $root . DIRECTORY_SEPARATOR . $dir, FilesystemIterator::SKIP_DOTS ) );
	foreach ( $iterator as $file ) {
		if ( 'php' !== strtolower( $file->getExtension() ) ) {
			continue;
		}
		$code = (string) file_get_contents( $file->getPathname() );
		if ( preg_match_all( '/^\s*(?:final\s+|abstract\s+)?class\s+([A-Za-z_][A-Za-z0-9_]*)/m', $code, $m ) ) {
			foreach ( array_filter( $m[1] ) as $class ) {
				if ( ! isset( $class_map[ $class ] ) ) {
					$unmapped[] = $class . ' (' . str_replace( $root . DIRECTORY_SEPARATOR, '', $file->getPathname() ) . ')';
				}
			}
		}
	}
}

t(
	'architecture: autoloader class map covers every plugin class' . ( $unmapped ? ' -> ' . implode( ', ', $unmapped ) : '' ),
	empty( $unmapped )
);

// Class map entries must point at files that exist.
$missing_files = array();
foreach ( $class_map as $class => $path ) {
	if ( '' === $path || ! is_readable( $path ) ) {
		$missing_files[] = $class;
	}
}
t(
	'architecture: every class map entry resolves to a readable file' . ( $missing_files ? ' -> ' . implode( ', ', $missing_files ) : '' ),
	empty( $missing_files )
);

echo "\n" . ( 0 === $failures ? 'ARCHITECTURE OK' : 'ARCHITECTURE FAILURES: ' . $failures ) . "\n";
exit( 0 === $failures ? 0 : 1 );
