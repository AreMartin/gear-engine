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
<div class="gear-engine-triage-picker" aria-label="<?php esc_attr_e( 'Velg område for symptomsjekk', 'gear-engine' ); ?>">
	<div class="gear-engine-triage-picker__header">
		<div class="overline"><?php esc_html_e( 'Digital triage · ca. 2 minutter', 'gear-engine' ); ?></div>
		<h1 class="headline-l" style="margin-top:4px"><?php esc_html_e( 'Hvor har du vondt?', 'gear-engine' ); ?></h1>
		<p class="body-m muted" style="margin-top:8px"><?php esc_html_e( 'Velg området som plager deg mest for å starte en målrettet vurdering og få konkrete råd.', 'gear-engine' ); ?></p>
	</div>

	<div class="region-grid">
		<?php foreach ( $cards as $card ) : ?>
			<?php if ( ! empty( $card['image_url'] ) ) : ?>
				<a class="region-tile region-card has-image" href="<?php echo esc_url( $card['url'] ); ?>">
					<div class="tile-media">
						<?php if ( ! empty( $card['badge'] ) ) : ?>
							<span class="tile-badge rc-badge"><?php echo esc_html( $card['badge'] ); ?></span>
						<?php endif; ?>
						<?php
						$img_pos    = ! empty( $card['image_position'] ) ? $card['image_position'] : 'center center';
						$media_html = '<img src="' . esc_url( $card['image_url'] ) . '" alt="' . esc_attr( $card['label'] ) . '" class="tile-image" style="object-position:' . esc_attr( $img_pos ) . ';" loading="lazy">';
						/**
						 * Filters the media HTML rendered inside a region tile.
						 *
						 * @param string $media_html Media markup.
						 * @param array  $card       Card metadata.
						 */
						echo apply_filters( 'gear_engine_triage_region_card_media', $media_html, $card ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
						?>
					</div>
					<div class="tile-body">
						<div class="tile-label"><?php echo esc_html( $card['label'] ); ?></div>
						<?php if ( ! empty( $card['summary'] ) ) : ?>
							<div class="tile-sub"><?php echo esc_html( $card['summary'] ); ?></div>
						<?php elseif ( ! empty( $card['chips'] ) && is_array( $card['chips'] ) ) : ?>
							<div class="tile-sub"><?php echo esc_html( implode( ' · ', $card['chips'] ) ); ?></div>
						<?php endif; ?>
					</div>
				</a>
			<?php else : ?>
				<a class="region-tile region-card is-typographic" href="<?php echo esc_url( $card['url'] ); ?>">
					<div class="typo-top">
						<?php if ( ! empty( $card['badge'] ) ) : ?>
							<span class="typo-badge rc-badge"><?php echo esc_html( $card['badge'] ); ?></span>
						<?php endif; ?>
						<span class="typo-action" aria-hidden="true">
							<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M7 17l10-10M7 7h10v10"/></svg>
						</span>
					</div>
					<div class="typo-mid">
						<div class="typo-title"><?php echo esc_html( $card['label'] ); ?></div>
						<?php if ( ! empty( $card['summary'] ) ) : ?>
							<div class="typo-sub"><?php echo esc_html( $card['summary'] ); ?></div>
						<?php endif; ?>
					</div>
					<?php if ( ! empty( $card['chips'] ) && is_array( $card['chips'] ) ) : ?>
						<div class="typo-chips">
							<?php foreach ( $card['chips'] as $chip ) : ?>
								<span class="typo-chip"><?php echo esc_html( $chip ); ?></span>
							<?php endforeach; ?>
						</div>
					<?php endif; ?>
				</a>
			<?php endif; ?>
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
