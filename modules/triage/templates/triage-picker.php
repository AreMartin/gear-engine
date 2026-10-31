<?php
/**
 * Triage region picker template.
 *
 * Expects $cards (array of region, label, summary, chips, url) from Gear_Engine_Triage::render_picker().
 *
 * @package GearEngine
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}
?>
<nav class="gear-engine-triage-picker" aria-label="<?php esc_attr_e( 'Velg område', 'gear-engine' ); ?>">
	<ul class="gear-engine-triage-picker__grid">
		<?php foreach ( $cards as $card ) : ?>
			<li>
				<a class="gear-engine-triage-picker__card" href="<?php echo esc_url( $card['url'] ); ?>">
					<div class="gear-engine-triage-picker__card-top">
						<span class="gear-engine-triage-picker__icon">
							<?php echo Gear_Engine_Triage::get_icon_svg( $card['icon'] ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Static plugin-defined SVG. ?>
						</span>
						<span class="gear-engine-triage-picker__arrow" aria-hidden="true">
							<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="5" y1="12" x2="19" y2="12"></line><polyline points="12 5 19 12 12 19"></polyline></svg>
						</span>
					</div>
					<div class="gear-engine-triage-picker__card-body">
						<span class="gear-engine-triage-picker__label"><?php echo esc_html( $card['label'] ); ?></span>
						<?php if ( ! empty( $card['summary'] ) ) : ?>
							<span class="gear-engine-triage-picker__summary"><?php echo esc_html( $card['summary'] ); ?></span>
						<?php endif; ?>
					</div>
					<?php if ( ! empty( $card['chips'] ) && is_array( $card['chips'] ) ) : ?>
						<div class="gear-engine-triage-picker__chips">
							<?php foreach ( $card['chips'] as $chip ) : ?>
								<span class="gear-engine-triage-picker__chip"><?php echo esc_html( $chip ); ?></span>
							<?php endforeach; ?>
						</div>
					<?php endif; ?>
				</a>
			</li>
		<?php endforeach; ?>
	</ul>
</nav>
