<?php
/**
 * Starter rehab protocols and sample exercises for testing.
 *
 * Populates realistic, approved rehabilitation protocols and exercises across all
 * six triage body areas (kne, rygg, skulder, fot-ankel, hofte, nakke) with ICD-10 codes,
 * three clinical phases, dosage, and equipment links.
 *
 * All seeded records are tagged with sample meta keys so they can be completely wiped
 * in one click when moving to production.
 *
 * @package GearEngine
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

/**
 * Class Gear_Engine_Starter_Rehab
 */
class Gear_Engine_Starter_Rehab {

	public const OPTION_SEEDED            = 'gear_engine_starter_rehab_seeded';
	public const META_IS_SAMPLE           = '_gear_engine_is_sample';
	public const META_EXERCISE_SAMPLE_KEY = '_gear_engine_exercise_sample_key';
	public const META_PROTOCOL_SAMPLE_KEY = '_gear_engine_protocol_sample_key';

	/**
	 * Constructor.
	 */
	public function __construct() {
		add_action( 'init', array( $this, 'maybe_seed_starter_content' ), 25 );
		add_action( 'admin_post_gear_engine_remove_sample_data', array( $this, 'handle_remove_sample_data' ) );
		add_action( 'admin_post_gear_engine_reseed_sample_data', array( $this, 'handle_reseed_sample_data' ) );
	}

	/**
	 * Automatically seed starter content on init if not already present.
	 *
	 * @return void
	 */
	public function maybe_seed_starter_content(): void {
		if ( ! get_option( self::OPTION_SEEDED, false ) ) {
			self::seed_starter_content();
		}
	}

	/**
	 * Seed all starter exercises and approved protocols.
	 *
	 * @return array{exercises:int,protocols:int}
	 */
	public static function seed_starter_content(): array {
		if ( ! function_exists( 'wp_insert_post' ) || ! function_exists( 'wp_insert_term' ) ) {
			return array( 'exercises' => 0, 'protocols' => 0 );
		}

		$exercise_ids = self::seed_exercises();
		$protocol_ids = self::seed_protocols();
		self::attach_exercises_to_phases( $exercise_ids, $protocol_ids );

		update_option( self::OPTION_SEEDED, 1, false );
		update_option( Gear_Engine_Exercises_CPT::GEAR_ENGINE_SAMPLE_OPTION, 1, false );
		update_option( 'gear_engine_enable_protocols', 1 );

		return array(
			'exercises' => count( $exercise_ids ),
			'protocols' => count( $protocol_ids ),
		);
	}

	/**
	 * Seed starter exercises across all 6 body areas.
	 *
	 * @return array<string,int> Map of exercise key to post ID.
	 */
	public static function seed_exercises(): array {
		$definitions = self::get_exercise_definitions();
		$ids         = array();

		foreach ( $definitions as $ex ) {
			$existing = get_posts(
				array(
					'post_type'      => Gear_Engine_Exercises_CPT::GEAR_ENGINE_POST_TYPE,
					'post_status'    => 'any',
					'posts_per_page' => 1,
					'fields'         => 'ids',
					'meta_key'       => self::META_EXERCISE_SAMPLE_KEY,
					'meta_value'     => $ex['key'],
				)
			);

			if ( ! empty( $existing ) ) {
				$ids[ $ex['key'] ] = (int) $existing[0];
				continue;
			}

			$post_id = wp_insert_post(
				array(
					'post_type'    => Gear_Engine_Exercises_CPT::GEAR_ENGINE_POST_TYPE,
					'post_status'  => 'publish',
					'post_title'   => $ex['title'],
					'post_excerpt' => $ex['excerpt'],
					'post_content' => $ex['content'],
				),
				true
			);

			if ( is_wp_error( $post_id ) || ! $post_id ) {
				continue;
			}

			$post_id = (int) $post_id;
			update_post_meta( $post_id, self::META_IS_SAMPLE, 1 );
			update_post_meta( $post_id, self::META_EXERCISE_SAMPLE_KEY, $ex['key'] );
			update_post_meta( $post_id, Gear_Engine_Exercises_CPT::GEAR_ENGINE_META_DOSAGE, $ex['dosage'] );
			update_post_meta( $post_id, Gear_Engine_Exercises_CPT::GEAR_ENGINE_META_RISK, $ex['risk'] );
			update_post_meta( $post_id, Gear_Engine_Exercises_CPT::GEAR_ENGINE_META_GOOD_FOR, $ex['good_for'] );
			update_post_meta( $post_id, Gear_Engine_Exercises_CPT::GEAR_ENGINE_META_VIDEO_CULTURE, 'nb-NO' );

			if ( ! empty( $ex['exorlive'] ) ) {
				update_post_meta( $post_id, Gear_Engine_Exercises_CPT::GEAR_ENGINE_META_EXORLIVE_ID, $ex['exorlive'] );
			}
			if ( ! empty( $ex['video_url'] ) ) {
				update_post_meta( $post_id, Gear_Engine_Exercises_CPT::GEAR_ENGINE_META_VIDEO_URL, $ex['video_url'] );
			}

			// Taxonomies.
			wp_set_object_terms( $post_id, $ex['area'], Gear_Engine_Exercises_CPT::GEAR_ENGINE_AREA_TAXONOMY );
			wp_set_object_terms( $post_id, $ex['equipment'], Gear_Engine_Exercises_CPT::GEAR_ENGINE_EQUIPMENT_TAXONOMY );
			wp_set_object_terms( $post_id, $ex['role'], Gear_Engine_Exercises_CPT::GEAR_ENGINE_ROLE_TAXONOMY );

			$ids[ $ex['key'] ] = $post_id;
		}

		return $ids;
	}

	/**
	 * Seed approved protocols and their 3 phases for each triage region.
	 *
	 * @return array<string,array{term_id:int,phases:array<int,int>}> Map of protocol slug to term ID and phase IDs.
	 */
	public static function seed_protocols(): array {
		$definitions = self::get_protocol_definitions();
		$seeded      = array();

		foreach ( $definitions as $proto ) {
			$slug = sanitize_title( $proto['slug'] );
			$term = get_term_by( 'slug', $slug, Gear_Engine_Protocols::GEAR_ENGINE_TAXONOMY );

			if ( ! $term || is_wp_error( $term ) ) {
				$inserted = wp_insert_term(
					$proto['title'],
					Gear_Engine_Protocols::GEAR_ENGINE_TAXONOMY,
					array( 'slug' => $slug )
				);

				if ( is_wp_error( $inserted ) || empty( $inserted['term_id'] ) ) {
					continue;
				}
				$term_id = (int) $inserted['term_id'];
			} else {
				$term_id = (int) $term->term_id;
			}

			// Tag as sample data.
			update_term_meta( $term_id, self::META_IS_SAMPLE, 1 );
			update_term_meta( $term_id, self::META_PROTOCOL_SAMPLE_KEY, $proto['slug'] );

			// Clinical metadata required for approval and display.
			update_term_meta( $term_id, Gear_Engine_Protocols::GEAR_ENGINE_META_CODES, $proto['codes'] );
			update_term_meta( $term_id, Gear_Engine_Protocols::GEAR_ENGINE_META_SUMMARY, $proto['summary'] );
			update_term_meta( $term_id, Gear_Engine_Protocols::GEAR_ENGINE_META_DOSAGE, $proto['dosage'] );
			update_term_meta( $term_id, Gear_Engine_Protocols::GEAR_ENGINE_META_RED_FLAGS, $proto['red_flags'] );
			update_term_meta( $term_id, Gear_Engine_Protocols::GEAR_ENGINE_META_STATUS, Gear_Engine_Protocols::GEAR_ENGINE_STATUS_APPROVED );
			update_term_meta( $term_id, Gear_Engine_Protocols::GEAR_ENGINE_META_REVIEWER, __( 'Klinisk Fagansvarlig (Testsett)', 'gear-engine' ) );
			update_term_meta( $term_id, Gear_Engine_Protocols::GEAR_ENGINE_META_REVIEWED, gmdate( 'Y-m-d' ) );
			update_term_meta( $term_id, Gear_Engine_Protocols::GEAR_ENGINE_META_REVIEW_DUE, gmdate( 'Y-m-d', strtotime( '+1 year' ) ) );

			// Ensure child phases (Fase 1, 2, 3) exist under this protocol.
			$phase_ids = array();
			for ( $n = 1; $n <= 3; ++$n ) {
				$phase_slug = $slug . '-fase-' . $n;
				$phase_term = get_term_by( 'slug', $phase_slug, Gear_Engine_Protocols::GEAR_ENGINE_TAXONOMY );

				if ( ! $phase_term || is_wp_error( $phase_term ) ) {
					$phase_inserted = wp_insert_term(
						'Fase ' . $n,
						Gear_Engine_Protocols::GEAR_ENGINE_TAXONOMY,
						array(
							'slug'   => $phase_slug,
							'parent' => $term_id,
						)
					);

					if ( ! is_wp_error( $phase_inserted ) && ! empty( $phase_inserted['term_id'] ) ) {
						$phase_id = (int) $phase_inserted['term_id'];
					} else {
						$phase_id = 0;
					}
				} else {
					$phase_id = (int) $phase_term->term_id;
				}

				if ( $phase_id ) {
					update_term_meta( $phase_id, self::META_IS_SAMPLE, 1 );
					update_term_meta( $phase_id, self::META_PROTOCOL_SAMPLE_KEY, $phase_slug );
					$phase_ids[ $n ] = $phase_id;
				}
			}

			$seeded[ $proto['slug'] ] = array(
				'term_id' => $term_id,
				'phases'  => $phase_ids,
			);
		}

		return $seeded;
	}

	/**
	 * Attach seeded exercises to the corresponding protocol phases.
	 *
	 * @param array<string,int>                                            $exercise_ids Map of exercise key to post ID.
	 * @param array<string,array{term_id:int,phases:array<int,int>}> $protocol_ids Map of protocol slug to term ID and phase IDs.
	 * @return void
	 */
	public static function attach_exercises_to_phases( array $exercise_ids, array $protocol_ids ): array {
		$assignments = self::get_protocol_exercise_map();

		foreach ( $assignments as $proto_slug => $phases ) {
			if ( empty( $protocol_ids[ $proto_slug ] ) ) {
				continue;
			}

			$proto_data = $protocol_ids[ $proto_slug ];
			$top_id     = $proto_data['term_id'];

			foreach ( $phases as $phase_num => $ex_keys ) {
				$phase_id = $proto_data['phases'][ $phase_num ] ?? 0;
				$term_ids = array_filter( array( $top_id, $phase_id ) );

				foreach ( $ex_keys as $key ) {
					if ( ! empty( $exercise_ids[ $key ] ) ) {
						wp_set_object_terms( $exercise_ids[ $key ], $term_ids, Gear_Engine_Protocols::GEAR_ENGINE_TAXONOMY, true );
					}
				}
			}
		}

		return $assignments;
	}

	/**
	 * Remove all seeded sample data completely (posts, terms, options).
	 *
	 * @return array{deleted_exercises:int,deleted_protocols:int}
	 */
	public static function remove_all_sample_data(): array {
		if ( ! function_exists( 'wp_delete_post' ) || ! function_exists( 'wp_delete_term' ) ) {
			return array( 'deleted_exercises' => 0, 'deleted_protocols' => 0 );
		}

		$deleted_exercises = 0;
		$deleted_protocols = 0;

		// 1. Delete all sample exercises.
		$posts = get_posts(
			array(
				'post_type'      => Gear_Engine_Exercises_CPT::GEAR_ENGINE_POST_TYPE,
				'post_status'    => 'any',
				'posts_per_page' => -1,
				'fields'         => 'ids',
				'meta_query'     => array(
					'relation' => 'OR',
					array(
						'key'     => self::META_IS_SAMPLE,
						'value'   => 1,
						'compare' => '=',
					),
					array(
						'key'     => self::META_EXERCISE_SAMPLE_KEY,
						'compare' => 'EXISTS',
					),
					array(
						'key'     => Gear_Engine_Exercises_CPT::GEAR_ENGINE_META_SAMPLE_KEY,
						'compare' => 'EXISTS',
					),
				),
			)
		);

		foreach ( $posts as $post_id ) {
			wp_delete_post( (int) $post_id, true );
			++$deleted_exercises;
		}

		// 2. Delete all sample protocol terms (children first, then parents).
		$terms = get_terms(
			array(
				'taxonomy'   => Gear_Engine_Protocols::GEAR_ENGINE_TAXONOMY,
				'hide_empty' => false,
				'meta_query' => array(
					'relation' => 'OR',
					array(
						'key'     => self::META_IS_SAMPLE,
						'value'   => 1,
						'compare' => '=',
					),
					array(
						'key'     => self::META_PROTOCOL_SAMPLE_KEY,
						'compare' => 'EXISTS',
					),
				),
			)
		);

		if ( is_array( $terms ) ) {
			// Sort so children (parent > 0) are deleted before top-level parents.
			usort(
				$terms,
				static function ( $a, $b ): int {
					$pa = is_object( $a ) && ! empty( $a->parent ) ? 1 : 0;
					$pb = is_object( $b ) && ! empty( $b->parent ) ? 1 : 0;
					return $pb <=> $pa;
				}
			);

			foreach ( $terms as $term ) {
				if ( is_object( $term ) && ! empty( $term->term_id ) ) {
					wp_delete_term( (int) $term->term_id, Gear_Engine_Protocols::GEAR_ENGINE_TAXONOMY );
					++$deleted_protocols;
				}
			}
		}

		// 3. Reset options.
		delete_option( self::OPTION_SEEDED );
		delete_option( Gear_Engine_Exercises_CPT::GEAR_ENGINE_SAMPLE_OPTION );

		return array(
			'deleted_exercises' => $deleted_exercises,
			'deleted_protocols' => $deleted_protocols,
		);
	}

	/**
	 * Handle admin request to wipe all sample data.
	 *
	 * @return void
	 */
	public function handle_remove_sample_data(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have permission to access this page.', 'gear-engine' ), '', array( 'response' => 403 ) );
		}

		check_admin_referer( 'gear_engine_remove_sample_data', 'gear_engine_nonce' );

		$result = self::remove_all_sample_data();

		$redirect_url = add_query_arg(
			array(
				'page'                         => 'gear-engine-shortcodes',
				'gear_engine_samples_removed' => '1',
				'deleted_ex'                   => $result['deleted_exercises'],
				'deleted_proto'                => $result['deleted_protocols'],
			),
			admin_url( 'options-general.php' )
		);

		if ( function_exists( 'wp_safe_redirect' ) ) {
			wp_safe_redirect( $redirect_url );
			if ( ! defined( 'GEAR_ENGINE_TESTING' ) ) {
				exit;
			}
		}
	}

	/**
	 * Handle admin request to reseed sample data.
	 *
	 * @return void
	 */
	public function handle_reseed_sample_data(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have permission to access this page.', 'gear-engine' ), '', array( 'response' => 403 ) );
		}

		check_admin_referer( 'gear_engine_reseed_sample_data', 'gear_engine_nonce' );

		$result = self::seed_starter_content();

		$redirect_url = add_query_arg(
			array(
				'page'                        => 'gear-engine-shortcodes',
				'gear_engine_samples_seeded' => '1',
				'seeded_ex'                   => $result['exercises'],
				'seeded_proto'                => $result['protocols'],
			),
			admin_url( 'options-general.php' )
		);

		if ( function_exists( 'wp_safe_redirect' ) ) {
			wp_safe_redirect( $redirect_url );
			if ( ! defined( 'GEAR_ENGINE_TESTING' ) ) {
				exit;
			}
		}
	}

	/**
	 * Master dictionary of starter exercises.
	 *
	 * @return array<int,array<string,string>>
	 */
	public static function get_exercise_definitions(): array {
		return array(
			// --- KNE ---
			array(
				'key'       => 'kne-sideliggende-benloft',
				'title'     => 'Sideliggende benløft',
				'excerpt'   => 'Aktivering av gluteus medius for bedre stabilitet og avlastning av kneet.',
				'content'   => 'Ligg på siden med strake ben eller lett bøy i nederste ben for støtte. Løft det øverste benet rolig opp mot taket med tærne pekende rett frem eller lett nedover. Hold i 1 sekund på toppen og senk kontrollert tilbake.',
				'dosage'    => '10–12 repetisjoner × 3 sett',
				'area'      => 'kne',
				'equipment' => 'matte',
				'role'      => 'activation',
				'risk'      => 'low',
				'good_for'  => 'løperkne, itbs, patellofemoralt, pfps, hofteabduksjon',
				'exorlive'  => '7282',
				'video_url' => '',
			),
			array(
				'key'       => 'kne-clamshell-strikk',
				'title'     => 'Muslingen (Clamshell) med miniband',
				'excerpt'   => 'Målrettet styrke for utadrotatorene i hoften for å hindre at kneet faller innover.',
				'content'   => 'Ligg på siden med 90 graders bøy i knærne og et elastisk miniband like over knærne. Hold føttene samlet mens du åpner det øverste kneet kontrollert som et skjell. Hold bekkenet i ro uten å tippe bakover.',
				'dosage'    => '12 repetisjoner × 3 sett',
				'area'      => 'kne',
				'equipment' => 'strikk',
				'role'      => 'strength',
				'risk'      => 'low',
				'good_for'  => 'løperkne, itbs, patellofemoralt, pfps, valgus',
				'exorlive'  => '',
				'video_url' => '',
			),
			array(
				'key'       => 'kne-stol-kneboy',
				'title'     => 'Knebøy til stol med kontroll',
				'excerpt'   => 'Funksjonell styrke for forside lår og sete med definert dybdekontroll.',
				'content'   => 'Plasser en stabil stol bak deg og stå med føttene i skulderbredde. Før hoftene bakover og bøy knærne kontrollert til setet så vidt berører stolen. Press jevnt gjennom hele foten for å reise deg opp igjen.',
				'dosage'    => '10 repetisjoner × 3 sett',
				'area'      => 'kne',
				'equipment' => 'stol',
				'role'      => 'strength',
				'risk'      => 'low',
				'good_for'  => 'patellofemoralt, pfps, kneartrose, quadriceps, lumbago',
				'exorlive'  => '',
				'video_url' => '',
			),
			array(
				'key'       => 'kne-terminal-extension',
				'title'     => 'Terminal Knee Extension (TKE) med strikk',
				'excerpt'   => 'Skånsom isolert aktivering av vastus medialis uten leddkompresjon.',
				'content'   => 'Fest en treningsstrikk i knehøyde foran deg og plasser den i knehasen. Start med lett bøyd kne og press kneet bakover mot strikkens motstand til benet er helt utstrakt. Stram forsiden av låret på toppen og hold i 2 sekunder.',
				'dosage'    => '15 repetisjoner × 3 sett',
				'area'      => 'kne',
				'equipment' => 'strikk',
				'role'      => 'activation',
				'risk'      => 'low',
				'good_for'  => 'patellofemoralt, pfps, fremre knesmerter, vastus medialis',
				'exorlive'  => '',
				'video_url' => '',
			),

			// --- RYGG ---
			array(
				'key'       => 'rygg-kne-til-bryst',
				'title'     => 'Liggende bekkenvipp og kne-til-bryst',
				'excerpt'   => 'Avlastende bevegelse som reduserer muskelspenninger i nedre korsrygg.',
				'content'   => 'Ligg på ryggen på en matte med bøyde knær. Trekk rolig det ene kneet opp mot brystet med hendene, hold i 5–10 sekunder mens du puster dypt. Gjenta vekselvis på begge sider. Gjør deretter milde bekkenvipp mot underlaget.',
				'dosage'    => '8–10 repetisjoner per side',
				'area'      => 'rygg',
				'equipment' => 'matte',
				'role'      => 'mobility',
				'risk'      => 'low',
				'good_for'  => 'lumbago, kink i ryggen, korsryggsmerter, stivhet',
				'exorlive'  => '',
				'video_url' => '',
			),
			array(
				'key'       => 'rygg-katte-kamel',
				'title'     => 'Katte-kamel (ryggmobilitet på alle fire)',
				'excerpt'   => 'Skånsom fleksjon og ekstensjon som smører ryggvirvlene og demper kramper.',
				'content'   => 'Stå på alle fire med hendene under skuldrene og knærne under hoftene. Skyt rolig rygg opp mot taket mens du slipper hodet ned (katt). Før deretter ryggen rolig ned i en lett svai mens du løfter blikket (kamel). Beveg deg i en myk, smertefri bue.',
				'dosage'    => '10 rolige repetisjoner × 2 sett',
				'area'      => 'rygg',
				'equipment' => 'matte',
				'role'      => 'mobility',
				'risk'      => 'low',
				'good_for'  => 'lumbago, stiv rygg, uspesifikke korsryggsmerter',
				'exorlive'  => '',
				'video_url' => '',
			),
			array(
				'key'       => 'rygg-glute-bridge',
				'title'     => 'Bekkenhev (Glute bridge)',
				'excerpt'   => 'Aktivering og styrking av setemuskulatur og korsryggstabilitet.',
				'content'   => 'Ligg på ryggen med bøyde knær og føttene flatt i underlaget i hoftebredde. Stram setet og løft bekkenet opp til kroppen danner en rett linje fra knær til skuldre. Hold i 2 sekunder på toppen uten å overstrekke ryggen, og senk kontrollert ned.',
				'dosage'    => '10–12 repetisjoner × 3 sett',
				'area'      => 'rygg',
				'equipment' => 'matte',
				'role'      => 'strength',
				'risk'      => 'low',
				'good_for'  => 'lumbago, uspesifikke korsryggsmerter, seteaktivering',
				'exorlive'  => '',
				'video_url' => '',
			),
			array(
				'key'       => 'rygg-bird-dog',
				'title'     => 'Bird-dog (diagonalhev på alle fire)',
				'excerpt'   => 'Styrker de dype ryggstrekkerne og forbedrer kjernestabilitet.',
				'content'   => 'Stå på alle fire med nøytral rygg. Strekk høyre arm fremover og venstre ben bakover samtidig, til de er på linje med overkroppen. Hold balansen i 2–3 sekunder uten å vri på bekkenet. Før rolig tilbake og bytt side.',
				'dosage'    => '8 repetisjoner per side × 3 sett',
				'area'      => 'rygg',
				'equipment' => 'matte',
				'role'      => 'stability',
				'risk'      => 'low',
				'good_for'  => 'uspesifikke korsryggsmerter, kjerne, multifidus, stabilitet',
				'exorlive'  => '',
				'video_url' => '',
			),

			// --- SKULDER ---
			array(
				'key'       => 'skulder-pendel',
				'title'     => 'Pendeløvelse (Codmans pendel)',
				'excerpt'   => 'Avlastende traksjon som åpner leddspalten og demper akutte smerter i skulderen.',
				'content'   => 'Støtt den friske armen mot et bord eller en stol og len overkroppen lett fremover. La den vonde armen henge helt slapt ned som et lodd. Lag små, rolige sirkler og pendelbevegelser med armen ved hjelp av overkroppens bevegelse, uten å bruke skuldermusklene aktivt.',
				'dosage'    => '1–2 minutter rolige sirkler',
				'area'      => 'skulder',
				'equipment' => 'egenvekt',
				'role'      => 'recovery',
				'risk'      => 'low',
				'good_for'  => 'saps, impingement, frossen skulder, seneirritasjon',
				'exorlive'  => '',
				'video_url' => '',
			),
			array(
				'key'       => 'skulder-utadrotasjon-strikk',
				'title'     => 'Utadrotasjon med treningsstrikk',
				'excerpt'   => 'Isolert styrketrening for infraspinatus og bakre rotatorcuff.',
				'content'   => 'Stå oppreist med albuen bøyd i 90 grader og holdt tett inntil siden. Hold en treningsstrikk festet foran deg. Roter underarmen utover fra magen mot motstanden, hold i 1 sekund og før rolig tilbake.',
				'dosage'    => '10–12 repetisjoner × 3 sett',
				'area'      => 'skulder',
				'equipment' => 'strikk',
				'role'      => 'strength',
				'risk'      => 'low',
				'good_for'  => 'saps, rotatorcuff, impingement, stabilitet',
				'exorlive'  => '',
				'video_url' => '',
			),
			array(
				'key'       => 'skulder-scapula-pushups',
				'title'     => 'Scapula push-ups mot vegg',
				'excerpt'   => 'Styrker serratus anterior for optimal bevegelse av skulderbladet.',
				'content'   => 'Stå en armlengde fra veggen med hendene i skulderhøyde og strake albuer. Uten å bøye albuene, klem skulderbladene sammen slik at brystkassen synker lett mot veggen. Press deretter hendene inn i veggen slik at skulderbladene glir fra hverandre og øvre rygg rundes lett.',
				'dosage'    => '12 repetisjoner × 3 sett',
				'area'      => 'skulder',
				'equipment' => 'egenvekt',
				'role'      => 'stability',
				'risk'      => 'low',
				'good_for'  => 'saps, serratus anterior, skulderbladskontroll, impingement',
				'exorlive'  => '',
				'video_url' => '',
			),
			array(
				'key'       => 'skulder-liggende-y-t',
				'title'     => 'Liggende Y- og T-hev på matte',
				'excerpt'   => 'Funksjonell styrke for nedre og midtre trapezius for å åpne skulderleddet.',
				'content'   => 'Ligg på magen på en matte med pannen hvilende i underlaget. Løft armene skrått oppover i en Y-posisjon med tomlene pekende opp mot taket. Klem skulderbladene ned og bak. Gjør det samme med armene rett ut til siden i en T-posisjon.',
				'dosage'    => '8 repetisjoner per posisjon × 2 sett',
				'area'      => 'skulder',
				'equipment' => 'matte',
				'role'      => 'strength',
				'risk'      => 'low',
				'good_for'  => 'saps, holdning, skulderbladskontroll',
				'exorlive'  => '',
				'video_url' => '',
			),

			// --- FOT OG ANKEL ---
			array(
				'key'       => 'fot-plantar-massasje',
				'title'     => 'Plantar fascia massasje med ball',
				'excerpt'   => 'Reduserer spenninger i bindevevet under foten og øker lokal sirkulasjon.',
				'content'   => 'Sitt på en stol og plasser en liten massasjeball (eller tennisball) under fotsålen. Rull foten rolig frem og tilbake over ballen fra hælen til tåballene med et moderat, behagelig trykk. Pust rolig og bruk 2–3 minutter per fot.',
				'dosage'    => '2–3 minutter per fot, morgen og kveld',
				'area'      => 'fot-ankel',
				'equipment' => 'egenvekt',
				'role'      => 'recovery',
				'risk'      => 'low',
				'good_for'  => 'plantar fasciitt, hælsmerter, fotsåle, stivhet',
				'exorlive'  => '',
				'video_url' => '',
			),
			array(
				'key'       => 'fot-eksentrisk-taahev-trinn',
				'title'     => 'Eksentrisk tåhev på trinn med håndkle',
				'excerpt'   => 'Rathleff-protokoll: Gradvis strekk og senebelastning av plantarfascien.',
				'content'   => 'Stå med fremre del av føttene på et trappetrinn og rull et lite håndkle under tærne slik at stortåen bøyes oppover. Løft deg opp på tå på 3 sekunder, hold i 2 sekunder på toppen, og senk deg rolig ned under trinnivå på 3 sekunder.',
				'dosage'    => '10–12 repetisjoner × 3 sett annenhver dag',
				'area'      => 'fot-ankel',
				'equipment' => 'egenvekt',
				'role'      => 'strength',
				'risk'      => 'low',
				'good_for'  => 'plantar fasciitt, hælspore, akillestendinopati',
				'exorlive'  => '',
				'video_url' => '',
			),
			array(
				'key'       => 'fot-isometrisk-taahev',
				'title'     => 'Isometrisk tåhev stående på gulv',
				'excerpt'   => 'Smertelindrende senebelastning for akilles og leggmuskulatur.',
				'content'   => 'Stå på flatt gulv i nærheten av en vegg for balanse. Løft deg opp på tærne til middels høyde og hold posisjonen helt i ro i 30–45 sekunder uten å vingle. Hvil i 1 minutt og gjenta.',
				'dosage'    => '35–45 sekunder hold × 4–5 repetisjoner',
				'area'      => 'fot-ankel',
				'equipment' => 'egenvekt',
				'role'      => 'activation',
				'risk'      => 'low',
				'good_for'  => 'akillestendinopati, akillessmerter, leggstyrke',
				'exorlive'  => '',
				'video_url' => '',
			),
			array(
				'key'       => 'fot-ettbens-balanse',
				'title'     => 'Balansetrening på ett ben',
				'excerpt'   => 'Nevromuskulær kontroll og stabilitet for fot og ankelledd.',
				'content'   => 'Stå på ett ben med lett bøy i kneet og blikket festet på et punkt foran deg. Hold balansen i 30 sekunder. For økt utfordring kan du lukke øynene eller stå på en myk balansepute eller et sammenbrettet teppe.',
				'dosage'    => '30 sekunder per ben × 3 repetisjoner',
				'area'      => 'fot-ankel',
				'equipment' => 'balansepute',
				'role'      => 'stability',
				'risk'      => 'low',
				'good_for'  => 'ankelforstuing, overtråkk, plantar fasciitt, balanse',
				'exorlive'  => '',
				'video_url' => '',
			),

			// --- HOFTE ---
			array(
				'key'       => 'hofte-isometrisk-abduksjon',
				'title'     => 'Isometrisk hofteabduksjon mot vegg',
				'excerpt'   => 'Smertelindrende aktivering av gluteus medius uten kompresjon over trokanter.',
				'content'   => 'Stå med siden til en vegg med den vonde siden ytterst. Løft det innerste benet lett og press utsiden av kneet forsiktig mot veggen. Du vil kjenne at setemuskelen på standbenet jobber for å holde deg oppreist. Hold i 20–30 sekunder.',
				'dosage'    => '25 sekunder hold × 3 sett',
				'area'      => 'hofte',
				'equipment' => 'egenvekt',
				'role'      => 'activation',
				'risk'      => 'low',
				'good_for'  => 'trokanterbursitt, gtps, gluteal tendinopati, hofte',
				'exorlive'  => '',
				'video_url' => '',
			),
			array(
				'key'       => 'hofte-sideliggende-benloft',
				'title'     => 'Sideliggende benløft med nøytral hofte',
				'excerpt'   => 'Styrker setets abduktorer i åpen kjede uten uheldig innadrotasjon.',
				'content'   => 'Ligg på siden med nøytralt bekken og underste ben bøyd for god støtte. Strekk øverste ben ut og før det lett bakover før du løfter det rolig opp. Pass på at tærne ikke peker oppover mot taket.',
				'dosage'    => '10–12 repetisjoner × 3 sett',
				'area'      => 'hofte',
				'equipment' => 'matte',
				'role'      => 'strength',
				'risk'      => 'low',
				'good_for'  => 'trokanterbursitt, gtps, hofteartrose, bekkenstabilitet',
				'exorlive'  => '',
				'video_url' => '',
			),
			array(
				'key'       => 'hofte-kneboy-miniband',
				'title'     => 'Knebøy til stol med miniband rundt knærne',
				'excerpt'   => 'Kombinerer funksjonell lår- og setestyrke med aktiv utadrotasjon mot strikk.',
				'content'   => 'Plasser et elastisk miniband like over knærne og stå foran en stol. Bøy knær og hofter ned mot stolen mens du aktivt presser knærne ut mot strikken slik at de sporer rett over tærne.',
				'dosage'    => '10 repetisjoner × 3 sett',
				'area'      => 'hofte',
				'equipment' => 'strikk',
				'role'      => 'strength',
				'risk'      => 'moderate',
				'good_for'  => 'trokanterbursitt, gtps, hofteartrose, knebøy',
				'exorlive'  => '',
				'video_url' => '',
			),
			array(
				'key'       => 'hofte-ettbens-bekkenhev',
				'title'     => 'Ettbens bekkenhev (Single leg bridge)',
				'excerpt'   => 'Avansert stabilitet og styrke for sete og bakside lår.',
				'content'   => 'Ligg på ryggen med det ene benet bøyd i underlaget og det andre benet strukket ut i luften. Løft bekkenet opp ved å presse gjennom hælen på standfoten, til hoften er helt utstrakt. Hold i 1–2 sekunder og senk rolig.',
				'dosage'    => '8 repetisjoner per side × 3 sett',
				'area'      => 'hofte',
				'equipment' => 'matte',
				'role'      => 'stability',
				'risk'      => 'moderate',
				'good_for'  => 'hofte, bekken, hamstring, stabilitet',
				'exorlive'  => '',
				'video_url' => '',
			),

			// --- NAKKE ---
			array(
				'key'       => 'nakke-chin-tuck',
				'title'     => 'Hakeinntrekk (Chin tuck)',
				'excerpt'   => 'Aktivering av dype nakkebøyere (longus colli/capitis) for avlastning av øvre nakke.',
				'content'   => 'Sitt eller ligg med rett rygg. Før haken rett bakover som om du lager en dobbelthake, uten å bøye hodet nedover. Du skal kjenne en lett strekk i nakkeovergangen og aktivering foran på halsen. Hold i 5 sekunder og slipp rolig.',
				'dosage'    => '8–10 repetisjoner × 3 sett',
				'area'      => 'nakke',
				'equipment' => 'egenvekt',
				'role'      => 'activation',
				'risk'      => 'low',
				'good_for'  => 'nakkemyalgi, hodepine, cervikogen, skjermnakke',
				'exorlive'  => '',
				'video_url' => '',
			),
			array(
				'key'       => 'nakke-skulderbladssamling-strikk',
				'title'     => 'Skulderbladssamling med treningsstrikk',
				'excerpt'   => 'Styrker rhomboideer og midtre trapezius for å motvirke fremoverbøyd nakkestilling.',
				'content'   => 'Hold en lett treningsstrikk foran brystet med begge hender og strake albuer. Før armene ut til siden slik at strikken strekkes mot brystkassen mens du klemmer skulderbladene sammen bak. Hold i 2 sekunder og før rolig tilbake.',
				'dosage'    => '12 repetisjoner × 3 sett',
				'area'      => 'nakke',
				'equipment' => 'strikk',
				'role'      => 'strength',
				'risk'      => 'low',
				'good_for'  => 'nakkemyalgi, holdning, skjermnakke, øvre rygg',
				'exorlive'  => '',
				'video_url' => '',
			),
			array(
				'key'       => 'nakke-rotasjon-firfot',
				'title'     => 'Thorakal rotasjon i firfotsstående',
				'excerpt'   => 'Øker bevegeligheten i brystryggen slik at nakken slipper overbelastning ved rotasjon.',
				'content'   => 'Stå på alle fire. Plasser den ene hånden bak nakken. Roter albuen ned mot den andre armen, og åpne deretter brystkassen opp mot taket så langt du kommer smertefritt. Følg albuen med blikket.',
				'dosage'    => '8 repetisjoner per side × 2 sett',
				'area'      => 'nakke',
				'equipment' => 'matte',
				'role'      => 'mobility',
				'risk'      => 'low',
				'good_for'  => 'stiv nakke, brystrygg, thorakal rotasjon',
				'exorlive'  => '',
				'video_url' => '',
			),
			array(
				'key'       => 'nakke-isometrisk-styrke',
				'title'     => 'Isometrisk nakkestyrke i 4 retninger',
				'excerpt'   => 'Skånsom styrking av nakken uten leddbevegelse.',
				'content'   => 'Sitt oppreist. Plasser hånden mot pannen og press hodet lett fremover mot hånden mens du holder helt igjen i 5 sekunder. Gjenta bakover mot bakhodet, og mot hver side over ørene. Bevegelsen skal være helt statisk.',
				'dosage'    => '5 sekunder hold × 4 repetisjoner per retning',
				'area'      => 'nakke',
				'equipment' => 'egenvekt',
				'role'      => 'strength',
				'risk'      => 'low',
				'good_for'  => 'nakkemyalgi, stabilitet, nakkesmerter',
				'exorlive'  => '',
				'video_url' => '',
			),
		);
	}

	/**
	 * Master dictionary of starter protocols.
	 *
	 * @return array<int,array<string,string>>
	 */
	public static function get_protocol_definitions(): array {
		return array(
			array(
				'title'     => 'Løperkne (Iliotibialt båndsyndrom)',
				'slug'      => 'loperkne',
				'codes'     => 'M76.3, L96',
				'summary'   => 'Fasebasert opptrening for løperkne (ITBS) med fokus på avlastning, lateral hoftestabilitet og gradvis retur til løping.',
				'dosage'    => '3 økter per uke. 2–3 sett per øvelse.',
				'red_flags' => 'Akutt hevelse i kneleddet, låsninger eller smerter som ikke avtar ved hvile.',
			),
			array(
				'title'     => 'Patellofemoralt smertesyndrom (PFPS)',
				'slug'      => 'patellofemoralt-smertesyndrom',
				'codes'     => 'M22.2, L96',
				'summary'   => 'Opptreningsprogram for fremre knesmerter med fokus på quadricepsstyrke, hoftestabilitet og bevegelseskontroll.',
				'dosage'    => '3–4 økter per uke. 10–12 repetisjoner per øvelse.',
				'red_flags' => 'Leddhevelse, plutselig svikt i kneet eller hvilesmerter om natten.',
			),
			array(
				'title'     => 'Akutt lumbago (Kink i ryggen)',
				'slug'      => 'akutt-lumbago',
				'codes'     => 'M54.5, L03',
				'summary'   => 'Trygt bevegelses- og avlastningsprogram for akutte korsryggsmerter. Bevegelse innenfor tålegrensen fremmer raskere tilheling.',
				'dosage'    => '2–3 korte økter daglig. Rolige, flytende bevegelser.',
				'red_flags' => 'Nummenhet i skrittet (sadelområdet), tap av blærekontroll eller kraftsvikt i bena (oppsøk legevakt umiddelbart).',
			),
			array(
				'title'     => 'Uspesifikke korsryggsmerter',
				'slug'      => 'uspesifikke-korsryggsmerter',
				'codes'     => 'M54.5, L02',
				'summary'   => 'Styrkende og stabiliserende program for vedvarende eller tilbakevendende ryggplager med fokus på ryggstrekkere og setemuskulatur.',
				'dosage'    => '3 økter per uke. 8–12 repetisjoner.',
				'red_flags' => 'Nyoppstått nummenhet eller kraftsvikt i bena.',
			),
			array(
				'title'     => 'Subakromiale skuldersmerter (SAPS)',
				'slug'      => 'subakromiale-skuldersmerter',
				'codes'     => 'M75.4, L92',
				'summary'   => 'Opptrening for seneirritasjon i skulderen med fokus på rotatorcuff, skulderbladskontroll og god holdning.',
				'dosage'    => '3–4 økter ukentlig. Kontrollerte bevegelser.',
				'red_flags' => 'Konstante nattesmerter, plutselig manglende evne til å løfte armen, eller stråling til brystet.',
			),
			array(
				'title'     => 'Plantar fasciitt (Hælsmerter)',
				'slug'      => 'plantar-fasciitt',
				'codes'     => 'M72.2, L99',
				'summary'   => 'Evidensbasert opptrening og avlastning for smerter under hælen med gradvis senebelastning og tøying av plantarfascien.',
				'dosage'    => 'Annenhver dag. 3 sett med gradvis økende motstand.',
				'red_flags' => 'Nummenhet eller prikking i fotsålen, hevelse og varme over hele foten.',
			),
			array(
				'title'     => 'Akillestendinopati',
				'slug'      => 'akillestendinopati',
				'codes'     => 'M76.6, L87',
				'summary'   => 'Tung, langsom styrketrening og eksentrisk belastning for akillessenen.',
				'dosage'    => '3 dager per uke.',
				'red_flags' => 'Plutselig smell i leggen eller manglende evne til å stå på tå (mistenkt ruptur).',
			),
			array(
				'title'     => 'Trokanterbursitt (GTPS)',
				'slug'      => 'trokanterbursitt-gtps',
				'codes'     => 'M70.6, L89',
				'summary'   => 'Avlastende og styrkende opplegg for laterale hoftesmerter med fokus på setemuskulatur og bekkenkontroll.',
				'dosage'    => '3 økter per uke.',
				'red_flags' => 'Feber, manglende evne til å belaste benet, eller smerter som stråler ned i leggen.',
			),
			array(
				'title'     => 'Belastningsplager i nakken (Nakkemyalgi)',
				'slug'      => 'belastningsplager-nakke',
				'codes'     => 'M54.2, L01',
				'summary'   => 'Skånsomt program for stiv og overbelastet nakke etter skjermarbeid eller stress med fokus på dype nakkebøyere og skulderbue.',
				'dosage'    => 'Daglige mikropauser (2–3 minutter) og 3 ukentlige treningsøkter.',
				'red_flags' => 'Svimmelhet, synsforstyrrelser, nummenhet i arm/fingre eller nedsatt kraft i grepet.',
			),
		);
	}

	/**
	 * Assignment map between protocols, phases, and exercise keys.
	 *
	 * @return array<string,array<int,array<int,string>>>
	 */
	public static function get_protocol_exercise_map(): array {
		return array(
			'loperkne' => array(
				1 => array( 'kne-sideliggende-benloft' ),
				2 => array( 'kne-clamshell-strikk' ),
				3 => array( 'kne-stol-kneboy' ),
			),
			'patellofemoralt-smertesyndrom' => array(
				1 => array( 'kne-terminal-extension', 'kne-sideliggende-benloft' ),
				2 => array( 'kne-stol-kneboy', 'kne-clamshell-strikk' ),
				3 => array( 'kne-stol-kneboy' ),
			),
			'akutt-lumbago' => array(
				1 => array( 'rygg-kne-til-bryst' ),
				2 => array( 'rygg-katte-kamel' ),
				3 => array( 'rygg-glute-bridge', 'rygg-bird-dog' ),
			),
			'uspesifikke-korsryggsmerter' => array(
				1 => array( 'rygg-kne-til-bryst', 'rygg-katte-kamel' ),
				2 => array( 'rygg-glute-bridge', 'rygg-bird-dog' ),
				3 => array( 'rygg-glute-bridge' ),
			),
			'subakromiale-skuldersmerter' => array(
				1 => array( 'skulder-pendel' ),
				2 => array( 'skulder-utadrotasjon-strikk' ),
				3 => array( 'skulder-scapula-pushups', 'skulder-liggende-y-t' ),
			),
			'plantar-fasciitt' => array(
				1 => array( 'fot-plantar-massasje' ),
				2 => array( 'fot-eksentrisk-taahev-trinn' ),
				3 => array( 'fot-ettbens-balanse' ),
			),
			'akillestendinopati' => array(
				1 => array( 'fot-isometrisk-taahev' ),
				2 => array( 'fot-eksentrisk-taahev-trinn' ),
				3 => array( 'fot-ettbens-balanse' ),
			),
			'trokanterbursitt-gtps' => array(
				1 => array( 'hofte-isometrisk-abduksjon' ),
				2 => array( 'hofte-sideliggende-benloft' ),
				3 => array( 'hofte-kneboy-miniband', 'hofte-ettbens-bekkenhev' ),
			),
			'belastningsplager-nakke' => array(
				1 => array( 'nakke-chin-tuck', 'nakke-rotasjon-firfot' ),
				2 => array( 'nakke-skulderbladssamling-strikk' ),
				3 => array( 'nakke-isometrisk-styrke' ),
			),
		);
	}
}
