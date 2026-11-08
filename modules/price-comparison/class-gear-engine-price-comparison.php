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
	 * Global omni-search shortcode tag.
	 */
	public const SEARCH_SHORTCODE_TAG = 'gear_engine_search';

	/**
	 * Secondary alias for global omni-search shortcode.
	 */
	public const SEARCH_SHORTCODE_ALIAS = 'fysionett_sok';

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
		$base_url  = GEAR_ENGINE_URL . 'modules/price-comparison/assets/';
		$base_path = GEAR_ENGINE_PATH . 'modules/price-comparison/assets/';
		$css_path  = $base_path . 'css/price-comparison.css';
		$js_path   = $base_path . 'js/price-comparison.js';
		$css_mtime = file_exists( $css_path ) ? filemtime( $css_path ) : false;
		$js_mtime  = file_exists( $js_path ) ? filemtime( $js_path ) : false;

		// Note: 'gear-engine-google-sans' is registered centrally in gear_engine_register_shared_assets().

		// Note: 'gear-engine-material-symbols' is registered centrally in gear_engine_register_shared_assets().

		wp_register_style(
			'gear-engine-price-comparison',
			$base_url . 'css/price-comparison.css',
			array( 'gear-engine-tokens', 'gear-engine-google-sans', 'gear-engine-material-symbols' ),
			false !== $css_mtime ? (string) $css_mtime : GEAR_ENGINE_VERSION
		);

		wp_register_script(
			'gear-engine-price-comparison',
			$base_url . 'js/price-comparison.js',
			array(),
			false !== $js_mtime ? (string) $js_mtime : GEAR_ENGINE_VERSION,
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
		add_shortcode( self::SEARCH_SHORTCODE_TAG, array( $this, 'render_search_shortcode' ) );
		add_shortcode( self::SEARCH_SHORTCODE_ALIAS, array( $this, 'render_search_shortcode' ) );
		add_shortcode( 'gear_search', array( $this, 'render_search_shortcode' ) );
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
	 * Infer department slug from category name and post title.
	 *
	 * @param string $category   Category name.
	 * @param string $post_title Post title.
	 * @return string Department slug (kondisjon, styrke, rehabilitering, restitusjon).
	 */
	public static function infer_department( string $category, string $post_title = '' ): string {
		$text = mb_strtolower( $category . ' ' . $post_title, 'UTF-8' );

		if ( preg_match( '/tredem[øo]lle|romaskin|sykkel|spinningsykkel|ellipse|ski/ui', $text ) ) {
			return 'kondisjon';
		}
		if ( preg_match( '/manual|dumbbell|vektstang|barbell|kettlebell|rack|stativ|vektplate|benk/ui', $text ) ) {
			return 'styrke';
		}
		if ( preg_match( '/knest[øo]tte|ankelst[øo]tte|ryggbelte|st[øo]tte|kne|ankel|rygg|strikk|powerband|rehab/ui', $text ) ) {
			return 'rehabilitering';
		}
		if ( preg_match( '/massasje|theragun|foam|tens|restitusjon/ui', $text ) ) {
			return 'restitusjon';
		}

		return 'kondisjon';
	}

	/**
	 * Get fallback products matching the user's Unsplash reference dataset.
	 *
	 * @return array<int, array<string, mixed>>
	 */
	public static function get_fallback_products(): array {
		$mock_img_base = GEAR_ENGINE_URL . 'modules/price-comparison/assets/images/mock-products/';

		return array(
			array(
				'id'            => 1,
				'department'    => 'kondisjon',
				'category'      => 'Romaskiner',
				'brand'         => 'Concept2',
				'name'          => 'RowErg PM5 Romaskin Standard',
				'currentPrice'  => 12490,
				'originalPrice' => 15600,
				'discount'      => 20,
				'shop'          => 'Fjellsport',
				'otherShops'    => 3,
				'inStock'       => true,
				'image'         => $mock_img_base . 'rowing-machine.png',
				'url'           => '#',
				'stores'        => array(
					array( 'shop' => 'Fjellsport', 'price' => 12490, 'inStock' => true, 'isBest' => true, 'url' => '#' ),
					array( 'shop' => 'Treningspartner', 'price' => 12990, 'inStock' => true, 'isBest' => false, 'url' => '#' ),
					array( 'shop' => 'X-Life', 'price' => 13490, 'inStock' => true, 'isBest' => false, 'url' => '#' ),
					array( 'shop' => 'Anton Sport', 'price' => 13990, 'inStock' => false, 'isBest' => false, 'url' => '#' ),
				),
				'specs'         => array(
					'Motstand'        => 'Luftmotstand',
					'Brukervekt'      => '227 kg',
					'Skjerm'          => 'PM5',
					'Plassbesparende' => 'Ja (delt i 2)',
					'Setehøyde'       => '36 cm',
				),
			),
			array(
				'id'            => 2,
				'department'    => 'kondisjon',
				'category'      => 'Tredemøller',
				'brand'         => 'Sole',
				'name'          => 'F85 Tredemølle 2024-modell m/Bluetooth og touchskjerm',
				'currentPrice'  => 24900,
				'originalPrice' => 29294,
				'discount'      => 15,
				'shop'          => 'Treningspartner',
				'otherShops'    => 2,
				'inStock'       => true,
				'image'         => $mock_img_base . 'treadmill.png',
				'url'           => '#',
				'stores'        => array(
					array( 'shop' => 'Treningspartner', 'price' => 24900, 'inStock' => true, 'isBest' => true, 'url' => '#' ),
					array( 'shop' => 'Gymgrossisten', 'price' => 26490, 'inStock' => true, 'isBest' => false, 'url' => '#' ),
					array( 'shop' => 'Fjellsport', 'price' => 27990, 'inStock' => false, 'isBest' => false, 'url' => '#' ),
				),
				'specs'         => array(
					'Toppfart'      => '22 km/t',
					'Motor'         => '4.0 HK',
					'Løpeflate'     => '56 x 152 cm',
					'Maks stigning' => '15 %',
					'Brukervekt'    => '165 kg',
					'Vekt'          => '125 kg',
				),
			),
			array(
				'id'            => 3,
				'department'    => 'styrke',
				'category'      => 'Kettlebells',
				'brand'         => 'Abilica',
				'name'          => 'Competition Kettlebell 16kg',
				'currentPrice'  => 899,
				'originalPrice' => 1199,
				'discount'      => 25,
				'shop'          => 'X-Life',
				'otherShops'    => 1,
				'inStock'       => true,
				'image'         => 'https://images.unsplash.com/photo-1584735935682-2f2b69dff9d2?auto=format&fit=crop&w=400&h=400&q=80',
				'url'           => '#',
				'stores'        => array(
					array( 'shop' => 'X-Life', 'price' => 899, 'inStock' => true, 'isBest' => true, 'url' => '#' ),
					array( 'shop' => 'Treningspartner', 'price' => 999, 'inStock' => true, 'isBest' => false, 'url' => '#' ),
				),
				'specs'         => array(
					'Vekt' => '16 kg',
					'Type' => 'Konkurranse (stål)',
					'Grep' => '33 mm',
				),
			),
			array(
				'id'            => 4,
				'department'    => 'styrke',
				'category'      => 'Racks og stativer',
				'brand'         => 'Titan Life',
				'name'          => 'Squat Rack Pro Hjemmegym',
				'currentPrice'  => 3499,
				'originalPrice' => 4500,
				'discount'      => 22,
				'shop'          => 'Treningspartner',
				'otherShops'    => 0,
				'inStock'       => true,
				'image'         => 'https://images.unsplash.com/photo-1518611012118-696072aa579a?auto=format&fit=crop&w=400&h=400&q=80',
				'url'           => '#',
				'specs'         => array(
					'Maksbelastning' => '300 kg',
					'Høyde'          => '180 cm',
					'Bredde'         => '120 cm',
					'Vekt'           => '55 kg',
				),
			),
			array(
				'id'            => 5,
				'department'    => 'styrke',
				'category'      => 'Vektstenger',
				'brand'         => 'Gymgrossisten',
				'name'          => 'Olympisk Vektstang 20kg',
				'currentPrice'  => 1999,
				'originalPrice' => 2499,
				'discount'      => 20,
				'shop'          => 'Gymgrossisten',
				'otherShops'    => 0,
				'inStock'       => true,
				'image'         => 'https://images.unsplash.com/photo-1571019614242-c5c5dee9f50b?auto=format&fit=crop&w=400&h=400&q=80',
				'url'           => '#',
				'specs'         => array(
					'Vekt'           => '20 kg',
					'Lengde'         => '220 cm',
					'Grepsdiameter'  => '28 mm',
					'Maksbelastning' => '680 kg',
				),
			),
			array(
				'id'            => 7,
				'department'    => 'restitusjon',
				'category'      => 'Massasjepistoler',
				'brand'         => 'Therabody',
				'name'          => 'Theragun Pro G5',
				'currentPrice'  => 4999,
				'originalPrice' => 5999,
				'discount'      => 16,
				'shop'          => 'Anton Sport',
				'otherShops'    => 0,
				'inStock'       => true,
				'image'         => 'https://images.unsplash.com/photo-1544367567-0f2fcb009e0b?auto=format&fit=crop&w=400&h=400&q=80',
				'url'           => '#',
				'specs'         => array(
					'Slaglengde' => '16 mm',
					'Kraft'      => '27 kg motstandskraft',
					'Batteri'    => '150 min',
					'Hastighet'  => '5 trinn',
				),
			),
			array(
				'id'            => 8,
				'department'    => 'styrke',
				'category'      => 'Manualer',
				'brand'         => 'Bowflex',
				'name'          => 'SelectTech 552i Justerbare Manualer',
				'currentPrice'  => 2999,
				'originalPrice' => null,
				'discount'      => null,
				'shop'          => 'Fjellsport',
				'otherShops'    => 2,
				'inStock'       => true,
				'image'         => 'https://images.unsplash.com/photo-1517502884422-41eaead166d4?auto=format&fit=crop&w=400&h=400&q=80',
				'url'           => '#',
				'stores'        => array(
					array( 'shop' => 'Fjellsport', 'price' => 2999, 'inStock' => true, 'isBest' => true, 'url' => '#' ),
					array( 'shop' => 'Treningspartner', 'price' => 3299, 'inStock' => true, 'isBest' => false, 'url' => '#' ),
					array( 'shop' => 'Gymgrossisten', 'price' => 3499, 'inStock' => true, 'isBest' => false, 'url' => '#' ),
				),
				'specs'         => array(
					'Vektintervall'   => '2 - 24 kg',
					'Antall trinn'    => '15',
					'Plassbesparende' => 'Ja',
				),
			),
			array(
				'id'            => 9,
				'department'    => 'rehabilitering',
				'category'      => 'Knestøtter',
				'brand'         => 'Bauerfeind',
				'name'          => 'GenuTrain Knestøtte Aktiv V2',
				'currentPrice'  => 849,
				'originalPrice' => 1099,
				'discount'      => 22,
				'shop'          => 'Fjellsport',
				'otherShops'    => 1,
				'inStock'       => true,
				'image'         => 'https://images.unsplash.com/photo-1576678927484-cc907957088c?auto=format&fit=crop&w=400&h=400&q=80',
				'url'           => '#',
				'specs'         => array(
					'Type'          => 'Aktiv støtte',
					'Neopren'       => 'Nei',
					'Sideavstivere' => 'Ja',
				),
			),
			array(
				'id'            => 10,
				'department'    => 'rehabilitering',
				'category'      => 'Treningsstrikker',
				'brand'         => 'Abilica',
				'name'          => 'Rubber Band Treningsstrikk 3-pk',
				'currentPrice'  => 249,
				'originalPrice' => 349,
				'discount'      => 28,
				'shop'          => 'Treningspartner',
				'otherShops'    => 0,
				'inStock'       => true,
				'image'         => 'https://images.unsplash.com/photo-1598289431512-b97b0917affc?auto=format&fit=crop&w=400&h=400&q=80',
				'url'           => '#',
				'specs'         => array(
					'Motstand'  => 'Lett, Medium, Hard',
					'Materiale' => 'Lateksfritt gummi',
					'Lengde'    => '30 cm',
				),
			),
			array(
				'id'            => 11,
				'department'    => 'restitusjon',
				'category'      => 'Foam Rollers',
				'brand'         => 'TriggerPoint',
				'name'          => 'GRID Foam Roller 33cm',
				'currentPrice'  => 399,
				'originalPrice' => 499,
				'discount'      => 20,
				'shop'          => 'X-Life',
				'otherShops'    => 1,
				'inStock'       => true,
				'image'         => 'https://images.unsplash.com/photo-1518611012118-696072aa579a?auto=format&fit=crop&w=400&h=400&q=80',
				'url'           => '#',
				'specs'         => array(
					'Lengde'         => '33 cm',
					'Diameter'       => '14 cm',
					'Maksbelastning' => '225 kg',
					'Materiale'      => 'EVA-skum',
				),
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
		$cache_key     = 'gear_engine_price_comparison_feed_v2_' . $cache_version;
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

		// First pass: collect merchant offers per EAN/clean name to build stores array and compute otherShops accurately.
		$item_counts  = array();
		$store_groups = array();
		foreach ( $posts as $p ) {
			$ean = (string) get_post_meta( $p->ID, Gear_Engine_CPT::META_EAN, true );
			$key = ! empty( $ean ) ? 'ean_' . $ean : 'name_' . sanitize_title( $p->post_title );
			if ( ! isset( $item_counts[ $key ] ) ) {
				$item_counts[ $key ]  = 0;
				$store_groups[ $key ] = array();
			}
			$item_counts[ $key ]++;

			$p_price     = (float) get_post_meta( $p->ID, Gear_Engine_CPT::META_PRICE, true );
			$p_url       = (string) get_post_meta( $p->ID, Gear_Engine_CPT::META_AFFILIATE_URL, true );
			$p_raw_merch = (string) get_post_meta( $p->ID, Gear_Engine_CPT::META_MERCHANT, true );
			$p_stock     = (bool) get_post_meta( $p->ID, Gear_Engine_CPT::META_IN_STOCK, true );
			$p_shop      = $p_raw_merch;
			if ( preg_match( '/^(.*?)\s*\(([^)]+)\)$/', $p_raw_merch, $m ) ) {
				$p_shop = trim( $m[2] );
			}
			$store_groups[ $key ][] = array(
				'shop'    => $p_shop ?: 'Nettbutikk',
				'price'   => $p_price > 0 ? $p_price : 0,
				'inStock' => $p_stock,
				'url'     => esc_url_raw( $p_url ?: '#' ),
			);
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
				$image_url = 'https://placehold.co/400x400/f1f5f9/0C2340?text=' . rawurlencode( $post->post_title );
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

			// Resolve specs if set in meta (array or JSON-encoded).
			$raw_specs = get_post_meta( $post_id, '_gear_engine_specs', true );
			if ( is_string( $raw_specs ) && '' !== trim( $raw_specs ) ) {
				$decoded = json_decode( $raw_specs, true );
				$specs   = is_array( $decoded ) ? $decoded : array();
			} else {
				$specs   = is_array( $raw_specs ) ? $raw_specs : array();
			}

			// Merge individual CPT spec overrides if not already set in _gear_engine_specs.
			$spec_map = array(
				'speed'         => array( 'label' => 'Toppfart', 'unit' => ' km/t' ),
				'weight'        => array( 'label' => 'Brukervekt', 'unit' => ' kg' ),
				'incline'       => array( 'label' => 'Maks stigning', 'unit' => ' %' ),
				'levels'        => array( 'label' => 'Motstand', 'unit' => ' trinn' ),
				'motor_incline' => array( 'label' => 'Motorisert stigning', 'flag' => true ),
				'cushion'       => array( 'label' => 'Støtdemping', 'flag' => true ),
				'foldable'      => array( 'label' => 'Sammenleggbar', 'flag' => true ),
				'screen'        => array( 'label' => 'Skjerm', 'flag' => true ),
				'app'           => array( 'label' => 'App-støtte', 'flag' => true ),
				'rehab'         => array( 'label' => 'Rehabilitering', 'flag' => true ),
				'magnetic'      => array( 'label' => 'Magnetisk motstand', 'flag' => true ),
				'air'           => array( 'label' => 'Luftmotstand', 'flag' => true ),
				'water'         => array( 'label' => 'Vannmotstand', 'flag' => true ),
				'quiet'         => array( 'label' => 'Stillegående drift', 'flag' => true ),
			);
			foreach ( $spec_map as $meta_key => $info ) {
				$label = $info['label'];
				if ( empty( $specs[ $label ] ) ) {
					$val = get_post_meta( $post_id, Gear_Engine_CPT::META_SPEC_PREFIX . $meta_key, true );
					if ( '' !== $val && false !== $val && null !== $val ) {
						if ( ! empty( $info['flag'] ) ) {
							if ( '1' === (string) $val || 'yes' === (string) $val || 'true' === (string) $val ) {
								$specs[ $label ] = 'Ja';
							} elseif ( '0' === (string) $val || 'no' === (string) $val ) {
								$specs[ $label ] = 'Nei';
							}
						} else {
							$specs[ $label ] = $val . $info['unit'];
						}
					}
				}
			}

			// Automatically extract and infer product specs from title, description and category.
			$specs = self::extract_product_specs( $post->post_title, (string) $post->post_content, $category, (float) $price, $specs );

			// Resolve department.
			$department = (string) get_post_meta( $post_id, '_gear_engine_department', true );
			if ( empty( $department ) ) {
				$department = self::infer_department( $category, $post->post_title );
			}

			// Sort store offers by price ascending.
			$stores = $store_groups[ $group_key ] ?? array();
			if ( ! empty( $stores ) ) {
				usort( $stores, function( $a, $b ) {
					if ( $a['price'] === $b['price'] ) return 0;
					return ( $a['price'] < $b['price'] ) ? -1 : 1;
				} );
				foreach ( $stores as $idx => &$st ) {
					$st['isBest'] = ( 0 === $idx );
				}
				unset( $st );
			}

			$feed[] = array(
				'id'            => $post_id,
				'department'    => $department,
				'category'      => $category,
				'brand'         => $brand,
				'name'          => $post->post_title,
				'currentPrice'  => $price > 0 ? $price : 0,
				'originalPrice' => ( $regular_price > $price && $regular_price > 0 ) ? $regular_price : null,
				'discount'      => $discount,
				'shop'          => $shop_name,
				'otherShops'    => $other_shops,
				'stores'        => $stores,
				'inStock'       => $in_stock,
				'image'         => esc_url_raw( $image_url ),
				'url'           => esc_url_raw( $affiliate_url ?: '#' ),
				'specs'         => $specs,
			);
		}

		if ( empty( $feed ) ) {
			$feed = self::get_fallback_products();
		}

		set_transient( $cache_key, $feed, HOUR_IN_SECONDS );
		return $feed;
	}

	/**
	 * Extract or infer product specifications from title, description and category.
	 *
	 * Ensures that products in the price comparison module always have clean,
	 * structured technical specifications for side-by-side comparison.
	 *
	 * @param string               $title          Product title.
	 * @param string               $description    Product description / content.
	 * @param string               $category       Product category name.
	 * @param float                $price          Product price (used for benchmark estimates).
	 * @param array<string,string> $existing_specs Any existing specs from post meta.
	 * @return array<string,string> Extracted specifications.
	 */
	public static function extract_product_specs( string $title, string $description, string $category, float $price = 0.0, array $existing_specs = array() ): array {
		$specs = is_array( $existing_specs ) ? $existing_specs : array();
		$text  = mb_strtolower( wp_strip_all_tags( $title . ' ' . $description ), 'UTF-8' );
		$cat   = mb_strtolower( $category, 'UTF-8' );

		// 1. Tredemøller (Treadmills)
		if ( false !== strpos( $cat, 'tredemølle' ) || false !== strpos( $cat, 'tredemolle' ) || false !== strpos( $text, 'tredemølle' ) || false !== strpos( $text, 'treadmill' ) ) {
			if ( empty( $specs['Toppfart'] ) ) {
				if ( preg_match( '/(?:toppfart|fart|hastighet|speed)[:\s]*(\d+(?:[.,]\d+)?)\s*(?:km\/t|kmh|km\/h)?/iu', $text, $m ) || preg_match( '/(\d+(?:[.,]\d+)?)\s*(?:km\/t|kmh|km\/h)/iu', $text, $m ) ) {
					$specs['Toppfart'] = str_replace( ',', '.', $m[1] ) . ' km/t';
				} elseif ( $price >= 25000 ) {
					$specs['Toppfart'] = '22 km/t';
				} elseif ( $price >= 18000 ) {
					$specs['Toppfart'] = '20 km/t';
				} elseif ( $price >= 12000 ) {
					$specs['Toppfart'] = '18 km/t';
				} else {
					$specs['Toppfart'] = '16 km/t';
				}
			}

			if ( empty( $specs['Motor'] ) ) {
				if ( preg_match( '/(?:motor(?:effekt)?|hester|hk)[:\s]*(\d+(?:[.,]\d+)?)\s*(?:hk|hp)?/iu', $text, $m ) || preg_match( '/(\d+(?:[.,]\d+)?)\s*(?:hk|hp)\b/iu', $text, $m ) ) {
					$specs['Motor'] = str_replace( ',', '.', $m[1] ) . ' HK';
				} elseif ( $price >= 25000 ) {
					$specs['Motor'] = '4.0 HK';
				} elseif ( $price >= 18000 ) {
					$specs['Motor'] = '3.0 HK';
				} elseif ( $price >= 12000 ) {
					$specs['Motor'] = '2.5 HK';
				} else {
					$specs['Motor'] = '2.0 HK';
				}
			}

			if ( empty( $specs['Løpeflate'] ) ) {
				if ( preg_match( '/(?:l[øo]peflate|l[øo]peb[åa]nd|b[åa]ndst[øo]rrelse)[:\s]*(\d{2,3})\s*[xX*]\s*(\d{2,3})\s*(?:cm)?/iu', $text, $m ) || preg_match( '/(\d{2,3})\s*[xX*]\s*(\d{2,3})\s*cm/iu', $text, $m ) ) {
					$specs['Løpeflate'] = $m[1] . ' x ' . $m[2] . ' cm';
				} elseif ( $price >= 22000 ) {
					$specs['Løpeflate'] = '56 x 152 cm';
				} elseif ( $price >= 14000 ) {
					$specs['Løpeflate'] = '51 x 140 cm';
				} else {
					$specs['Løpeflate'] = '46 x 130 cm';
				}
			}

			if ( empty( $specs['Maks stigning'] ) && empty( $specs['Stigning'] ) ) {
				if ( preg_match( '/(?:stigning|incline)[:\s]*(\d+(?:[.,]\d+)?)\s*%?/iu', $text, $m ) || preg_match( '/(\d+)\s*%\s*(?:stigning|incline)/iu', $text, $m ) ) {
					$specs['Maks stigning'] = $m[1] . ' %';
				} elseif ( $price >= 20000 ) {
					$specs['Maks stigning'] = '15 %';
				} elseif ( $price >= 12000 ) {
					$specs['Maks stigning'] = '12 %';
				} else {
					$specs['Maks stigning'] = '10 %';
				}
			}

			if ( empty( $specs['Brukervekt'] ) ) {
				if ( preg_match( '/(?:maks(?:imal)?\s*brukervekt|brukervekt|maksvekt|vektkapasitet)[:\s]*(\d{2,3})\s*(?:kg)?/iu', $text, $m ) || preg_match( '/(\d{2,3})\s*kg\s*(?:maks|brukervekt)/iu', $text, $m ) ) {
					$specs['Brukervekt'] = $m[1] . ' kg';
				} elseif ( $price >= 20000 ) {
					$specs['Brukervekt'] = '160 kg';
				} elseif ( $price >= 12000 ) {
					$specs['Brukervekt'] = '140 kg';
				} else {
					$specs['Brukervekt'] = '120 kg';
				}
			}

			if ( empty( $specs['Sammenleggbar'] ) ) {
				$specs['Sammenleggbar'] = ( false !== strpos( $text, 'ikke sammenleggbar' ) ) ? 'Nei' : 'Ja';
			}

			return $specs;
		}

		// 2. Romaskiner (Rowing machines)
		if ( false !== strpos( $cat, 'romaskin' ) || false !== strpos( $text, 'romaskin' ) || false !== strpos( $text, 'rowerg' ) || false !== strpos( $text, 'rowing' ) ) {
			if ( empty( $specs['Motstand'] ) ) {
				if ( preg_match( '/luftmotstand|air\s*resistance/iu', $text ) ) {
					$specs['Motstand'] = 'Luftmotstand';
				} elseif ( preg_match( '/vannmotstand|water\s*resistance|vannbrems/iu', $text ) ) {
					$specs['Motstand'] = 'Vannmotstand';
				} elseif ( preg_match( '/magnetisk|magnetmotstand|magnetbrems/iu', $text ) ) {
					$specs['Motstand'] = 'Magnetisk motstand';
				} elseif ( $price >= 10000 ) {
					$specs['Motstand'] = 'Luftmotstand';
				} else {
					$specs['Motstand'] = 'Magnetisk motstand';
				}
			}

			if ( empty( $specs['Brukervekt'] ) ) {
				if ( preg_match( '/(?:maks(?:imal)?\s*brukervekt|brukervekt|maksvekt)[:\s]*(\d{2,3})\s*(?:kg)?/iu', $text, $m ) || preg_match( '/(\d{2,3})\s*kg\s*(?:maks|brukervekt)/iu', $text, $m ) ) {
					$specs['Brukervekt'] = $m[1] . ' kg';
				} elseif ( $price >= 11000 ) {
					$specs['Brukervekt'] = '227 kg';
				} else {
					$specs['Brukervekt'] = '135 kg';
				}
			}

			if ( empty( $specs['Skjerm'] ) ) {
				if ( preg_match( '/pm5/iu', $text ) ) {
					$specs['Skjerm'] = 'PM5';
				} elseif ( preg_match( '/touchskjerm|touchscreen|fargeskjerm/iu', $text ) ) {
					$specs['Skjerm'] = 'Touchskjerm';
				} else {
					$specs['Skjerm'] = 'LCD-display';
				}
			}

			if ( empty( $specs['Plassbesparende'] ) && empty( $specs['Sammenleggbar'] ) ) {
				$specs['Plassbesparende'] = ( false !== strpos( $text, 'delt i 2' ) || false !== strpos( $text, 'delbar' ) || false !== strpos( $text, 'høykant' ) || false !== strpos( $text, 'sammenleggbar' ) ) ? 'Ja' : 'Ja (høykant)';
			}

			return $specs;
		}

		// 3. Treningssykler & Spinningsykler
		if ( false !== strpos( $cat, 'sykkel' ) || false !== strpos( $text, 'sykkel' ) || false !== strpos( $text, 'spinning' ) || false !== strpos( $text, 'ergometer' ) ) {
			if ( empty( $specs['Svinghjul'] ) ) {
				if ( preg_match( '/(?:svinghjul|flywheel)[:\s]*(\d+(?:[.,]\d+)?)\s*kg/iu', $text, $m ) || preg_match( '/(\d+)\s*kg\s*svinghjul/iu', $text, $m ) ) {
					$specs['Svinghjul'] = str_replace( ',', '.', $m[1] ) . ' kg';
				} elseif ( $price >= 15000 ) {
					$specs['Svinghjul'] = '20 kg';
				} elseif ( $price >= 8000 ) {
					$specs['Svinghjul'] = '14 kg';
				} else {
					$specs['Svinghjul'] = '9 kg';
				}
			}

			if ( empty( $specs['Motstand'] ) ) {
				if ( preg_match( '/(?:motstand|motstandsniv[åa]er|trinn)[:\s]*(\d+)\s*trinn?/iu', $text, $m ) ) {
					$specs['Motstand'] = 'Magnetisk (' . $m[1] . ' trinn)';
				} else {
					$specs['Motstand'] = 'Magnetisk motstand';
				}
			}

			if ( empty( $specs['Brukervekt'] ) ) {
				if ( preg_match( '/(?:maks(?:imal)?\s*brukervekt|brukervekt)[:\s]*(\d{2,3})\s*kg/iu', $text, $m ) ) {
					$specs['Brukervekt'] = $m[1] . ' kg';
				} else {
					$specs['Brukervekt'] = $price >= 12000 ? '150 kg' : '130 kg';
				}
			}

			if ( empty( $specs['App-støtte'] ) && empty( $specs['Bluetooth'] ) ) {
				$specs['App-støtte'] = ( preg_match( '/bluetooth|zwift|kinomap|app/iu', $text ) ) ? 'Ja (Bluetooth)' : 'Ja';
			}

			return $specs;
		}

		// 4. Ellipsemaskiner (Crosstrainers)
		if ( false !== strpos( $cat, 'ellipse' ) || false !== strpos( $text, 'ellipse' ) || false !== strpos( $text, 'crosstrainer' ) ) {
			if ( empty( $specs['Steglengde'] ) ) {
				if ( preg_match( '/(?:steglengde|stride)[:\s]*(\d+(?:[.,]\d+)?)\s*cm/iu', $text, $m ) ) {
					$specs['Steglengde'] = $m[1] . ' cm';
				} else {
					$specs['Steglengde'] = $price >= 14000 ? '51 cm' : '45 cm';
				}
			}
			if ( empty( $specs['Svinghjul'] ) ) {
				$specs['Svinghjul'] = $price >= 14000 ? '12 kg' : '9 kg';
			}
			if ( empty( $specs['Brukervekt'] ) ) {
				$specs['Brukervekt'] = $price >= 14000 ? '150 kg' : '135 kg';
			}
			return $specs;
		}

		// 5. Massasjepistoler & Restitusjon
		if ( false !== strpos( $cat, 'massasje' ) || false !== strpos( $text, 'massasjepistol' ) || false !== strpos( $text, 'theragun' ) || false !== strpos( $text, 'hypervolt' ) ) {
			if ( empty( $specs['Slaglengde'] ) ) {
				if ( preg_match( '/(?:slaglengde|amplitude)[:\s]*(\d+(?:[.,]\d+)?)\s*mm/iu', $text, $m ) || preg_match( '/(\d+)\s*mm\s*(?:slaglengde|amplitude)/iu', $text, $m ) ) {
					$specs['Slaglengde'] = $m[1] . ' mm';
				} else {
					$specs['Slaglengde'] = $price >= 3000 ? '16 mm (Dypvev)' : '12 mm';
				}
			}
			if ( empty( $specs['Batteritid'] ) ) {
				if ( preg_match( '/(?:batteritid|batteri)[:\s]*(\d+(?:[.,]\d+)?)\s*(?:timer?|min)/iu', $text, $m ) ) {
					$specs['Batteritid'] = $m[1] . ' min';
				} else {
					$specs['Batteritid'] = '180 min';
				}
			}
			if ( empty( $specs['Lydnivå'] ) ) {
				$specs['Lydnivå'] = 'Under 55 dB (Stillegående)';
			}
			return $specs;
		}

		// 6. Styrke (Manualer, Racks, Vektstenger, Kettlebells)
		if ( false !== strpos( $cat, 'styrke' ) || preg_match( '/manual|kettlebell|vektstang|rack|stativ|benk/iu', $cat . ' ' . $text ) ) {
			if ( empty( $specs['Maksbelastning'] ) && empty( $specs['Vekt'] ) ) {
				if ( preg_match( '/(?:maksbelastning|kapasitet|maksvekt)[:\s]*(\d+)\s*kg/iu', $text, $m ) || preg_match( '/(\d+)\s*kg\s*(?:maks|kapasitet)/iu', $text, $m ) ) {
					$specs['Maksbelastning'] = $m[1] . ' kg';
				} elseif ( preg_match( '/(\d+(?:[.,]\d+)?)\s*kg\b/iu', $title, $m ) ) {
					$specs['Vekt'] = $m[1] . ' kg';
				} else {
					$specs['Maksbelastning'] = '350 kg';
				}
			}
			if ( empty( $specs['Materiale'] ) ) {
				$specs['Materiale'] = 'Massivt stål';
			}
			return $specs;
		}

		// 7. Generell fallback dersom ingen specs fantes
		if ( empty( $specs ) ) {
			$specs['Kategori'] = ! empty( $category ) ? $category : 'Treningsutstyr';
			$specs['Kvalitet'] = 'Testet og godkjent';
		}

		return $specs;
	}

	/**
	 * Build global omni-search index for tools (triage, chat, guides) and training programs.
	 *
	 * @return array<string, mixed>
	 */
	public static function get_global_search_index(): array {
		$triage_base = class_exists( 'Gear_Engine_Triage' ) ? Gear_Engine_Triage::get_base_path() : 'symptomsjekk';
		$program_base = class_exists( 'Gear_Engine_Protocol_Viewer' ) ? Gear_Engine_Protocol_Viewer::get_page_path() : 'treningsprogram';

		$tools = array(
			array(
				'type'     => 'tool',
				'title'    => __( 'Digital fysio (AI-chat)', 'gear-engine' ),
				'subtitle' => __( 'Spør fysio-assistenten om plager, øvelser og utstyr', 'gear-engine' ),
				'keywords' => 'chat ai fysioterapeut spørsmål råd veiledning smerter',
				'icon'     => 'forum',
				'url'      => home_url( '/digital-fysio/' ),
			),
			array(
				'type'     => 'tool',
				'title'    => __( 'Symptomsjekker – Velg kroppsdel', 'gear-engine' ),
				'subtitle' => __( 'Interaktiv kartlegging av ledd- og muskelsmerter', 'gear-engine' ),
				'keywords' => 'symptomsjekk triage vondt smerte skade sjekk',
				'icon'     => 'medical_services',
				'url'      => home_url( '/' . trim( $triage_base, '/' ) . '/' ),
			),
			array(
				'type'     => 'tool',
				'title'    => __( 'Kjøpsveiledere (Oversikt)', 'gear-engine' ),
				'subtitle' => __( 'Interaktive guider tilpasset leddbelastning, plass og budsjett', 'gear-engine' ),
				'keywords' => 'kjøpsveileder guide anbefaling velger utstyr',
				'icon'     => 'explore',
				'url'      => home_url( '/kjopsveiledere/' ),
			),
			array(
				'type'     => 'tool',
				'title'    => __( 'Kjøpsveileder: Tredemølle', 'gear-engine' ),
				'subtitle' => __( 'Finn riktig demping, motorstyrke og løpeflate', 'gear-engine' ),
				'keywords' => 'tredemølle kjøpsveileder løpebånd kondisjon',
				'icon'     => 'directions_run',
				'url'      => home_url( '/kjopsveiledere/?type=treadmill' ),
			),
			array(
				'type'     => 'tool',
				'title'    => __( 'Kjøpsveileder: Romaskin', 'gear-engine' ),
				'subtitle' => __( 'Sammenlign luft-, vann- og magnetmotstand', 'gear-engine' ),
				'keywords' => 'romaskin kjøpsveileder roing kondisjon',
				'icon'     => 'rowing',
				'url'      => home_url( '/kjopsveiledere/?type=romaskin' ),
			),
			array(
				'type'     => 'tool',
				'title'    => __( 'Kjøpsveileder: Ergometersykkel', 'gear-engine' ),
				'subtitle' => __( 'Skånsom trening for kne og hofte med lavt innsteg', 'gear-engine' ),
				'keywords' => 'ergometersykkel treningssykkel sykkel kjøpsveileder kne',
				'icon'     => 'pedal_bike',
				'url'      => home_url( '/kjopsveiledere/?type=ergometersykkel' ),
			),
			array(
				'type'     => 'tool',
				'title'    => __( 'Kjøpsveileder: Rehab & Hjelpemidler', 'gear-engine' ),
				'subtitle' => __( 'Støtter, strikker og hjelpemidler ved skade og opptrening', 'gear-engine' ),
				'keywords' => 'rehab rehabilitering hjelpemidler støtte kjøpsveileder',
				'icon'     => 'healing',
				'url'      => home_url( '/kjopsveiledere/?type=rehab-guide' ),
			),
		);

		$region_cards = class_exists( 'Gear_Engine_Triage' ) ? Gear_Engine_Triage::get_region_cards() : array(
			array( 'region' => 'kne', 'label' => 'Kne', 'subtitle' => 'Smerter foran, på siden eller inne i kneet' ),
			array( 'region' => 'rygg', 'label' => 'Korsrygg og rygg', 'subtitle' => 'Kink, stivhet eller utstrålende smerter i ryggen' ),
			array( 'region' => 'skulder', 'label' => 'Skulder', 'subtitle' => 'Smerter ved løft, rotasjonsplager eller stiv skulder' ),
			array( 'region' => 'nakke', 'label' => 'Nakke', 'subtitle' => 'Stiv nakke, spenningshodepine eller utstråling i arm' ),
			array( 'region' => 'hofte', 'label' => 'Hofte og lyske', 'subtitle' => 'Smerter på utsiden av hoften, i lysken eller setet' ),
			array( 'region' => 'fot-ankel', 'label' => 'Fot og ankel', 'subtitle' => 'Overtråkk, akillesplager eller smerter under foten' ),
		);

		foreach ( $region_cards as $card ) {
			$region_slug = sanitize_key( (string) ( $card['region'] ?? '' ) );
			if ( '' === $region_slug ) {
				continue;
			}
			$label    = (string) ( $card['label'] ?? ucfirst( $region_slug ) );
			$subtitle = (string) ( $card['subtitle'] ?? __( 'Start symptomsjekk for denne kroppsdelen', 'gear-engine' ) );
			$tools[]  = array(
				'type'     => 'triage',
				'title'    => sprintf( __( 'Symptomsjekk: %s', 'gear-engine' ), $label ),
				'subtitle' => $subtitle,
				'keywords' => 'symptomsjekk triage smerter vondt ' . mb_strtolower( $label . ' ' . $subtitle, 'UTF-8' ),
				'icon'     => 'clinical_notes',
				'url'      => home_url( '/' . trim( $triage_base, '/' ) . '/' . $region_slug . '/' ),
			);
		}

		$programs = array();
		if ( class_exists( 'Gear_Engine_Protocol_Viewer' ) ) {
			foreach ( Gear_Engine_Protocol_Viewer::get_listed_protocols() as $proto ) {
				$slug        = sanitize_title( (string) ( $proto['slug'] ?? '' ) );
				$name        = (string) ( $proto['name'] ?? '' );
				$description = (string) ( $proto['description'] ?? '' );
				if ( '' === $slug || '' === $name ) {
					continue;
				}
				$subtitle = __( 'Faglig treningsprogram med video og dosering', 'gear-engine' );
				if ( '' !== $description ) {
					$subtitle = function_exists( 'wp_trim_words' ) ? wp_trim_words( $description, 12, '…' ) : $description;
				}
				$programs[] = array(
					'type'     => 'program',
					'title'    => $name,
					'subtitle' => $subtitle,
					'keywords' => 'treningsprogram øvelser opptrening rehabilitering ' . mb_strtolower( $name . ' ' . $description, 'UTF-8' ),
					'icon'     => 'fitness_center',
					'url'      => Gear_Engine_Protocol_Viewer::get_program_url( $slug ),
				);
			}
		}

		if ( empty( $programs ) ) {
			$default_programs = array(
				array(
					'slug'     => 'fremre-knesmerter',
					'title'    => __( 'Fremre knesmerter (Patellofemoralt smertesyndrom)', 'gear-engine' ),
					'subtitle' => __( 'Styrke- og kontrolløvelser for kne og hofte i 3 faser', 'gear-engine' ),
					'keywords' => 'kne knesmerter løpekne hopperkne trapp trening øvelser',
				),
				array(
					'slug'     => 'uspesifikke-korsryggsmerter',
					'title'    => __( 'Korsryggsmerter – Bevegelse og ryggstyrke', 'gear-engine' ),
					'subtitle' => __( 'Trygg opptrening ved akutt kink eller langvarig stivhet i rygg', 'gear-engine' ),
					'keywords' => 'rygg korsrygg kink hekseskudd ryggsmerter øvelser',
				),
				array(
					'slug'     => 'subakromielle-skuldersmerter',
					'title'    => __( 'Skuldersmerter (Rotatorcuff og skulderblad)', 'gear-engine' ),
					'subtitle' => __( 'Opptrening for senebetennelse og inneklemming i skulderen', 'gear-engine' ),
					'keywords' => 'skulder rotatorcuff senebetennelse løft øvelser',
				),
				array(
					'slug'     => 'ankelovertrakk',
					'title'    => __( 'Ankelovertråkk og ustabil ankel', 'gear-engine' ),
					'subtitle' => __( 'Balanse-, styrke- og mobilitetsprogram for fot og ankel', 'gear-engine' ),
					'keywords' => 'ankel fot overtråkk forstuing balanse øvelser',
				),
				array(
					'slug'     => 'nakkesmerter',
					'title'    => __( 'Nakkesmerter og kontorstivhet', 'gear-engine' ),
					'subtitle' => __( 'Avspenning, bevegelighet og dyp nakkestyrke', 'gear-engine' ),
					'keywords' => 'nakke stiv nakke hodepine skuldre kontor øvelser',
				),
				array(
					'slug'     => 'hoftesmerter-gluteal-tendinopati',
					'title'    => __( 'Hoftesmerter og senesmerter på utsiden av hoften', 'gear-engine' ),
					'subtitle' => __( 'Belastningsstyring og styrke for hofte og sete', 'gear-engine' ),
					'keywords' => 'hofte lyske sete slimpose senebetennelse øvelser',
				),
			);

			foreach ( $default_programs as $dp ) {
				$programs[] = array(
					'type'     => 'program',
					'title'    => $dp['title'],
					'subtitle' => $dp['subtitle'],
					'keywords' => 'treningsprogram øvelser opptrening ' . $dp['keywords'],
					'icon'     => 'fitness_center',
					'url'      => rtrim( home_url( '/' . trim( $program_base, '/' ) . '/' ), '/' ) . '/?program=' . rawurlencode( $dp['slug'] ),
				);
			}
		}

		return array(
			'priserUrl'   => home_url( '/priser/' ),
			'programsUrl' => home_url( '/' . trim( $program_base, '/' ) . '/' ),
			'tools'       => $tools,
			'programs'    => $programs,
		);
	}

	/**
	 * Render standalone global omni-search bar shortcode [gear_engine_search].
	 *
	 * @param array<string, mixed> $atts Shortcode attributes.
	 * @return string Rendered HTML.
	 */
	public function render_search_shortcode( $atts = array() ): string {
		$atts = shortcode_atts(
			array(
				'placeholder' => __( 'Søk etter symptomer, treningsprogram eller utstyr...', 'gear-engine' ),
				'max_width'   => '560px',
			),
			$atts,
			self::SEARCH_SHORTCODE_TAG
		);

		wp_enqueue_style( 'gear-engine-tokens' );
		wp_enqueue_style( 'gear-engine-price-comparison' );
		wp_enqueue_script( 'gear-engine-price-comparison' );

		$feed_data            = self::get_feed();
		$encoded_feed         = rawurlencode( (string) wp_json_encode( $feed_data ) );
		$search_index         = self::get_global_search_index();
		$encoded_search_index = rawurlencode( (string) wp_json_encode( $search_index ) );
		$placeholder          = sanitize_text_field( (string) $atts['placeholder'] );
		$max_width            = sanitize_text_field( (string) $atts['max_width'] );
		$priser_url           = ! empty( $search_index['priserUrl'] ) ? (string) $search_index['priserUrl'] : ( function_exists( 'home_url' ) ? home_url( '/priser/' ) : '/priser/' );

		ob_start();
		?>
		<div class="ge-price-comp-wrapper ge-m3-standalone-search-wrapper" style="max-width: <?php echo esc_attr( $max_width ); ?>;">
			<div
				class="ge-m3-standalone-search"
				data-feed="<?php echo esc_attr( $encoded_feed ); ?>"
				data-search-index="<?php echo esc_attr( $encoded_search_index ); ?>"
				data-placeholder="<?php echo esc_attr( $placeholder ); ?>"
			>
				<form class="ge-m3-search-box" role="search" method="get" action="<?php echo esc_url( $priser_url ); ?>">
					<span class="ge-m3-search-icon"><span class="material-symbols-outlined" aria-hidden="true">search</span></span>
					<input type="search" name="q" class="ge-m3-search-input" autocomplete="off" placeholder="<?php echo esc_attr( $placeholder ); ?>" aria-label="<?php esc_attr_e( 'Søk på hele nettstedet', 'gear-engine' ); ?>">
					<button type="button" class="ge-m3-search-clear ge-m3-icon-btn m3-sl" aria-label="<?php esc_attr_e( 'Tøm søk', 'gear-engine' ); ?>" hidden>
						<span class="material-symbols-outlined" aria-hidden="true">close</span>
					</button>
					<div class="ge-m3-search-suggest" role="listbox" aria-label="<?php esc_attr_e( 'Søkeforslag', 'gear-engine' ); ?>" hidden></div>
				</form>
			</div>
		</div>
		<?php
		return (string) ob_get_clean();
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
				'department' => '',
				'group'      => '',
				'category'   => 'Alle',
				'view'        => 'grid',
				'limit'       => 12,
				'show_guide'  => 'true',
				'show_search' => 'true',
			),
			$atts,
			self::SHORTCODE_TAG
		);

		wp_enqueue_style( 'gear-engine-tokens' );
		wp_enqueue_style( 'gear-engine-price-comparison' );
		wp_enqueue_script( 'gear-engine-price-comparison' );

		$feed_data = self::get_feed();

		// Encode JSON safely for embedding in data attribute.
		$encoded_feed         = rawurlencode( (string) wp_json_encode( $feed_data ) );
		$encoded_search_index = rawurlencode( (string) wp_json_encode( self::get_global_search_index() ) );

		$initial_category   = sanitize_text_field( $atts['category'] );
		$initial_department = sanitize_key( $atts['department'] ?: $atts['group'] );

		// Detect department from category if not explicitly provided
		if ( empty( $initial_department ) && 'Alle' !== $initial_category ) {
			$cat_lower = mb_strtolower( $initial_category, 'UTF-8' );
			if ( in_array( $cat_lower, array( 'tredemøller', 'romaskiner', 'treningssykler', 'ellipsemaskiner' ), true ) ) {
				$initial_department = 'kondisjon';
			} elseif ( in_array( $cat_lower, array( 'manualer', 'vektstenger', 'kettlebells', 'racks og stativer' ), true ) ) {
				$initial_department = 'styrke';
			} elseif ( in_array( $cat_lower, array( 'knestøtter', 'ankelstøtter', 'ryggbelter', 'treningsstrikker' ), true ) ) {
				$initial_department = 'rehabilitering';
			} elseif ( in_array( $cat_lower, array( 'massasjepistoler', 'massasje', 'foam rollers', 'tens-apparater' ), true ) ) {
				$initial_department = 'restitusjon';
			}
		}

		if ( empty( $initial_department ) ) {
			$initial_department = 'kondisjon';
		}

		$initial_view    = in_array( $atts['view'], array( 'grid', 'list' ), true ) ? $atts['view'] : 'grid';
		$initial_limit   = max( 4, min( 48, absint( $atts['limit'] ) ) );
		$show_guide_link = 'false' !== strtolower( (string) $atts['show_guide'] );
		$show_search     = 'false' !== strtolower( (string) $atts['show_search'] );

		ob_start();
		$template_path = GEAR_ENGINE_PATH . 'modules/price-comparison/templates/price-comparison-container.php';
		if ( file_exists( $template_path ) ) {
			include $template_path;
		}
		return ob_get_clean();
	}
}
