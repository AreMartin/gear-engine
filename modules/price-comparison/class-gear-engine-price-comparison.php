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
				'image'         => 'https://images.unsplash.com/photo-1534438327276-14e5300c3a48?auto=format&fit=crop&w=400&q=80',
				'url'           => '#',
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
				'otherShops'    => 0,
				'inStock'       => true,
				'image'         => 'https://images.unsplash.com/photo-1576678927484-cc907957088c?auto=format&fit=crop&w=400&q=80',
				'url'           => '#',
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
				'image'         => 'https://images.unsplash.com/photo-1584735935682-2f2b69dff9d2?auto=format&fit=crop&w=400&q=80',
				'url'           => '#',
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
				'image'         => 'https://images.unsplash.com/photo-1518611012118-696072aa579a?auto=format&fit=crop&w=400&q=80',
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
				'image'         => 'https://images.unsplash.com/photo-1571019614242-c5c5dee9f50b?auto=format&fit=crop&w=400&q=80',
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
				'image'         => 'https://images.unsplash.com/photo-1544367567-0f2fcb009e0b?auto=format&fit=crop&w=400&q=80',
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
				'image'         => 'https://images.unsplash.com/photo-1517502884422-41eaead166d4?auto=format&fit=crop&w=400&q=80',
				'url'           => '#',
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
				'image'         => 'https://images.unsplash.com/photo-1576678927484-cc907957088c?auto=format&fit=crop&w=400&q=80',
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
				'image'         => 'https://images.unsplash.com/photo-1598289431512-b97b0917affc?auto=format&fit=crop&w=400&q=80',
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
				'image'         => 'https://images.unsplash.com/photo-1518611012118-696072aa579a?auto=format&fit=crop&w=400&q=80',
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

			// Resolve specs if set in meta.
			$raw_specs = get_post_meta( $post_id, '_gear_engine_specs', true );
			$specs     = is_array( $raw_specs ) ? $raw_specs : array();

			// Merge individual CPT spec overrides if not already set in _gear_engine_specs.
			if ( empty( $specs['Toppfart'] ) ) {
				$val = get_post_meta( $post_id, Gear_Engine_CPT::META_SPEC_PREFIX . 'speed', true );
				if ( '' !== $val && false !== $val && null !== $val ) {
					$specs['Toppfart'] = $val . ' km/t';
				}
			}
			if ( empty( $specs['Brukervekt'] ) ) {
				$val = get_post_meta( $post_id, Gear_Engine_CPT::META_SPEC_PREFIX . 'weight', true );
				if ( '' !== $val && false !== $val && null !== $val ) {
					$specs['Brukervekt'] = $val . ' kg';
				}
			}
			if ( empty( $specs['Stigning'] ) ) {
				$val = get_post_meta( $post_id, Gear_Engine_CPT::META_SPEC_PREFIX . 'incline', true );
				if ( '' !== $val && false !== $val && null !== $val ) {
					$specs['Stigning'] = $val . ' %';
				}
			}
			if ( empty( $specs['Motstand'] ) ) {
				$val = get_post_meta( $post_id, Gear_Engine_CPT::META_SPEC_PREFIX . 'levels', true );
				if ( '' !== $val && false !== $val && null !== $val ) {
					$specs['Motstand'] = $val . ' trinn';
				}
			}
			if ( empty( $specs['Sammenleggbar'] ) ) {
				$val = get_post_meta( $post_id, Gear_Engine_CPT::META_SPEC_PREFIX . 'foldable', true );
				if ( '1' === (string) $val ) {
					$specs['Sammenleggbar'] = 'Ja';
				} elseif ( '0' === (string) $val ) {
					$specs['Sammenleggbar'] = 'Nei';
				}
			}
			if ( empty( $specs['Rehabilitering'] ) ) {
				$val = get_post_meta( $post_id, Gear_Engine_CPT::META_SPEC_PREFIX . 'rehab', true );
				if ( '1' === (string) $val ) {
					$specs['Rehabilitering'] = 'Ja';
				}
			}

			// Resolve department.
			$department = (string) get_post_meta( $post_id, '_gear_engine_department', true );
			if ( empty( $department ) ) {
				$department = self::infer_department( $category, $post->post_title );
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

		ob_start();
		$template_path = GEAR_ENGINE_PATH . 'modules/price-comparison/templates/price-comparison-container.php';
		if ( file_exists( $template_path ) ) {
			include $template_path;
		}
		return ob_get_clean();
	}
}

