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
	<?php if ( isset( $_GET['gear_engine_stack_installed'] ) ) : ?>
		<div class="notice notice-success is-dismissible" style="margin-top: 15px;">
			<p><strong>✓ <?php esc_html_e( 'GeneratePress og GenerateBlocks er installert og aktivert!', 'gear-engine' ); ?></strong> <?php esc_html_e( 'Nettstedet kjører nå med optimalisert ytelse, ren kodebase og full Gutenberg-støtte.', 'gear-engine' ); ?></p>
		</div>
	<?php elseif ( isset( $_GET['gear_engine_stack_error'] ) ) : ?>
		<div class="notice notice-error is-dismissible" style="margin-top: 15px;">
			<p><strong>✕ <?php esc_html_e( 'Installasjonen støtte på et problem:', 'gear-engine' ); ?></strong> <?php echo esc_html( sanitize_text_field( wp_unslash( $_GET['gear_engine_stack_error'] ) ) ); ?></p>
		</div>
	<?php elseif ( isset( $_GET['gear_engine_pages_created'] ) ) : ?>
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

	<!-- Anbefalt fundament: GeneratePress & GenerateBlocks -->
	<?php
	$gear_engine_theme_info  = $stack_status['theme'] ?? array();
	$gear_engine_plugin_info = $stack_status['plugin'] ?? array();
	$gear_engine_stack_done  = ! empty( $stack_status['complete'] );
	?>
	<div class="gear-engine-guide__hero-action" style="background: #FFFFFF; border: 1px solid #CBD7E6; border-left: 4px solid #0C2340; padding: 18px 22px; border-radius: 8px; margin: 20px 0 24px;">
		<div style="display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 14px;">
			<div style="max-width: 620px;">
				<h3 style="margin: 0 0 6px; font-size: 16px; font-weight: 700; color: #0C2340;">
					<?php esc_html_e( 'Anbefalt fundament: GeneratePress & GenerateBlocks', 'gear-engine' ); ?>
				</h3>
				<p style="margin: 0 0 10px; font-size: 13px; color: #4A5A6A; line-height: 1.5;">
					<?php esc_html_e( 'Fysionett og Gear Engine er bygget for maksimal ytelse og null bloat. GeneratePress gir deg et lynraskt, tilgjengelig tema, mens GenerateBlocks gir semantiske, lette blokker uten unødvendige skript.', 'gear-engine' ); ?>
				</p>
				<div style="display: flex; gap: 16px; align-items: center; flex-wrap: wrap; font-size: 12.5px;">
					<span style="display: inline-flex; align-items: center; gap: 6px;">
						<strong>GeneratePress (Tema):</strong>
						<?php if ( ! empty( $gear_engine_theme_info['active'] ) ) : ?>
							<span style="display: inline-flex; align-items: center; gap: 4px; background: #DCF5E8; color: #005236; font-weight: 700; padding: 2px 8px; border-radius: 999px;">✓ <?php esc_html_e( 'Aktivt', 'gear-engine' ); ?><?php echo ! empty( $gear_engine_theme_info['version'] ) ? ' (v' . esc_html( $gear_engine_theme_info['version'] ) . ')' : ''; ?></span>
						<?php elseif ( ! empty( $gear_engine_theme_info['installed'] ) ) : ?>
							<span style="display: inline-flex; align-items: center; gap: 4px; background: #FEF3C7; color: #92400E; font-weight: 600; padding: 2px 8px; border-radius: 999px;"><?php esc_html_e( 'Installert, inaktivt', 'gear-engine' ); ?></span>
						<?php else : ?>
							<span style="display: inline-flex; align-items: center; gap: 4px; background: #F0F2F5; color: #667085; font-weight: 600; padding: 2px 8px; border-radius: 999px;"><?php esc_html_e( 'Ikke installert', 'gear-engine' ); ?></span>
						<?php endif; ?>
					</span>
					<span style="display: inline-flex; align-items: center; gap: 6px;">
						<strong>GenerateBlocks (Plugin):</strong>
						<?php if ( ! empty( $gear_engine_plugin_info['active'] ) ) : ?>
							<span style="display: inline-flex; align-items: center; gap: 4px; background: #DCF5E8; color: #005236; font-weight: 700; padding: 2px 8px; border-radius: 999px;">✓ <?php esc_html_e( 'Aktivt', 'gear-engine' ); ?><?php echo ! empty( $gear_engine_plugin_info['version'] ) ? ' (v' . esc_html( $gear_engine_plugin_info['version'] ) . ')' : ''; ?></span>
						<?php elseif ( ! empty( $gear_engine_plugin_info['installed'] ) ) : ?>
							<span style="display: inline-flex; align-items: center; gap: 4px; background: #FEF3C7; color: #92400E; font-weight: 600; padding: 2px 8px; border-radius: 999px;"><?php esc_html_e( 'Installert, inaktivt', 'gear-engine' ); ?></span>
						<?php else : ?>
							<span style="display: inline-flex; align-items: center; gap: 4px; background: #F0F2F5; color: #667085; font-weight: 600; padding: 2px 8px; border-radius: 999px;"><?php esc_html_e( 'Ikke installert', 'gear-engine' ); ?></span>
						<?php endif; ?>
					</span>
				</div>
			</div>
			<?php if ( $gear_engine_stack_done ) : ?>
				<div style="display: inline-flex; align-items: center; gap: 8px; background: #DCF5E8; border: 1px solid #BEE3D0; color: #005236; padding: 10px 18px; border-radius: 999px; font-weight: 700; font-size: 13.5px;">
					<span>✓</span> <?php esc_html_e( 'GeneratePress og GenerateBlocks er klare!', 'gear-engine' ); ?>
				</div>
			<?php else : ?>
				<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" style="margin: 0;">
					<input type="hidden" name="action" value="gear_engine_install_recommended_stack">
					<?php
					if ( function_exists( 'wp_nonce_field' ) ) {
						wp_nonce_field( 'gear_engine_install_recommended_stack', 'gear_engine_nonce' );
					}
					?>
					<button type="submit" class="button button-primary button-hero" style="background-color: #0C2340; border-color: #0C2340; color: #FFFFFF; font-weight: 700;">
						<?php esc_html_e( 'Installer og aktiver GeneratePress & GenerateBlocks (1 klikk)', 'gear-engine' ); ?>
					</button>
				</form>
			<?php endif; ?>
		</div>
	</div>

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

	<div class="gear-engine-guide__hero-action" style="background: #FFFFFF; border: 1px solid #CBD7E6; border-left: 4px solid #0C2340; padding: 18px 22px; border-radius: 8px; margin: 28px 0 20px;">
		<div style="display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 14px;">
			<div style="max-width: 600px;">
				<h3 style="margin: 0 0 6px; font-size: 16px; font-weight: 700; color: #0C2340;">
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
				<button type="submit" class="button button-primary button-hero" style="background-color: #0C2340; border-color: #0C2340; color: #FFFFFF; font-weight: 700;">
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
