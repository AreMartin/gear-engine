<?php
/**
 * Triage region picker template.
 *
 * Expects $cards (array of region, label, summary, chips, badge, url) from Gear_Engine_Triage::render_picker().
 *
 * @package GearEngine
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}
?>
<div class="gear-engine-triage-picker" aria-label="<?php esc_attr_e( 'Velg område for smertesjekk', 'gear-engine' ); ?>">
	<div class="gear-engine-triage-picker__header">
		<div class="overline"><?php esc_html_e( 'Digital triage · ca. 2 minutter', 'gear-engine' ); ?></div>
		<h1 class="headline-l" style="margin-top:4px"><?php esc_html_e( 'Hvor har du vondt?', 'gear-engine' ); ?></h1>
		<p class="body-m muted" style="margin-top:8px"><?php esc_html_e( 'Velg området som plager deg mest for å starte en målrettet vurdering og få konkrete råd.', 'gear-engine' ); ?></p>
	</div>

	<div class="region-grid">
		<?php foreach ( $cards as $card ) : ?>
			<a class="region-card sl" href="<?php echo esc_url( $card['url'] ); ?>">
				<div class="rc-top">
					<div class="rc-title-row">
						<b><?php echo esc_html( $card['label'] ); ?></b>
						<?php if ( ! empty( $card['badge'] ) ) : ?>
							<span class="rc-badge"><?php echo esc_html( $card['badge'] ); ?></span>
						<?php endif; ?>
					</div>
					<span class="rc-action" aria-hidden="true">
						<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M9 18l6-6-6-6"/></svg>
					</span>
				</div>
				<?php if ( ! empty( $card['summary'] ) ) : ?>
					<p><?php echo esc_html( $card['summary'] ); ?></p>
				<?php endif; ?>
				<?php if ( ! empty( $card['chips'] ) && is_array( $card['chips'] ) ) : ?>
					<div class="rc-chips">
						<?php foreach ( $card['chips'] as $chip ) : ?>
							<span class="rc-chip"><?php echo esc_html( $chip ); ?></span>
						<?php endforeach; ?>
					</div>
				<?php endif; ?>
			</a>
		<?php endforeach; ?>
	</div>

	<div class="trust-banner">
		<div class="trust-item">
			<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/></svg>
			<span><b><?php esc_html_e( '100% anonymt:', 'gear-engine' ); ?></b> <?php esc_html_e( 'Ingen innlogging eller registrering kreves', 'gear-engine' ); ?></span>
		</div>
		<div class="trust-item">
			<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M22 12h-4l-3 9L9 3l-3 9H2"/></svg>
			<span><b><?php esc_html_e( 'Klinisk forankret:', 'gear-engine' ); ?></b> <?php esc_html_e( 'Basert på anerkjente faglige retningslinjer for primærhelsetjenesten', 'gear-engine' ); ?></span>
		</div>
	</div>
</div>
