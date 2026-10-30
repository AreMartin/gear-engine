<?php
/**
 * Gear picker module: a guided quiz that recommends exercise gear from the imported feed products.
 *
 * Each product type is described by a JSON config in modules/gear-picker/configs/{type}.json:
 * which `exercise_gear` posts qualify, how specs are read from their text, and the quiz steps with
 * declarative scoring rules. Products come from the CPT filled by the affiliate feed sync.
 *
 * @package GearEngine
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

/**
 * Class Gear_Engine_Gear_Picker
 */
class Gear_Engine_Gear_Picker {

	/**
	 * Shortcode tag.
	 */
	public const SHORTCODE_TAG = 'gear_engine_gear_picker';

	/**
	 * Option holding the cache version; bumping it invalidates every cached product list.
	 */
	public const OPTION_CACHE_VERSION = 'gear_engine_picker_cache_version';

	/**
	 * Decoded configs cache, keyed by type.
	 *
	 * @var array<string, array|WP_Error>
	 */
	private static $config_cache = array();

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
	 * Register shared assets.
	 *
	 * @return void
	 */
	public function register_assets(): void {
		$base = GEAR_ENGINE_URL . 'modules/gear-picker/assets/';

		wp_register_style( 'gear-engine-gear-picker', $base . 'css/gear-picker.css', array( 'gear-engine-tokens' ), GEAR_ENGINE_VERSION );
		wp_register_script( 'gear-engine-gear-picker-scoring', $base . 'js/gear-picker-scoring.js', array(), GEAR_ENGINE_VERSION, true );
		wp_register_script( 'gear-engine-gear-picker', $base . 'js/gear-picker.js', array( 'gear-engine-gear-picker-scoring' ), GEAR_ENGINE_VERSION, true );
	}

	/**
	 * Register the [gear_engine_gear_picker] shortcode.
	 *
	 * @return void
	 */
	public function register_shortcode(): void {
		add_shortcode( self::SHORTCODE_TAG, array( $this, 'render' ) );
	}

	/**
	 * Invalidate cached product lists.
	 *
	 * @return void
	 */
	public static function flush_cache(): void {
		update_option( self::OPTION_CACHE_VERSION, time(), false );
	}

	/**
	 * Available picker types (config file names).
	 *
	 * @return string[]
	 */
	public static function get_types(): array {
		$types = array();
		foreach ( (array) glob( GEAR_ENGINE_PATH . 'modules/gear-picker/configs/*.json' ) as $file ) {
			$types[] = basename( $file, '.json' );
		}
		sort( $types );
		return $types;
	}

	/**
	 * Load and validate a type config.
	 *
	 * @param string $type Type slug.
	 * @return array<string, mixed>|WP_Error
	 */
	public static function get_config( string $type ) {
		$type = sanitize_key( $type );
		if ( isset( self::$config_cache[ $type ] ) ) {
			return self::$config_cache[ $type ];
		}

		$config = Gear_Engine_Config_Loader::load(
			GEAR_ENGINE_PATH . 'modules/gear-picker/configs/',
			$type,
			static function ( $c ) {
				return ! empty( $c['steps'] ) && ! empty( $c['match'] ) && isset( $c['specs'] );
			}
		);

		if ( null === $config ) {
			$file = GEAR_ENGINE_PATH . 'modules/gear-picker/configs/' . $type . '.json';
			if ( '' === $type || ! is_readable( $file ) ) {
				return new WP_Error( 'gear_engine_picker_unknown', sprintf( 'Unknown gear picker type "%s".', $type ) );
			}
			return new WP_Error( 'gear_engine_picker_invalid', sprintf( 'Invalid gear picker config "%s".', $type ) );
		}

		/**
		 * Filters a gear picker config.
		 *
		 * @param array  $config Decoded config.
		 * @param string $type   Type slug.
		 */
		$config                       = (array) apply_filters( 'gear_engine_gear_picker_config', $config, $type );
		self::$config_cache[ $type ] = $config;
		return $config;
	}

	/**
	 * Lowercase a string, multibyte aware.
	 *
	 * @param string $text Text.
	 * @return string
	 */
	private static function lower( string $text ): string {
		return function_exists( 'mb_strtolower' ) ? mb_strtolower( $text, 'UTF-8' ) : strtolower( $text );
	}

	/**
	 * Whether the lowercase haystack contains any of the keywords.
	 *
	 * @param string   $haystack Lowercase text.
	 * @param string[] $keywords Keywords.
	 * @return bool
	 */
	private static function contains_any( string $haystack, array $keywords ): bool {
		foreach ( $keywords as $keyword ) {
			if ( '' !== $keyword && false !== strpos( $haystack, self::lower( (string) $keyword ) ) ) {
				return true;
			}
		}
		return false;
	}

	/**
	 * Whether any regex matches.
	 *
	 * @param string   $text     Text.
	 * @param string[] $patterns Patterns with delimiters.
	 * @return bool
	 */
	private static function matches_any_pattern( string $text, array $patterns ): bool {
		foreach ( $patterns as $pattern ) {
			if ( 1 === @preg_match( (string) $pattern, $text ) ) { // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged -- Config patterns may be malformed.
				return true;
			}
		}
		return false;
	}

	/**
	 * Whether a product (title and category names) belongs to the picker type.
	 *
	 * "exclude" words are checked in title and categories, "exclude_title" words and patterns in the title only.
	 *
	 * @param string               $title     Product title.
	 * @param string[]             $terms     Category names.
	 * @param array<string, mixed> $match     Config "match" block.
	 * @param bool                 $has_scope Whether a category scope already restricted the query.
	 * @return bool
	 */
	public static function is_match( string $title, array $terms, array $match, bool $has_scope = false ): bool {
		return '' === self::match_reason( $title, $terms, $match, $has_scope );
	}

	/**
	 * Why a product does not belong to the picker type.
	 *
	 * @param string               $title     Product title.
	 * @param string[]             $terms     Category names.
	 * @param array<string, mixed> $match     Config "match" block.
	 * @param bool                 $has_scope Whether a category scope already restricted the query.
	 * @return string "" when it matches, "other_type" when it lacks every include word, "excluded" for accessories and spare parts.
	 */
	public static function match_reason( string $title, array $terms, array $match, bool $has_scope = false ): string {
		$title_lower = self::lower( $title );
		$haystack    = self::lower( $title . ' ' . implode( ' ', $terms ) );

		if ( ! $has_scope && ! self::contains_any( $haystack, (array) ( $match['include'] ?? array() ) ) ) {
			return 'other_type';
		}
		if ( self::contains_any( $haystack, (array) ( $match['exclude'] ?? array() ) )
			|| self::contains_any( $title_lower, (array) ( $match['exclude_title'] ?? array() ) )
			|| self::matches_any_pattern( $title_lower, (array) ( $match['exclude_patterns'] ?? array() ) ) ) {
			return 'excluded';
		}
		return '';
	}

	/**
	 * Read specs from free text using the config "specs" block.
	 *
	 * Numbers that cannot be read are null and flags false, unless the spec has an "estimate" list
	 * (price thresholds, first match wins) that supplies a value. Estimated keys are returned in "est"
	 * so the UI can avoid presenting them as facts.
	 *
	 * @param string               $text  Product text.
	 * @param array<string, mixed> $specs Config "specs" block.
	 * @param float                $price Product price, used for estimates.
	 * @return array{specs: array<string, float|int|bool|null>, est: string[]}
	 */
	public static function extract_specs( string $text, array $specs, float $price = 0.0 ): array {
		$lower  = self::lower( wp_strip_all_tags( $text ) );
		$result = array();
		$est    = array();

		foreach ( $specs as $key => $def ) {
			$def     = (array) $def;
			$is_flag = 'flag' === ( $def['type'] ?? '' );

			if ( $is_flag ) {
				$value = self::contains_any( $lower, (array) ( $def['keywords'] ?? array() ) )
					|| self::matches_any_pattern( $lower, (array) ( $def['patterns'] ?? array() ) );
			} else {
				$value = null;
				foreach ( (array) ( $def['patterns'] ?? array() ) as $pattern ) {
					if ( 1 !== @preg_match( (string) $pattern, $lower, $m ) ) { // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged -- Config patterns may be malformed.
						continue;
					}
					$number = (float) str_replace( ',', '.', $m[1] );
					if ( $number >= (float) ( $def['min'] ?? 0 ) && $number <= (float) ( $def['max'] ?? PHP_INT_MAX ) ) {
						$value = $number;
						break;
					}
				}
			}

			if ( ! $value ) {
				foreach ( (array) ( $def['estimate'] ?? array() ) as $rule ) {
					$rule = (array) $rule;
					if ( ( isset( $rule['gt'] ) && $price <= (float) $rule['gt'] ) || ( isset( $rule['gte'] ) && $price < (float) $rule['gte'] ) ) {
						continue;
					}
					$value = $rule['value'];
					$est[] = (string) $key;
					break;
				}
			}

			$result[ $key ] = $value;
		}

		return array(
			'specs' => $result,
			'est'   => $est,
		);
	}

	/**
	 * Build the client payload for one product, or null when it does not qualify.
	 *
	 * @param WP_Post              $post      Gear post.
	 * @param array<string, mixed> $config    Picker config.
	 * @param bool                 $has_scope Whether a category scope restricted the query.
	 * @return array<string, mixed>|null
	 */
	public static function build_item( WP_Post $post, array $config, bool $has_scope = false ): ?array {
		return self::evaluate_post( $post, $config, $has_scope )['item'];
	}

	/**
	 * Decide whether a product qualifies and, if not, why.
	 *
	 * Reasons: other_type, excluded, out_of_stock, no_price, below_min_price, no_link.
	 *
	 * @param WP_Post              $post      Gear post.
	 * @param array<string, mixed> $config    Picker config.
	 * @param bool                 $has_scope Whether a category scope restricted the query.
	 * @return array{item: array<string, mixed>|null, reason: string}
	 */
	public static function evaluate_post( WP_Post $post, array $config, bool $has_scope = false ): array {
		$terms = wp_get_post_terms( $post->ID, Gear_Engine_CPT::TAXONOMY, array( 'fields' => 'names' ) );
		$terms = is_wp_error( $terms ) ? array() : $terms;
		$title = get_the_title( $post );

		$reason = self::match_reason( $title, $terms, (array) $config['match'], $has_scope );
		if ( '' !== $reason ) {
			return array( 'item' => null, 'reason' => $reason );
		}

		$in_stock = get_post_meta( $post->ID, Gear_Engine_CPT::META_IN_STOCK, true );
		$price    = (float) get_post_meta( $post->ID, Gear_Engine_CPT::META_PRICE, true );
		$url      = (string) get_post_meta( $post->ID, Gear_Engine_CPT::META_AFFILIATE_URL, true );

		if ( '0' === (string) $in_stock ) {
			return array( 'item' => null, 'reason' => 'out_of_stock' );
		}
		if ( $price <= 0 ) {
			return array( 'item' => null, 'reason' => 'no_price' );
		}
		if ( $price < (float) ( $config['match']['min_price'] ?? 0 ) ) {
			return array( 'item' => null, 'reason' => 'below_min_price' );
		}
		if ( '' === $url ) {
			return array( 'item' => null, 'reason' => 'no_link' );
		}

		$image = (string) get_post_meta( $post->ID, Gear_Engine_CPT::META_IMAGE_URL, true );
		if ( has_post_thumbnail( $post->ID ) ) {
			$thumb = wp_get_attachment_image_src( get_post_thumbnail_id( $post->ID ), 'large' );
			$image = ! empty( $thumb[0] ) ? $thumb[0] : $image;
		}

		$old  = (float) get_post_meta( $post->ID, Gear_Engine_CPT::META_REGULAR_PRICE, true );
		$read = self::extract_specs( $title . ' ' . $post->post_excerpt . ' ' . $post->post_content, (array) $config['specs'], $price );

		// Apply manual post meta spec overrides.
		if ( ! empty( $config['specs'] ) && is_array( $config['specs'] ) ) {
			foreach ( $config['specs'] as $spec_key => $spec_def ) {
				$override = get_post_meta( $post->ID, '_gear_engine_spec_' . $spec_key, true );
				if ( '' !== $override && false !== $override && null !== $override ) {
					$spec_type = is_array( $spec_def ) ? ( $spec_def['type'] ?? 'number' ) : 'number';
					if ( 'flag' === $spec_type ) {
						$read['specs'][ $spec_key ] = in_array( (string) $override, array( '1', 'yes', 'true' ), true );
					} elseif ( 'number' === $spec_type ) {
						$read['specs'][ $spec_key ] = (float) $override;
					} else {
						$read['specs'][ $spec_key ] = $override;
					}
					// Remove from estimates since it is now explicitly verified.
					$read['est'] = array_values( array_diff( $read['est'], array( $spec_key ) ) );
				}
			}
		}

		$item = array(
			'id'    => $post->ID,
			'name'  => $title,
			'shop'  => (string) get_post_meta( $post->ID, Gear_Engine_CPT::META_MERCHANT, true ),
			'price' => $price,
			'old'   => $old > $price ? $old : 0,
			'cur'   => (string) get_post_meta( $post->ID, Gear_Engine_CPT::META_CURRENCY, true ) ?: 'kr',
			'img'   => esc_url_raw( $image ),
			'url'   => esc_url_raw( $url ),
			'specs' => $read['specs'],
			'est'   => $read['est'],
		);

		/**
		 * Filter the qualifying gear picker product item.
		 *
		 * @param array<string, mixed>|null $item   Item payload.
		 * @param WP_Post                   $post   Post object.
		 * @param array<string, mixed>      $config Gear picker configuration.
		 */
		$item = apply_filters( 'gear_engine_picker_item', $item, $post, $config );

		if ( empty( $item ) || ! is_array( $item ) ) {
			return array(
				'item'   => null,
				'reason' => 'filtered',
			);
		}

		return array(
			'item'   => $item,
			'reason' => '',
		);
	}

	/**
	 * Qualifying products for a picker type, cached.
	 *
	 * @param string               $type     Type slug.
	 * @param array<string, mixed> $config   Picker config.
	 * @param string[]             $category Optional gear_category term slugs restricting the query.
	 * @return array<int, array<string, mixed>>
	 */
	public static function get_products( string $type, array $config, array $category = array() ): array {
		$cache_key = 'gear_engine_picker_' . md5( $type . '|' . implode( ',', $category ) . '|' . get_option( self::OPTION_CACHE_VERSION, 0 ) );
		$cached    = get_transient( $cache_key );
		if ( is_array( $cached ) ) {
			return $cached;
		}

		$posts = Gear_Engine_Product_Service::get_raw_posts(
			array(
				'posts_per_page' => (int) apply_filters( 'gear_engine_gear_picker_max_products', 1000 ),
				'category'       => $category,
			)
		);

		$items = array();
		foreach ( $posts as $post ) {
			$item = self::build_item( $post, $config, (bool) $category );
			if ( $item ) {
				$items[] = $item;
			}
		}

		set_transient( $cache_key, $items, (int) apply_filters( 'gear_engine_gear_picker_cache_ttl', HOUR_IN_SECONDS ) );
		return $items;
	}

	/**
	 * Config subset needed by the browser (extraction rules stay on the server).
	 *
	 * @param array<string, mixed> $config Picker config.
	 * @return array<string, mixed>
	 */
	public static function get_client_config( array $config ): array {
		return array_intersect_key( $config, array_flip( array( 'id', 'label', 'pills', 'limit', 'feature_shop', 'intro', 'steps', 'fallback', 'scoring', 'notes' ) ) );
	}

	/**
	 * Render the shortcode.
	 *
	 * @param array<string, string>|string $atts Shortcode attributes.
	 * @return string
	 */
	public function render( $atts = array() ): string {
		$atts = shortcode_atts(
			array(
				'type'     => 'treadmill',
				'category' => '',
			),
			is_array( $atts ) ? $atts : array(),
			self::SHORTCODE_TAG
		);

		$type   = sanitize_key( $atts['type'] );
		$config = self::get_config( $type );
		if ( is_wp_error( $config ) ) {
			return current_user_can( 'edit_posts' )
				? '<p class="gear-engine-picker-error">' . esc_html( $config->get_error_message() ) . '</p>'
				: '';
		}

		$category = array_values( array_filter( array_map( 'sanitize_title', explode( ',', (string) $atts['category'] ) ) ) );
		$items    = self::get_products( $type, $config, $category );
		$payload  = array(
			'config' => self::get_client_config( $config ),
			'items'  => $items,
		);

		wp_enqueue_style( 'gear-engine-gear-picker' );
		wp_enqueue_script( 'gear-engine-gear-picker' );

		ob_start();
		include GEAR_ENGINE_PATH . 'modules/gear-picker/templates/picker-container.php';
		return (string) ob_get_clean();
	}
}
