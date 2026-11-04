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
	<?php if ( isset( $_GET['gear_engine_pages_created'] ) ) : ?>
		<div class="notice notice-success is-dismissible" style="margin-top: 15px;">
			<p><strong>✓ <?php esc_html_e( 'Kjernesidene ble opprettet!', 'gear-engine' ); ?></strong> <?php esc_html_e( 'Sidene for Kjøpsveiledere, Treningsprogram, Symptomsjekker, Digital Fysio og Prissammenligning er nå klare.', 'gear-engine' ); ?></p>
		</div>
	<?php elseif ( isset( $_GET['gear_engine_samples_removed'] ) ) : ?>
		<div class="notice notice-success is-dismissible" style="margin-top: 15px;">
			<p><strong>✓ <?php esc_html_e( 'Testdata ble slettet!', 'gear-engine' ); ?></strong> <?php printf( esc_html__( 'Alle midlertidige testøvelser (%d) og testprotokoller (%d) ble fjernet. Databasen er nå ren.', 'gear-engine' ), absint( $_GET['deleted_ex'] ?? 0 ), absint( $_GET['deleted_proto'] ?? 0 ) ); ?></p>
		</div>
	<?php elseif ( isset( $_GET['gear_engine_samples_seeded'] ) ) : ?>
		<div class="notice notice-success is-dismissible" style="margin-top: 15px;">
			<p><strong>✓ <?php esc_html_e( 'Testdata ble lagt inn!', 'gear-engine' ); ?></strong> <?php printf( esc_html__( 'Lagt inn %d øvelser og %d godkjente protokoller for testing.', 'gear-engine' ), absint( $_GET['seeded_ex'] ?? 0 ), absint( $_GET['seeded_proto'] ?? 0 ) ); ?></p>
		</div>
	<?php endif; ?>

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

	<div class="gear-engine-guide__hero-action" style="background: #FFFFFF; border: 1px solid #CBD7E6; border-left: 4px solid #1A2A3A; padding: 18px 22px; border-radius: 8px; margin: 28px 0 20px;">
		<div style="display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 14px;">
			<div style="max-width: 600px;">
				<h3 style="margin: 0 0 6px; font-size: 16px; font-weight: 700; color: #1A2A3A;">
					<?php esc_html_e( 'Hurtigoppsett av kjernesider', 'gear-engine' ); ?>
				</h3>
				<p style="margin: 0; font-size: 13px; color: #4A5A6A; line-height: 1.5;">
					<?php esc_html_e( 'Trykk på knappen for å opprette alle manglende sider automatisk (/kjopsveiledere/, /priser/, /treningsprogram/, /symptomsjekker/ og /fysio-chat/) med riktige kortkoder ferdig satt inn.', 'gear-engine' ); ?>
				</p>
			</div>
			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" style="margin: 0;">
				<input type="hidden" name="action" value="gear_engine_create_core_pages">
				<?php
				if ( function_exists( 'wp_nonce_field' ) ) {
					wp_nonce_field( 'gear_engine_create_core_pages', 'gear_engine_nonce' );
				}
				?>
				<button type="submit" class="button button-primary button-hero" style="background-color: #1A2A3A; border-color: #1A2A3A; color: #FFFFFF; font-weight: 700;">
					<?php esc_html_e( 'Opprett manglende kjernesider automatisk (1 klikk)', 'gear-engine' ); ?>
				</button>
			</form>
		</div>
	</div>

	<!-- Testdata for Treningsprotokoller og Øvelser -->
	<div class="gear-engine-guide__hero-action" style="background: #FFFFFF; border: 1px solid #CBD7E6; border-left: 4px solid #00A878; padding: 18px 22px; border-radius: 8px; margin: 0 0 24px;">
		<div style="display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 14px;">
			<div style="max-width: 600px;">
				<h3 style="margin: 0 0 6px; font-size: 16px; font-weight: 700; color: #1A2A3A;">
					<?php esc_html_e( 'Testdata for Treningsprotokoller og Øvelser', 'gear-engine' ); ?>
				</h3>
				<p style="margin: 0; font-size: 13px; color: #4A5A6A; line-height: 1.5;">
					<?php esc_html_e( 'Systemet har lagt inn testprotokoller og øvelser for de 6 triage-områdene slik at hele flyten kan testes. Ved overgang til produksjon kan du fjerne alle testdata med ett klikk uten å berøre egne data.', 'gear-engine' ); ?>
				</p>
			</div>
			<div style="display: flex; gap: 10px; align-items: center; flex-wrap: wrap;">
				<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" style="margin: 0;" onsubmit="return confirm('<?php echo esc_js( __( 'Er du sikker på at du vil slette alle testøvelser og testprotokoller?', 'gear-engine' ) ); ?>');">
					<input type="hidden" name="action" value="gear_engine_remove_sample_data">
					<?php
					if ( function_exists( 'wp_nonce_field' ) ) {
						wp_nonce_field( 'gear_engine_remove_sample_data', 'gear_engine_nonce' );
					}
					?>
					<button type="submit" class="button button-secondary" style="color: #b32d2e; border-color: #b32d2e;">
						<?php esc_html_e( 'Fjern alle testdata (produksjonsrensing)', 'gear-engine' ); ?>
					</button>
				</form>
				<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" style="margin: 0;">
					<input type="hidden" name="action" value="gear_engine_reseed_sample_data">
					<?php
					if ( function_exists( 'wp_nonce_field' ) ) {
						wp_nonce_field( 'gear_engine_reseed_sample_data', 'gear_engine_nonce' );
					}
					?>
					<button type="submit" class="button button-secondary">
						<?php esc_html_e( 'Gjenopprett testdata', 'gear-engine' ); ?>
					</button>
				</form>
			</div>
		</div>
	</div>

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
			/* translators: %s: link to the Symptomsjekker settings page */
			esc_html__( 'Paths are set under %s. When you create a page, set its slug to match the path (region pages sit below the Symptomsjekker page).', 'gear-engine' ),
			'<a href="' . esc_url( admin_url( 'options-general.php?page=gear-engine-triage' ) ) . '">' . esc_html__( 'Settings → Symptomsjekker', 'gear-engine' ) . '</a>'
		);
		?>
	</p>
</div>
