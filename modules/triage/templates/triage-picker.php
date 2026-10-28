<?php
/**
 * Triage region picker template.
 *
 * Expects $cards (array of region, label, summary, url) from Gear_Engine_Triage::render_picker().
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
				<a class="gear-engine-triage-picker__card" href="<?php echo esc_url( $card['url'] ); ?>"<?php echo '' !== $card['summary'] ? ' title="' . esc_attr( $card['summary'] ) . '"' : ''; ?>>
					<?php echo Gear_Engine_Triage::get_icon_svg( $card['icon'] ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Static plugin-defined SVG. ?>
					<span class="gear-engine-triage-picker__label"><?php echo esc_html( $card['label'] ); ?></span>
				</a>
			</li>
		<?php endforeach; ?>
	</ul>
</nav>
