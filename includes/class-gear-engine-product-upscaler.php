<?php
/**
 * Product persistence for Gear Engine.
 *
 * Owns all writes into the `exercise_gear` CPT: EAN-keyed deduplication,
 * insert/update of posts and affiliate meta, and category term assignment.
 *
 * @package GearEngine
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

/**
 * Class Gear_Engine_Product_Upscaler
 */
class Gear_Engine_Product_Upscaler {

	/**
	 * Insert new post or update existing post based on EAN deduplication key.
	 *
	 * @param array<string, mixed> $item               Product data.
	 * @param bool                 $prevent_new_insert Whether to prevent inserting if catalog is capped.
	 * @return string 'inserted', 'updated', 'catalog_capped', or 'failed'
	 */
	public function upsert( array $item, bool $prevent_new_insert = false ): string {
		global $wpdb;

		$ean = (string) $item['ean'];

		// Fast indexed query by EAN meta value.
		$existing_post_id = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT post_id FROM {$wpdb->postmeta} WHERE meta_key = %s AND meta_value = %s LIMIT 1",
				Gear_Engine_CPT::META_EAN,
				$ean
			)
		);

		$now = current_time( 'mysql' );

		if ( $existing_post_id ) {
			$post_id = (int) $existing_post_id;

			// Update post title/content if feed provided updated data.
			$update_args = array(
				'ID'         => $post_id,
				'post_title' => wp_strip_all_tags( $item['title'] ),
			);
			if ( ! empty( $item['description'] ) ) {
				$update_args['post_content'] = wp_kses_post( $item['description'] );
			}
			wp_update_post( $update_args );

			// Update all meta values.
			update_post_meta( $post_id, Gear_Engine_CPT::META_PRICE, (float) $item['price'] );
			update_post_meta( $post_id, Gear_Engine_CPT::META_REGULAR_PRICE, (float) $item['regular_price'] );
			update_post_meta( $post_id, Gear_Engine_CPT::META_IN_STOCK, $item['in_stock'] ? 1 : 0 );
			update_post_meta( $post_id, Gear_Engine_CPT::META_AFFILIATE_URL, esc_url_raw( $item['affiliate_url'] ) );
			update_post_meta( $post_id, Gear_Engine_CPT::META_MERCHANT, sanitize_text_field( $item['merchant'] ) );
			update_post_meta( $post_id, Gear_Engine_CPT::META_CURRENCY, sanitize_text_field( $item['currency'] ) );
			update_post_meta( $post_id, Gear_Engine_CPT::META_LAST_SYNCED, $now );

			if ( ! empty( $item['image_url'] ) ) {
				update_post_meta( $post_id, Gear_Engine_CPT::META_IMAGE_URL, esc_url_raw( $item['image_url'] ) );
			}

			// Update taxonomy if category given.
			$this->assign_category( $post_id, (string) ( $item['category'] ?? '' ) );

			return 'updated';
		}

		// If catalog cap is reached and this is a new product, block new insert.
		if ( $prevent_new_insert ) {
			return 'catalog_capped';
		}

		// Insert new post.
		$post_args = array(
			'post_type'    => Gear_Engine_CPT::POST_TYPE,
			'post_status'  => 'publish',
			'post_title'   => wp_strip_all_tags( $item['title'] ),
			'post_content' => wp_kses_post( $item['description'] ),
			'post_excerpt' => wp_strip_all_tags( wp_trim_words( $item['description'], 35 ) ),
		);

		$new_post_id = wp_insert_post( $post_args );
		if ( is_wp_error( $new_post_id ) || 0 === $new_post_id ) {
			return 'failed';
		}

		// Populate all affiliate meta fields.
		update_post_meta( $new_post_id, Gear_Engine_CPT::META_EAN, sanitize_text_field( $ean ) );
		update_post_meta( $new_post_id, Gear_Engine_CPT::META_PRICE, (float) $item['price'] );
		update_post_meta( $new_post_id, Gear_Engine_CPT::META_REGULAR_PRICE, (float) $item['regular_price'] );
		update_post_meta( $new_post_id, Gear_Engine_CPT::META_IN_STOCK, $item['in_stock'] ? 1 : 0 );
		update_post_meta( $new_post_id, Gear_Engine_CPT::META_AFFILIATE_URL, esc_url_raw( $item['affiliate_url'] ) );
		update_post_meta( $new_post_id, Gear_Engine_CPT::META_MERCHANT, sanitize_text_field( $item['merchant'] ) );
		update_post_meta( $new_post_id, Gear_Engine_CPT::META_IMAGE_URL, esc_url_raw( $item['image_url'] ) );
		update_post_meta( $new_post_id, Gear_Engine_CPT::META_CURRENCY, sanitize_text_field( $item['currency'] ) );
		update_post_meta( $new_post_id, Gear_Engine_CPT::META_LAST_SYNCED, $now );

		// Assign taxonomy term.
		$this->assign_category( $new_post_id, (string) ( $item['category'] ?? '' ) );

		return 'inserted';
	}

	/**
	 * Assign category term to the exercise gear post.
	 *
	 * @param int    $post_id  Post ID.
	 * @param string $category Raw category string from feed.
	 * @return void
	 */
	protected function assign_category( int $post_id, string $category ): void {
		if ( empty( $category ) ) {
			return;
		}

		static $term_cache = array();

		// Clean up category string (e.g. take final segment if breadcrumb like "Sport > Gym > Tredemølle").
		$parts     = explode( '>', $category );
		$leaf_name = sanitize_text_field( trim( end( $parts ) ) );

		if ( empty( $leaf_name ) ) {
			return;
		}

		if ( isset( $term_cache[ $leaf_name ] ) ) {
			$term_id = $term_cache[ $leaf_name ];
		} else {
			$term = term_exists( $leaf_name, Gear_Engine_CPT::TAXONOMY );
			if ( ! $term ) {
				$term = wp_insert_term( $leaf_name, Gear_Engine_CPT::TAXONOMY );
			}
			$term_id = ( ! is_wp_error( $term ) && isset( $term['term_id'] ) ) ? (int) $term['term_id'] : 0;
			$term_cache[ $leaf_name ] = $term_id;
		}

		if ( $term_id > 0 ) {
			wp_set_object_terms( $post_id, $term_id, Gear_Engine_CPT::TAXONOMY, false );
		}
	}
}
