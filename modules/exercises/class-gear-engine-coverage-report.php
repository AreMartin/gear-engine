<?php
/**
 * Program coverage report: every triage diagnosis against its exercise path.
 *
 * Read-only health screen modeled on Gear Pickers Health: shows what each
 * quiz result actually reaches (curated program, auto program, or nothing),
 * flags diagnoses that name a missing/broken protocol, and lists approved
 * protocols no quiz can ever reach. Proposes no changes; plain-language fixes.
 *
 * @package GearEngine
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Gear_Engine_Coverage_Report
 */
class Gear_Engine_Coverage_Report {

	public const GEAR_ENGINE_PAGE_SLUG = 'gear-engine-program-coverage';

	/**
	 * Constructor.
	 */
	public function __construct() {
		add_action( 'admin_menu', array( $this, 'register_page' ) );
	}

	/**
	 * Add the report below the Exercise Library menu.
	 *
	 * @return void
	 */
	public function register_page(): void {
		add_submenu_page(
			'edit.php?post_type=' . Gear_Engine_Exercises_CPT::GEAR_ENGINE_POST_TYPE,
			__( 'Program Coverage', 'gear-engine' ),
			__( 'Program Coverage', 'gear-engine' ),
			'edit_posts',
			self::GEAR_ENGINE_PAGE_SLUG,
			array( $this, 'render_page' )
		);
	}

	/**
	 * Classify one diagnosis into a coverage state. Pure; used by render and tests.
	 *
	 * @param array<string, mixed> $dx             Diagnosis definition.
	 * @param string               $program_slug   Approved protocol slug reached, or ''.
	 * @param bool                 $auto_available Whether the region has auto-program exercises.
	 * @return array{state: string, note: string}
	 */
	public static function classify_diagnosis( array $dx, string $program_slug, bool $auto_available ): array {
		if ( in_array( (string) ( $dx['severity'] ?? '' ), array( 'Høy', 'Kritisk' ), true ) ) {
			return array( 'state' => 'by-design', 'note' => '' );
		}
		if ( '' !== $program_slug ) {
			return array( 'state' => 'curated', 'note' => $program_slug );
		}
		if ( ! empty( $dx['protocol'] ) ) {
			return array(
				'state' => 'broken',
				/* translators: %s: protocol slug named in the config */
				'note'  => sprintf( 'Names protocol "%s", but no approved program with exercises matches it.', $dx['protocol'] ),
			);
		}
		if ( $auto_available ) {
			return array( 'state' => 'auto', 'note' => '' );
		}
		return array( 'state' => 'missing', 'note' => 'No exercise path exists for this result yet.' );
	}

	/**
	 * Approved protocol slugs that no diagnosis can reach. Pure; prefix matching
	 * mirrors Gear_Engine_Protocols::find_approved_by_codes semantics.
	 *
	 * @param array<int, array{slug: string, codes: array<int, string>}> $protocols   Top-level approved protocols.
	 * @param array<int, array{protocol: string, codes: array<int, string>}> $diagnoses Every diagnosis's explicit slug + codes.
	 * @return array<int, string> Unreachable protocol slugs.
	 */
	public static function find_unreachable_protocols( array $protocols, array $diagnoses ): array {
		$reachable = array();
		foreach ( $diagnoses as $diagnosis ) {
			if ( '' !== $diagnosis['protocol'] ) {
				$reachable[ $diagnosis['protocol'] ] = true;
			}
			foreach ( $diagnosis['codes'] as $code ) {
				$code = strtoupper( (string) $code );
				foreach ( $protocols as $protocol ) {
					foreach ( $protocol['codes'] as $protocol_code ) {
						$protocol_code = strtoupper( (string) $protocol_code );
						if ( '' !== $protocol_code && 0 === strpos( $code, $protocol_code ) ) {
							$reachable[ $protocol['slug'] ] = true;
						}
					}
				}
			}
		}

		$unreachable = array();
		foreach ( $protocols as $protocol ) {
			if ( ! isset( $reachable[ $protocol['slug'] ] ) ) {
				$unreachable[] = $protocol['slug'];
			}
		}
		return $unreachable;
	}

	/**
	 * Collect the report inputs from the live configs, protocols and exercises.
	 *
	 * @return array{regions: array<int, array{name: string, rows: array<int, array{title: string, state: string, note: string}>>}, unreachable: array<int, string>}
	 */
	public static function build_report(): array {
		$regions = array();
		$diagnoses_seen = array();

		foreach ( Gear_Engine_Triage::get_regions() as $region_slug ) {
			$config = Gear_Engine_Triage::get_config( (string) $region_slug );
			if ( is_wp_error( $config ) ) {
				continue;
			}

			$auto_available = Gear_Engine_Protocol_Viewer::area_has_auto_exercises( Gear_Engine_Protocol_Viewer::get_region_area( (string) $region_slug, $config ) );
			$rows           = array();

			foreach ( (array) ( $config['diagnoses'] ?? array() ) as $id => $dx ) {
				if ( ! is_array( $dx ) ) {
					continue;
				}
				$program_slug = Gear_Engine_Triage::find_program_slug( $dx );
				$state        = self::classify_diagnosis( $dx, $program_slug, $auto_available );

				$diagnoses_seen[] = array(
					'protocol' => '' !== $program_slug ? $program_slug : ( isset( $dx['protocol'] ) ? sanitize_title( (string) $dx['protocol'] ) : '' ),
					'codes'    => array_filter( array( (string) ( $dx['icd10'] ?? '' ), (string) ( $dx['icpc2'] ?? '' ) ) ),
				);

				$rows[] = array(
					'title' => (string) ( $dx['title'] ?? $id ),
					'state' => $state['state'],
					'note'  => $state['note'],
				);
			}

			usort(
				$rows,
				static function ( array $a, array $b ): int {
					$order = array( 'missing' => 0, 'broken' => 1, 'auto' => 2, 'curated' => 3, 'by-design' => 4 );
					return ( $order[ $a['state'] ] ?? 9 ) <=> ( $order[ $b['state'] ] ?? 9 ) ?: strcmp( $a['title'], $b['title'] );
				}
			);

			$regions[] = array( 'name' => (string) ( $config['label'] ?? $region_slug ), 'rows' => $rows );
		}

		$protocols = array();
		foreach ( Gear_Engine_Protocol_Manager::get_protocol_tree() as $row ) {
			$term = $row['term'];
			if ( 0 !== (int) $term->parent || Gear_Engine_Protocols::GEAR_ENGINE_STATUS_APPROVED !== Gear_Engine_Protocols::get_status( (int) $term->term_id ) ) {
				continue;
			}
			$codes      = preg_split( '/[,\s]+/', (string) get_term_meta( (int) $term->term_id, Gear_Engine_Protocols::GEAR_ENGINE_META_CODES, true ) );
			$protocols[] = array(
				'slug'  => (string) $term->slug,
				'codes' => array_filter( array_map( 'trim', (array) $codes ) ),
			);
		}

		return array(
			'regions'     => $regions,
			'unreachable' => self::find_unreachable_protocols( $protocols, $diagnoses_seen ),
		);
	}

	/**
	 * Render the report page.
	 *
	 * @return void
	 */
	public function render_page(): void {
		if ( ! current_user_can( 'edit_posts' ) ) {
			wp_die( esc_html__( 'You do not have permission to view this page.', 'gear-engine' ), '', array( 'response' => 403 ) );
		}

		$labels = array(
			'curated'   => array( __( 'Curated program', 'gear-engine' ), '#1e7e34' ),
			'auto'      => array( __( 'Auto program', 'gear-engine' ), '#996800' ),
			'missing'   => array( __( 'No path', 'gear-engine' ), '#b32d2e' ),
			'broken'    => array( __( 'Broken assignment', 'gear-engine' ), '#b32d2e' ),
			'by-design' => array( __( 'No program by design', 'gear-engine' ), '#64748b' ),
		);

		$report = self::build_report();
		$counts = array();
		foreach ( $report['regions'] as $region ) {
			foreach ( $region['rows'] as $row ) {
				$counts[ $row['state'] ] = ( $counts[ $row['state'] ] ?? 0 ) + 1;
			}
		}
		$problems = ( $counts['missing'] ?? 0 ) + ( $counts['broken'] ?? 0 );

		echo '<div class="wrap"><h1>' . esc_html__( 'Program Coverage', 'gear-engine' ) . '</h1>';
		printf(
			'<p>%s</p>',
			esc_html__( 'What each quiz result actually reaches for exercises. Curated means an approved protocol; auto means generated from the library; no path means the result screen will offer nothing.', 'gear-engine' )
		);

		if ( $problems > 0 ) {
			printf(
				'<div class="notice notice-warning inline"><p><strong>%s</strong></p></div>',
				esc_html(
					sprintf(
						/* translators: %d: problem count */
						__( '%d results need attention (shown first in each list below).', 'gear-engine' ),
						$problems
					)
				)
			);
		} else {
			echo '<div class="notice notice-success inline"><p><strong>' . esc_html__( 'Every reachable result has an exercise path.', 'gear-engine' ) . '</strong></p></div>';
		}

		if ( ! Gear_Engine_Protocols::is_enabled() ) {
			echo '<div class="notice notice-info inline"><p>' . esc_html__( 'Clinical protocols are switched off, so no result can be curated. Enable them under Settings → AI to unlock the protocol columns.', 'gear-engine' ) . '</p></div>';
		}

		foreach ( $report['regions'] as $region ) {
			echo '<h2>' . esc_html( $region['name'] ) . '</h2><table class="widefat striped"><thead><tr><th>' . esc_html__( 'Diagnosis', 'gear-engine' ) . '</th><th>' . esc_html__( 'Reaches', 'gear-engine' ) . '</th><th>' . esc_html__( 'What to do', 'gear-engine' ) . '</th></tr></thead><tbody>';
			foreach ( $region['rows'] as $row ) {
				$label = $labels[ $row['state'] ] ?? array( $row['state'], '#000' );
				$todo  = '';
				if ( 'missing' === $row['state'] ) {
					$todo = esc_html__( 'Tag some exercises with this condition (Good for), or add exercises for this body area so auto programs can run.', 'gear-engine' );
				} elseif ( 'broken' === $row['state'] ) {
					$todo = esc_html( $row['note'] );
				} elseif ( 'auto' === $row['state'] ) {
					$todo = esc_html__( 'Optional: curate a protocol with codes matching this diagnosis for better control.', 'gear-engine' );
				} elseif ( 'curated' === $row['state'] ) {
					$todo = esc_html( $row['note'] );
				}
				echo '<tr><td>' . esc_html( $row['title'] ) . '</td>';
				echo '<td style="color:' . esc_attr( $label[1] ) . '"><strong>' . esc_html( $label[0] ) . '</strong></td>';
				echo '<td>' . $todo . '</td></tr>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- parts pre-escaped above.
			}
			echo '</tbody></table>';
		}

		if ( $report['unreachable'] ) {
			echo '<h2>' . esc_html__( 'Approved programs no quiz can reach', 'gear-engine' ) . '</h2>';
			echo '<p>' . esc_html__( 'These protocols are approved but no current quiz diagnosis produces a matching code or name. They stay invisible to users until a diagnosis matches (check the codes) or should be drafted back.', 'gear-engine' ) . '</p><ul style="list-style:disc;margin-left:20px">';
			foreach ( $report['unreachable'] as $slug ) {
				echo '<li><code>' . esc_html( $slug ) . '</code></li>';
			}
			echo '</ul>';
		}

		echo '</div>';
	}
}
