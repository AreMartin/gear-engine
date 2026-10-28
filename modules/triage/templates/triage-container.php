<?php
/**
 * Triage container template.
 *
 * Expects $region (string) and $config (array) from Gear_Engine_Triage::render().
 *
 * @package GearEngine
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}
?>
<div class="gear-engine-triage" data-region="<?php echo esc_attr( $region ); ?>">
	<?php if ( ! empty( $back_url ) ) : ?>
		<a class="gear-engine-triage__back get-noprint" href="<?php echo esc_url( $back_url ); ?>">&larr; <?php esc_html_e( 'Alle tester', 'gear-engine' ); ?></a>
	<?php endif; ?>
	<script type="application/json" class="gear-engine-triage__config"><?php echo wp_json_encode( $config, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- JSON with hex-escaped markup characters. ?></script>
	<noscript><?php esc_html_e( 'JavaScript is required to use this tool.', 'gear-engine' ); ?></noscript>
</div>
