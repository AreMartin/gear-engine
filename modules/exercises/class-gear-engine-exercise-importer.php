<?php
/**
 * CSV importer for the shared exercise library.
 *
 * @package GearEngine
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Provides a preview-first CSV import workflow for exercise records.
 */
class Gear_Engine_Exercise_Importer {

	private const GEAR_ENGINE_PAGE_SLUG       = 'gear-engine-exercise-import';
	private const GEAR_ENGINE_PREVIEW_ACTION  = 'gear_engine_exercise_csv_preview';
	private const GEAR_ENGINE_IMPORT_ACTION   = 'gear_engine_exercise_csv_import';
	private const GEAR_ENGINE_TEMPLATE_ACTION = 'gear_engine_exercise_csv_template';
	private const GEAR_ENGINE_PREVIEW_TTL     = 900;
	private const GEAR_ENGINE_RESULT_TTL      = 900;
	private const GEAR_ENGINE_MAX_FILE_BYTES  = 5242880;
	private const GEAR_ENGINE_MAX_ROWS        = 500;

	private const GEAR_ENGINE_META_IMPORT_KEY = '_gear_engine_exercise_import_key';

	/**
	 * Register dashboard routes for the importer.
	 */
	public function __construct() {
		add_action( 'admin_menu', array( $this, 'register_admin_page' ) );
		add_action( 'admin_post_' . self::GEAR_ENGINE_PREVIEW_ACTION, array( $this, 'handle_preview' ) );
		add_action( 'admin_post_' . self::GEAR_ENGINE_IMPORT_ACTION, array( $this, 'handle_import' ) );
		add_action( 'admin_post_' . self::GEAR_ENGINE_TEMPLATE_ACTION, array( $this, 'download_template' ) );
	}

	/**
	 * Add an importer submenu below the Exercise Library post type.
	 *
	 * @return void
	 */
	public function register_admin_page(): void {
		add_submenu_page(
			'edit.php?post_type=' . Gear_Engine_Exercises_CPT::GEAR_ENGINE_POST_TYPE,
			__( 'Import Exercises', 'gear-engine' ),
			__( 'Import Exercises', 'gear-engine' ),
			'edit_posts',
			self::GEAR_ENGINE_PAGE_SLUG,
			array( $this, 'render_admin_page' )
		);
	}

	/**
	 * Render the CSV upload and preview interface.
	 *
	 * @return void
	 */
	public function render_admin_page(): void {
		if ( ! current_user_can( 'edit_posts' ) ) {
			wp_die( esc_html__( 'You do not have permission to import exercises.', 'gear-engine' ) );
		}

		?>
		<div class="wrap">
			<h1><?php esc_html_e( 'Import Exercises from CSV', 'gear-engine' ); ?></h1>
			<p><?php esc_html_e( 'Upload a UTF-8 CSV file to preview and validate exercise records before importing them. New records are drafts unless you explicitly choose to publish them.', 'gear-engine' ); ?></p>
			<p><a class="button" href="<?php echo esc_url( wp_nonce_url( admin_url( 'admin-post.php?action=' . self::GEAR_ENGINE_TEMPLATE_ACTION ), self::GEAR_ENGINE_TEMPLATE_ACTION ) ); ?>"><?php esc_html_e( 'Download CSV template', 'gear-engine' ); ?></a></p>
			<p><?php esc_html_e( 'Required columns: title, description, and either exorlive_id or external_key. Separate multiple area/equipment terms with semicolons. Existing records are updated by ExorLive ID first, then external key.', 'gear-engine' ); ?></p>

			<?php $this->render_import_notice(); ?>

			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" enctype="multipart/form-data">
				<input type="hidden" name="action" value="<?php echo esc_attr( self::GEAR_ENGINE_PREVIEW_ACTION ); ?>" />
				<?php wp_nonce_field( self::GEAR_ENGINE_PREVIEW_ACTION, '_gear_engine_exercise_import_nonce' ); ?>
				<input type="file" name="exercise_csv" accept=".csv,text/csv" required />
				<p>
					<label>
						<?php if ( current_user_can( 'publish_posts' ) ) : ?>
							<input type="checkbox" name="publish_valid_rows" value="1" />
							<?php esc_html_e( 'Publish valid rows after preview (otherwise new exercises remain drafts)', 'gear-engine' ); ?>
						<?php else : ?>
							<?php esc_html_e( 'New exercises will remain drafts.', 'gear-engine' ); ?>
						<?php endif; ?>
					</label>
				</p>
				<?php submit_button( __( 'Upload and preview', 'gear-engine' ) ); ?>
			</form>
		</div>
		<?php
	}

	/**
	 * Validate an uploaded CSV, then store a short-lived preview for confirmation.
	 *
	 * @return void
	 */
	public function handle_preview(): void {
		$this->verify_request( self::GEAR_ENGINE_PREVIEW_ACTION );

		$file = $_FILES['exercise_csv'] ?? null;
		if (
			! is_array( $file )
			|| UPLOAD_ERR_OK !== ( $file['error'] ?? UPLOAD_ERR_NO_FILE )
			|| empty( $file['tmp_name'] )
			|| ! is_uploaded_file( $file['tmp_name'] )
			|| ! isset( $file['size'] )
			|| (int) $file['size'] > self::GEAR_ENGINE_MAX_FILE_BYTES
			|| 'csv' !== strtolower( pathinfo( sanitize_file_name( (string) ( $file['name'] ?? '' ) ), PATHINFO_EXTENSION ) )
		) {
			wp_die( esc_html__( 'Please upload a CSV file no larger than 5 MB.', 'gear-engine' ) );
		}

		$preview = self::validate_csv_file( $file['tmp_name'] );
		if ( is_wp_error( $preview ) ) {
			wp_die( esc_html( $preview->get_error_message() ) );
		}

		$token = wp_generate_password( 32, false, false );
		$preview_key = self::get_preview_transient_key();
		$publish = current_user_can( 'publish_posts' ) && ! empty( $_POST['publish_valid_rows'] );
		if ( ! set_transient(
			$preview_key,
			array(
				'token'   => $token,
				'rows'    => $preview,
				'publish' => $publish,
			),
			self::GEAR_ENGINE_PREVIEW_TTL
		) ) {
			wp_die( esc_html__( 'The import preview could not be saved. Please try again.', 'gear-engine' ) );
		}

		$this->render_preview( $preview, $token, $publish );
	}

	/**
	 * Validate and normalize all rows in a CSV file.
	 *
	 * @param string $path Temporary uploaded file path.
	 * @return array|WP_Error
	 */
	public static function validate_csv_file( string $path ) {
		$file_size = is_readable( $path ) ? filesize( $path ) : false;
		if ( false === $file_size || $file_size > self::GEAR_ENGINE_MAX_FILE_BYTES ) {
			return new WP_Error( 'gear_engine_exercise_csv_unreadable', __( 'The CSV file could not be read or exceeds the 5 MB limit.', 'gear-engine' ) );
		}

		$handle = fopen( $path, 'rb' );
		if ( false === $handle ) {
			return new WP_Error( 'gear_engine_exercise_csv_open_failed', __( 'The CSV file could not be opened.', 'gear-engine' ) );
		}

		$headers = fgetcsv( $handle, 0, ',', '"', '' );
		if ( ! is_array( $headers ) ) {
			fclose( $handle );
			return new WP_Error( 'gear_engine_exercise_csv_empty', __( 'The CSV file is empty.', 'gear-engine' ) );
		}

		$headers[0] = preg_replace( '/^\xEF\xBB\xBF/', '', (string) $headers[0] );
		$headers    = array_map(
			static function ( $header ): string {
				return sanitize_key( trim( (string) $header ) );
			},
			$headers
		);
		$allowed_headers = array( 'external_key', 'title', 'description', 'summary', 'area', 'equipment', 'dosage', 'exorlive_id', 'video_culture', 'source_url' );
		if ( count( array_unique( $headers ) ) !== count( $headers ) || array_diff( $headers, $allowed_headers ) ) {
			fclose( $handle );
			return new WP_Error( 'gear_engine_exercise_csv_headers_invalid', __( 'The CSV contains duplicate or unsupported column names. Download the template for the supported columns.', 'gear-engine' ) );
		}
		$required_headers = array( 'title', 'description' );
		if ( array_diff( $required_headers, $headers ) || ( ! in_array( 'exorlive_id', $headers, true ) && ! in_array( 'external_key', $headers, true ) ) ) {
			fclose( $handle );
			return new WP_Error( 'gear_engine_exercise_csv_headers', __( 'The CSV must include title, description, and either exorlive_id or external_key columns.', 'gear-engine' ) );
		}

		$rows            = array();
		$seen_identifiers = array();
		$line_number     = 1;
		while ( false !== ( $values = fgetcsv( $handle, 0, ',', '"', '' ) ) ) {
			++$line_number;
			if ( array( null ) === $values || ( 1 === count( $values ) && null === $values[0] ) ) {
				continue;
			}
			if ( count( $rows ) >= self::GEAR_ENGINE_MAX_ROWS ) {
				fclose( $handle );
				return new WP_Error( 'gear_engine_exercise_csv_rows', __( 'The CSV may contain no more than 500 data rows.', 'gear-engine' ) );
			}

			$has_extra_columns = count( $values ) > count( $headers );
			$values            = array_pad( array_slice( $values, 0, count( $headers ) ), count( $headers ), '' );
			$raw    = array_combine( $headers, $values );
			$row    = self::normalize_row( $raw, $line_number );
			if ( $has_extra_columns ) {
				$row['errors'][] = __( 'This row contains extra comma-separated values. Quote text fields containing commas.', 'gear-engine' );
			}

			foreach ( self::get_row_identifiers( $row['data'] ) as $identifier ) {
				if ( isset( $seen_identifiers[ $identifier ] ) ) {
					$row['errors'][] = sprintf(
						/* translators: %s: duplicate row number */
						__( 'Duplicate identifier also used on row %s.', 'gear-engine' ),
						$seen_identifiers[ $identifier ]
					);
				} else {
					$seen_identifiers[ $identifier ] = $line_number;
				}
			}
			$rows[] = $row;
		}
		fclose( $handle );

		if ( empty( $rows ) ) {
			return new WP_Error( 'gear_engine_exercise_csv_no_rows', __( 'The CSV contains no exercise rows.', 'gear-engine' ) );
		}

		return $rows;
	}

	/**
	 * Handle confirmation and import valid rows only.
	 *
	 * @return void
	 */
	public function handle_import(): void {
		$this->verify_request( self::GEAR_ENGINE_IMPORT_ACTION );

		$submitted_token = isset( $_POST['preview_token'] ) ? sanitize_text_field( wp_unslash( $_POST['preview_token'] ) ) : '';
		$preview_key    = self::get_preview_transient_key();
		$preview        = get_transient( $preview_key );
		if ( ! is_array( $preview ) || empty( $preview['token'] ) || ! hash_equals( (string) $preview['token'], $submitted_token ) ) {
			wp_die( esc_html__( 'This preview has expired. Upload the CSV again to create a new preview.', 'gear-engine' ) );
		}

		$results = self::import_rows( $preview['rows'], ! empty( $preview['publish'] ) );
		delete_transient( $preview_key );
		set_transient( self::get_result_transient_key(), $results, self::GEAR_ENGINE_RESULT_TTL );
		$url = add_query_arg(
			array(
				'page'      => self::GEAR_ENGINE_PAGE_SLUG,
				'imported'  => $results['imported'],
				'updated'   => $results['updated'],
				'skipped'   => $results['skipped'],
				'failed'    => $results['failed'],
			),
			admin_url( 'edit.php?post_type=' . Gear_Engine_Exercises_CPT::GEAR_ENGINE_POST_TYPE )
		);
		wp_safe_redirect( $url );
		exit;
	}

	/**
	 * Download a CSV header template.
	 *
	 * @return void
	 */
	public function download_template(): void {
		if ( ! current_user_can( 'edit_posts' ) ) {
			wp_die( esc_html__( 'You do not have permission to download this template.', 'gear-engine' ) );
		}
		check_admin_referer( self::GEAR_ENGINE_TEMPLATE_ACTION );

		nocache_headers();
		header( 'Content-Type: text/csv; charset=utf-8' );
		header( 'Content-Disposition: attachment; filename="gear-engine-exercises-template.csv"' );
		$output = fopen( 'php://output', 'wb' );
		if ( false === $output ) {
			wp_die( esc_html__( 'The CSV template could not be generated.', 'gear-engine' ) );
		}
		fputcsv( $output, array( 'external_key', 'title', 'description', 'summary', 'area', 'equipment', 'dosage', 'exorlive_id', 'video_culture', 'source_url' ) );
		fclose( $output );
		exit;
	}

	/**
	 * Validate and normalize one CSV record.
	 *
	 * @param array $raw         Raw CSV fields.
	 * @param int   $line_number CSV line number.
	 * @return array
	 */
	private static function normalize_row( array $raw, int $line_number ): array {
		$get_text = static function ( string $key ) use ( $raw ): string {
			$value = $raw[ $key ] ?? '';
			return is_scalar( $value ) ? trim( sanitize_textarea_field( (string) $value ) ) : '';
		};

		$data = array(
			'external_key' => sanitize_key( $get_text( 'external_key' ) ),
			'title'        => sanitize_text_field( $get_text( 'title' ) ),
			'description'  => $get_text( 'description' ),
			'summary'      => sanitize_textarea_field( $get_text( 'summary' ) ),
			'area'         => self::parse_terms( $get_text( 'area' ) ),
			'equipment'    => self::parse_terms( $get_text( 'equipment' ) ),
			'dosage'       => sanitize_text_field( $get_text( 'dosage' ) ),
			'exorlive_id'  => $get_text( 'exorlive_id' ),
			'video_culture'=> $get_text( 'video_culture' ),
			'source_url'   => $get_text( 'source_url' ),
		);
		$errors = array();

		if ( '' === $data['title'] ) {
			$errors[] = __( 'Title is required.', 'gear-engine' );
		}
		if ( '' === $data['description'] ) {
			$errors[] = __( 'Description is required.', 'gear-engine' );
		}
		if ( '' !== $data['exorlive_id'] ) {
			$data['exorlive_id'] = Gear_Engine_Exercises_CPT::sanitize_exorlive_id_static( $data['exorlive_id'] );
			if ( '' === $data['exorlive_id'] ) {
				$errors[] = __( 'ExorLive ID must contain only digits.', 'gear-engine' );
			}
		}
		if ( '' === $data['exorlive_id'] && '' === $data['external_key'] ) {
			$errors[] = __( 'Provide an ExorLive ID or external_key for reliable updates.', 'gear-engine' );
		}
		if ( '' !== $data['video_culture'] ) {
			$data['video_culture'] = Gear_Engine_Exercises_CPT::sanitize_video_culture_static( $data['video_culture'] );
			if ( 'nb-NO' === $data['video_culture'] && ! preg_match( '/^[a-z]{2}-[A-Z]{2}$/', $get_text( 'video_culture' ) ) ) {
				$errors[] = __( 'Video culture must use a locale such as nb-NO.', 'gear-engine' );
			}
		} else {
			$data['video_culture'] = 'nb-NO';
		}
		if ( '' !== $data['source_url'] ) {
			$data['source_url'] = esc_url_raw( $data['source_url'] );
			if ( ! wp_http_validate_url( $data['source_url'] ) ) {
				$errors[] = __( 'Source URL must be a valid HTTP or HTTPS URL.', 'gear-engine' );
			}
		}

		return array(
			'line'   => $line_number,
			'data'   => $data,
			'errors' => $errors,
		);
	}

	/**
	 * Parse semicolon-delimited taxonomy terms.
	 *
	 * @param string $value Raw field.
	 * @return array
	 */
	private static function parse_terms( string $value ): array {
		$terms = array_map( 'sanitize_text_field', explode( ';', $value ) );
		return array_values( array_unique( array_filter( array_map( 'trim', $terms ) ) ) );
	}

	/**
	 * Return stable identifiers used for duplicate detection.
	 *
	 * @param array $data Normalized exercise data.
	 * @return array
	 */
	private static function get_row_identifiers( array $data ): array {
		$identifiers = array();
		if ( '' !== $data['exorlive_id'] ) {
			$identifiers[] = 'exorlive:' . $data['exorlive_id'];
		}
		if ( '' !== $data['external_key'] ) {
			$identifiers[] = 'key:' . $data['external_key'];
		}
		return $identifiers;
	}

	/**
	 * Insert or update valid preview rows.
	 *
	 * @param array $rows    Preview rows.
	 * @param bool  $publish Whether to publish imported rows.
	 * @return array
	 */
	public static function import_rows( array $rows, bool $publish = false ): array {
		$results = array(
			'imported' => 0,
			'updated'  => 0,
			'skipped'  => 0,
			'failed'   => 0,
			'errors'   => array(),
		);

		foreach ( $rows as $row ) {
			if ( ! empty( $row['errors'] ) || empty( $row['data'] ) ) {
				++$results['skipped'];
				continue;
			}

			$data       = $row['data'];
			$existing_id = self::find_existing_post_id( $data );
			if ( is_wp_error( $existing_id ) ) {
				++$results['failed'];
				$results['errors'][] = sprintf(
					/* translators: 1: CSV row number, 2: error message */
					__( 'Row %1$d: %2$s', 'gear-engine' ),
					(int) ( $row['line'] ?? 0 ),
					$existing_id->get_error_message()
				);
				continue;
			}
			$post_status = $publish ? 'publish' : ( $existing_id ? get_post_status( $existing_id ) : 'draft' );
			$post_status = $post_status ?: 'draft';
			$post_args   = array(
				'post_type'    => Gear_Engine_Exercises_CPT::GEAR_ENGINE_POST_TYPE,
				'post_status'  => $post_status,
				'post_title'   => $data['title'],
				'post_content' => $data['description'],
				'post_excerpt' => $data['summary'],
			);

			if ( $existing_id ) {
				$post_args['ID'] = $existing_id;
				$post_id         = wp_update_post( $post_args, true );
			} else {
				$post_id = wp_insert_post( $post_args, true );
			}

			if ( is_wp_error( $post_id ) || ! $post_id ) {
				++$results['failed'];
				$results['errors'][] = sprintf(
					/* translators: %d: CSV row number */
					__( 'Row %d could not be saved.', 'gear-engine' ),
					(int) ( $row['line'] ?? 0 )
				);
				continue;
			}

			update_post_meta( $post_id, Gear_Engine_Exercises_CPT::GEAR_ENGINE_META_EXORLIVE_ID, $data['exorlive_id'] );
			update_post_meta( $post_id, Gear_Engine_Exercises_CPT::GEAR_ENGINE_META_VIDEO_CULTURE, $data['video_culture'] );
			update_post_meta( $post_id, Gear_Engine_Exercises_CPT::GEAR_ENGINE_META_DOSAGE, $data['dosage'] );
			update_post_meta( $post_id, Gear_Engine_Exercises_CPT::GEAR_ENGINE_META_SOURCE_URL, $data['source_url'] );
			update_post_meta( $post_id, self::GEAR_ENGINE_META_IMPORT_KEY, $data['external_key'] );

			$area_result      = wp_set_object_terms( $post_id, $data['area'], Gear_Engine_Exercises_CPT::GEAR_ENGINE_AREA_TAXONOMY );
			$equipment_result = wp_set_object_terms( $post_id, $data['equipment'], Gear_Engine_Exercises_CPT::GEAR_ENGINE_EQUIPMENT_TAXONOMY );
			if ( is_wp_error( $area_result ) || is_wp_error( $equipment_result ) ) {
				++$results['failed'];
				$term_error = is_wp_error( $area_result ) ? $area_result : $equipment_result;
				$results['errors'][] = sprintf(
					/* translators: 1: CSV row number, 2: error message */
					__( 'Row %1$d: %2$s', 'gear-engine' ),
					(int) ( $row['line'] ?? 0 ),
					$term_error->get_error_message()
				);
				continue;
			}

			++$results[ $existing_id ? 'updated' : 'imported' ];
		}

		return $results;
	}

	/**
	 * Find an existing exercise by ExorLive ID or external import key.
	 *
	 * @param array $data Normalized data.
	 * @return int|WP_Error
	 */
	private static function find_existing_post_id( array $data ) {
		$matches = array();
		if ( '' !== $data['exorlive_id'] ) {
			$matches = get_posts(
				array(
					'post_type'      => Gear_Engine_Exercises_CPT::GEAR_ENGINE_POST_TYPE,
					'post_status'    => 'any',
					'posts_per_page' => 2,
					'fields'         => 'ids',
					'meta_key'       => Gear_Engine_Exercises_CPT::GEAR_ENGINE_META_EXORLIVE_ID,
					'meta_value'     => $data['exorlive_id'],
				)
			);
		}

		$key_matches = array();
		if ( '' !== $data['external_key'] ) {
			$key_matches = get_posts(
				array(
					'post_type'      => Gear_Engine_Exercises_CPT::GEAR_ENGINE_POST_TYPE,
					'post_status'    => 'any',
					'posts_per_page' => 2,
					'fields'         => 'ids',
					'meta_key'       => self::GEAR_ENGINE_META_IMPORT_KEY,
					'meta_value'     => $data['external_key'],
				)
			);
		}

		$ids = array_unique( array_map( 'absint', array_merge( $matches, $key_matches ) ) );
		if ( count( $ids ) > 1 || count( $matches ) > 1 || count( $key_matches ) > 1 ) {
			return new WP_Error( 'gear_engine_exercise_import_ambiguous', __( 'The import identifiers match multiple exercises.', 'gear-engine' ) );
		}
		return empty( $ids ) ? 0 : (int) reset( $ids );
	}

	/**
	 * Verify capability and action nonce for a mutating request.
	 *
	 * @param string $action Action name.
	 * @return void
	 */
	private function verify_request( string $action ): void {
		if ( ! current_user_can( 'edit_posts' ) ) {
			wp_die( esc_html__( 'You do not have permission to import exercises.', 'gear-engine' ) );
		}
		check_admin_referer( $action, '_gear_engine_exercise_import_nonce' );
	}

	/**
	 * Render a validated preview with row-level errors.
	 *
	 * @param array  $rows    Preview rows.
	 * @param string $token   Confirmation token.
	 * @param bool   $publish Publish choice.
	 * @return void
	 */
	private function render_preview( array $rows, string $token, bool $publish ): void {
		$valid_count = count(
			array_filter(
				$rows,
				static function ( $row ): bool {
					return empty( $row['errors'] );
				}
			)
		);
		?>
		<div class="wrap">
			<h1><?php esc_html_e( 'Exercise CSV Preview', 'gear-engine' ); ?></h1>
			<p>
				<?php
				printf(
					/* translators: 1: valid row count, 2: total row count */
					esc_html__( '%1$d valid rows out of %2$d. Invalid rows will be skipped.', 'gear-engine' ),
					(int) $valid_count,
					count( $rows )
				);
				$results = get_transient( self::get_result_transient_key() );
				if ( is_array( $results ) && ! empty( $results['errors'] ) && is_array( $results['errors'] ) ) {
					echo '<div class="notice notice-error"><p>' . esc_html__( 'Some records could not be imported:', 'gear-engine' ) . '</p><ul>';
					foreach ( $results['errors'] as $error ) {
						echo '<li>' . esc_html( (string) $error ) . '</li>';
					}
					echo '</ul></div>';
				}
				delete_transient( self::get_result_transient_key() );
				?>
			</p>
			<table class="widefat striped">
				<thead><tr><th><?php esc_html_e( 'Row', 'gear-engine' ); ?></th><th><?php esc_html_e( 'Exercise', 'gear-engine' ); ?></th><th><?php esc_html_e( 'Identifier', 'gear-engine' ); ?></th><th><?php esc_html_e( 'Validation', 'gear-engine' ); ?></th></tr></thead>
				<tbody>
					<?php foreach ( $rows as $row ) : ?>
						<tr>
							<td><?php echo esc_html( (string) $row['line'] ); ?></td>
							<td><?php echo esc_html( $row['data']['title'] ); ?></td>
							<td><?php echo esc_html( $row['data']['exorlive_id'] ?: $row['data']['external_key'] ); ?></td>
							<td><?php echo esc_html( empty( $row['errors'] ) ? __( 'Ready to import', 'gear-engine' ) : implode( ' ', $row['errors'] ) ); ?></td>
						</tr>
					<?php endforeach; ?>
				</tbody>
			</table>
			<?php if ( $valid_count > 0 ) : ?>
				<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
					<input type="hidden" name="action" value="<?php echo esc_attr( self::GEAR_ENGINE_IMPORT_ACTION ); ?>" />
					<input type="hidden" name="preview_token" value="<?php echo esc_attr( $token ); ?>" />
					<?php if ( $publish ) : ?><input type="hidden" name="publish_valid_rows" value="1" /><?php endif; ?>
					<?php wp_nonce_field( self::GEAR_ENGINE_IMPORT_ACTION, '_gear_engine_exercise_import_nonce' ); ?>
					<?php submit_button( __( 'Import valid rows', 'gear-engine' ) ); ?>
				</form>
			<?php endif; ?>
			<p><a href="<?php echo esc_url( admin_url( 'edit.php?post_type=' . Gear_Engine_Exercises_CPT::GEAR_ENGINE_POST_TYPE . '&page=' . self::GEAR_ENGINE_PAGE_SLUG ) ); ?>"><?php esc_html_e( 'Cancel and return', 'gear-engine' ); ?></a></p>
		</div>
		<?php
	}

	/**
	 * Show the result notice after an import.
	 *
	 * @return void
	 */
	private function render_import_notice(): void {
		if ( ! isset( $_GET['imported'], $_GET['updated'], $_GET['skipped'], $_GET['failed'] ) ) {
			return;
		}
		foreach ( array( 'imported', 'updated', 'skipped', 'failed' ) as $count_key ) {
			if ( ! is_scalar( $_GET[ $count_key ] ) ) {
				return;
			}
		}
		$imported = absint( wp_unslash( $_GET['imported'] ) );
		$updated  = absint( wp_unslash( $_GET['updated'] ) );
		$skipped  = absint( wp_unslash( $_GET['skipped'] ) );
		$failed   = absint( wp_unslash( $_GET['failed'] ) );
		printf(
			'<div class="notice notice-info"><p>%s</p></div>',
			esc_html(
				sprintf(
					/* translators: 1: imported count, 2: updated count, 3: skipped count, 4: failed count */
					__( 'Import finished: %1$d created, %2$d updated, %3$d skipped, %4$d failed.', 'gear-engine' ),
					$imported,
					$updated,
					$skipped,
					$failed
				)
			)
		);
	}

	/**
	 * Get a per-user preview transient key.
	 *
	 * @return string
	 */
	private static function get_preview_transient_key(): string {
		return 'gear_engine_exercise_import_preview_' . absint( get_current_user_id() );
	}

	/**
	 * Get a per-user import result transient key.
	 *
	 * @return string
	 */
	private static function get_result_transient_key(): string {
		return 'gear_engine_exercise_import_result_' . absint( get_current_user_id() );
	}
}
