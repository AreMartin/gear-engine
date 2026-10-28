<?php
/**
 * Public protocol (training program) page.
 *
 * Renders an approved protocol with its phases and exercises. It is feature-neutral:
 * the triage result page links here, and so can any other page, without sharing code
 * or assets with the AI chat.
 *
 * @package GearEngine
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

/**
 * Protocol viewer shortcode and helpers.
 */
class Gear_Engine_Protocol_Viewer {

	public const SHORTCODE_TAG      = 'gear_engine_protocol';
	public const OPTION_PAGE_PATH   = 'gear_engine_program_page_path';
	public const DEFAULT_PAGE_PATH  = 'treningsprogram';
	public const QUERY_VAR          = 'program';
	private const EXERCISE_LIMIT    = 50;

	/**
	 * Constructor.
	 */
	public function __construct() {
		add_action( 'init', array( $this, 'register_shortcode' ) );
		add_action( 'wp_enqueue_scripts', array( $this, 'register_assets' ) );
		add_action( 'admin_init', array( $this, 'register_settings' ) );
	}

	/**
	 * Sanitize the program page path into slug segments joined by slashes.
	 *
	 * @param mixed $value Raw value.
	 * @return string
	 */
	public static function sanitize_page_path( $value ): string {
		$segments = array_filter( array_map( 'sanitize_title', explode( '/', is_scalar( $value ) ? (string) $value : '' ) ) );
		return $segments ? implode( '/', $segments ) : self::DEFAULT_PAGE_PATH;
	}

	/**
	 * Configured path of the page holding [gear_engine_protocol].
	 *
	 * @return string
	 */
	public static function get_page_path(): string {
		return self::sanitize_page_path( get_option( self::OPTION_PAGE_PATH, self::DEFAULT_PAGE_PATH ) );
	}

	/**
	 * Public URL of a protocol's program page.
	 *
	 * @param string $slug Protocol slug.
	 * @return string
	 */
	public static function get_program_url( string $slug ): string {
		$url = trailingslashit( home_url( self::get_page_path() ) ) . '?' . self::QUERY_VAR . '=' . rawurlencode( sanitize_title( $slug ) );

		/**
		 * Filters the URL of a protocol's program page.
		 *
		 * @param string $url  Default URL.
		 * @param string $slug Protocol slug.
		 */
		return (string) apply_filters( 'gear_engine_program_url', $url, $slug );
	}

	/**
	 * Register the program page path setting (shown on the Smertesjekk settings page).
	 *
	 * @return void
	 */
	public function register_settings(): void {
		register_setting(
			'gear_engine_triage',
			self::OPTION_PAGE_PATH,
			array(
				'type'              => 'string',
				'sanitize_callback' => array( __CLASS__, 'sanitize_page_path' ),
				'default'           => self::DEFAULT_PAGE_PATH,
			)
		);
	}

	/**
	 * Register shortcode.
	 *
	 * @return void
	 */
	public function register_shortcode(): void {
		add_shortcode( self::SHORTCODE_TAG, array( $this, 'render' ) );
	}

	/**
	 * Register client assets.
	 *
	 * @return void
	 */
	public function register_assets(): void {
		$base = GEAR_ENGINE_URL . 'modules/exercises/assets/';
		wp_register_style( 'gear-engine-protocol-viewer', $base . 'css/protocol-viewer.css', array( 'gear-engine-tokens' ), GEAR_ENGINE_VERSION );
		wp_register_script( 'gear-engine-protocol-viewer', $base . 'js/protocol-viewer.js', array(), GEAR_ENGINE_VERSION, true );
	}

	/**
	 * Approved, in-date top-level protocol by slug, or null.
	 *
	 * @param string $slug Protocol slug.
	 * @return array<string, mixed>|null
	 */
	public static function get_approved_protocol( string $slug ): ?array {
		$slug = sanitize_title( $slug );
		if ( '' === $slug || ! Gear_Engine_Protocols::is_enabled() ) {
			return null;
		}
		$term = get_term_by( 'slug', $slug, Gear_Engine_Protocols::GEAR_ENGINE_TAXONOMY );
		if ( ! $term || is_wp_error( $term ) || 0 !== (int) $term->parent ) {
			return null;
		}
		if ( Gear_Engine_Protocols::GEAR_ENGINE_STATUS_APPROVED !== Gear_Engine_Protocols::get_status( (int) $term->term_id ) ) {
			return null;
		}
		return Gear_Engine_Protocols::get_protocol_data( $term );
	}

	/**
	 * Exercises of a protocol grouped by phase. Exercises assigned straight to the protocol follow the phases.
	 *
	 * @param array<string, mixed> $protocol Result of get_approved_protocol().
	 * @return array<int, array{name:string,description:string,exercises:array<int,array<string,mixed>>}>
	 */
	public static function get_phase_groups( array $protocol ): array {
		$phases = get_terms(
			array(
				'taxonomy'   => Gear_Engine_Protocols::GEAR_ENGINE_TAXONOMY,
				'hide_empty' => false,
				'parent'     => (int) $protocol['term_id'],
				'orderby'    => 'name',
			)
		);
		$phases = is_array( $phases ) ? $phases : array();

		$seen   = array();
		$groups = array();
		$take   = static function ( string $slug ) use ( &$seen ): array {
			$list = array();
			foreach ( Gear_Engine_Exercise_Service::search_exercises( array( 'protocol' => $slug, 'limit' => self::EXERCISE_LIMIT ) ) as $exercise ) {
				if ( ! isset( $seen[ $exercise['id'] ] ) ) {
					$seen[ $exercise['id'] ] = true;
					$list[]                  = $exercise;
				}
			}
			return $list;
		};

		foreach ( $phases as $phase ) {
			$exercises = $take( (string) $phase->slug );
			if ( $exercises ) {
				$groups[] = array(
					'name'        => (string) $phase->name,
					'description' => (string) $phase->description,
					'exercises'   => $exercises,
				);
			}
		}

		$loose = $take( (string) $protocol['slug'] );
		if ( $loose ) {
			$groups[] = array(
				'name'        => $groups ? __( 'Øvrige øvelser', 'gear-engine' ) : '',
				'description' => '',
				'exercises'   => $loose,
			);
		}

		return $groups;
	}

	/**
	 * Whitelisted embed URL for an exercise video, or an empty string.
	 *
	 * Only Exorlive, YouTube (no-cookie) and Vimeo player URLs are ever returned.
	 *
	 * @param array<string, mixed> $exercise Result of Gear_Engine_Exercise_Service::get_exercise_data().
	 * @return string
	 */
	public static function get_embed_url( array $exercise ): string {
		$exorlive_id = (string) ( $exercise['exorlive_id'] ?? '' );
		if ( preg_match( '/^\d+$/', $exorlive_id ) ) {
			$culture = (string) ( $exercise['video_culture'] ?? 'nb-NO' );
			$culture = preg_match( '/^[a-z]{2}-[A-Z]{2}$/', $culture ) ? $culture : 'nb-NO';
			return 'https://exorlive.com/video/?culture=' . rawurlencode( $culture ) . '&ex=' . rawurlencode( $exorlive_id ) . '&autoplay=true&repeat=true&hd=true';
		}

		$url = (string) ( $exercise['video_url'] ?? '' );
		if ( preg_match( '#^https://player\.vimeo\.com/video/([0-9]+)(?:\?h=([A-Za-z0-9]+))?$#', $url, $m ) ) {
			return 'https://player.vimeo.com/video/' . $m[1] . '?autoplay=1' . ( ! empty( $m[2] ) ? '&h=' . $m[2] : '' );
		}
		if ( preg_match( '#^https://(?:www\.)?(?:youtube\.com/watch\?v=|youtu\.be/)([A-Za-z0-9_-]{11})(?:[&?].*)?$#', $url, $m ) ) {
			return 'https://www.youtube-nocookie.com/embed/' . $m[1] . '?autoplay=1&rel=0';
		}
		return '';
	}

	/**
	 * Approved protocols that have at least one exercise (used by the index view).
	 *
	 * @return array<int, array<string, mixed>>
	 */
	public static function get_listed_protocols(): array {
		if ( ! Gear_Engine_Protocols::is_enabled() ) {
			return array();
		}
		$terms = get_terms(
			array(
				'taxonomy'   => Gear_Engine_Protocols::GEAR_ENGINE_TAXONOMY,
				'hide_empty' => false,
				'parent'     => 0,
				'orderby'    => 'name',
				'meta_key'   => Gear_Engine_Protocols::GEAR_ENGINE_META_STATUS,
				'meta_value' => Gear_Engine_Protocols::GEAR_ENGINE_STATUS_APPROVED,
			)
		);
		$list = array();
		foreach ( is_array( $terms ) ? $terms : array() as $term ) {
			if ( Gear_Engine_Protocols::GEAR_ENGINE_STATUS_APPROVED !== Gear_Engine_Protocols::get_status( (int) $term->term_id ) ) {
				continue;
			}
			if ( ! Gear_Engine_Exercise_Service::search_exercises( array( 'protocol' => (string) $term->slug, 'limit' => 1 ) ) ) {
				continue;
			}
			$list[] = Gear_Engine_Protocols::get_protocol_data( $term );
		}
		return $list;
	}

	/**
	 * Render [gear_engine_protocol]. With ?program=slug (or slug="...") shows one program, otherwise an index.
	 *
	 * @param array|string $atts Shortcode attributes.
	 * @return string
	 */
	public function render( $atts = array() ): string {
		$atts = shortcode_atts( array( 'slug' => '' ), is_array( $atts ) ? $atts : array(), self::SHORTCODE_TAG );
		$slug = sanitize_title( (string) $atts['slug'] );
		if ( '' === $slug && isset( $_GET[ self::QUERY_VAR ] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only display of public content.
			$slug = sanitize_title( wp_unslash( $_GET[ self::QUERY_VAR ] ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
		}

		wp_enqueue_style( 'gear-engine-protocol-viewer' );
		wp_enqueue_script( 'gear-engine-protocol-viewer' );

		ob_start();
		if ( '' === $slug ) {
			$protocols = self::get_listed_protocols();
			include GEAR_ENGINE_PATH . 'modules/exercises/templates/protocol-index.php';
		} else {
			$protocol = self::get_approved_protocol( $slug );
			$groups   = $protocol ? self::get_phase_groups( $protocol ) : array();
			if ( $protocol && $groups ) {
				include GEAR_ENGINE_PATH . 'modules/exercises/templates/protocol-view.php';
			} else {
				echo '<div class="gear-engine-program"><p class="gear-engine-program__empty">' . esc_html__( 'Dette treningsprogrammet er ikke tilgjengelig.', 'gear-engine' ) . '</p></div>';
			}
		}
		return (string) ob_get_clean();
	}
}
