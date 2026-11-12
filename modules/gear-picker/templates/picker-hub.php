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
		<?php
		$counts = array( 'all' => count( $items ) );
		foreach ( $items as $it ) {
			$c = $it['category'] ?? '';
			if ( $c ) {
				$counts[ $c ] = ( $counts[ $c ] ?? 0 ) + 1;
			}
		}
		?>
		<div class="gear-engine-hub__chips" role="tablist" aria-label="<?php esc_attr_e( 'Filtrer etter treningskategori', 'gear-engine' ); ?>">
			<?php foreach ( $categories as $cat_id => $cat ) : ?>
				<?php
				$is_active = ( $cat_id === $initial_category || ( '' === $initial_category && 'all' === $cat_id ) );
				$count     = $counts[ $cat_id ] ?? 0;
				?>
				<button type="button"
					class="gear-engine-hub__chip fp-chip<?php echo $is_active ? ' gear-engine-hub__chip--active' : ''; ?>"
					data-category="<?php echo esc_attr( $cat_id ); ?>"
					role="tab"
					aria-pressed="<?php echo $is_active ? 'true' : 'false'; ?>">
					<span class="gear-engine-hub__chip-check chip-check material-symbols-outlined" aria-hidden="true">check</span>
					<span class="gear-engine-hub__chip-label"><?php echo esc_html( $cat['label'] ); ?></span>
					<span class="gear-engine-hub__chip-count chip-count"><?php echo esc_html( (string) $count ); ?></span>
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
			<?php if ( $is_available ) : ?>
				<a class="gear-engine-hub__card fp-tile"
					href="<?php echo esc_url( $card_href ); ?>"
					data-category="<?php echo esc_attr( $item['category'] ); ?>"
					aria-label="<?php echo esc_attr( $item['title'] ); ?>">
					<div class="gear-engine-hub__card-media tile-media">
						<?php if ( ! empty( $item['image'] ) ) : ?>
							<img src="<?php echo esc_url( $item['image'] ); ?>" alt="<?php echo esc_attr( $item['title'] ); ?>" class="gear-engine-hub__card-img tile-image" loading="lazy" />
						<?php else : ?>
							<div class="gear-engine-hub__card-icon-box">
								<?php echo Gear_Engine_Gear_Picker::get_hub_icon( (string) ( $item['icon'] ?? '' ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
							</div>
						<?php endif; ?>
					</div>
					<div class="gear-engine-hub__card-title tile-label"><?php echo esc_html( $item['title'] ); ?></div>
				</a>
			<?php else : ?>
				<div class="gear-engine-hub__card gear-engine-hub__card--upcoming fp-tile upcoming"
					data-category="<?php echo esc_attr( $item['category'] ); ?>"
					tabindex="0"
					role="button"
					aria-disabled="true"
					aria-label="<?php echo esc_attr( $item['title'] . ' (' . __( 'Kommer snart', 'gear-engine' ) . ')' ); ?>">
					<div class="gear-engine-hub__card-media tile-media">
						<?php if ( ! empty( $item['image'] ) ) : ?>
							<img src="<?php echo esc_url( $item['image'] ); ?>" alt="<?php echo esc_attr( $item['title'] ); ?>" class="gear-engine-hub__card-img tile-image" loading="lazy" />
						<?php else : ?>
							<div class="gear-engine-hub__card-icon-box">
								<?php echo Gear_Engine_Gear_Picker::get_hub_icon( (string) ( $item['icon'] ?? '' ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
							</div>
						<?php endif; ?>
					</div>
					<div class="gear-engine-hub__card-title tile-label"><?php echo esc_html( $item['title'] ); ?></div>
					<div class="gear-engine-hub__card-sub tile-sub"><?php esc_html_e( 'Kommer snart', 'gear-engine' ); ?></div>
				</div>
			<?php endif; ?>
		<?php endforeach; ?>
	</div>

	<!-- Price Comparison Cross-Link Banner -->
	<aside class="gear-engine-hub__price-banner" aria-label="<?php esc_attr_e( 'Prissammenligning', 'gear-engine' ); ?>">
		<div class="gear-engine-hub__price-banner-content">
			<div class="gear-engine-hub__price-banner-icon">
				<span class="material-symbols-outlined" aria-hidden="true" style="font-size: 22px; line-height: 1;">sell</span>
			</div>
			<div class="gear-engine-hub__price-banner-text">
				<strong><?php esc_html_e( 'Vet du allerede hva du leter etter?', 'gear-engine' ); ?></strong>
				<span><?php esc_html_e( 'Sammenlign dagspriser, tekniske spesifikasjoner og tilbud i vår komplette priskatalog.', 'gear-engine' ); ?></span>
			</div>
		</div>
		<a href="<?php echo esc_url( function_exists( 'home_url' ) ? home_url( '/priser/' ) : '/priser/' ); ?>" class="gear-engine-hub__price-banner-btn">
			<span><?php esc_html_e( 'Gå til Prissammenligning', 'gear-engine' ); ?></span>
			<span class="material-symbols-outlined" aria-hidden="true" style="font-size: 18px; line-height: 1;">arrow_forward</span>
		</a>
	</aside>

	<!-- Trust Signals Footer -->
	<div class="gear-engine-hub__trust-banner">
		<div class="gear-engine-hub__trust-item">
			<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/></svg>
			<span><strong><?php esc_html_e( '100% uavhengig scoring:', 'gear-engine' ); ?></strong> <?php esc_html_e( 'Rådene styres av dine fysiske forutsetninger, ikke butikkfortjeneste.', 'gear-engine' ); ?></span>
		</div>
		<div class="gear-engine-hub__trust-item">
			<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M22 12h-4l-3 9L9 3l-3 9H2"/></svg>
			<span><strong><?php esc_html_e( 'Daglige prisoppdateringer:', 'gear-engine' ); ?></strong> <?php esc_html_e( 'Vi sjekker lagerstatus og rabatter automatisk på tvers av ledende forhandlere.', 'gear-engine' ); ?></span>
		</div>
	</div>
</div>

