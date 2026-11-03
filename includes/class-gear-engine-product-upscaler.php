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
			$this->assign_category( $post_id, (string) ( $item['category'] ?? '' ), (string) ( $item['title'] ?? '' ) );

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
		$this->assign_category( $new_post_id, (string) ( $item['category'] ?? '' ), (string) ( $item['title'] ?? '' ) );

		return 'inserted';
	}

	/**
	 * Assign category term to the exercise gear post.
	 *
	 * Implements backward-compatible multi-tagging and manual lock (A + C):
	 * - Manual lock: Checks '_gear_engine_lock_categories' meta. If locked, terms are not touched.
	 * - Keyword mapping: Maps keywords in title/category to standardized categories.
	 * - Non-destructive fallback: Uses leaf category from feed if no keywords match.
	 *
	 * @param int    $post_id  Post ID.
	 * @param string $category Raw category string from feed.
	 * @param string $title    Product title.
	 * @return void
	 */
	protected function assign_category( int $post_id, string $category, string $title = '' ): void {
		// 1. Manual Lock check (Rule C).
		$is_locked = (bool) get_post_meta( $post_id, Gear_Engine_CPT::META_LOCK_CATEGORIES, true );
		if ( $is_locked ) {
			return;
		}

		if ( empty( $category ) && empty( $title ) ) {
			return;
		}

		static $term_cache = array();

		$target_categories = array();
		$search_text       = mb_strtolower( $category . ' ' . $title, 'UTF-8' );

		// 2. Keyword Mapping (Rule A).
		if ( preg_match( '/tredem[øo]lle|treadmill/ui', $search_text ) ) {
			$target_categories[] = 'Tredemøller';
		}
		if ( preg_match( '/romaskin|rowing\s*machine|rowerg/ui', $search_text ) ) {
			$target_categories[] = 'Romaskiner';
		}
		if ( preg_match( '/spinningsykkel|ergometersykkel|treningssykkel|motionscykel/ui', $search_text ) ) {
			$target_categories[] = 'Treningssykler';
		}
		if ( preg_match( '/ellipsemaskin|crosstrainer/ui', $search_text ) ) {
			$target_categories[] = 'Ellipsemaskiner';
		}
		if ( preg_match( '/manual|manualer|dumbbell|dumbell|hantel/ui', $search_text ) ) {
			$target_categories[] = 'Manualer';
		}
		if ( preg_match( '/vektstang|vektstenger|barbell|olympiastang/ui', $search_text ) ) {
			$target_categories[] = 'Vektstenger';
		}
		if ( preg_match( '/kettlebell/ui', $search_text ) ) {
			$target_categories[] = 'Kettlebells';
		}
		if ( preg_match( '/power\s*rack|squat\s*rack|kneb[øo]ystativ|treningsstativ|vektstativ/ui', $search_text ) ) {
			$target_categories[] = 'Racks og stativer';
		}
		if ( preg_match( '/knest[øo]tte|genutrain|kneskinne/ui', $search_text ) ) {
			$target_categories[] = 'Knestøtter';
		}
		if ( preg_match( '/ankelst[øo]tte|malleotrain/ui', $search_text ) ) {
			$target_categories[] = 'Ankelstøtter';
		}
		if ( preg_match( '/ryggbelte|l[øo]ftebelte|lumbal/ui', $search_text ) ) {
			$target_categories[] = 'Ryggbelter';
		}
		if ( preg_match( '/treningsstrikk|strikk|powerband|miniband|resistance\s*band/ui', $search_text ) ) {
			$target_categories[] = 'Treningsstrikker';
		}
		if ( preg_match( '/massasjepistol|theragun|hypervolt|massage\s*gun/ui', $search_text ) ) {
			$target_categories[] = 'Massasjepistoler';
		}
		if ( preg_match( '/foam\s*roller|foamroller|massasjerulle/ui', $search_text ) ) {
			$target_categories[] = 'Foam Rollers';
		}
		if ( preg_match( '/tens|elektrisk\s*stimulering/ui', $search_text ) ) {
			$target_categories[] = 'TENS-apparater';
		}

		// 3. Non-destructive fallback if no keywords matched.
		if ( empty( $target_categories ) && ! empty( $category ) ) {
			$parts     = explode( '>', $category );
			$leaf_name = sanitize_text_field( trim( end( $parts ) ) );
			if ( ! empty( $leaf_name ) ) {
				$target_categories[] = $leaf_name;
			}
		}

		if ( empty( $target_categories ) ) {
			return;
		}

		$term_ids = array();
		foreach ( $target_categories as $cat_name ) {
			if ( isset( $term_cache[ $cat_name ] ) ) {
				$term_ids[] = $term_cache[ $cat_name ];
			} else {
				$term = term_exists( $cat_name, Gear_Engine_CPT::TAXONOMY );
				if ( ! $term ) {
					$term = wp_insert_term( $cat_name, Gear_Engine_CPT::TAXONOMY );
				}
				$term_id = ( ! is_wp_error( $term ) && isset( $term['term_id'] ) ) ? (int) $term['term_id'] : 0;
				if ( $term_id > 0 ) {
					$term_cache[ $cat_name ] = $term_id;
					$term_ids[]              = $term_id;
				}
			}
		}

		if ( ! empty( $term_ids ) ) {
			wp_set_object_terms( $post_id, $term_ids, Gear_Engine_CPT::TAXONOMY, false );
		}
	}
}
