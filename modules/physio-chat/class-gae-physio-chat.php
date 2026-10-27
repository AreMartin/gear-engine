<?php
/**
 * Physio Advisor Chat Module Loader & Controller.
 *
 * Registers REST API endpoints, shortcodes, and Gutenberg blocks for the
 * interactive physiotherapist advisor chat interface.
 *
 * @package GearEngine
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

require_once dirname( __FILE__ ) . '/class-gae-chat-agent.php';

/**
 * Class GAE_Physio_Chat
 */
class GAE_Physio_Chat {

	/**
	 * Module identifier.
	 */
	public const MODULE_ID = 'physio-chat';

	/**
	 * Shortcode tag.
	 */
	public const SHORTCODE_TAG = 'gae_physio_chat';

	/**
	 * Constructor.
	 */
	public function __construct() {
		add_action( 'rest_api_init', array( $this, 'register_rest_routes' ) );
		add_action( 'init', array( $this, 'register_shortcode' ) );
		add_action( 'init', array( $this, 'register_gutenberg_block' ) );
		add_action( 'wp_enqueue_scripts', array( $this, 'register_assets' ) );
	}

	/**
	 * Register REST routes for the chat interface.
	 *
	 * @return void
	 */
	public function register_rest_routes(): void {
		// Primary route: /wp-json/gae/v1/physio/chat.
		register_rest_route(
			'gae/v1',
			'/physio/chat',
			array(
				array(
					'methods'             => 'POST',
					'callback'            => array( 'GAE_Chat_Agent', 'handle_chat_request' ),
					'permission_callback' => '__return_true',
				),
			)
		);

		// Backwards-compatible alias matching prototype: /wp-json/fysio/v1/chat.
		register_rest_route(
			'fysio/v1',
			'/chat',
			array(
				array(
					'methods'             => 'POST',
					'callback'            => array( 'GAE_Chat_Agent', 'handle_chat_request' ),
					'permission_callback' => '__return_true',
				),
			)
		);
	}

	/**
	 * Register client scripts and stylesheets.
	 *
	 * @return void
	 */
	public function register_assets(): void {
		$plugin_url = plugin_dir_url( GAE_PLUGIN_FILE );

		// FontAwesome 6.4.0.
		wp_register_style(
			'gae-fontawesome',
			'https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css',
			array(),
			'6.4.0'
		);

		// Google Fonts Poppins.
		wp_register_style(
			'gae-poppins-font',
			'https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700;800&display=swap',
			array(),
			null
		);

		// Module Scoped Stylesheet (Production Standalone CSS - zero CDN dependency).
		wp_register_style(
			'gae-physio-chat-css',
			$plugin_url . 'modules/physio-chat/assets/css/physio-chat.css',
			array( 'gae-fontawesome', 'gae-poppins-font' ),
			GAE_VERSION
		);

		// Module Client JavaScript.
		wp_register_script(
			'gae-physio-chat-js',
			$plugin_url . 'modules/physio-chat/assets/js/physio-chat.js',
			array(),
			GAE_VERSION,
			true
		);

		wp_localize_script(
			'gae-physio-chat-js',
			'gaePhysioData',
			array(
				'restUrl'   => esc_url_raw( rest_url( 'gae/v1/physio/chat' ) ),
				'legacyUrl' => esc_url_raw( rest_url( 'fysio/v1/chat' ) ),
			)
		);
	}

	/**
	 * Register [gae_physio_chat] shortcode.
	 *
	 * @return void
	 */
	public function register_shortcode(): void {
		add_shortcode( self::SHORTCODE_TAG, array( $this, 'render_chat_shortcode' ) );
		// Alias [digital_fysio_chat] for compatibility.
		add_shortcode( 'digital_fysio_chat', array( $this, 'render_chat_shortcode' ) );
	}

	/**
	 * Render shortcode output.
	 *
	 * @param array $atts
	 * @return string
	 */
	public function render_chat_shortcode( $atts = array() ): string {
		$atts = shortcode_atts(
			array(
				'height'    => '100dvh',
				'fullscreen' => 'true',
			),
			$atts,
			self::SHORTCODE_TAG
		);

		wp_enqueue_style( 'gae-physio-chat-css' );
		wp_enqueue_script( 'gae-physio-chat-js' );

		ob_start();
		$template_path = dirname( __FILE__ ) . '/templates/chat-container.php';
		if ( file_exists( $template_path ) ) {
			include $template_path;
		}
		return ob_get_clean();
	}

	/**
	 * Register Gutenberg block for block editor.
	 *
	 * @return void
	 */
	public function register_gutenberg_block(): void {
		if ( ! function_exists( 'register_block_type' ) ) {
			return;
		}

		$plugin_url = plugin_dir_url( GAE_PLUGIN_FILE );

		wp_register_script(
			'gae-physio-block-editor',
			$plugin_url . 'modules/physio-chat/assets/js/block-editor.js',
			array( 'wp-blocks', 'wp-element', 'wp-editor', 'wp-components' ),
			GAE_VERSION,
			true
		);

		register_block_type(
			'gear-engine/physio-chat',
			array(
				'editor_script'   => 'gae-physio-block-editor',
				'render_callback' => array( $this, 'render_chat_shortcode' ),
				'attributes'      => array(
					'fullscreen' => array(
						'type'    => 'boolean',
						'default' => true,
					),
				),
			)
		);
	}
}
