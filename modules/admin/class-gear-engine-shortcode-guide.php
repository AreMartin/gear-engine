<?php
/**
 * Admin overview of every shortcode and the page structure that connects them.
 *
 * @package GearEngine
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

/**
 * Settings page "Shortcodes & Pages" with copyable shortcodes and a page map with live status.
 */
class Gear_Engine_Shortcode_Guide {

	public const PAGE_SLUG = 'gear-engine-shortcodes';

	/**
	 * Constructor.
	 */
	public function __construct() {
		add_action( 'admin_menu', array( $this, 'register_page' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_assets' ) );
		add_action( 'admin_post_gear_engine_create_core_pages', array( $this, 'handle_create_core_pages' ) );
		add_action( 'admin_post_gear_engine_install_recommended_stack', array( $this, 'handle_install_recommended_stack' ) );
	}

	/**
	 * Register the page under Settings.
	 *
	 * @return void
	 */
	public function register_page(): void {
		add_options_page(
			__( 'Shortcodes & Pages', 'gear-engine' ),
			__( 'Gear Engine: Shortcodes', 'gear-engine' ),
			'manage_options',
			self::PAGE_SLUG,
			array( $this, 'render_page' )
		);
	}

	/**
	 * Load the copy button script and styles on this page only.
	 *
	 * @param string $hook Admin page hook suffix.
	 * @return void
	 */
	public function enqueue_assets( $hook ): void {
		if ( 'settings_page_' . self::PAGE_SLUG !== $hook ) {
			return;
		}
		wp_enqueue_style( 'gear-engine-shortcode-guide', GEAR_ENGINE_URL . 'modules/admin/assets/css/shortcode-guide.css', array(), GEAR_ENGINE_VERSION );
		wp_enqueue_script( 'gear-engine-shortcode-guide', GEAR_ENGINE_URL . 'modules/admin/assets/js/shortcode-guide.js', array(), GEAR_ENGINE_VERSION, true );
	}

	/**
	 * All shortcodes provided by the plugin.
	 *
	 * @return array<int, array{group:string,tag:string,example:string,description:string,note:string}>
	 */
	public static function get_shortcodes(): array {
		return array(
			array(
				'group'       => __( 'Digital fysio (chat)', 'gear-engine' ),
				'tag'         => 'gae_physio_chat',
				'example'     => '[gae_physio_chat]',
				'description' => __( 'The AI chat. Options: height="100dvh" fullscreen="true".', 'gear-engine' ),
				'note'        => __( 'Alias: [digital_fysio_chat]. Put it on the page the "Digital fysio" card links to.', 'gear-engine' ),
			),
			array(
				'group'       => __( 'Symptomsjekker (quiz)', 'gear-engine' ),
				'tag'         => Gear_Engine_Triage::PICKER_SHORTCODE_TAG,
				'example'     => '[' . Gear_Engine_Triage::PICKER_SHORTCODE_TAG . ']',
				'description' => __( 'Region picker with one card per body region. Each card links to the region page.', 'gear-engine' ),
				'note'        => __( 'Goes on the page the "Symptomsjekker" card links to.', 'gear-engine' ),
			),
			array(
				'group'       => __( 'Symptomsjekker (quiz)', 'gear-engine' ),
				'tag'         => Gear_Engine_Triage::SHORTCODE_TAG,
				'example'     => '[' . Gear_Engine_Triage::SHORTCODE_TAG . ' region="rygg"]',
				'description' => __( 'One quiz for one region. Options: region, back_link="true|false".', 'gear-engine' ),
				'note'        => __( 'One page per region. Ready-made shortcodes are listed in the page map below.', 'gear-engine' ),
			),
			array(
				'group'       => __( 'Treningsprogram', 'gear-engine' ),
				'tag'         => Gear_Engine_Protocol_Viewer::SHORTCODE_TAG,
				'example'     => '[' . Gear_Engine_Protocol_Viewer::SHORTCODE_TAG . '] eller [' . Gear_Engine_Protocol_Viewer::SHORTCODE_TAG . ' slug="akutt-overtrakk"]',
				'description' => __( 'Viser treningsprogrammer. Uten parameter vises oversikt/dynamisk program basert på URL (?program=...). Kan også låses til én spesifikk protokoll med slug="...", perfekt for dedikerte SEO-landingssider.', 'gear-engine' ),
				'note'        => __( 'Hovedsiden ligger på /treningsprogram/. Slugen til hver protokoll finner du under Øvelser > Protokoller i WP-menyen.', 'gear-engine' ),
			),
			array(
				'group'       => __( 'Produkter', 'gear-engine' ),
				'tag'         => Gear_Engine_Gear_Picker::HUB_SHORTCODE_TAG,
				'example'     => '[' . Gear_Engine_Gear_Picker::HUB_SHORTCODE_TAG . ']',
				'description' => __( 'Kjøpsveileder Hub med faneinndeling (Kondisjon, Styrke, Rehab & Helse) og dagsaktuelle tilbud.', 'gear-engine' ),
				'note'        => __( 'Hovedveileder på /kjopsveiledere/ siden. Lenket fra forsiden og Hero-knappen.', 'gear-engine' ),
			),
			array(
				'group'       => __( 'Kjøpsveiledere (Enkeltvelger)', 'gear-engine' ),
				'tag'         => Gear_Engine_Gear_Picker::SHORTCODE_TAG,
				'example'     => '[' . Gear_Engine_Gear_Picker::SHORTCODE_TAG . ' type="treadmill"]',
				'description' => __( 'Guided gear picker built from the imported exercise gear. Options: type (config in modules/gear-picker/configs), category (gear_category slugs, comma separated).', 'gear-engine' ),
				'note'        => __( 'Use anywhere. Needs imported products with a price and affiliate link.', 'gear-engine' ),
			),
			array(
				'group'       => __( 'Prissammenligning', 'gear-engine' ),
				'tag'         => class_exists( 'Gear_Engine_Price_Comparison' ) ? Gear_Engine_Price_Comparison::SHORTCODE_TAG : 'gear_engine_price_comparison',
				'example'     => '[' . ( class_exists( 'Gear_Engine_Price_Comparison' ) ? Gear_Engine_Price_Comparison::SHORTCODE_TAG : 'gear_engine_price_comparison' ) . ']',
				'description' => __( 'Fasettert prissammenligning og produktkatalog med avdelingsvelger, prisslider og sammenligningsmotor. Opsjoner: department="kondisjon|styrke|rehabilitering|restitusjon", group="slug".', 'gear-engine' ),
				'note'        => __( 'Hovedkatalog på /priser/. Kan også filtreres til en bestemt avdeling på andre undersider.', 'gear-engine' ),
			),
			array(
				'group'       => __( 'Produkter', 'gear-engine' ),
				'tag'         => 'gear_product',
				'example'     => '[gear_product id="123"]',
				'description' => __( 'Affiliate product card. Options: id or ean, show_ai, pitch, button_text.', 'gear-engine' ),
				'note'        => __( 'Use anywhere. The Gutenberg block "Product card" does the same.', 'gear-engine' ),
			),
		);
	}

	/**
	 * Ready-made quiz shortcodes, one per available region.
	 *
	 * @return array<int, array{label:string,path:string,example:string}>
	 */
	public static function get_region_shortcodes(): array {
		$rows = array();
		foreach ( Gear_Engine_Triage::get_region_cards() as $card ) {
			$rows[] = array(
				'label'   => $card['label'],
				'path'    => Gear_Engine_Triage::get_base_path() . '/' . $card['region'],
				'example' => '[' . Gear_Engine_Triage::SHORTCODE_TAG . ' region="' . $card['region'] . '"]',
			);
		}
		return $rows;
	}

	/**
	 * Check whether a page exists at a path and holds the expected shortcode.
	 *
	 * @param string $path Page path relative to the site root.
	 * @param string $tag  Shortcode tag the page should contain.
	 * @return array{state:string,post_id:int}
	 */
	public static function get_page_status( string $path, string $tag ): array {
		$page = '' === trim( $path, '/' ) ? null : get_page_by_path( trim( $path, '/' ) );
		if ( ! $page ) {
			return array(
				'state'   => 'missing',
				'post_id' => 0,
			);
		}

		$state = 'ok';
		if ( 'publish' !== $page->post_status ) {
			$state = 'unpublished';
		} elseif ( ! has_shortcode( (string) $page->post_content, $tag ) ) {
			$state = 'no_shortcode';
		}
		return array(
			'state'   => $state,
			'post_id' => (int) $page->ID,
		);
	}

	/**
	 * Find published pages or posts that contain a shortcode (for pages whose path is not fixed).
	 *
	 * @param string $tag Shortcode tag.
	 * @return array<int, int> Post IDs.
	 */
	public static function find_pages_with_shortcode( string $tag ): array {
		$ids   = array();
		$posts = get_posts(
			array(
				'post_type'      => array( 'page', 'post' ),
				'post_status'    => 'publish',
				's'              => '[' . $tag,
				'posts_per_page' => 5,
				'no_found_rows'  => true,
			)
		);
		foreach ( $posts as $post ) {
			if ( has_shortcode( (string) $post->post_content, $tag ) ) {
				$ids[] = (int) $post->ID;
			}
		}
		return $ids;
	}

	/**
	 * The page structure: where each shortcode lives and what it links to.
	 *
	 * Rows with a fixed path are checked by path; others are searched by shortcode.
	 *
	 * @return array<int, array<string, mixed>>
	 */
	public static function get_page_map(): array {
		$base    = Gear_Engine_Triage::get_base_path();
		$program = Gear_Engine_Protocol_Viewer::get_page_path();
		$picker  = Gear_Engine_Triage::PICKER_SHORTCODE_TAG;
		$quiz    = Gear_Engine_Triage::SHORTCODE_TAG;
		$viewer  = Gear_Engine_Protocol_Viewer::SHORTCODE_TAG;

		$map   = array();
		$map[] = array(
			'level'   => 0,
			'title'   => __( 'Digital fysio', 'gear-engine' ),
			'path'    => '',
			'tag'     => 'gae_physio_chat',
			'example' => '[gae_physio_chat]',
			'hint'    => __( 'Linked from the front page "Digital fysio" card. Standalone, links to nothing else.', 'gear-engine' ),
		);
		$map[] = array(
			'level'   => 0,
			'title'   => __( 'Symptomsjekker (Hub / Velger)', 'gear-engine' ),
			'path'    => $base,
			'tag'     => $picker,
			'example' => '[' . $picker . ']',
			'hint'    => __( 'Linked from the front page "Symptomsjekker" card. Shows the region cards.', 'gear-engine' ),
		);
		foreach ( self::get_region_shortcodes() as $row ) {
			$map[] = array(
				'level'   => 1,
				'title'   => sprintf(
					/* translators: %s: region name */
					__( 'Symptomsjekk: %s', 'gear-engine' ),
					$row['label']
				),
				'path'    => $row['path'],
				'tag'     => $quiz,
				'example' => $row['example'],
				'hint'    => __( 'Opened from the picker card.', 'gear-engine' ),
			);
		}
		$map[] = array(
			'level'   => 2,
			'title'   => __( 'Treningsprogram', 'gear-engine' ),
			'path'    => $program,
			'tag'     => $viewer,
			'example' => '[' . $viewer . ']',
			'hint'    => __( 'Viser øvelser og protokoller med video, dosering og utstyrsveiledning.', 'gear-engine' ),
		);

		$hub_tag    = class_exists( 'Gear_Engine_Gear_Picker' ) ? Gear_Engine_Gear_Picker::HUB_SHORTCODE_TAG : 'gear_engine_gear_picker_hub';
		$picker_tag = class_exists( 'Gear_Engine_Gear_Picker' ) ? Gear_Engine_Gear_Picker::SHORTCODE_TAG : 'gear_engine_gear_picker';

		$map[] = array(
			'level'   => 0,
			'title'   => __( 'Kjøpsveiledere (Hub)', 'gear-engine' ),
			'path'    => 'kjopsveiledere',
			'tag'     => $hub_tag,
			'example' => '[' . $hub_tag . ']',
			'hint'    => __( 'Hovedsiden for alle utstyrsvelgere (Kondisjon, Styrke, Rehab). Lenket fra forsiden og Hero.', 'gear-engine' ),
		);

		$guides = array(
			array(
				'slug'  => 'treadmill',
				'title' => __( 'Kjøpsveileder: Tredemølle', 'gear-engine' ),
				'hint'  => __( 'Løpebånd, motorstyrke og demping. Inkludert i Huben (?type=treadmill).', 'gear-engine' ),
			),
			array(
				'slug'  => 'romaskin',
				'title' => __( 'Kjøpsveileder: Romaskin', 'gear-engine' ),
				'hint'  => __( 'Vann-, luft- og magnetmotstand for helkropp. Inkludert i Huben (?type=romaskin).', 'gear-engine' ),
			),
			array(
				'slug'  => 'ergometersykkel',
				'title' => __( 'Kjøpsveileder: Ergometersykkel', 'gear-engine' ),
				'hint'  => __( 'Leddvennlig tråkk, lavt innsteg og spinning. Inkludert i Huben (?type=ergometersykkel).', 'gear-engine' ),
			),
			array(
				'slug'  => 'rehab-guide',
				'title' => __( 'Kjøpsveileder: Rehab & Hjelpemidler', 'gear-engine' ),
				'hint'  => __( 'Bred guide for leddavlastning, balanse og pedaltrener. Inkludert i Huben (?type=rehab-guide).', 'gear-engine' ),
			),
		);

		foreach ( $guides as $guide ) {
			$map[] = array(
				'level'    => 1,
				'title'    => $guide['title'],
				'path'     => 'kjopsveiledere/' . $guide['slug'],
				'tag'      => $picker_tag,
				'example'  => '[' . $picker_tag . ' type="' . $guide['slug'] . '"]',
				'hint'     => $guide['hint'],
				'hub_type' => $guide['slug'],
			);
		}

		$compare_tag = class_exists( 'Gear_Engine_Price_Comparison' ) ? Gear_Engine_Price_Comparison::SHORTCODE_TAG : 'gear_engine_price_comparison';
		$map[]       = array(
			'level'   => 0,
			'title'   => __( 'Prissammenligning (Katalog)', 'gear-engine' ),
			'path'    => 'priser',
			'tag'     => $compare_tag,
			'example' => '[' . $compare_tag . ']',
			'hint'    => __( 'Fasettert prissammenligning med avansert filtrering, sammenligningsmodal og avdelingstabs.', 'gear-engine' ),
		);

		$hub_status = self::get_page_status( 'kjopsveiledere', $hub_tag );

		foreach ( $map as $index => $row ) {
			if ( '' !== $row['path'] ) {
				$status = self::get_page_status( $row['path'], $row['tag'] );
			} else {
				$found  = self::find_pages_with_shortcode( $row['tag'] );
				$status = array(
					'state'   => $found ? 'ok' : 'missing',
					'post_id' => $found ? $found[0] : 0,
				);
			}

			// If this is an individual guide whose dedicated subpage is missing, but the Hub page is active,
			// mark it as 'in_hub' so it displays as active inside the Hub.
			if ( ! empty( $row['hub_type'] ) && 'ok' !== $status['state'] && 'ok' === $hub_status['state'] ) {
				$status = array(
					'state'   => 'in_hub',
					'post_id' => $hub_status['post_id'],
				);
			}

			$map[ $index ] += $status;
		}
		return $map;
	}

	/**
	 * Label and CSS class for a page state.
	 *
	 * @param string $state State key from get_page_status().
	 * @return array{0:string,1:string}
	 */
	public static function describe_state( string $state ): array {
		$states = array(
			'ok'           => array( __( 'OK', 'gear-engine' ), 'ok' ),
			'in_hub'       => array( __( 'Aktiv i Hub', 'gear-engine' ), 'ok' ),
			'missing'      => array( __( 'Page missing', 'gear-engine' ), 'bad' ),
			'unpublished'  => array( __( 'Not published', 'gear-engine' ), 'warn' ),
			'no_shortcode' => array( __( 'Shortcode missing on page', 'gear-engine' ), 'warn' ),
		);
		return $states[ $state ] ?? $states['missing'];
	}

	/**
	 * Link that opens an existing page for editing, or a new page with the shortcode prefilled.
	 *
	 * @param array<string, mixed> $row Page map row.
	 * @return array{0:string,1:string} URL and link label.
	 */
	public static function get_action_link( array $row ): array {
		if ( 'in_hub' === ( $row['state'] ?? '' ) && ! empty( $row['hub_type'] ) ) {
			return array(
				home_url( '/kjopsveiledere/?type=' . rawurlencode( (string) $row['hub_type'] ) ),
				__( 'Åpne i Hub', 'gear-engine' ),
			);
		}

		if ( ! empty( $row['post_id'] ) ) {
			return array( (string) get_edit_post_link( (int) $row['post_id'], 'raw' ), __( 'Edit page', 'gear-engine' ) );
		}
		$url = add_query_arg(
			array(
				'post_type'    => 'page',
				'post_title'   => rawurlencode( (string) $row['title'] ),
				'post_content' => rawurlencode( (string) $row['example'] ),
			),
			admin_url( 'post-new.php' )
		);
		return array( $url, __( 'Create page', 'gear-engine' ) );
	}

	/**
	 * Render the page.
	 *
	 * @return void
	 */
	public function render_page(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have permission to access this page.', 'gear-engine' ), '', array( 'response' => 403 ) );
		}

		$shortcodes   = self::get_shortcodes();
		$page_map     = self::get_page_map();
		$stack_status = class_exists( 'Gear_Engine_Recommended_Stack' ) ? Gear_Engine_Recommended_Stack::get_status() : array();
		include GEAR_ENGINE_PATH . 'modules/admin/templates/shortcode-guide.php';
	}

	/**
	 * Handle admin request to automatically install & activate GeneratePress and GenerateBlocks.
	 *
	 * @return void
	 */
	public function handle_install_recommended_stack(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have permission to access this page.', 'gear-engine' ), '', array( 'response' => 403 ) );
		}

		check_admin_referer( 'gear_engine_install_recommended_stack', 'gear_engine_nonce' );

		$res = class_exists( 'Gear_Engine_Recommended_Stack' )
			? Gear_Engine_Recommended_Stack::install_and_activate()
			: array( 'success' => false, 'errors' => array( 'Module missing' ) );

		$args = array(
			'page' => self::PAGE_SLUG,
		);

		if ( ! empty( $res['success'] ) ) {
			$args['gear_engine_stack_installed'] = '1';
		} elseif ( ! empty( $res['errors'] ) ) {
			$args['gear_engine_stack_error'] = rawurlencode( implode( ' ', $res['errors'] ) );
		}

		$redirect_url = add_query_arg( $args, admin_url( 'options-general.php' ) );

		if ( function_exists( 'wp_safe_redirect' ) ) {
			wp_safe_redirect( $redirect_url );
			if ( ! defined( 'GEAR_ENGINE_TESTING' ) ) {
				exit;
			}
		}
	}

	/**
	 * Handle admin request to automatically create missing core pages.
	 *
	 * @return void
	 */
	public function handle_create_core_pages(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have permission to access this page.', 'gear-engine' ), '', array( 'response' => 403 ) );
		}

		check_admin_referer( 'gear_engine_create_core_pages', 'gear_engine_nonce' );

		$base_triage  = class_exists( 'Gear_Engine_Triage' ) ? trim( Gear_Engine_Triage::get_base_path(), '/' ) : 'symptomsjekker';
		$base_triage  = '' !== $base_triage ? $base_triage : 'symptomsjekker';
		$program_path = class_exists( 'Gear_Engine_Protocol_Viewer' ) ? trim( Gear_Engine_Protocol_Viewer::get_page_path(), '/' ) : 'treningsprogram';
		$program_path = '' !== $program_path ? $program_path : 'treningsprogram';
		$hub_tag      = class_exists( 'Gear_Engine_Gear_Picker' ) ? Gear_Engine_Gear_Picker::HUB_SHORTCODE_TAG : 'gear_engine_gear_picker_hub';
		$triage_tag   = class_exists( 'Gear_Engine_Triage' ) ? Gear_Engine_Triage::PICKER_SHORTCODE_TAG : 'gear_engine_triage_picker';
		$viewer_tag   = class_exists( 'Gear_Engine_Protocol_Viewer' ) ? Gear_Engine_Protocol_Viewer::SHORTCODE_TAG : 'gear_engine_protocol';
		$compare_tag  = class_exists( 'Gear_Engine_Price_Comparison' ) ? Gear_Engine_Price_Comparison::SHORTCODE_TAG : 'gear_engine_price_comparison';

		$core_pages = array(
			array(
				'slug'    => 'kjopsveiledere',
				'title'   => __( 'Kjøpsveiledere', 'gear-engine' ),
				'content' => '<!-- wp:shortcode -->[' . $hub_tag . ']<!-- /wp:shortcode -->',
				'tag'     => $hub_tag,
			),
			array(
				'slug'    => 'priser',
				'title'   => __( 'Prissammenligning', 'gear-engine' ),
				'content' => '<!-- wp:shortcode -->[' . $compare_tag . ']<!-- /wp:shortcode -->',
				'tag'     => $compare_tag,
			),
			array(
				'slug'    => $program_path,
				'title'   => __( 'Treningsprogram', 'gear-engine' ),
				'content' => '<!-- wp:shortcode -->[' . $viewer_tag . ']<!-- /wp:shortcode -->',
				'tag'     => $viewer_tag,
			),
			array(
				'slug'    => $base_triage,
				'title'   => __( 'Symptomsjekker', 'gear-engine' ),
				'content' => '<!-- wp:shortcode -->[' . $triage_tag . ']<!-- /wp:shortcode -->',
				'tag'     => $triage_tag,
			),
			array(
				'slug'    => 'fysio-chat',
				'title'   => __( 'Digital Fysio', 'gear-engine' ),
				'content' => '<!-- wp:shortcode -->[gear_engine_physio_chat]<!-- /wp:shortcode -->',
				'tag'     => 'gear_engine_physio_chat',
			),
		);

		foreach ( $core_pages as $page_def ) {
			$existing = function_exists( 'get_page_by_path' ) ? get_page_by_path( $page_def['slug'] ) : null;
			if ( ! $existing && function_exists( 'wp_insert_post' ) ) {
				// 1. Fresh install: Create page from scratch with the shortcode pre-inserted.
				wp_insert_post(
					array(
						'post_title'   => $page_def['title'],
						'post_name'    => $page_def['slug'],
						'post_content' => $page_def['content'],
						'post_status'  => 'publish',
						'post_type'    => 'page',
					)
				);
			} elseif ( $existing && function_exists( 'wp_update_post' ) && function_exists( 'has_shortcode' ) ) {
				// 2. Existing page: If page exists but lacks the shortcode, append/insert it!
				if ( ! has_shortcode( (string) $existing->post_content, $page_def['tag'] ) ) {
					$current = trim( (string) $existing->post_content );
					$updated = '' !== $current
						? $current . "\n\n" . $page_def['content']
						: $page_def['content'];

					wp_update_post(
						array(
							'ID'           => $existing->ID,
							'post_content' => $updated,
						)
					);
				}
			}
		}

		// Also ensure triage subpages are created if triage module exists.
		if ( class_exists( 'Gear_Engine_Triage' ) && function_exists( 'wp_insert_post' ) && function_exists( 'get_page_by_path' ) ) {
			$parent_page = get_page_by_path( $base_triage );
			if ( $parent_page ) {
				foreach ( Gear_Engine_Triage::get_regions() as $region ) {
					$child_path = $base_triage . '/' . $region;
					$child_page = get_page_by_path( $child_path );
					if ( ! $child_page ) {
						$config = Gear_Engine_Triage::get_config( $region );
						$label  = ( ! is_wp_error( $config ) && ! empty( $config['label'] ) ) ? $config['label'] : ucfirst( $region );
						$title  = sprintf(
							/* translators: %s: Region label */
							__( 'Symptomsjekk: %s', 'gear-engine' ),
							$label
						);
						$content = '<!-- wp:paragraph -->' . "\n" .
							'<p>' . esc_html( sprintf( __( 'Rask, digital vurdering for %s. Svar på spørsmålene for å få råd og veiledning.', 'gear-engine' ), strtolower( $label ) ) ) . '</p>' . "\n" .
							'<!-- /wp:paragraph -->' . "\n\n" .
							'<!-- wp:shortcode -->[' . Gear_Engine_Triage::SHORTCODE_TAG . ' region="' . esc_attr( $region ) . '"]<!-- /wp:shortcode -->';

						wp_insert_post(
							array(
								'post_title'   => $title,
								'post_name'    => $region,
								'post_parent'  => $parent_page->ID,
								'post_content' => $content,
								'post_status'  => 'publish',
								'post_type'    => 'page',
							)
						);
					} elseif ( function_exists( 'wp_update_post' ) && function_exists( 'has_shortcode' ) ) {
						if ( ! has_shortcode( (string) $child_page->post_content, Gear_Engine_Triage::SHORTCODE_TAG ) ) {
							$current         = trim( (string) $child_page->post_content );
							$shortcode_block = '<!-- wp:shortcode -->[' . Gear_Engine_Triage::SHORTCODE_TAG . ' region="' . esc_attr( $region ) . '"]<!-- /wp:shortcode -->';
							$updated         = '' !== $current ? $current . "\n\n" . $shortcode_block : $shortcode_block;

							wp_update_post(
								array(
									'ID'           => $child_page->ID,
									'post_content' => $updated,
								)
							);
						}
					}
				}
			}
		}

		$redirect_url = add_query_arg(
			array(
				'page'                      => self::PAGE_SLUG,
				'gear_engine_pages_created' => '1',
			),
			admin_url( 'options-general.php' )
		);

		if ( function_exists( 'wp_safe_redirect' ) ) {
			wp_safe_redirect( $redirect_url );
			if ( ! defined( 'GEAR_ENGINE_TESTING' ) ) {
				exit;
			}
		}
	}
}
