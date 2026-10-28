<?php
/**
 * Gear picker container template.
 *
 * Expects $type (string) and $payload (array with "config" and "items") from Gear_Engine_Gear_Picker::render().
 *
 * @package GearEngine
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}
?>
<div class="gear-engine-picker" data-type="<?php echo esc_attr( $type ); ?>">
	<?php if ( empty( $payload['items'] ) ) : ?>
		<p class="gear-engine-picker__empty"><?php esc_html_e( 'No products are available in this guide right now.', 'gear-engine' ); ?></p>
	<?php else : ?>
		<script type="application/json" class="gear-engine-picker__data"><?php echo wp_json_encode( $payload, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- JSON with hex-escaped markup characters. ?></script>
		<div class="gear-engine-picker__app" aria-live="polite"></div>
		<noscript><?php esc_html_e( 'JavaScript is required to use this tool.', 'gear-engine' ); ?></noscript>
	<?php endif; ?>
</div>
