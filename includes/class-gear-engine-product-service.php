<?php
/**
 * Shared Product Search & RAG Retrieval Service.
 *
 * Provides high-performance querying and relevance ranking of `exercise_gear` CPT
 * products for AI modules (Physio Chat, Gear Picker Quiz, and Triage).
 *
 * @package GearEngine
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

/**
 * Class Gear_Engine_Product_Service
 */
class Gear_Engine_Product_Service {

	/**
	 * Shared read path for raw gear posts.
	 *
	 * Feature modules that need full post objects (title, content, meta) must
	 * fetch them through this method instead of issuing their own queries, so
	 * published-only rules and post-type scope stay defined in one place.
	 *
	 * @param array $args {
	 *     Optional. Query criteria.
	 *     @type int   $posts_per_page Maximum posts to return (default 1000, capped at 2000).
	 *     @type array $category       gear_category term slugs to restrict the query to.
	 * }
	 * @return \WP_Post[] Published gear posts.
	 */
	public static function get_raw_posts( array $args = array() ): array {
		$posts_per_page = isset( $args['posts_per_page'] ) ? max( 1, min( 2000, absint( $args['posts_per_page'] ) ) ) : 1000;
		$category       = isset( $args['category'] ) && is_array( $args['category'] ) ? array_values( array_filter( array_map( 'sanitize_title', $args['category'] ) ) ) : array();

		$query_args = array(
			'post_type'              => Gear_Engine_CPT::POST_TYPE,
			'post_status'            => 'publish',
			'posts_per_page'         => $posts_per_page,
			'no_found_rows'          => true,
			'update_post_term_cache' => true,
			'update_post_meta_cache' => true,
		);

		if ( ! empty( $category ) ) {
			$query_args['tax_query'] = array(
				array(
					'taxonomy' => Gear_Engine_CPT::TAXONOMY,
					'field'    => 'slug',
					'terms'    => $category,
				),
			);
		}

		$query = new WP_Query( $query_args );

		return is_array( $query->posts ) ? $query->posts : array();
	}

	/**
	 * Search and rank products from the exercise_gear CPT.
	 *
	 * @param array $args {
	 *     Optional. Search criteria.
	 *     @type array|string $keywords     Search terms or keyword phrase.
	 *     @type float        $min_price    Minimum price in NOK.
	 *     @type float        $max_price    Maximum price in NOK.
	 *     @type int          $limit        Maximum products to return (default 6).
	 *     @type string       $sort         Sort order: 'score', 'price_asc', 'price_desc'.
	 *     @type array        $exclude_urls URLs to deprioritize or exclude (already shown).
	 *     @type bool         $in_stock_only Whether to require in-stock products.
	 *     @type string       $category     Specific gear category term slug.
	 * }
	 * @return array List of normalized product card arrays.
	 */
	public static function search_products( array $args = array() ): array {
		$raw_keywords = $args['keywords'] ?? array();
		if ( is_string( $raw_keywords ) ) {
			$raw_keywords = explode( ' ', $raw_keywords );
		}

		$keywords = array();
		foreach ( (array) $raw_keywords as $kw ) {
			$trimmed = trim( (string) $kw );
			if ( mb_strlen( $trimmed, 'UTF-8' ) >= 2 ) {
				$keywords[] = mb_strtolower( $trimmed, 'UTF-8' );
			}
		}

		$min_price     = isset( $args['min_price'] ) ? (float) $args['min_price'] : 0.0;
		$max_price     = isset( $args['max_price'] ) ? (float) $args['max_price'] : 0.0;
		$limit         = isset( $args['limit'] ) ? max( 1, min( 20, (int) $args['limit'] ) ) : 6;
		$sort          = isset( $args['sort'] ) ? (string) $args['sort'] : 'score';
		$exclude_urls  = isset( $args['exclude_urls'] ) && is_array( $args['exclude_urls'] ) ? $args['exclude_urls'] : array();
		$in_stock_only = ! isset( $args['in_stock_only'] ) || (bool) $args['in_stock_only'];
		$category      = ! empty( $args['category'] ) ? sanitize_text_field( $args['category'] ) : '';

		// Build meta query.
		$meta_query = array( 'relation' => 'AND' );

		if ( $min_price > 0 ) {
			$meta_query[] = array(
				'key'     => Gear_Engine_CPT::META_PRICE,
				'value'   => $min_price,
				'type'    => 'NUMERIC',
				'compare' => '>=',
			);
		}

		if ( $max_price > 0 ) {
			$meta_query[] = array(
				'key'     => Gear_Engine_CPT::META_PRICE,
				'value'   => $max_price,
				'type'    => 'NUMERIC',
				'compare' => '<=',
			);
		}

		if ( $in_stock_only ) {
			$meta_query[] = array(
				'key'     => Gear_Engine_CPT::META_IN_STOCK,
				'value'   => 1,
				'compare' => '=',
			);
		}

		$query_args = array(
			'post_type'              => Gear_Engine_CPT::POST_TYPE,
			'post_status'            => 'publish',
			'posts_per_page'         => 50,
			'no_found_rows'          => true,
			'update_post_term_cache' => false,
		);

		if ( count( $meta_query ) > 1 ) {
			$query_args['meta_query'] = $meta_query;
		}

		if ( ! empty( $category ) ) {
			$query_args['tax_query'] = array(
				array(
					'taxonomy' => Gear_Engine_CPT::TAXONOMY,
					'field'    => 'slug',
					'terms'    => $category,
				),
			);
		}

		$query = new WP_Query( $query_args );
		$matched = array();

		// If no posts in DB, fall back to seeded sample products for development/testing.
		if ( ! $query->have_posts() ) {
			$samples = Gear_Engine_CPT::get_sample_products_definitions();
			foreach ( $samples as $sample ) {
				$matched[] = self::score_and_format_item( $sample, $keywords, $exclude_urls, $min_price, $max_price );
			}
		} else {
			foreach ( $query->posts as $post ) {
				$item_data = Gear_Engine_CPT::get_product_data( $post );
				if ( ! empty( $item_data ) ) {
					$matched[] = self::score_and_format_item( $item_data, $keywords, $exclude_urls, $min_price, $max_price );
				}
			}
		}

		// Filter out items that failed hard criteria.
		$matched = array_filter( $matched, function( $item ) {
			return null !== $item && ( $item['score'] > -1000 );
		} );

		// Sort results according to requested intent.
		usort( $matched, function( $a, $b ) use ( $sort ) {
			if ( 'price_asc' === $sort ) {
				return ( $a['raw_price'] <=> $b['raw_price'] ) ?: ( $b['score'] <=> $a['score'] );
			}
			if ( 'price_desc' === $sort ) {
				return ( $b['raw_price'] <=> $a['raw_price'] ) ?: ( $b['score'] <=> $a['score'] );
			}
			return $b['score'] <=> $a['score'];
		} );

		// Slice to limit.
		$top = array_slice( $matched, 0, $limit );

		// Return clean card format for front-end templates.
		return array_map( function( $item ) {
			return array(
				'id'          => $item['id'] ?? 0,
				'tittel'      => $item['tittel'],
				'butikk'      => $item['butikk'],
				'pris'        => $item['pris'],
				'forpris'     => $item['forpris'] ?? '',
				'url'         => $item['url'],
				'bilde_url'   => $item['bilde_url'],
				'beskrivelse' => $item['beskrivelse'] ?? '',
			);
		}, $top );
	}

	/**
	 * Score an individual product item based on keyword matches, discounts, and exclusions.
	 *
	 * @param array $item         Normalized product data from CPT.
	 * @param array $keywords     Array of normalized lowercase search terms.
	 * @param array $exclude_urls List of URLs already displayed to avoid repeats.
	 * @param float $min_price    Min price filter.
	 * @param float $max_price    Max price filter.
	 * @return array|null Scored product item or null if filtered out.
	 */
	private static function score_and_format_item( array $item, array $keywords, array $exclude_urls, float $min_price, float $max_price ): ?array {
		$raw_price   = isset( $item['price'] ) && is_numeric( $item['price'] ) ? (float) $item['price'] : (float) ( $item['raw_price'] ?? 0 );
		$raw_regular = isset( $item['regular_price'] ) && is_numeric( $item['regular_price'] ) ? (float) $item['regular_price'] : (float) ( $item['raw_regular_price'] ?? 0 );

		if ( $min_price > 0 && $raw_price < $min_price ) {
			return null;
		}
		if ( $max_price > 0 && $raw_price > $max_price ) {
			return null;
		}

		$title = mb_strtolower( (string) ( $item['title'] ?? '' ), 'UTF-8' );
		$desc  = mb_strtolower( (string) ( $item['excerpt'] ?? '' ), 'UTF-8' );
		$url   = (string) ( $item['affiliate_url'] ?? '#' );

		$score = 100.0;
		$match_count = 0;
		$full_phrase = implode( ' ', $keywords );

		if ( ! empty( $full_phrase ) && mb_strlen( $full_phrase, 'UTF-8' ) >= 3 ) {
			if ( false !== mb_strpos( $title, $full_phrase ) ) {
				$score += 5000;
				$match_count++;
			}
		}

		foreach ( $keywords as $kw ) {
			if ( mb_strlen( $kw, 'UTF-8' ) < 2 ) {
				continue;
			}
			if ( false !== mb_strpos( $title, $kw ) ) {
				$score += 400;
				$match_count++;
			}
			if ( false !== mb_strpos( $desc, $kw ) ) {
				$score += 60;
				$match_count++;
			}
		}

		// Reward products on sale.
		if ( $raw_regular > $raw_price && $raw_price > 0 ) {
			$discount_pct = ( ( $raw_regular - $raw_price ) / $raw_regular ) * 100;
			$score += $discount_pct * 2;
		}

		// Penalize already shown URLs to keep recommendations fresh.
		if ( in_array( trim( $url ), $exclude_urls, true ) ) {
			$score -= 3000;
		}

		$formatted_price = ! empty( $item['formatted_price'] ) ? $item['formatted_price'] : ( $raw_price > 0 ? number_format( $raw_price, 0, ',', ' ' ) . ' kr' : 'Sjekk pris' );
		$formatted_reg   = ! empty( $item['formatted_regular'] ) ? $item['formatted_regular'] : ( $raw_regular > $raw_price ? number_format( $raw_regular, 0, ',', ' ' ) . ' kr' : '' );

		return array(
			'id'          => $item['id'] ?? 0,
			'tittel'      => $item['title'] ?? 'Treningsutstyr',
			'butikk'      => ! empty( $item['merchant'] ) ? $item['merchant'] : 'Nettbutikk',
			'pris'        => $formatted_price,
			'forpris'     => $formatted_reg,
			'url'         => $url,
			'bilde_url'   => ! empty( $item['image_url'] ) ? $item['image_url'] : 'https://placehold.co/600x450/1e293b/ffffff?text=Bilde+mangler',
			'beskrivelse' => ! empty( $item['excerpt'] ) ? wp_strip_all_tags( $item['excerpt'] ) : '',
			'raw_price'   => $raw_price,
			'score'       => $score,
		);
	}
}


// Deprecated alias preserved for backwards compatibility.
if ( ! class_exists( 'GAE_Product_Service', false ) ) {
	class_alias( 'Gear_Engine_Product_Service', 'GAE_Product_Service' );
}

