<?php
/**
 * AI REST API & Multi-Variant Transient Caching Module.
 *
 * @package GearEngine
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

/**
 * Class GAE_AI_API
 *
 * Exposes custom REST API endpoints for the "Utstyrsvelger" (Equipment Chooser)
 * and interfaces with Google AI Studio (Gemini) with 30-day multi-variant caching.
 */
class GAE_AI_API {

	/**
	 * REST API Namespace.
	 */
	public const REST_NAMESPACE = 'gae/v1';

	/**
	 * Cache TTL: 30 days in seconds (30 * 86,400 = 2,592,000).
	 */
	public const CACHE_TTL = 2592000;

	/**
	 * Constructor.
	 */
	public function __construct() {
		add_action( 'rest_api_init', array( $this, 'register_rest_routes' ) );
	}

	/**
	 * Register custom REST API routes.
	 *
	 * @return void
	 */
	public function register_rest_routes(): void {
		// 1. Recommendation Pitch endpoint: /wp-json/gae/v1/recommendation.
		register_rest_route(
			self::REST_NAMESPACE,
			'/recommendation',
			array(
				array(
					'methods'             => array( 'POST', 'GET' ),
					'callback'            => array( $this, 'handle_recommendation_request' ),
					'permission_callback' => '__return_true',
					'args'                => array(
						'context'    => array(
							'required'          => true,
							'description'       => __( 'Quiz answers, training goals, or user constraints.', 'gear-engine' ),
							'validate_callback' => function( $param ) {
								return ! empty( $param );
							},
						),
						'product_id' => array(
							'required'          => false,
							'type'              => 'integer',
							'description'       => __( 'Target exercise gear post ID.', 'gear-engine' ),
							'sanitize_callback' => 'absint',
						),
						'ean'        => array(
							'required'          => false,
							'type'              => 'string',
							'description'       => __( 'Target exercise gear EAN barcode.', 'gear-engine' ),
							'sanitize_callback' => 'sanitize_text_field',
						),
						'refresh'    => array(
							'required'          => false,
							'type'              => 'boolean',
							'description'       => __( 'Bypass transient cache (admin testing).', 'gear-engine' ),
							'sanitize_callback' => 'rest_sanitize_boolean',
						),
					),
				),
			)
		);

		// 2. Headless Gear Catalog Query endpoint for Utstyrsvelger quiz: /wp-json/gae/v1/gear.
		register_rest_route(
			self::REST_NAMESPACE,
			'/gear',
			array(
				array(
					'methods'             => 'GET',
					'callback'            => array( $this, 'handle_gear_catalog_request' ),
					'permission_callback' => '__return_true',
					'args'                => array(
						'category'  => array(
							'required'          => false,
							'type'              => 'string',
							'sanitize_callback' => 'sanitize_text_field',
						),
						'min_price' => array(
							'required'          => false,
							'type'              => 'number',
							'sanitize_callback' => 'floatval',
						),
						'max_price' => array(
							'required'          => false,
							'type'              => 'number',
							'sanitize_callback' => 'floatval',
						),
						'in_stock'  => array(
							'required'          => false,
							'type'              => 'boolean',
							'sanitize_callback' => 'rest_sanitize_boolean',
						),
						'limit'     => array(
							'required'          => false,
							'type'              => 'integer',
							'default'           => 12,
							'sanitize_callback' => 'absint',
						),
					),
				),
			)
		);
	}

	/**
	 * Handle recommendation REST request.
	 *
	 * Checks 30-day multi-variant transient cache, queries Google AI Studio if miss,
	 * stores 3-5 variations, and randomly returns one variation to user.
	 *
	 * @param \WP_REST_Request $request REST request object.
	 * @return \WP_REST_Response|\WP_Error
	 */
	public function handle_recommendation_request( \WP_REST_Request $request ) {
		$context_raw = $request->get_param( 'context' );
		$product_id  = absint( $request->get_param( 'product_id' ) );
		$ean         = sanitize_text_field( (string) $request->get_param( 'ean' ) );
		$refresh     = (bool) $request->get_param( 'refresh' );

		// Resolve product data.
		$product = null;
		if ( $product_id > 0 ) {
			$product = GAE_CPT::get_product_data( $product_id );
		} elseif ( ! empty( $ean ) ) {
			$found = GAE_CPT::get_product_by_ean( $ean );
			if ( $found ) {
				$product = GAE_CPT::get_product_data( $found );
			}
		}

		if ( ! $product ) {
			return new \WP_Error(
				'gae_product_not_found',
				__( 'Specified exercise gear product was not found.', 'gear-engine' ),
				array( 'status' => 404 )
			);
		}

		// Normalize context string for hash generation.
		$context_str = is_array( $context_raw ) ? (string) wp_json_encode( $context_raw ) : trim( (string) $context_raw );
		$dedup_key   = ! empty( $product['ean'] ) ? $product['ean'] : (string) $product['id'];

		// Multi-Variant Transients Caching: MD5 hash of (context + EAN).
		$cache_key = 'gae_ai_' . md5( $context_str . '_' . $dedup_key );

		// Check cache unless refresh was explicitly requested by an admin.
		if ( ! $refresh ) {
			$cached_variations = get_transient( $cache_key );
			if ( is_array( $cached_variations ) && ! empty( $cached_variations ) ) {
				// Randomly return one variation to the user to bypass API costs on repeated queries.
				$selected_pitch = $cached_variations[ array_rand( $cached_variations ) ];
				return new \WP_REST_Response(
					array(
						'status'           => 'success',
						'cached'           => true,
						'pitch'            => $selected_pitch,
						'total_variations' => count( $cached_variations ),
						'product'          => $product,
					),
					200
				);
			}
		}

		// Cache miss: Call Google AI Studio (Gemini) API for 3-5 variations.
		$variations = $this->call_google_ai_studio( $product, $context_str );

		// Ensure we have a valid array of variations.
		if ( empty( $variations ) || ! is_array( $variations ) ) {
			$variations = $this->generate_fallback_variations( $product, $context_str );
		}

		// Store variations array in transient for 30 days.
		set_transient( $cache_key, $variations, self::CACHE_TTL );

		// Randomly return one variation.
		$selected_pitch = $variations[ array_rand( $variations ) ];

		return new \WP_REST_Response(
			array(
				'status'           => 'success',
				'cached'           => false,
				'pitch'            => $selected_pitch,
				'total_variations' => count( $variations ),
				'product'          => $product,
			),
			200
		);
	}

	/**
	 * Call Google AI Studio (Gemini API) to generate an array of 3-5 tailored pitch variations.
	 *
	 * @param array<string, mixed> $product     Product attributes.
	 * @param string               $context_str User quiz context.
	 * @return string[] Array of pitch variations.
	 */
	public function call_google_ai_studio( array $product, string $context_str ): array {
		$api_key = trim( (string) get_option( 'gae_google_ai_key', '' ) );
		$model   = trim( (string) get_option( 'gae_ai_model', 'gemini-1.5-flash' ) ) ?: 'gemini-1.5-flash';

		if ( empty( $api_key ) ) {
			return array();
		}

		$endpoint = sprintf(
			'https://generativelanguage.googleapis.com/v1beta/models/%s:generateContent?key=%s',
			rawurlencode( $model ),
			rawurlencode( $api_key )
		);

		// Build prompt.
		$system_instructions = "Du er en ledende personlig trener og ekspert på treningsutstyr for den norske nettsiden Utstyrsvelger. "
			. "Generer nøyaktig 3 til 5 unike, personlig tilpassede og overbevisende produktanbefalinger (varianter) på naturlig norsk. "
			. "Hver variant skal være 2-3 setninger lang, fremheve hvorfor dette spesifikke utstyret løser brukerens behov og mål, og ha en engasjerende, profesjonell tone. "
			. "Svaret MÅ returneres som et gyldig JSON array med strenger, f.eks: [\"variant 1...\", \"variant 2...\", \"variant 3...\"] uten annen tekst eller markdown.";

		$user_prompt = sprintf(
			"PRODUKTINFORMASJON:\n- Navn: %s\n- Pris: %s %s\n- Forhandler: %s\n- Beskrivelse: %s\n\nBRUKERENS QUIZ-SVAR OG BEHOV:\n%s\n\nGenerer nå et JSON-array med 3 til 5 unike anbefalingstekster skreddersydd for denne brukeren.",
			$product['title'],
			$product['price'],
			$product['currency'],
			$product['merchant'],
			$product['excerpt'],
			$context_str
		);

		$request_body = array(
			'contents'         => array(
				array(
					'role'  => 'user',
					'parts' => array(
						array( 'text' => $system_instructions . "\n\n" . $user_prompt ),
					),
				),
			),
			'generationConfig' => array(
				'temperature'      => 0.7,
				'topP'             => 0.9,
				'maxOutputTokens'  => 800,
				'responseMimeType' => 'application/json',
			),
		);

		$response = wp_remote_post(
			$endpoint,
			array(
				'headers' => array(
					'Content-Type' => 'application/json',
				),
				'body'    => wp_json_encode( $request_body ),
				'timeout' => 20,
			)
		);

		if ( is_wp_error( $response ) ) {
			return array();
		}

		$status_code = wp_remote_retrieve_response_code( $response );
		if ( 200 !== $status_code ) {
			return array();
		}

		$body = wp_remote_retrieve_body( $response );
		$data = json_decode( $body, true );

		if ( ! is_array( $data ) || empty( $data['candidates'][0]['content']['parts'][0]['text'] ) ) {
			return array();
		}

		$raw_text = trim( (string) $data['candidates'][0]['content']['parts'][0]['text'] );

		// Clean up potential markdown formatting fences if returned.
		$raw_text = preg_replace( '/^```(?:json)?\s*/i', '', $raw_text );
		$raw_text = preg_replace( '/\s*```$/', '', $raw_text );

		$decoded = json_decode( $raw_text, true );

		if ( is_array( $decoded ) && ! empty( $decoded ) ) {
			$valid_strings = array();
			foreach ( $decoded as $entry ) {
				if ( is_string( $entry ) && '' !== trim( $entry ) ) {
					$valid_strings[] = sanitize_text_field( trim( $entry ) );
				}
			}

			if ( count( $valid_strings ) >= 2 ) {
				return $valid_strings;
			}
		}

		return array();
	}

	/**
	 * Generate fallback variations when API key is missing or service is unreachable.
	 *
	 * Ensures the frontend Utstyrsvelger quiz never experiences a broken UI.
	 *
	 * @param array<string, mixed> $product     Product data.
	 * @param string               $context_str Quiz context.
	 * @return string[]
	 */
	public function generate_fallback_variations( array $product, string $context_str ): array {
		$title    = $product['title'];
		$merchant = $product['merchant'] ?: __( 'våre partnere', 'gear-engine' );

		return array(
			sprintf(
				/* translators: 1: Product title, 2: Merchant name */
				__( 'Perfekt match for dine treningsmål. %1$s kombinerer førsteklasses stabilitet, stillegående drift og høy byggekvalitet fra %2$s.', 'gear-engine' ),
				$title,
				$merchant
			),
			sprintf(
				/* translators: 1: Product title */
				__( 'Basert på dine preferanser er %1$s et enestående valg. Du får ergonomiske løsninger og slitesterke komponenter som motiverer til jevnlige økter hjemme.', 'gear-engine' ),
				$title
			),
			sprintf(
				/* translators: 1: Product title, 2: Merchant name */
				__( 'Med %1$s investerer du i solid treningsutstyr levert av %2$s, skreddersydd for å gi maksimal effekt innenfor dine oppgitte rammer.', 'gear-engine' ),
				$title,
				$merchant
			),
			sprintf(
				/* translators: 1: Product title */
				__( '%1$s gir deg den ideelle balansen mellom ytelse, komfort og holdbarhet. Et trygt og gjennomtestet valg for treningsrommet.', 'gear-engine' ),
				$title
			),
		);
	}

	/**
	 * Handle catalog search/filtering endpoint for Utstyrsvelger quiz.
	 *
	 * @param \WP_REST_Request $request Request.
	 * @return \WP_REST_Response
	 */
	public function handle_gear_catalog_request( \WP_REST_Request $request ): \WP_REST_Response {
		$category  = sanitize_text_field( (string) $request->get_param( 'category' ) );
		$min_price = floatval( $request->get_param( 'min_price' ) );
		$max_price = floatval( $request->get_param( 'max_price' ) );
		$in_stock  = $request->get_param( 'in_stock' );
		$limit     = min( 50, max( 1, absint( $request->get_param( 'limit' ) ?: 12 ) ) );

		$meta_query = array( 'relation' => 'AND' );

		if ( $min_price > 0 ) {
			$meta_query[] = array(
				'key'     => GAE_CPT::META_PRICE,
				'value'   => $min_price,
				'type'    => 'NUMERIC',
				'compare' => '>=',
			);
		}

		if ( $max_price > 0 ) {
			$meta_query[] = array(
				'key'     => GAE_CPT::META_PRICE,
				'value'   => $max_price,
				'type'    => 'NUMERIC',
				'compare' => '<=',
			);
		}

		if ( null !== $in_stock ) {
			$meta_query[] = array(
				'key'     => GAE_CPT::META_IN_STOCK,
				'value'   => rest_sanitize_boolean( $in_stock ) ? 1 : 0,
				'compare' => '=',
			);
		}

		$query_args = array(
			'post_type'      => GAE_CPT::POST_TYPE,
			'post_status'    => 'publish',
			'posts_per_page' => $limit,
			'orderby'        => 'meta_value_num',
			'meta_key'       => GAE_CPT::META_PRICE,
			'order'          => 'ASC',
		);

		if ( count( $meta_query ) > 1 ) {
			$query_args['meta_query'] = $meta_query;
		}

		if ( ! empty( $category ) ) {
			$query_args['tax_query'] = array(
				array(
					'taxonomy' => GAE_CPT::TAXONOMY,
					'field'    => 'name',
					'terms'    => $category,
				),
			);
		}

		$query = new \WP_Query( $query_args );
		$items = array();

		foreach ( $query->posts as $post ) {
			$data = GAE_CPT::get_product_data( $post );
			if ( $data ) {
				$items[] = $data;
			}
		}

		return new \WP_REST_Response(
			array(
				'success' => true,
				'total'   => $query->found_posts,
				'items'   => $items,
			),
			200
		);
	}
}
