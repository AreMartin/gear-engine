<?php
/**
 * Streaming Sync Engine Module.
 *
 * @package GearEngine
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

/**
 * Class GAE_Sync_Engine
 *
 * Implements high-volume streaming XML feed parsing via XMLReader with pre-filtering,
 * EAN deduplication, batch database upserts, and daily WP-Cron scheduling.
 */
class GAE_Sync_Engine {

	/**
	 * Daily cron hook name.
	 */
	public const CRON_HOOK = 'gae_daily_sync_event';

	/**
	 * Lock transient key to prevent concurrent syncs.
	 */
	public const LOCK_KEY = 'gae_sync_running_lock';

	/**
	 * Batch size for periodic memory cleanup.
	 */
	public const BATCH_SIZE = 100;

	/**
	 * Constructor.
	 */
	public function __construct() {
		// Hook WP-Cron scheduled task.
		add_action( self::CRON_HOOK, array( $this, 'run_sync' ) );
	}

	/**
	 * Main sync coordinator.
	 *
	 * Downloads and streams all configured feeds (Adtraction, TradeTracker) in batches.
	 *
	 * @param array<string, mixed> $override_feeds Optional feed URLs for manual/test runs.
	 * @return array<string, mixed> Detailed execution report.
	 */
	public function run_sync( array $override_feeds = array() ): array {
		// Acquire concurrency lock (2-hour TTL).
		if ( ! $this->acquire_lock() ) {
			return array(
				'status'  => 'locked',
				'message' => __( 'A sync process is already currently running.', 'gear-engine' ),
			);
		}

		// Increase runtime execution bounds if permitted.
		if ( ! ini_get( 'safe_mode' ) && function_exists( 'set_time_limit' ) ) {
			@set_time_limit( 0 ); // phpcs:ignore Generic.PHP.NoSilencedErrors.Discouraged
		}
		if ( function_exists( 'wp_raise_memory_limit' ) ) {
			wp_raise_memory_limit( 'admin' );
		}

		$report = array(
			'time'      => time(),
			'date'      => current_time( 'mysql' ),
			'processed' => 0,
			'inserted'  => 0,
			'updated'   => 0,
			'skipped'   => 0,
			'errors'    => array(),
		);

		// Preload filter criteria from settings once.
		$filter_rules = $this->load_filter_rules();

		// Determine feeds to process.
		$feeds = ! empty( $override_feeds ) ? $override_feeds : $this->get_configured_feeds();

		foreach ( $feeds as $source_name => $feed_source ) {
			if ( empty( $feed_source ) ) {
				continue;
			}

			try {
				$this->process_feed( $source_name, $feed_source, $filter_rules, $report );
			} catch ( \Throwable $e ) {
				$report['errors'][] = sprintf(
					'[%s] Error: %s',
					$source_name,
					$e->getMessage()
				);
			}
		}

		// Save report for admin dashboard.
		update_option( 'gae_last_sync_report', $report, false );

		// Periodically refresh available AI models list if transient expired.
		if ( class_exists( 'GAE_AI_API' ) ) {
			GAE_AI_API::get_available_models();
		}

		// Release lock.
		$this->release_lock();

		return $report;
	}

	/**
	 * Retrieve configured feed URLs.
	 *
	 * @return array<string, string>
	 */
	protected function get_configured_feeds(): array {
		return array(
			'Adtraction'   => (string) get_option( 'gae_adtraction_url', '' ),
			'TradeTracker' => (string) get_option( 'gae_tradetracker_url', '' ),
		);
	}

	/**
	 * Load and normalize pre-filtering criteria from WordPress settings.
	 *
	 * @return array{min_price: float, allowed_categories: string[], excluded_keywords: string[]}
	 */
	public function load_filter_rules(): array {
		$min_price          = (float) get_option( 'gae_min_price', 0 );
		$raw_categories     = (string) get_option( 'gae_allowed_categories', '' );
		$raw_excluded_words = (string) get_option( 'gae_excluded_keywords', '' );

		return array(
			'min_price'          => $min_price,
			'allowed_categories' => $this->parse_delimited_list( $raw_categories ),
			'excluded_keywords'  => $this->parse_delimited_list( $raw_excluded_words ),
		);
	}

	/**
	 * Parse comma/newline-delimited text into an array of lowercase trimmed strings.
	 *
	 * @param string $raw_text Input string.
	 * @return string[]
	 */
	public function parse_delimited_list( string $raw_text ): array {
		if ( empty( $raw_text ) ) {
			return array();
		}

		$items = preg_split( '/[\r\n,]+/', $raw_text );
		if ( ! is_array( $items ) ) {
			return array();
		}

		$cleaned = array();
		foreach ( $items as $item ) {
			$val = mb_strtolower( trim( $item ), 'UTF-8' );
			if ( '' !== $val ) {
				$cleaned[] = $val;
			}
		}

		return array_unique( $cleaned );
	}

	/**
	 * Process a single feed (either remote URL or local file path).
	 *
	 * Uses streaming to disk to avoid buffering massive feeds into PHP memory.
	 *
	 * @param string               $source_name  Feed label (Adtraction/TradeTracker).
	 * @param string               $feed_source  URL or file path.
	 * @param array<string, mixed> $filter_rules Pre-filter rules.
	 * @param array<string, mixed> $report       Reference to sync report.
	 * @return void
	 */
	public function process_feed( string $source_name, string $feed_source, array $filter_rules, array &$report ): void {
		$tmp_file = '';

		// If feed is a remote HTTP/HTTPS URL, stream it to a temporary file.
		if ( preg_match( '#^https?://#i', $feed_source ) ) {
			$tmp_file = $this->stream_remote_feed_to_file( $feed_source );
			if ( ! $tmp_file || ! file_exists( $tmp_file ) ) {
				$report['errors'][] = sprintf( '[%s] Failed to download remote feed from: %s', $source_name, $feed_source );
				return;
			}
			$stream_path = $tmp_file;
		} else {
			$stream_path = $feed_source;
		}

		if ( ! file_exists( $stream_path ) || ! is_readable( $stream_path ) ) {
			$report['errors'][] = sprintf( '[%s] Feed file is unreadable: %s', $source_name, $stream_path );
			if ( $tmp_file && file_exists( $tmp_file ) ) {
				@unlink( $tmp_file ); // phpcs:ignore Generic.PHP.NoSilencedErrors.Discouraged
			}
			return;
		}

		// Parse the XML using XMLReader for constant O(1) memory usage.
		$this->stream_parse_xml( $stream_path, $source_name, $filter_rules, $report );

		// Clean up temporary download file.
		if ( $tmp_file && file_exists( $tmp_file ) ) {
			@unlink( $tmp_file ); // phpcs:ignore Generic.PHP.NoSilencedErrors.Discouraged
		}
	}

	/**
	 * Stream remote feed directly into a temporary file on disk using wp_remote_get.
	 *
	 * @param string $url Remote feed URL.
	 * @return string|false Path to temporary file or false on failure.
	 */
	protected function stream_remote_feed_to_file( string $url ) {
		if ( ! function_exists( 'download_url' ) ) {
			require_once ABSPATH . 'wp-admin/includes/file.php';
		}

		// 300 second timeout for large catalog downloads.
		$tmp_file = download_url( $url, 300 );

		if ( is_wp_error( $tmp_file ) ) {
			return false;
		}

		return $tmp_file;
	}

	/**
	 * Stream-parse an XML file using XMLReader.
	 *
	 * Iterates over product/item elements, applies pre-filters, and upserts into database.
	 *
	 * @param string               $file_path    Local XML file path.
	 * @param string               $source_name  Source identifier.
	 * @param array<string, mixed> $filter_rules Pre-filter rules.
	 * @param array<string, mixed> $report       Reference to report.
	 * @return void
	 */
	public function stream_parse_xml( string $file_path, string $source_name, array $filter_rules, array &$report ): void {
		$reader = new \XMLReader();

		// Open XMLReader with libxml flags to ignore errors and preserve memory.
		if ( ! $reader->open( $file_path, 'UTF-8', LIBXML_NONET | LIBXML_NOBLANKS ) ) {
			$report['errors'][] = sprintf( '[%s] XMLReader unable to open file: %s', $source_name, $file_path );
			return;
		}

		$batch_counter = 0;

		// Stream through the XML node by node.
		while ( $reader->read() ) {
			// Look for product/item node elements.
			if ( \XMLReader::ELEMENT === $reader->nodeType && in_array( strtolower( $reader->localName ), array( 'product', 'item', 'article' ), true ) ) {
				$report['processed']++;
				$batch_counter++;

				// Extract outer XML for this node into SimpleXMLElement.
				$raw_node_xml = $reader->readOuterXml();
				$xml_element  = false;

				if ( ! empty( $raw_node_xml ) ) {
					$xml_element = simplexml_load_string( $raw_node_xml, 'SimpleXMLElement', LIBXML_NONET | LIBXML_NOBLANKS );
				}

				if ( ! $xml_element ) {
					// Fallback to DOM import if readOuterXml is unavailable.
					$doc  = new \DOMDocument( '1.0', 'UTF-8' );
					$node = $reader->expand();
					if ( $node ) {
						$node = $doc->importNode( $node, true );
						$doc->appendChild( $node );
						$xml_element = simplexml_import_dom( $doc );
					}
				}

				if ( ! $xml_element ) {
					continue;
				}

				// Extract normalized item data.
				$item = $this->extract_item_data( $xml_element, $source_name );

				// Apply pre-filters in-memory BEFORE touching the database.
				if ( ! $this->passes_pre_filters( $item, $filter_rules ) ) {
					$report['skipped']++;
					unset( $xml_element, $node, $item );
					continue;
				}

				// Upsert product into WordPress database based on EAN deduplication key.
				$result = $this->upsert_product( $item );
				if ( 'inserted' === $result ) {
					$report['inserted']++;
				} elseif ( 'updated' === $result ) {
					$report['updated']++;
				} else {
					$report['skipped']++;
				}

				unset( $xml_element, $node, $item );

				// Clean up memory cache periodically in batches.
				if ( $batch_counter >= self::BATCH_SIZE ) {
					$this->clear_batch_memory();
					$batch_counter = 0;
				}
			}
		}

		$reader->close();
	}

	/**
	 * Extract and normalize product data from SimpleXMLElement across feed formats.
	 *
	 * @param \SimpleXMLElement $xml         Product XML node.
	 * @param string            $source_name Source label.
	 * @return array<string, mixed> Normalized product record.
	 */
	public function extract_item_data( \SimpleXMLElement $xml, string $source_name ): array {
		// Helper closure to find the first matching tag value.
		$find_tag = static function( array $tags ) use ( $xml ): string {
			foreach ( $tags as $tag ) {
				// Handle property attributes like TradeTracker <property name="ean"><value>...</value></property>
				if ( false !== strpos( $tag, 'property:' ) ) {
					$prop_name = substr( $tag, 9 );
					if ( isset( $xml->properties ) ) {
						foreach ( $xml->properties->property as $prop ) {
							if ( strcasecmp( (string) $prop['name'], $prop_name ) === 0 ) {
								return trim( (string) $prop->value );
							}
						}
					}
				}

				if ( isset( $xml->{$tag} ) && '' !== trim( (string) $xml->{$tag} ) ) {
					return trim( (string) $xml->{$tag} );
				}
			}
			return '';
		};

		// 1. EAN / Barcode.
		$ean = $find_tag( array( 'ean', 'EAN', 'gtin', 'GTIN', 'barcode', 'property:ean', 'property:gtin', 'sku', 'SKU' ) );
		// Strip any non-alphanumeric characters from EAN.
		$ean = preg_replace( '/[^A-Za-z0-9]/', '', $ean ) ?? '';

		// 2. Title / Name.
		$title = $find_tag( array( 'name', 'title', 'product_name', 'productName', 'article_name' ) );

		// 3. Description.
		$description = $find_tag( array( 'description', 'summary', 'desc', 'long_description', 'content' ) );

		// 4. Prices.
		$raw_price = $find_tag( array( 'price', 'current_price', 'product_price', 'productPrice', 'sale_price' ) );
		$price     = $this->clean_price( $raw_price );

		$raw_regular = $find_tag( array( 'regular_price', 'old_price', 'original_price', 'retail_price', 'rrp', 'price_old' ) );
		$regular     = $this->clean_price( $raw_regular );
		if ( $regular <= 0 || $regular < $price ) {
			$regular = $price;
		}

		// 5. Affiliate Link.
		$affiliate_url = $find_tag( array( 'productUrl', 'product_url', 'url', 'trackingUrl', 'affiliate_url', 'link', 'deep_link' ) );

		// 6. Image Link.
		$image_url = $find_tag( array( 'imageUrl', 'image_url', 'image', 'largeImageUrl', 'product_image_url', 'image_link' ) );

		// 7. Merchant / Brand.
		$merchant = $find_tag( array( 'merchant', 'merchant_name', 'programName', 'brand', 'shop', 'shop_name' ) );
		if ( empty( $merchant ) ) {
			$merchant = $source_name;
		}

		// 8. Stock Status.
		$raw_stock = $find_tag( array( 'instock', 'in_stock', 'stock', 'availability', 'inStock', 'quantity' ) );
		$in_stock  = $this->parse_stock_status( $raw_stock );

		// 9. Category.
		$category = $find_tag( array( 'category', 'category_name', 'categories', 'product_category', 'department' ) );
		// If nested category path like "Sport > Trening > Tredemølle".
		if ( isset( $xml->categories->category ) ) {
			$cat_list = array();
			foreach ( $xml->categories->category as $c ) {
				$cat_list[] = trim( (string) $c );
			}
			if ( ! empty( $cat_list ) ) {
				$category = implode( ' > ', $cat_list );
			}
		}

		// 10. Currency.
		$currency = $find_tag( array( 'currency', 'price_currency' ) );
		if ( empty( $currency ) ) {
			$currency = 'kr';
		}

		return array(
			'ean'           => $ean,
			'title'         => $title,
			'description'   => $description,
			'price'         => $price,
			'regular_price' => $regular,
			'affiliate_url' => $affiliate_url,
			'image_url'     => $image_url,
			'merchant'      => $merchant,
			'in_stock'      => $in_stock,
			'category'      => $category,
			'currency'      => $currency,
			'source'        => $source_name,
		);
	}

	/**
	 * Convert price string with commas/spaces/currency symbols to a clean float.
	 *
	 * @param string $val Raw price.
	 * @return float
	 */
	public function clean_price( string $val ): float {
		if ( empty( $val ) ) {
			return 0.0;
		}

		// Remove non-numeric characters except comma and period.
		$val = preg_replace( '/[^\d,\.]/', '', $val ) ?? '';

		// Convert European comma decimal to period if appropriate.
		if ( false !== strpos( $val, ',' ) && false === strpos( $val, '.' ) ) {
			$val = str_replace( ',', '.', $val );
		} elseif ( false !== strpos( $val, ',' ) && false !== strpos( $val, '.' ) ) {
			// If both comma and dot exist (e.g. 1.499,00 or 1,499.00).
			if ( strrpos( $val, ',' ) > strrpos( $val, '.' ) ) {
				// 1.499,00
				$val = str_replace( '.', '', $val );
				$val = str_replace( ',', '.', $val );
			} else {
				// 1,499.00
				$val = str_replace( ',', '', $val );
			}
		}

		return (float) $val;
	}

	/**
	 * Parse string stock representations into boolean.
	 *
	 * @param string $raw Raw stock text.
	 * @return bool
	 */
	public function parse_stock_status( string $raw ): bool {
		if ( '' === $raw ) {
			return true; // Default to in-stock if unspecified.
		}

		$raw_lower = strtolower( trim( $raw ) );

		if ( in_array( $raw_lower, array( '0', 'no', 'false', 'out of stock', 'outofstock', 'ikke på lager', 'utsolgt' ), true ) ) {
			return false;
		}

		if ( is_numeric( $raw_lower ) && (int) $raw_lower <= 0 ) {
			return false;
		}

		return true;
	}

	/**
	 * Evaluate all settings pre-filters before interacting with the database.
	 *
	 * @param array<string, mixed> $item         Product data.
	 * @param array<string, mixed> $filter_rules Pre-filter criteria.
	 * @return bool True if product passes all filters, false to skip.
	 */
	public function passes_pre_filters( array $item, array $filter_rules ): bool {
		// 1. EAN must be present.
		if ( empty( $item['ean'] ) ) {
			return false;
		}

		// Title must not be empty.
		if ( empty( $item['title'] ) ) {
			return false;
		}

		// 2. Minimum price filter.
		$min_price = $filter_rules['min_price'] ?? 0;
		if ( $min_price > 0 && $item['price'] < $min_price ) {
			return false;
		}

		// Normalize text for category and keyword checks.
		$item_title = mb_strtolower( (string) $item['title'], 'UTF-8' );
		$item_cat   = mb_strtolower( (string) ( $item['category'] ?? '' ), 'UTF-8' );
		$item_desc  = mb_strtolower( (string) ( $item['description'] ?? '' ), 'UTF-8' );
		$searchable = $item_title . ' ' . $item_cat . ' ' . $item_desc;

		// 3. Allowed categories filter.
		$allowed_categories = $filter_rules['allowed_categories'] ?? array();
		if ( ! empty( $allowed_categories ) ) {
			$category_matched = false;
			foreach ( $allowed_categories as $allowed_cat ) {
				if ( '' !== $allowed_cat && ( false !== mb_strpos( $item_cat, $allowed_cat ) || false !== mb_strpos( $item_title, $allowed_cat ) ) ) {
					$category_matched = true;
					break;
				}
			}
			if ( ! $category_matched ) {
				return false;
			}
		}

		// 4. Blacklisted keywords filter.
		$excluded_keywords = $filter_rules['excluded_keywords'] ?? array();
		if ( ! empty( $excluded_keywords ) ) {
			foreach ( $excluded_keywords as $keyword ) {
				if ( '' !== $keyword && false !== mb_strpos( $searchable, $keyword ) ) {
					return false;
				}
			}
		}

		return true;
	}

	/**
	 * Insert new post or update existing post based on EAN deduplication key.
	 *
	 * @param array<string, mixed> $item Product data.
	 * @return string 'inserted', 'updated', or 'failed'
	 */
	public function upsert_product( array $item ): string {
		global $wpdb;

		$ean = (string) $item['ean'];

		// Fast indexed query by EAN meta value.
		$existing_post_id = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT post_id FROM {$wpdb->postmeta} WHERE meta_key = %s AND meta_value = %s LIMIT 1",
				GAE_CPT::META_EAN,
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
			update_post_meta( $post_id, GAE_CPT::META_PRICE, (float) $item['price'] );
			update_post_meta( $post_id, GAE_CPT::META_REGULAR_PRICE, (float) $item['regular_price'] );
			update_post_meta( $post_id, GAE_CPT::META_IN_STOCK, $item['in_stock'] ? 1 : 0 );
			update_post_meta( $post_id, GAE_CPT::META_AFFILIATE_URL, esc_url_raw( $item['affiliate_url'] ) );
			update_post_meta( $post_id, GAE_CPT::META_MERCHANT, sanitize_text_field( $item['merchant'] ) );
			update_post_meta( $post_id, GAE_CPT::META_CURRENCY, sanitize_text_field( $item['currency'] ) );
			update_post_meta( $post_id, GAE_CPT::META_LAST_SYNCED, $now );

			if ( ! empty( $item['image_url'] ) ) {
				update_post_meta( $post_id, GAE_CPT::META_IMAGE_URL, esc_url_raw( $item['image_url'] ) );
			}

			// Update taxonomy if category given.
			$this->assign_category( $post_id, (string) ( $item['category'] ?? '' ) );

			return 'updated';
		}

		// Insert new post.
		$post_args = array(
			'post_type'    => GAE_CPT::POST_TYPE,
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
		update_post_meta( $new_post_id, GAE_CPT::META_EAN, sanitize_text_field( $ean ) );
		update_post_meta( $new_post_id, GAE_CPT::META_PRICE, (float) $item['price'] );
		update_post_meta( $new_post_id, GAE_CPT::META_REGULAR_PRICE, (float) $item['regular_price'] );
		update_post_meta( $new_post_id, GAE_CPT::META_IN_STOCK, $item['in_stock'] ? 1 : 0 );
		update_post_meta( $new_post_id, GAE_CPT::META_AFFILIATE_URL, esc_url_raw( $item['affiliate_url'] ) );
		update_post_meta( $new_post_id, GAE_CPT::META_MERCHANT, sanitize_text_field( $item['merchant'] ) );
		update_post_meta( $new_post_id, GAE_CPT::META_IMAGE_URL, esc_url_raw( $item['image_url'] ) );
		update_post_meta( $new_post_id, GAE_CPT::META_CURRENCY, sanitize_text_field( $item['currency'] ) );
		update_post_meta( $new_post_id, GAE_CPT::META_LAST_SYNCED, $now );

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

		// Clean up category string (e.g. take final segment if breadcrumb like "Sport > Gym > Tredemølle").
		$parts     = explode( '>', $category );
		$leaf_name = sanitize_text_field( trim( end( $parts ) ) );

		if ( empty( $leaf_name ) ) {
			return;
		}

		$term = term_exists( $leaf_name, GAE_CPT::TAXONOMY );
		if ( ! $term ) {
			$term = wp_insert_term( $leaf_name, GAE_CPT::TAXONOMY );
		}

		if ( ! is_wp_error( $term ) && isset( $term['term_id'] ) ) {
			wp_set_object_terms( $post_id, (int) $term['term_id'], GAE_CPT::TAXONOMY, true );
		}
	}

	/**
	 * Clear WordPress internal caches to avoid memory leaks during batch streaming.
	 *
	 * @return void
	 */
	protected function clear_batch_memory(): void {
		global $wpdb, $wp_object_cache;

		// Clear WP object cache.
		if ( is_object( $wp_object_cache ) ) {
			$wp_object_cache->group_ops      = array();
			$wp_object_cache->stats          = array();
			$wp_object_cache->memcache_debug = array();
			$wp_object_cache->cache          = array();

			if ( method_exists( $wp_object_cache, '__remoteset' ) ) {
				$wp_object_cache->__remoteset();
			}
		}

		// Clear query log if SAVEQUERIES was active.
		$wpdb->queries = array();

		// Trigger PHP garbage collector.
		gc_collect_cycles();
	}

	/**
	 * Acquire sync lock via transient.
	 *
	 * @return bool
	 */
	protected function acquire_lock(): bool {
		if ( get_transient( self::LOCK_KEY ) ) {
			return false;
		}
		// Set lock for 2 hours (7200 seconds).
		return (bool) set_transient( self::LOCK_KEY, time(), 7200 );
	}

	/**
	 * Release sync lock.
	 *
	 * @return void
	 */
	protected function release_lock(): void {
		delete_transient( self::LOCK_KEY );
	}
}
