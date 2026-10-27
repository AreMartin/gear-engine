<?php
/**
 * Settings API Module for Gear Affiliate Engine.
 *
 * @package GearEngine
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

/**
 * Class GAE_Settings
 *
 * Handles the administrative dashboard settings page under Settings > Gear Engine.
 */
class GAE_Settings {

	/**
	 * Settings page slug.
	 */
	public const PAGE_SLUG = 'gear-affiliate-engine';

	/**
	 * Option group name.
	 */
	public const OPTION_GROUP = 'gae_settings_group';

	/**
	 * Constructor.
	 */
	public function __construct() {
		add_action( 'admin_menu', array( $this, 'register_settings_page' ) );
		add_action( 'admin_init', array( $this, 'register_settings' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_admin_assets' ) );
		add_action( 'wp_ajax_gae_trigger_manual_sync', array( $this, 'ajax_manual_sync' ) );
		add_action( 'wp_ajax_gae_refresh_gemini_models', array( $this, 'ajax_refresh_models' ) );
		add_action( 'wp_ajax_gae_clear_all_products', array( $this, 'ajax_clear_products' ) );
		add_action( 'wp_ajax_gae_save_dashboard_filters', array( $this, 'ajax_save_filters' ) );
		add_action( 'wp_ajax_gae_test_feed_url', array( $this, 'ajax_test_feed_url' ) );
	}

	/**
	 * Register the dashboard settings page under Settings > Gear Engine.
	 *
	 * @return void
	 */
	public function register_settings_page(): void {
		add_options_page(
			__( 'Gear Affiliate Engine Settings', 'gear-engine' ),
			__( 'Gear Engine', 'gear-engine' ),
			'manage_options',
			self::PAGE_SLUG,
			array( $this, 'render_settings_page' )
		);
	}

	/**
	 * Register settings, sections, and fields using the WordPress Settings API.
	 *
	 * @return void
	 */
	public function register_settings(): void {
		// 1. Feed URLs section (5-Slot Multi-Partner Card).
		add_settings_section(
			'gae_section_feeds',
			'',
			'__return_empty_string',
			self::PAGE_SLUG
		);

		register_setting(
			self::OPTION_GROUP,
			'gae_feeds',
			array(
				'type'              => 'array',
				'sanitize_callback' => array( $this, 'sanitize_feeds_table' ),
				'default'           => array(),
			)
		);

		add_settings_field(
			'gae_feeds_table',
			'',
			array( $this, 'render_feeds_table' ),
			self::PAGE_SLUG,
			'gae_section_feeds',
			array( 'class' => 'gae-feeds-table-row' )
		);

		// Keep legacy single options registered for backward compatibility.
		register_setting(
			self::OPTION_GROUP,
			'gae_adtraction_url',
			array(
				'type'              => 'string',
				'sanitize_callback' => array( $this, 'sanitize_feed_url' ),
				'default'           => '',
			)
		);

		register_setting(
			self::OPTION_GROUP,
			'gae_tradetracker_url',
			array(
				'type'              => 'string',
				'sanitize_callback' => array( $this, 'sanitize_feed_url' ),
				'default'           => '',
			)
		);

		// 2. Advanced Ingest Rules & Limits section.
		add_settings_section(
			'gae_section_filters',
			__( 'Advanced Ingest Rules & Limits', 'gear-engine' ),
			array( $this, 'render_filters_section_intro' ),
			self::PAGE_SLUG
		);

		// Core filter settings (registered for options persistence; UI is rendered in the interactive filter panel above).
		register_setting( self::OPTION_GROUP, 'gae_min_price', array( 'type' => 'number', 'sanitize_callback' => array( $this, 'sanitize_price' ), 'default' => 0 ) );
		register_setting( self::OPTION_GROUP, 'gae_max_price', array( 'type' => 'number', 'sanitize_callback' => array( $this, 'sanitize_price' ), 'default' => 0 ) );
		register_setting( self::OPTION_GROUP, 'gae_min_discount_pct', array( 'type' => 'number', 'sanitize_callback' => array( $this, 'sanitize_price' ), 'default' => 0 ) );
		register_setting( self::OPTION_GROUP, 'gae_only_in_stock', array( 'type' => 'boolean', 'sanitize_callback' => 'rest_sanitize_boolean', 'default' => false ) );
		register_setting( self::OPTION_GROUP, 'gae_max_items_per_sync', array( 'type' => 'integer', 'sanitize_callback' => 'absint', 'default' => 100 ) );
		register_setting( self::OPTION_GROUP, 'gae_allowed_categories', array( 'type' => 'string', 'sanitize_callback' => 'sanitize_textarea_field', 'default' => 'Tredemølle, Romaskin, Spinningsykkel, Vektstang, Vektvest, Treningsbenk, Crosstrainer, Styrkepakke' ) );
		register_setting( self::OPTION_GROUP, 'gae_excluded_keywords', array( 'type' => 'string', 'sanitize_callback' => 'sanitize_textarea_field', 'default' => 'reservedel, bruksanvisning, brukermanual, reservedeler, kabel, skrue, mutter, smøremiddel, underlagsmatte, sokker, drikkeflaske, plakat' ) );

		// Total Products Limit in Catalog (Catalog Cap).
		register_setting(
			self::OPTION_GROUP,
			'gae_max_total_products',
			array(
				'type'              => 'integer',
				'sanitize_callback' => 'absint',
				'default'           => 100,
			)
		);

		add_settings_field(
			'gae_max_total_products',
			__( 'Total Products Limit (Catalog Cap)', 'gear-engine' ),
			array( $this, 'render_field_text' ),
			self::PAGE_SLUG,
			'gae_section_filters',
			array(
				'id'          => 'gae_max_total_products',
				'type'        => 'number',
				'step'        => '1',
				'description' => __( 'Maximum total number of exercise gear products to store in the catalog (default 100). Once reached, no new products will be imported (existing products can still update prices). Set to 0 for unlimited.', 'gear-engine' ),
			)
		);

		// Max Feed Items to Scan per Run.
		register_setting(
			self::OPTION_GROUP,
			'gae_max_scan_items',
			array(
				'type'              => 'integer',
				'sanitize_callback' => 'absint',
				'default'           => 5000,
			)
		);

		add_settings_field(
			'gae_max_scan_items',
			__( 'Max Feed Items to Scan per Run', 'gear-engine' ),
			array( $this, 'render_field_text' ),
			self::PAGE_SLUG,
			'gae_section_filters',
			array(
				'id'          => 'gae_max_scan_items',
				'type'        => 'number',
				'step'        => '100',
				'description' => __( 'Maximum number of items to scan from each feed per sync run (default 5000). Protects against server timeouts on massive merchant feeds. Set to 0 for unlimited.', 'gear-engine' ),
			)
		);

		// Allowed Merchants Whitelist.
		register_setting(
			self::OPTION_GROUP,
			'gae_allowed_merchants',
			array(
				'type'              => 'string',
				'sanitize_callback' => 'sanitize_textarea_field',
				'default'           => '',
			)
		);

		add_settings_field(
			'gae_allowed_merchants',
			__( 'Allowed Merchants (Whitelist)', 'gear-engine' ),
			array( $this, 'render_field_textarea' ),
			self::PAGE_SLUG,
			'gae_section_filters',
			array(
				'id'          => 'gae_allowed_merchants',
				'rows'        => 2,
				'description' => __( 'Comma or newline separated merchant/brand names (e.g. GymGrossisten, XXL, Treningspartner). Leave empty to allow all merchants.', 'gear-engine' ),
			)
		);

		// Excluded Merchants Blacklist.
		register_setting(
			self::OPTION_GROUP,
			'gae_excluded_merchants',
			array(
				'type'              => 'string',
				'sanitize_callback' => 'sanitize_textarea_field',
				'default'           => '',
			)
		);

		add_settings_field(
			'gae_excluded_merchants',
			__( 'Excluded Merchants (Blacklist)', 'gear-engine' ),
			array( $this, 'render_field_textarea' ),
			self::PAGE_SLUG,
			'gae_section_filters',
			array(
				'id'          => 'gae_excluded_merchants',
				'rows'        => 2,
				'description' => __( 'Comma or newline separated merchant names to discard during sync.', 'gear-engine' ),
			)
		);

		// Required Keywords.
		register_setting(
			self::OPTION_GROUP,
			'gae_required_keywords',
			array(
				'type'              => 'string',
				'sanitize_callback' => 'sanitize_textarea_field',
				'default'           => '',
			)
		);

		add_settings_field(
			'gae_required_keywords',
			__( 'Required Keywords (Must Match At Least One)', 'gear-engine' ),
			array( $this, 'render_field_textarea' ),
			self::PAGE_SLUG,
			'gae_section_filters',
			array(
				'id'          => 'gae_required_keywords',
				'rows'        => 2,
				'description' => __( 'Comma or newline separated keywords. If set, products must contain at least one of these keywords in the title or description. Leave empty to allow all.', 'gear-engine' ),
			)
		);

		// 3. Google AI Studio configuration.
		add_settings_section(
			'gae_section_ai',
			__( 'Google AI Studio Configuration', 'gear-engine' ),
			array( $this, 'render_ai_section_intro' ),
			self::PAGE_SLUG
		);

		register_setting(
			self::OPTION_GROUP,
			'gae_google_ai_key',
			array(
				'type'              => 'string',
				'sanitize_callback' => 'sanitize_text_field',
				'default'           => '',
			)
		);

		add_settings_field(
			'gae_google_ai_key',
			__( 'Google AI Studio API Key', 'gear-engine' ),
			array( $this, 'render_field_text' ),
			self::PAGE_SLUG,
			'gae_section_ai',
			array(
				'id'          => 'gae_google_ai_key',
				'type'        => 'password',
				'description' => __( 'Your Gemini API key from Google AI Studio (ai.google.dev).', 'gear-engine' ),
			)
		);

		register_setting(
			self::OPTION_GROUP,
			'gae_ai_model',
			array(
				'type'              => 'string',
				'sanitize_callback' => 'sanitize_text_field',
				'default'           => 'gemini-3.8-flash',
			)
		);

		add_settings_field(
			'gae_ai_model',
			__( 'Google AI Model', 'gear-engine' ),
			array( $this, 'render_field_ai_model' ),
			self::PAGE_SLUG,
			'gae_section_ai',
			array(
				'id'          => 'gae_ai_model',
				'description' => __( 'Select or enter the Gemini model for personalized AI equipment pitches.', 'gear-engine' ),
			)
		);
	}

	/**
	 * Sanitize feed URL, ensuring HTML entities like &amp; are decoded and whitespace trimmed.
	 *
	 * @param mixed $value Input value.
	 * @return string
	 */
	public function sanitize_feed_url( $value ): string {
		if ( ! is_scalar( $value ) ) {
			return '';
		}
		$url = html_entity_decode( trim( (string) $value ), ENT_QUOTES, 'UTF-8' );
		$url = str_replace( '&amp;', '&', $url );
		return esc_url_raw( $url );
	}

	/**
	 * Sanitize price value.
	 *
	 * @param mixed $value Input value.
	 * @return float
	 */
	public function sanitize_price( $value ): float {
		return max( 0.0, floatval( $value ) );
	}

	/**
	 * Retrieve the 5 feed slots (padded to 5).
	 *
	 * Migrates legacy single options if gae_feeds is empty.
	 *
	 * @return array<int, array{name: string, network: string, url: string, enabled: int}>
	 */
	public static function get_feed_slots(): array {
		$saved = get_option( 'gae_feeds', array() );

		if ( empty( $saved ) || ! is_array( $saved ) ) {
			$legacy_adtraction   = (string) get_option( 'gae_adtraction_url', '' );
			$legacy_tradetracker = (string) get_option( 'gae_tradetracker_url', '' );

			$saved = array();
			if ( ! empty( $legacy_adtraction ) ) {
				$saved[] = array(
					'name'    => 'Adtraction Partner 1',
					'network' => 'Adtraction',
					'url'     => $legacy_adtraction,
					'enabled' => 1,
				);
			}
			if ( ! empty( $legacy_tradetracker ) ) {
				$saved[] = array(
					'name'    => 'TradeTracker Partner 1',
					'network' => 'TradeTracker',
					'url'     => $legacy_tradetracker,
					'enabled' => 1,
				);
			}
		}

		$slots = array();
		for ( $i = 0; $i < 5; $i++ ) {
			if ( isset( $saved[ $i ] ) && is_array( $saved[ $i ] ) ) {
				$slots[ $i ] = array(
					'name'    => (string) ( $saved[ $i ]['name'] ?? '' ),
					'network' => (string) ( $saved[ $i ]['network'] ?? 'Adtraction' ),
					'url'     => (string) ( $saved[ $i ]['url'] ?? '' ),
					'enabled' => ! empty( $saved[ $i ]['enabled'] ) ? 1 : 0,
				);
			} else {
				$slots[ $i ] = array(
					'name'    => '',
					'network' => 'Adtraction',
					'url'     => '',
					'enabled' => 0,
				);
			}
		}

		return $slots;
	}

	/**
	 * Sanitize the 5-slot feeds table array.
	 *
	 * @param mixed $input Raw POST array.
	 * @return array<int, array{name: string, network: string, url: string, enabled: int}>
	 */
	public function sanitize_feeds_table( $input ): array {
		if ( ! is_array( $input ) ) {
			return array();
		}

		$sanitized = array();
		$count     = 0;

		foreach ( $input as $slot ) {
			if ( $count >= 5 || ! is_array( $slot ) ) {
				break;
			}

			$url     = $this->sanitize_feed_url( (string) ( $slot['url'] ?? '' ) );
			$name    = sanitize_text_field( (string) ( $slot['name'] ?? '' ) );
			$network = sanitize_text_field( (string) ( $slot['network'] ?? 'Adtraction' ) );
			$enabled = ! empty( $slot['enabled'] ) ? 1 : 0;

			if ( ! in_array( $network, array( 'Adtraction', 'TradeTracker', 'Generic' ), true ) ) {
				$network = 'Adtraction';
			}

			$sanitized[] = array(
				'name'    => $name,
				'network' => $network,
				'url'     => $url,
				'enabled' => $enabled,
			);

			$count++;
		}

		return $sanitized;
	}

	/**
	 * Render the 5-slot feeds table as a modern full-width card.
	 *
	 * @return void
	 */
	public function render_feeds_table(): void {
		$slots        = self::get_feed_slots();
		$active_count = 0;
		$placeholders = array(
			'e.g. Gymgrossisten (Adtraction)',
			'e.g. XXL Sport (Adtraction)',
			'e.g. Treningspartner (TradeTracker)',
			'e.g. Outnorth (Adtraction)',
			'e.g. Intersport (Generic)',
		);

		foreach ( $slots as $slot ) {
			if ( ! empty( $slot['enabled'] ) && ! empty( $slot['url'] ) ) {
				$active_count++;
			}
		}

		$visible_count = 0;
		foreach ( $slots as $i => $slot ) {
			$has_data   = ! empty( $slot['url'] ) || ! empty( $slot['name'] );
			$is_visible = ( 0 === $i ) || $has_data || ! empty( $slot['enabled'] );
			if ( $is_visible ) {
				$visible_count++;
			}
		}
		?>
		<div class="gae-admin-card gae-feeds-card">
			<div class="gae-feeds-card-header">
				<div class="gae-feeds-card-title">
					<span class="dashicons dashicons-rss"></span>
					<div>
						<h3><?php esc_html_e( 'Affiliate Partner Feeds (Up to 5 Feeds)', 'gear-engine' ); ?></h3>
						<p class="description">
							<?php esc_html_e( 'Configure up to 5 partner product feeds. Toggle feeds active or paused, test connectivity, and auto-detect networks.', 'gear-engine' ); ?>
						</p>
					</div>
				</div>
				<div class="gae-feeds-card-badge">
					<span class="gae-feed-active-counter" id="gae-feed-active-badge">
						<?php printf( esc_html__( '%d / %d Active', 'gear-engine' ), $active_count, count( $slots ) ); ?>
					</span>
				</div>
			</div>

			<div class="gae-feeds-table-wrap">
				<table class="widefat fixed gae-feeds-table">
					<thead>
						<tr>
							<th style="width: 120px;"><?php esc_html_e( 'Status', 'gear-engine' ); ?></th>
							<th style="width: 220px;"><?php esc_html_e( 'Partner Name', 'gear-engine' ); ?></th>
							<th style="width: 140px;"><?php esc_html_e( 'Network', 'gear-engine' ); ?></th>
							<th><?php esc_html_e( 'Feed URL (XML, JSON, GZIP)', 'gear-engine' ); ?></th>
							<th style="width: 130px; text-align: right;"><?php esc_html_e( 'Actions', 'gear-engine' ); ?></th>
						</tr>
					</thead>
					<tbody id="gae-feeds-tbody">
						<?php
						foreach ( $slots as $i => $slot ) :
							$has_data   = ! empty( $slot['url'] ) || ! empty( $slot['name'] );
							$is_visible = ( 0 === $i ) || $has_data || ! empty( $slot['enabled'] );
							$row_class  = 'gae-feed-row' . ( ! $is_visible ? ' gae-slot-hidden' : '' ) . ( empty( $slot['enabled'] ) ? ' is-paused' : '' );
							?>
							<tr class="<?php echo esc_attr( $row_class ); ?>" data-slot="<?php echo (int) $i; ?>">
								<td style="vertical-align: middle;">
									<div class="gae-switch-wrap">
										<label class="gae-switch" title="<?php esc_attr_e( 'Toggle Feed Active/Paused', 'gear-engine' ); ?>">
											<input type="checkbox" name="gae_feeds[<?php echo (int) $i; ?>][enabled]" class="gae-feed-enabled-toggle" value="1" <?php checked( $slot['enabled'], 1 ); ?> />
											<span class="gae-slider"></span>
										</label>
										<span class="gae-status-pill <?php echo ! empty( $slot['enabled'] ) ? 'is-active' : 'is-paused'; ?>">
											<?php echo ! empty( $slot['enabled'] ) ? esc_html__( 'Active', 'gear-engine' ) : esc_html__( 'Paused', 'gear-engine' ); ?>
										</span>
									</div>
								</td>
								<td>
									<input type="text" name="gae_feeds[<?php echo (int) $i; ?>][name]" value="<?php echo esc_attr( $slot['name'] ); ?>" placeholder="<?php echo esc_attr( $placeholders[ $i ] ?? sprintf( 'Feed #%d', $i + 1 ) ); ?>" class="regular-text gae-feed-name-input" style="width: 100%;" />
								</td>
								<td>
									<select name="gae_feeds[<?php echo (int) $i; ?>][network]" class="gae-network-select" style="width: 100%;">
										<option value="Adtraction" <?php selected( $slot['network'], 'Adtraction' ); ?>>Adtraction</option>
										<option value="TradeTracker" <?php selected( $slot['network'], 'TradeTracker' ); ?>>TradeTracker</option>
										<option value="Generic" <?php selected( $slot['network'], 'Generic' ); ?>><?php esc_html_e( 'Generic / Other', 'gear-engine' ); ?></option>
									</select>
								</td>
								<td>
									<div class="gae-url-col">
										<input type="url" name="gae_feeds[<?php echo (int) $i; ?>][url]" value="<?php echo esc_attr( $slot['url'] ); ?>" placeholder="https://..." class="large-text gae-feed-url-input" style="width: 100%;" />
										<div class="gae-feed-test-result"></div>
									</div>
								</td>
								<td style="text-align: right; vertical-align: middle;">
									<div class="gae-row-actions">
										<button type="button" class="button button-small gae-test-feed-btn" title="<?php esc_attr_e( 'Test connection to this feed URL', 'gear-engine' ); ?>">
											<span class="dashicons dashicons-rest-api" style="font-size:14px; width:14px; height:14px; vertical-align:middle;"></span>
											<?php esc_html_e( 'Test', 'gear-engine' ); ?>
										</button>
										<button type="button" class="button button-small button-link-delete gae-remove-feed-btn" title="<?php esc_attr_e( 'Clear / remove this feed slot', 'gear-engine' ); ?>">
											<span class="dashicons dashicons-trash" style="font-size:14px; width:14px; height:14px; vertical-align:middle;"></span>
										</button>
									</div>
								</td>
							</tr>
						<?php endforeach; ?>
					</tbody>
				</table>
			</div>

			<div class="gae-feeds-card-footer">
				<button type="button" id="gae-add-feed-btn" class="button button-secondary" <?php disabled( $visible_count >= 5 ); ?>>
					<span class="dashicons dashicons-plus-alt2"></span>
					<span class="gae-add-feed-text">
						<?php
						$remaining = max( 0, 5 - $visible_count );
						if ( $remaining > 0 ) {
							printf( esc_html__( 'Add Partner Feed (%d slots remaining)', 'gear-engine' ), $remaining );
						} else {
							esc_html_e( 'All 5 Slots In Use', 'gear-engine' );
						}
						?>
					</span>
				</button>
				<p class="description" style="margin: 0; font-size: 12px; color: #64748b;">
					<?php esc_html_e( 'Paste an Adtraction or TradeTracker URL to auto-detect its network. Feeds marked "Active" are automatically refreshed during scheduled daily cron syncs.', 'gear-engine' ); ?>
				</p>
			</div>
		</div>
		<?php
	}

	/**
	 * Section intro: Feeds.
	 */
	public function render_feeds_section_intro(): void {
		// Handled directly inside render_feeds_table card header.
	}

	/**
	 * AJAX handler to test connection and probe format of a feed URL.
	 *
	 * @return void
	 */
	public function ajax_test_feed_url(): void {
		check_ajax_referer( 'gae_manual_sync_nonce', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'Insufficient permissions.', 'gear-engine' ) ), 403 );
		}

		$url = sanitize_text_field( wp_unslash( $_POST['url'] ?? '' ) );
		$url = html_entity_decode( trim( $url ), ENT_QUOTES, 'UTF-8' );
		$url = str_replace( '&amp;', '&', $url );

		if ( empty( $url ) || ! preg_match( '#^https?://#i', $url ) ) {
			wp_send_json_error( array( 'message' => __( 'Please enter a valid HTTP or HTTPS feed URL.', 'gear-engine' ) ) );
			return;
		}

		// Adtraction check
		if ( false !== stripos( $url, 'adtraction.com' ) ) {
			if ( preg_match( '/[?&]zip=1/i', $url ) ) {
				$url = preg_replace( '/([?&])zip=1/i', '${1}gzip=1', $url );
			} elseif ( ! preg_match( '/[?&](?:gzip|zip)=/i', $url ) ) {
				$url .= ( false !== strpos( $url, '?' ) ? '&' : '?' ) . 'gzip=1';
			}
		}

		// Perform a lightweight probe request (first 8KB) using range header
		$response = wp_remote_get(
			$url,
			array(
				'timeout'    => 15,
				'sslverify'  => false,
				'headers'    => array( 'Range' => 'bytes=0-8191' ),
				'user-agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36',
			)
		);

		if ( is_wp_error( $response ) ) {
			wp_send_json_error( array( 'message' => sprintf( __( 'Connection failed: %s', 'gear-engine' ), $response->get_error_message() ) ) );
			return;
		}

		$code = wp_remote_retrieve_response_code( $response );
		if ( 200 !== (int) $code && 206 !== (int) $code ) {
			wp_send_json_error( array( 'message' => sprintf( __( 'HTTP Error %d returned by feed server.', 'gear-engine' ), $code ) ) );
			return;
		}

		$body   = wp_remote_retrieve_body( $response );
		$is_gz  = ( strlen( $body ) >= 2 && "\x1f\x8b" === substr( $body, 0, 2 ) );
		$format = 'XML';
		$sample = $body;

		if ( $is_gz && function_exists( 'gzdecode' ) ) {
			$uncompressed = @gzdecode( $body );
			if ( false !== $uncompressed && '' !== trim( $uncompressed ) ) {
				$sample = $uncompressed;
			}
		}

		$sample_trimmed = ltrim( $sample );
		if ( ! empty( $sample_trimmed ) && in_array( $sample_trimmed[0], array( '{', '[' ), true ) ) {
			$format = 'JSON';
		}

		$info = sprintf( 'HTTP %d · %s%s (Connected)', $code, $format, $is_gz ? ' GZIP' : '' );
		wp_send_json_success( array( 'message' => $info, 'format' => $format, 'is_gzip' => $is_gz ) );
	}

	/**
	 * Section intro: Filters.
	 */
	public function render_filters_section_intro(): void {
		echo '<p class="description">' . esc_html__( 'Configure catalog limits and merchant filtering rules. Product categories, prices, stock requirements, and blocked keywords are managed in the interactive filter panel above.', 'gear-engine' ) . '</p>';
	}

	/**
	 * Section intro: AI.
	 */
	public function render_ai_section_intro(): void {
		echo '<p class="description">' . esc_html__( 'Configures Google AI Studio (Gemini) API for the "Utstyrsvelger" quiz and product recommendation pitches. Responses are cached with multi-variant 30-day transient caching to minimize API costs.', 'gear-engine' ) . '</p>';
	}

	/**
	 * Render standard text/password/number field.
	 *
	 * @param array<string, mixed> $args Field arguments.
	 */
	public function render_field_text( array $args ): void {
		$id          = $args['id'];
		$type        = $args['type'] ?? 'text';
		$step        = $args['step'] ?? null;
		$value       = get_option( $id, '' );
		$description = $args['description'] ?? '';

		$step_attr = $step ? ' step="' . esc_attr( $step ) . '"' : '';

		printf(
			'<input type="%s" id="%s" name="%s" value="%s" class="regular-text"%s />',
			esc_attr( $type ),
			esc_attr( $id ),
			esc_attr( $id ),
			esc_attr( (string) $value ),
			$step_attr // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		);

		if ( ! empty( $description ) ) {
			printf( '<p class="description">%s</p>', esc_html( $description ) );
		}
	}

	/**
	 * Render textarea field.
	 *
	 * @param array<string, mixed> $args Field arguments.
	 */
	public function render_field_textarea( array $args ): void {
		$id          = $args['id'];
		$rows        = $args['rows'] ?? 4;
		$value       = get_option( $id, '' );
		$description = $args['description'] ?? '';

		printf(
			'<textarea id="%s" name="%s" rows="%d" class="large-text code">%s</textarea>',
			esc_attr( $id ),
			esc_attr( $id ),
			absint( $rows ),
			esc_textarea( (string) $value )
		);

		if ( ! empty( $description ) ) {
			printf( '<p class="description">%s</p>', esc_html( $description ) );
		}
	}

	/**
	 * Render checkbox field.
	 *
	 * @param array<string, mixed> $args Field arguments.
	 */
	public function render_field_checkbox( array $args ): void {
		$id          = $args['id'];
		$value       = (bool) get_option( $id, false );
		$label       = $args['label'] ?? '';
		$description = $args['description'] ?? '';

		printf(
			'<label for="%s"><input type="checkbox" id="%s" name="%s" value="1"%s /> %s</label>',
			esc_attr( $id ),
			esc_attr( $id ),
			esc_attr( $id ),
			checked( $value, true, false ),
			esc_html( $label )
		);

		if ( ! empty( $description ) ) {
			printf( '<p class="description">%s</p>', esc_html( $description ) );
		}
	}

	/**
	 * Render AI model selector with live Google AI Studio integration and custom option.
	 *
	 * @param array<string, mixed> $args Field arguments.
	 */
	public function render_field_ai_model( array $args ): void {
		$id          = $args['id'];
		$value       = get_option( $id, 'gemini-3.8-flash' );
		$description = $args['description'] ?? '';

		$models    = GAE_AI_API::get_available_models();
		$is_custom = ! array_key_exists( $value, $models ) && ! empty( $value );

		echo '<div style="display: flex; align-items: center; gap: 8px; flex-wrap: wrap;">';
		echo '<select id="' . esc_attr( $id ) . '_select" onchange="gaeToggleModelInput(this.value);" class="regular-text">';
		foreach ( $models as $model_key => $model_label ) {
			printf(
				'<option value="%s"%s>%s</option>',
				esc_attr( $model_key ),
				selected( $value, $model_key, false ),
				esc_html( $model_label )
			);
		}
		printf(
			'<option value="custom"%s>%s</option>',
			selected( $is_custom, true, false ),
			esc_html__( 'Custom Model Name...', 'gear-engine' )
		);
		echo '</select>';

		echo '<button type="button" id="gae-refresh-models-btn" class="button button-secondary" title="' . esc_attr__( 'Query Google AI Studio for new and updated Gemini models', 'gear-engine' ) . '">';
		echo '<span class="dashicons dashicons-update" style="vertical-align: text-top; font-size: 16px; width: 16px; height: 16px;"></span> ';
		esc_html_e( 'Fetch Latest Models from Google AI', 'gear-engine' );
		echo '</button>';
		echo '<span id="gae-models-spinner" class="spinner" style="float:none; margin: 0 4px;"></span>';
		echo '<span id="gae-models-status" style="font-size: 13px; font-weight: 500;"></span>';
		echo '</div>';

		printf(
			'<input type="text" id="%s" name="%s" value="%s" class="regular-text" style="%s; margin-top: 6px;" placeholder="e.g. gemini-3.8-flash" />',
			esc_attr( $id ),
			esc_attr( $id ),
			esc_attr( (string) $value ),
			$is_custom ? 'display: block;' : 'display: none;'
		);

		if ( ! empty( $description ) ) {
			printf( '<p class="description">%s</p>', esc_html( $description ) );
		}
		echo '<p class="description" style="color: #64748b; margin-top: 4px;">' . esc_html__( 'The model list dynamically refreshes from Google AI Studio every 7 days (and during daily sync) to keep pace with new model releases and deprecations.', 'gear-engine' ) . '</p>';

		?>
		<script>
		function gaeToggleModelInput(val) {
			var input = document.getElementById('<?php echo esc_js( $id ); ?>');
			if (val === 'custom') {
				input.style.display = 'block';
				input.focus();
			} else {
				input.style.display = 'none';
				input.value = val;
			}
		}
		</script>
		<?php
	}

	/**
	 * Enqueue admin scripts & styles for settings page.
	 *
	 * @param string $hook_suffix Admin page hook.
	 * @return void
	 */
	public function enqueue_admin_assets( string $hook_suffix ): void {
		if ( 'settings_page_' . self::PAGE_SLUG !== $hook_suffix ) {
			return;
		}

		wp_enqueue_style(
			'gae-admin-style',
			GAE_PLUGIN_URL . 'assets/css/admin.css',
			array(),
			GAE_VERSION
		);

		wp_enqueue_script(
			'gae-admin-script',
			GAE_PLUGIN_URL . 'assets/js/admin.js',
			array( 'jquery' ),
			GAE_VERSION,
			true
		);

		wp_localize_script(
			'gae-admin-script',
			'gaeAdminData',
			array(
				'ajaxUrl'            => admin_url( 'admin-ajax.php' ),
				'syncNonce'          => wp_create_nonce( 'gae_manual_sync_nonce' ),
				'refreshModelsNonce' => wp_create_nonce( 'gae_refresh_models_nonce' ),
				'allowedCategories'  => (string) get_option( 'gae_allowed_categories', 'Tredemølle, Romaskin, Spinningsykkel, Vektstang, Vektvest, Treningsbenk, Crosstrainer, Styrkepakke' ),
				'excludedKeywords'   => (string) get_option( 'gae_excluded_keywords', 'reservedel, bruksanvisning, brukermanual, reservedeler, kabel, skrue, mutter, smøremiddel, underlagsmatte, sokker, drikkeflaske, plakat' ),
				'maxItemsPerSync'    => (int) get_option( 'gae_max_items_per_sync', 100 ),
				'maxTotalProducts'   => (int) get_option( 'gae_max_total_products', 100 ),
				'strings'            => array(
					'syncing'        => __( 'Syncing feeds in streaming mode... Please wait.', 'gear-engine' ),
					'syncSuccess'    => __( 'Sync completed successfully!', 'gear-engine' ),
					'syncFailed'     => __( 'Sync encountered an error.', 'gear-engine' ),
					'fetchingModels' => __( 'Fetching live models from Google AI Studio...', 'gear-engine' ),
					'fetchSuccess'   => __( 'Models updated successfully!', 'gear-engine' ),
					'fetchFailed'    => __( 'Failed to fetch models. Verify your API key.', 'gear-engine' ),
					'confirmClear'   => __( 'Are you sure you want to delete ALL synced exercise gear products from your database? This cannot be undone.', 'gear-engine' ),
					'clearing'       => __( 'Clearing catalog products...', 'gear-engine' ),
					'clearSuccess'   => __( 'Catalog cleared successfully!', 'gear-engine' ),
					'clearFailed'    => __( 'Failed to clear catalog.', 'gear-engine' ),
					'savingFilters'  => __( 'Saving filter defaults...', 'gear-engine' ),
					'filtersSaved'   => __( 'Filters saved successfully!', 'gear-engine' ),
				),
			)
		);
	}

	/**
	 * Render the full settings page HTML.
	 *
	 * @return void
	 */
	public function render_settings_page(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Unauthorized user.', 'gear-engine' ), '', array( 'response' => 403 ) );
		}

		$total_gear   = wp_count_posts( 'exercise_gear' );
		$published    = $total_gear->publish ?? 0;
		$next_cron    = wp_next_scheduled( 'gae_daily_sync_event' );
		$last_report  = get_option( 'gae_last_sync_report', array() );
		$catalog_cap  = (int) get_option( 'gae_max_total_products', 100 );
		$sync_limit   = (int) get_option( 'gae_max_items_per_sync', 100 );
		$min_price    = (float) get_option( 'gae_min_price', 0 );
		$max_price    = (float) get_option( 'gae_max_price', 0 );
		$min_discount = (float) get_option( 'gae_min_discount_pct', 0 );
		$only_stock   = (bool) get_option( 'gae_only_in_stock', false );
		?>
		<div class="wrap gae-settings-wrap">
			<h1>
				<span class="dashicons dashicons-heart" style="font-size:32px; width:32px; height:32px; vertical-align:middle; margin-right:8px; color:#16a34a;"></span>
				<?php echo esc_html__( 'Gear Affiliate Engine - Headless PIM & AI Settings', 'gear-engine' ); ?>
			</h1>

			<!-- System Overview Card -->
			<div class="gae-admin-card gae-status-card">
				<div class="gae-status-grid">
					<div class="gae-status-item">
						<span class="gae-status-label"><?php esc_html_e( 'Total Products in PIM', 'gear-engine' ); ?></span>
						<span class="gae-status-value">
							<?php
							echo esc_html( number_format_i18n( $published ) );
							if ( $catalog_cap > 0 ) {
								echo ' <span style="font-size:13px; font-weight:normal; color:#64748b;">/ ' . esc_html( number_format_i18n( $catalog_cap ) ) . ' ' . esc_html__( 'max cap', 'gear-engine' ) . '</span>';
							}
							?>
						</span>
					</div>
					<div class="gae-status-item">
						<span class="gae-status-label"><?php esc_html_e( 'Next Daily WP-Cron Sync', 'gear-engine' ); ?></span>
						<span class="gae-status-value">
							<?php
							if ( $next_cron ) {
								echo esc_html( human_time_diff( time(), $next_cron ) . ' ' . __( 'from now', 'gear-engine' ) );
							} else {
								echo esc_html__( 'Not scheduled', 'gear-engine' );
							}
							?>
						</span>
					</div>
					<div class="gae-status-item">
						<span class="gae-status-label"><?php esc_html_e( 'Last Sync Activity', 'gear-engine' ); ?></span>
						<span class="gae-status-value">
							<?php
							if ( ! empty( $last_report['time'] ) ) {
								echo esc_html( human_time_diff( $last_report['time'] ) . ' ' . __( 'ago', 'gear-engine' ) );
							} else {
								echo esc_html__( 'Never synced', 'gear-engine' );
							}
							?>
						</span>
					</div>
				</div>

				<?php if ( ! empty( $last_report ) ) : ?>
					<div class="gae-sync-report-summary">
						<strong><?php esc_html_e( 'Last Sync Report:', 'gear-engine' ); ?></strong>
						<span><?php printf( esc_html__( 'Inserted: %d | Updated: %d | Skipped (Filtered): %d | Total Processed: %d', 'gear-engine' ), absint( $last_report['inserted'] ?? 0 ), absint( $last_report['updated'] ?? 0 ), absint( $last_report['skipped'] ?? 0 ), absint( $last_report['processed'] ?? 0 ) ); ?></span>
						<?php if ( ! empty( $last_report['errors'] ) ) : ?>
							<div class="gae-report-errors" style="color:#ef4444; margin-top:4px;">
								<?php echo esc_html( implode( ' | ', (array) $last_report['errors'] ) ); ?>
							</div>
						<?php endif; ?>
					</div>
				<?php endif; ?>

				<!-- User-Friendly Dashboard Controls for Ingest Limits & Filters -->
				<div class="gae-dashboard-controls">
					<div class="gae-controls-header">
						<h3 style="margin: 0 0 4px 0; font-size: 15px; color: #1e293b; display: flex; align-items: center;">
							<span class="dashicons dashicons-filter" style="margin-right: 6px; color: #16a34a; font-size: 20px; width: 20px; height: 20px;"></span>
							<?php esc_html_e( 'Quick Sync & Filter Controls', 'gear-engine' ); ?>
						</h3>
						<p class="description" style="margin: 0 0 16px 0; color: #64748b;">
							<?php esc_html_e( 'Configure product limits and filters directly in the dashboard before syncing to prevent timeouts or memory crashes on large affiliate feeds.', 'gear-engine' ); ?>
						</p>
					</div>

					<div class="gae-filter-grid">
						<!-- Control 1: Import Limits -->
						<div class="gae-filter-box">
							<label class="gae-filter-heading"><?php esc_html_e( '1. Import Limit (This Run)', 'gear-engine' ); ?></label>
							<div class="gae-limit-selector">
								<button type="button" class="button gae-limit-btn <?php echo ( 25 === $sync_limit ) ? 'is-active' : ''; ?>" data-limit="25">25</button>
								<button type="button" class="button gae-limit-btn <?php echo ( 50 === $sync_limit ) ? 'is-active' : ''; ?>" data-limit="50">50</button>
								<button type="button" class="button gae-limit-btn <?php echo ( 100 === $sync_limit ) ? 'is-active' : ''; ?>" data-limit="100">100</button>
								<button type="button" class="button gae-limit-btn <?php echo ( 250 === $sync_limit ) ? 'is-active' : ''; ?>" data-limit="250">250</button>
								<button type="button" class="button gae-limit-btn <?php echo ( 500 === $sync_limit ) ? 'is-active' : ''; ?>" data-limit="500">500</button>
								<button type="button" class="button gae-limit-btn <?php echo ( 0 === $sync_limit ) ? 'is-active' : ''; ?>" data-limit="0"><?php esc_html_e( 'All', 'gear-engine' ); ?></button>
								<input type="number" id="gae-quick-limit-input" value="<?php echo esc_attr( $sync_limit ); ?>" min="0" max="10000" class="small-text" title="<?php esc_attr_e( 'Custom product limit (0 = unlimited)', 'gear-engine' ); ?>" />
							</div>
							<p class="description" style="margin-top: 6px; font-size: 11px; color: #64748b;">
								<?php printf( esc_html__( 'Total Catalog Cap: %d products maximum. Sync stops when cap is reached.', 'gear-engine' ), $catalog_cap ); ?>
							</p>
						</div>

						<!-- Control 2: Feed Source Selection & In-Stock -->
						<div class="gae-filter-box">
							<label class="gae-filter-heading"><?php esc_html_e( '2. Source Feed & Stock Status', 'gear-engine' ); ?></label>
							<div class="gae-feed-radios" style="margin-bottom: 8px; display: flex; flex-wrap: wrap; gap: 10px;">
								<label><input type="radio" name="gae_quick_feed" value="all" checked /> <?php esc_html_e( 'All Active Feeds', 'gear-engine' ); ?></label>
								<?php
								$configured_slots = self::get_feed_slots();
								$active_slots_cnt = 0;
								foreach ( $configured_slots as $c_idx => $c_slot ) {
									if ( ! empty( $c_slot['enabled'] ) && ! empty( $c_slot['url'] ) ) {
										$active_slots_cnt++;
										$feed_label = ! empty( $c_slot['name'] ) ? $c_slot['name'] : sprintf( __( 'Feed #%d', 'gear-engine' ), (int) $c_idx + 1 );
										echo '<label><input type="radio" name="gae_quick_feed" value="' . esc_attr( $feed_label ) . '" /> ' . esc_html( $feed_label ) . '</label>';
									}
								}
								if ( 0 === $active_slots_cnt ) {
									echo '<label><input type="radio" name="gae_quick_feed" value="Adtraction" /> Adtraction</label>';
									echo '<label><input type="radio" name="gae_quick_feed" value="TradeTracker" /> TradeTracker</label>';
								}
								?>
							</div>
							<div style="padding-top: 6px; border-top: 1px solid #e2e8f0;">
								<label style="font-weight: 600; font-size: 13px; cursor: pointer; color: #1e293b;">
									<input type="checkbox" id="gae-quick-in-stock" value="1" <?php checked( $only_stock ); ?> />
									<?php esc_html_e( 'Only import in-stock products (skip out-of-stock)', 'gear-engine' ); ?>
								</label>
							</div>
						</div>
					</div>

					<!-- Control 3: Interactive Category Filter Pills -->
					<div class="gae-filter-box-full" style="margin-top: 14px;">
						<div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 8px;">
							<label class="gae-filter-heading" style="margin-bottom: 0;">
								<?php esc_html_e( '3. Category Filter (Click pills to toggle on/off)', 'gear-engine' ); ?>
							</label>
							<div class="gae-pill-actions">
								<button type="button" id="gae-select-all-cats" class="button button-small"><?php esc_html_e( 'Select All', 'gear-engine' ); ?></button>
								<button type="button" id="gae-clear-all-cats" class="button button-small"><?php esc_html_e( 'Deselect All', 'gear-engine' ); ?></button>
							</div>
						</div>
						<div id="gae-category-pills" class="gae-pills-wrap">
							<!-- Populated dynamically by admin.js from current settings -->
						</div>
						<div class="gae-add-pill-row" style="margin-top: 8px; display: flex; gap: 8px;">
							<input type="text" id="gae-new-cat-input" placeholder="<?php esc_attr_e( 'Add category keyword (e.g. Vektsett, Massasjepistol)...', 'gear-engine' ); ?>" class="regular-text" style="height: 30px; font-size: 12px; max-width: 320px;" />
							<button type="button" id="gae-add-cat-btn" class="button button-secondary"><?php esc_html_e( '+ Add Category', 'gear-engine' ); ?></button>
						</div>
					</div>

					<!-- Control 4: Quick Price Range & Blacklist -->
					<div class="gae-filter-grid" style="margin-top: 14px;">
						<div class="gae-filter-box">
							<label class="gae-filter-heading"><?php esc_html_e( '4. Price & Discount Thresholds (kr)', 'gear-engine' ); ?></label>
							<div style="display: flex; align-items: center; gap: 10px; flex-wrap: wrap;">
								<div>
									<span style="font-size: 11px; color: #64748b; display: block;"><?php esc_html_e( 'Min Price (kr)', 'gear-engine' ); ?></span>
									<input type="number" id="gae-quick-min-price" value="<?php echo esc_attr( $min_price ); ?>" min="0" step="100" style="width: 105px;" />
								</div>
								<div>
									<span style="font-size: 11px; color: #64748b; display: block;"><?php esc_html_e( 'Max Price (kr)', 'gear-engine' ); ?></span>
									<input type="number" id="gae-quick-max-price" value="<?php echo esc_attr( $max_price ); ?>" min="0" step="500" style="width: 105px;" />
								</div>
								<div>
									<span style="font-size: 11px; color: #64748b; display: block;"><?php esc_html_e( 'Min Discount %', 'gear-engine' ); ?></span>
									<input type="number" id="gae-quick-min-discount" value="<?php echo esc_attr( $min_discount ); ?>" min="0" max="99" style="width: 80px;" />
								</div>
							</div>
						</div>

						<div class="gae-filter-box">
							<label class="gae-filter-heading"><?php esc_html_e( '5. Blocked Junk Keywords (Removes parts/accessories)', 'gear-engine' ); ?></label>
							<div id="gae-keyword-chips" class="gae-chips-wrap">
								<!-- Populated dynamically by admin.js from current settings -->
							</div>
							<div style="margin-top: 8px; display: flex; gap: 6px;">
								<input type="text" id="gae-new-kw-input" placeholder="<?php esc_attr_e( 'Block keyword...', 'gear-engine' ); ?>" style="height: 28px; font-size: 12px; width: 140px;" />
								<button type="button" id="gae-add-kw-btn" class="button button-small"><?php esc_html_e( '+ Block', 'gear-engine' ); ?></button>
							</div>
						</div>
					</div>
				</div>

				<div class="gae-manual-actions">
					<div class="gae-action-buttons">
						<button type="button" id="gae-sync-now-btn" class="button button-primary">
							<span class="dashicons dashicons-update"></span>
							<span><?php esc_html_e( 'Run Streaming Sync Now', 'gear-engine' ); ?></span>
						</button>
						<button type="button" id="gae-save-filters-btn" class="button button-secondary">
							<span class="dashicons dashicons-saved"></span>
							<span><?php esc_html_e( 'Save Filters as Defaults', 'gear-engine' ); ?></span>
						</button>
						<span id="gae-sync-spinner" class="spinner" style="float:none; margin: 0 4px;"></span>
						<span id="gae-sync-status-msg" style="font-weight:600;"></span>
					</div>
					<div>
						<button type="button" id="gae-clear-products-btn" class="button button-link-delete">
							<span class="dashicons dashicons-trash"></span>
							<span><?php esc_html_e( 'Clear All Synced Products', 'gear-engine' ); ?></span>
						</button>
					</div>
				</div>
			</div>

			<form method="post" action="options.php">
				<?php
				settings_fields( self::OPTION_GROUP );
				?>
				<!-- Synced filter values from top interactive card -->
				<input type="hidden" id="gae_allowed_categories" name="gae_allowed_categories" value="<?php echo esc_attr( get_option( 'gae_allowed_categories', 'Tredemølle, Romaskin, Spinningsykkel, Vektstang, Vektvest, Treningsbenk, Crosstrainer, Styrkepakke' ) ); ?>" />
				<input type="hidden" id="gae_excluded_keywords" name="gae_excluded_keywords" value="<?php echo esc_attr( get_option( 'gae_excluded_keywords', 'reservedel, bruksanvisning, brukermanual, reservedeler, kabel, skrue, mutter, smøremiddel, underlagsmatte, sokker, drikkeflaske, plakat' ) ); ?>" />
				<input type="hidden" id="gae_min_price" name="gae_min_price" value="<?php echo esc_attr( get_option( 'gae_min_price', 0 ) ); ?>" />
				<input type="hidden" id="gae_max_price" name="gae_max_price" value="<?php echo esc_attr( get_option( 'gae_max_price', 0 ) ); ?>" />
				<input type="hidden" id="gae_min_discount_pct" name="gae_min_discount_pct" value="<?php echo esc_attr( get_option( 'gae_min_discount_pct', 0 ) ); ?>" />
				<input type="hidden" id="gae_only_in_stock" name="gae_only_in_stock" value="<?php echo get_option( 'gae_only_in_stock', false ) ? '1' : '0'; ?>" />
				<input type="hidden" id="gae_max_items_per_sync" name="gae_max_items_per_sync" value="<?php echo esc_attr( get_option( 'gae_max_items_per_sync', 100 ) ); ?>" />
				<?php
				do_settings_sections( self::PAGE_SLUG );
				submit_button( __( 'Save Gear Engine Settings', 'gear-engine' ) );
				?>
			</form>
		</div>
		<?php
	}

	/**
	 * AJAX handler for manual sync trigger from admin dashboard.
	 *
	 * @return void
	 */
	public function ajax_manual_sync(): void {
		check_ajax_referer( 'gae_manual_sync_nonce', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'Insufficient permissions.', 'gear-engine' ) ), 403 );
		}

		// Discard any output buffered before sync to prevent JSON corruption.
		while ( ob_get_level() > 0 ) {
			ob_end_clean();
		}

		// Instantiate sync engine and run sync.
		if ( ! class_exists( 'GAE_Sync_Engine' ) ) {
			require_once GAE_PLUGIN_DIR . 'includes/class-gae-sync-engine.php';
		}

		$sync_engine = new GAE_Sync_Engine();

		// Collect runtime filter overrides from POST.
		$override_rules = array();

		if ( isset( $_POST['max_items'] ) ) {
			$override_rules['max_items_per_sync'] = absint( $_POST['max_items'] );
			if ( ! empty( $_POST['save_filters'] ) ) {
				update_option( 'gae_max_items_per_sync', $override_rules['max_items_per_sync'] );
			}
		}

		if ( isset( $_POST['target_feed'] ) ) {
			$override_rules['target_feed'] = sanitize_text_field( wp_unslash( $_POST['target_feed'] ) );
		}

		if ( isset( $_POST['only_in_stock'] ) ) {
			$override_rules['only_in_stock'] = ! empty( $_POST['only_in_stock'] );
			if ( ! empty( $_POST['save_filters'] ) ) {
				update_option( 'gae_only_in_stock', $override_rules['only_in_stock'] );
			}
		}

		if ( isset( $_POST['min_price'] ) ) {
			$override_rules['min_price'] = (float) sanitize_text_field( wp_unslash( $_POST['min_price'] ) );
			if ( ! empty( $_POST['save_filters'] ) ) {
				update_option( 'gae_min_price', $override_rules['min_price'] );
			}
		}

		if ( isset( $_POST['max_price'] ) ) {
			$override_rules['max_price'] = (float) sanitize_text_field( wp_unslash( $_POST['max_price'] ) );
			if ( ! empty( $_POST['save_filters'] ) ) {
				update_option( 'gae_max_price', $override_rules['max_price'] );
			}
		}

		if ( isset( $_POST['min_discount_pct'] ) ) {
			$override_rules['min_discount_pct'] = (float) sanitize_text_field( wp_unslash( $_POST['min_discount_pct'] ) );
			if ( ! empty( $_POST['save_filters'] ) ) {
				update_option( 'gae_min_discount_pct', $override_rules['min_discount_pct'] );
			}
		}

		if ( isset( $_POST['categories'] ) ) {
			$raw_cats = sanitize_textarea_field( wp_unslash( $_POST['categories'] ) );
			$override_rules['allowed_categories'] = $sync_engine->parse_delimited_list( $raw_cats );
			if ( ! empty( $_POST['save_filters'] ) ) {
				update_option( 'gae_allowed_categories', $raw_cats );
			}
		}

		if ( isset( $_POST['excluded_keywords'] ) ) {
			$raw_kw = sanitize_textarea_field( wp_unslash( $_POST['excluded_keywords'] ) );
			$override_rules['excluded_keywords'] = $sync_engine->parse_delimited_list( $raw_kw );
			if ( ! empty( $_POST['save_filters'] ) ) {
				update_option( 'gae_excluded_keywords', $raw_kw );
			}
		}

		// Force unlock orphaned locks on manual admin click.
		$report = $sync_engine->run_sync( array(), true, $override_rules );

		if ( isset( $report['status'] ) && 'locked' === $report['status'] ) {
			wp_send_json_error( array( 'message' => $report['message'] ?? __( 'A sync process is already currently running.', 'gear-engine' ) ) );
			return;
		}

		if ( ! empty( $report['errors'] ) && 0 === ( $report['processed'] ?? 0 ) ) {
			wp_send_json_error( array( 'message' => implode( ' | ', $report['errors'] ) ) );
			return;
		}

		$cap_notice = '';
		if ( ! empty( $report['batch_capped'] ) ) {
			$cap_notice .= ' — ' . sprintf( __( 'Batch run cap reached (%d products).', 'gear-engine' ), (int) ( $override_rules['max_items_per_sync'] ?? get_option( 'gae_max_items_per_sync', 100 ) ) );
		}
		if ( ! empty( $report['total_capped'] ) ) {
			$cap_notice .= ' — ' . sprintf( __( 'Catalog limit reached (%d total products).', 'gear-engine' ), (int) get_option( 'gae_max_total_products', 100 ) );
		}
		if ( ! empty( $report['scan_capped'] ) ) {
			$cap_notice .= ' — ' . sprintf( __( 'Feed scan cap reached (%d items).', 'gear-engine' ), (int) get_option( 'gae_max_scan_items', 5000 ) );
		}
		$err_notice = ! empty( $report['errors'] ) ? ' — ' . sprintf( __( 'Errors: %s', 'gear-engine' ), implode( ' | ', $report['errors'] ) ) : '';

		// Guarantee clean buffer before JSON response.
		while ( ob_get_level() > 0 ) {
			ob_end_clean();
		}

		wp_send_json_success(
			array(
				'message' => sprintf(
					/* translators: 1: Processed count, 2: Inserted, 3: Updated, 4: Skipped, 5: Cap notice, 6: Error notice */
					__( 'Sync complete! Processed: %1$d (Inserted: %2$d, Updated: %3$d, Skipped: %4$d)%5$s%6$s', 'gear-engine' ),
					absint( $report['processed'] ?? 0 ),
					absint( $report['inserted'] ?? 0 ),
					absint( $report['updated'] ?? 0 ),
					absint( $report['skipped'] ?? 0 ),
					$cap_notice,
					$err_notice
				),
				'report'  => $report,
			)
		);
	}

	/**
	 * AJAX handler to save dashboard filters without running sync.
	 *
	 * @return void
	 */
	public function ajax_save_filters(): void {
		check_ajax_referer( 'gae_manual_sync_nonce', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'Insufficient permissions.', 'gear-engine' ) ), 403 );
		}

		if ( isset( $_POST['max_items'] ) ) {
			update_option( 'gae_max_items_per_sync', absint( $_POST['max_items'] ) );
		}
		if ( isset( $_POST['max_total_products'] ) ) {
			update_option( 'gae_max_total_products', absint( $_POST['max_total_products'] ) );
		}
		if ( isset( $_POST['only_in_stock'] ) ) {
			update_option( 'gae_only_in_stock', ! empty( $_POST['only_in_stock'] ) );
		}
		if ( isset( $_POST['min_price'] ) ) {
			update_option( 'gae_min_price', (float) sanitize_text_field( wp_unslash( $_POST['min_price'] ) ) );
		}
		if ( isset( $_POST['max_price'] ) ) {
			update_option( 'gae_max_price', (float) sanitize_text_field( wp_unslash( $_POST['max_price'] ) ) );
		}
		if ( isset( $_POST['min_discount_pct'] ) ) {
			update_option( 'gae_min_discount_pct', (float) sanitize_text_field( wp_unslash( $_POST['min_discount_pct'] ) ) );
		}
		if ( isset( $_POST['categories'] ) ) {
			update_option( 'gae_allowed_categories', sanitize_textarea_field( wp_unslash( $_POST['categories'] ) ) );
		}
		if ( isset( $_POST['excluded_keywords'] ) ) {
			update_option( 'gae_excluded_keywords', sanitize_textarea_field( wp_unslash( $_POST['excluded_keywords'] ) ) );
		}

		wp_send_json_success( array( 'message' => __( 'Filter preferences saved successfully!', 'gear-engine' ) ) );
	}

	/**
	 * AJAX handler to clear all synced exercise gear products in safe batches.
	 *
	 * @return void
	 */
	public function ajax_clear_products(): void {
		check_ajax_referer( 'gae_manual_sync_nonce', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'Insufficient permissions.', 'gear-engine' ) ), 403 );
		}

		if ( ! ini_get( 'safe_mode' ) && function_exists( 'set_time_limit' ) ) {
			@set_time_limit( 0 ); // phpcs:ignore Generic.PHP.NoSilencedErrors.Discouraged
		}

		// Defer term and comment counting to prevent quadratic slowdown on bulk deletes.
		if ( function_exists( 'wp_defer_term_counting' ) ) {
			wp_defer_term_counting( true );
		}
		if ( function_exists( 'wp_defer_comment_counting' ) ) {
			wp_defer_comment_counting( true );
		}

		$posts = get_posts(
			array(
				'post_type'      => GAE_CPT::POST_TYPE,
				'post_status'    => 'any',
				'posts_per_page' => -1,
				'fields'         => 'ids',
			)
		);

		$deleted_count = 0;
		if ( is_array( $posts ) ) {
			foreach ( $posts as $i => $pid ) {
				if ( wp_delete_post( (int) $pid, true ) ) {
					$deleted_count++;
				}
				if ( 0 === ( $i % 50 ) && function_exists( 'gc_collect_cycles' ) ) {
					gc_collect_cycles();
				}
			}
		}

		if ( function_exists( 'wp_defer_term_counting' ) ) {
			wp_defer_term_counting( false );
		}
		if ( function_exists( 'wp_defer_comment_counting' ) ) {
			wp_defer_comment_counting( false );
		}

		wp_send_json_success(
			array(
				'message' => sprintf(
					/* translators: %d: count of deleted products */
					__( 'Catalog cleared! %d products were removed.', 'gear-engine' ),
					$deleted_count
				),
				'deleted' => $deleted_count,
			)
		);
	}

	/**
	 * AJAX handler to refresh Gemini models list directly from Google AI Studio.
	 *
	 * @return void
	 */
	public function ajax_refresh_models(): void {
		check_ajax_referer( 'gae_refresh_models_nonce', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'Insufficient permissions.', 'gear-engine' ) ), 403 );
		}

		$models = GAE_AI_API::get_available_models( true );

		wp_send_json_success(
			array(
				'message' => sprintf(
					/* translators: %d: count of models */
					__( 'Updated! %d models available from Google AI.', 'gear-engine' ),
					count( $models )
				),
				'models'  => $models,
			)
		);
	}
}
