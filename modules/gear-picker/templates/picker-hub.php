<?php
/**
 * Gear picker hub template.
 *
 * Expects $categories (array), $items (array) and $initial_category (string) from Gear_Engine_Gear_Picker::render_hub().
 *
 * @package GearEngine
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

if ( ! function_exists( 'gear_engine_hub_icon' ) ) {
	/**
	 * Return an inline SVG icon for a hub card.
	 *
	 * @param string $icon Icon key.
	 * @return string SVG markup.
	 */
	function gear_engine_hub_icon( string $icon ): string {
		return Gear_Engine_Gear_Picker::get_hub_icon( $icon );
	}
}
?>
<div class="gear-engine-hub" data-initial-category="<?php echo esc_attr( $initial_category ); ?>">
	<header class="gear-engine-hub__header">
		<div class="gear-engine-hub__badge">
			<span class="gear-engine-hub__badge-dot"></span>
			<?php esc_html_e( 'Smarte kjøpsveiledere · 1 minutt', 'gear-engine' ); ?>
		</div>
		<h1 class="gear-engine-hub__title"><?php esc_html_e( 'Finn riktig treningsutstyr for kroppen din', 'gear-engine' ); ?></h1>
		<p class="gear-engine-hub__ingress">
			<?php esc_html_e( 'Velg kategori eller ta en 1-minutters test for å finne optimal modell, riktig demping og beste dagspris.', 'gear-engine' ); ?>
		</p>

		<!-- Category Filter Chips -->
		<div class="gear-engine-hub__chips" role="tablist" aria-label="<?php esc_attr_e( 'Filtrer etter treningskategori', 'gear-engine' ); ?>">
			<?php foreach ( $categories as $cat_id => $cat ) : ?>
				<button type="button"
					class="gear-engine-hub__chip<?php echo $cat_id === $initial_category || ( '' === $initial_category && 'all' === $cat_id ) ? ' gear-engine-hub__chip--active' : ''; ?>"
					data-category="<?php echo esc_attr( $cat_id ); ?>"
					role="tab"
					aria-pressed="<?php echo $cat_id === $initial_category || ( '' === $initial_category && 'all' === $cat_id ) ? 'true' : 'false'; ?>">
					<?php if ( ! empty( $cat['emoji'] ) ) : ?>
						<span class="gear-engine-hub__chip-emoji"><?php echo esc_html( $cat['emoji'] ); ?></span>
					<?php endif; ?>
					<span><?php echo esc_html( $cat['label'] ); ?></span>
				</button>
			<?php endforeach; ?>
		</div>
	</header>

	<!-- Cards Grid -->
	<div class="gear-engine-hub__grid">
		<?php foreach ( $items as $item ) : ?>
			<?php
			$is_available = ! empty( $item['available'] );
			$card_href    = $is_available
				? esc_url( add_query_arg( 'type', $item['slug'] ) )
				: '#';
			?>
			<div class="gear-engine-hub__card<?php echo ! $is_available ? ' gear-engine-hub__card--upcoming' : ''; ?>"
				data-category="<?php echo esc_attr( $item['category'] ); ?>"
				<?php if ( $is_available ) : ?>
					onclick="window.location.href='<?php echo esc_url( $card_href ); ?>';"
					role="link"
					tabindex="0"
					style="cursor: pointer;"
					onkeydown="if(event.key==='Enter'||event.key===' '){event.preventDefault();window.location.href='<?php echo esc_url( $card_href ); ?>';}"
				<?php endif; ?>>
				<div class="gear-engine-hub__card-media">
					<?php if ( ! empty( $item['image'] ) ) : ?>
						<img src="<?php echo esc_url( $item['image'] ); ?>" alt="<?php echo esc_attr( $item['title'] ); ?>" class="gear-engine-hub__card-img" loading="lazy" />
					<?php else : ?>
						<div class="gear-engine-hub__card-img-placeholder">
							<div class="gear-engine-hub__card-icon-box">
								<?php echo Gear_Engine_Gear_Picker::get_hub_icon( (string) ( $item['icon'] ?? '' ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- hardcoded clean SVG helper ?>
							</div>
						</div>
					<?php endif; ?>
					<div class="gear-engine-hub__card-overlays">
						<span class="gear-engine-hub__card-cat-pill">
							<?php echo esc_html( $item['category_name'] ); ?>
						</span>
						<?php if ( ! empty( $item['badge'] ) ) : ?>
							<span class="gear-engine-hub__card-badge<?php echo ! $is_available ? ' gear-engine-hub__card-badge--muted' : ''; ?>">
								<?php echo esc_html( $item['badge'] ); ?>
							</span>
						<?php endif; ?>
					</div>
				</div>

				<div class="gear-engine-hub__card-content">
					<div class="gear-engine-hub__card-body">
						<h3 class="gear-engine-hub__card-title"><?php echo esc_html( $item['title'] ); ?></h3>
						<p class="gear-engine-hub__card-desc"><?php echo esc_html( $item['desc'] ); ?></p>
					</div>

					<div class="gear-engine-hub__card-footer">
						<span class="gear-engine-hub__card-time">
							<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="var(--ge-icon-stroke, 1.75px)" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="12" cy="12" r="9"/><polyline points="12 7 12 12 15 15"/></svg>
							<?php echo esc_html( $item['time'] ); ?>
						</span>
						<?php if ( $is_available ) : ?>
							<a href="<?php echo esc_url( $card_href ); ?>" class="gear-engine-hub__card-action-btn" onclick="event.stopPropagation();">
								<span><?php esc_html_e( 'Ta testen', 'gear-engine' ); ?></span>
								<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="var(--ge-icon-stroke, 1.75px)" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M5 12h14m-6-6l6 6-6 6"/></svg>
							</a>
						<?php else : ?>
							<span class="gear-engine-hub__card-action-muted">
								<?php esc_html_e( 'Kommer snart', 'gear-engine' ); ?>
							</span>
						<?php endif; ?>
					</div>
				</div>
			</div>
		<?php endforeach; ?>
	</div>

	<!-- Price Comparison Cross-Link Banner -->
	<aside class="gear-engine-hub__price-banner" aria-label="<?php esc_attr_e( 'Prissammenligning', 'gear-engine' ); ?>">
		<div class="gear-engine-hub__price-banner-content">
			<div class="gear-engine-hub__price-banner-icon">
				<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
					<line x1="12" y1="1" x2="12" y2="23"></line>
					<path d="M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"></path>
				</svg>
			</div>
			<div class="gear-engine-hub__price-banner-text">
				<strong><?php esc_html_e( 'Vet du allerede hva du leter etter?', 'gear-engine' ); ?></strong>
				<span><?php esc_html_e( 'Sammenlign dagspriser, tekniske spesifikasjoner og tilbud i vår komplette priskatalog.', 'gear-engine' ); ?></span>
			</div>
		</div>
		<a href="<?php echo esc_url( function_exists( 'home_url' ) ? home_url( '/priser/' ) : '/priser/' ); ?>" class="gear-engine-hub__price-banner-btn">
			<span><?php esc_html_e( 'Gå til Prissammenligning', 'gear-engine' ); ?></span>
			<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
				<line x1="5" y1="12" x2="19" y2="12"></line>
				<polyline points="12 5 19 12 12 19"></polyline>
			</svg>
		</a>
	</aside>

	<!-- Trust Signals Footer -->
	<div class="gear-engine-hub__trust-banner">
		<div class="gear-engine-hub__trust-item">
			<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="var(--ge-icon-stroke, 1.75px)" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/><polyline points="9 12 11 14 15 10"/></svg>
			<span><strong><?php esc_html_e( '100% uavhengig scoring:', 'gear-engine' ); ?></strong> <?php esc_html_e( 'Rådene styres av dine fysiske forutsetninger, ikke butikkfortjeneste.', 'gear-engine' ); ?></span>
		</div>
		<div class="gear-engine-hub__trust-item">
			<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="var(--ge-icon-stroke, 1.75px)" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="12" cy="12" r="9"/><polyline points="12 7 12 12 15 15"/></svg>
			<span><strong><?php esc_html_e( 'Daglige prisoppdateringer:', 'gear-engine' ); ?></strong> <?php esc_html_e( 'Vi sjekker lagerstatus og rabatter automatisk på tvers av ledende forhandlere.', 'gear-engine' ); ?></span>
		</div>
	</div>
</div>

