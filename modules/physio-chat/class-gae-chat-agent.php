<?php
/**
 * AI Physio Chat Agent & Orchestration Engine.
 *
 * Implements the clinical physiotherapist and product advisor persona,
 * Google Places integration, CPT product RAG search, and JSON response normalization.
 *
 * @package GearEngine
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

/**
 * Class GAE_Chat_Agent
 */
class GAE_Chat_Agent {

	/**
	 * Return the master clinical prompt.
	 *
	 * @return string
	 */
	public static function get_default_prompt(): string {
		return <<<'EOT'
Du er 'Fysionett-assistenten' - en empatisk og presis fysioterapeut og produktekspert. Du kan gi utfyllende svar dersom bruker spør, men hold det ellers konsist og tydelig. Det viktigste er å gi nyttige tips og råd som hjelper brukeren, med klare faglige begrunnelser.

=== DINE VERKTØY (TOOLS) ===
Du har tilgang til to verktøy:
1. 'search_products': For å finne treningsutstyr. Vurder ALLTID produktene i system-konteksten først! Bruk KUN verktøyet hvis konteksten mangler det kunden ber om. Du må aldri finne opp produkter som ikke eksisterer. Produktene skal finnes i system-kontekst eller feed fra samarbeidspartnere.
2. 'find_physiotherapist': For å søke opp fysioterapeuter eller klinikker via Google Places. 

VIKTIG SIKKERHETSREGEL: DU ER DIREKTE KOBLET TIL GOOGLE PLACES OG KAN SØKE LOKALT! 
Når kunden ber om å finne en fysioterapeut, behandler eller klinikk, er det STRENGT FORBUDT å si at du er en virtuell assistent som ikke kan søke. DU SKAL UMIDDELBART bruke verktøyet 'find_physiotherapist'. Ikke kom med unnskyldninger. Bare bruk verktøyet! (Hvis kunden ikke har oppgitt hvilken by de bor i, må du spørre om dette først). Gi alltid svaralternativer, for eksempel de største byene i Norge.

=== PRODUKTANBEFALINGER (MODUS A & B) ===
MODUS A: HOVEDUTSTYR (Bredt vs Spesifikt ønske)
Hvis kunden snakker BREDT om trening, still et oppfølgingsspørsmål for å snevre det inn. Når kunden har spesifisert hva de ønsker, vis produktkortet UMIDDELBART. Forklar alltid hvorfor du velger de spesifikke produktene.

MODUS B: SNIK-ANBEFALING / KONTEKSTUELT (Under triage/øvelser)
Når kunden forteller om en skade/smerte, og du finner relevante produkter under "PSSST!" i konteksten, kan du når det passer gi et lite, diskret tips nederst i meldingen din. Gi en slik snik-anbefaling maks ÉN GANG per tema.

=== DIN ARBEIDSFLYT ===
1. KARTLEGGING (Triage): Forstå smerten før du selger. Still 1 spørsmål av gangen og vær grundig. Følg gjerne JSON fra systemkontekst dersom den eksisterer.
2. KLINISK NOTAT: Vis 'triage_resultat' når du har nok info til en arbeidshypotese.
3. INGEN FYLLPRAT: Du er en AI. Unngå setninger som "Jeg leter nå...".

=== JSON STRUKTUR ===
Du MÅ returnere 100% gyldig JSON. 
Du MÅ ALLTID inkludere 'choices' (en array med 2-4 knapper/svaralternativer for brukeren for å drive samtalen videre). Dette gjelder alle responser uansett tema.
Du MÅ ALLTID inkludere 'multiselect' (boolean):
- Sett "multiselect": true når du stiller kartleggingsspørsmål om symptomer, smertekarakter eller provoserende aktiviteter (triage) der brukeren kan ha flere plager samtidig, slik at brukeren kan velge flere alternativer.
- Sett "multiselect": false ved vanlige valg der brukeren velger ett enkelt svar.

For øvelser bruker du systemkontekst hvis det finnes relevante øvelser der. Dersom det ikke finnes relevante øvelser kan du foreslå egnede øvelser basert på fysioterapifaglig kunnskap.

[PRODUKTER FLERE - type: "grid"]
"card": { "type": "grid", "data": { "tittel": "Anbefalt utstyr", "items": [ {"tittel": "Navn", "butikk": "Butikk", "pris": "100 kr", "forpris": "150 kr", "url": "URL", "bilde_url": "URL"} ] } }

[KLINIKKER / LISTE - type: "liste"]
VIKTIG: Du MÅ bruke type "liste" for klinikker. Du MÅ inkludere feltene rating, adresse og telefon hvis de finnes i verktøy-svaret.
"card": { "type": "liste", "data": { "tittel": "Fysioterapeuter i nærheten", "items": [ {"tittel": "Klinikk", "rating": "4.8", "adresse": "Gate 1", "telefon": "12345678", "url": "URL", "bilde_url": "URL"} ] } }

[TRIAGE - type: "triage_resultat"]
"card": { "type": "triage_resultat", "data": { "tittel": "Diagnose", "kategori": "Kne", "alvorlighetsgrad": "Lav", "forklaring": "Info", "tiltak_na": "Ro", "tiltak_videre": "Trening", "notat_anamnese": "Sammendrag.", "notat_rode_flagg": "Ingen.", "notat_hypotese": "...", "notat_icpc2": "L99", "notat_icd10": "M22.2" } }

[ØVELSER - type: "exorlive"]
"card": { "type": "exorlive", "data": { "hovedtittel": "Klinisk protokoll", "tittel": "Treningsprogram", "ovelser": [ { "navn": "Navn", "beskrivelse": "Kort", "reps": "3 x 10", "bilde_url": "", "video_url": "", "kriterier": "", "maal": "" } ] } }
EOT;
	}

	/**
	 * Process an incoming chat message request.
	 *
	 * @param WP_REST_Request $request
	 * @return WP_REST_Response
	 */
	public static function handle_chat_request( WP_REST_Request $request ): WP_REST_Response {
		$params = $request->get_json_params();
		if ( empty( $params['message'] ) ) {
			return new WP_REST_Response(
				array(
					'message' => 'Vennligst skriv en melding.',
					'choices' => array( 'Jeg har vondt et sted', 'Lag treningsprogram', 'Finn riktig treningsutstyr' ),
					'card'    => array( 'type' => 'none', 'data' => array() ),
				),
				400
			);
		}

		$user_message = sanitize_text_field( (string) $params['message'] );
		$raw_history  = ! empty( $params['history_b64'] ) ? json_decode( urldecode( base64_decode( (string) $params['history_b64'] ) ), true ) : array();
		$history      = is_array( $raw_history ) ? array_slice( $raw_history, -10 ) : array();

		// Fetch API credentials from core settings.
		$api_key = trim( (string) get_option( 'gae_google_ai_key', '' ) );
		$model   = trim( (string) get_option( 'gae_ai_model', 'gemini-3.8-flash' ) );

		if ( empty( $api_key ) ) {
			return new WP_REST_Response(
				array(
					'message' => 'Google AI Studio API-nøkkel er ikke konfigurert i kontrollpanelet under Gear Engine > Settings. Legg inn nøkkelen for å aktivere chatten.',
					'choices' => array( 'Prøv igjen' ),
					'card'    => array( 'type' => 'none', 'data' => array() ),
				),
				200
			);
		}

		$api_url = sprintf(
			'https://generativelanguage.googleapis.com/v1beta/models/%s:generateContent?key=%s',
			rawurlencode( $model ),
			rawurlencode( $api_key )
		);

		$system_instruction = get_option( 'gae_physio_custom_prompt', '' );
		if ( empty( $system_instruction ) ) {
			$system_instruction = self::get_default_prompt();
		}

		$msg_lower = mb_strtolower( $user_message, 'UTF-8' );

		// 1. Identify shown product URLs from history to prevent repetitive recommendations.
		$shown_urls = array();
		foreach ( $history as $h_item ) {
			if ( ( $h_item['role'] ?? '' ) === 'model' ) {
				$txt = $h_item['parts'][0]['text'] ?? '';
				if ( preg_match_all( '/"url"\s*:\s*"([^"]+)"/i', $txt, $m_urls ) ) {
					foreach ( $m_urls[1] as $u ) {
						if ( '#' !== $u && '' !== trim( $u ) ) {
							$shown_urls[] = trim( $u );
						}
					}
				}
			}
		}
		$shown_urls = array_unique( $shown_urls );

		// 2. Extract price constraints.
		$max_price = 0.0;
		$min_price = 0.0;
		if ( preg_match( '/(?:mellom|fra)\s*(\d{1,3}(?:[.,\s]\d{3})*|\d+)\s*k?\s*(?:og|til|-|–)\s*(\d{1,3}(?:[.,\s]\d{3})*|\d+)\s*k?/i', $msg_lower, $m_range ) ) {
			$v1 = preg_replace( '/[^\dkK]/i', '', $m_range[1] );
			$v2 = preg_replace( '/[^\dkK]/i', '', $m_range[2] );
			$min_price = ( false !== stripos( $v1, 'k' ) ) ? (float) str_ireplace( 'k', '', $v1 ) * 1000 : (float) $v1;
			$max_price = ( false !== stripos( $v2, 'k' ) ) ? (float) str_ireplace( 'k', '', $v2 ) * 1000 : (float) $v2;
		} elseif ( preg_match( '/(?:under|maks|max|opptil|til)\s*(\d{1,3}(?:[.,\s]\d{3})*|\d+)\s*k?/i', $msg_lower, $m_price ) ) {
			$val = preg_replace( '/[^\dkK]/i', '', $m_price[0] );
			$max_price = ( false !== stripos( $val, 'k' ) ) ? (float) str_ireplace( 'k', '', $val ) * 1000 : (float) $val;
		}

		// 3. Scan local data directory for triage & exercise protocol matches.
		$data_dir = dirname( __FILE__ ) . '/data/';
		$local_json_files = is_dir( $data_dir ) ? glob( $data_dir . '*.json' ) : array();

		$detected_part = '';
		$detected_ovelser_file = '';
		$detected_triage_file  = '';
		$kroppsdeler           = array();

		if ( ! empty( $local_json_files ) ) {
			foreach ( $local_json_files as $file ) {
				$filename = basename( $file, '.json' );
				$parts    = explode( '_', $filename, 2 );
				if ( ! empty( $parts[1] ) ) {
					$triggers = explode( '-', strtolower( $parts[1] ) );
					foreach ( $triggers as $t ) {
						$t_trim = trim( $t );
						if ( mb_strlen( $t_trim, 'UTF-8' ) > 2 ) {
							$kroppsdeler[] = $t_trim;
						}
					}
				}
			}
			$kroppsdeler = array_unique( $kroppsdeler );
		}

		// Combine historical context with current message.
		$historical_context = array();
		for ( $i = count( $history ) - 1; $i >= 0; $i-- ) {
			if ( ( $history[ $i ]['role'] ?? '' ) === 'user' ) {
				$h_text = mb_strtolower( $history[ $i ]['parts'][0]['text'] ?? '', 'UTF-8' );
				foreach ( $kroppsdeler as $kd ) {
					if ( false !== mb_strpos( $h_text, $kd ) && ! in_array( $kd, $historical_context, true ) ) {
						$historical_context[] = $kd;
					}
				}
			}
			if ( count( $historical_context ) >= 3 ) {
				break;
			}
		}

		$combined_intent = implode( ' ', $historical_context ) . ' ' . $msg_lower;

		// Match body part to JSON protocols.
		if ( ! empty( $local_json_files ) ) {
			foreach ( $local_json_files as $file ) {
				$filename        = basename( $file, '.json' );
				$is_ovelser_file = ( 0 === strpos( $filename, 'ovelser_' ) );
				$is_triage_file  = ( 0 === strpos( $filename, 'triage_' ) );

				if ( $is_ovelser_file || $is_triage_file ) {
					$parts = explode( '_', $filename, 2 );
					if ( ! empty( $parts[1] ) ) {
						$triggers = explode( '-', strtolower( $parts[1] ) );
						foreach ( $triggers as $t ) {
							$t_trim = trim( $t );
							if ( mb_strlen( $t_trim, 'UTF-8' ) > 2 && false !== mb_strpos( $combined_intent, $t_trim ) ) {
								$detected_part = $t_trim;
								if ( $is_ovelser_file && empty( $detected_ovelser_file ) ) {
									$detected_ovelser_file = $file;
								}
								if ( $is_triage_file && empty( $detected_triage_file ) ) {
									$detected_triage_file = $file;
								}
							}
						}
					}
				}
			}
		}

		$kw_ovelser = array( 'øvels', 'program', 'trening', 'rehab', 'styrke', 'tøy', 'opptrening' );
		$kw_triage  = array( 'vondt', 'smerte', 'skade', 'verk', 'sliter', 'diagnose', 'hva kan det være' );

		$is_ovelser = false;
		foreach ( $kw_ovelser as $kw ) {
			if ( false !== mb_strpos( $combined_intent, $kw ) ) {
				$is_ovelser = true;
				break;
			}
		}

		$is_triage = false;
		foreach ( $kw_triage as $kw ) {
			if ( false !== mb_strpos( $combined_intent, $kw ) ) {
				$is_triage = true;
				break;
			}
		}

		$injected_context = '';

		if ( $is_ovelser && ! empty( $detected_ovelser_file ) ) {
			$t_result = self::read_safe_json( $detected_ovelser_file );
			if ( ! empty( $t_result ) ) {
				$injected_context .= "\nSYSTEM-DATA (ØVELSER FUNNET for {$detected_part}):\n" . wp_json_encode( $t_result );
			}
		} elseif ( $is_triage && ! empty( $detected_triage_file ) ) {
			$t_result = self::read_safe_json( $detected_triage_file );
			if ( ! empty( $t_result ) ) {
				$injected_context .= "\nSYSTEM-DATA (TRIAGE MAL FUNNET for {$detected_part}):\n" . wp_json_encode( $t_result );
			}
		}

		// Body-part to equipment associations (PSSST contextual cross-sell).
		$assosiasjoner = array(
			'fot,foten,føtter,plantar,hælspore,hæl,plantarfasciitt' => array( 'sko', 'såle', 'massasjeball', 'rulle' ),
			'tennisalbue,golfalbue,musearm,albue,albuen'            => array( 'støttebind', 'albuestøtte', 'kinesiotape', 'massasje' ),
			'korsrygg,lumbago,isjias,rygg,ryggen,hekseskudd,prolaps' => array( 'matte', 'foam roller', 'rulle', 'massasjepistol' ),
			'løperkne,hoppekne,knesmerte,kne,kneet,knær'            => array( 'knestøtte', 'balansepute', 'strikk', 'massasjerulle' ),
			'nakke,nakken,stiv nakke,nakkeprolaps,kink'             => array( 'massasjepistol', 'matte', 'strikk' ),
			'skulder,skulderen,skuldre,impingement,frossen skulder'  => array( 'strikk', 'manualer', 'kettlebell' ),
			'hofte,hoften,hofter,bekken,bekkenløsning'              => array( 'matte', 'strikk', 'foam roller', 'balansepute' ),
			'overtråkk,ankel,ankelen,ankler,vrikket'                => array( 'balansebrett', 'balansepute', 'støttebind', 'ankelstøtte' ),
		);

		$associated_terms = array();
		foreach ( $assosiasjoner as $keys => $terms ) {
			foreach ( explode( ',', $keys ) as $trigger ) {
				if ( false !== mb_strpos( $combined_intent, trim( $trigger ) ) ) {
					$associated_terms = array_merge( $associated_terms, $terms );
					break;
				}
			}
		}

		if ( ! empty( $associated_terms ) ) {
			$assoc_products = GAE_Product_Service::search_products(
				array(
					'keywords'     => $associated_terms,
					'min_price'    => $min_price,
					'max_price'    => $max_price,
					'limit'        => 4,
					'exclude_urls' => $shown_urls,
				)
			);
			if ( ! empty( $assoc_products ) ) {
				$injected_context .= "\nPSSST! SYSTEMET HAR UOPPFORDRET FUNNET RELEVANTE HJELPEMIDLER:\n" . wp_json_encode( $assoc_products );
			}
		}

		// Broad search terms for equipment query.
		$noise = array(
			'kjøpe', 'finne', 'beste', 'billig', 'billigste', 'har', 'dere', 'noen', 'gode', 'anbefal', 'pris', 'for',
			'trenger', 'ønsker', 'til', 'hva', 'med', 'vis', 'meg', 'andre', 'kan', 'du', 'jeg', 'vi', 'han', 'hun',
			'den', 'det', 'de', 'din', 'min', 'vår', 'hvilken', 'hvilke', 'hvem', 'hvor', 'hvordan', 'hvorfor', 'når',
			'velge', 'velg', 'mellom', 'disse', 'dette', 'denne', 'er', 'var', 'blir', 'ble', 'ha', 'hadde', 'få', 'fikk',
			'ta', 'gjøre', 'skal', 'vil', 'bør', 'må', 'bra', 'finnes', 'som', 'om', 'at', 'da', 'hvis', 'eller', 'men',
			'og', 'også', 'bare', 'kanskje', 'veldig', 'litt', 'mer', 'mest', 'mindre', 'minst', 'av', 'på', 'i', 'en',
			'et', 'ikke', 'ja', 'nei', 'flere', 'produkter', 'produkt', 'utstyr', 'utstyret', 'ting',
		);

		$clean_msg = str_replace( array( '/', '-', '&', '+', '.', ',', '?', '!' ), ' ', $combined_intent );
		$sokeord_raw = explode( ' ', $clean_msg );
		$clean_sokeord = array();
		foreach ( $sokeord_raw as $ord ) {
			$ord = trim( $ord );
			if ( mb_strlen( $ord, 'UTF-8' ) > 2 && ! in_array( $ord, $noise, true ) ) {
				$clean_sokeord[] = $ord;
			}
		}

		if ( ! empty( $clean_sokeord ) ) {
			$broad_products = GAE_Product_Service::search_products(
				array(
					'keywords'     => array_slice( $clean_sokeord, 0, 6 ),
					'min_price'    => $min_price,
					'max_price'    => $max_price,
					'limit'        => 6,
					'exclude_urls' => $shown_urls,
				)
			);
			if ( ! empty( $broad_products ) ) {
				$injected_context .= "\nTILGJENGELIG I VÅRT PRODUKTSORTIMENT:\n" . wp_json_encode( $broad_products );
			}
		}

		if ( mb_strlen( $injected_context, 'UTF-8' ) > 8000 ) {
			$injected_context = mb_substr( $injected_context, 0, 8000, 'UTF-8' ) . "\n[KONTEKST AVKORTET]";
		}

		// Tool declarations.
		$tools = array(
			array(
				'function_declarations' => array(
					array(
						'name'        => 'search_products',
						'description' => 'Søker i produktdatabasen etter utstyr. Bruk KUN hvis de forhåndsinnlastede produktene ikke passer kundens spesifikke forespørsel.',
						'parameters'  => array(
							'type'       => 'OBJECT',
							'properties' => array(
								'query'     => array( 'type' => 'STRING', 'description' => 'Søkeord, f.eks "sammenleggbar tredemølle"' ),
								'max_price' => array( 'type' => 'NUMBER', 'description' => 'Makspris kunden har nevnt' ),
								'min_price' => array( 'type' => 'NUMBER', 'description' => 'Minstepris kunden har nevnt' ),
							),
							'required'   => array( 'query' ),
						),
					),
					array(
						'name'        => 'find_physiotherapist',
						'description' => 'Søker etter fysioterapeuter eller klinikker via Google Places. DU MÅ BRUKE DETTE VERKTØYET når brukeren ber om å finne en behandler.',
						'parameters'  => array(
							'type'       => 'OBJECT',
							'properties' => array(
								'location' => array( 'type' => 'STRING', 'description' => 'Stedet det skal søkes i, f.eks "Oslo", "Bergen", "Tønsberg"' ),
							),
							'required'   => array( 'location' ),
						),
					),
				),
			),
		);

		$final_user_prompt = "BRUKER SIER:\n\"{$user_message}\"\n" . ( ! empty( $injected_context ) ? "\n--- USYNLIG SYSTEM KONTEKST ---\n{$injected_context}" : '' );
		$contents   = $history;
		$contents[] = array( 'role' => 'user', 'parts' => array( array( 'text' => $final_user_prompt ) ) );

		$body = array(
			'systemInstruction' => array( 'parts' => array( array( 'text' => $system_instruction ) ) ),
			'contents'          => $contents,
			'tools'             => $tools,
			'generationConfig'  => array(
				'temperature' => 0.1,
			),
		);

		// First Gemini API call.
		$response = self::call_remote_api( $api_url, $body );

		if ( isset( $response['error'] ) ) {
			return new WP_REST_Response(
				array(
					'message' => 'Beklager, jeg fikk et midlertidig teknisk problem ved tilkobling til AI-tjenesten. Vennligst prøv igjen.',
					'choices' => array( 'Prøv igjen' ),
					'card'    => array( 'type' => 'none', 'data' => array() ),
				),
				200
			);
		}

		$candidate = $response['candidates'][0] ?? null;
		if ( ! $candidate ) {
			return new WP_REST_Response(
				array(
					'message' => 'Beklager, jeg fikk ikke generert et svar. Prøv å omformulere spørsmålet ditt.',
					'choices' => array( 'Prøv igjen' ),
					'card'    => array( 'type' => 'none', 'data' => array() ),
				),
				200
			);
		}

		// Handle Tool Call.
		if ( isset( $candidate['content']['parts'][0]['functionCall'] ) ) {
			$call    = $candidate['content']['parts'][0]['functionCall'];
			$fn_name = $call['name'] ?? '';
			$fn_args = $call['args'] ?? array();

			// 1. Google Places Tool Call.
			if ( 'find_physiotherapist' === $fn_name ) {
				$loc     = ! empty( $fn_args['location'] ) ? (string) $fn_args['location'] : 'området';
				$clinics = self::search_google_places( $loc );

				if ( empty( $clinics ) ) {
					return new WP_REST_Response(
						array(
							'message' => 'Jeg fant dessverre ingen fysioterapeuter i ' . ucfirst( $loc ) . ' akkurat nå. Sjekk gjerne om stedsnavnet er stavet riktig, eller prøv en større by i nærheten.',
							'card'    => array( 'type' => 'none', 'data' => array() ),
							'choices' => array( 'Prøv et annet sted', 'Jeg har vondt et sted' ),
						),
						200
					);
				}

				return new WP_REST_Response(
					array(
						'message' => 'Det er veldig lurt å få en lokal fagperson til å undersøke plagene dine. Jeg har søkt opp noen av de best vurderte klinikkene i **' . ucfirst( $loc ) . "** for deg her:\n\nDu kan kontakte dem direkte eller se veibeskrivelse via knappene.",
						'card'    => array(
							'type' => 'liste',
							'data' => array(
								'tittel' => 'Klinikker i ' . ucfirst( $loc ),
								'items'  => $clinics,
							),
						),
						'choices' => array( 'Jeg har vondt et sted', 'Lag treningsprogram', 'Finn riktig treningsutstyr' ),
					),
					200
				);
			}

			// 2. Product Search Tool Call.
			if ( 'search_products' === $fn_name ) {
				$q     = ! empty( $fn_args['query'] ) ? (string) $fn_args['query'] : implode( ' ', $clean_sokeord );
				$s_max = ! empty( $fn_args['max_price'] ) ? (float) $fn_args['max_price'] : $max_price;
				$s_min = ! empty( $fn_args['min_price'] ) ? (float) $fn_args['min_price'] : $min_price;

				$tool_products = GAE_Product_Service::search_products(
					array(
						'keywords'     => explode( ' ', mb_strtolower( $q, 'UTF-8' ) ),
						'min_price'    => $s_min,
						'max_price'    => $s_max,
						'limit'        => 6,
						'exclude_urls' => $shown_urls,
					)
				);

				$contents[] = $candidate['content'];
				$contents[] = array(
					'role'  => 'function',
					'parts' => array(
						array(
							'functionResponse' => array(
								'name'     => $fn_name,
								'response' => array( 'result' => empty( $tool_products ) ? 'Ingen produkter funnet.' : $tool_products ),
							),
						),
					),
				);

				$body['contents'] = $contents;
				unset( $body['tools'] );
				$body['generationConfig']['responseMimeType'] = 'application/json';

				$response = self::call_remote_api( $api_url, $body );
				$candidate = $response['candidates'][0] ?? null;
			}
		}

		// Ensure candidate response is valid JSON.
		$json_text = trim( (string) ( $candidate['content']['parts'][0]['text'] ?? '' ) );
		$bot_data  = self::repair_and_decode_json( $json_text );

		if ( ! $bot_data ) {
			return new WP_REST_Response(
				array(
					'message' => 'Beklager, jeg fant informasjonen, men slet med å formatere den. Kan du prøve å spørre på nytt?',
					'choices' => array( 'Prøv igjen' ),
					'card'    => array( 'type' => 'none', 'data' => array() ),
				),
				200
			);
		}

		// Normalize cards and choices.
		$bot_data = self::normalize_bot_response( $bot_data );

		return new WP_REST_Response( $bot_data, 200 );
	}

	/**
	 * Search Google Places API for physiotherapists in a given location.
	 *
	 * @param string $location
	 * @return array
	 */
	public static function search_google_places( string $location ): array {
		$api_key = trim( (string) get_option( 'gae_google_places_key', '' ) );
		if ( empty( $api_key ) ) {
			return array();
		}

		$url = 'https://places.googleapis.com/v1/places:searchText';

		$body = array(
			'textQuery'      => 'fysioterapeut i ' . $location,
			'languageCode'   => 'no',
			'maxResultCount' => 10,
		);

		$response = wp_remote_post(
			$url,
			array(
				'headers' => array(
					'Content-Type'     => 'application/json',
					'X-Goog-Api-Key'   => $api_key,
					'X-Goog-FieldMask' => 'places.displayName,places.formattedAddress,places.rating,places.userRatingCount,places.googleMapsUri,places.photos,places.nationalPhoneNumber,places.websiteUri',
					'Referer'          => home_url( '/' ),
				),
				'body'    => wp_json_encode( $body ),
				'timeout' => 15,
			)
		);

		if ( is_wp_error( $response ) ) {
			return array();
		}

		$data = json_decode( wp_remote_retrieve_body( $response ), true );
		$results = array();

		if ( ! empty( $data['places'] ) && is_array( $data['places'] ) ) {
			$places = $data['places'];

			// Sort by rating desc, then reviews count desc.
			usort( $places, function( $a, $b ) {
				$rA = isset( $a['rating'] ) ? (float) $a['rating'] : 0.0;
				$rB = isset( $b['rating'] ) ? (float) $b['rating'] : 0.0;
				if ( abs( $rA - $rB ) < 0.001 ) {
					$revA = isset( $a['userRatingCount'] ) ? (int) $a['userRatingCount'] : 0;
					$revB = isset( $b['userRatingCount'] ) ? (int) $b['userRatingCount'] : 0;
					return $revB - $revA;
				}
				return ( $rB > $rA ) ? 1 : -1;
			} );

			$top_places = array_slice( $places, 0, 4 );

			foreach ( $top_places as $place ) {
				$photo_url = '';
				if ( ! empty( $place['photos'][0]['name'] ) ) {
					$photo_name = $place['photos'][0]['name'];
					$media_url  = "https://places.googleapis.com/v1/{$photo_name}/media?maxHeightPx=400&maxWidthPx=400&skipHttpRedirect=true&key={$api_key}";
					$media_res  = wp_remote_get( $media_url, array( 'timeout' => 5 ) );

					if ( ! is_wp_error( $media_res ) ) {
						$media_data = json_decode( wp_remote_retrieve_body( $media_res ), true );
						if ( ! empty( $media_data['photoUri'] ) ) {
							$photo_url = $media_data['photoUri'];
						}
					}
				}

				$rating  = isset( $place['rating'] ) ? (string) $place['rating'] : 'Ny';
				$phone   = isset( $place['nationalPhoneNumber'] ) ? preg_replace( '/[^0-9+ ]/', '', (string) $place['nationalPhoneNumber'] ) : '';
				$address = isset( $place['formattedAddress'] ) ? str_replace( ', Norge', '', (string) $place['formattedAddress'] ) : 'Adresse ikke oppgitt';
				$target  = ! empty( $place['websiteUri'] ) ? $place['websiteUri'] : ( $place['googleMapsUri'] ?? '#' );

				$results[] = array(
					'tittel'    => $place['displayName']['text'] ?? 'Fysioterapeut',
					'rating'    => $rating,
					'adresse'   => $address,
					'telefon'   => $phone,
					'url'       => $target,
					'bilde_url' => $photo_url,
				);
			}
		}

		return $results;
	}

	/**
	 * Perform remote API post with timeout shield.
	 *
	 * @param string $url
	 * @param array  $payload
	 * @return array
	 */
	private static function call_remote_api( string $url, array $payload ): array {
		$response = wp_remote_post(
			$url,
			array(
				'headers' => array( 'Content-Type' => 'application/json' ),
				'body'    => wp_json_encode( $payload ),
				'timeout' => 45,
			)
		);

		if ( is_wp_error( $response ) ) {
			return array( 'error' => array( 'message' => $response->get_error_message() ) );
		}

		return (array) json_decode( wp_remote_retrieve_body( $response ), true );
	}

	/**
	 * Safely read and decode a local JSON file.
	 *
	 * @param string $path
	 * @return array
	 */
	public static function read_safe_json( string $path ): array {
		if ( ! file_exists( $path ) ) {
			return array();
		}
		$content = file_get_contents( $path );
		if ( false === $content ) {
			return array();
		}
		$decoded = json_decode( $content, true );
		return is_array( $decoded ) ? $decoded : array();
	}

	/**
	 * Robustly parse and repair LLM JSON responses.
	 *
	 * @param string $json_text
	 * @return array|null
	 */
	private static function repair_and_decode_json( string $json_text ): ?array {
		$start = strpos( $json_text, '{' );
		$end   = strrpos( $json_text, '}' );

		if ( false !== $start && false !== $end ) {
			$json_text = substr( $json_text, $start, $end - $start + 1 );
		}

		$decoded = json_decode( $json_text, true );
		if ( is_array( $decoded ) ) {
			return $decoded;
		}

		// Attempt cleanups for unescaped control chars.
		$fixed = preg_replace( '/[\x00-\x1F\x7F]/u', '', $json_text );
		$fixed = preg_replace( '/(?<!\\\\)\r/', '', (string) $fixed );
		$decoded = json_decode( (string) $fixed, true );

		return is_array( $decoded ) ? $decoded : null;
	}

	/**
	 * Normalize bot response structure, card types, and choices array.
	 *
	 * @param array $bot_data
	 * @return array
	 */
	private static function normalize_bot_response( array $bot_data ): array {
		if ( ! isset( $bot_data['card'] ) || ! is_array( $bot_data['card'] ) ) {
			$bot_data['card'] = array( 'type' => 'none', 'data' => array() );
		}

		// Protect card type mismatches.
		$card_type = strtolower( (string) ( $bot_data['card']['type'] ?? 'none' ) );
		$items     = $bot_data['card']['data']['items'] ?? array();

		if ( 'liste' === $card_type && ! empty( $items[0] ) ) {
			if ( ! isset( $items[0]['rating'] ) && ! isset( $items[0]['adresse'] ) ) {
				$bot_data['card']['type'] = 'grid';
			}
		} elseif ( 'grid' === $card_type && ! empty( $items[0] ) ) {
			if ( isset( $items[0]['rating'] ) || isset( $items[0]['adresse'] ) ) {
				$bot_data['card']['type'] = 'liste';
			}
		}

		// Ensure required defaults for products.
		if ( in_array( $bot_data['card']['type'], array( 'grid', 'stort_kort', 'lite_kort' ), true ) ) {
			if ( empty( $bot_data['card']['data']['items'] ) ) {
				$bot_data['card']['type'] = 'none';
			} else {
				foreach ( $bot_data['card']['data']['items'] as &$item ) {
					$item['tittel']    = $item['tittel'] ?? 'Anbefalt treningsutstyr';
					$item['butikk']    = $item['butikk'] ?? 'Nettbutikk';
					$item['pris']      = $item['pris'] ?? 'Sjekk pris';
					$item['url']       = $item['url'] ?? '#';
					$item['bilde_url'] = ! empty( $item['bilde_url'] ) ? $item['bilde_url'] : 'https://placehold.co/600x450/1e293b/ffffff?text=Bilde+mangler';
				}
			}
		}

		// Clean choices.
		if ( isset( $bot_data['choices'] ) && is_array( $bot_data['choices'] ) ) {
			$clean_choices = array();
			foreach ( $bot_data['choices'] as $c ) {
				if ( is_string( $c ) && '' !== trim( $c ) ) {
					$clean_choices[] = sanitize_text_field( trim( $c ) );
				} elseif ( is_array( $c ) && ! empty( $c['text'] ) ) {
					$clean_choices[] = sanitize_text_field( trim( (string) $c['text'] ) );
				} elseif ( is_array( $c ) && ! empty( $c['label'] ) ) {
					$clean_choices[] = sanitize_text_field( trim( (string) $c['label'] ) );
				}
			}
			$bot_data['choices'] = array_values( array_unique( $clean_choices ) );
		}

		if ( empty( $bot_data['choices'] ) ) {
			$bot_data['choices'] = array( 'Jeg har vondt et sted', 'Lag treningsprogram', 'Finn riktig treningsutstyr' );
		}

		// Ensure multiselect boolean.
		$bot_data['multiselect'] = ! empty( $bot_data['multiselect'] );

		// Format message markdown.
		if ( isset( $bot_data['message'] ) && is_string( $bot_data['message'] ) ) {
			$msg = $bot_data['message'];
			$msg = preg_replace( '/\[([^\]]+)\]\((https?:\/\/[^\)]+)\)/', '<a href="$2" target="_blank" rel="noopener" style="color: #0A2558; text-decoration: underline; font-weight: 600;">$1</a>', $msg );
			$msg = preg_replace( '/\*\*(.*?)\*\*/s', '<strong style="color: #1A2A3A; font-weight: 700;">$1</strong>', $msg );
			$msg = str_replace( '*', '', $msg );
			$msg = preg_replace( '/^[\-]\s+(.*)$/m', '<li style="margin-left: 20px; list-style-type: disc; margin-bottom: 6px;">$1</li>', $msg );
			$msg = preg_replace( '/(<li.*?>.*?<\/li>\s*)+/s', '<ul style="margin-top: 12px; margin-bottom: 16px;">$0</ul>', $msg );
			$msg = preg_replace( '/<\/li>\s*<li/s', '</li><li', $msg );
			$msg = preg_replace( '/<\/ul>\s*/s', '</ul>', $msg );
			$msg = preg_replace( '/\s*<ul>/s', '<ul>', $msg );
			$msg = preg_replace( '/\n{2,}/', '<br><br>', $msg );
			$msg = nl2br( $msg );
			$bot_data['message'] = $msg;
		}

		return $bot_data;
	}
}
