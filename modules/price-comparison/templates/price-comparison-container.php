<?php
/**
 * Price comparison container template.
 *
 * @package GearEngine
 *
 * @var string $encoded_feed       URI-encoded JSON product feed.
 * @var string $initial_department Default department tab.
 * @var string $initial_category   Default category tab.
 * @var string $initial_view       Default view mode (grid or list).
 * @var int    $initial_limit      Initial number of items visible.
 * @var bool   $show_guide_link    Whether to render the cross-link banner to Kjøpsveilederne.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}
?>
<div class="ge-price-comp-wrapper">
	<?php if ( $show_guide_link ) : ?>
		<aside class="ge-price-comp-guide-banner" aria-label="<?php esc_attr_e( 'Veiledning', 'gear-engine' ); ?>">
			<div class="ge-guide-banner-content">
				<div class="ge-guide-banner-icon">
					<svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
						<circle cx="12" cy="12" r="10"></circle>
						<path d="M9.09 9a3 3 0 0 1 5.83 1c0 2-3 3-3 3"></path>
						<line x1="12" y1="17" x2="12.01" y2="17"></line>
					</svg>
				</div>
				<div class="ge-guide-banner-text">
					<strong><?php esc_html_e( 'Usikker på hvilket utstyr som passer best for deg?', 'gear-engine' ); ?></strong>
					<span><?php esc_html_e( 'Finn optimal modell for leddbelastning, plass og budsjett i vår interaktive veileder.', 'gear-engine' ); ?></span>
				</div>
			</div>
			<a href="<?php echo esc_url( home_url( '/kjopsveiledere/' ) ); ?>" class="ge-guide-banner-btn">
				<?php esc_html_e( 'Gå til Kjøpsveileder', 'gear-engine' ); ?>
				<svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
					<line x1="5" y1="12" x2="19" y2="12"></line>
					<polyline points="12 5 19 12 12 19"></polyline>
				</svg>
			</a>
		</aside>
	<?php endif; ?>

	<div
		id="fysionett-prissok-root"
		data-feed="<?php echo esc_attr( $encoded_feed ); ?>"
		data-initial-department="<?php echo esc_attr( $initial_department ); ?>"
		data-initial-category="<?php echo esc_attr( $initial_category ); ?>"
		data-initial-view="<?php echo esc_attr( $initial_view ); ?>"
		data-initial-limit="<?php echo esc_attr( (string) $initial_limit ); ?>"
	>
		<!-- Innhold rendres dynamisk via price-comparison.js -->
		<div class="ge-price-comp-loading" style="padding: 40px 0; text-align: center; color: #667085;">
			<div class="ge-price-comp-spinner" style="display: inline-block; width: 32px; height: 32px; border: 3px solid #e2e8f0; border-top-color: #0C2340; border-radius: 50%; animation: ge-spin 0.8s linear infinite;"></div>
			<p style="margin-top: 12px; font-weight: 500; font-size: 14px;"><?php esc_html_e( 'Laster priser og produkter...', 'gear-engine' ); ?></p>
		</div>
	</div>

	<!-- Flytende dokk for side-om-side sammenligning -->
	<aside id="ge-floating-cmp-bar" class="ge-comp-dock" style="display: none;" aria-label="<?php esc_attr_e( 'Sammenligningsdokk', 'gear-engine' ); ?>">
		<div class="ge-dock-info">
			<span class="ge-dock-count"><b id="ge-cmp-selected-count">0</b> <?php esc_html_e( 'modeller valgt (maks 3)', 'gear-engine' ); ?></span>
			<div class="ge-dock-thumbs" id="ge-dock-thumbs"></div>
		</div>
		<div class="ge-dock-actions">
			<button type="button" id="ge-cmp-open-modal-btn" class="ge-dock-btn">
				<?php esc_html_e( 'Sammenlign nå', 'gear-engine' ); ?> (<span id="ge-dock-btn-count">0</span>)
			</button>
			<button type="button" id="ge-cmp-clear-btn" class="ge-dock-clear-btn">
				<?php esc_html_e( 'Nullstill', 'gear-engine' ); ?>
			</button>
		</div>
	</aside>

	<!-- Side-om-side sammenligningsmodal (Material 3 Surface) -->
	<div id="ge-cmp-modal" class="ge-cmp-modal-scrim" style="display: none;" role="dialog" aria-modal="true" aria-labelledby="ge-cmp-modal-title">
		<div class="ge-cmp-modal-card">
			<div class="ge-cmp-modal-head">
				<div>
					<h3 id="ge-cmp-modal-title" class="ge-cmp-modal-title"><?php esc_html_e( 'Sammenlign modeller side-om-side', 'gear-engine' ); ?></h3>
					<span class="ge-cmp-modal-subtitle"><?php esc_html_e( 'Strukturerte nøkkeltall og prissammenligning for de valgte modellene', 'gear-engine' ); ?></span>
				</div>
				<div class="ge-cmp-modal-controls">
					<label class="ge-cmp-diff-toggle" title="<?php esc_attr_e( 'Skjul spesifikasjoner der modellene har like verdier', 'gear-engine' ); ?>">
						<input type="checkbox" id="ge-cmp-diff-only" class="ge-cmp-diff-checkbox" />
						<span><?php esc_html_e( 'Vis kun ulikheter', 'gear-engine' ); ?></span>
					</label>
					<button type="button" id="ge-modal-close-btn" class="ge-cmp-modal-close" title="<?php esc_attr_e( 'Lukk sammenligning', 'gear-engine' ); ?>" aria-label="<?php esc_attr_e( 'Lukk', 'gear-engine' ); ?>">&times;</button>
				</div>
			</div>
			<div id="ge-cmp-modal-body" class="ge-cmp-modal-body"></div>
		</div>
	</div>
</div>
<style>
@keyframes ge-spin {
	to { transform: rotate(360deg); }
}
</style>

