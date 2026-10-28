<?php
/**
 * Triage module: rule-based quiz with a printable clinical note.
 *
 * Independent from the AI chat module. Each body region is described by a JSON
 * config in modules/triage/configs/{region}.json (steps, declarative rules and diagnoses).
 *
 * @package GearEngine
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

/**
 * Class Gear_Engine_Triage
 */
class Gear_Engine_Triage {

	/**
	 * Shortcode tag.
	 */
	public const SHORTCODE_TAG = 'gear_engine_triage';

	/**
	 * Block name.
	 */
	public const BLOCK_NAME = 'gear-engine/triage';

	/**
	 * Picker shortcode tag.
	 */
	public const PICKER_SHORTCODE_TAG = 'gear_engine_triage_picker';

	/**
	 * Option holding the URL path (relative to the site root) where region pages live.
	 */
	public const OPTION_BASE_PATH = 'gear_engine_triage_base_path';

	/**
	 * Default section path.
	 */
	public const DEFAULT_BASE_PATH = 'smertesjekk';

	/**
	 * Decoded configs cache, keyed by region.
	 *
	 * @var array<string, array|null>
	 */
	private static $config_cache = array();

	/**
	 * Constructor.
	 */
	public function __construct() {
		add_action( 'init', array( $this, 'register_shortcode' ) );
		add_action( 'init', array( $this, 'register_gutenberg_block' ) );
		add_action( 'wp_enqueue_scripts', array( $this, 'register_assets' ) );
		add_action( 'admin_menu', array( $this, 'register_settings_page' ) );
		add_action( 'admin_init', array( $this, 'register_settings' ) );
	}

	/**
	 * Sanitize the section base path into slug segments joined by slashes.
	 *
	 * @param mixed $value Raw value.
	 * @return string
	 */
	public static function sanitize_base_path( $value ): string {
		$segments = array_filter(
			array_map( 'sanitize_title', explode( '/', (string) $value ) )
		);
		return $segments ? implode( '/', $segments ) : self::DEFAULT_BASE_PATH;
	}

	/**
	 * Section base path, e.g. "smertesjekk".
	 *
	 * @return string
	 */
	public static function get_base_path(): string {
		return self::sanitize_base_path( get_option( self::OPTION_BASE_PATH, self::DEFAULT_BASE_PATH ) );
	}

	/**
	 * Public URL of the picker/section page.
	 *
	 * @return string
	 */
	public static function get_section_url(): string {
		return trailingslashit( home_url( self::get_base_path() ) );
	}

	/**
	 * Public URL of a region quiz page.
	 *
	 * @param string $region Region slug.
	 * @return string
	 */
	public static function get_region_url( string $region ): string {
		$region = sanitize_key( $region );
		$url    = trailingslashit( home_url( self::get_base_path() . '/' . $region ) );

		/**
		 * Filters the page URL a region card links to.
		 *
		 * @param string $url    Default URL.
		 * @param string $region Region slug.
		 */
		return (string) apply_filters( 'gear_engine_triage_region_url', $url, $region );
	}

	/**
	 * Regions with display metadata, sorted by "order" then label. Invalid configs are skipped.
	 *
	 * @return array<int, array{region:string,label:string,summary:string,order:int,icon:string,url:string}>
	 */
	public static function get_region_cards(): array {
		$cards = array();
		foreach ( self::get_regions() as $region ) {
			$config = self::get_config( $region );
			if ( is_wp_error( $config ) ) {
				continue;
			}
			$cards[] = array(
				'region'  => $region,
				'label'   => isset( $config['label'] ) ? (string) $config['label'] : ucfirst( $region ),
				'summary' => isset( $config['summary'] ) ? (string) $config['summary'] : '',
				'order'   => isset( $config['order'] ) ? (int) $config['order'] : 100,
				'icon'    => isset( $config['icon'] ) ? sanitize_key( $config['icon'] ) : 'activity',
				'url'     => self::get_region_url( $region ),
			);
		}
		usort(
			$cards,
			static function ( $a, $b ) {
				return array( $a['order'], $a['label'] ) <=> array( $b['order'], $b['label'] );
			}
		);
		return $cards;
	}

	/**
	 * Inner SVG markup for picker icons, keyed by the config "icon" value.
	 *
	 * @return array<string, string>
	 */
	public static function get_icon_paths(): array {
		$icons = array(
			'activity' => '<polyline points="22 12 18 12 15 21 9 3 6 12 2 12"/>',
			'back'     => '<path d="M12 3v18"/><path d="M8 6h8"/><path d="M8.5 10h7"/><path d="M8 14h8"/><path d="M8.5 18h7"/>',
			'knee'     => '<path d="M9 2v8a3 3 0 0 0 3 3"/><path d="M12 13a3 3 0 0 1 3 3v6"/><circle cx="12" cy="13" r="2.5"/>',
			'shoulder' => '<circle cx="12" cy="5" r="2.5"/><path d="M4 21v-5a8 8 0 0 1 16 0v5"/><path d="M4 12c0-3 2-5 5-5"/>',
			'foot'     => '<path d="M5 20h13a2 2 0 0 0 2-2v-1a4 4 0 0 0-3-3.9L12 12V4H8v8l-3 2.5z"/>',
			'hip'      => '<path d="M5 4c0 5 1 8 4 10l-1 6"/><path d="M19 4c0 5-1 8-4 10l1 6"/><path d="M9 14h6"/>',
			'neck'     => '<circle cx="12" cy="6" r="3"/><path d="M12 9v5"/><path d="M5 21v-3a7 7 0 0 1 14 0v3"/>',
		);

		/**
		 * Filters the picker icon set. Values are trusted inner SVG markup (24x24 viewBox, stroke based).
		 *
		 * @param array<string, string> $icons Icon markup keyed by name.
		 */
		return (array) apply_filters( 'gear_engine_triage_icons', $icons );
	}

	/**
	 * Full SVG for a picker icon, falling back to the activity icon.
	 *
	 * @param string $name Icon key.
	 * @return string
	 */
	public static function get_icon_svg( string $name ): string {
		$icons = self::get_icon_paths();
		$body  = $icons[ $name ] ?? ( $icons['activity'] ?? '' );
		return '<svg class="gear-engine-triage-picker__icon" xmlns="http://www.w3.org/2000/svg" width="32" height="32" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false">' . $body . '</svg>';
	}

	/**
	 * Register the settings page under Settings.
	 *
	 * @return void
	 */
	public function register_settings_page(): void {
		add_options_page(
			__( 'Smertesjekk', 'gear-engine' ),
			__( 'Smertesjekk', 'gear-engine' ),
			'manage_options',
			'gear-engine-triage',
			array( $this, 'render_settings_page' )
		);
	}

	/**
	 * Register settings.
	 *
	 * @return void
	 */
	public function register_settings(): void {
		register_setting(
			'gear_engine_triage',
			self::OPTION_BASE_PATH,
			array(
				'type'              => 'string',
				'sanitize_callback' => array( __CLASS__, 'sanitize_base_path' ),
				'default'           => self::DEFAULT_BASE_PATH,
			)
		);
	}

	/**
	 * Render the settings page.
	 *
	 * @return void
	 */
	public function render_settings_page(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have permission to access this page.', 'gear-engine' ), '', array( 'response' => 403 ) );
		}
		?>
		<div class="wrap">
			<h1><?php esc_html_e( 'Smertesjekk', 'gear-engine' ); ?></h1>
			<form method="post" action="options.php">
				<?php settings_fields( 'gear_engine_triage' ); ?>
				<table class="form-table" role="presentation">
					<tr>
						<th scope="row"><label for="gear-engine-triage-base-path"><?php esc_html_e( 'Section path', 'gear-engine' ); ?></label></th>
						<td>
							<code><?php echo esc_html( trailingslashit( home_url() ) ); ?></code>
							<input type="text" id="gear-engine-triage-base-path" name="<?php echo esc_attr( self::OPTION_BASE_PATH ); ?>" value="<?php echo esc_attr( self::get_base_path() ); ?>" class="regular-text">
							<p class="description"><?php esc_html_e( 'The page holding [gear_engine_triage_picker]. Each region card links to this path plus the region slug (for example /smertesjekk/rygg/), so create a page for each region with that slug.', 'gear-engine' ); ?></p>
						</td>
					</tr>
					<tr>
						<th scope="row"><label for="gear-engine-program-page-path"><?php esc_html_e( 'Program page path', 'gear-engine' ); ?></label></th>
						<td>
							<code><?php echo esc_html( trailingslashit( home_url() ) ); ?></code>
							<input type="text" id="gear-engine-program-page-path" name="<?php echo esc_attr( Gear_Engine_Protocol_Viewer::OPTION_PAGE_PATH ); ?>" value="<?php echo esc_attr( Gear_Engine_Protocol_Viewer::get_page_path() ); ?>" class="regular-text">
							<p class="description"><?php esc_html_e( 'The page holding [gear_engine_protocol]. Quiz results link to it as /path/?program=protocol-slug when an approved protocol with exercises matches the diagnosis codes.', 'gear-engine' ); ?></p>
						</td>
					</tr>
				</table>
				<?php submit_button(); ?>
			</form>
		</div>
		<?php
	}

	/**
	 * Absolute path to the configs directory.
	 *
	 * @return string
	 */
	public static function get_configs_dir(): string {
		return GEAR_ENGINE_PATH . 'modules/triage/configs/';
	}

	/**
	 * List available regions (one per config file).
	 *
	 * @return string[]
	 */
	public static function get_regions(): array {
		$files   = glob( self::get_configs_dir() . '*.json' );
		$regions = array();
		foreach ( (array) $files as $file ) {
			$regions[] = sanitize_key( basename( $file, '.json' ) );
		}
		sort( $regions );
		return $regions;
	}

	/**
	 * Load and validate a region config.
	 *
	 * @param string $region Region slug.
	 * @return array|WP_Error
	 */
	public static function get_config( string $region ) {
		$region = sanitize_key( $region );

		if ( ! in_array( $region, self::get_regions(), true ) ) {
			return new WP_Error( 'gear_engine_triage_unknown_region', __( 'Unknown triage region.', 'gear-engine' ) );
		}

		if ( ! array_key_exists( $region, self::$config_cache ) ) {
			$raw    = file_get_contents( self::get_configs_dir() . $region . '.json' ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- Local plugin file.
			$config = false === $raw ? null : json_decode( $raw, true );
			self::$config_cache[ $region ] = self::validate_config( $config ) ? $config : null;
		}

		$config = self::$config_cache[ $region ];
		if ( null === $config ) {
			return new WP_Error( 'gear_engine_triage_invalid_config', __( 'Invalid triage configuration.', 'gear-engine' ) );
		}

		/**
		 * Filters a triage region config before it is sent to the client.
		 *
		 * @param array  $config Decoded config.
		 * @param string $region Region slug.
		 */
		return apply_filters( 'gear_engine_triage_config', $config, $region );
	}

	/**
	 * Structural validation: required keys, and every rule must point at a known diagnosis.
	 *
	 * @param mixed $config Decoded JSON.
	 * @return bool
	 */
	public static function validate_config( $config ): bool {
		if ( ! is_array( $config ) ) {
			return false;
		}
		foreach ( array( 'title', 'steps', 'rules', 'defaultDiagnosis', 'diagnoses' ) as $key ) {
			if ( ! isset( $config[ $key ] ) ) {
				return false;
			}
		}
		if ( ! is_array( $config['steps'] ) || ! is_array( $config['rules'] ) || ! is_array( $config['diagnoses'] ) ) {
			return false;
		}
		if ( ! isset( $config['diagnoses'][ $config['defaultDiagnosis'] ] ) ) {
			return false;
		}
		foreach ( $config['rules'] as $rule ) {
			if ( ! isset( $rule['diagnosis'], $rule['when'], $config['diagnoses'][ $rule['diagnosis'] ] ) ) {
				return false;
			}
		}
		return true;
	}

	/**
	 * Register client scripts and stylesheets.
	 *
	 * @return void
	 */
	public function register_assets(): void {
		$base = GEAR_ENGINE_URL . 'modules/triage/assets/';

		wp_register_style( 'gear-engine-triage', $base . 'css/triage.css', array( 'gear-engine-tokens' ), GEAR_ENGINE_VERSION );
		wp_register_script( 'gear-engine-triage-rules', $base . 'js/triage-rules.js', array(), GEAR_ENGINE_VERSION, true );
		wp_register_script( 'gear-engine-triage-icons', $base . 'js/triage-icons.js', array(), GEAR_ENGINE_VERSION, true );
		wp_register_script(
			'gear-engine-triage',
			$base . 'js/triage-engine.js',
			array( 'gear-engine-triage-rules', 'gear-engine-triage-icons' ),
			GEAR_ENGINE_VERSION,
			true
		);
	}

	/**
	 * Register the [gear_engine_triage] shortcode.
	 *
	 * @return void
	 */
	public function register_shortcode(): void {
		add_shortcode( self::SHORTCODE_TAG, array( $this, 'render' ) );
		add_shortcode( self::PICKER_SHORTCODE_TAG, array( $this, 'render_picker' ) );
	}

	/**
	 * Render the region picker grid.
	 *
	 * @return string
	 */
	public function render_picker(): string {
		$cards = self::get_region_cards();
		if ( ! $cards ) {
			return '';
		}

		wp_enqueue_style( 'gear-engine-triage' );

		ob_start();
		include GEAR_ENGINE_PATH . 'modules/triage/templates/triage-picker.php';
		return (string) ob_get_clean();
	}

	/**
	 * Register the Gutenberg block.
	 *
	 * @return void
	 */
	public function register_gutenberg_block(): void {
		if ( ! function_exists( 'register_block_type' ) ) {
			return;
		}

		wp_register_script(
			'gear-engine-triage-block-editor',
			GEAR_ENGINE_URL . 'modules/triage/assets/js/block-editor.js',
			array( 'wp-blocks', 'wp-element', 'wp-components', 'wp-block-editor' ),
			GEAR_ENGINE_VERSION,
			true
		);
		wp_localize_script( 'gear-engine-triage-block-editor', 'gearEngineTriageBlock', array( 'regions' => self::get_regions() ) );

		register_block_type(
			self::BLOCK_NAME,
			array(
				'editor_script'   => 'gear-engine-triage-block-editor',
				'render_callback' => array( $this, 'render' ),
				'attributes'      => array(
					'region' => array(
						'type'    => 'string',
						'default' => 'rygg',
					),
				),
			)
		);
	}

	/**
	 * Render the quiz container for a region.
	 *
	 * @param array|string $atts Shortcode or block attributes.
	 * @return string
	 */
	public function render( $atts = array() ): string {
		$atts = shortcode_atts(
			array(
				'region'    => 'rygg',
				'back_link' => 'true',
			),
			is_array( $atts ) ? $atts : array(),
			self::SHORTCODE_TAG
		);

		$region    = sanitize_key( $atts['region'] );
		$back_url  = filter_var( $atts['back_link'], FILTER_VALIDATE_BOOLEAN ) ? self::get_section_url() : '';
		$config = self::get_config( $region );

		if ( is_wp_error( $config ) ) {
			return current_user_can( 'edit_posts' )
				? '<p class="gear-engine-triage-error">' . esc_html( $config->get_error_message() ) . '</p>'
				: '';
		}

		$config = self::attach_programs( $config );

		wp_enqueue_style( 'gear-engine-triage' );
		wp_enqueue_script( 'gear-engine-triage' );

		ob_start();
		$template = GEAR_ENGINE_PATH . 'modules/triage/templates/triage-container.php';
		include $template;
		return (string) ob_get_clean();
	}

	/**
	 * Link diagnoses to approved training programs (protocols).
	 *
	 * A diagnosis gets a program when an approved, in-date protocol with exercises matches its
	 * ICD-10/ICPC-2 codes, or the protocol named in its optional "protocol" field. High and
	 * critical severity diagnoses never get a program. An existing manual trainingProgram.url is kept.
	 *
	 * @param array<string, mixed> $config Validated region config.
	 * @return array<string, mixed>
	 */
	public static function attach_programs( array $config ): array {
		if ( ! class_exists( 'Gear_Engine_Protocol_Viewer' ) || ! class_exists( 'Gear_Engine_Protocols' ) ) {
			return $config;
		}

		foreach ( $config['diagnoses'] as $id => $diagnosis ) {
			$program = isset( $diagnosis['trainingProgram'] ) && is_array( $diagnosis['trainingProgram'] ) ? $diagnosis['trainingProgram'] : array();
			unset( $config['diagnoses'][ $id ]['trainingProgram'] );

			if ( ! empty( $program['url'] ) ) {
				$config['diagnoses'][ $id ]['trainingProgram'] = $program;
				continue;
			}
			if ( in_array( $diagnosis['severity'] ?? '', array( 'Kritisk', 'Høy' ), true ) ) {
				continue;
			}

			$slug = self::find_program_slug( $diagnosis );
			if ( '' === $slug ) {
				continue;
			}

			$config['diagnoses'][ $id ]['trainingProgram'] = array(
				'title' => isset( $program['title'] ) && '' !== $program['title'] ? (string) $program['title'] : __( 'Åpne treningsprogrammet', 'gear-engine' ),
				'url'   => Gear_Engine_Protocol_Viewer::get_program_url( $slug ),
			);
		}

		return $config;
	}

	/**
	 * Slug of the approved protocol (with exercises) that fits a diagnosis, or an empty string.
	 *
	 * @param array<string, mixed> $diagnosis Diagnosis definition.
	 * @return string
	 */
	private static function find_program_slug( array $diagnosis ): string {
		$has_exercises = static function ( string $slug ): bool {
			return (bool) Gear_Engine_Exercise_Service::search_exercises( array( 'protocol' => $slug, 'limit' => 1 ) );
		};

		if ( ! empty( $diagnosis['protocol'] ) ) {
			$slug = sanitize_title( (string) $diagnosis['protocol'] );
			return Gear_Engine_Protocol_Viewer::get_approved_protocol( $slug ) && $has_exercises( $slug ) ? $slug : '';
		}

		$codes = array_filter( array( $diagnosis['icd10'] ?? '', $diagnosis['icpc2'] ?? '' ) );
		foreach ( Gear_Engine_Protocols::find_approved_by_codes( $codes ) as $protocol ) {
			if ( $has_exercises( (string) $protocol['slug'] ) ) {
				return (string) $protocol['slug'];
			}
		}
		return '';
	}
}
