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
	private const AUTO_LIMIT        = 8;

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
	 * URL of an automatically assembled program for a triage diagnosis.
	 *
	 * @param string $region    Triage region slug.
	 * @param string $diagnosis Diagnosis id within the region config.
	 * @return string
	 */
	public static function get_auto_program_url( string $region, string $diagnosis ): string {
		return trailingslashit( home_url( self::get_page_path() ) ) . '?region=' . rawurlencode( sanitize_key( $region ) ) . '&dx=' . rawurlencode( sanitize_key( $diagnosis ) );
	}

	/**
	 * Exercise area slug used for a triage region (a config may override it with "area").
	 *
	 * @param string               $region Region slug.
	 * @param array<string, mixed> $config Region config.
	 * @return string
	 */
	public static function get_region_area( string $region, array $config = array() ): string {
		return sanitize_title( (string) ( ! empty( $config['area'] ) ? $config['area'] : $region ) );
	}

	/**
	 * Order exercises so those whose "good for" tags occur in the text come first (stable otherwise).
	 *
	 * @param array<int, array<string, mixed>> $exercises Exercises.
	 * @param string                           $text      Text to match tags against.
	 * @return array<int, array<string, mixed>>
	 */
	public static function rank_exercises( array $exercises, string $text ): array {
		$text   = mb_strtolower( $text, 'UTF-8' );
		$scored = array();
		foreach ( $exercises as $index => $exercise ) {
			$score = 0;
			foreach ( preg_split( '/[;,]/', (string) ( $exercise['good_for'] ?? '' ) ) as $tag ) {
				$tag = mb_strtolower( trim( $tag ), 'UTF-8' );
				if ( mb_strlen( $tag, 'UTF-8' ) >= 3 && false !== mb_strpos( $text, $tag ) ) {
					++$score;
				}
			}
			$scored[] = array( $score, $index, $exercise );
		}
		usort(
			$scored,
			static function ( array $a, array $b ): int {
				return $b[0] <=> $a[0] ?: $a[1] <=> $b[1];
			}
		);
		return array_column( $scored, 2 );
	}

	/**
	 * Whether a region has any exercises that can be used for automatic programs.
	 *
	 * @param string $area Exercise area slug.
	 * @return bool
	 */
	public static function area_has_auto_exercises( string $area ): bool {
		return '' !== $area && (bool) Gear_Engine_Exercise_Service::search_exercises(
			array(
				'area'     => $area,
				'max_risk' => 'moderate',
				'limit'    => 1,
			)
		);
	}

	/**
	 * Allowed answers of the phase questions, in order from most to least limited.
	 *
	 * @return array<string, array<int, string>>
	 */
	public static function get_phase_answers(): array {
		return array(
			'pain'  => array( 'severe', 'moderate', 'mild' ),
			'func'  => array( 'limited', 'partial', 'normal' ),
			'since' => array( 'acute', 'subacute', 'chronic' ),
		);
	}

	/**
	 * Rehab phase (1 calm, 2 build, 3 return to activity) from the phase answers.
	 *
	 * The most limited of pain and daily function decides; a problem younger than two weeks never goes past phase 2.
	 *
	 * @param array<string, string> $answers Keys pain, func, since.
	 * @return int 0 when an answer is missing or invalid.
	 */
	public static function compute_phase( array $answers ): int {
		$levels = array();
		foreach ( self::get_phase_answers() as $key => $allowed ) {
			$index = isset( $answers[ $key ] ) ? array_search( $answers[ $key ], $allowed, true ) : false;
			if ( false === $index ) {
				return 0;
			}
			$levels[ $key ] = (int) $index;
		}

		$phase = min( $levels['pain'], $levels['func'] ) + 1;
		return 0 === $levels['since'] ? min( $phase, 2 ) : $phase;
	}

	/**
	 * Short guidance text for a phase.
	 *
	 * @param int $phase Phase 0-3.
	 * @return string
	 */
	public static function get_phase_summary( int $phase ): string {
		if ( 1 === $phase ) {
			return __( 'Fase 1 – Roe ned: milde, smertelindrende øvelser. Hold deg innenfor det som er komfortabelt.', 'gear-engine' );
		}
		if ( 2 === $phase ) {
			return __( 'Fase 2 – Bygg opp: gradvis økende belastning og bevegelighet. Litt ubehag er greit, men smertene skal ikke øke.', 'gear-engine' );
		}
		if ( 3 === $phase ) {
			return __( 'Fase 3 – Tilbake til aktivitet: mer krevende øvelser for styrke og kontroll. Øk gradvis.', 'gear-engine' );
		}
		return __( 'Et utvalg rolige øvelser for området. Start forsiktig og slutt hvis det gjør vondt verre.', 'gear-engine' );
	}

	/**
	 * Assemble a program from the region's low/moderate risk exercises, ranked by the diagnosis.
	 *
	 * Never produced for high or critical severity diagnoses.
	 *
	 * @param string $region    Triage region slug.
	 * @param string $diagnosis Diagnosis id.
	 * @param int    $phase     Rehab phase 1-3 (0 = not specified): 1 uses low risk exercises only, 3 puts moderate risk first.
	 * @return array{protocol: array<string, mixed>, groups: array<int, array<string, mixed>>}|null
	 */
	public static function get_auto_program( string $region, string $diagnosis, int $phase = 0 ): ?array {
		$region    = sanitize_key( $region );
		$diagnosis = sanitize_key( $diagnosis );
		if ( '' === $region || '' === $diagnosis || ! class_exists( 'Gear_Engine_Triage' ) ) {
			return null;
		}

		$config = Gear_Engine_Triage::get_config( $region );
		if ( is_wp_error( $config ) || empty( $config['diagnoses'][ $diagnosis ] ) ) {
			return null;
		}

		$dx = $config['diagnoses'][ $diagnosis ];
		if ( in_array( $dx['severity'] ?? '', array( 'Kritisk', 'Høy' ), true ) ) {
			return null;
		}

		$exercises = Gear_Engine_Exercise_Service::search_exercises(
			array(
				'area'     => self::get_region_area( $region, $config ),
				'max_risk' => 1 === $phase ? 'low' : 'moderate',
				'limit'    => self::EXERCISE_LIMIT,
			)
		);
		$title     = (string) ( $dx['title'] ?? '' );
		$exercises = self::rank_exercises( $exercises, $title . ' ' . (string) ( $dx['description'] ?? '' ) );
		if ( 3 === $phase ) {
			usort(
				$exercises,
				static function ( array $a, array $b ): int {
					return ( 'moderate' === ( $b['risk'] ?? '' ) ) <=> ( 'moderate' === ( $a['risk'] ?? '' ) );
				}
			);
		}
		$exercises = array_slice( $exercises, 0, 1 === $phase ? self::AUTO_LIMIT - 3 : self::AUTO_LIMIT );
		if ( ! $exercises ) {
			return null;
		}

		return array(
			'protocol' => array(
				'slug'      => '',
				'name'      => $title,
				'summary'   => self::get_phase_summary( $phase ),
				'dosage'    => '',
				'red_flags' => __( 'Smertene øker, du får nummenhet, kraftsvikt eller nye symptomer.', 'gear-engine' ),
			),
			'groups'   => array(
				array(
					'name'        => '',
					'description' => '',
					'exercises'   => $exercises,
				),
			),
		);
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

		$region = '';
		$dx     = '';
		if ( '' === $slug && isset( $_GET['region'], $_GET['dx'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only display of public content.
			$region = sanitize_key( wp_unslash( $_GET['region'] ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
			$dx     = sanitize_key( wp_unslash( $_GET['dx'] ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
		}

		ob_start();
		if ( '' !== $region && '' !== $dx ) {
			$answers = array();
			foreach ( array_keys( self::get_phase_answers() ) as $key ) {
				$answers[ $key ] = isset( $_GET[ $key ] ) ? sanitize_key( wp_unslash( $_GET[ $key ] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
			}
			$phase       = self::compute_phase( $answers );
			$warning_yes = isset( $_GET['warn'] ) && 'yes' === sanitize_key( wp_unslash( $_GET['warn'] ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
			$auto        = self::get_auto_program( $region, $dx, 0 === $phase ? 0 : $phase );
			if ( $auto && $warning_yes ) {
				echo '<div class="gear-engine-program"><div class="gear-engine-program__warning" role="alert"><strong>' . esc_html__( 'Ikke start øvelser nå.', 'gear-engine' ) . '</strong><p>' . esc_html__( 'Nummenhet, kraftsvikt eller raskt økende smerter bør vurderes av lege eller fysioterapeut før du trener.', 'gear-engine' ) . '</p></div></div>';
			} elseif ( $auto && 0 === $phase ) {
				$question_title = $auto['protocol']['name'];
				include GEAR_ENGINE_PATH . 'modules/exercises/templates/protocol-phase-questions.php';
			} elseif ( $auto ) {
				$protocol = $auto['protocol'];
				$groups   = $auto['groups'];
				include GEAR_ENGINE_PATH . 'modules/exercises/templates/protocol-view.php';
			} else {
				echo '<div class="gear-engine-program"><p class="gear-engine-program__empty">' . esc_html__( 'Dette treningsprogrammet er ikke tilgjengelig.', 'gear-engine' ) . '</p></div>';
			}
		} elseif ( '' === $slug ) {
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
