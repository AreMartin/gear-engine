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
	<div
		id="fysionett-prissok-root"
		class="ge-m3-comp-wrapper"
		data-feed="<?php echo esc_attr( $encoded_feed ); ?>"
		data-initial-department="<?php echo esc_attr( $initial_department ); ?>"
		data-initial-category="<?php echo esc_attr( $initial_category ); ?>"
		data-initial-view="<?php echo esc_attr( $initial_view ); ?>"
		data-initial-limit="<?php echo esc_attr( (string) $initial_limit ); ?>"
		data-show-guide="<?php echo esc_attr( $show_guide_link ? 'true' : 'false' ); ?>"
		data-guide-url="<?php echo esc_url( home_url( '/kjopsveiledere/' ) ); ?>"
	>
		<p class="ge-m3-affiliate-disclosure">
			<span class="material-symbols-outlined" aria-hidden="true">info</span>
			<span><?php esc_html_e( 'Noen av butikkene er samarbeidspartnere, og vi kan motta provisjon hvis du handler via lenkene. Dette påvirker ikke prisen.', 'gear-engine' ); ?></span>
		</p>

		<?php if ( $show_guide_link ) : ?>
			<aside class="ge-m3-guide-banner ge-price-comp-guide-banner" id="ge-guide-banner" aria-label="<?php esc_attr_e( 'Veiledning', 'gear-engine' ); ?>">
				<button class="ge-m3-guide-dismiss" id="ge-guide-dismiss" type="button" aria-label="<?php esc_attr_e( 'Lukk kjøpsveileder', 'gear-engine' ); ?>" title="<?php esc_attr_e( 'Lukk kjøpsveileder', 'gear-engine' ); ?>">
					<span class="material-symbols-outlined" aria-hidden="true">close</span>
				</button>
				<div class="ge-m3-guide-content">
					<div class="ge-m3-guide-text">
						<strong><?php esc_html_e( 'Usikker på hvilket utstyr som passer best for deg?', 'gear-engine' ); ?></strong>
						<span><?php esc_html_e( 'Finn optimal modell for leddbelastning, plass og budsjett i vår interaktive veileder.', 'gear-engine' ); ?></span>
					</div>
				</div>
				<a href="<?php echo esc_url( home_url( '/kjopsveiledere/' ) ); ?>" class="ge-m3-guide-btn ge-m3-btn ge-m3-btn--filled m3-sl">
					<?php esc_html_e( 'Gå til Kjøpsveileder', 'gear-engine' ); ?>
					<span class="material-symbols-outlined" aria-hidden="true">arrow_forward</span>
				</a>
			</aside>
		<?php endif; ?>

		<!-- Innhold rendres dynamisk via price-comparison.js -->
		<div class="ge-price-comp-loading" style="padding: 40px 0; text-align: center; color: #667085;">
			<div class="ge-price-comp-spinner" style="display: inline-block; width: 32px; height: 32px; border: 3px solid #e2e8f0; border-top-color: #0C2340; border-radius: 50%; animation: ge-spin 0.8s linear infinite;"></div>
			<p style="margin-top: 12px; font-weight: 500; font-size: 14px;"><?php esc_html_e( 'Laster priser og produkter...', 'gear-engine' ); ?></p>
		</div>
	</div>

	<!-- Flytende dokk for side-om-side sammenligning -->
	<aside id="ge-floating-cmp-bar" class="ge-m3-cmp-bar ge-comp-dock" style="display: none;" role="region" aria-label="<?php esc_attr_e( 'Sammenligning', 'gear-engine' ); ?>">
		<span class="ge-m3-cmp-bar-count">
			<span class="material-symbols-outlined" aria-hidden="true">compare_arrows</span>
			<span><b id="ge-cmp-selected-count">0</b> <?php esc_html_e( 'modeller valgt', 'gear-engine' ); ?></span>
		</span>
		<span id="ge-dock-thumbs" class="ge-m3-cmp-bar-products" aria-label="<?php esc_attr_e( 'Valgte produkter', 'gear-engine' ); ?>"></span>
		<button type="button" id="ge-cmp-open-modal-btn" class="ge-m3-cmp-bar-btn ge-m3-btn ge-m3-btn--filled m3-sl" aria-label="<?php esc_attr_e( 'Sammenlign spesifikasjoner', 'gear-engine' ); ?>">
			<span class="ge-m3-cmp-action-full"><?php esc_html_e( 'Sammenlign spesifikasjoner', 'gear-engine' ); ?></span>
			<span class="ge-m3-cmp-action-compact"><?php esc_html_e( 'Sammenlign', 'gear-engine' ); ?></span>
		</button>
		<button type="button" id="ge-cmp-clear-btn" class="ge-m3-btn ge-m3-btn--text m3-sl">
			<?php esc_html_e( 'Nullstill', 'gear-engine' ); ?>
		</button>
	</aside>

	<!-- Side-om-side sammenligningsmodal (Material 3 Surface) -->
	<div id="ge-cmp-modal" class="ge-m3-modal-scrim ge-cmp-modal-scrim" style="display: none;" role="dialog" aria-modal="true" aria-labelledby="ge-cmp-modal-title">
		<div class="ge-m3-modal-card ge-cmp-modal-card">
			<div class="ge-m3-modal-head">
				<div>
					<h2 id="ge-cmp-modal-title" class="ge-m3-modal-title"><?php esc_html_e( 'Sammenlign modeller side-om-side', 'gear-engine' ); ?></h2>
					<p class="ge-m3-modal-supporting"><?php esc_html_e( 'Strukturerte nøkkeltall og prissammenligning for de valgte modellene', 'gear-engine' ); ?></p>
				</div>
				<div class="ge-m3-modal-actions">
					<label class="ge-m3-diff-toggle ge-cmp-diff-toggle m3-sl" title="<?php esc_attr_e( 'Skjul spesifikasjoner der modellene har like verdier', 'gear-engine' ); ?>">
						<input type="checkbox" id="ge-cmp-diff-only" class="ge-m3-diff-checkbox" />
						<span class="ge-m3-chip-check material-symbols-outlined" aria-hidden="true">check</span>
						<span><?php esc_html_e( 'Vis kun ulikheter', 'gear-engine' ); ?></span>
					</label>
					<button type="button" id="ge-modal-close-btn" class="ge-m3-modal-close ge-m3-icon-btn m3-sl" title="<?php esc_attr_e( 'Lukk sammenligning', 'gear-engine' ); ?>" aria-label="<?php esc_attr_e( 'Lukk sammenligning', 'gear-engine' ); ?>">
						<span class="material-symbols-outlined" aria-hidden="true">close</span>
					</button>
				</div>
			</div>
			<div id="ge-cmp-modal-body" class="ge-m3-modal-body"></div>
		</div>
	</div>
</div>
<style>
@keyframes ge-spin {
	to { transform: rotate(360deg); }
}
</style>
