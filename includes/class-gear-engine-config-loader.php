<?php
/**
 * Shared bundled JSON config loader for feature modules.
 *
 * Triage regions and gear picker types (and future modules) each ship a
 * declarative JSON config. This owns the read/decode/validate/cache path so
 * every module gets identical safety rules (slug whitelist, valid JSON,
 * per-request caching) while keeping its own shape validation and error type.
 *
 * @package GearEngine
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

/**
 * Class Gear_Engine_Config_Loader
 */
class Gear_Engine_Config_Loader {

	/**
	 * Per-request decoded config cache, keyed by "dir|slug".
	 *
	 * @var array<string, array<string, mixed>|null>
	 */
	private static $cache = array();

	/**
	 * Load, decode and validate one bundled JSON config file.
	 *
	 * @param string        $dir       Absolute directory containing the configs.
	 * @param string        $slug      Config slug (filename without .json).
	 * @param callable|null $validator Optional shape check; receives decoded array.
	 * @return array<string, mixed>|null Decoded valid config, or null.
	 */
	public static function load( string $dir, string $slug, ?callable $validator = null ): ?array {
		// Slugs double as filenames: refuse anything that could traverse paths.
		if ( '' === $slug || ! preg_match( '/^[a-z0-9_\-]+$/i', $slug ) ) {
			return null;
		}

		$key = $dir . '|' . $slug;
		if ( array_key_exists( $key, self::$cache ) ) {
			return self::$cache[ $key ];
		}
		self::$cache[ $key ] = null;

		$file = rtrim( $dir, '/\\' ) . DIRECTORY_SEPARATOR . $slug . '.json';
		if ( ! is_readable( $file ) ) {
			return null;
		}

		$raw = file_get_contents( $file ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- Local bundled file.
		if ( false === $raw ) {
			return null;
		}

		$config = json_decode( $raw, true );
		if ( ! is_array( $config ) ) {
			return null;
		}

		if ( null !== $validator && ! $validator( $config ) ) {
			return null;
		}

		self::$cache[ $key ] = $config;
		return $config;
	}

	/**
	 * Clear the per-request cache (used by tests and config regeneration).
	 *
	 * @return void
	 */
	public static function flush_cache(): void {
		self::$cache = array();
	}
}
