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
 * Class Gear_Engine_Sync_Engine
 *
 * Implements high-volume streaming XML feed parsing via XMLReader with pre-filtering,
 * EAN deduplication, batch database upserts, and daily WP-Cron scheduling.
 */
class Gear_Engine_Sync_Engine {

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
	public const BATCH_SIZE = 25;

	/**
	 * Follow-up cron hook that resumes a capped feed from its saved cursor.
	 */
	public const CONTINUE_HOOK = 'gae_sync_continue_event';

	/**
	 * Option storing per-feed scan cursors for chunked syncing.
	 */
	public const CURSOR_OPTION = 'gae_sync_cursor';

	/**
	 * Constructor.
	 */
	public function __construct() {
		// Hook WP-Cron scheduled tasks.
		add_action( self::CRON_HOOK, array( $this, 'run_sync' ) );
		add_action( self::CONTINUE_HOOK, array( $this, 'run_sync' ) );
	}

	/**
	 * Main sync coordinator.
	 *
	 * Downloads and streams all configured feeds (Adtraction, TradeTracker) in batches.
	 *
	 * @param array<string, mixed> $override_feeds        Optional feed URLs for manual/test runs.
	 * @param bool                 $force_unlock          Whether to clear any existing lock (e.g. manual admin trigger).
	 * @param array<string, mixed> $override_filter_rules Optional filter rules override (e.g. from dashboard).
	 * @return array<string, mixed> Detailed execution report.
	 */
	public function run_sync( array $override_feeds = array(), bool $force_unlock = false, array $override_filter_rules = array() ): array {
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

		// Optimization for high-volume batch imports: defer term/comment counting and disable revisions.
		if ( function_exists( 'wp_defer_term_counting' ) ) {
			wp_defer_term_counting( true );
		}
		if ( function_exists( 'wp_defer_comment_counting' ) ) {
			wp_defer_comment_counting( true );
		}
		if ( function_exists( 'remove_action' ) ) {
			remove_action( 'post_updated', 'wp_save_post_revision', 10 );
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

		// Preload filter criteria from settings once and merge any runtime overrides.
		$filter_rules = $this->load_filter_rules();
		if ( ! empty( $override_filter_rules ) ) {
			$filter_rules = array_merge( $filter_rules, $override_filter_rules );
		}

		// Determine feeds to process.
		$feeds = ! empty( $override_feeds ) ? $override_feeds : $this->get_configured_feeds();

		// If a specific target feed was requested (e.g. specific partner name or network).
		if ( ! empty( $filter_rules['target_feed'] ) && 'all' !== strtolower( (string) $filter_rules['target_feed'] ) ) {
			$target_key = strtolower( trim( (string) $filter_rules['target_feed'] ) );
			$filtered_feeds = array();
			foreach ( $feeds as $s_name => $s_url ) {
				if ( strtolower( $s_name ) === $target_key || false !== stripos( $s_name, $target_key ) ) {
					$filtered_feeds[ $s_name ] = $s_url;
				}
			}
			if ( ! empty( $filtered_feeds ) ) {
				$feeds = $filtered_feeds;
			}
		}

		// Never end a run silently with all zeros: say why nothing happened.
		if ( empty( $feeds ) ) {
			$report['errors'][] = __( 'No active feed URLs found. Add a feed under Settings, tick its Active checkbox and save before syncing.', 'gear-engine' );
			if ( function_exists( 'wp_defer_term_counting' ) ) {
				wp_defer_term_counting( false );
			}
			if ( function_exists( 'wp_defer_comment_counting' ) ) {
				wp_defer_comment_counting( false );
			}
			update_option( 'gae_last_sync_report', $report, false );
			$this->release_lock();
			return $report;
		}

		// Chunked sync: daily cron cycles start fresh cursors; a continuation
		// run resumes from saved scan offsets so capped feeds eventually finish
		// instead of silently re-processing only their first chunk forever.
		$in_cron         = function_exists( 'wp_doing_cron' ) && wp_doing_cron();
		$is_continuation = $in_cron && function_exists( 'wp_next_scheduled' ) && wp_next_scheduled( self::CONTINUE_HOOK );

		$cursor = get_option( self::CURSOR_OPTION, array() );
		if ( ! is_array( $cursor ) ) {
			$cursor = array();
		}
		if ( $in_cron && ! $is_continuation && ! empty( $cursor ) ) {
			delete_option( self::CURSOR_OPTION );
			$cursor = array();
		}

		foreach ( $feeds as $source_name => $feed_source ) {
			if ( empty( $feed_source ) ) {
				continue;
			}

			$feed_rules = $filter_rules;
			if ( $in_cron && isset( $cursor[ $source_name ]['scanned'] ) && ( time() - (int) ( $cursor[ $source_name ]['ts'] ?? 0 ) ) < 6 * HOUR_IN_SECONDS ) {
				$feed_rules['start_offset'] = max( 0, (int) $cursor[ $source_name ]['scanned'] );
			}

			try {
				$this->process_feed( $source_name, $feed_source, $feed_rules, $report );
			} catch ( \Throwable $e ) {
				$report['errors'][] = sprintf(
					'[%s] Error: %s',
					$source_name,
					$e->getMessage()
				);
			}

			if ( ! empty( $report['total_capped'] ) ) {
				// Catalog cap reached: nothing more to import until the cap changes.
				break;
			}

			$capped = ! empty( $report['batch_capped'] ) || ! empty( $report['scan_capped'] );

			if ( ! $capped ) {
				// Feed fully consumed within this batch: drop its stale cursor.
				if ( isset( $cursor[ $source_name ] ) ) {
					unset( $cursor[ $source_name ] );
					update_option( self::CURSOR_OPTION, $cursor, false );
				}
				continue;
			}

			// Background runs queue a follow-up chunk. Manual dashboard runs
			// keep their one-shot "this run" semantics and touch no cursors.
			if ( $in_cron ) {
				$cursor[ $source_name ] = array(
					'scanned' => (int) ( $report['feed_scanned'] ?? 0 ),
					'ts'      => time(),
				);
				update_option( self::CURSOR_OPTION, $cursor, false );
				if ( function_exists( 'as_schedule_single_event' ) && function_exists( 'wp_next_scheduled' ) && ! wp_next_scheduled( self::CONTINUE_HOOK ) ) {
					as_schedule_single_event( time() + 60, self::CONTINUE_HOOK );
				}
			}

			// Total product limit or batch limit was hit: stop this run here.
			break;
		}

		// Re-enable term and comment counting and recalculate deferred counts.
		if ( function_exists( 'wp_defer_term_counting' ) ) {
			wp_defer_term_counting( false );
		}
		if ( function_exists( 'wp_defer_comment_counting' ) ) {
			wp_defer_comment_counting( false );
		}

		// Save report for admin dashboard.
		update_option( 'gae_last_sync_report', $report, false );

		// Periodically refresh available AI models list if transient expired (only during background cron, not blocking user AJAX).
		if ( function_exists( 'wp_doing_cron' ) && wp_doing_cron() && class_exists( 'Gear_Engine_AI_API' ) ) {
			Gear_Engine_AI_API::get_available_models();
		}

		// Release lock.
		$this->release_lock();

		return $report;
	}

	/**
	 * Retrieve configured feed URLs.
	 *
	 * Checks the 5-slot feed table (gae_feeds), falling back to legacy single options.
	 *
	 * @return array<string, string> Associative array of [ 'Feed Name' => 'URL' ].
	 */
	public function get_configured_feeds(): array {
		$feeds_option = get_option( 'gae_feeds', array() );
		$feeds        = array();

		if ( ! empty( $feeds_option ) && is_array( $feeds_option ) ) {
			foreach ( $feeds_option as $index => $slot ) {
				if ( ! is_array( $slot ) ) {
					continue;
				}
				$enabled = ! empty( $slot['enabled'] );
				$url     = trim( (string) ( $slot['url'] ?? '' ) );
				$name    = trim( (string) ( $slot['name'] ?? '' ) );
				if ( empty( $name ) ) {
					$network = trim( (string) ( $slot['network'] ?? 'Feed' ) );
					$name    = sprintf( '%s #%d', $network, (int) $index + 1 );
				}

				if ( $enabled && ! empty( $url ) ) {
					$feeds[ $name ] = $url;
				}
			}
		}

		// Fallback to legacy single options if gae_feeds was never configured or had no active feeds.
		if ( empty( $feeds ) ) {
			$adtraction_url   = trim( (string) get_option( 'gae_adtraction_url', '' ) );
			$tradetracker_url = trim( (string) get_option( 'gae_tradetracker_url', '' ) );

			if ( ! empty( $adtraction_url ) ) {
				$feeds['Adtraction'] = $adtraction_url;
			}
			if ( ! empty( $tradetracker_url ) ) {
				$feeds['TradeTracker'] = $tradetracker_url;
			}
		}

		return $feeds;
	}

	/**
	 * Retrieve total count of published exercise gear products in the catalog.
	 *
	 * @return int Total published products.
	 */
	public function get_catalog_product_count(): int {
		if ( function_exists( 'wp_count_posts' ) ) {
			$counts = wp_count_posts( Gear_Engine_CPT::POST_TYPE );
			return (int) ( $counts->publish ?? 0 );
		}
		if ( isset( $GLOBALS['wp_posts'] ) && is_array( $GLOBALS['wp_posts'] ) ) {
			$count = 0;
			foreach ( $GLOBALS['wp_posts'] as $post ) {
				$pt = is_object( $post ) ? ( $post->post_type ?? '' ) : ( $post['post_type'] ?? '' );
				$ps = is_object( $post ) ? ( $post->post_status ?? 'publish' ) : ( $post['post_status'] ?? 'publish' );
				if ( $pt === Gear_Engine_CPT::POST_TYPE && 'publish' === $ps ) {
					$count++;
				}
			}
			return $count;
		}
		return 0;
	}

	/**
	 * Load and normalize pre-filtering criteria from WordPress settings.
	 *
	 * @return array<string, mixed>
	 */
	public function load_filter_rules(): array {
		return array(
			'max_items_per_sync' => (int) get_option( 'gae_max_items_per_sync', 100 ),
			'max_total_products' => (int) get_option( 'gae_max_total_products', 100 ),
			'max_scan_items'     => (int) get_option( 'gae_max_scan_items', 5000 ),
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
	 * Check if file has GZIP compression header (\x1f\x8b).
	 *
	 * @param string $file_path Local file path.
	 * @return bool True if gzipped.
	 */
	public function is_gzipped( string $file_path ): bool {
		$handle = @fopen( $file_path, 'rb' ); // phpcs:ignore Generic.PHP.NoSilencedErrors.Discouraged
		if ( ! $handle ) {
			return false;
		}
		$magic = fread( $handle, 2 );
		fclose( $handle );
		return ( "\x1f\x8b" === $magic );
	}

	/**
	 * Detect whether the feed file content is JSON or XML.
	 *
	 * Supports plain or gzip-compressed files.
	 *
	 * @param string $file_path Local feed file path.
	 * @return string 'json' or 'xml'
	 */
	public function detect_feed_format( string $file_path ): string {
		$stream_uri = $this->is_gzipped( $file_path ) ? 'compress.zlib://' . $file_path : $file_path;
		$handle     = @fopen( $stream_uri, 'r' ); // phpcs:ignore Generic.PHP.NoSilencedErrors.Discouraged
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
	 * Uses streaming to disk chunk by chunk to avoid buffering massive feeds into PHP memory.
	 *
	 * @param string $url Remote feed URL.
	 * @return string|\WP_Error Path to temporary file or WP_Error on failure.
	 */
	protected function stream_remote_feed_to_file( string $url ) {
		// Clean and normalize URL (decode &amp; and trim whitespace).
		$url = html_entity_decode( trim( $url ), ENT_QUOTES, 'UTF-8' );
		$url = str_replace( '&amp;', '&', $url );

		// For Adtraction feeds: prefer gzip=1 so feeds are downloaded 10-20x smaller,
		// and stream directly via compress.zlib:// with minimal memory and zero memory blowup.
		if ( false !== stripos( $url, 'adtraction.com' ) ) {
			if ( preg_match( '/[?&]zip=1/i', $url ) ) {
				$url = preg_replace( '/([?&])zip=1/i', '${1}gzip=1', $url );
			} elseif ( ! preg_match( '/[?&](?:gzip|zip)=/i', $url ) ) {
				$url .= ( false !== strpos( $url, '?' ) ? '&' : '?' ) . 'gzip=1';
			}
		}

		if ( ! function_exists( 'download_url' ) && defined( 'ABSPATH' ) && file_exists( ABSPATH . 'wp-admin/includes/file.php' ) ) {
			require_once ABSPATH . 'wp-admin/includes/file.php';
		}

		if ( function_exists( 'download_url' ) ) {
			$tmp_file = download_url( $url, 300 );
			if ( ! is_wp_error( $tmp_file ) && file_exists( $tmp_file ) ) {
				if ( filesize( $tmp_file ) > 0 ) {
					return $tmp_file;
				}
				@unlink( $tmp_file ); // phpcs:ignore Generic.PHP.NoSilencedErrors.Discouraged
			}
		}

		// Fallback: stream directly to temporary file on disk using wp_remote_get without buffering into RAM.
		$tmp_file = function_exists( 'wp_tempnam' ) ? wp_tempnam( 'gae_feed_' ) : tempnam( sys_get_temp_dir(), 'gae_feed_' );
		if ( ! $tmp_file ) {
			return new \WP_Error( 'gae_tmp_failed', __( 'Unable to create local temporary file for feed streaming.', 'gear-engine' ) );
		}

		$response = wp_remote_get(
			$url,
			array(
				'timeout'    => 300,
				'stream'     => true,
				'filename'   => $tmp_file,
				'sslverify'  => false,
				'user-agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36',
			)
		);

		if ( is_wp_error( $response ) ) {
			if ( file_exists( $tmp_file ) ) {
				@unlink( $tmp_file ); // phpcs:ignore Generic.PHP.NoSilencedErrors.Discouraged
			}
			return $response;
		}

		$status_code = wp_remote_retrieve_response_code( $response );
		if ( 200 !== (int) $status_code ) {
			if ( file_exists( $tmp_file ) ) {
				@unlink( $tmp_file ); // phpcs:ignore Generic.PHP.NoSilencedErrors.Discouraged
			}
			return new \WP_Error( 'gae_http_error', sprintf( 'Remote feed returned HTTP %d', $status_code ) );
		}

		if ( ! file_exists( $tmp_file ) || filesize( $tmp_file ) === 0 ) {
			if ( file_exists( $tmp_file ) ) {
				@unlink( $tmp_file ); // phpcs:ignore Generic.PHP.NoSilencedErrors.Discouraged
			}
			return new \WP_Error( 'gae_empty_feed', __( 'Remote feed returned empty response body.', 'gear-engine' ) );
		}

		return $tmp_file;
	}

	public function file_sample( string $file_path, int $length = 240 ): string {
		$uri    = $this->is_gzipped( $file_path ) ? 'compress.zlib://' . $file_path : $file_path;
		$handle = @fopen( $uri, 'r' ); // phpcs:ignore Generic.PHP.NoSilencedErrors.Discouraged, WordPress.WP.AlternativeFunctions.file_system_read_fopen
		if ( ! $handle ) {
			return '(unreadable)';
		}
		$raw = (string) fread( $handle, $length );
		fclose( $handle );

		$clean = trim( sanitize_text_field( preg_replace( '/\s+/', ' ', $raw ) ) );
		if ( '' === $clean ) {
			$raw_handle = @fopen( $file_path, 'rb' ); // phpcs:ignore Generic.PHP.NoSilencedErrors.Discouraged, WordPress.WP.AlternativeFunctions.file_system_read_fopen
			$raw_head   = $raw_handle ? (string) fread( $raw_handle, 48 ) : '';
			if ( $raw_handle ) {
				fclose( $raw_handle );
			}
			return sprintf(
				'(no readable feed text via %1$s; stored file starts with hex %2$s, size %3$d bytes)',
				$uri === $file_path ? 'direct read' : 'zlib decompression',
				substr( (string) bin2hex( $raw_head ), 0, 32 ),
				(int) @filesize( $file_path )
			);
		}
		return $clean;
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
		libxml_use_internal_errors( true );
		$reader = new \XMLReader();

		$open_uri = $this->is_gzipped( $file_path ) ? 'compress.zlib://' . $file_path : $file_path;

		// Open XMLReader with libxml flags to ignore errors and preserve memory.
		if ( ! $reader->open( $open_uri, 'UTF-8', LIBXML_NONET | LIBXML_NOBLANKS ) ) {
			$report['errors'][] = sprintf( '[%s] XMLReader unable to open file: %s', $source_name, $file_path );
			libxml_clear_errors();
			return;
		}

		$batch_counter  = 0;
		$feed_scanned   = 0;
		$start_offset   = max( 0, (int) ( $filter_rules['start_offset'] ?? 0 ) );
		$report['feed_scanned'] = $start_offset;
		$max_limit      = (int) ( $filter_rules['max_items_per_sync'] ?? 100 );
		$max_scan       = (int) ( $filter_rules['max_scan_items'] ?? 5000 );

		// Stream through the XML node by node.
		while ( $reader->read() ) {
			if ( $max_scan > 0 && $feed_scanned >= $max_scan ) {
				$report['scan_capped'] = true;
				break;
			}

			// Look for product/item node elements.
			if ( \XMLReader::ELEMENT === $reader->nodeType && in_array( strtolower( $reader->localName ), array( 'product', 'item', 'article' ), true ) ) {
				$feed_scanned++;
				if ( $feed_scanned <= $start_offset ) {
					// Already imported by a previous chunk of this cycle.
					continue;
				}
				if ( $max_limit > 0 && ( $report['inserted'] + $report['updated'] ) >= $max_limit ) {
					$report['batch_capped'] = true;
					break;
				}
				$report['feed_scanned'] = $feed_scanned;

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
		libxml_clear_errors();

		if ( 0 === $feed_scanned ) {
			$report['errors'][] = sprintf(
				'[%1$s] Feed opened but no <product>/<item>/<article> elements were found (0 nodes scanned). First bytes: %2$s',
				$source_name,
				$this->file_sample( $file_path )
			);
		}
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
		$open_uri = $this->is_gzipped( $file_path ) ? 'compress.zlib://' . $file_path : $file_path;
		$handle   = @fopen( $open_uri, 'r' ); // phpcs:ignore Generic.PHP.NoSilencedErrors.Discouraged
		if ( ! $handle ) {
			$report['errors'][] = sprintf( '[%s] Unable to open JSON feed file: %s', $source_name, $file_path );
			return;
		}

		$batch_counter  = 0;
		$feed_scanned   = 0;
		$start_offset   = max( 0, (int) ( $filter_rules['start_offset'] ?? 0 ) );
		$report['feed_scanned'] = $start_offset;
		$max_limit      = (int) ( $filter_rules['max_items_per_sync'] ?? 100 );
		$max_scan       = (int) ( $filter_rules['max_scan_items'] ?? 5000 );
		$depth          = 0;
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
				if ( $max_scan > 0 && $feed_scanned >= $max_scan ) {
					$report['scan_capped'] = true;
					break;
				}
				$line = trim( $line );
				if ( empty( $line ) ) {
					continue;
				}
				$raw = json_decode( $line, true );
				if ( is_array( $raw ) ) {
					$feed_scanned++;
					if ( $feed_scanned <= $start_offset ) {
						continue;
					}
					if ( $max_limit > 0 && ( $report['inserted'] + $report['updated'] ) >= $max_limit ) {
						$report['batch_capped'] = true;
						break;
					}
					$report['feed_scanned'] = $feed_scanned;
					$item = $this->extract_json_item_data( $raw, $source_name );
					$this->process_single_item( $item, $filter_rules, $report, $batch_counter );
				}
			}
		} else {
			// Streaming parser for standard JSON array / object container.
			while ( ( $chunk = fread( $handle, 8192 ) ) !== false && strlen( $chunk ) > 0 ) {
				if ( $max_scan > 0 && $feed_scanned >= $max_scan ) {
					$report['scan_capped'] = true;
					break;
				}
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
									$feed_scanned++;
									if ( $feed_scanned <= $start_offset ) {
										continue;
									}
									if ( $max_limit > 0 && ( $report['inserted'] + $report['updated'] ) >= $max_limit ) {
										$report['batch_capped'] = true;
										$report['feed_scanned'] = $feed_scanned - 1;
										break;
									}
									$report['feed_scanned'] = $feed_scanned;
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

		if ( 0 === $feed_scanned ) {
			$report['errors'][] = sprintf(
				'[%1$s] JSON feed opened but no item objects were recognised (0 nodes scanned). First bytes: %2$s',
				$source_name,
				$this->file_sample( $file_path )
			);
		}
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
			// Clean up memory cache periodically in batches even when items are filtered/skipped.
			if ( $batch_counter >= self::BATCH_SIZE ) {
				$this->clear_batch_memory();
				$batch_counter = 0;
			}
			return;
		}

		// Check total catalog product limit before inserting a brand new product.
		$max_total_limit = (int) ( $filter_rules['max_total_products'] ?? 0 );
		$catalog_count   = ( $max_total_limit > 0 ) ? $this->get_catalog_product_count() : 0;
		$at_catalog_cap  = ( $max_total_limit > 0 && ( $catalog_count + $report['inserted'] ) >= $max_total_limit );

		// Upsert product into WordPress database based on EAN deduplication key.
		$result = $this->upsert_product( $item, $at_catalog_cap );
		if ( 'inserted' === $result ) {
			$report['inserted']++;
		} elseif ( 'updated' === $result ) {
			$report['updated']++;
		} elseif ( 'catalog_capped' === $result ) {
			$report['skipped']++;
			$report['total_capped'] = true;
			$report['skip_reasons']['Catalog limit reached'] = ( $report['skip_reasons']['Catalog limit reached'] ?? 0 ) + 1;
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
		return $this->rules()->passes_pre_filters( $item, $filter_rules, $reason );
	}

	/**
	 * Insert new post or update existing post based on EAN deduplication key.
	 *
	 * @param array<string, mixed> $item               Product data.
	 * @param bool                 $prevent_new_insert Whether to prevent inserting if catalog is capped.
	 * @return string 'inserted', 'updated', 'catalog_capped', or 'failed'
	 */
	public function upsert_product( array $item, bool $prevent_new_insert = false ): string {
		return $this->upscaler()->upsert( $item, $prevent_new_insert );
	}

	/**
	 * Clear WordPress internal caches to avoid memory leaks during batch streaming.
	 *
	 * @return void
	 */
	protected function clear_batch_memory(): void {
		global $wpdb, $wp_object_cache;

		if ( function_exists( 'libxml_clear_errors' ) ) {
			libxml_clear_errors();
		}

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
		if ( function_exists( 'gc_collect_cycles' ) ) {
			gc_collect_cycles();
		}
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

	/**
	 * Shared pre-filter rule evaluator.
	 *
	 * @return Gear_Engine_Sync_Rules
	 */
	private function rules(): Gear_Engine_Sync_Rules {
		static $instance;
		if ( null === $instance ) {
			$instance = new Gear_Engine_Sync_Rules();
		}
		return $instance;
	}

	/**
	 * Shared product persistence helper.
	 *
	 * @return Gear_Engine_Product_Upscaler
	 */
	private function upscaler(): Gear_Engine_Product_Upscaler {
		static $instance;
		if ( null === $instance ) {
			$instance = new Gear_Engine_Product_Upscaler();
		}
		return $instance;
	}
}


// Deprecated alias preserved for backwards compatibility.
if ( ! class_exists( 'GAE_Sync_Engine', false ) ) {
	class_alias( 'Gear_Engine_Sync_Engine', 'GAE_Sync_Engine' );
}

