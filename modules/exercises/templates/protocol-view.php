<?php
/**
 * Protocol (training program) page template.
 *
 * Expects $protocol (array) and $groups (array) from Gear_Engine_Protocol_Viewer::render();
 * $change_url (optional) links back to the phase questions of an automatic program.
 *
 * @package GearEngine
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}
?>
<div class="gear-engine-program">
	<header class="gear-engine-program__head">
		<h2 class="gear-engine-program__title"><?php echo esc_html( $protocol['name'] ); ?></h2>
		<?php if ( '' !== $protocol['summary'] ) : ?>
			<p class="gear-engine-program__summary"><?php echo esc_html( $protocol['summary'] ); ?></p>
		<?php endif; ?>
		<?php if ( '' !== $protocol['dosage'] ) : ?>
			<p class="gear-engine-program__dosage"><strong><?php esc_html_e( 'Dosering:', 'gear-engine' ); ?></strong> <?php echo esc_html( $protocol['dosage'] ); ?></p>
		<?php endif; ?>
		<?php if ( '' !== $protocol['red_flags'] ) : ?>
			<div class="gear-engine-program__warning" role="note">
				<strong><?php esc_html_e( 'Avbryt og kontakt lege hvis:', 'gear-engine' ); ?></strong>
				<p><?php echo esc_html( $protocol['red_flags'] ); ?></p>
			</div>
		<?php endif; ?>
	</header>

	<?php foreach ( $groups as $group ) : ?>
		<section class="gear-engine-program__phase">
			<?php if ( '' !== $group['name'] ) : ?>
				<h3 class="gear-engine-program__phase-title"><?php echo esc_html( $group['name'] ); ?></h3>
			<?php endif; ?>
			<?php if ( '' !== $group['description'] ) : ?>
				<p class="gear-engine-program__phase-desc"><?php echo esc_html( $group['description'] ); ?></p>
			<?php endif; ?>

			<ol class="gear-engine-program__list">
				<?php foreach ( $group['exercises'] as $exercise ) : ?>
					<?php $embed = Gear_Engine_Protocol_Viewer::get_embed_url( $exercise ); ?>
					<li class="gear-engine-program__exercise">
						<h4><?php echo esc_html( $exercise['title'] ); ?></h4>
						<?php if ( '' !== $exercise['dosage'] ) : ?>
							<p class="gear-engine-program__reps"><?php echo esc_html( $exercise['dosage'] ); ?></p>
						<?php endif; ?>
						<?php if ( '' !== $exercise['description'] ) : ?>
							<p><?php echo esc_html( $exercise['description'] ); ?></p>
						<?php elseif ( '' !== $exercise['summary'] ) : ?>
							<p><?php echo esc_html( $exercise['summary'] ); ?></p>
						<?php endif; ?>
						<?php if ( '' !== $embed ) : ?>
							<div class="gear-engine-program__video" data-embed="<?php echo esc_url( $embed ); ?>" data-title="<?php echo esc_attr( $exercise['title'] ); ?>">
								<button type="button" class="gear-engine-program__play gear-engine-program__noprint"><?php esc_html_e( 'Se video', 'gear-engine' ); ?></button>
							</div>
							<?php if ( '' !== $exercise['attribution_text'] && '' !== $exercise['attribution_url'] ) : ?>
								<p class="gear-engine-program__credit gear-engine-program__noprint"><a href="<?php echo esc_url( $exercise['attribution_url'] ); ?>" target="_blank" rel="noopener noreferrer"><?php echo esc_html( $exercise['attribution_text'] ); ?></a></p>
							<?php endif; ?>
						<?php endif; ?>
					</li>
				<?php endforeach; ?>
			</ol>
		</section>
	<?php endforeach; ?>

	<p class="gear-engine-program__disclaimer">
		<?php esc_html_e( 'Programmet er generell veiledning og erstatter ikke undersøkelse av helsepersonell. Stopp og kontakt lege ved økende smerter eller nye symptomer.', 'gear-engine' ); ?>
	</p>
	<div class="gear-engine-program__actions gear-engine-program__noprint">
		<button type="button" class="gear-engine-program__btn gear-engine-program__btn--secondary" data-action="print"><?php esc_html_e( 'Skriv ut', 'gear-engine' ); ?></button>
		<?php if ( ! empty( $change_url ) ) : ?>
			<a class="gear-engine-program__btn gear-engine-program__btn--secondary" href="<?php echo esc_url( $change_url ); ?>"><?php esc_html_e( 'Endre svar', 'gear-engine' ); ?></a>
		<?php endif; ?>
	</div>
</div>
