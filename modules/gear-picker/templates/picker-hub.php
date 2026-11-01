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

/**
 * Return an inline SVG icon for a hub card.
 *
 * @param string $icon Icon key.
 * @return string SVG markup.
 */
function gear_engine_hub_icon( string $icon ): string {
	$stroke = 'stroke="currentColor" stroke-width="var(--ge-icon-stroke, 1.75px)" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"';
	switch ( $icon ) {
		case 'treadmill':
			return '<svg width="22" height="22" viewBox="0 0 24 24" fill="none" ' . $stroke . '><circle cx="17" cy="4" r="2"/><path d="M4 17l6-3 3 2 5-6"/><path d="M10 14l-2 5-5-2"/><path d="M21 21H3"/></svg>';
		case 'rower':
			return '<svg width="22" height="22" viewBox="0 0 24 24" fill="none" ' . $stroke . '><circle cx="18" cy="5" r="2"/><path d="M4 19l4-3 5 2 7-6"/><path d="M2 12h5l3 4"/><path d="M7 21h14"/></svg>';
		case 'bike':
			return '<svg width="22" height="22" viewBox="0 0 24 24" fill="none" ' . $stroke . '><circle cx="5.5" cy="17.5" r="3.5"/><circle cx="18.5" cy="17.5" r="3.5"/><path d="M15 6h2l3 6.5-1.5.5"/><path d="M12 17.5V14l-3-3 4-3 3 3"/><circle cx="12" cy="5" r="1"/></svg>';
		case 'ellipse':
			return '<svg width="22" height="22" viewBox="0 0 24 24" fill="none" ' . $stroke . '><circle cx="12" cy="4" r="2"/><path d="M8 9l8 2-2 9-4-1"/><path d="M6 19l4-1 2-6"/><ellipse cx="12" cy="19" rx="8" ry="3"/></svg>';
		case 'dumbbell':
			return '<svg width="22" height="22" viewBox="0 0 24 24" fill="none" ' . $stroke . '><path d="M6 5v14M18 5v14M2 9v6M22 9v6M6 12h12"/></svg>';
		case 'multigym':
			return '<svg width="22" height="22" viewBox="0 0 24 24" fill="none" ' . $stroke . '><rect x="4" y="3" width="16" height="18" rx="2"/><line x1="4" y1="9" x2="20" y2="9"/><line x1="4" y1="15" x2="20" y2="15"/><line x1="12" y1="3" x2="12" y2="21"/></svg>';
		case 'rack':
			return '<svg width="22" height="22" viewBox="0 0 24 24" fill="none" ' . $stroke . '><line x1="4" y1="3" x2="4" y2="21"/><line x1="20" y1="3" x2="20" y2="21"/><line x1="4" y1="7" x2="20" y2="7"/><circle cx="8" cy="7" r="1.5"/><circle cx="16" cy="7" r="1.5"/><line x1="2" y1="21" x2="22" y2="21"/></svg>';
		case 'bench':
			return '<svg width="22" height="22" viewBox="0 0 24 24" fill="none" ' . $stroke . '><line x1="2" y1="10" x2="22" y2="10"/><path d="M4 10v7M20 10v7M12 10v7"/><line x1="2" y1="17" x2="6" y2="17"/><line x1="18" y1="17" x2="22" y2="17"/></svg>';
		case 'shield':
			return '<svg width="22" height="22" viewBox="0 0 24 24" fill="none" ' . $stroke . '><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/><polyline points="9 12 11 14 15 10"/></svg>';
		case 'target':
			return '<svg width="22" height="22" viewBox="0 0 24 24" fill="none" ' . $stroke . '><circle cx="12" cy="12" r="10"/><circle cx="12" cy="12" r="6"/><circle cx="12" cy="12" r="2"/></svg>';
		case 'activity':
			return '<svg width="22" height="22" viewBox="0 0 24 24" fill="none" ' . $stroke . '><path d="M22 12h-4l-3 9L9 3l-3 9H2"/></svg>';
		default:
			return '<svg width="22" height="22" viewBox="0 0 24 24" fill="none" ' . $stroke . '><circle cx="12" cy="12" r="9"/><polyline points="12 7 12 12 15 15"/></svg>';
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
				data-category="<?php echo esc_attr( $item['category'] ); ?>">
				<div class="gear-engine-hub__card-top">
					<span class="gear-engine-hub__card-cat-pill">
						<?php echo esc_html( $item['category_name'] ); ?>
					</span>
					<?php if ( ! empty( $item['badge'] ) ) : ?>
						<span class="gear-engine-hub__card-badge<?php echo ! $is_available ? ' gear-engine-hub__card-badge--muted' : ''; ?>">
							<?php echo esc_html( $item['badge'] ); ?>
						</span>
					<?php endif; ?>
				</div>

				<div class="gear-engine-hub__card-body">
					<div class="gear-engine-hub__card-icon-box">
						<?php echo gear_engine_hub_icon( (string) ( $item['icon'] ?? '' ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- hardcoded clean SVG helper ?>
					</div>
					<h3 class="gear-engine-hub__card-title"><?php echo esc_html( $item['title'] ); ?></h3>
					<p class="gear-engine-hub__card-desc"><?php echo esc_html( $item['desc'] ); ?></p>
				</div>

				<div class="gear-engine-hub__card-footer">
					<span class="gear-engine-hub__card-time">
						<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="var(--ge-icon-stroke, 1.75px)" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="12" cy="12" r="9"/><polyline points="12 7 12 12 15 15"/></svg>
						<?php echo esc_html( $item['time'] ); ?>
					</span>
					<?php if ( $is_available ) : ?>
						<a href="<?php echo esc_url( $card_href ); ?>" class="gear-engine-hub__card-action-btn">
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
		<?php endforeach; ?>
	</div>

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

