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
		// 1. Feed URLs section.
		add_settings_section(
			'gae_section_feeds',
			__( 'Affiliate Data Feeds', 'gear-engine' ),
			array( $this, 'render_feeds_section_intro' ),
			self::PAGE_SLUG
		);

		register_setting(
			self::OPTION_GROUP,
			'gae_adtraction_url',
			array(
				'type'              => 'string',
				'sanitize_callback' => array( $this, 'sanitize_feed_url' ),
				'default'           => '',
			)
		);

		add_settings_field(
			'gae_adtraction_url',
			__( 'Adtraction Feed URL', 'gear-engine' ),
			array( $this, 'render_field_text' ),
			self::PAGE_SLUG,
			'gae_section_feeds',
			array(
				'id'          => 'gae_adtraction_url',
				'type'        => 'url',
				'description' => __( 'Direct XML or JSON product feed URL from Adtraction (supports XML, JSON, and NDJSON).', 'gear-engine' ),
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

		add_settings_field(
			'gae_tradetracker_url',
			__( 'TradeTracker Feed URL', 'gear-engine' ),
			array( $this, 'render_field_text' ),
			self::PAGE_SLUG,
			'gae_section_feeds',
			array(
				'id'          => 'gae_tradetracker_url',
				'type'        => 'url',
				'description' => __( 'Direct XML or JSON product feed URL from TradeTracker (supports XML, JSON, and NDJSON).', 'gear-engine' ),
			)
		);

		// 2. Pre-Filter Settings section.
		add_settings_section(
			'gae_section_filters',
			__( 'Feed Pre-Filtering Rules', 'gear-engine' ),
			array( $this, 'render_filters_section_intro' ),
			self::PAGE_SLUG
		);

		// Minimum Price.
		register_setting(
			self::OPTION_GROUP,
			'gae_min_price',
			array(
				'type'              => 'number',
				'sanitize_callback' => array( $this, 'sanitize_price' ),
				'default'           => 0,
			)
		);

		add_settings_field(
			'gae_min_price',
			__( 'Minimum Price Threshold (NOK / kr)', 'gear-engine' ),
			array( $this, 'render_field_text' ),
			self::PAGE_SLUG,
			'gae_section_filters',
			array(
				'id'          => 'gae_min_price',
				'type'        => 'number',
				'step'        => 'any',
				'description' => __( 'Skip exercise products with price lower than this threshold (e.g. 1000). Set to 0 to disable.', 'gear-engine' ),
			)
		);

		// Maximum Price.
		register_setting(
			self::OPTION_GROUP,
			'gae_max_price',
			array(
				'type'              => 'number',
				'sanitize_callback' => array( $this, 'sanitize_price' ),
				'default'           => 0,
			)
		);

		add_settings_field(
			'gae_max_price',
			__( 'Maximum Price Cap (NOK / kr)', 'gear-engine' ),
			array( $this, 'render_field_text' ),
			self::PAGE_SLUG,
			'gae_section_filters',
			array(
				'id'          => 'gae_max_price',
				'type'        => 'number',
				'step'        => 'any',
				'description' => __( 'Skip commercial or ultra-expensive products above this price threshold (e.g. 60000). Set to 0 to disable.', 'gear-engine' ),
			)
		);

		// Minimum Discount Percentage.
		register_setting(
			self::OPTION_GROUP,
			'gae_min_discount_pct',
			array(
				'type'              => 'number',
				'sanitize_callback' => array( $this, 'sanitize_price' ),
				'default'           => 0,
			)
		);

		add_settings_field(
			'gae_min_discount_pct',
			__( 'Minimum Discount Percentage (%)', 'gear-engine' ),
			array( $this, 'render_field_text' ),
			self::PAGE_SLUG,
			'gae_section_filters',
			array(
				'id'          => 'gae_min_discount_pct',
				'type'        => 'number',
				'step'        => '1',
				'description' => __( 'Only import products currently on sale with at least this discount percentage (e.g. 15 for 15% off). Set to 0 to import all.', 'gear-engine' ),
			)
		);

		// In-Stock Only Checkbox.
		register_setting(
			self::OPTION_GROUP,
			'gae_only_in_stock',
			array(
				'type'              => 'boolean',
				'sanitize_callback' => 'rest_sanitize_boolean',
				'default'           => false,
			)
		);

		add_settings_field(
			'gae_only_in_stock',
			__( 'Stock Availability Filter', 'gear-engine' ),
			array( $this, 'render_field_checkbox' ),
			self::PAGE_SLUG,
			'gae_section_filters',
			array(
				'id'          => 'gae_only_in_stock',
				'label'       => __( 'Only import products that are currently in stock', 'gear-engine' ),
				'description' => __( 'Discards out-of-stock feed items at ingest time, saving database storage and avoiding obsolete product listings.', 'gear-engine' ),
			)
		);

		// Max Products to Import per Run.
		register_setting(
			self::OPTION_GROUP,
			'gae_max_items_per_sync',
			array(
				'type'              => 'integer',
				'sanitize_callback' => 'absint',
				'default'           => 100,
			)
		);

		add_settings_field(
			'gae_max_items_per_sync',
			__( 'Max Products to Import per Run', 'gear-engine' ),
			array( $this, 'render_field_text' ),
			self::PAGE_SLUG,
			'gae_section_filters',
			array(
				'id'          => 'gae_max_items_per_sync',
				'type'        => 'number',
				'step'        => '1',
				'description' => __( 'Maximum number of products to insert/update in a single sync batch (default 100). Prevents browser memory crashes in WordPress Playground. Set to 0 for unlimited.', 'gear-engine' ),
			)
		);

		// Allowed Categories.
		register_setting(
			self::OPTION_GROUP,
			'gae_allowed_categories',
			array(
				'type'              => 'string',
				'sanitize_callback' => 'sanitize_textarea_field',
				'default'           => "Tredemølle, Romaskin, Spinningsykkel, Vektstang, Vektvest, Treningsbenk, Crosstrainer, Styrkepakke",
			)
		);

		add_settings_field(
			'gae_allowed_categories',
			__( 'Allowed Categories / Keywords', 'gear-engine' ),
			array( $this, 'render_field_textarea' ),
			self::PAGE_SLUG,
			'gae_section_filters',
			array(
				'id'          => 'gae_allowed_categories',
				'rows'        => 3,
				'description' => __( 'Comma or newline separated list. Products must match at least one category to be imported. Leave empty to allow all.', 'gear-engine' ),
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

		// Excluded Keywords.
		register_setting(
			self::OPTION_GROUP,
			'gae_excluded_keywords',
			array(
				'type'              => 'string',
				'sanitize_callback' => 'sanitize_textarea_field',
				'default'           => "reservedel, manual, reservedeler, kabel, skrue, mutter, smøremiddel, underlagsmatte, sokker, drikkeflaske, plakat",
			)
		);

		add_settings_field(
			'gae_excluded_keywords',
			__( 'Blacklisted Keywords', 'gear-engine' ),
			array( $this, 'render_field_textarea' ),
			self::PAGE_SLUG,
			'gae_section_filters',
			array(
				'id'          => 'gae_excluded_keywords',
				'rows'        => 3,
				'description' => __( 'Comma or newline separated keywords. Any product containing these words in title, category, or description is discarded.', 'gear-engine' ),
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
	 * Section intro: Feeds.
	 */
	public function render_feeds_section_intro(): void {
		echo '<p class="description">' . esc_html__( 'Configure the remote affiliate XML feeds from Adtraction and TradeTracker. The streaming sync engine parses these feeds incrementally to prevent memory exhaustion.', 'gear-engine' ) . '</p>';
	}

	/**
	 * Section intro: Filters.
	 */
	public function render_filters_section_intro(): void {
		echo '<p class="description">' . esc_html__( 'Pre-filters are evaluated before database interaction. Products not meeting price criteria or containing blacklisted keywords are skipped entirely, saving database resources.', 'gear-engine' ) . '</p>';
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
				'strings'            => array(
					'syncing'         => __( 'Syncing feeds in streaming mode... Please wait.', 'gear-engine' ),
					'syncSuccess'     => __( 'Sync completed successfully!', 'gear-engine' ),
					'syncFailed'      => __( 'Sync encountered an error.', 'gear-engine' ),
					'fetchingModels'  => __( 'Fetching live models from Google AI Studio...', 'gear-engine' ),
					'fetchSuccess'    => __( 'Models updated successfully!', 'gear-engine' ),
					'fetchFailed'     => __( 'Failed to fetch models. Verify your API key.', 'gear-engine' ),
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
						<span class="gae-status-value"><?php echo esc_html( number_format_i18n( $published ) ); ?></span>
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
						<?php if ( ! empty( $last_report['skip_reasons'] ) ) : ?>
							<div class="gae-skip-reasons" style="margin-top: 6px; font-size: 12px; color: #b45309;">
								<strong><?php esc_html_e( 'Filter Skip Breakdown:', 'gear-engine' ); ?></strong>
								<?php
								$reason_labels = array();
								foreach ( (array) $last_report['skip_reasons'] as $r_msg => $r_cnt ) {
									$reason_labels[] = esc_html( $r_msg . ' (' . $r_cnt . ')' );
								}
								echo implode( ' · ', $reason_labels );
								?>
							</div>
						<?php endif; ?>
						<?php if ( ! empty( $last_report['errors'] ) ) : ?>
							<div class="gae-report-errors" style="color:#ef4444; margin-top:4px;">
								<?php echo esc_html( implode( ' | ', (array) $last_report['errors'] ) ); ?>
							</div>
						<?php endif; ?>
					</div>
				<?php endif; ?>

				<div class="gae-manual-actions">
					<button type="button" id="gae-sync-now-btn" class="button button-primary">
						<span class="dashicons dashicons-update" style="vertical-align:text-top;"></span>
						<?php esc_html_e( 'Run Streaming Sync Now', 'gear-engine' ); ?>
					</button>
					<span id="gae-sync-spinner" class="spinner" style="float:none; margin: 4px 8px 0;"></span>
					<span id="gae-sync-status-msg" style="font-weight:600; margin-left:8px;"></span>
				</div>
			</div>

			<form method="post" action="options.php">
				<?php
				settings_fields( self::OPTION_GROUP );
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
		// Force unlock orphaned locks on manual admin click.
		$report = $sync_engine->run_sync( array(), true );

		if ( isset( $report['status'] ) && 'locked' === $report['status'] ) {
			wp_send_json_error( array( 'message' => $report['message'] ?? __( 'A sync process is already currently running.', 'gear-engine' ) ) );
			return;
		}

		if ( ! empty( $report['errors'] ) && 0 === ( $report['processed'] ?? 0 ) ) {
			wp_send_json_error( array( 'message' => implode( ' | ', $report['errors'] ) ) );
			return;
		}

		$reason_summary = '';
		if ( ! empty( $report['skip_reasons'] ) ) {
			$labels = array();
			foreach ( (array) $report['skip_reasons'] as $r_msg => $r_cnt ) {
				$labels[] = $r_msg . ' (' . $r_cnt . ')';
			}
			$reason_summary = ' — Filters: ' . implode( ', ', array_slice( $labels, 0, 3 ) );
		}

		$cap_notice = ! empty( $report['batch_capped'] ) ? ' — ' . sprintf( __( 'Batch cap of %d products reached.', 'gear-engine' ), (int) get_option( 'gae_max_items_per_sync', 100 ) ) : '';
		$err_notice = ! empty( $report['errors'] ) ? ' — ' . sprintf( __( 'Errors: %s', 'gear-engine' ), implode( ' | ', $report['errors'] ) ) : '';

		// Guarantee clean buffer before JSON response
		while ( ob_get_level() > 0 ) {
			ob_end_clean();
		}

		wp_send_json_success(
			array(
				'message' => sprintf(
					/* translators: 1: Processed count, 2: Inserted, 3: Updated, 4: Skipped, 5: Reason summary, 6: Cap notice, 7: Error notice */
					__( 'Sync complete! Processed: %1$d (Inserted: %2$d, Updated: %3$d, Skipped: %4$d%5$s)%6$s%7$s', 'gear-engine' ),
					absint( $report['processed'] ?? 0 ),
					absint( $report['inserted'] ?? 0 ),
					absint( $report['updated'] ?? 0 ),
					absint( $report['skipped'] ?? 0 ),
					$reason_summary,
					$cap_notice,
					$err_notice
				),
				'report'  => $report,
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
