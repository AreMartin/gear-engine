<?php
/**
 * Shared query service for curated exercise content.
 *
 * @package GearEngine
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Read-only exercise access for chat and future feature modules.
 */
class Gear_Engine_Exercise_Service {

	/**
	 * Build an official ExorLive player URL from structured exercise metadata.
	 *
	 * @param mixed  $exercise_id ExorLive exercise ID.
	 * @param mixed  $culture     Player locale.
	 * @return string
	 */
	public static function build_exorlive_video_url( $exercise_id, $culture = 'nb-NO' ): string {
		if ( ! is_scalar( $exercise_id ) || ! preg_match( '/^\d+$/', trim( (string) $exercise_id ) ) ) {
			return '';
		}

		$exercise_id = trim( (string) $exercise_id );
		$culture     = is_scalar( $culture ) ? trim( (string) $culture ) : '';
		$culture     = preg_match( '/^[a-z]{2}-[A-Z]{2}$/', $culture ) ? $culture : 'nb-NO';

		return sprintf(
			'https://exorlive.com/video/?culture=%s&ex=%s',
			rawurlencode( $culture ),
			rawurlencode( $exercise_id )
		);
	}

	/**
	 * Search published curated exercises.
	 *
	 * Callers may request larger result sets for non-program features. A plan
	 * builder should explicitly request no more than five exercises.
	 *
	 * @param array<string, mixed> $args Search options: keywords, area, equipment, protocol (slug), max_risk (low|moderate|high), limit.
	 * @return array<int, array<string, mixed>>
	 */
	public static function search_exercises( array $args = array() ): array {
		$raw_limit = $args['limit'] ?? 5;
		$limit     = is_scalar( $raw_limit ) ? max( 1, min( 50, absint( $raw_limit ) ) ) : 5;
		$query_args = array(
			'post_type'              => Gear_Engine_Exercises_CPT::GEAR_ENGINE_POST_TYPE,
			'post_status'            => 'publish',
			'posts_per_page'         => $limit,
			'no_found_rows'          => true,
			'orderby'                => array(
				'menu_order' => 'ASC',
				'title'      => 'ASC',
			),
		);

		if ( isset( $args['keywords'] ) && is_scalar( $args['keywords'] ) && '' !== (string) $args['keywords'] ) {
			$query_args['s'] = sanitize_text_field( (string) $args['keywords'] );
		}

		$tax_query = array( 'relation' => 'AND' );
		if ( isset( $args['area'] ) && is_scalar( $args['area'] ) && '' !== (string) $args['area'] ) {
			$tax_query[] = array(
				'taxonomy' => Gear_Engine_Exercises_CPT::GEAR_ENGINE_AREA_TAXONOMY,
				'field'    => 'slug',
				'terms'    => sanitize_title( (string) $args['area'] ),
			);
		}
		if ( isset( $args['equipment'] ) && is_scalar( $args['equipment'] ) && '' !== (string) $args['equipment'] ) {
			$tax_query[] = array(
				'taxonomy' => Gear_Engine_Exercises_CPT::GEAR_ENGINE_EQUIPMENT_TAXONOMY,
				'field'    => 'slug',
				'terms'    => sanitize_title( (string) $args['equipment'] ),
			);
		}
		if ( isset( $args['protocol'] ) && is_scalar( $args['protocol'] ) && '' !== (string) $args['protocol'] ) {
			$tax_query[] = array(
				'taxonomy'         => Gear_Engine_Protocols::GEAR_ENGINE_TAXONOMY,
				'field'            => 'slug',
				'terms'            => sanitize_title( (string) $args['protocol'] ),
				'include_children' => true,
			);
		}
		if ( count( $tax_query ) > 1 ) {
			$query_args['tax_query'] = $tax_query;
		}

		// Unrated exercises are excluded whenever a maximum risk level is requested.
		if ( isset( $args['max_risk'] ) && is_scalar( $args['max_risk'] ) && '' !== (string) $args['max_risk'] ) {
			$levels  = array( 'low', 'moderate', 'high' );
			$max_idx = array_search( strtolower( (string) $args['max_risk'] ), $levels, true );
			$allowed = false === $max_idx ? array( 'low' ) : array_slice( $levels, 0, $max_idx + 1 );
			$query_args['meta_query'] = array(
				array(
					'key'     => Gear_Engine_Exercises_CPT::GEAR_ENGINE_META_RISK,
					'value'   => $allowed,
					'compare' => 'IN',
				),
			);
		}

		$query = new WP_Query( $query_args );
		$exercises = array();
		foreach ( $query->posts as $post ) {
			// WP_Query enforces the post type in WordPress; retain this guard for
			// alternate query implementations and lightweight test environments.
			if ( isset( $post->post_type ) && Gear_Engine_Exercises_CPT::GEAR_ENGINE_POST_TYPE !== $post->post_type ) {
				continue;
			}
			$exercise = self::get_exercise_data( $post );
			if ( $exercise ) {
				$exercises[] = $exercise;
			}
		}

		return array_slice( $exercises, 0, $limit );
	}

	/**
	 * Return a normalized exercise record for integrations.
	 *
	 * @param int|WP_Post $post Exercise ID or post.
	 * @return array<string, mixed>|null
	 */
	public static function get_exercise_data( $post ): ?array {
		if ( is_numeric( $post ) ) {
			$post = get_post( absint( $post ) );
		}
		if ( ! $post instanceof WP_Post || Gear_Engine_Exercises_CPT::GEAR_ENGINE_POST_TYPE !== $post->post_type ) {
			return null;
		}

		$raw_exorlive_id = get_post_meta( $post->ID, Gear_Engine_Exercises_CPT::GEAR_ENGINE_META_EXORLIVE_ID, true );
		$raw_culture     = get_post_meta( $post->ID, Gear_Engine_Exercises_CPT::GEAR_ENGINE_META_VIDEO_CULTURE, true );
		$raw_video_url   = get_post_meta( $post->ID, Gear_Engine_Exercises_CPT::GEAR_ENGINE_META_VIDEO_URL, true );
		$exorlive_id     = is_scalar( $raw_exorlive_id ) ? trim( (string) $raw_exorlive_id ) : '';
		$culture         = is_scalar( $raw_culture ) ? trim( (string) $raw_culture ) : '';
		$culture     = preg_match( '/^[a-z]{2}-[A-Z]{2}$/', $culture ) ? $culture : 'nb-NO';
		$video_url      = Gear_Engine_Exercises_CPT::sanitize_video_url_static( $raw_video_url );

		if ( '' !== $video_url ) {
			$video_parts = wp_parse_url( $video_url );
			if ( is_array( $video_parts ) && in_array( strtolower( (string) ( $video_parts['host'] ?? '' ) ), array( 'exorlive.com', 'www.exorlive.com' ), true ) ) {
				$query = array();
				parse_str( (string) ( $video_parts['query'] ?? '' ), $query );
				$exorlive_id = Gear_Engine_Exercises_CPT::sanitize_exorlive_id_static( $query['ex'] ?? '' );
				$culture     = Gear_Engine_Exercises_CPT::sanitize_video_culture_static( $query['culture'] ?? $culture );
			} else {
				$exorlive_id = '';
			}
		} else {
			$video_url = self::build_exorlive_video_url( $exorlive_id, $culture );
		}

		$area      = self::get_term_names( $post->ID, Gear_Engine_Exercises_CPT::GEAR_ENGINE_AREA_TAXONOMY );
		$equipment = self::get_term_names( $post->ID, Gear_Engine_Exercises_CPT::GEAR_ENGINE_EQUIPMENT_TAXONOMY );

		return array(
			'id'               => (int) $post->ID,
			'title'            => (string) $post->post_title,
			'description'      => wp_strip_all_tags( (string) $post->post_content ),
			'summary'          => wp_strip_all_tags( (string) $post->post_excerpt ),
			'dosage'           => (string) get_post_meta( $post->ID, Gear_Engine_Exercises_CPT::GEAR_ENGINE_META_DOSAGE, true ),
			'area'             => $area,
			'equipment'        => $equipment,
			'risk'             => Gear_Engine_Exercises_CPT::sanitize_risk( get_post_meta( $post->ID, Gear_Engine_Exercises_CPT::GEAR_ENGINE_META_RISK, true ) ),
			'video_culture'    => $culture,
			'exorlive_id'      => $video_url ? $exorlive_id : '',
			'video_url'        => $video_url,
			'attribution_text' => $exorlive_id && $video_url ? sprintf( '%s - ExorLive', $post->post_title ) : '',
			'attribution_url'  => $exorlive_id && $video_url ? 'https://www.exorlive.com/no/ovelser-i-exorlive' : '',
			'source_url'       => (string) get_post_meta( $post->ID, Gear_Engine_Exercises_CPT::GEAR_ENGINE_META_SOURCE_URL, true ),
		);
	}

	/**
	 * Retrieve assigned taxonomy labels without requiring them in test doubles.
	 *
	 * @param int    $post_id  Post ID.
	 * @param string $taxonomy Taxonomy name.
	 * @return array<int, string>
	 */
	private static function get_term_names( int $post_id, string $taxonomy ): array {
		if ( ! function_exists( 'wp_get_post_terms' ) ) {
			return array();
		}
		$terms = wp_get_post_terms( $post_id, $taxonomy, array( 'fields' => 'names' ) );
		return is_wp_error( $terms ) || ! is_array( $terms ) ? array() : $terms;
	}
}
