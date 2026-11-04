<?php
/**
 * Protocol (training program) page template.
 *
 * Supports interactive step-by-step workout runner (stepper) with video embeds,
 * tabs, and "Next exercise" navigation, as well as a full overview list view.
 *
 * Expects $protocol (array) and $groups (array) from Gear_Engine_Protocol_Viewer::render();
 * $change_url (optional) links back to the phase questions of an automatic program.
 *
 * @package GearEngine
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

$total_exercises = 0;
foreach ( $groups as $grp ) {
	$total_exercises += count( $grp['exercises'] );
}
?>
<div class="gear-engine-program" data-active-mode="stepper">
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

	<!-- View Mode Switcher (Steg for steg vs Full oversikt) -->
	<div class="gear-engine-program__toolbar gear-engine-program__noprint">
		<div class="gear-engine-program__view-modes" role="tablist" aria-label="<?php esc_attr_e( 'Visningsmodus', 'gear-engine' ); ?>">
			<button type="button" class="gear-engine-program__mode-btn gear-engine-program__mode-btn--active" data-view-mode="stepper" role="tab" aria-selected="true">
				<svg width="16" height="16" viewBox="0 0 24 24" fill="currentColor" stroke="none" aria-hidden="true"><polygon points="6 4 20 12 6 20 6 4"/></svg>
				<span><?php esc_html_e( 'Steg for steg', 'gear-engine' ); ?></span>
			</button>
			<button type="button" class="gear-engine-program__mode-btn" data-view-mode="list" role="tab" aria-selected="false">
				<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><line x1="8" y1="6" x2="21" y2="6"/><line x1="8" y1="12" x2="21" y2="12"/><line x1="8" y1="18" x2="21" y2="18"/><circle cx="4" cy="6" r="1.5" fill="currentColor"/><circle cx="4" cy="12" r="1.5" fill="currentColor"/><circle cx="4" cy="18" r="1.5" fill="currentColor"/></svg>
				<span><?php esc_html_e( 'Full oversikt', 'gear-engine' ); ?></span>
			</button>
		</div>
	</div>

	<?php if ( count( $groups ) > 1 ) : ?>
		<!-- Phase Selector Tabs (For multi-phase clinical protocols) -->
		<div class="gear-engine-program__phase-tabs gear-engine-program__noprint" role="tablist" aria-label="<?php esc_attr_e( 'Rehabiliteringsfaser', 'gear-engine' ); ?>">
			<?php foreach ( $groups as $p_idx => $grp ) : ?>
				<button type="button" class="gear-engine-program__phase-tab <?php echo 0 === $p_idx ? 'gear-engine-program__phase-tab--active' : ''; ?>" data-phase-index="<?php echo esc_attr( (string) $p_idx ); ?>" role="tab" aria-selected="<?php echo 0 === $p_idx ? 'true' : 'false'; ?>">
					<span class="gear-engine-program__phase-tab-dot"></span>
					<span class="gear-engine-program__phase-tab-title"><?php echo esc_html( '' !== $grp['name'] ? $grp['name'] : sprintf( __( 'Fase %d', 'gear-engine' ), $p_idx + 1 ) ); ?></span>
					<span class="gear-engine-program__phase-count"><?php echo esc_html( (string) count( $grp['exercises'] ) ); ?></span>
				</button>
			<?php endforeach; ?>
		</div>
	<?php endif; ?>

	<!-- =========================================================
	     1. INTERACTIVE STEPPER VIEW (Default)
	     ========================================================= -->
	<div class="gear-engine-program__stepper-view gear-engine-program__noprint">
		<?php foreach ( $groups as $p_idx => $group ) : ?>
			<div class="gear-engine-program__stepper-phase <?php echo 0 === $p_idx ? 'gear-engine-program__stepper-phase--active' : ''; ?>" data-phase="<?php echo esc_attr( (string) $p_idx ); ?>" <?php echo 0 !== $p_idx ? 'style="display:none;"' : ''; ?>>
				
				<?php if ( '' !== $group['description'] ) : ?>
					<div class="gear-engine-program__phase-callout">
						<div class="gear-engine-program__phase-callout-icon">
							<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="12" cy="12" r="10"/><path d="M12 16v-4"/><path d="M12 8h.01"/></svg>
						</div>
						<p><?php echo esc_html( $group['description'] ); ?></p>
					</div>
				<?php endif; ?>

				<!-- App-style Workout Card Container (Identical to AI Chat Modal) -->
				<div class="gear-engine-program__workout-card">
					<!-- Card Top Header -->
					<div class="gear-engine-program__card-header">
						<div class="gear-engine-program__card-header-main">
							<span class="gear-engine-program__card-badge"><?php esc_html_e( 'Kvalitetssikret treningsprogram', 'gear-engine' ); ?></span>
							<h3 class="gear-engine-program__card-title"><?php echo esc_html( $protocol['name'] ); ?></h3>
						</div>
						<button type="button" onclick="window.print()" class="gear-engine-program__card-print-btn" title="<?php esc_attr_e( 'Skriv ut øvelser', 'gear-engine' ); ?>" aria-label="<?php esc_attr_e( 'Skriv ut øvelser', 'gear-engine' ); ?>">
							<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><polyline points="6 9 6 2 18 2 18 9"/><path d="M6 18H4a2 2 0 0 1-2-2v-5a2 2 0 0 1 2-2h16a2 2 0 0 1 2 2v5a2 2 0 0 1-2 2h-2"/><rect x="6" y="14" width="12" height="8"/></svg>
						</button>
					</div>

					<!-- Horizontal Exercise Navigation Tabs -->
					<div class="gear-engine-program__stepper-tabs no-scrollbar" role="tablist">
						<?php foreach ( $group['exercises'] as $ex_idx => $exercise ) : ?>
							<button type="button" class="gear-engine-program__stepper-tab <?php echo 0 === $ex_idx ? 'gear-engine-program__stepper-tab--active' : ''; ?>" data-step="<?php echo esc_attr( (string) $ex_idx ); ?>" role="tab" aria-selected="<?php echo 0 === $ex_idx ? 'true' : 'false'; ?>">
								<span><?php echo esc_html( sprintf( __( 'Øvelse %d', 'gear-engine' ), $ex_idx + 1 ) ); ?></span>
							</button>
						<?php endforeach; ?>
					</div>

					<!-- Step Cards (One Active at a Time) -->
					<div class="gear-engine-program__step-cards-container">
						<?php foreach ( $group['exercises'] as $ex_idx => $exercise ) : ?>
							<?php $embed = Gear_Engine_Protocol_Viewer::get_embed_url( $exercise ); ?>
							<div class="gear-engine-program__step-card <?php echo 0 === $ex_idx ? 'gear-engine-program__step-card--active' : ''; ?>" data-step-card="<?php echo esc_attr( (string) $ex_idx ); ?>" <?php echo 0 !== $ex_idx ? 'style="display:none;"' : ''; ?>>
								
								<!-- Video / Media Player Container -->
								<div class="gear-engine-program__stepper-media">
									<div class="gear-engine-program__step-counter-tag">
										<?php echo esc_html( sprintf( '%1$d/%2$d', $ex_idx + 1, count( $group['exercises'] ) ) ); ?>
									</div>
									<?php if ( '' !== $embed ) : ?>
										<div class="gear-engine-program__video-wrapper gear-engine-program__video" data-embed="<?php echo esc_url( $embed ); ?>" data-title="<?php echo esc_attr( $exercise['title'] ); ?>">
											<button type="button" class="gear-engine-program__play gear-engine-program__noprint">
												<svg width="22" height="22" viewBox="0 0 24 24" fill="currentColor" stroke="none" aria-hidden="true"><polygon points="6 4 20 12 6 20 6 4"/></svg>
												<span><?php esc_html_e( 'Se video', 'gear-engine' ); ?></span>
											</button>
										</div>
										<?php if ( '' !== $exercise['attribution_text'] && '' !== $exercise['attribution_url'] ) : ?>
											<p class="gear-engine-program__credit gear-engine-program__noprint">
												<a href="<?php echo esc_url( $exercise['attribution_url'] ); ?>" target="_blank" rel="noopener noreferrer">
													<?php echo esc_html( $exercise['attribution_text'] ); ?>
												</a>
											</p>
										<?php endif; ?>
									<?php else : ?>
										<div class="gear-engine-program__video-placeholder">
											<div class="gear-engine-program__placeholder-icon">
												<svg width="40" height="40" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="12" cy="12" r="10"/><polygon points="10 8 16 12 10 16 10 8"/></svg>
											</div>
											<span class="gear-engine-program__placeholder-title"><?php echo esc_html( $exercise['title'] ); ?></span>
										</div>
									<?php endif; ?>
								</div>

								<!-- Exercise Title -->
								<h3 class="gear-engine-program__step-title"><?php echo esc_html( $exercise['title'] ); ?></h3>

								<!-- Dosage & Attribute Pills -->
								<div class="gear-engine-program__step-pills">
									<?php if ( '' !== $exercise['dosage'] ) : ?>
										<span class="gear-engine-program__reps-pill">
											<svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M21.5 2v6h-6M21.34 15.57a10 10 0 1 1-.57-8.38l5.67-5.67"/></svg>
											<span><?php echo esc_html( $exercise['dosage'] ); ?></span>
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
									<?php if ( ! empty( $exercise['role'] ) ) : ?>
										<span class="gear-engine-program__role-pill">
											<?php echo esc_html( ucfirst( (string) $exercise['role'] ) ); ?>
										</span>
									<?php endif; ?>
								</div>

								<!-- Step-by-Step Instructions -->
								<div class="gear-engine-program__instructions-card">
									<h4 class="gear-engine-program__instructions-title"><?php esc_html_e( 'Slik gjør du:', 'gear-engine' ); ?></h4>
									<div class="gear-engine-program__instructions-body">
										<?php
										$raw_desc = '' !== $exercise['description'] ? $exercise['description'] : $exercise['summary'];
										$lines    = array_filter( array_map( 'trim', explode( "\n", $raw_desc ) ) );
										if ( count( $lines ) > 1 ) :
											?>
											<ul class="gear-engine-program__instructions-list">
												<?php foreach ( $lines as $line ) : ?>
													<?php $clean_line = preg_replace( '/^[-*•\d\.]+\s*/', '', $line ); ?>
													<li><?php echo esc_html( $clean_line ); ?></li>
												<?php endforeach; ?>
											</ul>
										<?php else : ?>
											<p><?php echo esc_html( $raw_desc ); ?></p>
										<?php endif; ?>
									</div>
								</div>

								<!-- Equipment Recommendation Box -->
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

							</div>
						<?php endforeach; ?>
					</div>

					<!-- Stepper Bottom Navigation Bar -->
					<div class="gear-engine-program__stepper-nav gear-engine-program__noprint">
						<div class="gear-engine-program__stepper-nav-inner">
							<button type="button" class="gear-engine-program__nav-btn gear-engine-program__nav-btn--prev" data-nav="prev" disabled aria-label="<?php esc_attr_e( 'Forrige øvelse', 'gear-engine' ); ?>">
								<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M15 18l-6-6 6-6"/></svg>
							</button>
							<button type="button" class="gear-engine-program__nav-btn gear-engine-program__nav-btn--next" data-nav="next">
								<span><?php esc_html_e( 'Neste', 'gear-engine' ); ?></span>
								<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M9 18l6-6-6-6"/></svg>
							</button>
							<button type="button" class="gear-engine-program__nav-btn gear-engine-program__nav-btn--finish" data-nav="finish" style="display: none;">
								<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><polyline points="20 6 9 17 4 12"/></svg>
								<span><?php esc_html_e( 'Fullfør økten', 'gear-engine' ); ?></span>
							</button>
						</div>
					</div>

					<!-- Workout Completion Celebration Screen -->
					<div class="gear-engine-program__completion-card" style="display: none;">
						<div class="gear-engine-program__completion-circle">
							<svg width="36" height="36" viewBox="0 0 24 24" fill="none" stroke="#00A878" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><polyline points="20 6 9 17 4 12"/></svg>
						</div>
						<h3 class="gear-engine-program__completion-title"><?php esc_html_e( 'Flott gjennomført!', 'gear-engine' ); ?></h3>
						<p class="gear-engine-program__completion-desc">
							<?php esc_html_e( 'Du har fullført alle øvelsene i denne økten. God rehabilitering bygger på jevn belastning over tid.', 'gear-engine' ); ?>
						</p>
						<div class="gear-engine-program__completion-actions">
							<button type="button" class="gear-engine-program__btn gear-engine-program__btn--secondary" data-action="restart-workout">
								<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><polyline points="1 4 1 10 7 10"/><path d="M3.51 15a9 9 0 1 0 2.13-9.36L1 10"/></svg>
								<span><?php esc_html_e( 'Start økten på nytt', 'gear-engine' ); ?></span>
							</button>
							<?php if ( $p_idx + 1 < count( $groups ) ) : ?>
								<button type="button" class="gear-engine-program__btn gear-engine-program__btn--primary" data-action="next-phase" data-target-phase="<?php echo esc_attr( (string) ( $p_idx + 1 ) ); ?>">
									<span><?php esc_html_e( 'Gå til neste fase', 'gear-engine' ); ?></span>
									<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M5 12h14M12 5l7 7-7 7"/></svg>
								</button>
							<?php endif; ?>
						</div>
					</div>

				</div><!-- .gear-engine-program__workout-card -->

			</div>
		<?php endforeach; ?>
	</div>

	<!-- =========================================================
	     2. FULL OVERVIEW LIST VIEW (Alternative + Print)
	     ========================================================= -->
	<div class="gear-engine-program__list-view" style="display: none;">
		<?php foreach ( $groups as $p_idx => $group ) : ?>
			<section class="gear-engine-program__phase" data-list-phase="<?php echo esc_attr( (string) $p_idx ); ?>">
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
								<div class="gear-engine-program__exercise-action gear-engine-program__noprint">
									<button type="button" class="gear-engine-program__start-step-btn" data-jump-phase="<?php echo esc_attr( (string) $p_idx ); ?>" data-jump-step="<?php echo esc_attr( (string) $exercise_idx ); ?>" title="<?php esc_attr_e( 'Start i steg-for-steg', 'gear-engine' ); ?>">
										<svg width="14" height="14" viewBox="0 0 24 24" fill="currentColor" stroke="none" aria-hidden="true"><polygon points="6 4 20 12 6 20 6 4"/></svg>
										<span><?php esc_html_e( 'Start her', 'gear-engine' ); ?></span>
									</button>
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
									<p class="gear-engine-program__credit gear-engine-program__noprint">
										<a href="<?php echo esc_url( $exercise['attribution_url'] ); ?>" target="_blank" rel="noopener noreferrer">
											<?php echo esc_html( $exercise['attribution_text'] ); ?>
										</a>
									</p>
								<?php endif; ?>
							<?php endif; ?>
						</li>
					<?php endforeach; ?>
				</ol>
			</section>
		<?php endforeach; ?>
	</div>

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
