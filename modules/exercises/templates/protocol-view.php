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
		<div class="gear-engine-program__badge">
			<span class="gear-engine-program__badge-dot"></span>
			<?php esc_html_e( 'Kvalitetssikret treningsprogram', 'gear-engine' ); ?>
		</div>
		<h2 class="gear-engine-program__title"><?php echo esc_html( $protocol['name'] ); ?></h2>
		<?php if ( '' !== $protocol['summary'] ) : ?>
			<p class="gear-engine-program__summary"><?php echo esc_html( $protocol['summary'] ); ?></p>
		<?php endif; ?>
		<?php if ( '' !== $protocol['dosage'] ) : ?>
			<div class="gear-engine-program__dosage">
				<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="var(--ge-icon-stroke, 1.75px)" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="12" cy="12" r="9"/><polyline points="12 7 12 12 15 15"/></svg>
				<span><strong><?php esc_html_e( 'Dosering:', 'gear-engine' ); ?></strong> <?php echo esc_html( $protocol['dosage'] ); ?></span>
			</div>
		<?php endif; ?>
		<?php if ( '' !== $protocol['red_flags'] ) : ?>
			<div class="gear-engine-program__warning" role="note">
				<div class="gear-engine-program__warning-icon">
					<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="var(--ge-icon-stroke, 1.75px)" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M12 3L2 20h20L12 3zm0 6v5m0 3h.01"/></svg>
				</div>
				<div class="gear-engine-program__warning-content">
					<strong><?php esc_html_e( 'Avbryt og kontakt lege hvis:', 'gear-engine' ); ?></strong>
					<p><?php echo esc_html( $protocol['red_flags'] ); ?></p>
				</div>
			</div>
		<?php endif; ?>
	</header>

	<?php foreach ( $groups as $group ) : ?>
		<section class="gear-engine-program__phase">
			<div class="gear-engine-program__phase-header">
				<?php if ( '' !== $group['name'] ) : ?>
					<div class="gear-engine-program__phase-pill">
						<span class="gear-engine-program__phase-pill-indicator"></span>
						<h3 class="gear-engine-program__phase-title"><?php echo esc_html( $group['name'] ); ?></h3>
					</div>
				<?php endif; ?>
				<?php if ( '' !== $group['description'] ) : ?>
					<p class="gear-engine-program__phase-desc"><?php echo esc_html( $group['description'] ); ?></p>
				<?php endif; ?>
			</div>

			<ol class="gear-engine-program__list">
				<?php foreach ( $group['exercises'] as $exercise_idx => $exercise ) : ?>
					<?php $embed = Gear_Engine_Protocol_Viewer::get_embed_url( $exercise ); ?>
					<li class="gear-engine-program__exercise">
						<div class="gear-engine-program__exercise-header">
							<div class="gear-engine-program__exercise-num"><?php echo esc_html( (string) ( $exercise_idx + 1 ) ); ?></div>
							<div class="gear-engine-program__exercise-meta">
								<h4><?php echo esc_html( $exercise['title'] ); ?></h4>
								<div class="gear-engine-program__exercise-pills">
									<?php if ( '' !== $exercise['dosage'] ) : ?>
										<span class="gear-engine-program__reps">
											<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="var(--ge-icon-stroke, 1.75px)" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="12" cy="12" r="9"/><polyline points="12 7 12 12 15 15"/></svg>
											<?php echo esc_html( $exercise['dosage'] ); ?>
										</span>
									<?php endif; ?>
									<?php if ( ! empty( $exercise['equipment'] ) && is_array( $exercise['equipment'] ) ) : ?>
										<?php foreach ( $exercise['equipment'] as $item ) : ?>
											<span class="gear-engine-program__equip-pill">
												<svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="var(--ge-icon-stroke, 1.75px)" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M6 5v14M18 5v14M2 9v6M22 9v6M6 12h12"/></svg>
												<?php echo esc_html( $item ); ?>
											</span>
										<?php endforeach; ?>
									<?php endif; ?>
								</div>
							</div>
						</div>

						<div class="gear-engine-program__exercise-body">
							<?php if ( '' !== $exercise['description'] ) : ?>
								<p><?php echo esc_html( $exercise['description'] ); ?></p>
							<?php elseif ( '' !== $exercise['summary'] ) : ?>
								<p><?php echo esc_html( $exercise['summary'] ); ?></p>
							<?php endif; ?>
						</div>

						<?php
						$non_bodyweight_gear = array();
						if ( ! empty( $exercise['equipment_details'] ) && is_array( $exercise['equipment_details'] ) ) {
							foreach ( $exercise['equipment_details'] as $gear_item ) {
								if ( empty( $gear_item['is_bodyweight'] ) ) {
									$non_bodyweight_gear[] = $gear_item;
								}
							}
						}
						?>
						<?php if ( ! empty( $non_bodyweight_gear ) ) : ?>
							<div class="gear-engine-program__gear-section">
								<?php foreach ( $non_bodyweight_gear as $gear ) : ?>
									<div class="gear-engine-program__gear-item">
										<div class="gear-engine-program__gear-details">
											<div class="gear-engine-program__gear-header">
												<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="var(--ge-icon-stroke, 1.75px)" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M6 5v14M18 5v14M2 9v6M22 9v6M6 12h12"/></svg>
												<strong><?php echo esc_html( $gear['title'] ); ?></strong>
											</div>
											<?php if ( ! empty( $gear['free_alt'] ) ) : ?>
												<p class="gear-engine-program__gear-free">
													<span class="gear-engine-program__gear-free-label"><?php esc_html_e( 'Gratis hjemme:', 'gear-engine' ); ?></span>
													<?php echo esc_html( $gear['free_alt'] ); ?>
												</p>
											<?php endif; ?>
										</div>
										<?php if ( ! empty( $gear['picker_url'] ) ) : ?>
											<div class="gear-engine-program__gear-action gear-engine-program__noprint">
												<a href="<?php echo esc_url( $gear['picker_url'] ); ?>" class="gear-engine-program__gear-btn" target="_blank" rel="noopener">
													<span><?php echo esc_html( ! empty( $gear['picker_label'] ) ? $gear['picker_label'] : __( 'Se veileder', 'gear-engine' ) ); ?></span>
													<svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M5 12h14M12 5l7 7-7 7"/></svg>
												</a>
											</div>
										<?php endif; ?>
									</div>
								<?php endforeach; ?>
							</div>
						<?php endif; ?>

						<?php if ( '' !== $embed ) : ?>
							<div class="gear-engine-program__video" data-embed="<?php echo esc_url( $embed ); ?>" data-title="<?php echo esc_attr( $exercise['title'] ); ?>">
								<button type="button" class="gear-engine-program__play gear-engine-program__noprint">
									<svg width="16" height="16" viewBox="0 0 24 24" fill="currentColor" stroke="none" aria-hidden="true"><polygon points="6 4 20 12 6 20 6 4"/></svg>
									<span><?php esc_html_e( 'Se video', 'gear-engine' ); ?></span>
								</button>
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

	<div class="gear-engine-program__disclaimer">
		<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="var(--ge-icon-stroke, 1.75px)" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="12" cy="12" r="9"/><line x1="12" y1="16" x2="12" y2="12"/><circle cx="12" cy="8.5" r="0.75" fill="currentColor"/></svg>
		<p><?php esc_html_e( 'Programmet er generell veiledning og erstatter ikke undersøkelse av helsepersonell. Stopp og kontakt lege ved økende smerter eller nye symptomer.', 'gear-engine' ); ?></p>
	</div>

	<div class="gear-engine-program__actions gear-engine-program__noprint">
		<button type="button" class="gear-engine-program__btn gear-engine-program__btn--secondary" data-action="print">
			<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="var(--ge-icon-stroke, 1.75px)" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M6 9V3h12v6m-12 9H4a2 2 0 0 1-2-2v-5a2 2 0 0 1 2-2h16a2 2 0 0 1 2 2v5a2 2 0 0 1-2 2h-2m-12-4h12v7H6z"/></svg>
			<span><?php esc_html_e( 'Skriv ut', 'gear-engine' ); ?></span>
		</button>
		<?php if ( ! empty( $change_url ) ) : ?>
			<a class="gear-engine-program__btn gear-engine-program__btn--secondary" href="<?php echo esc_url( $change_url ); ?>">
				<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="var(--ge-icon-stroke, 1.75px)" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M12 20h9"/><path d="M16.5 3.5a2.121 2.121 0 0 1 3 3L7 19l-4 1 1-4L16.5 3.5z"/></svg>
				<span><?php esc_html_e( 'Endre svar', 'gear-engine' ); ?></span>
			</a>
		<?php endif; ?>
	</div>
</div>
