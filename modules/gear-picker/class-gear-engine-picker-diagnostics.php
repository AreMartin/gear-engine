<?php
/**
 * Admin diagnostics for the gear pickers: how many products each picker can use, why others
 * are skipped, and plain-language advice on what to do next.
 *
 * @package GearEngine
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

/**
 * Class Gear_Engine_Picker_Diagnostics
 */
class Gear_Engine_Picker_Diagnostics {

	public const PAGE_SLUG = 'gear-engine-picker-diagnostics';

	/**
	 * Fewer qualifying products than this makes a picker unreliable.
	 */
	public const MIN_PRODUCTS = 5;

	/**
	 * Hook suffix of the page, set on registration.
	 *
	 * @var string
	 */
	private $hook = '';

	/**
	 * Constructor.
	 */
	public function __construct() {
		add_action( 'admin_menu', array( $this, 'register_page' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_assets' ) );
	}

	/**
	 * Register the page under Exercise Gear.
	 *
	 * @return void
	 */
	public function register_page(): void {
		$hook = add_submenu_page(
			'edit.php?post_type=' . Gear_Engine_CPT::POST_TYPE,
			__( 'Gear Pickers Health', 'gear-engine' ),
			__( 'Gear Pickers Health', 'gear-engine' ),
			'manage_options',
			self::PAGE_SLUG,
			array( $this, 'render_page' )
		);
		$this->hook = is_string( $hook ) ? $hook : '';
	}

	/**
	 * Load the page stylesheet on this page only.
	 *
	 * @param string $hook Admin page hook suffix.
	 * @return void
	 */
	public function enqueue_assets( $hook ): void {
		if ( '' === $this->hook || $this->hook !== $hook ) {
			return;
		}
		wp_enqueue_style( 'gear-engine-picker-diagnostics', GEAR_ENGINE_URL . 'modules/gear-picker/assets/css/diagnostics.css', array(), GEAR_ENGINE_VERSION );
	}

	/**
	 * Plain-language labels for rejection reasons.
	 *
	 * @param int $min_price Minimum price of the picker type.
	 * @return array<string, array{label:string,help:string}>
	 */
	public static function get_reason_labels( int $min_price = 0 ): array {
		return array(
			'other_type'      => array(
				'label' => __( 'Other kinds of gear', 'gear-engine' ),
				'help'  => __( 'Normal: these simply are not this product type. Look through the examples to make sure no real product of this type is hiding here.', 'gear-engine' ),
			),
			'excluded'        => array(
				'label' => __( 'Looks like an accessory or spare part', 'gear-engine' ),
				'help'  => __( 'Skipped on purpose (mats, belts, spare parts and similar). If a real product shows up in the examples, remove the matching word from "exclude" or "exclude_title" in the setup file.', 'gear-engine' ),
			),
			'below_min_price' => array(
				'label' => sprintf(
					/* translators: %s: minimum price */
					__( 'Priced below %s kr', 'gear-engine' ),
					number_format_i18n( $min_price )
				),
				'help'  => __( 'Usually accessories or wrongly priced items. If real products are cut, lower "min_price" in the setup file.', 'gear-engine' ),
			),
			'out_of_stock'    => array(
				'label' => __( 'Out of stock', 'gear-engine' ),
				'help'  => __( 'Normal. They come back automatically when the feed says they are in stock again.', 'gear-engine' ),
			),
			'no_price'        => array(
				'label' => __( 'Missing price', 'gear-engine' ),
				'help'  => __( 'The feed did not give a price. Open the product and check its price field, or check the feed mapping.', 'gear-engine' ),
			),
			'no_link'         => array(
				'label' => __( 'Missing affiliate link', 'gear-engine' ),
				'help'  => __( 'Without a link there is nothing to send visitors to. Check the feed mapping or add the link on the product.', 'gear-engine' ),
			),
		);
	}

	/**
	 * Count qualifying and rejected products for a picker type.
	 *
	 * @param array<string, mixed> $config Picker config.
	 * @param WP_Post[]            $posts  All gear posts.
	 * @return array<string, mixed>
	 */
	public static function analyze( array $config, array $posts ): array {
		$stats = array(
			'total'     => count( $posts ),
			'qualified' => 0,
			'reasons'   => array(),
			'samples'   => array(),
			'specs'     => array(),
		);

		foreach ( (array) $config['specs'] as $key => $def ) {
			$stats['specs'][ $key ] = array(
				'type'    => (string) ( $def['type'] ?? 'number' ),
				'found'   => 0,
				'guessed' => 0,
				'missing' => 0,
			);
		}

		foreach ( $posts as $post ) {
			$result = Gear_Engine_Gear_Picker::evaluate_post( $post, $config );
			if ( $result['item'] ) {
				++$stats['qualified'];
				self::tally_specs( $stats['specs'], $result['item'] );
				continue;
			}
			$reason                      = $result['reason'];
			$stats['reasons'][ $reason ] = ( $stats['reasons'][ $reason ] ?? 0 ) + 1;
			if ( count( $stats['samples'][ $reason ] ?? array() ) < 5 ) {
				$stats['samples'][ $reason ][] = get_the_title( $post );
			}
		}

		return $stats;
	}

	/**
	 * Add one product to the per-spec coverage counts.
	 *
	 * @param array<string, array<string, mixed>> $specs Coverage counters (by reference).
	 * @param array<string, mixed>                $item  Picker item.
	 * @return void
	 */
	public static function tally_specs( array &$specs, array $item ): void {
		foreach ( $specs as $key => $row ) {
			$value = $item['specs'][ $key ] ?? null;
			if ( in_array( $key, (array) ( $item['est'] ?? array() ), true ) ) {
				++$specs[ $key ]['guessed'];
			} elseif ( null !== $value && false !== $value ) {
				++$specs[ $key ]['found'];
			} else {
				++$specs[ $key ]['missing'];
			}
		}
	}

	/**
	 * Category terms whose name contains one of the picker's include words.
	 *
	 * @param array<int, array{name:string,slug:string,count:int}> $terms   Category terms.
	 * @param string[]                                             $include Include words.
	 * @return array<int, array{name:string,slug:string,count:int}>
	 */
	public static function suggest_categories( array $terms, array $include ): array {
		$found = array();
		foreach ( $terms as $term ) {
			$name = function_exists( 'mb_strtolower' ) ? mb_strtolower( $term['name'], 'UTF-8' ) : strtolower( $term['name'] );
			foreach ( $include as $word ) {
				if ( '' !== $word && false !== strpos( $name, function_exists( 'mb_strtolower' ) ? mb_strtolower( $word, 'UTF-8' ) : strtolower( $word ) ) ) {
					$found[] = $term;
					break;
				}
			}
		}
		return $found;
	}

	/**
	 * Which picker types a piece of content embeds through the shortcode.
	 *
	 * @param string $content Post content.
	 * @return string[]
	 */
	public static function shortcode_types_in( string $content ): array {
		$types = array();
		if ( preg_match_all( '/\[' . preg_quote( Gear_Engine_Gear_Picker::SHORTCODE_TAG, '/' ) . '(\s[^\]]*)?\]/', $content, $matches ) ) {
			foreach ( $matches[1] as $attrs ) {
				$types[] = preg_match( '/\btype\s*=\s*["\']?([a-z0-9_\-]+)/i', (string) $attrs, $m ) ? strtolower( $m[1] ) : 'treadmill';
			}
		}
		return array_values( array_unique( $types ) );
	}

	/**
	 * Turn the numbers into advice a non-technical admin can act on.
	 *
	 * @param array<string, mixed> $stats      Result of analyze().
	 * @param array<int, array>    $categories Suggested categories (see suggest_categories()).
	 * @param int                  $used_on    Number of pages that embed this picker.
	 * @return array<int, array{level:string,text:string}> Levels: ok, tip, warn, bad.
	 */
	public static function advise( array $stats, array $categories = array(), int $used_on = 0 ): array {
		$advice = array();
		$q      = (int) $stats['qualified'];
		$total  = (int) $stats['total'];
		$r      = (array) $stats['reasons'];

		if ( 0 === $total ) {
			return array(
				array(
					'level' => 'bad',
					'text'  => __( 'There are no gear products on the site yet. Run the affiliate feed import first, then reload this page.', 'gear-engine' ),
				),
			);
		}

		if ( 0 === $q ) {
			$advice[] = array(
				'level' => 'bad',
				'text'  => __( 'This picker has no products to recommend, so visitors would see "no results". Follow the steps below.', 'gear-engine' ),
			);
			if ( $categories ) {
				$advice[] = array(
					'level' => 'bad',
					'text'  => sprintf(
						/* translators: %s: shortcode attribute */
						__( 'Your products have a matching category. Try the shortcode with %s so the picker uses that category instead of guessing from product names.', 'gear-engine' ),
						'category="' . $categories[0]['slug'] . '"'
					),
				);
			}
			if ( ! empty( $r['other_type'] ) && $r['other_type'] === $total ) {
				$advice[] = array(
					'level' => 'bad',
					'text'  => __( 'No product name or category contains the words this picker looks for. Check that this kind of product has been imported, or add the words your feed uses to "include" in the setup file.', 'gear-engine' ),
				);
			}
		} elseif ( $q < self::MIN_PRODUCTS ) {
			$advice[] = array(
				'level' => 'warn',
				'text'  => sprintf(
					/* translators: 1: product count, 2: recommended minimum */
					_n( 'Only %1$d product qualifies. The quiz will often show few or no results. Aim for at least %2$d by importing more feeds or loosening the filters.', 'Only %1$d products qualify. The quiz will often show few or no results. Aim for at least %2$d by importing more feeds or loosening the filters.', $q, 'gear-engine' ),
					$q,
					self::MIN_PRODUCTS
				),
			);
			if ( $categories ) {
				$advice[] = array(
					'level' => 'tip',
					'text'  => sprintf(
						/* translators: %s: shortcode attribute */
						__( 'You have a matching category. Using %s can catch products whose names do not mention the product type.', 'gear-engine' ),
						'category="' . $categories[0]['slug'] . '"'
					),
				);
			}
		} else {
			$advice[] = array(
				'level' => 'ok',
				'text'  => __( 'Enough products for good recommendations.', 'gear-engine' ),
			);
		}

		if ( $q > 0 ) {
			foreach ( (array) $stats['specs'] as $key => $row ) {
				if ( 'number' !== $row['type'] ) {
					continue;
				}
				if ( $row['found'] / $q < 0.5 ) {
					$advice[] = array(
						'level' => 'warn',
						'text'  => sprintf(
							/* translators: 1: spec name, 2: products where it was found, 3: product count */
							__( 'Most products do not state "%1$s" in their description (found for %2$d of %3$d). The picker then guesses or skips it, which makes matching less accurate. Fuller descriptions in the feed, or extra patterns for "%1$s" in the setup file, will help.', 'gear-engine' ),
							$key,
							$row['found'],
							$q
						),
					);
				}
			}
		}

		if ( ! empty( $r['excluded'] ) && $q > 0 ) {
			$advice[] = array(
				'level' => 'tip',
				'text'  => sprintf(
					/* translators: %d: number of products */
					_n( '%d product was skipped as an accessory or spare part. Open the details and check it is not a real product.', '%d products were skipped as accessories or spare parts. Open the details and check none of them are real products.', (int) $r['excluded'], 'gear-engine' ),
					(int) $r['excluded']
				),
			);
		}
		if ( ! empty( $r['no_price'] ) || ! empty( $r['no_link'] ) ) {
			$advice[] = array(
				'level' => 'warn',
				'text'  => __( 'Some matching products are missing a price or an affiliate link and are left out. Check the feed mapping or fix them on the product.', 'gear-engine' ),
			);
		}
		if ( 0 === $used_on ) {
			$advice[] = array(
				'level' => 'tip',
				'text'  => __( 'This picker is not on any published page yet. Copy the shortcode above into a page to use it.', 'gear-engine' ),
			);
		}

		return $advice;
	}

	/**
	 * Overall status from advice levels.
	 *
	 * @param array<int, array{level:string,text:string}> $advice Advice list.
	 * @return string ready|attention|broken
	 */
	public static function status( array $advice ): string {
		$levels = array_column( $advice, 'level' );
		if ( in_array( 'bad', $levels, true ) ) {
			return 'broken';
		}
		return in_array( 'warn', $levels, true ) ? 'attention' : 'ready';
	}

	/**
	 * Published pages and posts embedding each picker type.
	 *
	 * @return array<string, array<int, array{title:string,url:string}>>
	 */
	public static function find_usage(): array {
		$usage = array();
		$posts = get_posts(
			array(
				'post_type'      => array( 'page', 'post' ),
				'post_status'    => 'publish',
				's'              => Gear_Engine_Gear_Picker::SHORTCODE_TAG,
				'posts_per_page' => 100,
				'no_found_rows'  => true,
			)
		);
		foreach ( $posts as $post ) {
			foreach ( self::shortcode_types_in( (string) $post->post_content ) as $type ) {
				$usage[ $type ][] = array(
					'title' => get_the_title( $post ),
					'url'   => (string) get_edit_post_link( $post->ID, 'raw' ),
				);
			}
		}
		return $usage;
	}

	/**
	 * Build the report for every picker type.
	 *
	 * @return array<int, array<string, mixed>>
	 */
	public static function build_report(): array {
		$posts = Gear_Engine_Product_Service::get_raw_posts();
		$terms = get_terms( array( 'taxonomy' => Gear_Engine_CPT::TAXONOMY, 'hide_empty' => true ) );
		$terms = is_wp_error( $terms ) ? array() : array_map(
			static function ( $t ) {
				return array(
					'name'  => (string) $t->name,
					'slug'  => (string) $t->slug,
					'count' => (int) $t->count,
				);
			},
			$terms
		);
		$usage = self::find_usage();

		$report = array();
		foreach ( Gear_Engine_Gear_Picker::get_types() as $type ) {
			$config = Gear_Engine_Gear_Picker::get_config( $type );
			$row    = array(
				'type'      => $type,
				'shortcode' => '[' . Gear_Engine_Gear_Picker::SHORTCODE_TAG . ' type="' . $type . '"]',
				'usage'     => $usage[ $type ] ?? array(),
			);

			if ( is_wp_error( $config ) ) {
				$row['label']  = $type;
				$row['error']  = $config->get_error_message();
				$row['advice'] = array(
					array(
						'level' => 'bad',
						'text'  => __( 'The setup file for this picker has a problem and the picker is switched off. Fix the file (it must be valid JSON) or ask your developer.', 'gear-engine' ),
					),
				);
				$row['status'] = 'broken';
				$report[]      = $row;
				continue;
			}

			$stats  = self::analyze( $config, $posts );
			$cats   = self::suggest_categories( $terms, (array) ( $config['match']['include'] ?? array() ) );
			$advice = self::advise( $stats, $cats, count( $row['usage'] ) );

			$row['label']      = (string) ( $config['label'] ?? $type );
			$row['stats']      = $stats;
			$row['categories'] = $cats;
			$row['advice']     = $advice;
			$row['status']     = self::status( $advice );
			$row['reasons']    = self::get_reason_labels( (int) ( $config['match']['min_price'] ?? 0 ) );
			$report[]          = $row;
		}

		return $report;
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

		$report = self::build_report();
		include GEAR_ENGINE_PATH . 'modules/gear-picker/templates/diagnostics.php';
	}
}
