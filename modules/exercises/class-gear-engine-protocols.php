<?php
/**
 * Clinician-curated rehab protocols stored as taxonomy terms on exercises.
 *
 * @package GearEngine
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Registers the Protocols taxonomy, its editorial term fields and approved-protocol lookup.
 *
 * A protocol is a top-level term (the condition); child terms are phases. Exercises
 * tagged with the condition or any of its phases belong to the protocol.
 */
class Gear_Engine_Protocols {

	public const GEAR_ENGINE_TAXONOMY        = 'gear_engine_protocol';
	public const GEAR_ENGINE_STATUS_DRAFT    = 'draft';
	public const GEAR_ENGINE_STATUS_APPROVED = 'approved';
	public const GEAR_ENGINE_META_SUMMARY    = '_gear_engine_protocol_summary';
	public const GEAR_ENGINE_META_RED_FLAGS  = '_gear_engine_protocol_red_flags';
	public const GEAR_ENGINE_META_CODES      = '_gear_engine_protocol_codes';
	public const GEAR_ENGINE_META_SOURCES    = '_gear_engine_protocol_sources';
	public const GEAR_ENGINE_META_DOSAGE     = '_gear_engine_protocol_dosage';
	public const GEAR_ENGINE_META_REVIEWER   = '_gear_engine_protocol_reviewed_by';
	public const GEAR_ENGINE_META_REVIEWED   = '_gear_engine_protocol_reviewed_on';
	public const GEAR_ENGINE_META_REVIEW_DUE = '_gear_engine_protocol_review_due';
	public const GEAR_ENGINE_META_STATUS     = '_gear_engine_protocol_status';
	private const GEAR_ENGINE_NONCE          = 'gear_engine_protocol_nonce';
	private const GEAR_ENGINE_NONCE_ACTION   = 'gear_engine_save_protocol';

	public function __construct() {
		add_action( 'init', array( $this, 'register_taxonomy' ), 6 );
		add_action( self::GEAR_ENGINE_TAXONOMY . '_add_form_fields', array( $this, 'render_add_fields' ) );
		add_action( self::GEAR_ENGINE_TAXONOMY . '_edit_form_fields', array( $this, 'render_edit_fields' ) );
		add_action( 'created_' . self::GEAR_ENGINE_TAXONOMY, array( $this, 'save_term_fields' ) );
		add_action( 'edited_' . self::GEAR_ENGINE_TAXONOMY, array( $this, 'save_term_fields' ) );
		add_filter( 'manage_edit-' . self::GEAR_ENGINE_TAXONOMY . '_columns', array( $this, 'register_columns' ) );
		add_filter( 'manage_' . self::GEAR_ENGINE_TAXONOMY . '_custom_column', array( $this, 'render_column' ), 10, 3 );
		add_action( 'admin_notices', array( $this, 'render_notice' ) );
	}

	/**
	 * Register the Protocols taxonomy for exercises.
	 *
	 * @return void
	 */
	public function register_taxonomy(): void {
		register_taxonomy(
			self::GEAR_ENGINE_TAXONOMY,
			array( Gear_Engine_Exercises_CPT::GEAR_ENGINE_POST_TYPE ),
			array(
				'labels'            => array(
					'name'          => __( 'Protocols', 'gear-engine' ),
					'singular_name' => __( 'Protocol', 'gear-engine' ),
					'add_new_item'  => __( 'Add Protocol or Phase', 'gear-engine' ),
					'parent_item'   => __( 'Parent protocol', 'gear-engine' ),
				),
				'hierarchical'      => true,
				'show_ui'           => self::is_enabled(),
				'show_admin_column' => self::is_enabled(),
				'show_in_rest'      => true, // Required for the block editor tag panel; term meta is not exposed.
				'rewrite'           => false,
			)
		);

		$fields = array(
			self::GEAR_ENGINE_META_SUMMARY    => 'sanitize_textarea_field',
			self::GEAR_ENGINE_META_RED_FLAGS  => 'sanitize_textarea_field',
			self::GEAR_ENGINE_META_CODES      => array( __CLASS__, 'sanitize_codes' ),
			self::GEAR_ENGINE_META_SOURCES    => 'sanitize_textarea_field',
			self::GEAR_ENGINE_META_DOSAGE     => 'sanitize_textarea_field',
			self::GEAR_ENGINE_META_REVIEWER   => 'sanitize_text_field',
			self::GEAR_ENGINE_META_REVIEWED   => array( __CLASS__, 'sanitize_date' ),
			self::GEAR_ENGINE_META_REVIEW_DUE => array( __CLASS__, 'sanitize_date' ),
			self::GEAR_ENGINE_META_STATUS     => array( __CLASS__, 'sanitize_status' ),
		);
		foreach ( $fields as $meta_key => $callback ) {
			register_term_meta(
				self::GEAR_ENGINE_TAXONOMY,
				$meta_key,
				array(
					'type'              => 'string',
					'single'            => true,
					'show_in_rest'      => false,
					'sanitize_callback' => $callback,
				)
			);
		}
	}

	/**
	 * Normalize a comma/semicolon separated list of ICD-10/ICPC-2 codes (e.g. "m23.2, L96").
	 *
	 * @param mixed $value Raw value.
	 * @return string
	 */
	public static function sanitize_codes( $value ): string {
		if ( ! is_scalar( $value ) ) {
			return '';
		}
		$codes = array();
		foreach ( preg_split( '/[,;\s]+/', strtoupper( (string) $value ) ) as $code ) {
			$code = preg_replace( '/[^A-Z0-9.]/', '', $code );
			if ( preg_match( '/^[A-Z][0-9]{2}(\.?[0-9A-Z]{1,4})?$/', $code ) ) {
				$codes[] = $code;
			}
		}
		return implode( ', ', array_values( array_unique( $codes ) ) );
	}

	/**
	 * Accept only a valid YYYY-MM-DD date.
	 *
	 * @param mixed $value Raw value.
	 * @return string
	 */
	public static function sanitize_date( $value ): string {
		if ( ! is_scalar( $value ) || ! preg_match( '/^(\d{4})-(\d{2})-(\d{2})$/', trim( (string) $value ), $m ) ) {
			return '';
		}
		return checkdate( (int) $m[2], (int) $m[3], (int) $m[1] ) ? trim( (string) $value ) : '';
	}

	/**
	 * Restrict status to the known workflow values.
	 *
	 * @param mixed $value Raw value.
	 * @return string
	 */
	public static function sanitize_status( $value ): string {
		return is_scalar( $value ) && self::GEAR_ENGINE_STATUS_APPROVED === (string) $value ? self::GEAR_ENGINE_STATUS_APPROVED : self::GEAR_ENGINE_STATUS_DRAFT;
	}

	/**
	 * Fields shown on the add/edit term screens.
	 *
	 * @return array<string, array<string, string>>
	 */
	private static function get_field_definitions(): array {
		return array(
			'summary'   => array(
				'meta'  => self::GEAR_ENGINE_META_SUMMARY,
				'type'  => 'textarea',
				'label' => __( 'Short clinical summary', 'gear-engine' ),
				'help'  => __( '2-3 sentences shown to the chat as the protocol note, e.g. goals of this rehab approach.', 'gear-engine' ),
			),
			'red_flags' => array(
				'meta'  => self::GEAR_ENGINE_META_RED_FLAGS,
				'type'  => 'textarea',
				'label' => __( 'Contraindications / red flags', 'gear-engine' ),
				'help'  => __( 'When these apply, exercises must not be offered. Required for approval.', 'gear-engine' ),
			),
			'codes'     => array(
				'meta'  => self::GEAR_ENGINE_META_CODES,
				'type'  => 'text',
				'label' => __( 'Matching ICD-10 / ICPC-2 codes', 'gear-engine' ),
				'help'  => __( 'Comma separated, e.g. M23.2, L96. A shorter code also matches longer triage codes (M23 matches M23.2). Set on the top-level protocol only.', 'gear-engine' ),
			),
			'dosage'    => array(
				'meta'  => self::GEAR_ENGINE_META_DOSAGE,
				'type'  => 'textarea',
				'label' => __( 'Dosage and progression guidance', 'gear-engine' ),
				'help'  => __( 'Overrides the default dosage on each exercise when this protocol is used.', 'gear-engine' ),
			),
			'sources'   => array(
				'meta'  => self::GEAR_ENGINE_META_SOURCES,
				'type'  => 'textarea',
				'label' => __( 'Sources / guideline references', 'gear-engine' ),
				'help'  => __( 'Titles and links only. Used for your own review, never sent to the chat.', 'gear-engine' ),
			),
			'due'       => array(
				'meta'  => self::GEAR_ENGINE_META_REVIEW_DUE,
				'type'  => 'date',
				'label' => __( 'Review reminder (optional)', 'gear-engine' ),
				'help'  => __( 'Optional. If set, the chat stops using this protocol after this date until you approve it again. Leave empty for no expiry.', 'gear-engine' ),
			),
		);
	}

	/**
	 * Render fields on the add-term screen.
	 *
	 * @return void
	 */
	public function render_add_fields(): void {
		wp_nonce_field( self::GEAR_ENGINE_NONCE_ACTION, self::GEAR_ENGINE_NONCE );
		foreach ( self::get_field_definitions() as $key => $field ) {
			echo '<div class="form-field">';
			$this->render_input( $key, $field, '' );
			echo '</div>';
		}
		echo '<div class="form-field">';
		$this->render_status_input( self::GEAR_ENGINE_STATUS_DRAFT );
		echo '</div>';
	}

	/**
	 * Render fields on the edit-term screen.
	 *
	 * @param WP_Term $term Term being edited.
	 * @return void
	 */
	public function render_edit_fields( $term ): void {
		wp_nonce_field( self::GEAR_ENGINE_NONCE_ACTION, self::GEAR_ENGINE_NONCE );
		foreach ( self::get_field_definitions() as $key => $field ) {
			echo '<tr class="form-field"><th scope="row">';
			echo '<label for="gear-engine-protocol-' . esc_attr( $key ) . '">' . esc_html( $field['label'] ) . '</label></th><td>';
			$this->render_input( $key, $field, (string) get_term_meta( $term->term_id, $field['meta'], true ), false );
			echo '</td></tr>';
		}
		echo '<tr class="form-field"><th scope="row">' . esc_html__( 'Status', 'gear-engine' ) . '</th><td>';
		$this->render_status_input( self::sanitize_status( get_term_meta( $term->term_id, self::GEAR_ENGINE_META_STATUS, true ) ), false );
		echo '</td></tr>';
	}

	/**
	 * Render a single input.
	 *
	 * @param string               $key   Field key.
	 * @param array<string,string> $field Field definition.
	 * @param string               $value Current value.
	 * @param bool                 $label Whether to print the label.
	 * @return void
	 */
	private function render_input( string $key, array $field, string $value, bool $label = true ): void {
		$id   = 'gear-engine-protocol-' . $key;
		$name = 'gear_engine_protocol_' . $key;
		if ( $label ) {
			echo '<label for="' . esc_attr( $id ) . '">' . esc_html( $field['label'] ) . '</label>';
		}
		if ( 'textarea' === $field['type'] ) {
			echo '<textarea class="large-text" rows="4" id="' . esc_attr( $id ) . '" name="' . esc_attr( $name ) . '">' . esc_textarea( $value ) . '</textarea>';
		} else {
			echo '<input class="regular-text" type="' . esc_attr( $field['type'] ) . '" id="' . esc_attr( $id ) . '" name="' . esc_attr( $name ) . '" value="' . esc_attr( $value ) . '" />';
		}
		if ( '' !== $field['help'] ) {
			echo '<p class="description">' . esc_html( $field['help'] ) . '</p>';
		}
	}

	/**
	 * Render the status selector.
	 *
	 * @param string $status Current status.
	 * @param bool   $label  Whether to print the label.
	 * @return void
	 */
	private function render_status_input( string $status, bool $label = true ): void {
		if ( $label ) {
			echo '<label for="gear-engine-protocol-status">' . esc_html__( 'Status', 'gear-engine' ) . '</label>';
		}
		echo '<select id="gear-engine-protocol-status" name="gear_engine_protocol_status">';
		echo '<option value="draft"' . selected( $status, self::GEAR_ENGINE_STATUS_DRAFT, false ) . '>' . esc_html__( 'Draft (not used by the chat)', 'gear-engine' ) . '</option>';
		echo '<option value="approved"' . selected( $status, self::GEAR_ENGINE_STATUS_APPROVED, false ) . '>' . esc_html__( 'Approved (used by the chat)', 'gear-engine' ) . '</option>';
		echo '</select>';
		echo '<p class="description">' . esc_html__( 'Approval requires codes, red flags, reviewer, review date and at least one exercise.', 'gear-engine' ) . '</p>';
	}

	/**
	 * Save term fields and enforce the approval requirements.
	 *
	 * @param int $term_id Term ID.
	 * @return void
	 */
	public function save_term_fields( int $term_id ): void {
		if ( ! isset( $_POST[ self::GEAR_ENGINE_NONCE ] ) ) {
			return;
		}
		$nonce = sanitize_text_field( wp_unslash( $_POST[ self::GEAR_ENGINE_NONCE ] ) );
		if ( ! wp_verify_nonce( $nonce, self::GEAR_ENGINE_NONCE_ACTION ) || ! current_user_can( 'edit_term', $term_id ) ) {
			return;
		}

		$values = array();
		foreach ( self::get_field_definitions() as $key => $field ) {
			$raw      = isset( $_POST[ 'gear_engine_protocol_' . $key ] ) ? wp_unslash( $_POST[ 'gear_engine_protocol_' . $key ] ) : '';
			$raw      = is_scalar( $raw ) ? (string) $raw : '';
			$values[ $key ] = 'textarea' === $field['type'] ? sanitize_textarea_field( $raw ) : sanitize_text_field( $raw );
			if ( 'codes' === $key ) {
				$values[ $key ] = self::sanitize_codes( $values[ $key ] );
			} elseif ( 'due' === $key ) {
				$values[ $key ] = self::sanitize_date( $values[ $key ] );
			}
			update_term_meta( $term_id, $field['meta'], $values[ $key ] );
		}

		$raw_status = isset( $_POST['gear_engine_protocol_status'] ) ? wp_unslash( $_POST['gear_engine_protocol_status'] ) : '';
		$status     = self::sanitize_status( $raw_status );
		if ( self::GEAR_ENGINE_STATUS_APPROVED === $status ) {
			$missing = $this->get_missing_requirements( $term_id, $values );
			if ( $missing ) {
				$status = self::GEAR_ENGINE_STATUS_DRAFT;
				set_transient(
					'gear_engine_protocol_notice_' . get_current_user_id(),
					sprintf(
						/* translators: %s: comma separated missing fields */
						__( 'Protocol kept as Draft. Missing for approval: %s.', 'gear-engine' ),
						implode( ', ', $missing )
					),
					60
				);
			}
		}
		if ( self::GEAR_ENGINE_STATUS_APPROVED === $status && self::GEAR_ENGINE_STATUS_APPROVED !== self::sanitize_status( get_term_meta( $term_id, self::GEAR_ENGINE_META_STATUS, true ) ) ) {
			$user = function_exists( 'wp_get_current_user' ) ? wp_get_current_user() : null;
			update_term_meta( $term_id, self::GEAR_ENGINE_META_REVIEWER, is_object( $user ) && ! empty( $user->display_name ) ? sanitize_text_field( $user->display_name ) : '' );
			update_term_meta( $term_id, self::GEAR_ENGINE_META_REVIEWED, gmdate( 'Y-m-d' ) );
		}
		update_term_meta( $term_id, self::GEAR_ENGINE_META_STATUS, $status );
	}

	/**
	 * List the approval requirements that are not met.
	 *
	 * @param int                  $term_id Term ID.
	 * @param array<string,string> $values  Saved field values.
	 * @return array<int, string>
	 */
	private static function get_missing_requirements( int $term_id, array $values ): array {
		$missing = array();
		$term    = get_term( $term_id, self::GEAR_ENGINE_TAXONOMY );
		$is_top  = ! is_object( $term ) || empty( $term->parent );

		if ( $is_top && '' === $values['codes'] ) {
			$missing[] = __( 'matching codes', 'gear-engine' );
		}
		if ( '' === trim( $values['red_flags'] ) ) {
			$missing[] = __( 'red flags', 'gear-engine' );
		}
		if ( ! self::get_published_exercise_ids( $term_id ) ) {
			$missing[] = __( 'at least one published exercise', 'gear-engine' );
		}

		return $missing;
	}

	/**
	 * Approval requirements still missing for a saved protocol term.
	 *
	 * @param int $term_id Term ID.
	 * @return array<int, string>
	 */
	public static function get_missing_for_term( int $term_id ): array {
		$values = array(
			'codes'     => (string) get_term_meta( $term_id, self::GEAR_ENGINE_META_CODES, true ),
			'red_flags' => (string) get_term_meta( $term_id, self::GEAR_ENGINE_META_RED_FLAGS, true ),
		);
		return self::get_missing_requirements( $term_id, $values );
	}

	/**
	 * Published exercise IDs tagged with a protocol term or its phases.
	 *
	 * @param int $term_id Term ID.
	 * @return array<int, int>
	 */
	private static function get_published_exercise_ids( int $term_id ): array {
		$query = new WP_Query(
			array(
				'post_type'      => Gear_Engine_Exercises_CPT::GEAR_ENGINE_POST_TYPE,
				'post_status'    => 'publish',
				'posts_per_page' => 1,
				'fields'         => 'ids',
				'no_found_rows'  => true,
				'tax_query'      => array(
					array(
						'taxonomy' => self::GEAR_ENGINE_TAXONOMY,
						'field'    => 'term_id',
						'terms'    => $term_id,
					),
				),
			)
		);
		return array_map( 'absint', $query->posts );
	}

	/**
	 * Show the approval result after saving.
	 *
	 * @return void
	 */
	public function render_notice(): void {
		$key     = 'gear_engine_protocol_notice_' . get_current_user_id();
		$message = get_transient( $key );
		if ( ! is_string( $message ) || '' === $message ) {
			return;
		}
		delete_transient( $key );
		echo '<div class="notice notice-warning is-dismissible"><p>' . esc_html( $message ) . '</p></div>';
	}

	/**
	 * Add status columns to the protocol list table.
	 *
	 * @param array<string,string> $columns Columns.
	 * @return array<string,string>
	 */
	public function register_columns( array $columns ): array {
		$columns['gear_engine_status'] = __( 'Status', 'gear-engine' );
		$columns['gear_engine_codes']  = __( 'Codes', 'gear-engine' );
		return $columns;
	}

	/**
	 * Render the status/codes column values.
	 *
	 * @param string $content   Existing content.
	 * @param string $column    Column name.
	 * @param int    $term_id   Term ID.
	 * @return string
	 */
	public function render_column( $content, $column, $term_id ): string {
		$content = is_string( $content ) ? $content : '';
		if ( 'gear_engine_status' === $column ) {
			$status = self::get_status( (int) $term_id );
			return esc_html( self::GEAR_ENGINE_STATUS_APPROVED === $status ? __( 'Approved', 'gear-engine' ) : ( 'expired' === $status ? __( 'Review overdue', 'gear-engine' ) : __( 'Draft', 'gear-engine' ) ) );
		}
		if ( 'gear_engine_codes' === $column ) {
			return esc_html( (string) get_term_meta( (int) $term_id, self::GEAR_ENGINE_META_CODES, true ) );
		}
		return $content;
	}

	/**
	 * Whether the optional clinical protocols feature is switched on in settings.
	 *
	 * @return bool
	 */
	public static function is_enabled(): bool {
		return (bool) get_option( 'gear_engine_enable_protocols', false );
	}

	/**
	 * Effective status: approved, expired (approved but past an optional reminder date) or draft.
	 *
	 * @param int $term_id Term ID.
	 * @return string
	 */
	public static function get_status( int $term_id ): string {
		if ( self::GEAR_ENGINE_STATUS_APPROVED !== self::sanitize_status( get_term_meta( $term_id, self::GEAR_ENGINE_META_STATUS, true ) ) ) {
			return self::GEAR_ENGINE_STATUS_DRAFT;
		}
		$due = self::sanitize_date( get_term_meta( $term_id, self::GEAR_ENGINE_META_REVIEW_DUE, true ) );
		if ( '' !== $due && $due < gmdate( 'Y-m-d' ) ) {
			return 'expired';
		}
		return self::GEAR_ENGINE_STATUS_APPROVED;
	}

	/**
	 * Find approved, in-date protocols whose codes match any triage code.
	 *
	 * Most specific match first. Only top-level terms carry codes; phases inherit.
	 *
	 * @param array<int, string> $codes Triage ICD-10/ICPC-2 codes.
	 * @return array<int, array<string, mixed>>
	 */
	public static function find_approved_by_codes( array $codes ): array {
		if ( ! self::is_enabled() ) {
			return array();
		}
		$needles = array();
		foreach ( $codes as $code ) {
			$code = is_scalar( $code ) ? preg_replace( '/[^A-Z0-9]/', '', strtoupper( (string) $code ) ) : '';
			if ( '' !== $code ) {
				$needles[] = $code;
			}
		}
		if ( ! $needles ) {
			return array();
		}

		$terms = get_terms(
			array(
				'taxonomy'   => self::GEAR_ENGINE_TAXONOMY,
				'hide_empty' => false,
				'parent'     => 0,
				'meta_key'   => self::GEAR_ENGINE_META_STATUS,
				'meta_value' => self::GEAR_ENGINE_STATUS_APPROVED,
			)
		);
		if ( is_wp_error( $terms ) || ! is_array( $terms ) ) {
			return array();
		}

		$matches = array();
		foreach ( $terms as $term ) {
			if ( self::GEAR_ENGINE_STATUS_APPROVED !== self::get_status( (int) $term->term_id ) ) {
				continue;
			}
			$best = 0;
			foreach ( preg_split( '/[,\s]+/', (string) get_term_meta( $term->term_id, self::GEAR_ENGINE_META_CODES, true ) ) as $protocol_code ) {
				$protocol_code = preg_replace( '/[^A-Z0-9]/', '', strtoupper( $protocol_code ) );
				foreach ( $needles as $needle ) {
					if ( '' !== $protocol_code && 0 === strpos( $needle, $protocol_code ) ) {
						$best = max( $best, strlen( $protocol_code ) );
					}
				}
			}
			if ( $best > 0 ) {
				$matches[] = array( 'specificity' => $best, 'data' => self::get_protocol_data( $term ) );
			}
		}

		usort(
			$matches,
			static function ( array $a, array $b ): int {
				return $b['specificity'] <=> $a['specificity'];
			}
		);

		return array_column( $matches, 'data' );
	}

	/**
	 * Fields the chat is allowed to see for a protocol. Sources are intentionally excluded.
	 *
	 * @param object $term Protocol term.
	 * @return array<string, mixed>
	 */
	public static function get_protocol_data( $term ): array {
		return array(
			'term_id'   => (int) $term->term_id,
			'slug'      => (string) $term->slug,
			'name'      => (string) $term->name,
			'summary'   => (string) get_term_meta( $term->term_id, self::GEAR_ENGINE_META_SUMMARY, true ),
			'red_flags' => (string) get_term_meta( $term->term_id, self::GEAR_ENGINE_META_RED_FLAGS, true ),
			'dosage'    => (string) get_term_meta( $term->term_id, self::GEAR_ENGINE_META_DOSAGE, true ),
		);
	}
}
