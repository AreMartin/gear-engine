<?php
/**
 * Price comparison container template.
 *
 * @package GearEngine
 *
 * @var string $encoded_feed     URI-encoded JSON product feed.
 * @var string $initial_category Default category tab.
 * @var string $initial_view     Default view mode (grid or list).
 * @var int    $initial_limit    Initial number of items visible.
 * @var bool   $show_guide_link  Whether to render the cross-link banner to Kjøpsveilederne.
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
		data-initial-category="<?php echo esc_attr( $initial_category ); ?>"
		data-initial-view="<?php echo esc_attr( $initial_view ); ?>"
		data-initial-limit="<?php echo esc_attr( (string) $initial_limit ); ?>"
	>
		<!-- Innhold rendres dynamisk via price-comparison.js -->
		<div class="ge-price-comp-loading" style="padding: 40px 0; text-align: center; color: #667085;">
			<div class="ge-price-comp-spinner" style="display: inline-block; width: 32px; height: 32px; border: 3px solid #e2e8f0; border-top-color: #1A2A3A; border-radius: 50%; animation: ge-spin 0.8s linear infinite;"></div>
			<p style="margin-top: 12px; font-weight: 500; font-size: 14px;"><?php esc_html_e( 'Laster priser og produkter...', 'gear-engine' ); ?></p>
		</div>
	</div>
</div>
<style>
@keyframes ge-spin {
	to { transform: rotate(360deg); }
}
</style>

