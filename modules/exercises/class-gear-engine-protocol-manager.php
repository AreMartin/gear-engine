<?php
/**
 * Dashboard overview and bulk tools for protocols and exercise risk levels.
 *
 * @package GearEngine
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Adds the Protocol Manager page and exercise list columns, filters and bulk edit.
 */
class Gear_Engine_Protocol_Manager {

	public const GEAR_ENGINE_PAGE_SLUG   = 'gear-engine-protocol-manager';
	private const GEAR_ENGINE_SAVE_ACTION = 'gear_engine_save_protocol_matrix';
	private const GEAR_ENGINE_PER_PAGE    = 40;

	public function __construct() {
		add_action( 'admin_menu', array( $this, 'register_admin_page' ) );
		add_action( 'admin_post_' . self::GEAR_ENGINE_SAVE_ACTION, array( $this, 'handle_matrix_save' ) );
		add_action( 'admin_notices', array( $this, 'render_saved_notice' ) );

		$post_type = Gear_Engine_Exercises_CPT::GEAR_ENGINE_POST_TYPE;
		add_filter( 'manage_' . $post_type . '_posts_columns', array( $this, 'register_list_columns' ), 20 );
		add_action( 'manage_' . $post_type . '_posts_custom_column', array( $this, 'render_list_column' ), 10, 2 );
		add_action( 'restrict_manage_posts', array( $this, 'render_list_filters' ) );
		add_action( 'pre_get_posts', array( $this, 'apply_list_filters' ) );
		add_action( 'bulk_edit_custom_box', array( $this, 'render_bulk_edit_box' ), 10, 2 );
		add_action( 'save_post_' . $post_type, array( $this, 'save_bulk_edit' ), 20 );
	}

	/**
	 * Add the Protocol Manager below the Exercise Library menu.
	 *
	 * @return void
	 */
	public function register_admin_page(): void {
		add_submenu_page(
			'edit.php?post_type=' . Gear_Engine_Exercises_CPT::GEAR_ENGINE_POST_TYPE,
			__( 'Protocol Manager', 'gear-engine' ),
			__( 'Protocol Manager', 'gear-engine' ),
			'edit_posts',
			self::GEAR_ENGINE_PAGE_SLUG,
			array( $this, 'render_admin_page' )
		);
	}

	/**
	 * Protocol terms ordered parent-first, with depth.
	 *
	 * @return array<int, array{term: object, depth: int}>
	 */
	public static function get_protocol_tree(): array {
		$terms = get_terms(
			array(
				'taxonomy'   => Gear_Engine_Protocols::GEAR_ENGINE_TAXONOMY,
				'hide_empty' => false,
				'orderby'    => 'name',
			)
		);
		if ( is_wp_error( $terms ) || ! is_array( $terms ) ) {
			return array();
		}

		$by_parent = array();
		foreach ( $terms as $term ) {
			$by_parent[ (int) $term->parent ][] = $term;
		}

		$tree  = array();
		$visit = static function ( int $parent, int $depth ) use ( &$visit, &$tree, $by_parent ): void {
			foreach ( $by_parent[ $parent ] ?? array() as $term ) {
				$tree[] = array( 'term' => $term, 'depth' => $depth );
				if ( $depth < 5 ) {
					$visit( (int) $term->term_id, $depth + 1 );
				}
			}
		};
		$visit( 0, 0 );

		return $tree;
	}

	/**
	 * Replace the protocol assignments and risk level for exercises shown in the matrix.
	 *
	 * @param array<int, int>               $post_ids Exercise IDs that were displayed.
	 * @param array<int|string, mixed>      $assign   Map of exercise ID to checked term IDs.
	 * @param array<int|string, mixed>      $risk     Map of exercise ID to risk level.
	 * @return int Number of exercises updated.
	 */
	public static function apply_matrix_changes( array $post_ids, array $assign, array $risk ): int {
		$valid_terms = array();
		foreach ( self::get_protocol_tree() as $row ) {
			$valid_terms[] = (int) $row['term']->term_id;
		}

		$updated = 0;
		foreach ( array_unique( array_map( 'absint', $post_ids ) ) as $post_id ) {
			if ( $post_id < 1 || ! current_user_can( 'edit_post', $post_id ) ) {
				continue;
			}
			$post = get_post( $post_id );
			if ( ! $post || Gear_Engine_Exercises_CPT::GEAR_ENGINE_POST_TYPE !== $post->post_type ) {
				continue;
			}

			$checked = isset( $assign[ $post_id ] ) && is_array( $assign[ $post_id ] ) ? array_map( 'absint', $assign[ $post_id ] ) : array();
			wp_set_object_terms( $post_id, array_values( array_intersect( $checked, $valid_terms ) ), Gear_Engine_Protocols::GEAR_ENGINE_TAXONOMY );
			update_post_meta( $post_id, Gear_Engine_Exercises_CPT::GEAR_ENGINE_META_RISK, Gear_Engine_Exercises_CPT::sanitize_risk( $risk[ $post_id ] ?? '' ) );
			++$updated;
		}

		return $updated;
	}

	/**
	 * Handle the matrix form submission.
	 *
	 * @return void
	 */
	public function handle_matrix_save(): void {
		check_admin_referer( self::GEAR_ENGINE_SAVE_ACTION, 'gear_engine_matrix_nonce' );
		if ( ! current_user_can( 'edit_posts' ) ) {
			wp_die( esc_html__( 'You do not have permission to do this.', 'gear-engine' ), '', array( 'response' => 403 ) );
		}

		$post_ids = isset( $_POST['gear_engine_post_ids'] ) && is_array( $_POST['gear_engine_post_ids'] ) ? array_map( 'absint', wp_unslash( $_POST['gear_engine_post_ids'] ) ) : array();
		$assign   = isset( $_POST['gear_engine_assign'] ) && is_array( $_POST['gear_engine_assign'] ) ? wp_unslash( $_POST['gear_engine_assign'] ) : array();
		$risk     = isset( $_POST['gear_engine_risk'] ) && is_array( $_POST['gear_engine_risk'] ) ? wp_unslash( $_POST['gear_engine_risk'] ) : array();
		$count    = self::apply_matrix_changes( $post_ids, $assign, $risk );

		$return = isset( $_POST['gear_engine_return'] ) ? esc_url_raw( wp_unslash( $_POST['gear_engine_return'] ) ) : '';
		$return = $return ? wp_validate_redirect( $return, admin_url( 'edit.php' ) ) : admin_url( 'edit.php' );
		wp_safe_redirect( add_query_arg( 'gear_engine_saved', $count, $return ) );
		exit;
	}

	/**
	 * Confirm a matrix save.
	 *
	 * @return void
	 */
	public function render_saved_notice(): void {
		$page = isset( $_GET['page'] ) ? sanitize_key( wp_unslash( $_GET['page'] ) ) : '';
		if ( self::GEAR_ENGINE_PAGE_SLUG !== $page || ! isset( $_GET['gear_engine_saved'] ) ) {
			return;
		}
		$count = absint( wp_unslash( $_GET['gear_engine_saved'] ) );
		echo '<div class="notice notice-success is-dismissible"><p>' . esc_html(
			sprintf(
				/* translators: %d: number of exercises */
				_n( '%d exercise updated.', '%d exercises updated.', $count, 'gear-engine' ),
				$count
			)
		) . '</p></div>';
	}

	/**
	 * Render the manager with an overview tab and a matrix tab.
	 *
	 * @return void
	 */
	public function render_admin_page(): void {
		if ( ! current_user_can( 'edit_posts' ) ) {
			wp_die( esc_html__( 'You do not have permission to view this page.', 'gear-engine' ), '', array( 'response' => 403 ) );
		}
		$tab      = isset( $_GET['tab'] ) ? sanitize_key( wp_unslash( $_GET['tab'] ) ) : 'overview';
		$tab      = 'matrix' === $tab ? 'matrix' : 'overview';
		$base_url = admin_url( 'edit.php?post_type=' . Gear_Engine_Exercises_CPT::GEAR_ENGINE_POST_TYPE . '&page=' . self::GEAR_ENGINE_PAGE_SLUG );
		?>
		<div class="wrap">
			<h1><?php esc_html_e( 'Protocol Manager', 'gear-engine' ); ?></h1>
			<h2 class="nav-tab-wrapper">
				<a class="nav-tab <?php echo 'overview' === $tab ? 'nav-tab-active' : ''; ?>" href="<?php echo esc_url( $base_url ); ?>"><?php esc_html_e( 'Overview', 'gear-engine' ); ?></a>
				<a class="nav-tab <?php echo 'matrix' === $tab ? 'nav-tab-active' : ''; ?>" href="<?php echo esc_url( add_query_arg( 'tab', 'matrix', $base_url ) ); ?>"><?php esc_html_e( 'Exercise matrix', 'gear-engine' ); ?></a>
			</h2>
			<?php
			if ( 'matrix' === $tab ) {
				$this->render_matrix( $base_url );
			} else {
				$this->render_overview();
			}
			?>
		</div>
		<?php
	}

	/**
	 * Count published exercises matching an extra query fragment.
	 *
	 * @param array<string, mixed> $extra WP_Query arguments.
	 * @return int
	 */
	private static function count_published_exercises( array $extra = array() ): int {
		$query = new WP_Query(
			array_merge(
				array(
					'post_type'      => Gear_Engine_Exercises_CPT::GEAR_ENGINE_POST_TYPE,
					'post_status'    => 'publish',
					'posts_per_page' => 1,
					'fields'         => 'ids',
				),
				$extra
			)
		);
		return (int) $query->found_posts;
	}

	/**
	 * Short to-do list that replaces hunting through screens.
	 *
	 * @return void
	 */
	private function render_attention_box(): void {
		$list_url = admin_url( 'edit.php?post_type=' . Gear_Engine_Exercises_CPT::GEAR_ENGINE_POST_TYPE );
		$unrated  = self::count_published_exercises(
			array(
				'meta_query' => array(
					'relation' => 'OR',
					array( 'key' => Gear_Engine_Exercises_CPT::GEAR_ENGINE_META_RISK, 'compare' => 'NOT EXISTS' ),
					array( 'key' => Gear_Engine_Exercises_CPT::GEAR_ENGINE_META_RISK, 'value' => '' ),
				),
			)
		);
		$untagged = self::count_published_exercises(
			array(
				'tax_query' => array(
					array(
						'taxonomy' => Gear_Engine_Protocols::GEAR_ENGINE_TAXONOMY,
						'operator' => 'NOT EXISTS',
					),
				),
			)
		);
		$drafts   = 0;
		foreach ( self::get_protocol_tree() as $row ) {
			if ( 0 === (int) $row['term']->parent && Gear_Engine_Protocols::GEAR_ENGINE_STATUS_APPROVED !== Gear_Engine_Protocols::get_status( (int) $row['term']->term_id ) ) {
				++$drafts;
			}
		}

		$items = array(
			array( $unrated, __( 'published exercises have no risk level (the chat cannot suggest them without a protocol)', 'gear-engine' ), add_query_arg( 'tab', 'matrix', admin_url( 'edit.php?post_type=' . Gear_Engine_Exercises_CPT::GEAR_ENGINE_POST_TYPE . '&page=' . self::GEAR_ENGINE_PAGE_SLUG ) ) ),
			array( $untagged, __( 'published exercises are in no protocol', 'gear-engine' ), add_query_arg( 'gear_engine_protocol_filter', 'none', $list_url ) ),
			array( $drafts, __( 'protocols are not approved or have expired', 'gear-engine' ), '' ),
		);

		echo '<div class="notice notice-info inline" style="padding:8px 12px"><p><strong>' . esc_html__( 'Needs attention', 'gear-engine' ) . '</strong></p><ul style="list-style:disc;margin-left:20px">';
		$any = false;
		foreach ( $items as $item ) {
			if ( $item[0] < 1 ) {
				continue;
			}
			$any  = true;
			$text = $item[0] . ' ' . $item[1];
			echo '<li>' . ( $item[2] ? '<a href="' . esc_url( $item[2] ) . '">' . esc_html( $text ) . '</a>' : esc_html( $text ) ) . '</li>';
		}
		if ( ! $any ) {
			echo '<li>' . esc_html__( 'Everything is up to date.', 'gear-engine' ) . '</li>';
		}
		echo '</ul></div>';
	}

	/**
	 * Protocol overview table.
	 *
	 * @return void
	 */
	private function render_overview(): void {
		$this->render_attention_box();
		$tree = self::get_protocol_tree();
		$terms_url = admin_url( 'edit-tags.php?taxonomy=' . Gear_Engine_Protocols::GEAR_ENGINE_TAXONOMY . '&post_type=' . Gear_Engine_Exercises_CPT::GEAR_ENGINE_POST_TYPE );

		echo '<p>' . esc_html__( 'Top-level rows are conditions; indented rows are phases. Only Approved, in-date protocols are used by the chat.', 'gear-engine' ) . ' ';
		echo '<a href="' . esc_url( $terms_url ) . '">' . esc_html__( 'Add or edit protocols', 'gear-engine' ) . '</a></p>';

		if ( ! $tree ) {
			echo '<p>' . esc_html__( 'No protocols yet. Add your first one under Protocols.', 'gear-engine' ) . '</p>';
			return;
		}

		echo '<table class="widefat striped"><thead><tr>';
		foreach ( array( __( 'Protocol / phase', 'gear-engine' ), __( 'Status', 'gear-engine' ), __( 'Codes', 'gear-engine' ), __( 'Published exercises', 'gear-engine' ), __( 'Review reminder', 'gear-engine' ), __( 'Needs before approval', 'gear-engine' ) ) as $heading ) {
			echo '<th>' . esc_html( $heading ) . '</th>';
		}
		echo '</tr></thead><tbody>';

		foreach ( $tree as $row ) {
			$term_id = (int) $row['term']->term_id;
			$status  = Gear_Engine_Protocols::get_status( $term_id );
			$labels  = array(
				Gear_Engine_Protocols::GEAR_ENGINE_STATUS_APPROVED => array( __( 'Approved', 'gear-engine' ), '#1e7e34' ),
				'expired'                                          => array( __( 'Review overdue', 'gear-engine' ), '#b32d2e' ),
				Gear_Engine_Protocols::GEAR_ENGINE_STATUS_DRAFT    => array( __( 'Draft', 'gear-engine' ), '#996800' ),
			);
			$missing = Gear_Engine_Protocols::GEAR_ENGINE_STATUS_APPROVED === $status ? array() : Gear_Engine_Protocols::get_missing_for_term( $term_id );
			$edit    = get_edit_term_link( $term_id, Gear_Engine_Protocols::GEAR_ENGINE_TAXONOMY, Gear_Engine_Exercises_CPT::GEAR_ENGINE_POST_TYPE );
			$list    = add_query_arg(
				array(
					'post_type'                      => Gear_Engine_Exercises_CPT::GEAR_ENGINE_POST_TYPE,
					'gear_engine_protocol_filter'    => $term_id,
				),
				admin_url( 'edit.php' )
			);

			echo '<tr><td style="padding-left:' . esc_attr( (string) ( 10 + 20 * (int) $row['depth'] ) ) . 'px">';
			echo $row['depth'] ? '&mdash; ' : '';
			echo '<strong><a href="' . esc_url( (string) $edit ) . '">' . esc_html( $row['term']->name ) . '</a></strong></td>';
			echo '<td style="color:' . esc_attr( $labels[ $status ][1] ) . '"><strong>' . esc_html( $labels[ $status ][0] ) . '</strong></td>';
			echo '<td>' . esc_html( (string) get_term_meta( $term_id, Gear_Engine_Protocols::GEAR_ENGINE_META_CODES, true ) ) . '</td>';
			echo '<td><a href="' . esc_url( $list ) . '">' . esc_html( (string) (int) $row['term']->count ) . '</a></td>';
			echo '<td>' . esc_html( (string) get_term_meta( $term_id, Gear_Engine_Protocols::GEAR_ENGINE_META_REVIEW_DUE, true ) ) . '</td>';
			echo '<td>' . esc_html( implode( ', ', $missing ) ) . '</td></tr>';
		}
		echo '</tbody></table>';
	}

	/**
	 * Exercises x protocols grid with checkboxes and a risk selector.
	 *
	 * @param string $base_url Page URL.
	 * @return void
	 */
	private function render_matrix( string $base_url ): void {
		$tree = self::get_protocol_tree();
		if ( ! $tree ) {
			echo '<p>' . esc_html__( 'Create at least one protocol first, then assign exercises here.', 'gear-engine' ) . '</p>';
			return;
		}

		$area     = isset( $_GET['area'] ) ? sanitize_title( wp_unslash( $_GET['area'] ) ) : '';
		$search   = isset( $_GET['s'] ) ? sanitize_text_field( wp_unslash( $_GET['s'] ) ) : '';
		$paged    = isset( $_GET['paged'] ) ? max( 1, absint( wp_unslash( $_GET['paged'] ) ) ) : 1;
		$args     = array(
			'post_type'      => Gear_Engine_Exercises_CPT::GEAR_ENGINE_POST_TYPE,
			'post_status'    => array( 'publish', 'draft', 'pending' ),
			'posts_per_page' => self::GEAR_ENGINE_PER_PAGE,
			'paged'          => $paged,
			'orderby'        => 'title',
			'order'          => 'ASC',
		);
		if ( '' !== $search ) {
			$args['s'] = $search;
		}
		if ( '' !== $area ) {
			$args['tax_query'] = array(
				array(
					'taxonomy' => Gear_Engine_Exercises_CPT::GEAR_ENGINE_AREA_TAXONOMY,
					'field'    => 'slug',
					'terms'    => $area,
				),
			);
		}
		$query = new WP_Query( $args );
		$areas = get_terms( array( 'taxonomy' => Gear_Engine_Exercises_CPT::GEAR_ENGINE_AREA_TAXONOMY, 'hide_empty' => false ) );

		echo '<form method="get" style="margin:12px 0"><input type="hidden" name="post_type" value="' . esc_attr( Gear_Engine_Exercises_CPT::GEAR_ENGINE_POST_TYPE ) . '" /><input type="hidden" name="page" value="' . esc_attr( self::GEAR_ENGINE_PAGE_SLUG ) . '" /><input type="hidden" name="tab" value="matrix" />';
		echo '<select name="area"><option value="">' . esc_html__( 'All body areas', 'gear-engine' ) . '</option>';
		if ( is_array( $areas ) ) {
			foreach ( $areas as $term ) {
				echo '<option value="' . esc_attr( $term->slug ) . '"' . selected( $area, $term->slug, false ) . '>' . esc_html( $term->name ) . '</option>';
			}
		}
		echo '</select> <input type="search" name="s" value="' . esc_attr( $search ) . '" placeholder="' . esc_attr__( 'Search exercises', 'gear-engine' ) . '" /> ';
		echo '<button class="button">' . esc_html__( 'Filter', 'gear-engine' ) . '</button></form>';

		if ( ! $query->posts ) {
			echo '<p>' . esc_html__( 'No exercises found.', 'gear-engine' ) . '</p>';
			return;
		}

		$return = add_query_arg( array( 'tab' => 'matrix', 'area' => $area, 's' => $search, 'paged' => $paged ), $base_url );
		echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '">';
		echo '<input type="hidden" name="action" value="' . esc_attr( self::GEAR_ENGINE_SAVE_ACTION ) . '" />';
		echo '<input type="hidden" name="gear_engine_return" value="' . esc_url( $return ) . '" />';
		wp_nonce_field( self::GEAR_ENGINE_SAVE_ACTION, 'gear_engine_matrix_nonce' );

		echo '<div style="overflow-x:auto"><table class="widefat striped"><thead><tr><th>' . esc_html__( 'Exercise', 'gear-engine' ) . '</th><th>' . esc_html__( 'Risk', 'gear-engine' ) . '</th>';
		foreach ( $tree as $row ) {
			echo '<th style="text-align:center;font-weight:' . ( $row['depth'] ? 'normal' : '700' ) . '">' . ( $row['depth'] ? '&mdash; ' : '' ) . esc_html( $row['term']->name ) . '</th>';
		}
		echo '</tr></thead><tbody>';

		foreach ( $query->posts as $post ) {
			$post_id  = (int) $post->ID;
			$assigned = wp_get_object_terms( $post_id, Gear_Engine_Protocols::GEAR_ENGINE_TAXONOMY, array( 'fields' => 'ids' ) );
			$assigned = is_array( $assigned ) ? array_map( 'absint', $assigned ) : array();
			$risk     = Gear_Engine_Exercises_CPT::sanitize_risk( get_post_meta( $post_id, Gear_Engine_Exercises_CPT::GEAR_ENGINE_META_RISK, true ) );
			$can_edit = current_user_can( 'edit_post', $post_id );

			echo '<tr><td><input type="hidden" name="gear_engine_post_ids[]" value="' . esc_attr( (string) $post_id ) . '" />';
			echo '<a href="' . esc_url( (string) get_edit_post_link( $post_id ) ) . '">' . esc_html( $post->post_title ) . '</a>';
			echo 'publish' !== $post->post_status ? ' <em>(' . esc_html( $post->post_status ) . ')</em>' : '';
			echo '</td><td><select name="gear_engine_risk[' . esc_attr( (string) $post_id ) . ']"' . ( $can_edit ? '' : ' disabled' ) . '>';
			foreach ( array( '' => __( 'Not rated', 'gear-engine' ), 'low' => __( 'Low', 'gear-engine' ), 'moderate' => __( 'Moderate', 'gear-engine' ), 'high' => __( 'High', 'gear-engine' ) ) as $value => $label ) {
				echo '<option value="' . esc_attr( $value ) . '"' . selected( $risk, $value, false ) . '>' . esc_html( $label ) . '</option>';
			}
			echo '</select></td>';
			foreach ( $tree as $row ) {
				$term_id = (int) $row['term']->term_id;
				echo '<td style="text-align:center"><input type="checkbox" name="gear_engine_assign[' . esc_attr( (string) $post_id ) . '][]" value="' . esc_attr( (string) $term_id ) . '"' . checked( in_array( $term_id, $assigned, true ), true, false ) . ( $can_edit ? '' : ' disabled' ) . ' /></td>';
			}
			echo '</tr>';
		}
		echo '</tbody></table></div>';
		submit_button( __( 'Save changes on this page', 'gear-engine' ) );
		echo '</form>';

		$total = (int) ( $query->max_num_pages ?? 1 );
		if ( $total > 1 ) {
			echo '<div class="tablenav"><div class="tablenav-pages">' . wp_kses_post(
				paginate_links(
					array(
						'base'    => add_query_arg( 'paged', '%#%', add_query_arg( array( 'tab' => 'matrix', 'area' => $area, 's' => $search ), $base_url ) ),
						'format'  => '',
						'current' => $paged,
						'total'   => $total,
					)
				)
			) . '</div></div>';
		}
	}

	/**
	 * Add protocol and risk columns to the exercise list. Protocols are added by the taxonomy itself.
	 *
	 * @param array<string, string> $columns Columns.
	 * @return array<string, string>
	 */
	public function register_list_columns( array $columns ): array {
		$columns['gear_engine_risk'] = __( 'Risk', 'gear-engine' );
		return $columns;
	}

	/**
	 * Render the risk column.
	 *
	 * @param string $column  Column.
	 * @param int    $post_id Post ID.
	 * @return void
	 */
	public function render_list_column( string $column, int $post_id ): void {
		if ( 'gear_engine_risk' !== $column ) {
			return;
		}
		$risk = Gear_Engine_Exercises_CPT::sanitize_risk( get_post_meta( $post_id, Gear_Engine_Exercises_CPT::GEAR_ENGINE_META_RISK, true ) );
		echo '' === $risk ? '<em>' . esc_html__( 'Not rated', 'gear-engine' ) . '</em>' : esc_html( ucfirst( $risk ) );
	}

	/**
	 * Protocol and risk filter dropdowns above the exercise list.
	 *
	 * @param string $post_type Current post type.
	 * @return void
	 */
	public function render_list_filters( $post_type ): void {
		if ( Gear_Engine_Exercises_CPT::GEAR_ENGINE_POST_TYPE !== $post_type ) {
			return;
		}
		$protocol = isset( $_GET['gear_engine_protocol_filter'] ) ? sanitize_text_field( wp_unslash( $_GET['gear_engine_protocol_filter'] ) ) : '';
		$risk     = isset( $_GET['gear_engine_risk_filter'] ) ? sanitize_text_field( wp_unslash( $_GET['gear_engine_risk_filter'] ) ) : '';

		echo '<select name="gear_engine_protocol_filter"><option value="">' . esc_html__( 'All protocols', 'gear-engine' ) . '</option>';
		echo '<option value="none"' . selected( $protocol, 'none', false ) . '>' . esc_html__( 'No protocol', 'gear-engine' ) . '</option>';
		foreach ( self::get_protocol_tree() as $row ) {
			echo '<option value="' . esc_attr( (string) $row['term']->term_id ) . '"' . selected( $protocol, (string) $row['term']->term_id, false ) . '>' . esc_html( str_repeat( '— ', (int) $row['depth'] ) . $row['term']->name ) . '</option>';
		}
		echo '</select>';

		echo '<select name="gear_engine_risk_filter"><option value="">' . esc_html__( 'All risk levels', 'gear-engine' ) . '</option>';
		foreach ( array( 'unrated' => __( 'Not rated', 'gear-engine' ), 'low' => __( 'Low', 'gear-engine' ), 'moderate' => __( 'Moderate', 'gear-engine' ), 'high' => __( 'High', 'gear-engine' ) ) as $value => $label ) {
			echo '<option value="' . esc_attr( $value ) . '"' . selected( $risk, $value, false ) . '>' . esc_html( $label ) . '</option>';
		}
		echo '</select>';
	}

	/**
	 * Apply the list filters to the main admin query.
	 *
	 * @param WP_Query $query Query.
	 * @return void
	 */
	public function apply_list_filters( $query ): void {
		if ( ! is_admin() || ! $query->is_main_query() || Gear_Engine_Exercises_CPT::GEAR_ENGINE_POST_TYPE !== $query->get( 'post_type' ) ) {
			return;
		}

		$protocol = isset( $_GET['gear_engine_protocol_filter'] ) ? sanitize_text_field( wp_unslash( $_GET['gear_engine_protocol_filter'] ) ) : '';
		if ( 'none' === $protocol ) {
			$query->set( 'tax_query', array( array( 'taxonomy' => Gear_Engine_Protocols::GEAR_ENGINE_TAXONOMY, 'operator' => 'NOT EXISTS' ) ) );
		} elseif ( '' !== $protocol && absint( $protocol ) > 0 ) {
			$query->set( 'tax_query', array( array( 'taxonomy' => Gear_Engine_Protocols::GEAR_ENGINE_TAXONOMY, 'field' => 'term_id', 'terms' => absint( $protocol ) ) ) );
		}

		$risk = isset( $_GET['gear_engine_risk_filter'] ) ? sanitize_key( wp_unslash( $_GET['gear_engine_risk_filter'] ) ) : '';
		if ( 'unrated' === $risk ) {
			$query->set(
				'meta_query',
				array(
					'relation' => 'OR',
					array( 'key' => Gear_Engine_Exercises_CPT::GEAR_ENGINE_META_RISK, 'compare' => 'NOT EXISTS' ),
					array( 'key' => Gear_Engine_Exercises_CPT::GEAR_ENGINE_META_RISK, 'value' => '', 'compare' => '=' ),
				)
			);
		} elseif ( '' !== Gear_Engine_Exercises_CPT::sanitize_risk( $risk ) ) {
			$query->set( 'meta_query', array( array( 'key' => Gear_Engine_Exercises_CPT::GEAR_ENGINE_META_RISK, 'value' => $risk ) ) );
		}
	}

	/**
	 * Add a risk selector to WordPress Bulk Edit. Protocol tags are handled by core's bulk edit.
	 *
	 * @param string $column    Column name.
	 * @param string $post_type Post type.
	 * @return void
	 */
	public function render_bulk_edit_box( $column, $post_type ): void {
		if ( 'gear_engine_risk' !== $column || Gear_Engine_Exercises_CPT::GEAR_ENGINE_POST_TYPE !== $post_type ) {
			return;
		}
		?>
		<fieldset class="inline-edit-col-right"><div class="inline-edit-col">
			<label>
				<span class="title"><?php esc_html_e( 'Risk', 'gear-engine' ); ?></span>
				<select name="gear_engine_bulk_risk">
					<option value=""><?php esc_html_e( '— No change —', 'gear-engine' ); ?></option>
					<option value="low"><?php esc_html_e( 'Low', 'gear-engine' ); ?></option>
					<option value="moderate"><?php esc_html_e( 'Moderate', 'gear-engine' ); ?></option>
					<option value="high"><?php esc_html_e( 'High', 'gear-engine' ); ?></option>
				</select>
			</label>
		</div></fieldset>
		<?php
	}

	/**
	 * Save the bulk-edit risk level.
	 *
	 * @param int $post_id Post ID.
	 * @return void
	 */
	public function save_bulk_edit( int $post_id ): void {
		if ( ! isset( $_REQUEST['bulk_edit'], $_REQUEST['_wpnonce'], $_REQUEST['gear_engine_bulk_risk'] ) ) {
			return;
		}
		if ( ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_REQUEST['_wpnonce'] ) ), 'bulk-posts' ) || ! current_user_can( 'edit_post', $post_id ) ) {
			return;
		}
		$risk = Gear_Engine_Exercises_CPT::sanitize_risk( wp_unslash( $_REQUEST['gear_engine_bulk_risk'] ) );
		if ( '' !== $risk ) {
			update_post_meta( $post_id, Gear_Engine_Exercises_CPT::GEAR_ENGINE_META_RISK, $risk );
		}
	}
}
