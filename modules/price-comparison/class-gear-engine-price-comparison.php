<?php
/**
 * Price Comparison Engine Module.
 *
 * Faceted price comparison catalog for exercise gear across partner shops,
 * with real-time filtering, dual-range price slider, category tabs,
 * and grid/list layout toggling.
 *
 * @package GearEngine
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

/**
 * Class Gear_Engine_Price_Comparison
 */
class Gear_Engine_Price_Comparison {

	/**
	 * Shortcode tag.
	 */
	public const SHORTCODE_TAG = 'gear_engine_price_comparison';

	/**
	 * Secondary alias shortcode tag.
	 */
	public const SHORTCODE_ALIAS = 'fysionett_prissammenligning';

	/**
	 * Option holding cache version for feed invalidation.
	 */
	public const OPTION_CACHE_VERSION = 'gear_engine_price_comparison_cache_version';

	/**
	 * Constructor.
	 */
	public function __construct() {
		add_action( 'init', array( $this, 'register_shortcode' ) );
		add_action( 'wp_enqueue_scripts', array( $this, 'register_assets' ) );
		add_action( 'save_post_' . Gear_Engine_CPT::POST_TYPE, array( __CLASS__, 'flush_cache' ) );
		add_action( 'deleted_post', array( __CLASS__, 'flush_cache' ) );
	}

	/**
	 * Register module scripts and styles.
	 *
	 * @return void
	 */
	public function register_assets(): void {
		$base_url = GEAR_ENGINE_URL . 'modules/price-comparison/assets/';

		wp_register_style(
			'gear-engine-price-comparison',
			$base_url . 'css/price-comparison.css',
			array( 'gear-engine-tokens' ),
			GEAR_ENGINE_VERSION
		);

		wp_register_script(
			'gear-engine-price-comparison',
			$base_url . 'js/price-comparison.js',
			array(),
			GEAR_ENGINE_VERSION,
			true
		);
	}

	/**
	 * Register shortcodes.
	 *
	 * @return void
	 */
	public function register_shortcode(): void {
		add_shortcode( self::SHORTCODE_TAG, array( $this, 'render' ) );
		add_shortcode( self::SHORTCODE_ALIAS, array( $this, 'render' ) );
	}

	/**
	 * Flush transient cache on product updates.
	 *
	 * @return void
	 */
	public static function flush_cache(): void {
		update_option( self::OPTION_CACHE_VERSION, time(), false );
	}

	/**
	 * Get fallback products matching the user's Unsplash reference dataset.
	 *
	 * @return array<int, array<string, mixed>>
	 */
	public static function get_fallback_products(): array {
		return array(
			array(
				'id'            => 1,
				'category'      => 'Romaskiner',
				'brand'         => 'Concept2',
				'name'          => 'RowErg PM5 Romaskin Standard',
				'currentPrice'  => 12490,
				'originalPrice' => 15600,
				'discount'      => 20,
				'shop'          => 'Fjellsport',
				'otherShops'    => 3,
				'inStock'       => true,
				'image'         => 'https://images.unsplash.com/photo-1534438327276-14e5300c3a48?auto=format&fit=crop&w=400&q=80',
				'url'           => '#',
			),
			array(
				'id'            => 2,
				'category'      => 'Tredemøller',
				'brand'         => 'Sole',
				'name'          => 'F85 Tredemølle 2024-modell m/Bluetooth og touchskjerm',
				'currentPrice'  => 24900,
				'originalPrice' => 29294,
				'discount'      => 15,
				'shop'          => 'Treningspartner',
				'otherShops'    => 0,
				'inStock'       => true,
				'image'         => 'https://images.unsplash.com/photo-1576678927484-cc907957088c?auto=format&fit=crop&w=400&q=80',
				'url'           => '#',
			),
			array(
				'id'            => 3,
				'category'      => 'Kettlebells',
				'brand'         => 'Abilica',
				'name'          => 'Competition Kettlebell 16kg',
				'currentPrice'  => 899,
				'originalPrice' => 1199,
				'discount'      => 25,
				'shop'          => 'X-Life',
				'otherShops'    => 1,
				'inStock'       => true,
				'image'         => 'https://images.unsplash.com/photo-1584735935682-2f2b69dff9d2?auto=format&fit=crop&w=400&q=80',
				'url'           => '#',
			),
			array(
				'id'            => 4,
				'category'      => 'Racks og stativer',
				'brand'         => 'Titan Life',
				'name'          => 'Squat Rack Pro Hjemmegym',
				'currentPrice'  => 3499,
				'originalPrice' => 4500,
				'discount'      => 22,
				'shop'          => 'Treningspartner',
				'otherShops'    => 0,
				'inStock'       => true,
				'image'         => 'https://images.unsplash.com/photo-1518611012118-696072aa579a?auto=format&fit=crop&w=400&q=80',
				'url'           => '#',
			),
			array(
				'id'            => 5,
				'category'      => 'Vektstenger',
				'brand'         => 'Gymgrossisten',
				'name'          => 'Olympisk Vektstang 20kg',
				'currentPrice'  => 1999,
				'originalPrice' => 2499,
				'discount'      => 20,
				'shop'          => 'Gymgrossisten',
				'otherShops'    => 0,
				'inStock'       => true,
				'image'         => 'https://images.unsplash.com/photo-1571019614242-c5c5dee9f50b?auto=format&fit=crop&w=400&q=80',
				'url'           => '#',
			),
			array(
				'id'            => 7,
				'category'      => 'Massasje',
				'brand'         => 'Therabody',
				'name'          => 'Theragun Pro G5',
				'currentPrice'  => 4999,
				'originalPrice' => 5999,
				'discount'      => 16,
				'shop'          => 'Anton Sport',
				'otherShops'    => 0,
				'inStock'       => true,
				'image'         => 'https://images.unsplash.com/photo-1544367567-0f2fcb009e0b?auto=format&fit=crop&w=400&q=80',
				'url'           => '#',
			),
			array(
				'id'            => 8,
				'category'      => 'Manualer',
				'brand'         => 'Bowflex',
				'name'          => 'SelectTech 552i Justerbare Manualer',
				'currentPrice'  => 2999,
				'originalPrice' => null,
				'discount'      => null,
				'shop'          => 'Fjellsport',
				'otherShops'    => 2,
				'inStock'       => true,
				'image'         => 'https://images.unsplash.com/photo-1517502884422-41eaead166d4?auto=format&fit=crop&w=400&q=80',
				'url'           => '#',
			),
		);
	}

	/**
	 * Build normalized price comparison product feed from exercise_gear CPT.
	 *
	 * @return array<int, array<string, mixed>>
	 */
	public static function get_feed(): array {
		$cache_version = get_option( self::OPTION_CACHE_VERSION, 0 );
		$cache_key     = 'gear_engine_price_comparison_feed_' . $cache_version;
		$cached        = get_transient( $cache_key );

		if ( is_array( $cached ) && ! empty( $cached ) ) {
			return $cached;
		}

		$posts = Gear_Engine_Product_Service::get_raw_posts(
			array(
				'posts_per_page' => 1000,
			)
		);

		if ( empty( $posts ) ) {
			return self::get_fallback_products();
		}

		// First pass: count merchants per EAN/clean name to compute otherShops accurately.
		$item_counts = array();
		foreach ( $posts as $p ) {
			$ean = (string) get_post_meta( $p->ID, Gear_Engine_CPT::META_EAN, true );
			$key = ! empty( $ean ) ? 'ean_' . $ean : 'name_' . sanitize_title( $p->post_title );
			if ( ! isset( $item_counts[ $key ] ) ) {
				$item_counts[ $key ] = 0;
			}
			$item_counts[ $key ]++;
		}

		$feed = array();

		foreach ( $posts as $post ) {
			$post_id       = $post->ID;
			$price         = (float) get_post_meta( $post_id, Gear_Engine_CPT::META_PRICE, true );
			$regular_price = (float) get_post_meta( $post_id, Gear_Engine_CPT::META_REGULAR_PRICE, true );
			$affiliate_url = (string) get_post_meta( $post_id, Gear_Engine_CPT::META_AFFILIATE_URL, true );
			$raw_merchant  = (string) get_post_meta( $post_id, Gear_Engine_CPT::META_MERCHANT, true );
			$in_stock      = (bool) get_post_meta( $post_id, Gear_Engine_CPT::META_IN_STOCK, true );
			$ean           = (string) get_post_meta( $post_id, Gear_Engine_CPT::META_EAN, true );

			// Resolve image.
			$image_url = '';
			if ( has_post_thumbnail( $post_id ) ) {
				$thumb = wp_get_attachment_image_src( get_post_thumbnail_id( $post_id ), 'large' );
				if ( ! empty( $thumb[0] ) ) {
					$image_url = $thumb[0];
				}
			}
			if ( empty( $image_url ) ) {
				$image_url = (string) get_post_meta( $post_id, Gear_Engine_CPT::META_IMAGE_URL, true );
			}
			if ( empty( $image_url ) ) {
				$image_url = 'https://placehold.co/400x400/f1f5f9/1A2A3A?text=' . rawurlencode( $post->post_title );
			}

			// Resolve category from taxonomy.
			$category = 'Treningsutstyr';
			$terms    = get_the_terms( $post_id, Gear_Engine_CPT::TAXONOMY );
			if ( ! empty( $terms ) && ! is_wp_error( $terms ) ) {
				$category = $terms[0]->name;
			}

			// Resolve merchant shop name and brand.
			$shop_name = $raw_merchant;
			$brand     = '';

			if ( preg_match( '/^(.*?)\s*\(([^)]+)\)$/', $raw_merchant, $merchant_match ) ) {
				$brand     = trim( $merchant_match[1] );
				$shop_name = trim( $merchant_match[2] );
			}

			// If brand is still missing, attempt extraction from title prefix.
			if ( empty( $brand ) ) {
				if ( false !== strpos( $post->post_title, ' - ' ) ) {
					$parts = explode( ' - ', $post->post_title );
					$brand = trim( $parts[0] );
				} elseif ( preg_match( '/^([A-Z0-9][a-zA-Z0-9+]+)\b/', $post->post_title, $title_match ) ) {
					$brand = trim( $title_match[1] );
				}
			}
			if ( empty( $brand ) ) {
				$brand = $shop_name ?: 'Merke';
			}
			if ( empty( $shop_name ) ) {
				$shop_name = 'Nettbutikk';
			}

			// Calculate discount percentage.
			$discount = null;
			if ( $regular_price > $price && $regular_price > 0 && $price > 0 ) {
				$discount = (int) round( ( ( $regular_price - $price ) / $regular_price ) * 100 );
			}

			// Calculate count of other shops selling this product.
			$group_key   = ! empty( $ean ) ? 'ean_' . $ean : 'name_' . sanitize_title( $post->post_title );
			$total_shops = $item_counts[ $group_key ] ?? 1;
			$other_shops = max( 0, $total_shops - 1 );

			$feed[] = array(
				'id'            => $post_id,
				'category'      => $category,
				'brand'         => $brand,
				'name'          => $post->post_title,
				'currentPrice'  => $price > 0 ? $price : 0,
				'originalPrice' => ( $regular_price > $price && $regular_price > 0 ) ? $regular_price : null,
				'discount'      => $discount,
				'shop'          => $shop_name,
				'otherShops'    => $other_shops,
				'inStock'       => $in_stock,
				'image'         => esc_url_raw( $image_url ),
				'url'           => esc_url_raw( $affiliate_url ?: '#' ),
			);
		}

		if ( empty( $feed ) ) {
			$feed = self::get_fallback_products();
		}

		set_transient( $cache_key, $feed, HOUR_IN_SECONDS );
		return $feed;
	}

	/**
	 * Render the price comparison catalog shortcode.
	 *
	 * @param array<string, mixed> $atts Shortcode attributes.
	 * @return string Rendered HTML.
	 */
	public function render( $atts = array() ): string {
		$atts = shortcode_atts(
			array(
				'category'   => 'Alle',
				'view'       => 'grid',
				'limit'      => 12,
				'show_guide' => 'true',
			),
			$atts,
			self::SHORTCODE_TAG
		);

		wp_enqueue_style( 'gear-engine-tokens' );
		wp_enqueue_style( 'gear-engine-price-comparison' );
		wp_enqueue_script( 'gear-engine-price-comparison' );

		$feed_data = self::get_feed();

		// Encode JSON safely for embedding in data attribute.
		$encoded_feed = rawurlencode( (string) wp_json_encode( $feed_data ) );

		$initial_category = sanitize_text_field( $atts['category'] );
		$initial_view     = in_array( $atts['view'], array( 'grid', 'list' ), true ) ? $atts['view'] : 'grid';
		$initial_limit    = max( 4, min( 48, absint( $atts['limit'] ) ) );
		$show_guide_link  = 'false' !== strtolower( (string) $atts['show_guide'] );

		ob_start();
		$template_path = GEAR_ENGINE_PATH . 'modules/price-comparison/templates/price-comparison-container.php';
		if ( file_exists( $template_path ) ) {
			include $template_path;
		}
		return ob_get_clean();
	}
}

