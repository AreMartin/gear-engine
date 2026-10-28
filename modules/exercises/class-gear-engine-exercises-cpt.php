<?php
/**
 * Exercise library post type and editorial fields.
 *
 * @package GearEngine
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Registers the shared exercise library used by feature modules.
 */
class Gear_Engine_Exercises_CPT {

	public const GEAR_ENGINE_POST_TYPE          = 'gear_engine_exercise';
	public const GEAR_ENGINE_AREA_TAXONOMY      = 'gear_engine_exercise_area';
	public const GEAR_ENGINE_EQUIPMENT_TAXONOMY = 'gear_engine_exercise_equipment';
	public const GEAR_ENGINE_META_EXORLIVE_ID   = '_gear_engine_exorlive_id';
	public const GEAR_ENGINE_META_VIDEO_CULTURE = '_gear_engine_video_culture';
	public const GEAR_ENGINE_META_VIDEO_URL     = '_gear_engine_exercise_video_url';
	public const GEAR_ENGINE_META_DOSAGE        = '_gear_engine_exercise_dosage';
	public const GEAR_ENGINE_META_SOURCE_URL    = '_gear_engine_exercise_source_url';
	public const GEAR_ENGINE_META_RISK          = '_gear_engine_exercise_risk';
	public const GEAR_ENGINE_META_SAMPLE_KEY    = '_gear_engine_exercise_sample_key';
	public const GEAR_ENGINE_SAMPLE_OPTION      = 'gear_engine_exercise_samples_seeded';
	private const GEAR_ENGINE_META_BOX_NONCE    = 'gear_engine_exercise_nonce';
	private const GEAR_ENGINE_META_BOX_ACTION   = 'gear_engine_save_exercise';

	public function __construct() {
		add_action( 'init', array( $this, 'register_content_types' ), 5 );
		add_action( 'init', array( $this, 'maybe_seed_sample_exercises' ), 20 );
		add_action( 'add_meta_boxes_' . self::GEAR_ENGINE_POST_TYPE, array( $this, 'register_meta_box' ) );
		add_action( 'save_post_' . self::GEAR_ENGINE_POST_TYPE, array( $this, 'save_meta_box' ), 10, 2 );
		add_filter( 'manage_' . self::GEAR_ENGINE_POST_TYPE . '_posts_columns', array( $this, 'register_admin_columns' ) );
		add_action( 'manage_' . self::GEAR_ENGINE_POST_TYPE . '_posts_custom_column', array( $this, 'render_admin_column' ), 10, 2 );
	}

	/**
	 * Register the exercise post type and its reusable classification taxonomies.
	 *
	 * @return void
	 */
	public function register_content_types(): void {
		register_post_type(
			self::GEAR_ENGINE_POST_TYPE,
			array(
				'labels' => array(
					'name'          => __( 'Exercises', 'gear-engine' ),
					'singular_name' => __( 'Exercise', 'gear-engine' ),
					'add_new_item'  => __( 'Add Exercise', 'gear-engine' ),
					'edit_item'     => __( 'Edit Exercise', 'gear-engine' ),
					'menu_name'     => __( 'Exercise Library', 'gear-engine' ),
				),
				'public'       => true,
				'show_in_rest' => true,
				'has_archive'  => true,
				'rewrite'      => array( 'slug' => 'exercises', 'with_front' => false ),
				'menu_icon'    => 'dashicons-universal-access',
				'supports'     => array( 'title', 'editor', 'excerpt', 'revisions' ),
			)
		);

		register_taxonomy(
			self::GEAR_ENGINE_AREA_TAXONOMY,
			array( self::GEAR_ENGINE_POST_TYPE ),
			array(
				'labels'       => array(
					'name'          => __( 'Body Areas', 'gear-engine' ),
					'singular_name' => __( 'Body Area', 'gear-engine' ),
				),
				'hierarchical' => true,
				'show_in_rest' => true,
				'rewrite'      => array( 'slug' => 'exercise-area' ),
			)
		);

		register_taxonomy(
			self::GEAR_ENGINE_EQUIPMENT_TAXONOMY,
			array( self::GEAR_ENGINE_POST_TYPE ),
			array(
				'labels'       => array(
					'name'          => __( 'Exercise Equipment', 'gear-engine' ),
					'singular_name' => __( 'Exercise Equipment', 'gear-engine' ),
				),
				'hierarchical' => false,
				'show_in_rest' => true,
				'rewrite'      => array( 'slug' => 'exercise-equipment' ),
			)
		);

		$this->register_meta_fields();
	}

	/**
	 * Register structured fields for the REST API and integrations.
	 *
	 * @return void
	 */
	private function register_meta_fields(): void {
		$fields = array(
			self::GEAR_ENGINE_META_EXORLIVE_ID => array(
				'type'              => 'string',
				'description'       => __( 'Numeric exercise ID from the official ExorLive sharing link.', 'gear-engine' ),
				'sanitize_callback' => array( $this, 'sanitize_exorlive_id' ),
			),
			self::GEAR_ENGINE_META_VIDEO_CULTURE => array(
				'type'              => 'string',
				'description'       => __( 'Locale used by the ExorLive video player, for example nb-NO.', 'gear-engine' ),
				'sanitize_callback' => array( $this, 'sanitize_video_culture' ),
			),
			self::GEAR_ENGINE_META_VIDEO_URL => array(
				'type'              => 'string',
				'description'       => __( 'Optional HTTPS video link from ExorLive, YouTube, or Vimeo.', 'gear-engine' ),
				'sanitize_callback' => array( $this, 'sanitize_video_url' ),
			),
			self::GEAR_ENGINE_META_DOSAGE => array(
				'type'              => 'string',
				'description'       => __( 'Suggested sets, repetitions, or duration for this exercise.', 'gear-engine' ),
				'sanitize_callback' => 'sanitize_text_field',
			),
			self::GEAR_ENGINE_META_SOURCE_URL => array(
				'type'              => 'string',
				'description'       => __( 'Optional source PDF or reference URL for editorial traceability.', 'gear-engine' ),
				'sanitize_callback' => array( $this, 'sanitize_source_url' ),
			),
			self::GEAR_ENGINE_META_RISK => array(
				'type'              => 'string',
				'description'       => __( 'Clinician-assigned risk level: low, moderate or high. Unrated exercises are never used without an approved protocol.', 'gear-engine' ),
				'sanitize_callback' => array( __CLASS__, 'sanitize_risk' ),
			),
		);

		foreach ( $fields as $meta_key => $args ) {
			register_post_meta(
				self::GEAR_ENGINE_POST_TYPE,
				$meta_key,
				array_merge(
					$args,
					array(
						'single'        => true,
						'show_in_rest'  => true,
						'auth_callback' => array( $this, 'authorize_meta_edit' ),
					)
				)
			);
		}
	}

	/**
	 * Accept only a known risk level; empty means unrated.
	 *
	 * @param mixed $value Raw value.
	 * @return string
	 */
	public static function sanitize_risk( $value ): string {
		$value = is_scalar( $value ) ? strtolower( trim( (string) $value ) ) : '';
		return in_array( $value, array( 'low', 'moderate', 'high' ), true ) ? $value : '';
	}

	/**
	 * Restrict exercise metadata editing to users who can edit the post.
	 *
	 * @param bool   $allowed  Whether WordPress already allows the operation.
	 * @param string $meta_key Metadata key.
	 * @param int    $post_id  Post ID.
	 * @param int    $user_id  User ID.
	 * @return bool
	 */
	public function authorize_meta_edit( bool $allowed, string $meta_key, int $post_id, int $user_id ): bool {
		return user_can( $user_id, 'edit_post', $post_id );
	}

	/**
	 * Sanitize a numeric ExorLive exercise identifier.
	 *
	 * @param mixed $value Raw identifier.
	 * @return string
	 */
	public function sanitize_exorlive_id( $value ): string {
		return self::sanitize_exorlive_id_static( $value );
	}

	/**
	 * Static ExorLive ID sanitizer shared by the importer and query service.
	 *
	 * @param mixed $value Raw identifier.
	 * @return string
	 */
	public static function sanitize_exorlive_id_static( $value ): string {
		if ( ! is_scalar( $value ) ) {
			return '';
		}
		$value = trim( (string) $value );
		return preg_match( '/^\d+$/', $value ) ? $value : '';
	}

	/**
	 * Sanitize the player locale, falling back to Norwegian Bokmål.
	 *
	 * @param mixed $value Raw locale.
	 * @return string
	 */
	public function sanitize_video_culture( $value ): string {
		return self::sanitize_video_culture_static( $value );
	}

	/**
	 * Static player locale sanitizer shared by the importer.
	 *
	 * @param mixed $value Raw locale.
	 * @return string
	 */
	public static function sanitize_video_culture_static( $value ): string {
		if ( ! is_scalar( $value ) ) {
			return 'nb-NO';
		}
		$value = trim( (string) $value );
		return preg_match( '/^[a-z]{2}-[A-Z]{2}$/', $value ) ? $value : 'nb-NO';
	}

	/**
	 * Sanitize a supported video link into a canonical player URL.
	 *
	 * @param mixed $value Raw video URL.
	 * @return string
	 */
	public function sanitize_video_url( $value ): string {
		return self::sanitize_video_url_static( $value );
	}

	/**
	 * Allow only HTTPS player URLs for the supported video providers.
	 *
	 * @param mixed $value Raw video URL.
	 * @return string
	 */
	public static function sanitize_video_url_static( $value ): string {
		if ( ! is_scalar( $value ) ) {
			return '';
		}

		$url   = esc_url_raw( trim( (string) $value ) );
		$parts = wp_parse_url( $url );
		if (
			! is_array( $parts )
			|| 'https' !== strtolower( (string) ( $parts['scheme'] ?? '' ) )
			|| empty( $parts['host'] )
			|| isset( $parts['user'] )
			|| isset( $parts['pass'] )
			|| isset( $parts['port'] )
			|| ! wp_http_validate_url( $url )
		) {
			return '';
		}

		$host = strtolower( (string) $parts['host'] );
		$path = (string) ( $parts['path'] ?? '' );
		$query = array();
		parse_str( (string) ( $parts['query'] ?? '' ), $query );

		if ( in_array( $host, array( 'exorlive.com', 'www.exorlive.com' ), true ) && '/video' === rtrim( $path, '/' ) ) {
			$exercise_id = Gear_Engine_Exercises_CPT::sanitize_exorlive_id_static( $query['ex'] ?? '' );
			$culture     = self::sanitize_video_culture_static( $query['culture'] ?? 'nb-NO' );
			return Gear_Engine_Exercise_Service::build_exorlive_video_url( $exercise_id, $culture );
		}

		$youtube_hosts = array( 'youtube.com', 'www.youtube.com', 'm.youtube.com', 'music.youtube.com', 'youtu.be' );
		if ( in_array( $host, $youtube_hosts, true ) ) {
			$video_id = '';
			if ( 'youtu.be' === $host ) {
				$video_id = trim( $path, '/' );
			} elseif ( '/watch' === $path ) {
				$video_id = is_scalar( $query['v'] ?? null ) ? (string) $query['v'] : '';
			} elseif ( preg_match( '#^/(?:embed|shorts|live|v)/([A-Za-z0-9_-]+)/*$#', $path, $matches ) ) {
				$video_id = $matches[1];
			}

			return preg_match( '/^[A-Za-z0-9_-]{11}$/', $video_id )
				? 'https://www.youtube.com/watch?v=' . rawurlencode( $video_id )
				: '';
		}

		if ( in_array( $host, array( 'vimeo.com', 'www.vimeo.com', 'player.vimeo.com' ), true ) ) {
			if ( ! preg_match( '#^/(?:video/)?([0-9]+)(?:/([A-Za-z0-9]+))?/*$#', $path, $matches ) ) {
				return '';
			}

			$video_id = $matches[1];
			$hash     = $matches[2] ?? ( is_scalar( $query['h'] ?? null ) ? (string) $query['h'] : '' );
			if ( '' !== $hash && ! preg_match( '/^[A-Za-z0-9]+$/', $hash ) ) {
				return '';
			}

			return 'https://player.vimeo.com/video/' . rawurlencode( $video_id ) . ( '' !== $hash ? '?h=' . rawurlencode( $hash ) : '' );
		}

		return '';
	}

	/**
	 * Sanitize an optional source/reference URL.
	 *
	 * @param mixed $value Raw URL.
	 * @return string
	 */
	public function sanitize_source_url( $value ): string {
		return is_scalar( $value ) ? esc_url_raw( (string) $value ) : '';
	}

	/**
	 * Add an editor panel for the ExorLive player and exercise dosage fields.
	 *
	 * @return void
	 */
	public function register_meta_box(): void {
		add_meta_box(
			'gear-engine-exercise-details',
			__( 'Exercise Details', 'gear-engine' ),
			array( $this, 'render_meta_box' ),
			self::GEAR_ENGINE_POST_TYPE,
			'side',
			'default'
		);
	}

	/**
	 * Render the structured exercise fields.
	 *
	 * @param WP_Post $post Exercise post.
	 * @return void
	 */
	public function render_meta_box( WP_Post $post ): void {
		wp_nonce_field( self::GEAR_ENGINE_META_BOX_ACTION, self::GEAR_ENGINE_META_BOX_NONCE );
		$exorlive_id = get_post_meta( $post->ID, self::GEAR_ENGINE_META_EXORLIVE_ID, true );
		$culture     = get_post_meta( $post->ID, self::GEAR_ENGINE_META_VIDEO_CULTURE, true );
		$video_url   = get_post_meta( $post->ID, self::GEAR_ENGINE_META_VIDEO_URL, true );
		$dosage      = get_post_meta( $post->ID, self::GEAR_ENGINE_META_DOSAGE, true );
		$source_url  = get_post_meta( $post->ID, self::GEAR_ENGINE_META_SOURCE_URL, true );
		$risk        = self::sanitize_risk( get_post_meta( $post->ID, self::GEAR_ENGINE_META_RISK, true ) );
		?>
		<p>
			<label for="gear-engine-exercise-risk"><?php esc_html_e( 'Risk level', 'gear-engine' ); ?></label>
			<select class="widefat" id="gear-engine-exercise-risk" name="gear_engine_exercise_risk">
				<option value=""><?php esc_html_e( 'Not rated (protocol use only)', 'gear-engine' ); ?></option>
				<option value="low" <?php selected( $risk, 'low' ); ?>><?php esc_html_e( 'Low', 'gear-engine' ); ?></option>
				<option value="moderate" <?php selected( $risk, 'moderate' ); ?>><?php esc_html_e( 'Moderate', 'gear-engine' ); ?></option>
				<option value="high" <?php selected( $risk, 'high' ); ?>><?php esc_html_e( 'High (clinician guided)', 'gear-engine' ); ?></option>
			</select>
			<span class="description"><?php esc_html_e( 'Without an approved protocol the chat only uses Low and Moderate exercises.', 'gear-engine' ); ?></span>
		</p>
		<p>
			<label for="gear-engine-exorlive-id"><?php esc_html_e( 'ExorLive exercise ID', 'gear-engine' ); ?></label>
			<input class="widefat" id="gear-engine-exorlive-id" name="gear_engine_exorlive_id" type="text" inputmode="numeric" value="<?php echo esc_attr( (string) $exorlive_id ); ?>" />
			<span class="description"><?php esc_html_e( 'Use the ID from the official shared video link, for example ex=7282. Do not paste iframe code.', 'gear-engine' ); ?></span>
		</p>
		<p>
			<label for="gear-engine-exercise-video-url"><?php esc_html_e( 'Video URL (optional)', 'gear-engine' ); ?></label>
			<input class="widefat" id="gear-engine-exercise-video-url" name="gear_engine_exercise_video_url" type="url" value="<?php echo esc_attr( (string) $video_url ); ?>" placeholder="https://..." />
			<span class="description"><?php esc_html_e( 'Paste an HTTPS ExorLive, YouTube, or Vimeo link. When provided, this link is used instead of the ExorLive exercise ID.', 'gear-engine' ); ?></span>
		</p>
		<p>
			<label for="gear-engine-video-culture"><?php esc_html_e( 'Video language', 'gear-engine' ); ?></label>
			<input class="widefat" id="gear-engine-video-culture" name="gear_engine_video_culture" type="text" value="<?php echo esc_attr( $culture ? (string) $culture : 'nb-NO' ); ?>" />
		</p>
		<p>
			<label for="gear-engine-exercise-dosage"><?php esc_html_e( 'Suggested dosage', 'gear-engine' ); ?></label>
			<input class="widefat" id="gear-engine-exercise-dosage" name="gear_engine_exercise_dosage" type="text" value="<?php echo esc_attr( (string) $dosage ); ?>" placeholder="<?php esc_attr_e( 'For example: 3 sets of 8 repetitions', 'gear-engine' ); ?>" />
		</p>
		<p>
			<label for="gear-engine-exercise-source"><?php esc_html_e( 'Source/reference URL', 'gear-engine' ); ?></label>
			<input class="widefat" id="gear-engine-exercise-source" name="gear_engine_exercise_source_url" type="url" value="<?php echo esc_attr( (string) $source_url ); ?>" />
		</p>
		<?php
	}

	/**
	 * Save structured fields after validating the nonce and edit capability.
	 *
	 * @param int     $post_id Post ID.
	 * @param WP_Post|null $post Saved post.
	 * @return void
	 */
	public function save_meta_box( int $post_id, ?WP_Post $post = null ): void {
		if ( ! $post instanceof WP_Post || self::GEAR_ENGINE_POST_TYPE !== $post->post_type || $post_id !== (int) $post->ID ) {
			return;
		}
		if ( ! isset( $_POST[ self::GEAR_ENGINE_META_BOX_NONCE ] ) ) {
			return;
		}
		$nonce = sanitize_text_field( wp_unslash( $_POST[ self::GEAR_ENGINE_META_BOX_NONCE ] ) );
		if ( ! wp_verify_nonce( $nonce, self::GEAR_ENGINE_META_BOX_ACTION ) ) {
			return;
		}
		if ( ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) || wp_is_post_revision( $post_id ) ) {
			return;
		}
		if ( ! current_user_can( 'edit_post', $post_id ) ) {
			return;
		}

		update_post_meta( $post_id, self::GEAR_ENGINE_META_EXORLIVE_ID, $this->sanitize_exorlive_id( wp_unslash( $_POST['gear_engine_exorlive_id'] ?? '' ) ) );
		update_post_meta( $post_id, self::GEAR_ENGINE_META_VIDEO_CULTURE, $this->sanitize_video_culture( wp_unslash( $_POST['gear_engine_video_culture'] ?? 'nb-NO' ) ) );
		update_post_meta( $post_id, self::GEAR_ENGINE_META_VIDEO_URL, $this->sanitize_video_url( wp_unslash( $_POST['gear_engine_exercise_video_url'] ?? '' ) ) );
		update_post_meta( $post_id, self::GEAR_ENGINE_META_DOSAGE, sanitize_text_field( wp_unslash( $_POST['gear_engine_exercise_dosage'] ?? '' ) ) );
		update_post_meta( $post_id, self::GEAR_ENGINE_META_SOURCE_URL, $this->sanitize_source_url( wp_unslash( $_POST['gear_engine_exercise_source_url'] ?? '' ) ) );
		update_post_meta( $post_id, self::GEAR_ENGINE_META_RISK, self::sanitize_risk( wp_unslash( $_POST['gear_engine_exercise_risk'] ?? '' ) ) );
	}

	/**
	 * Seed a couple of editable examples once, including the official shared video ID supplied by the site owner.
	 *
	 * @return array<int, int>
	 */
	public static function seed_sample_exercises(): array {
		$definitions = array(
			array(
				'key'       => 'sample-deep-squat-7282',
				'title'     => 'Dyp knebøy med stang',
				'excerpt'   => 'En kontrollert knebøyvariant med vektstang.',
				'content'   => 'Stå stødig med føttene omtrent i skulderbreddes avstand og hold stangen kontrollert over øvre rygg. Bøy hofter og knær mens overkroppen holdes stabil, og gå bare så dypt du kan med god kontroll. Press gjennom føttene for å reise deg rolig opp igjen.',
				'dosage'    => 'Tilpass belastning og repetisjoner til eget nivå.',
				'exorlive'  => '7282',
				'area'      => 'kne',
				'equipment' => 'vektstang',
			),
			array(
				'key'       => 'sample-chair-squat',
				'title'     => 'Knebøy til stol',
				'excerpt'   => 'En enkel knebøyvariant der stolen gir et tydelig dybdemål.',
				'content'   => 'Plasser en stabil stol bak deg og stå med føttene omtrent i hoftebreddes avstand. Før hoftene bakover og bøy knærne til du så vidt berører setet, uten å slippe spenningen i kroppen. Reis deg kontrollert ved å presse føttene ned i gulvet.',
				'dosage'    => 'Start rolig og bruk stolhøyden som støtte for bevegelsesutslaget.',
				'exorlive'  => '',
				'area'      => 'kne',
				'equipment' => 'stol',
			),
		);

		$ids = array();
		foreach ( $definitions as $exercise ) {
			$existing = get_posts(
				array(
					'post_type'      => self::GEAR_ENGINE_POST_TYPE,
					'post_status'    => 'any',
					'posts_per_page' => 1,
					'fields'         => 'ids',
					'meta_key'       => self::GEAR_ENGINE_META_SAMPLE_KEY,
					'meta_value'     => $exercise['key'],
				)
			);

			if ( ! empty( $existing ) ) {
				$ids[] = (int) $existing[0];
				continue;
			}

			$post_id = wp_insert_post(
				array(
					'post_type'    => self::GEAR_ENGINE_POST_TYPE,
					'post_status'  => 'publish',
					'post_title'   => $exercise['title'],
					'post_excerpt' => $exercise['excerpt'],
					'post_content' => $exercise['content'],
				),
				true
			);

			if ( is_wp_error( $post_id ) || ! $post_id ) {
				continue;
			}

			update_post_meta( $post_id, self::GEAR_ENGINE_META_SAMPLE_KEY, $exercise['key'] );
			update_post_meta( $post_id, self::GEAR_ENGINE_META_EXORLIVE_ID, $exercise['exorlive'] );
			update_post_meta( $post_id, self::GEAR_ENGINE_META_VIDEO_CULTURE, 'nb-NO' );
			update_post_meta( $post_id, self::GEAR_ENGINE_META_DOSAGE, $exercise['dosage'] );
			wp_set_object_terms( $post_id, $exercise['area'], self::GEAR_ENGINE_AREA_TAXONOMY );
			wp_set_object_terms( $post_id, $exercise['equipment'], self::GEAR_ENGINE_EQUIPMENT_TAXONOMY );
			$ids[] = (int) $post_id;
		}

		if ( count( $ids ) === count( $definitions ) ) {
			update_option( self::GEAR_ENGINE_SAMPLE_OPTION, 1, false );
		}

		return $ids;
	}

	/**
	 * Seed examples for existing installations as well as fresh activation.
	 *
	 * @return void
	 */
	public function maybe_seed_sample_exercises(): void {
		if ( ! get_option( self::GEAR_ENGINE_SAMPLE_OPTION, false ) ) {
			self::seed_sample_exercises();
		}
	}

	/**
	 * Add ExorLive ID to the admin list table.
	 *
	 * @param array<string, string> $columns Existing columns.
	 * @return array<string, string>
	 */
	public function register_admin_columns( array $columns ): array {
		$columns['gear_engine_exorlive'] = __( 'ExorLive ID', 'gear-engine' );
		return $columns;
	}

	/**
	 * Render the ExorLive ID admin column.
	 *
	 * @param string $column  Column name.
	 * @param int    $post_id Post ID.
	 * @return void
	 */
	public function render_admin_column( string $column, int $post_id ): void {
		if ( 'gear_engine_exorlive' === $column ) {
			$exorlive_id = get_post_meta( $post_id, self::GEAR_ENGINE_META_EXORLIVE_ID, true );
			echo '' !== (string) $exorlive_id ? esc_html( (string) $exorlive_id ) : esc_html__( 'No video linked', 'gear-engine' );
		}
	}
}
