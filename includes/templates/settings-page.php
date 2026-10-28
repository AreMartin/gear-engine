<?php
/**
 * Admin template: Gear Engine settings page.
 *
 * Rendered by Gear_Engine_Settings::render_settings_page().
 * Available variables: $published, $catalog_cap, $next_cron, $last_report,
 * $sync_limit, $only_stock, $min_price, $max_price, $min_discount,
 * $active_feed_labels, $page_slug, $option_group.
 *
 * @package GearEngine
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}
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
						if ( empty( $active_feed_labels ) ) {
							echo '<label><input type="radio" name="gae_quick_feed" value="Adtraction" /> Adtraction</label>';
							echo '<label><input type="radio" name="gae_quick_feed" value="TradeTracker" /> TradeTracker</label>';
						} else {
							foreach ( $active_feed_labels as $feed_label ) {
								echo '<label><input type="radio" name="gae_quick_feed" value="' . esc_attr( $feed_label ) . '" /> ' . esc_html( $feed_label ) . '</label>';
							}
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
		settings_fields( $option_group );
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
		do_settings_sections( $page_slug );
		submit_button( __( 'Save Gear Engine Settings', 'gear-engine' ) );
		?>
	</form>
</div>
