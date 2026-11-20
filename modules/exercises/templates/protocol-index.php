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

$area_labels = Gear_Engine_Protocol_Viewer::get_area_labels();
$area_counts = array( 'all' => count( $protocols ) );

foreach ( $protocols as $idx => $proto ) {
	if ( empty( $proto['area_slug'] ) || empty( $proto['area_label'] ) ) {
		$resolved                        = Gear_Engine_Protocol_Viewer::resolve_protocol_area( $proto );
		$protocols[ $idx ]['area_slug']  = $resolved['slug'];
		$protocols[ $idx ]['area_label'] = $resolved['label'];
	}
	$a_slug                 = (string) $protocols[ $idx ]['area_slug'];
	$area_counts[ $a_slug ] = ( $area_counts[ $a_slug ] ?? 0 ) + 1;
}

// Build ordered filter tabs: "Alle" first, then anatomical order for populated areas.
$filter_tabs = array(
	'all' => array(
		'label' => __( 'Alle', 'gear-engine' ),
		'count' => $area_counts['all'],
	),
);
foreach ( $area_labels as $a_slug => $a_label ) {
	if ( ! empty( $area_counts[ $a_slug ] ) ) {
		$filter_tabs[ $a_slug ] = array(
			'label' => $a_label,
			'count' => $area_counts[ $a_slug ],
		);
	}
}
if ( ! empty( $area_counts['annet'] ) ) {
	$filter_tabs['annet'] = array(
		'label' => __( 'Øvrige', 'gear-engine' ),
		'count' => $area_counts['annet'],
	);
}
?>
<div class="gear-engine-program gear-engine-program--hub" data-initial-area="all">
	<header class="gear-engine-program__hub-header">
		<div class="gear-engine-program__hub-badge">
			<span class="gear-engine-program__hub-badge-dot"></span>
			<?php esc_html_e( 'Fysioterapeutisk øvelsesbibliotek', 'gear-engine' ); ?>
		</div>
		<h1 class="gear-engine-program__hub-title"><?php esc_html_e( 'Treningsprogrammer & Rehabprotokoller', 'gear-engine' ); ?></h1>
		<p class="gear-engine-program__hub-ingress"><?php esc_html_e( 'Strukturerte opptreningsforløp utarbeidet etter kliniske retningslinjer for trygg fremgang.', 'gear-engine' ); ?></p>

		<?php if ( count( $filter_tabs ) > 1 ) : ?>
			<div class="gear-engine-program__chips" role="tablist" aria-label="<?php esc_attr_e( 'Filtrer etter kroppsområde', 'gear-engine' ); ?>">
				<?php foreach ( $filter_tabs as $tab_slug => $tab ) : ?>
					<?php $is_active = ( 'all' === $tab_slug ); ?>
					<button type="button"
						class="gear-engine-program__chip<?php echo $is_active ? ' gear-engine-program__chip--active' : ''; ?>"
						data-area="<?php echo esc_attr( $tab_slug ); ?>"
						role="tab"
						aria-pressed="<?php echo $is_active ? 'true' : 'false'; ?>">
						<svg class="gear-engine-program__chip-check" width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><polyline points="20 6 9 17 4 12"/></svg>
						<span class="gear-engine-program__chip-label"><?php echo esc_html( $tab['label'] ); ?></span>
					</button>
				<?php endforeach; ?>
			</div>
		<?php endif; ?>
	</header>

	<?php if ( empty( $protocols ) ) : ?>
		<p class="gear-engine-program__empty"><?php esc_html_e( 'Ingen treningsprogrammer er tilgjengelige ennå.', 'gear-engine' ); ?></p>
	<?php else : ?>
		<ul class="gear-engine-program__index">
			<?php foreach ( $protocols as $protocol ) : ?>
				<?php
				$area_slug  = (string) ( $protocol['area_slug'] ?? 'annet' );
				$area_label = (string) ( $protocol['area_label'] ?? __( 'Opptreningsprogram', 'gear-engine' ) );
				$ex_count   = (int) ( $protocol['exercise_count'] ?? 0 );
				$sub_text   = $ex_count > 0
					? sprintf(
						/* translators: 1: body area label, 2: number of exercises */
						__( '%1$s · %2$d øvelser', 'gear-engine' ),
						$area_label,
						$ex_count
					)
					: $area_label;
				?>
				<li class="gear-engine-program__index-item" data-area="<?php echo esc_attr( $area_slug ); ?>">
					<a class="gear-engine-program__card" href="<?php echo esc_url( Gear_Engine_Protocol_Viewer::get_program_url( $protocol['slug'] ) ); ?>">
						<div class="gear-engine-program__card-body">
							<span class="gear-engine-program__index-title"><?php echo esc_html( $protocol['name'] ); ?></span>
							<span class="gear-engine-program__card-sub"><?php echo esc_html( $sub_text ); ?></span>
						</div>
						<span class="gear-engine-program__card-arrow" aria-hidden="true">
							<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round"><path d="M9 18l6-6-6-6"/></svg>
						</span>
					</a>
				</li>
			<?php endforeach; ?>
		</ul>

		<!-- Trust Signals Footer (Harmonized with Triage & Gear Picker Hubs) -->
		<div class="gear-engine-program__trust-banner">
			<div class="gear-engine-program__trust-item">
				<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M22 12h-4l-3 9L9 3l-3 9H2"/></svg>
				<span><strong><?php esc_html_e( 'Klinisk forankret:', 'gear-engine' ); ?></strong> <?php esc_html_e( 'Strukturerte rehabiliteringsforløp basert på anerkjente faglige retningslinjer.', 'gear-engine' ); ?></span>
			</div>
			<div class="gear-engine-program__trust-item">
				<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/></svg>
				<span><strong><?php esc_html_e( 'Trygg progresjon:', 'gear-engine' ); ?></strong> <?php esc_html_e( 'Fasebasert opptrening steg for steg med videoveiledning og røde flagg.', 'gear-engine' ); ?></span>
			</div>
		</div>
	<?php endif; ?>
</div>
