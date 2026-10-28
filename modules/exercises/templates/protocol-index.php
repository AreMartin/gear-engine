<?php
/**
 * Protocol index template: lists approved training programs.
 *
 * Expects $protocols (array) from Gear_Engine_Protocol_Viewer::render().
 *
 * @package GearEngine
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}
?>
<div class="gear-engine-program">
	<?php if ( empty( $protocols ) ) : ?>
		<p class="gear-engine-program__empty"><?php esc_html_e( 'Ingen treningsprogrammer er tilgjengelige ennå.', 'gear-engine' ); ?></p>
	<?php else : ?>
		<ul class="gear-engine-program__index">
			<?php foreach ( $protocols as $protocol ) : ?>
				<li>
					<a class="gear-engine-program__card" href="<?php echo esc_url( Gear_Engine_Protocol_Viewer::get_program_url( $protocol['slug'] ) ); ?>">
						<span class="gear-engine-program__card-title"><?php echo esc_html( $protocol['name'] ); ?></span>
						<?php if ( '' !== $protocol['summary'] ) : ?>
							<span class="gear-engine-program__card-summary"><?php echo esc_html( $protocol['summary'] ); ?></span>
						<?php endif; ?>
					</a>
				</li>
			<?php endforeach; ?>
		</ul>
	<?php endif; ?>
</div>
