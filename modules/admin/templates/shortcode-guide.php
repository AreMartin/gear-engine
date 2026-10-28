<?php
/**
 * Shortcodes & Pages admin template.
 *
 * Expects $shortcodes and $page_map from Gear_Engine_Shortcode_Guide::render_page().
 *
 * @package GearEngine
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

$gear_engine_group = '';
?>
<div class="wrap gear-engine-guide">
	<h1><?php esc_html_e( 'Shortcodes & Pages', 'gear-engine' ); ?></h1>

	<h2><?php esc_html_e( 'Shortcodes', 'gear-engine' ); ?></h2>
	<table class="widefat striped">
		<thead>
			<tr>
				<th><?php esc_html_e( 'Shortcode', 'gear-engine' ); ?></th>
				<th><?php esc_html_e( 'What it does', 'gear-engine' ); ?></th>
				<th><?php esc_html_e( 'Where to use it', 'gear-engine' ); ?></th>
			</tr>
		</thead>
		<tbody>
			<?php foreach ( $shortcodes as $gear_engine_row ) : ?>
				<?php if ( $gear_engine_row['group'] !== $gear_engine_group ) : ?>
					<?php $gear_engine_group = $gear_engine_row['group']; ?>
					<tr class="gear-engine-guide__group"><th colspan="3"><?php echo esc_html( $gear_engine_group ); ?></th></tr>
				<?php endif; ?>
				<tr>
					<td>
						<code><?php echo esc_html( $gear_engine_row['example'] ); ?></code>
						<button type="button" class="button button-small gear-engine-guide__copy" data-copy="<?php echo esc_attr( $gear_engine_row['example'] ); ?>"><?php esc_html_e( 'Copy', 'gear-engine' ); ?></button>
					</td>
					<td><?php echo esc_html( $gear_engine_row['description'] ); ?></td>
					<td><?php echo esc_html( $gear_engine_row['note'] ); ?></td>
				</tr>
			<?php endforeach; ?>
		</tbody>
	</table>

	<h2><?php esc_html_e( 'Page structure', 'gear-engine' ); ?></h2>
	<p class="description"><?php esc_html_e( 'Front page cards (built in the block editor) link to the top pages. Indented pages are opened from the page above. Status is checked live.', 'gear-engine' ); ?></p>
	<table class="widefat">
		<thead>
			<tr>
				<th><?php esc_html_e( 'Page', 'gear-engine' ); ?></th>
				<th><?php esc_html_e( 'Path', 'gear-engine' ); ?></th>
				<th><?php esc_html_e( 'Shortcode', 'gear-engine' ); ?></th>
				<th><?php esc_html_e( 'Status', 'gear-engine' ); ?></th>
				<th></th>
			</tr>
		</thead>
		<tbody>
			<?php foreach ( $page_map as $gear_engine_row ) : ?>
				<?php
				list( $gear_engine_state_label, $gear_engine_state_class ) = Gear_Engine_Shortcode_Guide::describe_state( (string) $gear_engine_row['state'] );
				list( $gear_engine_action_url, $gear_engine_action_label ) = Gear_Engine_Shortcode_Guide::get_action_link( $gear_engine_row );
				?>
				<tr>
					<td style="padding-left: <?php echo esc_attr( (string) ( 12 + 24 * (int) $gear_engine_row['level'] ) ); ?>px">
						<?php echo $gear_engine_row['level'] ? '↳ ' : ''; ?><strong><?php echo esc_html( $gear_engine_row['title'] ); ?></strong>
						<br><span class="description"><?php echo esc_html( $gear_engine_row['hint'] ); ?></span>
					</td>
					<td>
						<?php if ( '' !== $gear_engine_row['path'] ) : ?>
							<code>/<?php echo esc_html( $gear_engine_row['path'] ); ?>/</code>
						<?php else : ?>
							<em><?php esc_html_e( 'any page', 'gear-engine' ); ?></em>
						<?php endif; ?>
					</td>
					<td>
						<code><?php echo esc_html( $gear_engine_row['example'] ); ?></code>
						<button type="button" class="button button-small gear-engine-guide__copy" data-copy="<?php echo esc_attr( $gear_engine_row['example'] ); ?>"><?php esc_html_e( 'Copy', 'gear-engine' ); ?></button>
					</td>
					<td><span class="gear-engine-guide__state gear-engine-guide__state--<?php echo esc_attr( $gear_engine_state_class ); ?>"><?php echo esc_html( $gear_engine_state_label ); ?></span></td>
					<td><a href="<?php echo esc_url( $gear_engine_action_url ); ?>"><?php echo esc_html( $gear_engine_action_label ); ?></a></td>
				</tr>
			<?php endforeach; ?>
		</tbody>
	</table>
	<p class="description">
		<?php
		printf(
			/* translators: %s: link to the Smertesjekk settings page */
			esc_html__( 'Paths are set under %s. When you create a page, set its slug to match the path (region pages sit below the Smertesjekk page).', 'gear-engine' ),
			'<a href="' . esc_url( admin_url( 'options-general.php?page=gear-engine-triage' ) ) . '">' . esc_html__( 'Settings → Smertesjekk', 'gear-engine' ) . '</a>'
		);
		?>
	</p>
</div>
