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
				'group'       => __( 'Smertesjekk (quiz)', 'gear-engine' ),
				'tag'         => Gear_Engine_Triage::PICKER_SHORTCODE_TAG,
				'example'     => '[' . Gear_Engine_Triage::PICKER_SHORTCODE_TAG . ']',
				'description' => __( 'Region picker with one card per body region. Each card links to the region page.', 'gear-engine' ),
				'note'        => __( 'Goes on the page the "Smertesjekk" card links to.', 'gear-engine' ),
			),
			array(
				'group'       => __( 'Smertesjekk (quiz)', 'gear-engine' ),
				'tag'         => Gear_Engine_Triage::SHORTCODE_TAG,
				'example'     => '[' . Gear_Engine_Triage::SHORTCODE_TAG . ' region="rygg"]',
				'description' => __( 'One quiz for one region. Options: region, back_link="true|false".', 'gear-engine' ),
				'note'        => __( 'One page per region. Ready-made shortcodes are listed in the page map below.', 'gear-engine' ),
			),
			array(
				'group'       => __( 'Treningsprogram', 'gear-engine' ),
				'tag'         => Gear_Engine_Protocol_Viewer::SHORTCODE_TAG,
				'example'     => '[' . Gear_Engine_Protocol_Viewer::SHORTCODE_TAG . ']',
				'description' => __( 'The program page. Quiz results open it with ?program=… or ?region=…&dx=…; without a parameter it lists approved programs.', 'gear-engine' ),
				'note'        => __( 'One page only, at the program page path.', 'gear-engine' ),
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
			'title'   => __( 'Smertesjekk (picker)', 'gear-engine' ),
			'path'    => $base,
			'tag'     => $picker,
			'example' => '[' . $picker . ']',
			'hint'    => __( 'Linked from the front page "Smertesjekk" card. Shows the region cards.', 'gear-engine' ),
		);
		foreach ( self::get_region_shortcodes() as $row ) {
			$map[] = array(
				'level'   => 1,
				'title'   => sprintf(
					/* translators: %s: region name */
					__( 'Smertesjekk: %s', 'gear-engine' ),
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
			'hint'    => __( 'Opened from the quiz result button.', 'gear-engine' ),
		);

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

		$shortcodes = self::get_shortcodes();
		$page_map   = self::get_page_map();
		include GEAR_ENGINE_PATH . 'modules/admin/templates/shortcode-guide.php';
	}
}
