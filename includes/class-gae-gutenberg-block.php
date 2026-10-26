<?php
/**
 * Gutenberg Block & Frontend Card Module.
 *
 * @package GearEngine
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

/**
 * Class GAE_Gutenberg_Block
 *
 * Registers the native Gutenberg dynamic block and shortcode to render horizontal product cards.
 */
class GAE_Gutenberg_Block {

	/**
	 * Block identifier.
	 */
	public const BLOCK_NAME = 'gae/product-card';

	/**
	 * Constructor.
	 */
	public function __construct() {
		add_action( 'init', array( $this, 'register_block' ) );
		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_frontend_assets' ) );
		add_shortcode( 'gear_product', array( $this, 'render_shortcode' ) );
	}

	/**
	 * Register Gutenberg dynamic block type and editor assets.
	 *
	 * @return void
	 */
	public function register_block(): void {
		// Register editor script.
		wp_register_script(
			'gae-block-editor-script',
			GAE_PLUGIN_URL . 'assets/js/block.js',
			array( 'wp-blocks', 'wp-element', 'wp-components', 'wp-block-editor', 'wp-server-side-render', 'wp-api-fetch' ),
			GAE_VERSION,
			true
		);

		// Register shared block style.
		wp_register_style(
			'gae-block-style',
			GAE_PLUGIN_URL . 'assets/css/product-card.css',
			array(),
			GAE_VERSION
		);

		register_block_type(
			self::BLOCK_NAME,
			array(
				'api_version'     => 2,
				'editor_script'   => 'gae-block-editor-script',
				'style'           => 'gae-block-style',
				'render_callback' => array( $this, 'render_block' ),
				'attributes'      => array(
					'productId'     => array(
						'type'    => 'number',
						'default' => 0,
					),
					'showAiSummary' => array(
						'type'    => 'boolean',
						'default' => true,
					),
					'customPitch'   => array(
						'type'    => 'string',
						'default' => '',
					),
					'buttonText'    => array(
						'type'    => 'string',
						'default' => __( 'Order now', 'gear-engine' ),
					),
				),
			)
		);
	}

	/**
	 * Enqueue stylesheet on frontend.
	 *
	 * @return void
	 */
	public function enqueue_frontend_assets(): void {
		wp_enqueue_style(
			'gae-product-card-css',
			GAE_PLUGIN_URL . 'assets/css/product-card.css',
			array(),
			GAE_VERSION
		);
	}

	/**
	 * Dynamic render callback for Gutenberg block.
	 *
	 * @param array<string, mixed> $attributes Block attributes.
	 * @param string               $content    Block inner content.
	 * @return string Rendered HTML.
	 */
	public function render_block( array $attributes, string $content = '' ): string {
		$product_id      = isset( $attributes['productId'] ) ? absint( $attributes['productId'] ) : 0;
		$show_ai_summary = ! isset( $attributes['showAiSummary'] ) || (bool) $attributes['showAiSummary'];
		$custom_pitch    = isset( $attributes['customPitch'] ) ? trim( (string) $attributes['customPitch'] ) : '';
		$button_text     = ! empty( $attributes['buttonText'] ) ? sanitize_text_field( $attributes['buttonText'] ) : __( 'Order now', 'gear-engine' );

		if ( $product_id <= 0 ) {
			if ( is_admin() || wp_doing_ajax() ) {
				return '<div class="gae-block-placeholder"><p>' . esc_html__( 'Please select an exercise gear item in the sidebar settings.', 'gear-engine' ) . '</p></div>';
			}
			return '';
		}

		return $this->render_product_card( $product_id, $show_ai_summary, $custom_pitch, $button_text );
	}

	/**
	 * Shortcode callback: [gear_product id="123" pitch="..."]
	 *
	 * @param array<string, mixed>|string $atts Shortcode attributes.
	 * @return string
	 */
	public function render_shortcode( $atts ): string {
		$args = shortcode_atts(
			array(
				'id'          => 0,
				'ean'         => '',
				'show_ai'     => '1',
				'pitch'       => '',
				'button_text' => __( 'Order now', 'gear-engine' ),
			),
			$atts,
			'gear_product'
		);

		$product_id = absint( $args['id'] );

		// If EAN is supplied instead of ID, find product by EAN.
		if ( 0 === $product_id && ! empty( $args['ean'] ) ) {
			$found = GAE_CPT::get_product_by_ean( sanitize_text_field( $args['ean'] ) );
			if ( $found ) {
				$product_id = $found->ID;
			}
		}

		if ( $product_id <= 0 ) {
			return '';
		}

		$show_ai = ! in_array( strtolower( (string) $args['show_ai'] ), array( '0', 'false', 'no' ), true );

		return $this->render_product_card( $product_id, $show_ai, (string) $args['pitch'], (string) $args['button_text'] );
	}

	/**
	 * Render horizontal product card HTML.
	 *
	 * Matches the exact UI prototype layout and styling.
	 *
	 * @param int    $product_id      Exercise gear post ID.
	 * @param bool   $show_ai_summary Whether to display the AI insights box.
	 * @param string $custom_pitch    Custom pitch override.
	 * @param string $button_text     CTA button label.
	 * @return string Rendered HTML.
	 */
	public function render_product_card( int $product_id, bool $show_ai_summary = true, string $custom_pitch = '', string $button_text = 'Order now' ): string {
		$product = GAE_CPT::get_product_data( $product_id );
		if ( ! $product ) {
			return '';
		}

		// Ensure CSS is loaded.
		wp_enqueue_style( 'gae-product-card-css', GAE_PLUGIN_URL . 'assets/css/product-card.css', array(), GAE_VERSION );

		// Determine AI insights summary text.
		$ai_pitch = '';
		if ( $show_ai_summary ) {
			if ( ! empty( $custom_pitch ) ) {
				$ai_pitch = $custom_pitch;
			} else {
				// Check if there is an existing cached AI recommendation or generate excerpt pitch.
				$ai_pitch = $this->get_default_ai_pitch( $product );
			}
		}

		$cta_url = ! empty( $product['affiliate_url'] ) ? $product['affiliate_url'] : $product['permalink'];

		ob_start();
		?>
		<div class="gae-product-card" id="gae-product-<?php echo esc_attr( (string) $product['id'] ); ?>">
			<div class="gae-image-wrapper">
				<img src="<?php echo esc_url( $product['image_url'] ); ?>" alt="<?php echo esc_attr( $product['title'] ); ?>" loading="lazy" />
			</div>
			<div class="gae-content-wrapper">
				<div class="gae-header">
					<h3 class="gae-title"><?php echo esc_html( $product['title'] ); ?></h3>
				</div>

				<div class="gae-descriptions">
					<?php if ( ! empty( $product['excerpt'] ) ) : ?>
						<div class="gae-standard-desc">
							<?php echo esc_html( $product['excerpt'] ); ?>
						</div>
					<?php endif; ?>

					<?php if ( $show_ai_summary && ! empty( $ai_pitch ) ) : ?>
						<div class="gae-ai-summary">
							<strong>
								<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M11.666 2.005a.75.75 0 0 1 .668 0l.372.186c.65.326 1.222.808 1.666 1.385l.135.187c.28.4.526.822.733 1.26l.128.283c.3.682.723 1.306 1.238 1.834l.19.186c.49.467 1.05.85 1.654 1.127l.325.14c.485.197.944.453 1.365.758a.75.75 0 0 1 0 1.248c-.42.305-.88.56-1.365.758l-.325.14c-.604.277-1.164.66-1.654 1.128l-.19.186c-.515.528-.938 1.152-1.238 1.834l-.128.283a8.966 8.966 0 0 1-.733 1.26l-.135.187c-.444.577-1.016 1.06-1.666 1.385l-.372.186a.75.75 0 0 1-.668 0l-.372-.186a5.526 5.526 0 0 1-1.666-1.385l-.135-.187a8.96 8.96 0 0 1-.733-1.26l-.128-.283a5.518 5.518 0 0 0-1.238-1.834l-.19-.186a5.534 5.534 0 0 0-1.654-1.127l-.325-.14a8.948 8.948 0 0 1-1.365-.758.75.75 0 0 1 0-1.248c.42-.305.88-.56 1.365-.758l.325-.14c.604-.277 1.164-.66 1.654-1.128l.19-.186c.515-.528.938-1.152 1.238-1.834l.128-.283c.207-.438.453-.86.733-1.26l.135-.187c.444-.577 1.016-1.06 1.666-1.385l.372-.186ZM7 19.5a1.5 1.5 0 1 1-3 0 1.5 1.5 0 0 1 3 0Zm14-1.5a1 1 0 1 1-2 0 1 1 0 0 1 2 0Z"/></svg>
								<?php esc_html_e( 'AI Insights', 'gear-engine' ); ?>
							</strong>
							<?php echo esc_html( $ai_pitch ); ?>
						</div>
					<?php endif; ?>
				</div>

				<div class="gae-stock-status <?php echo $product['in_stock'] ? 'gae-in-stock' : 'gae-out-of-stock'; ?>">
					<span class="gae-stock-icon"></span>
					<?php echo $product['in_stock'] ? esc_html__( 'In stock', 'gear-engine' ) : esc_html__( 'Out of stock', 'gear-engine' ); ?>
					<?php if ( ! empty( $product['merchant'] ) ) : ?>
						<span style="color:#64748b; font-weight:400; margin-left: 6px;">· <?php echo esc_html( $product['merchant'] ); ?></span>
					<?php endif; ?>
				</div>

				<div class="gae-footer">
					<div class="gae-pricing">
						<span class="gae-current-price"><?php echo esc_html( $product['formatted_price'] ); ?></span>
						<?php if ( ! empty( $product['formatted_regular'] ) ) : ?>
							<span class="gae-regular-price"><?php echo esc_html( $product['formatted_regular'] ); ?></span>
						<?php endif; ?>
					</div>
					<a href="<?php echo esc_url( $cta_url ); ?>" target="_blank" rel="nofollow noopener sponsored" class="gae-cta-btn">
						<?php echo esc_html( $button_text ); ?>
					</a>
				</div>
			</div>
		</div>
		<?php
		return (string) ob_get_clean();
	}

	/**
	 * Retrieve or generate a default AI pitch for a product.
	 *
	 * Checks multi-variant transient cache or creates a personalized fitness summary.
	 *
	 * @param array<string, mixed> $product Product data.
	 * @return string
	 */
	protected function get_default_ai_pitch( array $product ): string {
		$ean       = $product['ean'] ?: (string) $product['id'];
		$cache_key = 'gae_ai_' . md5( 'default_pitch_' . $ean );

		$cached = get_transient( $cache_key );
		if ( is_array( $cached ) && ! empty( $cached ) ) {
			return (string) $cached[ array_rand( $cached ) ];
		}

		// Intelligent fallback pitch based on product title and description.
		$title = $product['title'];
		$desc  = $product['excerpt'];

		$fallback = sprintf(
			/* translators: 1: Product Title, 2: Merchant name */
			__( 'Optimal choice for dedicated training sessions. %1$s delivers robust build quality, ergonomic mechanics, and superior durability verified by %2$s.', 'gear-engine' ),
			$title,
			$product['merchant'] ?: __( 'leading fitness retailers', 'gear-engine' )
		);

		return $fallback;
	}
}
