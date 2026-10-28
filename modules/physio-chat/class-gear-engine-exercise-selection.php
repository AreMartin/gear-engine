<?php
/**
 * Server-side exercise selection rules for the physio chat.
 *
 * @package GearEngine
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Decides which exercises the AI may choose from and enforces the safety gate.
 *
 * Modes: "protocol" (approved clinician protocol), "general" (low/moderate risk
 * exercises for the body area), "blocked" (red flags, no exercises) and "none".
 */
class Gear_Engine_Exercise_Selection {

	public const GEAR_ENGINE_LOG_POST_TYPE   = 'gear_engine_selection_log';
	public const GEAR_ENGINE_LOG_LIMIT       = 500;
	public const GEAR_ENGINE_PROTOCOL_LIMIT  = 5;
	public const GEAR_ENGINE_GENERAL_LIMIT   = 12;

	/**
	 * Body-part keywords mapped to exercise body-area slugs.
	 *
	 * @var array<string, string>
	 */
	private const AREA_ALIASES = array(
		'kne'     => 'kne',
		'skulder' => 'skulder',
		'rygg'    => 'rygg',
		'fot'     => 'fot-ankel',
		'ankel'   => 'fot-ankel',
		'hofte'   => 'hofte',
		'albue'   => 'albue',
	);

	/**
	 * Unambiguous red-flag phrases in the user's own words (Norwegian).
	 *
	 * @var array<int, string>
	 */
	private const RED_FLAG_PHRASES = array(
		'nummenhet',
		'nummen i',
		'lammelse',
		'lammet',
		'blærekontroll',
		'tarmkontroll',
		'ridebukse',
		'brystsmerte',
		'feber',
		'kan ikke belaste',
		'får ikke belastet',
		'uutholdelig',
		'ute av ledd',
	);

	public function __construct() {
		add_action( 'init', array( $this, 'register_log_post_type' ), 7 );
	}

	/**
	 * Register the read-only audit log shown under the Exercise Library menu.
	 *
	 * @return void
	 */
	public function register_log_post_type(): void {
		register_post_type(
			self::GEAR_ENGINE_LOG_POST_TYPE,
			array(
				'labels'       => array(
					'name'          => __( 'Selection Log', 'gear-engine' ),
					'singular_name' => __( 'Selection Log Entry', 'gear-engine' ),
					'menu_name'     => __( 'Selection Log', 'gear-engine' ),
				),
				'public'       => false,
				'show_ui'      => true,
				'show_in_menu' => 'tools.php',
				'supports'     => array( 'title', 'editor' ),
				'capabilities' => array( 'create_posts' => 'do_not_allow' ),
				'map_meta_cap' => true,
			)
		);
	}

	/**
	 * Map a body-part keyword to an exercise area slug.
	 *
	 * @param string $keyword Keyword such as "kne".
	 * @return string Empty when unknown.
	 */
	public static function area_slug_for( string $keyword ): string {
		return self::AREA_ALIASES[ mb_strtolower( trim( $keyword ), 'UTF-8' ) ] ?? '';
	}

	/**
	 * Read the most recent triage card from the chat history.
	 *
	 * @param array<int, mixed> $history Chat history (Gemini content format).
	 * @return array<string, mixed> Empty array when no triage has been shown.
	 */
	public static function extract_triage( array $history ): array {
		for ( $i = count( $history ) - 1; $i >= 0; $i-- ) {
			$item = $history[ $i ] ?? null;
			if ( ! is_array( $item ) || 'model' !== ( $item['role'] ?? '' ) ) {
				continue;
			}
			$text = $item['parts'][0]['text'] ?? '';
			if ( ! is_string( $text ) ) {
				continue;
			}
			$start = strpos( $text, '{' );
			$end   = strrpos( $text, '}' );
			if ( false === $start || false === $end || $end <= $start ) {
				continue;
			}
			$decoded = json_decode( substr( $text, $start, $end - $start + 1 ), true );
			$card    = is_array( $decoded ) ? ( $decoded['card'] ?? null ) : null;
			if ( ! is_array( $card ) || 'triage_resultat' !== strtolower( (string) ( $card['type'] ?? '' ) ) || ! is_array( $card['data'] ?? null ) ) {
				continue;
			}

			$data  = $card['data'];
			$codes = array();
			foreach ( array( 'notat_icd10', 'notat_icpc2' ) as $field ) {
				if ( is_scalar( $data[ $field ] ?? null ) ) {
					preg_match_all( '/[A-Za-z]\d{2}(?:\.\d{1,4})?/', (string) $data[ $field ], $matches );
					$codes = array_merge( $codes, $matches[0] );
				}
			}

			$scalar = static function ( string $key ) use ( $data ): string {
				return is_scalar( $data[ $key ] ?? null ) ? trim( (string) $data[ $key ] ) : '';
			};

			return array(
				'codes'     => array_values( array_unique( array_map( 'strtoupper', $codes ) ) ),
				'severity'  => $scalar( 'alvorlighetsgrad' ),
				'red_flags' => $scalar( 'notat_rode_flagg' ),
				'category'  => $scalar( 'kategori' ),
				'title'     => $scalar( 'tittel' ),
			);
		}

		return array();
	}

	/**
	 * Return a reason string when exercises must not be offered, otherwise an empty string.
	 *
	 * @param array<string, mixed> $triage    Triage summary from extract_triage().
	 * @param string               $user_text Recent user messages.
	 * @return string
	 */
	public static function get_block_reason( array $triage, string $user_text ): string {
		$severity = mb_strtolower( (string) ( $triage['severity'] ?? '' ), 'UTF-8' );
		if ( '' !== $severity && preg_match( '/høy|alvorlig|akutt|high|severe/u', $severity ) ) {
			return 'severity';
		}

		$flags = mb_strtolower( trim( (string) ( $triage['red_flags'] ?? '' ) ), 'UTF-8' );
		if ( '' !== $flags && ! preg_match( '/^(ingen|nei|ikke|ikke noe|none|no\b)/u', $flags ) ) {
			return 'red_flags';
		}

		$text = mb_strtolower( $user_text, 'UTF-8' );
		foreach ( self::RED_FLAG_PHRASES as $phrase ) {
			if ( false !== mb_strpos( $text, $phrase ) ) {
				return 'user_reported';
			}
		}

		return '';
	}

	/**
	 * Decide the selection mode and the candidate exercises the AI may choose from.
	 *
	 * @param array<string, mixed> $triage          Triage summary.
	 * @param string               $area_slug       Body-area slug, or empty.
	 * @param string               $user_text       Recent user messages.
	 * @param bool                 $general_enabled Whether general (non-protocol) mode is allowed.
	 * @param bool                 $wants_exercises False when the user is not asking for exercises; only the safety gate then runs.
	 * @return array<string, mixed>
	 */
	public static function select( array $triage, string $area_slug, string $user_text, bool $general_enabled, bool $wants_exercises = true ): array {
		$selection = array(
			'mode'       => 'none',
			'candidates' => array(),
			'protocol'   => null,
			'reason'     => '',
			'area'       => $area_slug,
			'triage'     => $triage,
		);

		$reason = self::get_block_reason( $triage, $user_text );
		if ( '' !== $reason ) {
			$selection['mode']   = 'blocked';
			$selection['reason'] = $reason;
			return $selection;
		}

		if ( ! $wants_exercises ) {
			return $selection;
		}

		$codes = isset( $triage['codes'] ) && is_array( $triage['codes'] ) ? $triage['codes'] : array();
		foreach ( Gear_Engine_Protocols::find_approved_by_codes( $codes ) as $protocol ) {
			$candidates = Gear_Engine_Exercise_Service::search_exercises(
				array(
					'protocol' => $protocol['slug'],
					'limit'    => self::GEAR_ENGINE_PROTOCOL_LIMIT,
				)
			);
			if ( $candidates ) {
				$selection['mode']       = 'protocol';
				$selection['protocol']   = $protocol;
				$selection['candidates'] = $candidates;
				return $selection;
			}
		}

		if ( $general_enabled && '' !== $area_slug ) {
			$candidates = Gear_Engine_Exercise_Service::search_exercises(
				array(
					'area'     => $area_slug,
					'max_risk' => 'moderate',
					'limit'    => self::GEAR_ENGINE_GENERAL_LIMIT,
				)
			);
			if ( $candidates ) {
				$selection['mode']       = 'general';
				$selection['candidates'] = $candidates;
			}
		}

		return $selection;
	}

	/**
	 * Build the hidden context and selection rules sent to the AI.
	 *
	 * @param array<string, mixed> $selection Result of select().
	 * @return string
	 */
	public static function build_context( array $selection ): string {
		$mode = (string) ( $selection['mode'] ?? 'none' );

		if ( 'blocked' === $mode ) {
			return "\nØVELSER_SPERRET: Røde flagg eller høy alvorlighetsgrad er registrert. Du skal IKKE foreslå eller vise øvelser. Anbefal vurdering av lege eller fysioterapeut, og tilby å finne en behandler i nærheten. Returner ikke et 'exorlive'-kort.";
		}
		if ( ! in_array( $mode, array( 'protocol', 'general' ), true ) || empty( $selection['candidates'] ) ) {
			return '';
		}

		$compact = array_map(
			static function ( array $exercise ): array {
				return array(
					'library_id' => $exercise['id'],
					'title'      => $exercise['title'],
					'summary'    => wp_trim_words( $exercise['summary'] ?: $exercise['description'], 24 ),
					'area'       => $exercise['area'],
					'equipment'  => $exercise['equipment'],
					'dosage'     => $exercise['dosage'],
				);
			},
			$selection['candidates']
		);

		if ( 'protocol' === $mode ) {
			$protocol = $selection['protocol'];
			$text     = "\nMODUS: KLINISK PROTOKOLL ({$protocol['name']}). Programmet er gjennomgått av en fysioterapeut.\n";
			$text    .= "PROTOKOLL_NOTAT: {$protocol['summary']}\n";
			if ( '' !== trim( (string) $protocol['dosage'] ) ) {
				$text .= "DOSERING_OG_PROGRESJON: {$protocol['dosage']}\n";
			}
			if ( '' !== trim( (string) $protocol['red_flags'] ) ) {
				$text .= "KONTRAINDIKASJONER_RØDE_FLAGG: {$protocol['red_flags']}\nHvis noe av dette gjelder brukeren etter det som er fortalt, vis IKKE øvelser; anbefal vurdering av lege eller fysioterapeut.\n";
			}
			$text .= "REGLER: Presenter øvelsene fra CPT_EXERCISE_LIBRARY. Du skal ikke endre, bytte ut eller legge til øvelser. Forklar kort hvordan programmet passer brukerens situasjon.\n";
		} else {
			$text  = "\nMODUS: GENERELLE ØVELSESFORSLAG (ingen klinisk gjennomgått protokoll for denne tilstanden).\n";
			$text .= "STRENGE REGLER FOR VALG:\n";
			$text .= "1. Velg KUN fra CPT_EXERCISE_LIBRARY, maks 5. Aldri andre øvelser.\n";
			$text .= "2. Foretrekk skånsomme øvelser som passer brukerens oppgitte smerte, funksjon og kapasitet.\n";
			$text .= "3. Utelat øvelser som strider mot det brukeren har fortalt (akutt skade, operasjon, forverring ved belastning).\n";
			$text .= "4. Er du usikker, velg færre øvelser eller ingen, og forklar hvorfor.\n";
			$text .= "5. Ikke påstå at programmet er evidensbasert eller spesifikt for en diagnose, og ikke still diagnose.\n";
			$text .= "6. Gi en kort begrunnelse per øvelse i feltet \"begrunnelse\".\n";
		}

		return $text . "\nCPT_EXERCISE_LIBRARY (velg kun relevante øvelser og returner library_id):\n" . wp_json_encode( $compact );
	}

	/**
	 * Short instruction appended to the user turn for the chosen mode.
	 *
	 * @param array<string, mixed> $selection Result of select().
	 * @return string
	 */
	public static function build_instruction( array $selection ): string {
		if ( empty( $selection['candidates'] ) ) {
			return '';
		}
		return "\nFor treningsprogram: velg kun øvelser fra CPT_EXERCISE_LIBRARY. Returner library_id og en kort \"begrunnelse\" per øvelse. Ikke skriv egne øvelsesdetaljer; backend fyller disse fra CPT.";
	}

	/**
	 * Collect the AI's per-exercise rationale before the card is normalized.
	 *
	 * @param array<string, mixed> $bot_data Parsed AI response.
	 * @return array<string, string> Map of library ID to rationale.
	 */
	public static function extract_rationales( array $bot_data ): array {
		$rationales = array();
		$exercises  = $bot_data['card']['data']['ovelser'] ?? array();
		if ( ! is_array( $exercises ) ) {
			return $rationales;
		}
		foreach ( $exercises as $exercise ) {
			if ( ! is_array( $exercise ) || ! is_scalar( $exercise['library_id'] ?? null ) || ! is_scalar( $exercise['begrunnelse'] ?? null ) ) {
				continue;
			}
			$rationales[ (string) absint( $exercise['library_id'] ) ] = mb_substr( sanitize_text_field( (string) $exercise['begrunnelse'] ), 0, 300, 'UTF-8' );
		}
		return $rationales;
	}

	/**
	 * Enforce the selection mode on the normalized response, label it and log it.
	 *
	 * @param array<string, mixed> $bot_data   Normalized response.
	 * @param array<string, mixed> $selection  Result of select().
	 * @param array<string, string> $rationales Rationale per library ID.
	 * @return array<string, mixed>
	 */
	public static function finalize_response( array $bot_data, array $selection, array $rationales = array() ): array {
		$mode         = (string) ( $selection['mode'] ?? 'none' );
		$is_exercises = isset( $bot_data['card']['type'] ) && 'exorlive' === strtolower( (string) $bot_data['card']['type'] );

		if ( 'blocked' === $mode ) {
			if ( $is_exercises ) {
				$bot_data['card'] = array( 'type' => 'none', 'data' => array() );
				self::log( $selection, array(), $rationales );
			}
			return $bot_data;
		}

		if ( ! $is_exercises || ! in_array( $mode, array( 'protocol', 'general' ), true ) ) {
			return $bot_data;
		}

		$bot_data['card']['data']['hovedtittel'] = 'protocol' === $mode
			? 'Klinisk gjennomgått program: ' . (string) $selection['protocol']['name']
			: 'Generelle øvelsesforslag – ikke et klinisk program for denne tilstanden';

		$selected = array();
		foreach ( (array) ( $bot_data['card']['data']['ovelser'] ?? array() ) as $exercise ) {
			if ( is_array( $exercise ) && isset( $exercise['library_id'] ) ) {
				$selected[] = (int) $exercise['library_id'];
			}
		}
		self::log( $selection, $selected, $rationales );

		return $bot_data;
	}

	/**
	 * Save an audit entry (no user message text is stored) and prune old entries.
	 *
	 * @param array<string, mixed>  $selection  Result of select().
	 * @param array<int, int>       $selected   Exercise IDs shown to the user.
	 * @param array<string, string> $rationales Rationale per library ID.
	 * @return int Post ID, or 0 on failure.
	 */
	public static function log( array $selection, array $selected, array $rationales ): int {
		$triage = isset( $selection['triage'] ) && is_array( $selection['triage'] ) ? $selection['triage'] : array();
		$mode   = (string) ( $selection['mode'] ?? 'none' );
		$record = array(
			'mode'        => $mode,
			'block'       => (string) ( $selection['reason'] ?? '' ),
			'protocol'    => isset( $selection['protocol']['slug'] ) ? (string) $selection['protocol']['slug'] : '',
			'area'        => (string) ( $selection['area'] ?? '' ),
			'codes'       => $triage['codes'] ?? array(),
			'severity'    => (string) ( $triage['severity'] ?? '' ),
			'candidates'  => array_map( 'absint', array_column( (array) ( $selection['candidates'] ?? array() ), 'id' ) ),
			'selected'    => array_map( 'absint', $selected ),
			'rationales'  => $rationales,
		);

		$post_id = wp_insert_post(
			array(
				'post_type'    => self::GEAR_ENGINE_LOG_POST_TYPE,
				'post_status'  => 'private',
				'post_title'   => sprintf( '%s | %s | %s', $mode, $record['area'] ?: '-', current_time( 'mysql' ) ),
				'post_content' => wp_json_encode( $record, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE ),
			),
			true
		);
		if ( is_wp_error( $post_id ) || ! $post_id ) {
			return 0;
		}

		$query = new WP_Query(
			array(
				'post_type'      => self::GEAR_ENGINE_LOG_POST_TYPE,
				'post_status'    => 'any',
				'posts_per_page' => -1,
				'fields'         => 'ids',
				'orderby'        => 'date',
				'order'          => 'DESC',
				'no_found_rows'  => true,
			)
		);
		foreach ( array_slice( array_map( 'absint', $query->posts ), self::GEAR_ENGINE_LOG_LIMIT ) as $old_id ) {
			wp_delete_post( $old_id, true );
		}

		return (int) $post_id;
	}
}
