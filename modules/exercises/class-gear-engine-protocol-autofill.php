<?php
/**
 * Draft-assist auto-fill for protocol phases.
 *
 * Proposes exercises into under-filled protocol phases so a human can review
 * and approve them. Hard guarantees: only adds missing exercises to phases
 * below the target size, never removes or changes anything a human curated,
 * never touches the Draft/Approved status, and records every proposed
 * assignment so a whole auto pass can be cleared again.
 *
 * @package GearEngine
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Gear_Engine_Protocol_Autofill
 */
class Gear_Engine_Protocol_Autofill {

	public const TARGET_PER_PHASE = 5;
	public const NONCE_ACTION     = 'gear_engine_autofill_protocols';
	public const META_AUTO_IDS    = '_gear_engine_phase_auto_exercise_ids';

	/**
	 * Constructor.
	 */
	public function __construct() {
		add_action( 'admin_post_gear_engine_autofill_protocol', array( $this, 'handle_fill' ) );
		add_action( 'admin_post_gear_engine_autofill_clear', array( $this, 'handle_clear' ) );
		add_action( 'admin_notices', array( $this, 'render_result_notice' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_assets' ) );
	}

	/**
	 * Load the batch runner script on the Protocol Manager screen only.
	 *
	 * @param string $hook_suffix Current admin page hook.
	 * @return void
	 */
	public function enqueue_assets( $hook_suffix ): void {
		$page = isset( $_GET['page'] ) ? sanitize_key( wp_unslash( $_GET['page'] ) ) : '';
		if ( Gear_Engine_Protocol_Manager::GEAR_ENGINE_PAGE_SLUG !== $page || ! Gear_Engine_Protocols::is_enabled() ) {
			return;
		}
		wp_enqueue_script( 'gear-engine-protocol-autofill', GEAR_ENGINE_URL . 'modules/exercises/assets/js/protocol-autofill.js', array(), GEAR_ENGINE_VERSION, true );
	}

	/**
	 * Score one exercise against condition text via "good for" tags and title.
	 *
	 * @param array<string, mixed> $exercise Exercise data from the query service.
	 * @param string               $text     Condition name plus summary, lowercase.
	 * @return int
	 */
	public static function score_exercise( array $exercise, string $text ): int {
		$text   = mb_strtolower( $text, 'UTF-8' );
		$score  = 0;
		$title  = mb_strtolower( (string) ( $exercise['title'] ?? '' ), 'UTF-8' );
		foreach ( preg_split( '/[;,]/', (string) ( $exercise['good_for'] ?? '' ) ) as $tag ) {
			$tag = trim( $tag );
			if ( mb_strlen( $tag, 'UTF-8' ) >= 3 && false !== mb_strpos( $text, mb_strtolower( $tag, 'UTF-8' ) ) ) {
				$score += 2;
			}
		}
		if ( '' !== $title && '' !== $text && false !== mb_strpos( $text, $title ) ) {
			$score += 3;
		}
		return $score;
	}

	/**
	 * Pick proposed exercises for one phase from a candidate pool.
	 *
	 * Pure selection used by both the batch handler and tests: keeps only
	 * positively matched, phase-risk-allowed exercises, ordered by session
	 * role (phase 3 puts moderate risk first inside a band), excluding any
	 * exercise already curated anywhere in the protocol.
	 *
	 * @param array<int, array<string, mixed>> $pool       Candidate exercise data.
	 * @param string                           $text       Condition name plus summary to match against.
	 * @param array<int, int>                  $excluded   Exercise IDs already tagged in the protocol tree.
	 * @param int                              $needed     Slots left to fill.
	 * @param int                              $phase      Phase number 1-3 (other values allow low+moderate).
	 * @return array<int, array{exercise: array<string, mixed>, score: int}> Selected exercises with scores.
	 */
	public static function pick_candidates( array $pool, string $text, array $excluded, int $needed, int $phase ): array {
		if ( $needed < 1 ) {
			return array();
		}
		$allowed = 1 === $phase ? array( 'low' ) : array( 'low', 'moderate' );
		$scored  = array();
		foreach ( $pool as $index => $exercise ) {
			$id = (int) ( $exercise['id'] ?? 0 );
			if ( $id < 1 || in_array( $id, $excluded, true ) ) {
				continue;
			}
			if ( ! in_array( (string) ( $exercise['risk'] ?? '' ), $allowed, true ) ) {
				continue;
			}
			$score = self::score_exercise( $exercise, $text );
			if ( $score < 1 ) {
				continue;
			}
			$scored[] = array(
				'band'    => Gear_Engine_Exercises_CPT::role_order( (string) ( $exercise['role'] ?? '' ) ),
				'heavier' => ( 3 === $phase && 'moderate' === (string) ( $exercise['risk'] ?? '' ) ) ? 0 : 1,
				'score'   => $score,
				'index'   => $index,
				'exercise' => $exercise,
			);
		}
		usort(
			$scored,
			static function ( array $a, array $b ): int {
				return $a['band'] <=> $b['band'] ?: $a['heavier'] <=> $b['heavier'] ?: $b['score'] <=> $a['score'] ?: $a['index'] <=> $b['index'];
			}
		);

		$picked = array_slice( $scored, 0, $needed );

		return array_map(
			static function ( array $row ): array {
				return array( 'exercise' => $row['exercise'], 'score' => (int) $row['score'] );
			},
			$picked
		);
	}

	/**
	 * Phase number inferred from a phase term's slug or name (1, 2 or 3, else 0).
	 *
	 * @param object $term Phase term.
	 * @return int
	 */
	public static function phase_number_for( $term ): int {
		foreach ( array( (string) $term->slug, (string) $term->name ) as $source ) {
			if ( preg_match( '/(?:^|[^0-9])([123])(?:[^0-9]|$)/', $source, $m ) ) {
				return (int) $m[1];
			}
		}
		return 0;
	}

	/**
	 * Exercise IDs tagged anywhere in a protocol tree.
	 *
	 * @param int $condition_term_id Top-level protocol term ID.
	 * @return array<int, int>
	 */
	public static function get_tree_exercise_ids( int $condition_term_id ): array {
		$query = new WP_Query(
			array(
				'post_type'      => Gear_Engine_Exercises_CPT::GEAR_ENGINE_POST_TYPE,
				'post_status'    => 'publish',
				'posts_per_page' => 200,
				'fields'         => 'ids',
				'no_found_rows'  => true,
				'tax_query'      => array(
					array(
						'taxonomy'         => Gear_Engine_Protocols::GEAR_ENGINE_TAXONOMY,
						'field'            => 'term_id',
						'terms'            => $condition_term_id,
						'include_children' => true,
					),
				),
			)
		);
		return array_map( 'absint', (array) $query->posts );
	}

	/**
	 * Fill every under-sized phase of one protocol condition.
	 *
	 * @param int $condition_term_id Top-level protocol term ID.
	 * @return array{protocol: string, phases: array<int, array{name: string, added: int, existing: int, gap: int, ids: array<int, int>>}>
	 */
	public static function fill_protocol( int $condition_term_id ): array {
		$condition = get_term( $condition_term_id, Gear_Engine_Protocols::GEAR_ENGINE_TAXONOMY );
		if ( ! $condition instanceof \WP_Term || 0 !== (int) $condition->parent ) {
			return array( 'protocol' => '', 'phases' => array() );
		}

		$children = get_terms(
			array(
				'taxonomy'   => Gear_Engine_Protocols::GEAR_ENGINE_TAXONOMY,
				'parent'     => $condition_term_id,
				'hide_empty' => false,
				'orderby'    => 'name',
			)
		);
		if ( is_wp_error( $children ) || empty( $children ) ) {
			// A protocol without phase terms is filled into the condition term itself.
			$children = array( $condition );
		}

		$tree_ids = self::get_tree_exercise_ids( $condition_term_id );
		$text     = (string) $condition->name . ' ' . (string) get_term_meta( $condition_term_id, Gear_Engine_Protocols::GEAR_ENGINE_META_SUMMARY, true );

		$report = array( 'protocol' => (string) $condition->name, 'phases' => array() );
		foreach ( $children as $phase_term ) {
			$phase_number = self::phase_number_for( $phase_term );
			$ceiling      = 1 === $phase_number ? 'low' : 'moderate';

			$existing = Gear_Engine_Exercise_Service::search_exercises(
				array(
					'protocol' => (string) $phase_term->slug,
					'limit'    => 50,
				)
			);
			$existing_ids = array_map(
				static function ( array $exercise ): int {
					return (int) $exercise['id'];
				},
				$existing
			);

			$gap = max( 0, self::TARGET_PER_PHASE - count( $existing_ids ) );
			$added = array();

			if ( $gap > 0 ) {
				$pool = Gear_Engine_Exercise_Service::search_exercises(
					array(
						'max_risk' => $ceiling,
						'limit'    => 50,
					)
				);
				$picks = self::pick_candidates( $pool, $text, array_unique( array_merge( $tree_ids, $existing_ids ) ), $gap, $phase_number );
				foreach ( $picks as $pick ) {
					$post_id = (int) $pick['exercise']['id'];
					wp_set_object_terms( $post_id, array( (int) $phase_term->term_id ), Gear_Engine_Protocols::GEAR_ENGINE_TAXONOMY, true );
					$added[] = $post_id;
				}
				if ( $added ) {
					$recorded   = array_map( 'absint', (array) get_term_meta( (int) $phase_term->term_id, self::META_AUTO_IDS, true ) );
					$merge      = array_values( array_unique( array_merge( $recorded, $added ) ) );
					update_term_meta( (int) $phase_term->term_id, self::META_AUTO_IDS, $merge );
					$tree_ids   = array_values( array_unique( array_merge( $tree_ids, $added ) ) );
				}
			}

			$report['phases'][] = array(
				'name'     => (string) $phase_term->name,
				'added'    => count( $added ),
				'existing' => count( $existing_ids ),
				'gap'      => max( 0, $gap - count( $added ) ),
				'ids'      => $added,
			);
		}

		return $report;
	}

	/**
	 * Remove this phase's auto-proposed assignments, keeping human picks.
	 *
	 * @param int $phase_term_id Phase term ID.
	 * @return int Number of exercises untagged.
	 */
	public static function clear_auto_for_term( int $phase_term_id ): int {
		$auto_ids = array_map( 'absint', (array) get_term_meta( $phase_term_id, self::META_AUTO_IDS, true ) );
		$cleared  = 0;
		foreach ( $auto_ids as $post_id ) {
			$current = wp_get_post_terms( $post_id, Gear_Engine_Protocols::GEAR_ENGINE_TAXONOMY, array( 'fields' => 'ids' ) );
			if ( is_wp_error( $current ) || ! is_array( $current ) ) {
				continue;
			}
			$keep = array_values( array_diff( array_map( 'absint', $current ), array( $phase_term_id ) ) );
			wp_set_object_terms( $post_id, $keep, Gear_Engine_Protocols::GEAR_ENGINE_TAXONOMY );
			++$cleared;
		}
		delete_term_meta( $phase_term_id, self::META_AUTO_IDS );
		return $cleared;
	}

	/**
	 * Handle one auto-fill request for a single protocol condition.
	 *
	 * @return void
	 */
	public function handle_fill(): void {
		check_admin_referer( self::NONCE_ACTION, 'gear_engine_autofill_nonce' );
		if ( ! current_user_can( 'edit_posts' ) ) {
			wp_die( esc_html__( 'You do not have permission to do this.', 'gear-engine' ), '', array( 'response' => 403 ) );
		}
		$term_id = isset( $_POST['term_id'] ) ? absint( wp_unslash( $_POST['term_id'] ) ) : 0;

		$report    = $term_id > 0 ? self::fill_protocol( $term_id ) : array( 'protocol' => '', 'phases' => array() );
		$transient = get_transient( $this->report_key() );
		$all       = is_array( $transient ) ? $transient : array();
		if ( '' !== $report['protocol'] ) {
			$all[ $term_id ] = $report;
		}
		set_transient( $this->report_key(), $all, 120 );

		if ( isset( $_POST['gear_engine_format'] ) && 'json' === (string) wp_unslash( $_POST['gear_engine_format'] ) ) {
			wp_send_json_success( array( 'term_id' => $term_id, 'report' => $report ) );
		}
		wp_safe_redirect( $this->return_url( 'gear_engine_autofill=done' ) );
		exit;
	}

	/**
	 * Handle clearing one phase's auto proposals.
	 *
	 * @return void
	 */
	public function handle_clear(): void {
		check_admin_referer( self::NONCE_ACTION, 'gear_engine_autofill_nonce' );
		if ( ! current_user_can( 'edit_posts' ) ) {
			wp_die( esc_html__( 'You do not have permission to do this.', 'gear-engine' ), '', array( 'response' => 403 ) );
		}
		$term_id = isset( $_GET['term_id'] ) ? absint( wp_unslash( $_GET['term_id'] ) ) : 0;
		if ( $term_id > 0 ) {
			self::clear_auto_for_term( $term_id );
		}
		wp_safe_redirect( $this->return_url( 'gear_engine_autofill_cleared=' . $term_id ) );
		exit;
	}

	/**
	 * Where to return after a fill or clear request.
	 *
	 * @param string $param Extra query string appended to the manager URL.
	 * @return string
	 */
	private function return_url( string $param ): string {
		$ref = isset( $_REQUEST['_wp_http_referer'] ) ? esc_url_raw( wp_unslash( $_REQUEST['_wp_http_referer'] ) ) : '';
		$url = $ref ? wp_validate_redirect( $ref, admin_url( 'edit.php' ) ) : admin_url( 'edit.php' );
		return add_query_arg( $param, $url );
	}

	/**
	 * Per-user transient key for the last fill report.
	 *
	 * @return string
	 */
	private function report_key(): string {
		return 'gear_engine_autofill_report_' . get_current_user_id();
	}

	/**
	 * Show the plain-language result of the last auto pass.
	 *
	 * @return void
	 */
	public function render_result_notice(): void {
		$screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
		if ( ! $screen || false === strpos( (string) $screen->id, Gear_Engine_Protocol_Manager::GEAR_ENGINE_PAGE_SLUG ) ) {
			return;
		}
		$report = get_transient( $this->report_key() );
		if ( ! is_array( $report ) || empty( $report ) ) {
			return;
		}
		delete_transient( $this->report_key() );

		echo '<div class="notice notice-info is-dismissible"><p><strong>' . esc_html__( 'Auto-fill finished', 'gear-engine' ) . '</strong></p><ul style="list-style:disc;margin-left:20px">';
		foreach ( $report as $protocol_report ) {
			$total_added = 0;
			$total_gap   = 0;
			foreach ( $protocol_report['phases'] as $phase ) {
				$total_added += (int) $phase['added'];
				$total_gap   += (int) $phase['gap'];
			}
			printf(
				'<li>%s — %s',
				'<strong>' . esc_html( (string) $protocol_report['protocol'] ) . '</strong>: ',
				esc_html(
					sprintf(
						/* translators: 1: added count, 2: still missing count */
						__( '%1$d proposed, %2$d still missing (review the matrix, then approve)', 'gear-engine' ),
						$total_added,
						$total_gap
					)
				)
			);
			if ( $protocol_report['phases'] ) {
				$bits = array();
				foreach ( $protocol_report['phases'] as $phase ) {
					$bits[] = $phase['name'] . ': +' . $phase['added'] . ( $phase['gap'] > 0 ? ' (' . $phase['gap'] . ' short)' : '' );
				}
				echo ' <em>(' . esc_html( implode( ', ', $bits ) ) . ')</em>';
			}
			echo '</li>';
		}
		echo '</ul><p>' . esc_html__( 'Auto picks are marked and can be cleared per phase. Nothing was approved.', 'gear-engine' ) . '</p></div>';
	}
}
