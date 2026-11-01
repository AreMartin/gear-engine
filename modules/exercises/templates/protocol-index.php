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
	<header class="gear-engine-program__head">
		<div class="gear-engine-program__badge">
			<span class="gear-engine-program__badge-dot"></span>
			<?php esc_html_e( 'Fysioterapeutisk øvelsesbibliotek', 'gear-engine' ); ?>
		</div>
		<h2 class="gear-engine-program__title"><?php esc_html_e( 'Treningsprogrammer & Rehabprotokoller', 'gear-engine' ); ?></h2>
		<p class="gear-engine-program__summary"><?php esc_html_e( 'Strukturerte opptreningsforløp utarbeidet etter kliniske retningslinjer for trygg fremgang.', 'gear-engine' ); ?></p>
	</header>

	<?php if ( empty( $protocols ) ) : ?>
		<p class="gear-engine-program__empty"><?php esc_html_e( 'Ingen treningsprogrammer er tilgjengelige ennå.', 'gear-engine' ); ?></p>
	<?php else : ?>
		<ul class="gear-engine-program__index">
			<?php foreach ( $protocols as $protocol ) : ?>
				<li>
					<a class="gear-engine-program__card" href="<?php echo esc_url( Gear_Engine_Protocol_Viewer::get_program_url( $protocol['slug'] ) ); ?>">
						<div class="gear-engine-program__card-content">
							<span class="gear-engine-program__card-pill"><?php esc_html_e( 'Opptreningsprogram', 'gear-engine' ); ?></span>
							<span class="gear-engine-program__card-title"><?php echo esc_html( $protocol['name'] ); ?></span>
							<?php if ( '' !== $protocol['summary'] ) : ?>
								<span class="gear-engine-program__card-summary"><?php echo esc_html( $protocol['summary'] ); ?></span>
							<?php endif; ?>
						</div>
						<div class="gear-engine-program__card-arrow" aria-hidden="true">
							<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="var(--ge-icon-stroke, 1.75px)" stroke-linecap="round" stroke-linejoin="round"><path d="M9 18l6-6-6-6"/></svg>
						</div>
					</a>
				</li>
			<?php endforeach; ?>
		</ul>
	<?php endif; ?>
</div>
