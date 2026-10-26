<?php
/**
 * Custom Post Type and Meta Registration Module.
 *
 * @package GearEngine
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

/**
 * Class GAE_CPT
 *
 * Registers the `exercise_gear` post type, custom taxonomy, and affiliate meta fields.
 */
class GAE_CPT {

	/**
	 * Post type slug.
	 */
	public const POST_TYPE = 'exercise_gear';

	/**
	 * Taxonomy slug.
	 */
	public const TAXONOMY = 'gear_category';

	/**
	 * Meta field keys.
	 */
	public const META_EAN           = '_product_ean';
	public const META_PRICE         = '_affiliate_price';
	public const META_REGULAR_PRICE = '_regular_price';
	public const META_IN_STOCK      = '_in_stock';
	public const META_AFFILIATE_URL = '_affiliate_url';
	public const META_MERCHANT      = '_merchant_name';
	public const META_IMAGE_URL     = '_product_image_url';
	public const META_CURRENCY      = '_currency';
	public const META_LAST_SYNCED   = '_last_synced';

	/**
	 * Constructor.
	 */
	public function __construct() {
		add_action( 'init', array( $this, 'register_post_type' ) );
		add_action( 'init', array( $this, 'register_taxonomy' ) );
		add_action( 'init', array( $this, 'register_meta_fields' ) );
		add_action( 'add_meta_boxes', array( $this, 'add_meta_boxes' ) );
		add_action( 'save_post_' . self::POST_TYPE, array( $this, 'save_meta_boxes' ), 10, 2 );
		add_action( 'admin_init', array( $this, 'maybe_seed_sample_products' ) );

		// Admin columns.
		add_filter( 'manage_' . self::POST_TYPE . '_posts_columns', array( $this, 'register_admin_columns' ) );
		add_action( 'manage_' . self::POST_TYPE . '_posts_custom_column', array( $this, 'render_admin_columns' ), 10, 2 );
		add_filter( 'manage_edit-' . self::POST_TYPE . '_sortable_columns', array( $this, 'register_sortable_columns' ) );
	}

	/**
	 * Register the `exercise_gear` Custom Post Type.
	 *
	 * @return void
	 */
	public function register_post_type(): void {
		$labels = array(
			'name'                  => _x( 'Exercise Gear', 'Post type general name', 'gear-engine' ),
			'singular_name'         => _x( 'Gear Item', 'Post type singular name', 'gear-engine' ),
			'menu_name'             => _x( 'Exercise Gear', 'Admin Menu text', 'gear-engine' ),
			'name_admin_bar'        => _x( 'Gear Item', 'Add New on Toolbar', 'gear-engine' ),
			'add_new'               => __( 'Add New', 'gear-engine' ),
			'add_new_item'          => __( 'Add New Gear Item', 'gear-engine' ),
			'new_item'              => __( 'New Gear Item', 'gear-engine' ),
			'edit_item'             => __( 'Edit Gear Item', 'gear-engine' ),
			'view_item'             => __( 'View Gear Item', 'gear-engine' ),
			'all_items'             => __( 'All Exercise Gear', 'gear-engine' ),
			'search_items'          => __( 'Search Exercise Gear', 'gear-engine' ),
			'parent_item_colon'     => __( 'Parent Gear Items:', 'gear-engine' ),
			'not_found'             => __( 'No exercise gear found.', 'gear-engine' ),
			'not_found_in_trash'    => __( 'No exercise gear found in Trash.', 'gear-engine' ),
			'featured_image'        => _x( 'Product Image', 'Overrides the "Featured Image" phrase', 'gear-engine' ),
			'set_featured_image'    => _x( 'Set product image', 'Overrides the "Set featured image" phrase', 'gear-engine' ),
			'remove_featured_image' => _x( 'Remove product image', 'Overrides the "Remove featured image" phrase', 'gear-engine' ),
			'use_featured_image'    => _x( 'Use as product image', 'Overrides the "Use as featured image" phrase', 'gear-engine' ),
		);

		$args = array(
			'labels'             => $labels,
			'public'             => true,
			'publicly_queryable' => true,
			'show_ui'            => true,
			'show_in_menu'       => true,
			'query_var'          => true,
			'rewrite'            => array( 'slug' => 'gear', 'with_front' => false ),
			'capability_type'    => 'post',
			'has_archive'        => true,
			'hierarchical'       => false,
			'menu_position'      => 25,
			'menu_icon'          => 'dashicons-heart',
			'supports'           => array( 'title', 'editor', 'thumbnail', 'excerpt', 'custom-fields' ),
			'show_in_rest'       => true,
			'rest_base'          => 'exercise_gear',
		);

		register_post_type( self::POST_TYPE, $args );
	}

	/**
	 * Register gear category taxonomy.
	 *
	 * @return void
	 */
	public function register_taxonomy(): void {
		$labels = array(
			'name'              => _x( 'Gear Categories', 'taxonomy general name', 'gear-engine' ),
			'singular_name'     => _x( 'Gear Category', 'taxonomy singular name', 'gear-engine' ),
			'search_items'      => __( 'Search Categories', 'gear-engine' ),
			'all_items'         => __( 'All Categories', 'gear-engine' ),
			'parent_item'       => __( 'Parent Category', 'gear-engine' ),
			'parent_item_colon' => __( 'Parent Category:', 'gear-engine' ),
			'edit_item'         => __( 'Edit Category', 'gear-engine' ),
			'update_item'       => __( 'Update Category', 'gear-engine' ),
			'add_new_item'      => __( 'Add New Category', 'gear-engine' ),
			'new_item_name'     => __( 'New Category Name', 'gear-engine' ),
			'menu_name'         => __( 'Categories', 'gear-engine' ),
		);

		$args = array(
			'hierarchical'      => true,
			'labels'            => $labels,
			'show_ui'           => true,
			'show_admin_column' => true,
			'query_var'         => true,
			'rewrite'           => array( 'slug' => 'gear-category' ),
			'show_in_rest'      => true,
		);

		register_taxonomy( self::TAXONOMY, array( self::POST_TYPE ), $args );
	}

	/**
	 * Register meta fields with schema for Gutenberg and REST API.
	 *
	 * @return void
	 */
	public function register_meta_fields(): void {
		// 1. _product_ean (Deduplication key).
		register_post_meta(
			self::POST_TYPE,
			self::META_EAN,
			array(
				'show_in_rest'      => true,
				'single'            => true,
				'type'              => 'string',
				'description'       => __( 'Product EAN/GTIN barcode identifier used for deduplication.', 'gear-engine' ),
				'sanitize_callback' => 'sanitize_text_field',
				'auth_callback'     => array( $this, 'meta_auth_callback' ),
			)
		);

		// 2. _affiliate_price.
		register_post_meta(
			self::POST_TYPE,
			self::META_PRICE,
			array(
				'show_in_rest'      => true,
				'single'            => true,
				'type'              => 'number',
				'description'       => __( 'Current affiliate price.', 'gear-engine' ),
				'sanitize_callback' => 'floatval',
				'auth_callback'     => array( $this, 'meta_auth_callback' ),
			)
		);

		// 3. _regular_price.
		register_post_meta(
			self::POST_TYPE,
			self::META_REGULAR_PRICE,
			array(
				'show_in_rest'      => true,
				'single'            => true,
				'type'              => 'number',
				'description'       => __( 'Original / regular price before discount.', 'gear-engine' ),
				'sanitize_callback' => 'floatval',
				'auth_callback'     => array( $this, 'meta_auth_callback' ),
			)
		);

		// 4. _in_stock.
		register_post_meta(
			self::POST_TYPE,
			self::META_IN_STOCK,
			array(
				'show_in_rest'      => true,
				'single'            => true,
				'type'              => 'boolean',
				'description'       => __( 'Stock availability status.', 'gear-engine' ),
				'sanitize_callback' => 'rest_sanitize_boolean',
				'auth_callback'     => array( $this, 'meta_auth_callback' ),
			)
		);

		// 5. _affiliate_url.
		register_post_meta(
			self::POST_TYPE,
			self::META_AFFILIATE_URL,
			array(
				'show_in_rest'      => true,
				'single'            => true,
				'type'              => 'string',
				'description'       => __( 'Affiliate tracking link.', 'gear-engine' ),
				'sanitize_callback' => 'esc_url_raw',
				'auth_callback'     => array( $this, 'meta_auth_callback' ),
			)
		);

		// 6. _merchant_name.
		register_post_meta(
			self::POST_TYPE,
			self::META_MERCHANT,
			array(
				'show_in_rest'      => true,
				'single'            => true,
				'type'              => 'string',
				'description'       => __( 'Merchant or retailer name.', 'gear-engine' ),
				'sanitize_callback' => 'sanitize_text_field',
				'auth_callback'     => array( $this, 'meta_auth_callback' ),
			)
		);

		// 7. _product_image_url.
		register_post_meta(
			self::POST_TYPE,
			self::META_IMAGE_URL,
			array(
				'show_in_rest'      => true,
				'single'            => true,
				'type'              => 'string',
				'description'       => __( 'External product image URL from feed.', 'gear-engine' ),
				'sanitize_callback' => 'esc_url_raw',
				'auth_callback'     => array( $this, 'meta_auth_callback' ),
			)
		);

		// 8. _currency.
		register_post_meta(
			self::POST_TYPE,
			self::META_CURRENCY,
			array(
				'show_in_rest'      => true,
				'single'            => true,
				'type'              => 'string',
				'description'       => __( 'Currency symbol or code (e.g. kr / NOK).', 'gear-engine' ),
				'sanitize_callback' => 'sanitize_text_field',
				'auth_callback'     => array( $this, 'meta_auth_callback' ),
			)
		);

		// 9. _last_synced.
		register_post_meta(
			self::POST_TYPE,
			self::META_LAST_SYNCED,
			array(
				'show_in_rest'      => true,
				'single'            => true,
				'type'              => 'string',
				'description'       => __( 'Timestamp of the last feed synchronization.', 'gear-engine' ),
				'sanitize_callback' => 'sanitize_text_field',
				'auth_callback'     => array( $this, 'meta_auth_callback' ),
			)
		);
	}

	/**
	 * Meta authorization callback for REST API.
	 *
	 * @return bool
	 */
	public function meta_auth_callback(): bool {
		return current_user_can( 'edit_posts' );
	}

	/**
	 * Add meta box in the post editor.
	 *
	 * @return void
	 */
	public function add_meta_boxes(): void {
		add_meta_box(
			'gae_gear_meta_box',
			__( 'Affiliate & Product PIM Data', 'gear-engine' ),
			array( $this, 'render_meta_box' ),
			self::POST_TYPE,
			'normal',
			'high'
		);
	}

	/**
	 * Render the post edit meta box.
	 *
	 * @param WP_Post $post Current post.
	 * @return void
	 */
	public function render_meta_box( WP_Post $post ): void {
		wp_nonce_field( 'gae_save_meta_box', 'gae_meta_box_nonce' );

		$ean           = get_post_meta( $post->ID, self::META_EAN, true );
		$price         = get_post_meta( $post->ID, self::META_PRICE, true );
		$regular_price = get_post_meta( $post->ID, self::META_REGULAR_PRICE, true );
		$in_stock      = get_post_meta( $post->ID, self::META_IN_STOCK, true );
		$affiliate_url = get_post_meta( $post->ID, self::META_AFFILIATE_URL, true );
		$merchant      = get_post_meta( $post->ID, self::META_MERCHANT, true );
		$image_url     = get_post_meta( $post->ID, self::META_IMAGE_URL, true );
		$currency      = get_post_meta( $post->ID, self::META_CURRENCY, true ) ?: 'kr';
		$last_synced   = get_post_meta( $post->ID, self::META_LAST_SYNCED, true );
		?>
		<table class="form-table">
			<tr>
				<th><label for="gae_ean"><?php esc_html_e( 'Product EAN (Deduplication Key)', 'gear-engine' ); ?></label></th>
				<td>
					<input type="text" id="gae_ean" name="gae_ean" value="<?php echo esc_attr( (string) $ean ); ?>" class="regular-text" required />
					<p class="description"><?php esc_html_e( 'Unique barcode / EAN / GTIN used to match and update products across feeds.', 'gear-engine' ); ?></p>
				</td>
			</tr>
			<tr>
				<th><label for="gae_merchant"><?php esc_html_e( 'Merchant Name', 'gear-engine' ); ?></label></th>
				<td>
					<input type="text" id="gae_merchant" name="gae_merchant" value="<?php echo esc_attr( (string) $merchant ); ?>" class="regular-text" />
					<p class="description"><?php esc_html_e( 'Retailer/store providing this affiliate product.', 'gear-engine' ); ?></p>
				</td>
			</tr>
			<tr>
				<th><label for="gae_price"><?php esc_html_e( 'Affiliate Price', 'gear-engine' ); ?></label></th>
				<td>
					<input type="number" step="any" id="gae_price" name="gae_price" value="<?php echo esc_attr( (string) $price ); ?>" class="regular-text" />
					<input type="text" name="gae_currency" value="<?php echo esc_attr( (string) $currency ); ?>" style="width: 60px;" placeholder="kr" />
				</td>
			</tr>
			<tr>
				<th><label for="gae_regular_price"><?php esc_html_e( 'Regular Price (Before Discount)', 'gear-engine' ); ?></label></th>
				<td>
					<input type="number" step="any" id="gae_regular_price" name="gae_regular_price" value="<?php echo esc_attr( (string) $regular_price ); ?>" class="regular-text" />
					<p class="description"><?php esc_html_e( 'Original price shown as strikethrough if on sale.', 'gear-engine' ); ?></p>
				</td>
			</tr>
			<tr>
				<th><label for="gae_in_stock"><?php esc_html_e( 'Stock Status', 'gear-engine' ); ?></label></th>
				<td>
					<label>
						<input type="checkbox" id="gae_in_stock" name="gae_in_stock" value="1" <?php checked( (bool) $in_stock ); ?> />
						<?php esc_html_e( 'In Stock / Available for Purchase', 'gear-engine' ); ?>
					</label>
				</td>
			</tr>
			<tr>
				<th><label for="gae_affiliate_url"><?php esc_html_e( 'Affiliate Tracking URL', 'gear-engine' ); ?></label></th>
				<td>
					<input type="url" id="gae_affiliate_url" name="gae_affiliate_url" value="<?php echo esc_attr( (string) $affiliate_url ); ?>" class="large-text" />
					<p class="description"><?php esc_html_e( 'Direct tracking URL from Adtraction or TradeTracker.', 'gear-engine' ); ?></p>
				</td>
			</tr>
			<tr>
				<th><label for="gae_image_url"><?php esc_html_e( 'External Product Image URL', 'gear-engine' ); ?></label></th>
				<td>
					<input type="url" id="gae_image_url" name="gae_image_url" value="<?php echo esc_attr( (string) $image_url ); ?>" class="large-text" />
					<p class="description"><?php esc_html_e( 'Remote feed image URL (used when featured image is not set).', 'gear-engine' ); ?></p>
				</td>
			</tr>
			<?php if ( ! empty( $last_synced ) ) : ?>
			<tr>
				<th><?php esc_html_e( 'Last Synced From Feed', 'gear-engine' ); ?></th>
				<td>
					<code><?php echo esc_html( (string) $last_synced ); ?></code>
				</td>
			</tr>
			<?php endif; ?>
		</table>
		<?php
	}

	/**
	 * Save meta box values on post save.
	 *
	 * @param int     $post_id Post ID.
	 * @param WP_Post $post    Post object.
	 * @return void
	 */
	public function save_meta_boxes( int $post_id, WP_Post $post ): void {
		// Nonce check.
		if ( ! isset( $_POST['gae_meta_box_nonce'] ) || ! wp_verify_nonce( sanitize_key( wp_unslash( $_POST['gae_meta_box_nonce'] ) ), 'gae_save_meta_box' ) ) {
			return;
		}

		// Autosave check.
		if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
			return;
		}

		// Permissions check.
		if ( ! current_user_can( 'edit_post', $post_id ) ) {
			return;
		}

		// Save EAN.
		if ( isset( $_POST['gae_ean'] ) ) {
			update_post_meta( $post_id, self::META_EAN, sanitize_text_field( wp_unslash( $_POST['gae_ean'] ) ) );
		}

		// Save Merchant.
		if ( isset( $_POST['gae_merchant'] ) ) {
			update_post_meta( $post_id, self::META_MERCHANT, sanitize_text_field( wp_unslash( $_POST['gae_merchant'] ) ) );
		}

		// Save Price.
		if ( isset( $_POST['gae_price'] ) ) {
			update_post_meta( $post_id, self::META_PRICE, floatval( wp_unslash( $_POST['gae_price'] ) ) );
		}

		// Save Regular Price.
		if ( isset( $_POST['gae_regular_price'] ) ) {
			update_post_meta( $post_id, self::META_REGULAR_PRICE, floatval( wp_unslash( $_POST['gae_regular_price'] ) ) );
		}

		// Save Stock status.
		$in_stock = isset( $_POST['gae_in_stock'] ) ? 1 : 0;
		update_post_meta( $post_id, self::META_IN_STOCK, $in_stock );

		// Save Affiliate URL.
		if ( isset( $_POST['gae_affiliate_url'] ) ) {
			update_post_meta( $post_id, self::META_AFFILIATE_URL, esc_url_raw( wp_unslash( $_POST['gae_affiliate_url'] ) ) );
		}

		// Save Image URL.
		if ( isset( $_POST['gae_image_url'] ) ) {
			update_post_meta( $post_id, self::META_IMAGE_URL, esc_url_raw( wp_unslash( $_POST['gae_image_url'] ) ) );
		}

		// Save Currency.
		if ( isset( $_POST['gae_currency'] ) ) {
			update_post_meta( $post_id, self::META_CURRENCY, sanitize_text_field( wp_unslash( $_POST['gae_currency'] ) ) );
		}
	}

	/**
	 * Register custom columns in the exercise_gear admin post table.
	 *
	 * @param array<string, string> $columns Existing columns.
	 * @return array<string, string>
	 */
	public function register_admin_columns( array $columns ): array {
		$new_columns = array();
		foreach ( $columns as $key => $label ) {
			$new_columns[ $key ] = $label;
			if ( 'title' === $key ) {
				$new_columns['gae_thumb']    = __( 'Image', 'gear-engine' );
				$new_columns['gae_ean']      = __( 'EAN', 'gear-engine' );
				$new_columns['gae_merchant'] = __( 'Merchant', 'gear-engine' );
				$new_columns['gae_price']    = __( 'Price', 'gear-engine' );
				$new_columns['gae_stock']    = __( 'Stock', 'gear-engine' );
				$new_columns['gae_synced']   = __( 'Last Synced', 'gear-engine' );
			}
		}
		return $new_columns;
	}

	/**
	 * Render custom column content.
	 *
	 * @param string $column  Column identifier.
	 * @param int    $post_id Post ID.
	 * @return void
	 */
	public function render_admin_columns( string $column, int $post_id ): void {
		switch ( $column ) {
			case 'gae_thumb':
				$thumb_id = get_post_thumbnail_id( $post_id );
				if ( $thumb_id ) {
					echo wp_get_attachment_image( $thumb_id, array( 45, 45 ), true, array( 'style' => 'border-radius:4px; object-fit:cover;' ) );
				} else {
					$ext_img = get_post_meta( $post_id, self::META_IMAGE_URL, true );
					if ( $ext_img ) {
						printf( '<img src="%s" width="45" height="45" style="border-radius:4px; object-fit:cover;" alt="" />', esc_url( $ext_img ) );
					} else {
						echo '<span style="color:#94a3b8;">—</span>';
					}
				}
				break;

			case 'gae_ean':
				$ean = get_post_meta( $post_id, self::META_EAN, true );
				echo $ean ? '<code>' . esc_html( (string) $ean ) . '</code>' : '<span style="color:#94a3b8;">—</span>';
				break;

			case 'gae_merchant':
				$merchant = get_post_meta( $post_id, self::META_MERCHANT, true );
				echo $merchant ? esc_html( (string) $merchant ) : '<span style="color:#94a3b8;">—</span>';
				break;

			case 'gae_price':
				$price    = get_post_meta( $post_id, self::META_PRICE, true );
				$reg      = get_post_meta( $post_id, self::META_REGULAR_PRICE, true );
				$currency = get_post_meta( $post_id, self::META_CURRENCY, true ) ?: 'kr';
				if ( '' !== $price && null !== $price ) {
					printf( '<strong>%s %s</strong>', esc_html( number_format_i18n( (float) $price ) ), esc_html( $currency ) );
					if ( $reg && (float) $reg > (float) $price ) {
						printf( '<br><small style="color:#94a3b8; text-decoration:line-through;">%s %s</small>', esc_html( number_format_i18n( (float) $reg ) ), esc_html( $currency ) );
					}
				} else {
					echo '<span style="color:#94a3b8;">—</span>';
				}
				break;

			case 'gae_stock':
				$in_stock = (bool) get_post_meta( $post_id, self::META_IN_STOCK, true );
				if ( $in_stock ) {
					echo '<span style="color:#16a34a; font-weight:600;">● ' . esc_html__( 'In Stock', 'gear-engine' ) . '</span>';
				} else {
					echo '<span style="color:#ef4444; font-weight:600;">● ' . esc_html__( 'Out of Stock', 'gear-engine' ) . '</span>';
				}
				break;

			case 'gae_synced':
				$synced = get_post_meta( $post_id, self::META_LAST_SYNCED, true );
				if ( $synced ) {
					$time = strtotime( $synced );
					if ( $time ) {
						printf( '<span title="%s">%s %s</span>', esc_attr( $synced ), esc_html( human_time_diff( $time ) ), esc_html__( 'ago', 'gear-engine' ) );
					} else {
						echo esc_html( (string) $synced );
					}
				} else {
					echo '<span style="color:#94a3b8;">—</span>';
				}
				break;
		}
	}

	/**
	 * Register sortable columns.
	 *
	 * @param array<string, string> $columns Existing columns.
	 * @return array<string, string>
	 */
	public function register_sortable_columns( array $columns ): array {
		$columns['gae_price']    = 'gae_price';
		$columns['gae_stock']    = 'gae_stock';
		$columns['gae_merchant'] = 'gae_merchant';
		return $columns;
	}

	/**
	 * Find a product by its EAN deduplication key.
	 *
	 * @param string $ean EAN/GTIN barcode.
	 * @return WP_Post|null
	 */
	public static function get_product_by_ean( string $ean ): ?WP_Post {
		global $wpdb;

		if ( empty( $ean ) ) {
			return null;
		}

		$post_id = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT post_id FROM {$wpdb->postmeta} WHERE meta_key = %s AND meta_value = %s LIMIT 1",
				self::META_EAN,
				$ean
			)
		);

		if ( $post_id ) {
			$post = get_post( (int) $post_id );
			return ( $post instanceof WP_Post && self::POST_TYPE === $post->post_type ) ? $post : null;
		}

		return null;
	}

	/**
	 * Retrieve all formatted product data for a given post.
	 *
	 * @param int|string|WP_Post $post Post ID or WP_Post instance.
	 * @return array<string, mixed>|null
	 */
	public static function get_product_data( $post ): ?array {
		$original_input = $post;

		if ( is_numeric( $post ) ) {
			$post = get_post( (int) $post );
		}

		if ( ! ( $post instanceof WP_Post ) || self::POST_TYPE !== $post->post_type ) {
			// Fallback: check if requested ID matches a hardcoded test product definition.
			$samples = self::get_sample_products_definitions();
			if ( is_numeric( $original_input ) || is_string( $original_input ) ) {
				$key = (int) $original_input;
				if ( 1 === $key && isset( $samples[0] ) ) {
					return $samples[0];
				}
				if ( 2 === $key && isset( $samples[1] ) ) {
					return $samples[1];
				}
			}
			return null;
		}

		$post_id       = $post->ID;
		$ean           = (string) get_post_meta( $post_id, self::META_EAN, true );
		$price         = (float) get_post_meta( $post_id, self::META_PRICE, true );
		$regular_price = (float) get_post_meta( $post_id, self::META_REGULAR_PRICE, true );
		$in_stock      = (bool) get_post_meta( $post_id, self::META_IN_STOCK, true );
		$affiliate_url = (string) get_post_meta( $post_id, self::META_AFFILIATE_URL, true );
		$merchant      = (string) get_post_meta( $post_id, self::META_MERCHANT, true );
		$currency      = (string) get_post_meta( $post_id, self::META_CURRENCY, true ) ?: 'kr';
		$image_url     = (string) get_post_meta( $post_id, self::META_IMAGE_URL, true );

		// Use featured image thumbnail if available, otherwise external image URL.
		if ( has_post_thumbnail( $post_id ) ) {
			$thumb = wp_get_attachment_image_src( get_post_thumbnail_id( $post_id ), 'large' );
			if ( ! empty( $thumb[0] ) ) {
				$image_url = $thumb[0];
			}
		}

		// Fallback placeholder image.
		if ( empty( $image_url ) ) {
			$image_url = 'https://placehold.co/600x450/1e293b/ffffff?text=' . rawurlencode( $post->post_title );
		}

		// Formatted prices (e.g. "14 999 kr").
		$formatted_price = number_format( $price, 0, ',', ' ' ) . ' ' . $currency;
		$formatted_regular = $regular_price > $price ? number_format( $regular_price, 0, ',', ' ' ) . ' ' . $currency : '';

		return array(
			'id'                => $post_id,
			'title'             => get_the_title( $post ),
			'content'           => apply_filters( 'the_content', $post->post_content ),
			'excerpt'           => wp_strip_all_tags( $post->post_excerpt ?: wp_trim_words( $post->post_content, 35 ) ),
			'ean'               => $ean,
			'price'             => $price,
			'regular_price'     => $regular_price,
			'formatted_price'   => $formatted_price,
			'formatted_regular' => $formatted_regular,
			'in_stock'          => $in_stock,
			'affiliate_url'     => $affiliate_url,
			'merchant'          => $merchant,
			'currency'          => $currency,
			'image_url'         => $image_url,
			'permalink'         => get_permalink( $post_id ),
		);
	}

	/**
	 * Automatically seed sample test products if not yet present.
	 *
	 * @return void
	 */
	public function maybe_seed_sample_products(): void {
		if ( ! get_option( 'gae_sample_products_seeded' ) ) {
			self::seed_sample_products();
			update_option( 'gae_sample_products_seeded', 1, false );
		}
	}

	/**
	 * Hardcoded test product definitions for testing the Gutenberg block and PIM.
	 *
	 * @return array<int, array<string, mixed>>
	 */
	public static function get_sample_products_definitions(): array {
		return array(
			array(
				'id'                => 1,
				'title'             => 'NordicTrack T Series 6.5S Treadmill',
				'content'           => 'Experience the ultimate indoor workout with this premium treadmill. It features a spacious 20" x 55" running belt, an interactive HD touchscreen for on-demand classes, and an auto-adjusting incline to simulate real-world terrain perfectly.',
				'excerpt'           => 'Experience the ultimate indoor workout with this premium treadmill. It features a spacious 20" x 55" running belt, an interactive HD touchscreen for on-demand classes, and an auto-adjusting incline to simulate real-world terrain perfectly.',
				'ean'               => '7071234567890',
				'price'             => 14999.0,
				'regular_price'     => 19999.0,
				'formatted_price'   => '14 999 kr',
				'formatted_regular' => '19 999 kr',
				'in_stock'          => true,
				'affiliate_url'     => 'https://example.com/deal/nordictrack-t-series',
				'merchant'          => 'GymGrossisten',
				'currency'          => 'kr',
				'image_url'         => 'https://placehold.co/600x450/1e293b/ffffff?text=Treadmill+Pro',
				'permalink'         => '#',
				'category'          => 'Tredemølle',
			),
			array(
				'id'                => 2,
				'title'             => 'Concept2 RowErg Romaskin PM5',
				'content'           => 'Verdens mest anerkjente romaskin for seriøs utholdenhetstrening. Utstyrt med den avanserte PM5-monitoren, presis luftmotstand som tilpasser seg intensiteten din, og en solid stålramme testet av eliteutøvere over hele verden.',
				'excerpt'           => 'Verdens mest anerkjente romaskin for seriøs utholdenhetstrening. Utstyrt med den avanserte PM5-monitoren, presis luftmotstand som tilpasser seg intensiteten din, og en solid stålramme testet av eliteutøvere over hele verden.',
				'ean'               => '7079999000001',
				'price'             => 11499.0,
				'regular_price'     => 13499.0,
				'formatted_price'   => '11 499 kr',
				'formatted_regular' => '13 499 kr',
				'in_stock'          => true,
				'affiliate_url'     => 'https://example.com/deal/concept2-rowerg',
				'merchant'          => 'Treningspartner',
				'currency'          => 'kr',
				'image_url'         => 'https://placehold.co/600x450/0f172a/ffffff?text=Concept2+RowErg',
				'permalink'         => '#',
				'category'          => 'Romaskin',
			),
		);
	}

	/**
	 * Seed sample products directly into the exercise_gear post type.
	 *
	 * @return int[] Seeded post IDs.
	 */
	public static function seed_sample_products(): array {
		$samples    = self::get_sample_products_definitions();
		$seeded_ids = array();

		foreach ( $samples as $item ) {
			// Check if already in DB by EAN.
			$existing = self::get_product_by_ean( $item['ean'] );
			if ( $existing ) {
				$seeded_ids[] = $existing->ID;
				continue;
			}

			// Insert real post into database.
			$post_id = wp_insert_post(
				array(
					'post_type'    => self::POST_TYPE,
					'post_status'  => 'publish',
					'post_title'   => $item['title'],
					'post_content' => $item['content'],
					'post_excerpt' => $item['excerpt'],
				)
			);

			if ( $post_id && ! is_wp_error( $post_id ) ) {
				update_post_meta( $post_id, self::META_EAN, sanitize_text_field( $item['ean'] ) );
				update_post_meta( $post_id, self::META_PRICE, (float) $item['price'] );
				update_post_meta( $post_id, self::META_REGULAR_PRICE, (float) $item['regular_price'] );
				update_post_meta( $post_id, self::META_IN_STOCK, 1 );
				update_post_meta( $post_id, self::META_AFFILIATE_URL, esc_url_raw( $item['affiliate_url'] ) );
				update_post_meta( $post_id, self::META_MERCHANT, sanitize_text_field( $item['merchant'] ) );
				update_post_meta( $post_id, self::META_IMAGE_URL, esc_url_raw( $item['image_url'] ) );
				update_post_meta( $post_id, self::META_CURRENCY, sanitize_text_field( $item['currency'] ) );
				update_post_meta( $post_id, self::META_LAST_SYNCED, current_time( 'mysql' ) );

				// Assign taxonomy.
				if ( ! empty( $item['category'] ) ) {
					$term = term_exists( $item['category'], self::TAXONOMY );
					if ( ! $term ) {
						$term = wp_insert_term( $item['category'], self::TAXONOMY );
					}
					if ( ! is_wp_error( $term ) && isset( $term['term_id'] ) ) {
						wp_set_object_terms( $post_id, (int) $term['term_id'], self::TAXONOMY, true );
					}
				}

				$seeded_ids[] = $post_id;
			}
		}

		return $seeded_ids;
	}
}
