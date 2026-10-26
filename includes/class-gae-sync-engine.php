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
	 * @param bool                 $force_unlock   Whether to clear any existing lock (e.g. manual admin trigger).
	 * @return array<string, mixed> Detailed execution report.
	 */
	public function run_sync( array $override_feeds = array(), bool $force_unlock = false ): array {
		if ( $force_unlock ) {
			$this->release_lock();
		}

		// Acquire concurrency lock (10-minute TTL).
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
			'time'         => time(),
			'date'         => current_time( 'mysql' ),
			'processed'    => 0,
			'inserted'     => 0,
			'updated'      => 0,
			'skipped'      => 0,
			'skip_reasons' => array(),
			'errors'       => array(),
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
	 * @return array{
	 *     min_price: float,
	 *     max_price: float,
	 *     min_discount_pct: float,
	 *     only_in_stock: bool,
	 *     allowed_categories: string[],
	 *     allowed_merchants: string[],
	 *     excluded_merchants: string[],
	 *     excluded_keywords: string[],
	 *     required_keywords: string[]
	 * }
	 */
	public function load_filter_rules(): array {
		return array(
			'min_price'          => (float) get_option( 'gae_min_price', 0 ),
			'max_price'          => (float) get_option( 'gae_max_price', 0 ),
			'min_discount_pct'   => (float) get_option( 'gae_min_discount_pct', 0 ),
			'only_in_stock'      => (bool) get_option( 'gae_only_in_stock', false ),
			'allowed_categories' => $this->parse_delimited_list( (string) get_option( 'gae_allowed_categories', '' ) ),
			'allowed_merchants'  => $this->parse_delimited_list( (string) get_option( 'gae_allowed_merchants', '' ) ),
			'excluded_merchants' => $this->parse_delimited_list( (string) get_option( 'gae_excluded_merchants', '' ) ),
			'excluded_keywords'  => $this->parse_delimited_list( (string) get_option( 'gae_excluded_keywords', '' ) ),
			'required_keywords'  => $this->parse_delimited_list( (string) get_option( 'gae_required_keywords', '' ) ),
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
			$download_result = $this->stream_remote_feed_to_file( $feed_source );
			if ( is_wp_error( $download_result ) ) {
				$report['errors'][] = sprintf( '[%s] Download failed: %s', $source_name, $download_result->get_error_message() );
				return;
			} elseif ( ! $download_result || ! file_exists( $download_result ) ) {
				$report['errors'][] = sprintf( '[%s] Failed to download remote feed from: %s', $source_name, $feed_source );
				return;
			}
			$tmp_file    = $download_result;
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

		// Detect whether feed is JSON or XML format and stream-parse accordingly.
		$format = $this->detect_feed_format( $stream_path );
		if ( 'json' === $format ) {
			$this->stream_parse_json( $stream_path, $source_name, $filter_rules, $report );
		} else {
			$this->stream_parse_xml( $stream_path, $source_name, $filter_rules, $report );
		}

		// Clean up temporary download file.
		if ( $tmp_file && file_exists( $tmp_file ) ) {
			@unlink( $tmp_file ); // phpcs:ignore Generic.PHP.NoSilencedErrors.Discouraged
		}
	}

	/**
	 * Detect whether the feed file content is JSON or XML.
	 *
	 * @param string $file_path Local feed file path.
	 * @return string 'json' or 'xml'
	 */
	public function detect_feed_format( string $file_path ): string {
		$handle = fopen( $file_path, 'r' );
		if ( ! $handle ) {
			return 'xml';
		}

		$sample = fread( $handle, 2048 );
		fclose( $handle );

		if ( false === $sample || '' === trim( $sample ) ) {
			return 'xml';
		}

		$sample_trimmed = ltrim( $sample );

		// JSON files begin with '{' (object) or '[' (array).
		if ( in_array( $sample_trimmed[0], array( '{', '[' ), true ) ) {
			return 'json';
		}

		return 'xml';
	}

	/**
	 * Stream remote feed directly into a temporary file on disk using download_url or wp_remote_get.
	 *
	 * @param string $url Remote feed URL.
	 * @return string|\WP_Error Path to temporary file or WP_Error on failure.
	 */
	protected function stream_remote_feed_to_file( string $url ) {
		if ( function_exists( 'download_url' ) ) {
			require_once ABSPATH . 'wp-admin/includes/file.php';
			$tmp_file = download_url( $url, 180 );
			if ( ! is_wp_error( $tmp_file ) && file_exists( $tmp_file ) ) {
				return $tmp_file;
			}
		}

		// Fallback: standard wp_remote_get into temporary file.
		$response = wp_remote_get(
			$url,
			array(
				'timeout'    => 120,
				'sslverify'  => false,
				'user-agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36',
			)
		);

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		$status_code = wp_remote_retrieve_response_code( $response );
		if ( 200 !== (int) $status_code ) {
			return new \WP_Error( 'gae_http_error', sprintf( 'Remote feed returned HTTP %d', $status_code ) );
		}

		$body = wp_remote_retrieve_body( $response );
		if ( empty( $body ) ) {
			return new \WP_Error( 'gae_empty_feed', __( 'Remote feed returned empty response body.', 'gear-engine' ) );
		}

		$tmp_file = tempnam( sys_get_temp_dir(), 'gae_feed_' );
		if ( ! $tmp_file ) {
			return new \WP_Error( 'gae_tmp_failed', __( 'Unable to create local temporary file for feed streaming.', 'gear-engine' ) );
		}

		file_put_contents( $tmp_file, $body );
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

				$this->process_single_item( $item, $filter_rules, $report, $batch_counter );

				unset( $xml_element, $node, $item );
			}
		}

		$reader->close();
	}

	/**
	 * Stream-parse a JSON feed file.
	 *
	 * Supports standard JSON containers ({"products": [...]}, {"items": [...]}, [{...}])
	 * and Line-Delimited JSON (NDJSON) with O(1) memory usage.
	 *
	 * @param string               $file_path    Local JSON file path.
	 * @param string               $source_name  Feed source label.
	 * @param array<string, mixed> $filter_rules Pre-filter rules.
	 * @param array<string, mixed> $report       Reference to report.
	 * @return void
	 */
	public function stream_parse_json( string $file_path, string $source_name, array $filter_rules, array &$report ): void {
		$handle = fopen( $file_path, 'r' );
		if ( ! $handle ) {
			$report['errors'][] = sprintf( '[%s] Unable to open JSON feed file: %s', $source_name, $file_path );
			return;
		}

		$batch_counter = 0;
		$depth         = 0;
		$buffer        = '';
		$in_string     = false;
		$escape        = false;
		$in_array      = false;

		// Check if feed is formatted as NDJSON (JSON Lines).
		$first_line = fgets( $handle );
		$is_ndjson  = false;
		if ( false !== $first_line ) {
			$trimmed = trim( $first_line );
			if ( ! empty( $trimmed ) && '{' === $trimmed[0] && '}' === $trimmed[ strlen( $trimmed ) - 1 ] ) {
				$decoded = json_decode( $trimmed, true );
				if ( is_array( $decoded ) && ( isset( $decoded['ean'] ) || isset( $decoded['name'] ) || isset( $decoded['title'] ) || isset( $decoded['price'] ) || isset( $decoded['sku'] ) ) ) {
					$is_ndjson = true;
				}
			}
		}
		rewind( $handle );

		if ( $is_ndjson ) {
			while ( ( $line = fgets( $handle ) ) !== false ) {
				$line = trim( $line );
				if ( empty( $line ) ) {
					continue;
				}
				$raw = json_decode( $line, true );
				if ( is_array( $raw ) ) {
					$item = $this->extract_json_item_data( $raw, $source_name );
					$this->process_single_item( $item, $filter_rules, $report, $batch_counter );
				}
			}
		} else {
			// Streaming parser for standard JSON array / object container.
			while ( ( $chunk = fread( $handle, 8192 ) ) !== false && strlen( $chunk ) > 0 ) {
				$len = strlen( $chunk );
				for ( $i = 0; $i < $len; $i++ ) {
					$c = $chunk[ $i ];

					if ( $in_string ) {
						if ( $in_array && $depth > 0 ) {
							$buffer .= $c;
						}
						if ( $escape ) {
							$escape = false;
						} elseif ( '\\' === $c ) {
							$escape = true;
						} elseif ( '"' === $c ) {
							$in_string = false;
						}
					} else {
						if ( '"' === $c ) {
							$in_string = true;
							if ( $in_array && $depth > 0 ) {
								$buffer .= $c;
							}
						} elseif ( '[' === $c ) {
							$in_array = true;
						} elseif ( ']' === $c ) {
							$in_array = false;
						} elseif ( '{' === $c && $in_array ) {
							$depth++;
							$buffer .= $c;
						} elseif ( '}' === $c && $in_array ) {
							$depth--;
							$buffer .= $c;
							if ( 0 === $depth && ! empty( $buffer ) ) {
								$raw = json_decode( $buffer, true );
								if ( is_array( $raw ) ) {
									$item = $this->extract_json_item_data( $raw, $source_name );
									$this->process_single_item( $item, $filter_rules, $report, $batch_counter );
								}
								$buffer = '';
							}
						} elseif ( $in_array && $depth > 0 ) {
							$buffer .= $c;
						}
					}
				}
			}
		}

		fclose( $handle );
	}

	/**
	 * Process a single extracted item through pre-filters and database upsert.
	 *
	 * @param array<string, mixed> $item          Normalized product record.
	 * @param array<string, mixed> $filter_rules  Pre-filter criteria.
	 * @param array<string, mixed> $report        Reference to sync report.
	 * @param int                  $batch_counter Reference to batch counter.
	 * @return void
	 */
	public function process_single_item( array $item, array $filter_rules, array &$report, int &$batch_counter ): void {
		$report['processed']++;
		$batch_counter++;

		// Apply pre-filters in-memory BEFORE touching the database.
		$skip_reason = '';
		if ( ! $this->passes_pre_filters( $item, $filter_rules, $skip_reason ) ) {
			$report['skipped']++;
			if ( ! empty( $skip_reason ) ) {
				$report['skip_reasons'][ $skip_reason ] = ( $report['skip_reasons'][ $skip_reason ] ?? 0 ) + 1;
			}
			return;
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

		// Clean up memory cache periodically in batches.
		if ( $batch_counter >= self::BATCH_SIZE ) {
			$this->clear_batch_memory();
			$batch_counter = 0;
		}
	}

	/**
	 * Extract and normalize product data from JSON arrays or objects.
	 *
	 * @param array<string, mixed> $raw         Raw product data from JSON.
	 * @param string               $source_name Feed source label.
	 * @return array<string, mixed> Normalized product record.
	 */
	public function extract_json_item_data( array $raw, string $source_name ): array {
		$find_key = static function( array $keys ) use ( $raw ): string {
			foreach ( $keys as $k ) {
				if ( isset( $raw[ $k ] ) && is_scalar( $raw[ $k ] ) && '' !== trim( (string) $raw[ $k ] ) ) {
					return trim( (string) $raw[ $k ] );
				}
				$lower = strtolower( $k );
				foreach ( $raw as $rk => $rv ) {
					if ( strtolower( (string) $rk ) === $lower && is_scalar( $rv ) && '' !== trim( (string) $rv ) ) {
						return trim( (string) $rv );
					}
				}
			}
			return '';
		};

		// 1. EAN / Barcode / Unique Identifier.
		$ean = $find_key( array( 'ean', 'EAN', 'gtin', 'GTIN', 'barcode', 'sku', 'SKU', 'product_id', 'productId', 'productID', 'article_number', 'articleNumber', 'article_id', 'id', 'ID' ) );
		$ean = preg_replace( '/[^A-Za-z0-9_-]/', '', $ean ) ?? '';

		// 2. Title.
		$title = $find_key( array( 'name', 'title', 'product_name', 'productName', 'article_name' ) );

		// 3. Description.
		$description = $find_key( array( 'description', 'summary', 'desc', 'long_description', 'content' ) );

		// 4. Prices.
		$raw_price = $find_key( array( 'price', 'current_price', 'product_price', 'productPrice', 'sale_price' ) );
		$price     = $this->clean_price( $raw_price );

		$raw_regular = $find_key( array( 'fromPrice', 'from_price', 'regular_price', 'old_price', 'original_price', 'retail_price', 'rrp', 'price_old' ) );
		$regular     = $this->clean_price( $raw_regular );
		if ( $regular <= 0 || $regular < $price ) {
			$regular = $price;
		}

		// 5. Affiliate URL.
		$affiliate_url = $find_key( array( 'URL', 'url', 'productUrl', 'product_url', 'trackingUrl', 'affiliate_url', 'link', 'deep_link' ) );
		if ( ! empty( $affiliate_url ) ) {
			$affiliate_url = html_entity_decode( $affiliate_url, ENT_QUOTES, 'UTF-8' );
		}

		// 6. Image URL.
		$image_url = '';
		if ( isset( $raw['images'] ) && is_array( $raw['images'] ) && ! empty( $raw['images'] ) ) {
			$first_img = reset( $raw['images'] );
			$image_url = is_string( $first_img ) ? trim( $first_img ) : (string) ( $first_img['url'] ?? $first_img['image'] ?? '' );
		}
		if ( empty( $image_url ) ) {
			$image_url = $find_key( array( 'imageURL_large', 'imageUrl', 'image_url', 'image', 'largeImageUrl', 'product_image_url', 'image_link' ) );
		}

		// 7. Merchant.
		$merchant = $find_key( array( 'merchant', 'merchant_name', 'programName', 'brand', 'shop', 'shop_name' ) );

		// Extract store name from URL
		$store_name = '';
		if ( ! empty( $affiliate_url ) ) {
			if ( preg_match( '#u=https?%3A%2F%2F(?:www\.)?([^%2F]+)#i', $affiliate_url, $u_matches ) ) {
				$domain_parts = explode( '.', $u_matches[1] );
				$store_name   = ucfirst( $domain_parts[0] );
			} elseif ( $host = ( function_exists( 'wp_parse_url' ) ? wp_parse_url( $affiliate_url, PHP_URL_HOST ) : parse_url( $affiliate_url, PHP_URL_HOST ) ) ) {
				$clean_host   = preg_replace( '/^(?:partner\.|www\.)/', '', $host );
				$domain_parts = explode( '.', $clean_host );
				$store_name   = ucfirst( $domain_parts[0] );
			}
		}

		$brand_from_title = '';
		if ( ! empty( $title ) && false !== strpos( $title, ' - ' ) ) {
			$title_parts      = explode( ' - ', $title );
			$brand_from_title = trim( $title_parts[0] );
		}

		if ( ! empty( $brand_from_title ) && ! empty( $store_name ) && strcasecmp( $brand_from_title, $store_name ) !== 0 ) {
			$merchant = $brand_from_title . ' (' . $store_name . ')';
		} elseif ( ! empty( $store_name ) ) {
			$merchant = $store_name;
		} elseif ( ! empty( $brand_from_title ) ) {
			$merchant = $brand_from_title;
		} elseif ( empty( $merchant ) ) {
			$merchant = $source_name;
		}

		// 8. Stock Status.
		$raw_stock = $find_key( array( 'availability', 'instock', 'in_stock', 'stock', 'inStock', 'quantity' ) );
		$in_stock  = $this->parse_stock_status( $raw_stock );

		// 9. Category.
		$category = $find_key( array( 'categoryPath', 'category_path', 'category', 'category_name', 'categories', 'product_category', 'department' ) );
		if ( isset( $raw['categories'] ) && is_array( $raw['categories'] ) ) {
			$category = implode( ' > ', array_filter( array_map( 'trim', $raw['categories'] ) ) );
		}

		// 10. Currency.
		$currency = $find_key( array( 'currency', 'price_currency' ) );
		if ( empty( $currency ) ) {
			$currency = 'kr';
		}
		if ( 'NOK' === strtoupper( $currency ) ) {
			$currency = 'kr';
		}

		// Fallback: If no explicit EAN/ID was present, generate a deterministic deduplication key
		// from the affiliate URL or merchant + title so products are never dropped.
		if ( empty( $ean ) ) {
			if ( ! empty( $affiliate_url ) ) {
				$ean = 'url_' . substr( md5( $affiliate_url ), 0, 16 );
			} elseif ( ! empty( $title ) ) {
				$ean = 'item_' . substr( md5( $source_name . '_' . $title ), 0, 16 );
			}
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
	 * Extract and normalize product data from SimpleXMLElement across feed formats.
	 *
	 * @param \SimpleXMLElement $xml         Product XML node.
	 * @param string            $source_name Source label.
	 * @return array<string, mixed> Normalized product record.
	 */
	public function extract_item_data( \SimpleXMLElement $xml, string $source_name ): array {
		// Helper closure to find matching values from child elements, XML attributes, or namespaces.
		$find_tag = static function( array $tags ) use ( $xml ): string {
			// 1. Check direct child elements.
			foreach ( $tags as $tag ) {
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

			// 2. Check XML element attributes on the node (e.g. <product id="123">).
			foreach ( $tags as $tag ) {
				if ( isset( $xml[ $tag ] ) && '' !== trim( (string) $xml[ $tag ] ) ) {
					return trim( (string) $xml[ $tag ] );
				}
			}

			// 3. Check case-insensitive child elements.
			$lower_map = array();
			foreach ( $tags as $t ) {
				$lower_map[ strtolower( $t ) ] = true;
			}
			foreach ( $xml->children() as $child_name => $child_val ) {
				if ( isset( $lower_map[ strtolower( (string) $child_name ) ] ) && '' !== trim( (string) $child_val ) ) {
					return trim( (string) $child_val );
				}
			}

			// 4. Check namespaced tags (e.g. Google Shopping <g:id>, <g:gtin>, <g:title>).
			$namespaces = $xml->getNamespaces( true );
			foreach ( $namespaces as $prefix => $ns_url ) {
				$ns_children = $xml->children( $prefix, true );
				foreach ( $tags as $tag ) {
					$clean_tag = str_replace( array( 'g:', 'c:', 'p:' ), '', $tag );
					if ( isset( $ns_children->{$clean_tag} ) && '' !== trim( (string) $ns_children->{$clean_tag} ) ) {
						return trim( (string) $ns_children->{$clean_tag} );
					}
				}
			}

			return '';
		};

		// 1. EAN / Barcode / Unique Identifier.
		$ean = $find_tag( array(
			'ean', 'EAN', 'gtin', 'GTIN', 'barcode', 'property:ean', 'property:gtin', 'property:productid',
			'sku', 'SKU', 'productID', 'productId', 'product_id', 'Product_ID',
			'articleNumber', 'article_number', 'article_id', 'articleId', 'artno', 'art_no',
			'id', 'ID', 'item_id', 'itemId', 'mpn', 'MPN', 'model', 'g:id', 'g:gtin',
		) );
		$ean = preg_replace( '/[^A-Za-z0-9_-]/', '', $ean ) ?? '';

		// 2. Title / Name.
		$title = $find_tag( array( 'name', 'title', 'product_name', 'productName', 'article_name', 'g:title' ) );

		// 3. Description.
		$description = $find_tag( array( 'description', 'summary', 'desc', 'long_description', 'content', 'g:description' ) );

		// 4. Prices & Discounts.
		$raw_sale_price = $find_tag( array( 'sale_price', 'g:sale_price', 'salePrice', 'discount_price' ) );
		$raw_price      = $find_tag( array( 'price', 'g:price', 'current_price', 'product_price', 'productPrice' ) );

		$raw_regular = '';
		// Check TradeTracker <property name="fromPrice">
		if ( isset( $xml->properties ) ) {
			foreach ( $xml->properties->property as $prop ) {
				$prop_name = strtolower( (string) $prop['name'] );
				if ( in_array( $prop_name, array( 'fromprice', 'regularprice', 'oldprice', 'regular_price' ), true ) && '' !== trim( (string) $prop->value ) ) {
					$raw_regular = trim( (string) $prop->value );
					break;
				}
			}
		}
		if ( empty( $raw_regular ) ) {
			$raw_regular = $find_tag( array( 'regular_price', 'old_price', 'original_price', 'retail_price', 'rrp', 'price_old', 'g:regular_price' ) );
		}

		if ( ! empty( $raw_sale_price ) && $this->clean_price( $raw_sale_price ) > 0 ) {
			// In Google Shopping feeds (Adtraction), sale_price is current price and price is regular price.
			$price   = $this->clean_price( $raw_sale_price );
			$regular = ! empty( $raw_price ) ? $this->clean_price( $raw_price ) : $price;
			if ( $regular <= 0 || $regular < $price ) {
				$regular = $price;
			}
		} else {
			$price   = $this->clean_price( $raw_price );
			$regular = ! empty( $raw_regular ) ? $this->clean_price( $raw_regular ) : $price;
			if ( $regular <= 0 || $regular < $price ) {
				$regular = $price;
			}
		}

		// 5. Affiliate Link.
		$affiliate_url = $find_tag( array( 'link', 'g:link', 'URL', 'url', 'productUrl', 'product_url', 'trackingUrl', 'affiliate_url', 'deep_link' ) );
		if ( ! empty( $affiliate_url ) ) {
			$affiliate_url = html_entity_decode( $affiliate_url, ENT_QUOTES, 'UTF-8' );
		}

		// 6. Image Link.
		$image_url = '';
		// Check TradeTracker <images><image>...</image></images>
		if ( isset( $xml->images->image ) && '' !== trim( (string) $xml->images->image ) ) {
			$image_url = trim( (string) $xml->images->image );
		}
		// Check TradeTracker <property name="imageURL_large"> or <property name="imageURL">
		if ( empty( $image_url ) && isset( $xml->properties ) ) {
			foreach ( $xml->properties->property as $prop ) {
				$prop_name = strtolower( (string) $prop['name'] );
				if ( in_array( $prop_name, array( 'imageurl_large', 'imageurl', 'image_url', 'image' ), true ) && '' !== trim( (string) $prop->value ) ) {
					$image_url = trim( (string) $prop->value );
					break;
				}
			}
		}
		if ( empty( $image_url ) ) {
			$image_url = $find_tag( array( 'image_link', 'g:image_link', 'imageUrl', 'image_url', 'image', 'largeImageUrl', 'product_image_url' ) );
		}

		// 7. Merchant / Brand.
		$merchant = $find_tag( array( 'brand', 'g:brand', 'merchant', 'merchant_name', 'programName', 'shop', 'shop_name' ) );
		if ( empty( $merchant ) && isset( $xml->properties ) ) {
			foreach ( $xml->properties->property as $prop ) {
				$prop_name = strtolower( (string) $prop['name'] );
				if ( in_array( $prop_name, array( 'brand', 'merchant', 'shop' ), true ) && '' !== trim( (string) $prop->value ) ) {
					$merchant = trim( (string) $prop->value );
					break;
				}
			}
		}

		// Extract store name from affiliate URL (e.g. do.loepeshop.no or url=https://loepeshop.no)
		$store_name = '';
		if ( ! empty( $affiliate_url ) ) {
			if ( preg_match( '#(?:url|u)=https?(?:%3A%2F%2F|://)(?:www\.)?([^%&/\?]+)#i', $affiliate_url, $u_matches ) ) {
				$domain_parts = explode( '.', $u_matches[1] );
				$store_name   = ucfirst( $domain_parts[0] );
			} elseif ( $host = ( function_exists( 'wp_parse_url' ) ? wp_parse_url( $affiliate_url, PHP_URL_HOST ) : parse_url( $affiliate_url, PHP_URL_HOST ) ) ) {
				$clean_host   = preg_replace( '/^(?:do\.|partner\.|www\.)/', '', $host );
				$domain_parts = explode( '.', $clean_host );
				$store_name   = ucfirst( $domain_parts[0] );
			}
		}

		// Extract brand prefix from title if present (e.g. "Abilica - SP 900 Spinningsykkel")
		$brand_from_title = '';
		if ( ! empty( $title ) && false !== strpos( $title, ' - ' ) ) {
			$title_parts      = explode( ' - ', $title );
			$brand_from_title = trim( $title_parts[0] );
		}

		if ( ! empty( $merchant ) && ! empty( $store_name ) && strcasecmp( $merchant, $store_name ) !== 0 ) {
			$merchant = $merchant . ' (' . $store_name . ')';
		} elseif ( ! empty( $brand_from_title ) && ! empty( $store_name ) && strcasecmp( $brand_from_title, $store_name ) !== 0 ) {
			$merchant = $brand_from_title . ' (' . $store_name . ')';
		} elseif ( ! empty( $store_name ) ) {
			$merchant = $store_name;
		} elseif ( ! empty( $brand_from_title ) ) {
			$merchant = $brand_from_title;
		} elseif ( empty( $merchant ) ) {
			$merchant = $source_name;
		}

		// 8. Stock Status.
		$raw_stock = '';
		if ( isset( $xml->properties ) ) {
			foreach ( $xml->properties->property as $prop ) {
				$prop_name = strtolower( (string) $prop['name'] );
				if ( in_array( $prop_name, array( 'availability', 'stock', 'instock' ), true ) && '' !== trim( (string) $prop->value ) ) {
					$raw_stock = trim( (string) $prop->value );
					break;
				}
			}
		}
		if ( empty( $raw_stock ) ) {
			$raw_stock = $find_tag( array( 'availability', 'g:availability', 'instock', 'in_stock', 'stock', 'inStock', 'quantity' ) );
		}
		$in_stock = $this->parse_stock_status( $raw_stock );

		// 9. Category.
		$category = '';
		// Check TradeTracker <property name="categoryPath">
		if ( isset( $xml->properties ) ) {
			foreach ( $xml->properties->property as $prop ) {
				if ( strcasecmp( (string) $prop['name'], 'categoryPath' ) === 0 && '' !== trim( (string) $prop->value ) ) {
					$category = trim( (string) $prop->value );
					break;
				}
			}
		}
		// If nested categories like <categories><category path="...">
		if ( empty( $category ) && isset( $xml->categories->category ) ) {
			$cat_list = array();
			foreach ( $xml->categories->category as $c ) {
				$cat_list[] = trim( (string) $c );
			}
			if ( ! empty( $cat_list ) ) {
				$category = implode( ' > ', $cat_list );
			}
		}
		if ( empty( $category ) ) {
			$category = $find_tag( array( 'product_type', 'g:product_type', 'category', 'category_name', 'categories', 'google_product_category', 'g:google_product_category', 'department' ) );
		}

		// 10. Currency.
		$currency = '';
		if ( isset( $xml->price['currency'] ) && '' !== trim( (string) $xml->price['currency'] ) ) {
			$currency = trim( (string) $xml->price['currency'] );
		}
		if ( empty( $currency ) ) {
			if ( preg_match( '/\b([A-Za-z]{3})\b/', $raw_price, $c_matches ) ) {
				$currency = strtoupper( $c_matches[1] );
			} elseif ( preg_match( '/\b([A-Za-z]{3})\b/', $raw_sale_price, $c_matches ) ) {
				$currency = strtoupper( $c_matches[1] );
			}
		}
		if ( empty( $currency ) ) {
			$currency = $find_tag( array( 'currency', 'price_currency' ) );
		}
		if ( empty( $currency ) || 'NOK' === strtoupper( $currency ) ) {
			$currency = 'kr';
		}

		// Fallback: If no explicit EAN/ID was present, generate a deterministic deduplication key
		// from the affiliate URL or merchant + title so products are never dropped.
		if ( empty( $ean ) ) {
			if ( ! empty( $affiliate_url ) ) {
				$ean = 'url_' . substr( md5( $affiliate_url ), 0, 16 );
			} elseif ( ! empty( $title ) ) {
				$ean = 'item_' . substr( md5( $source_name . '_' . $title ), 0, 16 );
			}
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
	 * @param string               $reason       Output variable for skip reason.
	 * @return bool True if product passes all filters, false to skip.
	 */
	public function passes_pre_filters( array $item, array $filter_rules, string &$reason = '' ): bool {
		// 1. EAN / Barcode / Unique ID must be present.
		if ( empty( $item['ean'] ) ) {
			$reason = 'Missing EAN or Product ID';
			return false;
		}

		// Title must not be empty.
		if ( empty( $item['title'] ) ) {
			$reason = 'Missing Product Title';
			return false;
		}

		// 2. Minimum price filter.
		$min_price = $filter_rules['min_price'] ?? 0;
		if ( $min_price > 0 && $item['price'] < $min_price ) {
			$reason = sprintf( 'Price below minimum threshold (%.0f kr)', $min_price );
			return false;
		}

		// 3. Maximum price filter.
		$max_price = $filter_rules['max_price'] ?? 0;
		if ( $max_price > 0 && $item['price'] > $max_price ) {
			$reason = sprintf( 'Price above maximum cap (%.0f kr)', $max_price );
			return false;
		}

		// 4. Minimum discount percentage filter.
		$min_discount_pct = $filter_rules['min_discount_pct'] ?? 0;
		if ( $min_discount_pct > 0 ) {
			$regular = (float) ( $item['regular_price'] ?? 0 );
			$price   = (float) ( $item['price'] ?? 0 );
			if ( $regular <= $price ) {
				$reason = 'Not discounted (regular price <= current price)';
				return false;
			}
			$discount_pct = ( ( $regular - $price ) / $regular ) * 100;
			if ( $discount_pct < $min_discount_pct ) {
				$reason = sprintf( 'Discount (%.0f%%) below min threshold (%.0f%%)', $discount_pct, $min_discount_pct );
				return false;
			}
		}

		// 5. Stock status filter (Only in-stock products).
		$only_in_stock = ! empty( $filter_rules['only_in_stock'] );
		if ( $only_in_stock && empty( $item['in_stock'] ) ) {
			$reason = 'Item out of stock';
			return false;
		}

		$item_merchant = mb_strtolower( (string) ( $item['merchant'] ?? '' ), 'UTF-8' );

		// 6. Allowed merchants filter (whitelist).
		$allowed_merchants = $filter_rules['allowed_merchants'] ?? array();
		if ( ! empty( $allowed_merchants ) ) {
			$merchant_matched = false;
			foreach ( $allowed_merchants as $allowed_m ) {
				if ( '' !== $allowed_m && false !== mb_strpos( $item_merchant, $allowed_m ) ) {
					$merchant_matched = true;
					break;
				}
			}
			if ( ! $merchant_matched ) {
				$reason = sprintf( 'Merchant "%s" not in allowed whitelist', $item['merchant'] ?: 'unknown' );
				return false;
			}
		}

		// 7. Excluded merchants filter (blacklist).
		$excluded_merchants = $filter_rules['excluded_merchants'] ?? array();
		if ( ! empty( $excluded_merchants ) ) {
			foreach ( $excluded_merchants as $excluded_m ) {
				if ( '' !== $excluded_m && false !== mb_strpos( $item_merchant, $excluded_m ) ) {
					$reason = sprintf( 'Merchant "%s" matched blacklisted merchants', $item['merchant'] );
					return false;
				}
			}
		}

		// Normalize text for category and keyword checks.
		$item_title = mb_strtolower( (string) $item['title'], 'UTF-8' );
		$item_cat   = mb_strtolower( (string) ( $item['category'] ?? '' ), 'UTF-8' );
		$item_desc  = mb_strtolower( (string) ( $item['description'] ?? '' ), 'UTF-8' );
		$searchable = $item_title . ' ' . $item_cat . ' ' . $item_desc;

		// 8. Allowed categories filter.
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
				$reason = sprintf( 'Category/Title did not match allowed categories (Cat: "%s")', $item['category'] ?: 'none' );
				return false;
			}
		}

		// 9. Blacklisted keywords filter.
		$excluded_keywords = $filter_rules['excluded_keywords'] ?? array();
		if ( ! empty( $excluded_keywords ) ) {
			foreach ( $excluded_keywords as $keyword ) {
				if ( '' !== $keyword && false !== mb_strpos( $searchable, $keyword ) ) {
					$reason = sprintf( 'Matched blacklisted keyword "%s"', $keyword );
					return false;
				}
			}
		}

		// 10. Required keywords filter (product must contain at least one).
		$required_keywords = $filter_rules['required_keywords'] ?? array();
		if ( ! empty( $required_keywords ) ) {
			$req_matched = false;
			foreach ( $required_keywords as $req_kw ) {
				if ( '' !== $req_kw && false !== mb_strpos( $searchable, $req_kw ) ) {
					$req_matched = true;
					break;
				}
			}
			if ( ! $req_matched ) {
				$reason = 'Did not contain any of the required keywords';
				return false;
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
		// Set lock for 10 minutes (600 seconds).
		return (bool) set_transient( self::LOCK_KEY, time(), 600 );
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
