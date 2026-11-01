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
		add_action( 'admin_post_gear_engine_create_triage_pages', array( $this, 'handle_create_pages' ) );
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
			$default_chips = array(
				'rygg'      => array( 'Korsrygg', 'Isjias', 'Hekseskudd' ),
				'kne'       => array( 'Menisk', 'Kneskål', 'Artrose' ),
				'nakke'     => array( 'Nakkesperre', 'Hodepine', 'Utstråling' ),
				'skulder'   => array( 'Impingement', 'Senebetennelse', 'Kalkskulder' ),
				'hofte'     => array( 'Lyske', 'Trokanter', 'Hofteartrose' ),
				'fot-ankel' => array( 'Overtråkk', 'Plantar fascitt', 'Akilles' ),
			);

			$chips = array();
			if ( ! empty( $config['chips'] ) && is_array( $config['chips'] ) ) {
				$chips = array_slice( $config['chips'], 0, 3 );
			} elseif ( isset( $default_chips[ $region ] ) ) {
				$chips = $default_chips[ $region ];
			} elseif ( ! empty( $config['diagnoses'] ) && is_array( $config['diagnoses'] ) ) {
				foreach ( $config['diagnoses'] as $diag_id => $diag ) {
					if ( 'medical_referral' !== $diag_id && ! empty( $diag['title'] ) ) {
						$chips[] = $diag['title'];
						if ( count( $chips ) >= 3 ) {
							break;
						}
					}
				}
			}

			$cards[] = array(
				'region'  => $region,
				'label'   => isset( $config['label'] ) ? (string) $config['label'] : ucfirst( $region ),
				'summary' => isset( $config['summary'] ) ? (string) $config['summary'] : '',
				'chips'   => $chips,
				'badge'   => 'rygg' === $region ? 'Vanligst' : '',
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
			'back'     => '<rect x="8.5" y="2.5" width="7" height="3.4" rx="1.3"/><rect x="8" y="7.3" width="8" height="3.4" rx="1.3"/><rect x="7.5" y="12.1" width="9" height="3.4" rx="1.3"/><rect x="8" y="16.9" width="8" height="3.4" rx="1.3"/>',
			'knee'     => '<path d="M8 2.5l2.25 7.5"/><path d="M12.36 14.72L16.5 21.5"/><circle cx="11" cy="12.5" r="2.6"/>',
			'shoulder' => '<circle cx="11" cy="5" r="2.6"/><path d="M4.5 21v-5.5A4.5 4.5 0 0 1 9 11h6.6"/><circle cx="18.2" cy="11" r="2.6"/><path d="M18.2 13.6V21"/>',
			'foot'     => '<path d="M7.5 3v9L4 17.4a2 2 0 0 0 1.7 3.1H19a2 2 0 0 0 2-2V17a2.5 2.5 0 0 0-1.7-2.4L13.5 13V3"/>',
			'hip'      => '<path d="M3.5 5c0 5.5 1.5 8.5 5 10"/><path d="M20.5 5c0 5.5-1.5 8.5-5 10"/><circle cx="8" cy="18" r="2.6"/><circle cx="16" cy="18" r="2.6"/>',
			'neck'     => '<circle cx="12" cy="6" r="3.4"/><path d="M10.2 9.6v4M13.8 9.6v4"/><path d="M3.5 21.5c0-4 3-6.5 8.5-6.5s8.5 2.5 8.5 6.5"/>',
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
		return '<svg class="gear-engine-triage-picker__icon" xmlns="http://www.w3.org/2000/svg" width="44" height="44" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.4" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false">' . $body . '</svg>';
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
	 * Get status of required triage pages.
	 *
	 * @return array{parent: array{title: string, path: string, page_id: int|null, exists: bool}, regions: array<string, array{title: string, label: string, path: string, page_id: int|null, exists: bool}>, all_exist: bool, has_missing: bool}
	 */
	public static function get_pages_status(): array {
		$base_path   = self::get_base_path();
		$parent_page = function_exists( 'get_page_by_path' ) ? get_page_by_path( $base_path ) : null;
		$parent_id   = ( $parent_page && ! empty( $parent_page->ID ) ) ? (int) $parent_page->ID : null;

		$status = array(
			'parent'      => array(
				'title'   => __( 'Smertesjekk (Hovedside / Velger)', 'gear-engine' ),
				'path'    => $base_path,
				'page_id' => $parent_id,
				'exists'  => ( null !== $parent_id ),
			),
			'regions'     => array(),
			'all_exist'   => true,
			'has_missing' => false,
		);

		if ( ! $status['parent']['exists'] ) {
			$status['all_exist']   = false;
			$status['has_missing'] = true;
		}

		foreach ( self::get_regions() as $region ) {
			$config     = self::get_config( $region );
			$label      = ( ! is_wp_error( $config ) && ! empty( $config['label'] ) ) ? $config['label'] : ucfirst( $region );
			$child_path = $base_path . '/' . $region;
			$child_page = function_exists( 'get_page_by_path' ) ? get_page_by_path( $child_path ) : null;
			$child_id   = ( $child_page && ! empty( $child_page->ID ) ) ? (int) $child_page->ID : null;

			$exists = ( null !== $child_id );
			if ( ! $exists ) {
				$status['all_exist']   = false;
				$status['has_missing'] = true;
			}

			$status['regions'][ $region ] = array(
				'title'   => sprintf(
					/* translators: %s: Region label */
					__( 'Smertesjekk %s', 'gear-engine' ),
					$label
				),
				'label'   => $label,
				'path'    => $child_path,
				'page_id' => $child_id,
				'exists'  => $exists,
			);
		}

		return $status;
	}

	/**
	 * Handle admin request to automatically create missing triage pages.
	 *
	 * @return void
	 */
	public function handle_create_pages(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have permission to access this page.', 'gear-engine' ), '', array( 'response' => 403 ) );
		}

		check_admin_referer( 'gear_engine_create_triage_pages', 'gear_engine_nonce' );

		$base_path   = self::get_base_path();
		$parent_page = function_exists( 'get_page_by_path' ) ? get_page_by_path( $base_path ) : null;

		if ( ! $parent_page && function_exists( 'wp_insert_post' ) ) {
			$parent_id = wp_insert_post(
				array(
					'post_title'   => __( 'Smertesjekk', 'gear-engine' ),
					'post_name'    => $base_path,
					'post_content' => '<!-- wp:shortcode -->[' . self::PICKER_SHORTCODE_TAG . ']<!-- /wp:shortcode -->',
					'post_status'  => 'publish',
					'post_type'    => 'page',
				)
			);
		} else {
			$parent_id = $parent_page ? (int) $parent_page->ID : 0;
		}

		if ( $parent_id && ! is_wp_error( $parent_id ) && function_exists( 'wp_insert_post' ) ) {
			foreach ( self::get_regions() as $region ) {
				$child_path = $base_path . '/' . $region;
				$child_page = function_exists( 'get_page_by_path' ) ? get_page_by_path( $child_path ) : null;
				if ( ! $child_page ) {
					$config = self::get_config( $region );
					$label  = ( ! is_wp_error( $config ) && ! empty( $config['label'] ) ) ? $config['label'] : ucfirst( $region );
					$title  = sprintf(
						/* translators: %s: Region label, e.g. Rygg */
						__( 'Smertesjekk %s', 'gear-engine' ),
						$label
					);
					$content = '<!-- wp:paragraph -->' . "\n" .
						'<p>' . esc_html( sprintf( __( 'Rask, digital vurdering for %s. Svar på spørsmålene for å få råd og veiledning.', 'gear-engine' ), strtolower( $label ) ) ) . '</p>' . "\n" .
						'<!-- /wp:paragraph -->' . "\n\n" .
						'<!-- wp:shortcode -->[' . self::SHORTCODE_TAG . ' region="' . esc_attr( $region ) . '"]<!-- /wp:shortcode -->';

					wp_insert_post(
						array(
							'post_title'   => $title,
							'post_name'    => $region,
							'post_parent'  => $parent_id,
							'post_content' => $content,
							'post_status'  => 'publish',
							'post_type'    => 'page',
						)
					);
				}
			}
		}

		$redirect_url = add_query_arg(
			array(
				'page'                      => 'gear-engine-triage',
				'gear_engine_pages_created' => '1',
			),
			admin_url( 'options-general.php' )
		);

		wp_safe_redirect( $redirect_url );
		exit;
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
			<?php if ( isset( $_GET['gear_engine_pages_created'] ) ) : ?>
				<div class="notice notice-success is-dismissible">
					<p><?php esc_html_e( 'Smertesjekk-sidene ble opprettet!', 'gear-engine' ); ?></p>
				</div>
			<?php endif; ?>
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

			<hr style="margin: 32px 0 24px;">

			<h2><?php esc_html_e( 'Sidestatus og SEO-snarveier', 'gear-engine' ); ?></h2>
			<p class="description">
				<?php esc_html_e( 'Hver region har sin egen unike side for optimal SEO og synlighet i Google. Her kan du se status og åpne WordPress-editoren for å tilpasse innholdet.', 'gear-engine' ); ?>
			</p>

			<?php
			$pages_status = self::get_pages_status();
			?>
			<table class="widefat striped" style="max-width: 880px; margin-top: 14px;">
				<thead>
					<tr>
						<th style="font-weight: 600;"><?php esc_html_e( 'Side', 'gear-engine' ); ?></th>
						<th style="font-weight: 600;"><?php esc_html_e( 'URL-sti', 'gear-engine' ); ?></th>
						<th style="font-weight: 600; width: 120px;"><?php esc_html_e( 'Status', 'gear-engine' ); ?></th>
						<th style="font-weight: 600; width: 170px;"><?php esc_html_e( 'Handlinger', 'gear-engine' ); ?></th>
					</tr>
				</thead>
				<tbody>
					<?php
					$all_rows = array_merge(
						array( $pages_status['parent'] ),
						array_values( $pages_status['regions'] )
					);
					foreach ( $all_rows as $row ) :
						?>
						<tr>
							<td><strong><?php echo esc_html( $row['title'] ); ?></strong></td>
							<td><code>/<?php echo esc_html( $row['path'] ); ?>/</code></td>
							<td>
								<?php if ( $row['exists'] ) : ?>
									<span style="display:inline-block; padding: 2px 8px; border-radius: 4px; background: #e7f7ed; color: #127038; font-weight: 600; font-size: 12px;">
										✓ <?php esc_html_e( 'Publisert', 'gear-engine' ); ?>
									</span>
								<?php else : ?>
									<span style="display:inline-block; padding: 2px 8px; border-radius: 4px; background: #fff3cd; color: #856404; font-weight: 600; font-size: 12px;">
										⚠ <?php esc_html_e( 'Mangler', 'gear-engine' ); ?>
									</span>
								<?php endif; ?>
							</td>
							<td>
								<?php if ( $row['exists'] && ! empty( $row['page_id'] ) ) : ?>
									<?php if ( function_exists( 'get_permalink' ) ) : ?>
										<a href="<?php echo esc_url( get_permalink( $row['page_id'] ) ); ?>" class="button button-small" target="_blank" rel="noopener noreferrer">
											<?php esc_html_e( 'Vis side', 'gear-engine' ); ?>
										</a>
									<?php endif; ?>
									<?php if ( function_exists( 'get_edit_post_link' ) ) : ?>
										<a href="<?php echo esc_url( (string) get_edit_post_link( $row['page_id'] ) ); ?>" class="button button-small">
											<?php esc_html_e( 'Rediger', 'gear-engine' ); ?>
										</a>
									<?php endif; ?>
								<?php else : ?>
									<span class="description">—</span>
								<?php endif; ?>
							</td>
						</tr>
					<?php endforeach; ?>
				</tbody>
			</table>

			<?php if ( $pages_status['has_missing'] ) : ?>
				<div style="margin-top: 18px;">
					<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
						<input type="hidden" name="action" value="gear_engine_create_triage_pages">
						<?php wp_nonce_field( 'gear_engine_create_triage_pages', 'gear_engine_nonce' ); ?>
						<button type="submit" class="button button-primary button-hero">
							<?php esc_html_e( 'Opprett manglende Smertesjekk-sider automatisk', 'gear-engine' ); ?>
						</button>
					</form>
				</div>
			<?php else : ?>
				<div class="notice notice-success inline" style="max-width: 880px; margin-top: 18px; padding: 10px 14px;">
					<p style="margin: 0; font-size: 13px;">
						<strong>✓ <?php esc_html_e( 'Alle Smertesjekk-sider er operative!', 'gear-engine' ); ?></strong>
						<?php esc_html_e( 'Du kan trykke "Rediger" over for å tilpasse introtekst og SEO-innhold for hver enkelt side.', 'gear-engine' ); ?>
					</p>
				</div>
			<?php endif; ?>
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
			self::$config_cache[ $region ] = Gear_Engine_Config_Loader::load(
				self::get_configs_dir(),
				$region,
				array( __CLASS__, 'validate_config' )
			);
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

		$config = self::attach_programs( $config, $region );
		if ( class_exists( 'Gear_Engine_Protocol_Viewer' ) ) {
			$config['programPageUrl'] = trailingslashit( home_url( Gear_Engine_Protocol_Viewer::get_page_path() ) );
		}

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
	 * critical severity diagnoses never get a program. Without a protocol, and with a region, the link
	 * points to an automatic program built from the region's exercises. An existing manual trainingProgram.url is kept.
	 *
	 * @param array<string, mixed> $config Validated region config.
	 * @return array<string, mixed>
	 */
	public static function attach_programs( array $config, string $region = '' ): array {
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
			if ( '' !== $slug ) {
				$url = Gear_Engine_Protocol_Viewer::get_program_url( $slug );
			} elseif ( '' !== $region && Gear_Engine_Protocol_Viewer::area_has_auto_exercises( Gear_Engine_Protocol_Viewer::get_region_area( $region, $config ) ) ) {
				$url = Gear_Engine_Protocol_Viewer::get_auto_program_url( $region, (string) $id );
			} else {
				continue;
			}

			$config['diagnoses'][ $id ]['trainingProgram'] = array(
				'title' => isset( $program['title'] ) && '' !== $program['title'] ? (string) $program['title'] : __( 'Åpne treningsprogrammet', 'gear-engine' ),
				'url'   => $url,
			);
		}

		return $config;
	}

	/**
	 * Slug of the approved protocol (with exercises) that fits a diagnosis, or an empty string.
	 *
	 * Public: the coverage report resolves the same way the result screen does,
	 * so both always agree on what a diagnosis actually reaches.
	 *
	 * @param array<string, mixed> $diagnosis Diagnosis definition.
	 * @return string
	 */
	public static function find_program_slug( array $diagnosis ): string {
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
