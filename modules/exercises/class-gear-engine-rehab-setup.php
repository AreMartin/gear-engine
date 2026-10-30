<?php
/**
 * Guided Rehab Setup page: library tagging, protocol scaffolding,
 * auto-fill review and approval in one place.
 *
 * Replaces the five-screen workflow with a single guided page. Automation
 * drafts; the human always confirms: suggested Good-for tags and session
 * roles are only applied on explicit confirm, risk levels are never
 * suggested, and protocols stay Draft until an admin approves them here or
 * in the Protocol Manager.
 *
 * @package GearEngine
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Gear_Engine_Rehab_Setup
 */
class Gear_Engine_Rehab_Setup {

	public const PAGE_SLUG    = 'gear-engine-rehab-setup';
	public const NONCE_ACTION = 'gear_engine_rehab_setup';
	public const META_SUGGEST = '_gear_engine_exercise_suggestions';
	public const META_TEXT_DRAFT = '_gear_engine_protocol_text_draft';
	public const PHASE_COUNT  = 3;

	/**
	 * Constructor.
	 */
	public function __construct() {
		add_action( 'admin_menu', array( $this, 'register_page' ) );
		add_action( 'admin_menu', array( $this, 'tidy_menu' ), 99 );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_assets' ) );
		add_action( 'admin_post_gear_engine_rehab_enable', array( $this, 'handle_enable' ) );
		add_action( 'admin_post_gear_engine_rehab_suggest', array( $this, 'handle_suggest' ) );
		add_action( 'admin_post_gear_engine_rehab_confirm', array( $this, 'handle_confirm' ) );
		add_action( 'admin_post_gear_engine_rehab_confirm_all', array( $this, 'handle_confirm_all' ) );
		add_action( 'admin_post_gear_engine_rehab_seed', array( $this, 'handle_seed' ) );
		add_action( 'admin_post_gear_engine_rehab_save_fields', array( $this, 'handle_save_fields' ) );
		add_action( 'admin_post_gear_engine_rehab_slot_add', array( $this, 'handle_slot_add' ) );
		add_action( 'admin_post_gear_engine_rehab_slot_remove', array( $this, 'handle_slot_remove' ) );
		add_action( 'admin_post_gear_engine_rehab_approve', array( $this, 'handle_approve' ) );
		add_action( 'admin_notices', array( $this, 'render_notice' ) );
	}

	/* ------------------------------------------------------------------
	 * Pure helpers (unit tested).
	 * ---------------------------------------------------------------- */

	/**
	 * Words too generic to identify a condition on their own.
	 *
	 * @return array<int, string>
	 */
	public static function get_stopwords(): array {
		return array(
			'mulig', 'akutt', 'kronisk', 'uspesifikke', 'skade', 'skader', 'smerte', 'smerter',
			'smertene', 'plager', 'etter', 'vurdering', 'anbefales', 'lege', 'legen', 'oyeblikkelig',
			'hjelp', 'hos', 'rundt', 'mellom', 'nedsatt', 'kraft', 'hodepine',
		);
	}

	/**
	 * Distinctive keywords per diagnosis, taken from its own title.
	 *
	 * Words in parentheses are strongest (e.g. "hoppekne", "artrose"),
	 * followed by specific title words of five letters or more.
	 *
	 * @param array<string, mixed> $config Region config.
	 * @return array<string, array<int, string>> Map of diagnosis id to keywords.
	 */
	public static function diagnosis_keywords( array $config ): array {
		$stop = self::get_stopwords();
		$map  = array();
		foreach ( (array) ( $config['diagnoses'] ?? array() ) as $id => $dx ) {
			if ( ! is_array( $dx ) ) {
				continue;
			}
			$title = (string) ( $dx['title'] ?? '' );
			$words = array();
			if ( preg_match_all( '/\(([^)]+)\)/u', $title, $m ) ) {
				foreach ( $m[1] as $group ) {
					foreach ( preg_split( '/[\s,;]+/u', mb_strtolower( $group, 'UTF-8' ) ) as $w ) {
						if ( mb_strlen( $w, 'UTF-8' ) >= 4 && ! in_array( $w, $stop, true ) ) {
							$words[] = $w;
						}
					}
				}
			}
			$bare = preg_replace( '/\([^)]*\)/u', ' ', $title );
			foreach ( preg_split( '/[\s,;\/]+/u', mb_strtolower( $bare, 'UTF-8' ) ) as $w ) {
				$w = trim( $w, '.:-' );
				if ( mb_strlen( $w, 'UTF-8' ) >= 5 && ! in_array( $w, $stop, true ) && ! in_array( $w, $words, true ) ) {
					$words[] = $w;
				}
			}
			if ( $words ) {
				$map[ (string) $id ] = array_slice( array_unique( $words ), 0, 6 );
			}
		}
		return $map;
	}

	/**
	 * Suggest a session role from exercise text. First match wins in session order.
	 *
	 * @param string $text Lowercased exercise title plus description.
	 * @return string Role slug or ''.
	 */
	public static function suggest_role( string $text ): string {
		$text = mb_strtolower( $text, 'UTF-8' );
		$map  = array(
			'mobility'   => array( 'strekk', 'rull', 'pendel', 'sirkel', 'vippe', 'katt', 'beveg', 'mobili', 'smoerke' ),
			'activation' => array( 'klem', 'spenn', 'spenning', 'aktiver', 'samle', 'brø', 'bro ', 'våk', 'pump' ),
			'stability'  => array( 'balanse', 'ett ben', 'balanser', 'sideplank', 'fugl', 'hold kroppen' ),
			'strength'   => array( 'press', 'knebøy', 'løft', 'hevning', 'rodding', 'stig', 'utfor', 'motstand' ),
			'recovery'   => array( 'avspenn', 'avslapp', 'pust', 'is mot', ' isrull', 'rolig hvil', 'sove' ),
		);
		foreach ( $map as $role => $needles ) {
			foreach ( $needles as $needle ) {
				if ( '' !== $needle && false !== mb_strpos( $text, $needle ) ) {
					return $role;
				}
			}
		}
		return '';
	}

	/**
	 * Match exercise text against diagnosis keyword sets.
	 *
	 * @param string                             $text Lowercased exercise text.
	 * @param array<string, array<int, string>> $keywords Keyword map from diagnosis_keywords().
	 * @return array<int, string> Matched keywords, most matched first, max 6.
	 */
	public static function suggest_good_for( string $text, array $keywords ): array {
		$text  = mb_strtolower( $text, 'UTF-8' );
		$hits  = array();
		$order = array();
		foreach ( $keywords as $words ) {
			foreach ( $words as $word ) {
				if ( mb_strlen( $word, 'UTF-8' ) >= 4 && false !== mb_strpos( $text, $word ) ) {
					if ( ! isset( $hits[ $word ] ) ) {
						$hits[ $word ] = 0;
						$order[]       = $word;
					}
					++$hits[ $word ];
				}
			}
		}
		usort(
			$order,
			static function ( string $a, string $b ) use ( $hits ): int {
				return $hits[ $b ] <=> $hits[ $a ];
			}
		);
		return array_slice( $order, 0, 6 );
	}

	/**
	 * Whether a diagnosis severity should get a program at all.
	 *
	 * @param array<string, mixed> $dx Diagnosis definition.
	 * @return bool
	 */
	public static function diagnosis_needs_program( array $dx ): bool {
		return ! in_array( (string) ( $dx['severity'] ?? '' ), array( 'Høy', 'Kritisk' ), true );
	}

	/* ------------------------------------------------------------------
	 * Data passes.
	 * ---------------------------------------------------------------- */

	/**
	 * Run the suggestion pass over published exercises.
	 *
	 * @return int Number of exercises that received suggestions.
	 */
	public static function run_suggestions(): int {
		$keywords = array();
		foreach ( Gear_Engine_Triage::get_regions() as $region ) {
			$config = Gear_Engine_Triage::get_config( (string) $region );
			if ( ! is_wp_error( $config ) ) {
				$keywords[] = self::diagnosis_keywords( $config );
			}
		}

		$posts = get_posts(
			array(
				'post_type'      => Gear_Engine_Exercises_CPT::GEAR_ENGINE_POST_TYPE,
				'post_status'    => 'publish',
				'posts_per_page' => 200,
			)
		);

		$count = 0;
		foreach ( $posts as $post ) {
			$text        = $post->post_title . ' ' . $post->post_content . ' ' . $post->post_excerpt;
			$flat        = array();
			foreach ( $keywords as $by_dx ) {
				foreach ( $by_dx as $words ) {
					foreach ( $words as $w ) {
						$flat[ $w ] = true;
					}
				}
			}
			$suggested_tags = self::suggest_good_for( $text, array( array_keys( $flat ) ) );
			$suggested_role = self::suggest_role( $text );
			if ( ! $suggested_tags && '' === $suggested_role ) {
				continue;
			}
			update_post_meta(
				(int) $post->ID,
				self::META_SUGGEST,
				array( 'good_for' => $suggested_tags, 'role' => $suggested_role )
			);
			++$count;
		}
		return $count;
	}

	/**
	 * Apply confirmed suggestions to one exercise.
	 *
	 * @param int         $post_id Exercise ID.
	 * @param string[]    $tags    Confirmed good-for keywords.
	 * @param string      $role    Confirmed role slug ('' keeps current).
	 * @return void
	 */
	public static function confirm_suggestions( int $post_id, array $tags, string $role ): void {
		$existing = array_filter( array_map( 'trim', preg_split( '/[,;]/', (string) get_post_meta( $post_id, Gear_Engine_Exercises_CPT::GEAR_ENGINE_META_GOOD_FOR, true ) ) ) );
		$merged   = array_values( array_unique( array_merge( $existing, array_map( 'sanitize_text_field', $tags ) ) ) );
		if ( $merged ) {
			update_post_meta( $post_id, Gear_Engine_Exercises_CPT::GEAR_ENGINE_META_GOOD_FOR, Gear_Engine_Exercises_CPT::sanitize_good_for( implode( ',', $merged ) ) );
		}
		$role = Gear_Engine_Exercises_CPT::sanitize_role( $role );
		if ( '' !== $role ) {
			wp_set_object_terms( $post_id, array( $role ), Gear_Engine_Exercises_CPT::GEAR_ENGINE_ROLE_TAXONOMY );
		}
		delete_post_meta( $post_id, self::META_SUGGEST );
	}

	/**
	 * Create draft protocols (with codes and Fase 1-3) from the quiz configs.
	 *
	 * @return array{created: int, skipped: int}
	 */
	public static function seed_protocols_from_quiz(): array {
		$result = array( 'created' => 0, 'skipped' => 0 );
		if ( ! current_user_can( 'edit_terms' ) ) {
			return $result;
		}
		foreach ( Gear_Engine_Triage::get_regions() as $region ) {
			$config = Gear_Engine_Triage::get_config( (string) $region );
			if ( is_wp_error( $config ) ) {
				continue;
			}
			foreach ( (array) ( $config['diagnoses'] ?? array() ) as $dx ) {
				if ( ! is_array( $dx ) || ! self::diagnosis_needs_program( $dx ) ) {
					continue;
				}
				$title = trim( (string) ( $dx['title'] ?? '' ) );
				if ( '' === $title ) {
					continue;
				}
				$slug = sanitize_title( $title );
				if ( get_term_by( 'slug', $slug, Gear_Engine_Protocols::GEAR_ENGINE_TAXONOMY ) ) {
					++$result['skipped'];
					continue;
				}
				$inserted = wp_insert_term(
					$title,
					Gear_Engine_Protocols::GEAR_ENGINE_TAXONOMY,
					array( 'slug' => $slug )
				);
				if ( is_wp_error( $inserted ) || empty( $inserted['term_id'] ) ) {
					continue;
				}
				$term_id = (int) $inserted['term_id'];
				$codes   = Gear_Engine_Protocols::sanitize_codes( implode( ', ', array_filter( array( (string) ( $dx['icd10'] ?? '' ), (string) ( $dx['icpc2'] ?? '' ) ) ) ) );
				update_term_meta( $term_id, Gear_Engine_Protocols::GEAR_ENGINE_META_CODES, $codes );
				update_term_meta( $term_id, Gear_Engine_Protocols::GEAR_ENGINE_META_STATUS, Gear_Engine_Protocols::GEAR_ENGINE_STATUS_DRAFT );
				// Safe starter text so nobody faces a blank page; the draft flag
				// blocks approval until a human has edited (and thereby owned) it.
				update_term_meta( $term_id, Gear_Engine_Protocols::GEAR_ENGINE_META_SUMMARY, sprintf( 'Utkast - rediger: treningsmål og ramme for %s.', mb_strtolower( $title, 'UTF-8' ) ) );
				update_term_meta( $term_id, Gear_Engine_Protocols::GEAR_ENGINE_META_RED_FLAGS, 'Utkast - rediger: Smertene øker kraftig, nummenhet, kraftsvikt eller nye symptomer. Du får feber eller blir syk. Kneskade: klarer ikke å belaste. Stopp og få det vurdert.' );
				update_term_meta( $term_id, self::META_TEXT_DRAFT, 1 );
				for ( $n = 1; $n <= self::PHASE_COUNT; ++$n ) {
					$phase_slug = 'fase-' . $n;
					$exists     = false;
					foreach ( (array) get_terms( array( 'taxonomy' => Gear_Engine_Protocols::GEAR_ENGINE_TAXONOMY, 'parent' => $term_id, 'hide_empty' => false ) ) as $child ) {
						if ( isset( $child->slug ) && $phase_slug === $child->slug ) {
							$exists = true;
							break;
						}
					}
					if ( ! $exists ) {
						wp_insert_term( 'Fase ' . $n, Gear_Engine_Protocols::GEAR_ENGINE_TAXONOMY, array( 'slug' => $phase_slug, 'parent' => $term_id ) );
					}
				}
				++$result['created'];
			}
		}
		return $result;
	}

	/**
	 * Approval blockers: the taxonomy requirements plus the unedited-draft rule.
	 *
	 * @param int $term_id Protocol term ID.
	 * @return array<int, string>
	 */
	public static function approval_blockers( int $term_id ): array {
		$missing = Gear_Engine_Protocols::get_missing_for_term( $term_id );
		if ( get_term_meta( $term_id, self::META_TEXT_DRAFT, true ) ) {
			$missing[] = __( 'draft clinical text (edit the Utkast lines first)', 'gear-engine' );
		}
		return $missing;
	}

	/**
	 * Approve a protocol if every requirement is met.
	 *
	 * @param int $term_id Protocol term ID.
	 * @return array<int, string> Missing requirements; empty means approved.
	 */
	public static function try_approve( int $term_id ): array {
		$missing = self::approval_blockers( $term_id );
		if ( $missing ) {
			return $missing;
		}
		update_term_meta( $term_id, Gear_Engine_Protocols::GEAR_ENGINE_META_STATUS, Gear_Engine_Protocols::GEAR_ENGINE_STATUS_APPROVED );
		update_term_meta( $term_id, Gear_Engine_Protocols::GEAR_ENGINE_META_REVIEWED, gmdate( 'Y-m-d' ) );
		if ( function_exists( 'wp_get_current_user' ) ) {
			$user = wp_get_current_user();
			update_term_meta( $term_id, Gear_Engine_Protocols::GEAR_ENGINE_META_REVIEWER, is_object( $user ) && ! empty( $user->display_name ) ? sanitize_text_field( $user->display_name ) : '' );
		}
		return array();
	}

	/**
	 * Exercises currently tagged to one phase term, in session order.
	 *
	 * @param int $phase_term_id Phase term ID.
	 * @return array<int, array<string, mixed>>
	 */
	public static function get_phase_exercises( int $phase_term_id ): array {
		$ids   = get_posts(
			array(
				'post_type'      => Gear_Engine_Exercises_CPT::GEAR_ENGINE_POST_TYPE,
				'post_status'    => 'publish',
				'posts_per_page' => 50,
				'fields'         => 'ids',
				'tax_query'      => array(
					array(
						'taxonomy' => Gear_Engine_Protocols::GEAR_ENGINE_TAXONOMY,
						'field'    => 'term_id',
						'terms'    => $phase_term_id,
					),
				),
			)
		);
		$rows  = array();
		foreach ( (array) $ids as $id ) {
			$data = Gear_Engine_Exercise_Service::get_exercise_data( get_post( (int) $id ) );
			if ( $data ) {
				$rows[] = $data;
			}
		}
		usort(
			$rows,
			static function ( array $a, array $b ): int {
				return Gear_Engine_Exercises_CPT::role_order( (string) ( $a['role'] ?? '' ) ) <=> Gear_Engine_Exercises_CPT::role_order( (string) ( $b['role'] ?? '' ) );
			}
		);
		return $rows;
	}

	/**
	 * Top candidate exercises to add to a phase.
	 *
	 * @param object $condition Top-level protocol term.
	 * @param object $phase     Phase term.
	 * @return array<int, array<string, mixed>>
	 */
	public static function get_slot_candidates( $condition, $phase ): array {
		$phase_number = Gear_Engine_Protocol_Autofill::phase_number_for( $phase );
		$ceiling      = 1 === $phase_number ? 'low' : 'moderate';
		$tree_ids     = Gear_Engine_Protocol_Autofill::get_tree_exercise_ids( (int) $condition->term_id );
		$pool         = Gear_Engine_Exercise_Service::search_exercises(
			array(
				'max_risk' => $ceiling,
				'limit'    => 50,
			)
		);
		$text         = (string) $condition->name . ' ' . (string) get_term_meta( (int) $condition->term_id, Gear_Engine_Protocols::GEAR_ENGINE_META_SUMMARY, true );
		$picks        = Gear_Engine_Protocol_Autofill::pick_candidates( $pool, $text, $tree_ids, 15, $phase_number );
		return array_map(
			static function ( array $pick ): array {
				return $pick['exercise'];
			},
			$picks
		);
	}

	/* ------------------------------------------------------------------
	 * Admin wiring.
	 * ---------------------------------------------------------------- */

	/**
	 * Register the page.
	 *
	 * @return void
	 */
	public function register_page(): void {
		add_submenu_page(
			'edit.php?post_type=' . Gear_Engine_Exercises_CPT::GEAR_ENGINE_POST_TYPE,
			__( 'Rehab Setup', 'gear-engine' ),
			__( 'Rehab Setup', 'gear-engine' ),
			'edit_posts',
			self::PAGE_SLUG,
			array( $this, 'render_page' ),
			4
		);
	}

	/**
	 * Hide the power-user screens behind Rehab Setup so the menu shows one path.
	 *
	 * The pages themselves stay reachable by URL (and from the links at the
	 * bottom of the setup page); only the menu clutter goes away.
	 *
	 * @return void
	 */
	public function tidy_menu(): void {
		$parent = 'edit.php?post_type=' . Gear_Engine_Exercises_CPT::GEAR_ENGINE_POST_TYPE;
		remove_submenu_page( $parent, Gear_Engine_Protocol_Manager::GEAR_ENGINE_PAGE_SLUG );
		remove_submenu_page( $parent, Gear_Engine_Coverage_Report::GEAR_ENGINE_PAGE_SLUG );
		remove_submenu_page( $parent, 'edit-tags.php?taxonomy=' . Gear_Engine_Protocols::GEAR_ENGINE_TAXONOMY . '&post_type=' . Gear_Engine_Exercises_CPT::GEAR_ENGINE_POST_TYPE );
	}

	/**
	 * Page script for the batch fill loop.
	 *
	 * @param string $hook_suffix Hook suffix.
	 * @return void
	 */
	public function enqueue_assets( $hook_suffix ): void {
		$page = isset( $_GET['page'] ) ? sanitize_key( wp_unslash( $_GET['page'] ) ) : '';
		if ( self::PAGE_SLUG !== $page ) {
			return;
		}
		wp_enqueue_script( 'gear-engine-rehab-setup', GEAR_ENGINE_URL . 'modules/exercises/assets/js/rehab-setup.js', array(), GEAR_ENGINE_VERSION, true );
	}

	/**
	 * Guard shared by all handlers.
	 *
	 * @return void
	 */
	private function guard(): void {
		if ( ! current_user_can( 'edit_posts' ) ) {
			wp_die( esc_html__( 'You do not have permission to do this.', 'gear-engine' ), '', array( 'response' => 403 ) );
		}
	}

	/**
	 * Flash a message on the setup page.
	 *
	 * @param string $message Message text.
	 * @return void
	 */
	private function flash( string $message ): void {
		set_transient( 'gear_engine_rehab_notice_' . get_current_user_id(), $message, 90 );
	}

	/**
	 * Redirect back to the setup page.
	 *
	 * @return void
	 */
	private function back(): void {
		wp_safe_redirect( admin_url( 'edit.php?post_type=' . Gear_Engine_Exercises_CPT::GEAR_ENGINE_POST_TYPE . '&page=' . self::PAGE_SLUG ) );
		exit;
	}

	/**
	 * Show the flashed message.
	 *
	 * @return void
	 */
	public function render_notice(): void {
		$page = isset( $_GET['page'] ) ? sanitize_key( wp_unslash( $_GET['page'] ) ) : '';
		if ( self::PAGE_SLUG !== $page ) {
			return;
		}
		$message = get_transient( 'gear_engine_rehab_notice_' . get_current_user_id() );
		if ( ! is_string( $message ) || '' === $message ) {
			return;
		}
		delete_transient( 'gear_engine_rehab_notice_' . get_current_user_id() );
		echo '<div class="notice notice-success is-dismissible"><p>' . esc_html( $message ) . '</p></div>';
	}

	/**
	 * Enable the protocols feature.
	 *
	 * @return void
	 */
	public function handle_enable(): void {
		check_admin_referer( self::NONCE_ACTION, 'gear_engine_rehab_nonce' );
		$this->guard();
		if ( ! current_user_can( 'manage_options' ) ) {
			$this->flash( __( 'You need administrator access to enable protocols.', 'gear-engine' ) );
			$this->back();
		}
		update_option( 'gear_engine_enable_protocols', true );
		$this->flash( __( 'Clinical protocols enabled.', 'gear-engine' ) );
		$this->back();
	}

	/**
	 * Run the suggestion pass.
	 *
	 * @return void
	 */
	public function handle_suggest(): void {
		check_admin_referer( self::NONCE_ACTION, 'gear_engine_rehab_nonce' );
		$this->guard();
		$count = self::run_suggestions();
		$this->flash( sprintf( /* translators: %d: count */ _n( 'Suggestions ready for %d exercise.', 'Suggestions ready for %d exercises.', $count, 'gear-engine' ), $count ) );
		$this->back();
	}

	/**
	 * Confirm suggestions for one exercise.
	 *
	 * @return void
	 */
	public function handle_confirm(): void {
		check_admin_referer( self::NONCE_ACTION, 'gear_engine_rehab_nonce' );
		$this->guard();
		$post_id = isset( $_POST['post_id'] ) ? absint( wp_unslash( $_POST['post_id'] ) ) : 0;
		$tags    = isset( $_POST['confirm_tags'] ) && is_array( $_POST['confirm_tags'] ) ? array_map( 'sanitize_text_field', wp_unslash( $_POST['confirm_tags'] ) ) : array();
		$role    = isset( $_POST['confirm_role'] ) ? sanitize_text_field( wp_unslash( $_POST['confirm_role'] ) ) : '';
		if ( $post_id > 0 && current_user_can( 'edit_post', $post_id ) ) {
			self::confirm_suggestions( $post_id, $tags, $role );
		}
		$this->back();
	}

	/**
	 * Apply every pending suggestion in one pass.
	 *
	 * @return int Number of exercises confirmed.
	 */
	public static function confirm_all_suggestions(): int {
		$posts = get_posts(
			array(
				'post_type'      => Gear_Engine_Exercises_CPT::GEAR_ENGINE_POST_TYPE,
				'post_status'    => 'publish',
				'posts_per_page' => 200,
				'meta_query'     => array( array( 'key' => self::META_SUGGEST, 'compare' => 'EXISTS' ) ),
			)
		);
		$count = 0;
		foreach ( $posts as $post ) {
			$s = get_post_meta( (int) $post->ID, self::META_SUGGEST, true );
			if ( ! is_array( $s ) || ! current_user_can( 'edit_post', (int) $post->ID ) ) {
				continue;
			}
			self::confirm_suggestions( (int) $post->ID, (array) ( $s['good_for'] ?? array() ), (string) ( $s['role'] ?? '' ) );
			++$count;
		}
		return $count;
	}

	/**
	 * Confirm every pending suggestion in one pass.
	 *
	 * @return void
	 */
	public function handle_confirm_all(): void {
		check_admin_referer( self::NONCE_ACTION, 'gear_engine_rehab_nonce' );
		$this->guard();
		$count = self::confirm_all_suggestions();
		$this->flash( sprintf( /* translators: %d: count */ _n( 'Confirmed suggestions for %d exercise.', 'Confirmed suggestions for %d exercises.', $count, 'gear-engine' ), $count ) );
		$this->back();
	}

	/**
	 * Seed protocols from the quiz configs.
	 *
	 * @return void
	 */
	public function handle_seed(): void {
		check_admin_referer( self::NONCE_ACTION, 'gear_engine_rehab_nonce' );
		$this->guard();
		$result = self::seed_protocols_from_quiz();
		$this->flash( sprintf( /* translators: 1: created, 2: skipped */ __( 'Protocols created: %1$d. Already existed: %2$d.', 'gear-engine' ), $result['created'], $result['skipped'] ) );
		$this->back();
	}

	/**
	 * Save inline clinical fields for one protocol.
	 *
	 * @return void
	 */
	public function handle_save_fields(): void {
		check_admin_referer( self::NONCE_ACTION, 'gear_engine_rehab_nonce' );
		$this->guard();
		$term_id = isset( $_POST['term_id'] ) ? absint( wp_unslash( $_POST['term_id'] ) ) : 0;
		if ( $term_id < 1 || ! current_user_can( 'edit_terms' ) ) {
			$this->back();
		}
		update_term_meta( $term_id, Gear_Engine_Protocols::GEAR_ENGINE_META_CODES, Gear_Engine_Protocols::sanitize_codes( wp_unslash( $_POST['field_codes'] ?? '' ) ) );
		update_term_meta( $term_id, Gear_Engine_Protocols::GEAR_ENGINE_META_SUMMARY, sanitize_textarea_field( wp_unslash( $_POST['field_summary'] ?? '' ) ) );
		update_term_meta( $term_id, Gear_Engine_Protocols::GEAR_ENGINE_META_RED_FLAGS, sanitize_textarea_field( wp_unslash( $_POST['field_red_flags'] ?? '' ) ) );
		// Saving from this page means a human owned the text: lift the draft block.
		delete_term_meta( $term_id, self::META_TEXT_DRAFT );
		$this->flash( __( 'Protocol text saved.', 'gear-engine' ) );
		$this->back();
	}

	/**
	 * Add one exercise to one phase from the candidate picker.
	 *
	 * @return void
	 */
	public function handle_slot_add(): void {
		check_admin_referer( self::NONCE_ACTION, 'gear_engine_rehab_nonce' );
		$this->guard();
		$term_id = isset( $_POST['term_id'] ) ? absint( wp_unslash( $_POST['term_id'] ) ) : 0;
		$post_id = isset( $_POST['post_id'] ) ? absint( wp_unslash( $_POST['post_id'] ) ) : 0;
		if ( $term_id > 0 && $post_id > 0 && current_user_can( 'edit_post', $post_id ) ) {
			wp_set_object_terms( $post_id, array( $term_id ), Gear_Engine_Protocols::GEAR_ENGINE_TAXONOMY, true );
		}
		$this->back();
	}

	/**
	 * Remove one exercise from one phase.
	 *
	 * @return void
	 */
	public function handle_slot_remove(): void {
		check_admin_referer( self::NONCE_ACTION, 'gear_engine_rehab_nonce' );
		$this->guard();
		$term_id = isset( $_POST['term_id'] ) ? absint( wp_unslash( $_POST['term_id'] ) ) : 0;
		$post_id = isset( $_POST['post_id'] ) ? absint( wp_unslash( $_POST['post_id'] ) ) : 0;
		if ( $term_id > 0 && $post_id > 0 && current_user_can( 'edit_post', $post_id ) ) {
			$current = wp_get_post_terms( $post_id, Gear_Engine_Protocols::GEAR_ENGINE_TAXONOMY, array( 'fields' => 'ids' ) );
			if ( is_array( $current ) ) {
				wp_set_object_terms( $post_id, array_values( array_diff( array_map( 'absint', $current ), array( $term_id ) ) ), Gear_Engine_Protocols::GEAR_ENGINE_TAXONOMY );
			}
		}
		$this->back();
	}

	/**
	 * Approve a protocol when complete.
	 *
	 * @return void
	 */
	public function handle_approve(): void {
		check_admin_referer( self::NONCE_ACTION, 'gear_engine_rehab_nonce' );
		$this->guard();
		$term_id = isset( $_POST['term_id'] ) ? absint( wp_unslash( $_POST['term_id'] ) ) : 0;
		if ( $term_id > 0 && current_user_can( 'edit_terms' ) ) {
			$missing = self::try_approve( $term_id );
			$this->flash( $missing
				? sprintf( /* translators: %s: list */ __( 'Still missing before approval: %s.', 'gear-engine' ), implode( ', ', $missing ) )
				: __( 'Protocol approved and now live for matching quiz results.', 'gear-engine' ) );
		}
		$this->back();
	}

	/* ------------------------------------------------------------------
	 * Render.
	 * ---------------------------------------------------------------- */

	/**
	 * Render the guided page.
	 *
	 * @return void
	 */
	public function render_page(): void {
		if ( ! current_user_can( 'edit_posts' ) ) {
			wp_die( esc_html__( 'You do not have permission to view this page.', 'gear-engine' ), '', array( 'response' => 403 ) );
		}
		$nonce = wp_create_nonce( self::NONCE_ACTION );
		echo '<div class="wrap"><h1>' . esc_html__( 'Rehab Setup', 'gear-engine' ) . '</h1>';
		echo '<p class="description">' . esc_html__( 'Four steps from empty library to live programs. Everything the assistant drafts, you confirm.', 'gear-engine' ) . '</p>';

		if ( ! Gear_Engine_Protocols::is_enabled() ) {
			echo '<div class="notice notice-info inline"><p>';
			echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '" style="display:inline">';
			wp_nonce_field( self::NONCE_ACTION, 'gear_engine_rehab_nonce' );
			echo '<input type="hidden" name="action" value="gear_engine_rehab_enable" /><button class="button button-primary">' . esc_html__( 'Enable clinical protocols', 'gear-engine' ) . '</button> ';
			echo esc_html__( 'The protocol features are switched off in settings. Enable them to continue this setup.', 'gear-engine' );
			echo '</form></p></div></div>';
			return;
		}

		$published = (int) ( wp_count_posts( Gear_Engine_Exercises_CPT::GEAR_ENGINE_POST_TYPE )->publish ?? 0 );
		$pending   = count( (array) get_posts(
			array(
				'post_type'      => Gear_Engine_Exercises_CPT::GEAR_ENGINE_POST_TYPE,
				'post_status'    => 'publish',
				'posts_per_page' => 200,
				'fields'         => 'ids',
				'meta_query'     => array( array( 'key' => self::META_SUGGEST, 'compare' => 'EXISTS' ) ),
			)
		) );
		$conditions = 0;
		$underfilled = 0;
		foreach ( Gear_Engine_Protocol_Manager::get_protocol_tree() as $row ) {
			if ( 0 === (int) $row['term']->parent ) {
				++$conditions;
			} elseif ( (int) $row['term']->count < Gear_Engine_Protocol_Autofill::TARGET_PER_PHASE ) {
				++$underfilled;
			}
		}

		$steps = array(
			'library'    => array( $published > 0 && 0 === $pending, $published > 0 ? sprintf( /* translators: %d: published count */ __( 'Done — %d exercises published.', 'gear-engine' ), $published ) : __( 'Import exercises first.', 'gear-engine' ) ),
			'protocols'  => array( $conditions > 0, $conditions > 0 ? sprintf( /* translators: %d: count */ __( 'Done — %d protocols exist.', 'gear-engine' ), $conditions ) : __( 'Create them from the quiz in one click.', 'gear-engine' ) ),
			'fill'       => array( $conditions > 0 && 0 === $underfilled, $underfilled > 0 ? sprintf( /* translators: %d: count */ __( '%d phases below 5 exercises.', 'gear-engine' ), $underfilled ) : __( 'All phases filled.', 'gear-engine' ) ),
		);
		$next = array_key_first( array_filter( $steps, static function ( array $s ): bool { return ! $s[0]; } ) );
		$next = null === $next ? '' : $next;

		$this->render_step_library( $nonce, 'library' === $next );
		$this->render_step_protocols( $nonce, 'protocols' === $next );
		$this->render_step_fill( $nonce, 'fill' === $next );
		$this->render_step_coverage();

		$base = admin_url( 'edit.php?post_type=' . Gear_Engine_Exercises_CPT::GEAR_ENGINE_POST_TYPE );
		echo '<p class="description" style="margin-top:16px">' . esc_html__( 'Power tools:', 'gear-engine' ) . ' <a href="' . esc_url( add_query_arg( array( 'page' => Gear_Engine_Protocol_Manager::GEAR_ENGINE_PAGE_SLUG, 'tab' => 'matrix' ), $base ) ) . '">' . esc_html__( 'full exercise matrix', 'gear-engine' ) . '</a> · <a href="' . esc_url( add_query_arg( 'page', Gear_Engine_Coverage_Report::GEAR_ENGINE_PAGE_SLUG, $base ) ) . '">' . esc_html__( 'coverage report', 'gear-engine' ) . '</a></p>';
		echo '</div>';
	}

	/**
	 * Open/closed wrapper so only the next actionable step is expanded.
	 *
	 * @param string $title   Step heading.
	 * @param string $summary Collapsed-state line.
	 * @param bool   $open    Whether this is the active step.
	 * @return void
	 */
	private function step_open( string $title, string $summary, bool $open ): void {
		echo '<details' . ( $open ? ' open' : '' ) . ' style="margin:12px 0;border:1px solid #dcdcde;padding:8px 12px;background:#fff"><summary style="cursor:pointer;font-size:1.1em;font-weight:600">' . esc_html( $title ) . ( $open ? '' : ' — <em style="font-weight:normal;color:#1e7e34">' . esc_html( $summary ) . '</em>' ) . '</summary>';
	}

	/**
	 * Close a step wrapper.
	 *
	 * @return void
	 */
	private function step_close(): void {
		echo '</details>';
	}

	/**
	 * Step 1: library status and suggestion review.
	 *
	 * @param string $nonce Nonce.
	 * @param bool   $open  Whether this step is the active one.
	 * @return void
	 */
	private function render_step_library( string $nonce, bool $open ): void {
		$total     = wp_count_posts( Gear_Engine_Exercises_CPT::GEAR_ENGINE_POST_TYPE );
		$published = (int) ( $total->publish ?? 0 );
		$rated_ids = get_posts(
			array(
				'post_type'      => Gear_Engine_Exercises_CPT::GEAR_ENGINE_POST_TYPE,
				'post_status'    => 'publish',
				'posts_per_page' => 200,
				'fields'         => 'ids',
				'meta_query'     => array(
					array( 'key' => Gear_Engine_Exercises_CPT::GEAR_ENGINE_META_RISK, 'compare' => 'EXISTS' ),
					array( 'key' => Gear_Engine_Exercises_CPT::GEAR_ENGINE_META_RISK, 'value' => '', 'compare' => '!=' ),
				),
			)
		);
		$rated     = count( (array) $rated_ids );
		$pending   = get_posts(
			array(
				'post_type'      => Gear_Engine_Exercises_CPT::GEAR_ENGINE_POST_TYPE,
				'post_status'    => 'publish',
				'posts_per_page' => 60,
				'meta_query'     => array(
					array( 'key' => self::META_SUGGEST, 'compare' => 'EXISTS' ),
				),
			)
		);

		$this->step_open(
			__( '1. Exercise library', 'gear-engine' ),
			$published > 0 && 0 === count( $pending ) ? sprintf( /* translators: %d: count */ __( '%d exercises ready', 'gear-engine' ), $published ) : __( 'not finished', 'gear-engine' ),
			$open
		);
		printf(
			'<p>%s <strong>%d</strong> %s <strong>%d</strong>. %s</p>',
			esc_html__( 'Published exercises:', 'gear-engine' ),
			$published,
			esc_html__( 'with a clinician-set risk level:', 'gear-engine' ),
			$rated,
			esc_html__( 'Risk is always your decision — the assistant never sets it.', 'gear-engine' )
		);

		echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '" style="margin-bottom:12px">';
		wp_nonce_field( self::NONCE_ACTION, 'gear_engine_rehab_nonce' );
		echo '<input type="hidden" name="action" value="gear_engine_rehab_suggest" /><button class="button">' . esc_html__( 'Suggest tags for the library', 'gear-engine' ) . '</button>';
		echo ' <span class="description">' . esc_html__( 'Drafts Good-for conditions and session roles from your quiz vocabulary. Nothing changes until you confirm below.', 'gear-engine' ) . '</span></form>';

		if ( ! $pending ) {
			$this->step_close();
			return;
		}
		echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '" style="margin-bottom:8px">';
		wp_nonce_field( self::NONCE_ACTION, 'gear_engine_rehab_nonce' );
		echo '<input type="hidden" name="action" value="gear_engine_rehab_confirm_all" /><button class="button button-primary">' . esc_html__( 'Confirm ALL suggestions below', 'gear-engine' ) . '</button>';
		echo ' <span class="description">' . esc_html__( 'Uncheck anything wrong first — or fix it afterwards in the matrix.', 'gear-engine' ) . '</span></form>';
		echo '<table class="widefat striped"><thead><tr><th>' . esc_html__( 'Exercise', 'gear-engine' ) . '</th><th>' . esc_html__( 'Suggested conditions (tick to keep)', 'gear-engine' ) . '</th><th>' . esc_html__( 'Suggested role', 'gear-engine' ) . '</th><th></th></tr></thead><tbody>';
		foreach ( $pending as $post ) {
			$s = get_post_meta( (int) $post->ID, self::META_SUGGEST, true );
			if ( ! is_array( $s ) ) {
				continue;
			}
			$role = Gear_Engine_Exercises_CPT::sanitize_role( (string) ( $s['role'] ?? '' ) );
			echo '<tr><td>' . esc_html( $post->post_title ) . '</td><td>';
			echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '">';
			wp_nonce_field( self::NONCE_ACTION, 'gear_engine_rehab_nonce' );
			echo '<input type="hidden" name="action" value="gear_engine_rehab_confirm" /><input type="hidden" name="post_id" value="' . esc_attr( (string) $post->ID ) . '" />';
			foreach ( (array) ( $s['good_for'] ?? array() ) as $tag ) {
				echo '<label style="margin-right:10px"><input type="checkbox" name="confirm_tags[]" value="' . esc_attr( $tag ) . '" checked /> ' . esc_html( $tag ) . '</label>';
			}
			echo '</td><td><select name="confirm_role"><option value="">—</option>';
			foreach ( Gear_Engine_Exercises_CPT::get_roles() as $slug => $label ) {
				echo '<option value="' . esc_attr( $slug ) . '"' . selected( $role, $slug, false ) . '>' . esc_html( $label ) . '</option>';
			}
			echo '</select></td><td><button class="button button-small">' . esc_html__( 'Confirm', 'gear-engine' ) . '</button></td></form></tr>';
		}
		echo '</tbody></table>';
		$this->step_close();
	}

	/**
	 * Step 2: protocol scaffolding and clinical text.
	 *
	 * @param string $nonce Nonce.
	 * @param bool   $open  Whether this step is the active one.
	 * @return void
	 */
	private function render_step_protocols( string $nonce, bool $open ): void {
		$conditions = array();
		foreach ( Gear_Engine_Protocol_Manager::get_protocol_tree() as $row ) {
			if ( 0 === (int) $row['term']->parent ) {
				$conditions[] = $row['term'];
			}
		}

		$this->step_open(
			__( '2. Protocols', 'gear-engine' ),
			$conditions ? sprintf( /* translators: %d: count */ __( '%d created', 'gear-engine' ), count( $conditions ) ) : __( 'none yet', 'gear-engine' ),
			$open
		);
		echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '" style="margin-bottom:12px">';
		wp_nonce_field( self::NONCE_ACTION, 'gear_engine_rehab_nonce' );
		echo '<input type="hidden" name="action" value="gear_engine_rehab_seed" /><button class="button">' . esc_html__( 'Create protocols from the quiz (with codes)', 'gear-engine' ) . '</button>';
		echo ' <span class="description">' . esc_html__( 'One draft per diagnosis your quiz can produce, codes pre-filled from your own configs, with Fase 1-3. Safe to re-run.', 'gear-engine' ) . '</span></form>';

		if ( ! $conditions ) {
			echo '<p class="description">' . esc_html__( 'No protocols yet — create them above.', 'gear-engine' ) . '</p>';
			$this->step_close();
			return;
		}

		echo '<p class="description">' . esc_html__( 'Seeded protocols carry draft starter text marked "Utkast". Edit the lines you own and save — approval unlocks only after you have.', 'gear-engine' ) . '</p>';
		echo '<table class="widefat striped"><thead><tr><th>' . esc_html__( 'Protocol', 'gear-engine' ) . '</th><th>' . esc_html__( 'Codes', 'gear-engine' ) . '</th><th>' . esc_html__( 'Summary', 'gear-engine' ) . '</th><th>' . esc_html__( 'Red flags', 'gear-engine' ) . '</th><th>' . esc_html__( 'Exercises', 'gear-engine' ) . '</th><th>' . esc_html__( 'Status', 'gear-engine' ) . '</th><th></th></tr></thead><tbody>';
		foreach ( $conditions as $term ) {
			$term_id = (int) $term->term_id;
			$status  = Gear_Engine_Protocols::get_status( $term_id );
			$missing = Gear_Engine_Protocols::GEAR_ENGINE_STATUS_APPROVED === $status ? array() : self::approval_blockers( $term_id );
			echo '<tr><td><strong>' . esc_html( $term->name ) . '</strong></td>';
			echo '<td><form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '" style="display:flex;gap:6px;align-items:center;flex-wrap:wrap">';
			wp_nonce_field( self::NONCE_ACTION, 'gear_engine_rehab_nonce' );
			echo '<input type="hidden" name="action" value="gear_engine_rehab_save_fields" /><input type="hidden" name="term_id" value="' . esc_attr( (string) $term_id ) . '" />';
			echo '<input type="text" size="8" name="field_codes" value="' . esc_attr( (string) get_term_meta( $term_id, Gear_Engine_Protocols::GEAR_ENGINE_META_CODES, true ) ) . '" />';
			echo '<input type="text" size="28" name="field_summary" placeholder="' . esc_attr__( 'Short clinical summary', 'gear-engine' ) . '" value="' . esc_attr( (string) get_term_meta( $term_id, Gear_Engine_Protocols::GEAR_ENGINE_META_SUMMARY, true ) ) . '" />';
			echo '<input type="text" size="28" name="field_red_flags" placeholder="' . esc_attr__( 'Red flags / contraindications', 'gear-engine' ) . '" value="' . esc_attr( (string) get_term_meta( $term_id, Gear_Engine_Protocols::GEAR_ENGINE_META_RED_FLAGS, true ) ) . '" />';
			echo '<button class="button button-small">' . esc_html__( 'Save', 'gear-engine' ) . '</button></form></td>';
			echo '<td>' . (int) $term->count . '</td>';
			echo '<td>' . esc_html( Gear_Engine_Protocols::GEAR_ENGINE_STATUS_APPROVED === $status ? __( 'Approved', 'gear-engine' ) : ( 'expired' === $status ? __( 'Review overdue', 'gear-engine' ) : __( 'Draft', 'gear-engine' ) ) ) . '</td>';
			echo '<td colspan="2">';
			if ( Gear_Engine_Protocols::GEAR_ENGINE_STATUS_APPROVED !== $status ) {
				if ( $missing ) {
					echo '<span style="color:#996800">' . esc_html( implode( ', ', $missing ) ) . '</span>';
				} else {
					echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '">';
					wp_nonce_field( self::NONCE_ACTION, 'gear_engine_rehab_nonce' );
					echo '<input type="hidden" name="action" value="gear_engine_rehab_approve" /><input type="hidden" name="term_id" value="' . esc_attr( (string) $term_id ) . '" /><button class="button button-primary button-small">' . esc_html__( 'Approve', 'gear-engine' ) . '</button></form>';
				}
			}
			echo '</td></tr>';
		}
		echo '</tbody></table>';
		$this->step_close();
	}

	/**
	 * Step 3: auto-fill plus per-phase slot review.
	 *
	 * @param string $nonce Nonce.
	 * @param bool   $open  Whether this step is the active one.
	 * @return void
	 */
	private function render_step_fill( string $nonce, bool $open ): void {
		$this->step_open( __( '3. Fill and review the exercises', 'gear-engine' ), __( 'reviewed', 'gear-engine' ), $open );
		echo '<p><button type="button" id="gear-engine-rehab-fill-all" class="button button-primary" data-nonce="' . esc_attr( wp_create_nonce( Gear_Engine_Protocol_Autofill::NONCE_ACTION ) ) . '" data-admin-url="' . esc_attr( admin_url( 'admin-post.php' ) ) . '">' . esc_html__( 'Auto-fill all protocols', 'gear-engine' ) . '</button> <span id="gear-engine-rehab-fill-status" aria-live="polite"></span></p>';

		$tree = Gear_Engine_Protocol_Manager::get_protocol_tree();
		$by_parent = array();
		foreach ( $tree as $row ) {
			$by_parent[ (int) $row['term']->parent ][] = $row['term'];
		}
		foreach ( $by_parent[0] ?? array() as $condition ) {
			echo '<div class="postbox" style="padding:8px 12px;margin-bottom:10px"><h3 style="margin:4px 0">' . esc_html( $condition->name ) . ' <button type="button" class="button button-small gear-engine-rehab-fill-one" data-term-id="' . esc_attr( (string) $condition->term_id ) . '" data-nonce="' . esc_attr( wp_create_nonce( Gear_Engine_Protocol_Autofill::NONCE_ACTION ) ) . '" data-admin-url="' . esc_attr( admin_url( 'admin-post.php' ) ) . '">' . esc_html__( 'Fill this', 'gear-engine' ) . '</button></h3>';
			foreach ( $by_parent[ (int) $condition->term_id ] ?? array() as $phase ) {
				$slots       = self::get_phase_exercises( (int) $phase->term_id );
				$candidates  = self::get_slot_candidates( $condition, $phase );
				echo '<p style="margin:6px 0 2px"><strong>' . esc_html( $phase->name ) . '</strong> (' . count( $slots ) . '/5)</p>';
				echo '<ol style="margin:0 0 4px 20px">';
				foreach ( $slots as $slot ) {
					echo '<li>' . esc_html( (string) $slot['title'] ) . ' <em style="color:#64748b">(' . esc_html( (string) ( $slot['role'] ? $slot['role'] : __( 'no role', 'gear-engine' ) ) ) . ' · ' . esc_html( (string) $slot['risk'] ) . ')</em> ';
					echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '" style="display:inline">';
					wp_nonce_field( self::NONCE_ACTION, 'gear_engine_rehab_nonce' );
					echo '<input type="hidden" name="action" value="gear_engine_rehab_slot_remove" /><input type="hidden" name="term_id" value="' . esc_attr( (string) $phase->term_id ) . '" /><input type="hidden" name="post_id" value="' . esc_attr( (string) $slot['id'] ) . '" />';
					echo '<button class="button-link" style="color:#b32d2e">' . esc_html__( 'remove', 'gear-engine' ) . '</button></form></li>';
				}
				echo '</ol>';
				if ( count( $slots ) < Gear_Engine_Protocol_Autofill::TARGET_PER_PHASE && $candidates ) {
					echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '" style="display:inline-block">';
					wp_nonce_field( self::NONCE_ACTION, 'gear_engine_rehab_nonce' );
					echo '<input type="hidden" name="action" value="gear_engine_rehab_slot_add" /><input type="hidden" name="term_id" value="' . esc_attr( (string) $phase->term_id ) . '" />';
					echo '<select name="post_id">';
					foreach ( $candidates as $c ) {
						echo '<option value="' . esc_attr( (string) $c['id'] ) . '">' . esc_html( (string) $c['title'] ) . ' (' . esc_html( (string) $c['risk'] ) . ')</option>';
					}
					echo '</select> <button class="button button-small">' . esc_html__( 'Add to phase', 'gear-engine' ) . '</button></form>';
				}
			}
			echo '</div>';
		}
		$this->step_close();
	}

	/**
	 * Step 4: coverage strip.
	 *
	 * @return void
	 */
	private function render_step_coverage(): void {
		echo '<h2>' . esc_html__( '4. Check coverage', 'gear-engine' ) . '</h2>';
		if ( ! class_exists( 'Gear_Engine_Coverage_Report' ) ) {
			return;
		}
		$report = Gear_Engine_Coverage_Report::build_report();
		$counts = array();
		foreach ( $report['regions'] as $region ) {
			foreach ( $region['rows'] as $row ) {
				$counts[ $row['state'] ] = ( $counts[ $row['state'] ] ?? 0 ) + 1;
			}
		}
		$curated = $counts['curated'] ?? 0;
		$auto    = $counts['auto'] ?? 0;
		$missing = ( $counts['missing'] ?? 0 ) + ( $counts['broken'] ?? 0 );
		$total   = $curated + $auto + $missing + ( $counts['by-design'] ?? 0 );
		printf(
			'<p><strong>%1$s</strong> %2$s <strong>%3$d/%4$d</strong> %5$s <strong>%6$d</strong> %7$s <strong>%8$d</strong>. <a href="%9$s">%10$s</a> · <a href="%11$s" target="_blank">%12$s</a></p>',
			esc_html__( 'Quiz results reach a program:', 'gear-engine' ),
			esc_html__( 'curated', 'gear-engine' ),
			$curated,
			$total,
			esc_html__( ', auto-drafted', 'gear-engine' ),
			$auto,
			esc_html__( ', still missing or broken', 'gear-engine' ),
			$missing,
			esc_url( admin_url( 'edit.php?post_type=' . Gear_Engine_Exercises_CPT::GEAR_ENGINE_POST_TYPE . '&page=' . Gear_Engine_Coverage_Report::GEAR_ENGINE_PAGE_SLUG ) ),
			esc_html__( 'Open full coverage report', 'gear-engine' ),
			esc_url( home_url( '/' ) ),
			esc_html__( 'Test a quiz', 'gear-engine' )
		);
	}
}
