<?php
/**
 * Gear picker diagnostics page template.
 *
 * Expects $report (array) from Gear_Engine_Picker_Diagnostics::build_report().
 *
 * @package GearEngine
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

$gear_engine_status_labels = array(
	'ready'     => __( 'Ready', 'gear-engine' ),
	'attention' => __( 'Needs attention', 'gear-engine' ),
	'broken'    => __( 'Not working', 'gear-engine' ),
);
$gear_engine_level_labels  = array(
	'ok'   => __( 'Good', 'gear-engine' ),
	'tip'  => __( 'Tip', 'gear-engine' ),
	'warn' => __( 'Fix', 'gear-engine' ),
	'bad'  => __( 'Fix first', 'gear-engine' ),
);
$gear_engine_ready_count   = count( array_filter( $report, static function ( $r ) { return 'ready' === $r['status']; } ) );
?>
<div class="wrap gear-engine-diag">
	<h1><?php esc_html_e( 'Gear Pickers Health', 'gear-engine' ); ?></h1>
	<p class="gear-engine-diag__intro">
		<?php esc_html_e( 'Each gear picker builds its recommendations from the products imported from your affiliate feeds. This page checks every picker against your real products and tells you what to fix. Reload the page after changes to see the new result.', 'gear-engine' ); ?>
	</p>

	<?php if ( ! $report ) : ?>
		<p><?php esc_html_e( 'No gear pickers found.', 'gear-engine' ); ?></p>
	<?php else : ?>
		<p class="gear-engine-diag__summary">
			<?php
			printf(
				/* translators: 1: ready pickers, 2: total pickers */
				esc_html__( '%1$d of %2$d pickers are ready to use.', 'gear-engine' ),
				(int) $gear_engine_ready_count,
				count( $report )
			);
			?>
		</p>
	<?php endif; ?>

	<?php foreach ( $report as $gear_engine_row ) : ?>
		<section class="gear-engine-diag__card gear-engine-diag__card--<?php echo esc_attr( $gear_engine_row['status'] ); ?>">
			<header class="gear-engine-diag__head">
				<h2><?php echo esc_html( $gear_engine_row['label'] ); ?></h2>
				<span class="gear-engine-diag__badge gear-engine-diag__badge--<?php echo esc_attr( $gear_engine_row['status'] ); ?>"><?php echo esc_html( $gear_engine_status_labels[ $gear_engine_row['status'] ] ); ?></span>
			</header>

			<p class="gear-engine-diag__shortcode">
				<label><?php esc_html_e( 'Shortcode:', 'gear-engine' ); ?>
					<input type="text" readonly class="gear-engine-diag__code" value="<?php echo esc_attr( $gear_engine_row['shortcode'] ); ?>" onclick="this.select();" />
				</label>
			</p>

			<?php if ( ! empty( $gear_engine_row['stats'] ) ) : ?>
				<p class="gear-engine-diag__count">
					<strong><?php echo esc_html( number_format_i18n( $gear_engine_row['stats']['qualified'] ) ); ?></strong>
					<?php
					printf(
						/* translators: %s: total gear products */
						esc_html__( 'products can be recommended (out of %s gear products on the site)', 'gear-engine' ),
						esc_html( number_format_i18n( $gear_engine_row['stats']['total'] ) )
					);
					?>
				</p>
			<?php endif; ?>

			<p class="gear-engine-diag__where">
				<strong><?php esc_html_e( 'Used on:', 'gear-engine' ); ?></strong>
				<?php if ( $gear_engine_row['usage'] ) : ?>
					<?php
					$gear_engine_links = array();
					foreach ( $gear_engine_row['usage'] as $gear_engine_page ) {
						$gear_engine_links[] = '<a href="' . esc_url( $gear_engine_page['url'] ) . '">' . esc_html( $gear_engine_page['title'] ) . '</a>';
					}
					echo wp_kses( implode( ', ', $gear_engine_links ), array( 'a' => array( 'href' => array() ) ) );
					?>
				<?php else : ?>
					<?php esc_html_e( 'No published page yet', 'gear-engine' ); ?>
				<?php endif; ?>
			</p>

			<?php if ( ! empty( $gear_engine_row['error'] ) && current_user_can( 'manage_options' ) ) : ?>
				<p class="gear-engine-diag__error"><?php echo esc_html( $gear_engine_row['error'] ); ?></p>
			<?php endif; ?>

			<h3><?php esc_html_e( 'What to do', 'gear-engine' ); ?></h3>
			<ul class="gear-engine-diag__advice">
				<?php foreach ( $gear_engine_row['advice'] as $gear_engine_item ) : ?>
					<li class="gear-engine-diag__advice-item gear-engine-diag__advice-item--<?php echo esc_attr( $gear_engine_item['level'] ); ?>">
						<span class="gear-engine-diag__level"><?php echo esc_html( $gear_engine_level_labels[ $gear_engine_item['level'] ] ); ?></span>
						<?php echo esc_html( $gear_engine_item['text'] ); ?>
					</li>
				<?php endforeach; ?>
			</ul>

			<?php if ( ! empty( $gear_engine_row['stats'] ) && $gear_engine_row['stats']['total'] > 0 ) : ?>
				<details class="gear-engine-diag__details">
					<summary><?php esc_html_e( 'Why some products are left out', 'gear-engine' ); ?></summary>
					<?php if ( ! $gear_engine_row['stats']['reasons'] ) : ?>
						<p><?php esc_html_e( 'Every product on the site qualifies.', 'gear-engine' ); ?></p>
					<?php else : ?>
						<table class="widefat striped">
							<thead><tr>
								<th><?php esc_html_e( 'Reason', 'gear-engine' ); ?></th>
								<th><?php esc_html_e( 'Products', 'gear-engine' ); ?></th>
								<th><?php esc_html_e( 'Examples and what to do', 'gear-engine' ); ?></th>
							</tr></thead>
							<tbody>
							<?php foreach ( $gear_engine_row['stats']['reasons'] as $gear_engine_code => $gear_engine_n ) : ?>
								<?php $gear_engine_info = $gear_engine_row['reasons'][ $gear_engine_code ] ?? array( 'label' => $gear_engine_code, 'help' => '' ); ?>
								<tr>
									<td><?php echo esc_html( $gear_engine_info['label'] ); ?></td>
									<td><?php echo esc_html( number_format_i18n( $gear_engine_n ) ); ?></td>
									<td>
										<?php if ( ! empty( $gear_engine_row['stats']['samples'][ $gear_engine_code ] ) ) : ?>
											<em><?php echo esc_html( implode( ' · ', $gear_engine_row['stats']['samples'][ $gear_engine_code ] ) ); ?></em><br />
										<?php endif; ?>
										<?php echo esc_html( $gear_engine_info['help'] ); ?>
									</td>
								</tr>
							<?php endforeach; ?>
							</tbody>
						</table>
					<?php endif; ?>
				</details>

				<?php if ( $gear_engine_row['stats']['qualified'] > 0 ) : ?>
					<details class="gear-engine-diag__details">
						<summary><?php esc_html_e( 'How well the product details are read', 'gear-engine' ); ?></summary>
						<p><?php esc_html_e( 'The picker reads specifications (speed, weight, features) from each product description. When a number is missing it may guess from the price, which is less accurate. Features that are not mentioned count as "no".', 'gear-engine' ); ?></p>
						<table class="widefat striped">
							<thead><tr>
								<th><?php esc_html_e( 'Detail', 'gear-engine' ); ?></th>
								<th><?php esc_html_e( 'Found in description', 'gear-engine' ); ?></th>
								<th><?php esc_html_e( 'Guessed from price', 'gear-engine' ); ?></th>
								<th><?php esc_html_e( 'Not found', 'gear-engine' ); ?></th>
							</tr></thead>
							<tbody>
							<?php foreach ( $gear_engine_row['stats']['specs'] as $gear_engine_key => $gear_engine_spec ) : ?>
								<tr>
									<td><?php echo esc_html( str_replace( '_', ' ', $gear_engine_key ) ); ?></td>
									<td><?php echo esc_html( number_format_i18n( $gear_engine_spec['found'] ) ); ?></td>
									<td><?php echo esc_html( number_format_i18n( $gear_engine_spec['guessed'] ) ); ?></td>
									<td><?php echo esc_html( number_format_i18n( $gear_engine_spec['missing'] ) ); ?></td>
								</tr>
							<?php endforeach; ?>
							</tbody>
						</table>
					</details>
				<?php endif; ?>
			<?php endif; ?>
		</section>
	<?php endforeach; ?>

	<p class="description">
		<?php esc_html_e( 'The setup file of each picker lives in modules/gear-picker/configs/. Product lists are cached for an hour and refresh when a gear product is saved.', 'gear-engine' ); ?>
	</p>
</div>
