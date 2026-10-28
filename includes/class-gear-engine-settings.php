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
 * Class Gear_Engine_Settings
 *
 * Handles the administrative dashboard settings page under Settings > Gear Engine.
 */
class Gear_Engine_Settings {

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

		register_setting(
			self::OPTION_GROUP,
			'gae_google_places_key',
			array(
				'type'              => 'string',
				'sanitize_callback' => 'sanitize_text_field',
				'default'           => '',
			)
		);

		add_settings_field(
			'gae_google_places_key',
			__( 'Google Places API Key', 'gear-engine' ),
			array( $this, 'render_field_text' ),
			self::PAGE_SLUG,
			'gae_section_ai',
			array(
				'id'          => 'gae_google_places_key',
				'type'        => 'password',
				'description' => __( 'Optional API key for clinic and physiotherapist lookups. Defaults to built-in key if empty.', 'gear-engine' ),
			)
		);

		register_setting(
			self::OPTION_GROUP,
			'gae_physio_custom_prompt',
			array(
				'type'              => 'string',
				'sanitize_callback' => 'wp_kses_post',
				'default'           => '',
			)
		);

		add_settings_field(
			'gae_physio_custom_prompt',
			__( 'Custom Physio Advisor Prompt', 'gear-engine' ),
			array( $this, 'render_field_textarea' ),
			self::PAGE_SLUG,
			'gae_section_ai',
			array(
				'id'          => 'gae_physio_custom_prompt',
				'rows'        => 6,
				'description' => __( 'Optional override for the AI Physio Advisor system instructions. Leave blank to use default clinical protocol prompt.', 'gear-engine' ),
			)
		);

		register_setting(
			self::OPTION_GROUP,
			'gear_engine_disable_general_exercises',
			array(
				'type'              => 'boolean',
				'sanitize_callback' => 'rest_sanitize_boolean',
				'default'           => false,
			)
		);

		register_setting(
			self::OPTION_GROUP,
			'gear_engine_enable_protocols',
			array(
				'type'              => 'boolean',
				'sanitize_callback' => 'rest_sanitize_boolean',
				'default'           => false,
			)
		);

		add_settings_field(
			'gear_engine_enable_protocols',
			__( 'Clinical protocols (advanced)', 'gear-engine' ),
			array( $this, 'render_field_checkbox' ),
			self::PAGE_SLUG,
			'gae_section_ai',
			array(
				'id'          => 'gear_engine_enable_protocols',
				'label'       => __( 'Enable hand-picked protocols', 'gear-engine' ),
				'description' => __( 'Optional. Adds a Protocols screen where you can define fixed exercise sets per condition. Leave off to let the chat choose from rated exercises by body area and "Good for" conditions.', 'gear-engine' ),
			)
		);

		add_settings_field(
			'gear_engine_disable_general_exercises',
			__( 'Exercise suggestions without a protocol', 'gear-engine' ),
			array( $this, 'render_field_checkbox' ),
			self::PAGE_SLUG,
			'gae_section_ai',
			array(
				'id'          => 'gear_engine_disable_general_exercises',
				'label'       => __( 'Disable general suggestions', 'gear-engine' ),
				'description' => __( 'When checked, the chat only suggests exercises from Approved protocols. Otherwise it may choose from Low and Moderate risk exercises for the body area and labels them as general guidance.', 'gear-engine' ),
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

		$models    = Gear_Engine_AI_API::get_available_models();
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
		echo '<span class="dashicons dashicons-update"></span>';
		echo '<span>' . esc_html__( 'Fetch Latest Models from Google AI', 'gear-engine' ) . '</span>';
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
			GEAR_ENGINE_URL . 'assets/css/admin.css',
			array(),
			GEAR_ENGINE_VERSION
		);

		wp_enqueue_script(
			'gae-admin-script',
			GEAR_ENGINE_URL . 'assets/js/admin.js',
			array( 'jquery' ),
			GEAR_ENGINE_VERSION,
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

		$total_gear   = wp_count_posts( Gear_Engine_CPT::POST_TYPE );
		$published    = $total_gear->publish ?? 0;
		$next_cron    = wp_next_scheduled( 'gae_daily_sync_event' );
		$last_report  = get_option( 'gae_last_sync_report', array() );
		$catalog_cap  = (int) get_option( 'gae_max_total_products', 100 );
		$sync_limit   = (int) get_option( 'gae_max_items_per_sync', 100 );
		$min_price    = (float) get_option( 'gae_min_price', 0 );
		$max_price    = (float) get_option( 'gae_max_price', 0 );
		$min_discount = (float) get_option( 'gae_min_discount_pct', 0 );
		$only_stock   = (bool) get_option( 'gae_only_in_stock', false );
		$page_slug    = self::PAGE_SLUG;
		$option_group = self::OPTION_GROUP;

		$active_feed_labels = array();
		foreach ( self::get_feed_slots() as $c_idx => $c_slot ) {
			if ( ! empty( $c_slot['enabled'] ) && ! empty( $c_slot['url'] ) ) {
				$active_feed_labels[] = ! empty( $c_slot['name'] ) ? $c_slot['name'] : sprintf( __( 'Feed #%d', 'gear-engine' ), (int) $c_idx + 1 );
			}
		}

		require GEAR_ENGINE_PATH . 'includes/templates/settings-page.php';
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
		if ( ! class_exists( 'Gear_Engine_Sync_Engine' ) ) {
			require_once GEAR_ENGINE_PATH . 'includes/class-gear-engine-sync-engine.php';
		}

		$sync_engine = new Gear_Engine_Sync_Engine();

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
				'post_type'      => Gear_Engine_CPT::POST_TYPE,
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

		$models = Gear_Engine_AI_API::get_available_models( true );

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


// Deprecated alias preserved for backwards compatibility.
if ( ! class_exists( 'GAE_Settings', false ) ) {
	class_alias( 'Gear_Engine_Settings', 'GAE_Settings' );
}

