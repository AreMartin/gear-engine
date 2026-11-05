<?php
/**
 * Price comparison unit tests.
 * Run with: C:\php\php.exe tests/price-comparison.test.php
 */

define( 'ABSPATH', __DIR__ . '/../' );
define( 'GEAR_ENGINE_PATH', ABSPATH );
define( 'GEAR_ENGINE_URL', 'http://example.com/' );
define( 'GEAR_ENGINE_VERSION', '1.0.0' );

$registered_assets = array();

function add_action() {}
function add_shortcode() {}
function wp_register_style( $handle, $src = '', $deps = array(), $version = false, $media = 'all' ) {
	global $registered_assets;
	$registered_assets['style'][ $handle ] = $version;
}
function wp_register_script( $handle, $src = '', $deps = array(), $version = false, $in_footer = false ) {
	global $registered_assets;
	$registered_assets['script'][ $handle ] = $version;
}
function wp_enqueue_style() {}
function wp_enqueue_script() {}
function update_option() {}
function get_option( $k, $d = null ) { return $d; }
function get_transient( $k ) { return false; }
function set_transient() {}
function esc_url_raw( $u ) { return (string) $u; }
function esc_html( $s ) { return htmlspecialchars( (string) $s, ENT_QUOTES ); }
function esc_attr( $s ) { return htmlspecialchars( (string) $s, ENT_QUOTES ); }
function esc_url( $s ) { return (string) $s; }
function esc_html_e( $s ) { echo esc_html( $s ); }
function esc_attr_e( $s ) { echo esc_attr( $s ); }
function __( $s ) { return $s; }
function home_url( $p = '' ) { return 'http://example.com' . $p; }
function sanitize_text_field( $s ) { return trim( strip_tags( (string) $s ) ); }
function sanitize_key( $key ) { return preg_replace( '/[^a-z0-9_\-]/', '', strtolower( (string) $key ) ); }
function absint( $n ) { return abs( (int) $n ); }
function wp_json_encode( $d ) { return json_encode( $d ); }
function shortcode_atts( $pairs, $atts ) { return array_merge( $pairs, (array) $atts ); }
function is_wp_error( $e ) { return false; }
function wp_strip_all_tags( $s ) { return strip_tags( (string) $s ); }
function wp_kses_post( $s ) { return (string) $s; }
function wp_trim_words( $s, $n = 55 ) { return (string) $s; }
function sanitize_title( $s ) { return strtolower( preg_replace( '/[^a-z0-9]/i', '-', (string) $s ) ); }
function current_time( $t ) { return '2026-11-04 12:00:00'; }

global $mock_post_meta, $mock_terms;
$mock_post_meta = array();
$mock_terms     = array();

function update_post_meta( $id, $k, $v ) {
	global $mock_post_meta;
	$mock_post_meta[ $id ][ $k ] = $v;
	return true;
}
function get_post_meta( $id, $k, $s = false ) {
	global $mock_post_meta;
	return $mock_post_meta[ $id ][ $k ] ?? ( $s ? '' : array() );
}
function delete_post_meta( $id, $k ) {
	global $mock_post_meta;
	unset( $mock_post_meta[ $id ][ $k ] );
	return true;
}
function wp_set_object_terms( $id, $terms, $taxonomy, $append = false ) {
	global $mock_terms;
	$terms = (array) $terms;
	if ( $append && isset( $mock_terms[ $id ][ $taxonomy ] ) ) {
		$mock_terms[ $id ][ $taxonomy ] = array_unique( array_merge( $mock_terms[ $id ][ $taxonomy ], $terms ) );
	} else {
		$mock_terms[ $id ][ $taxonomy ] = $terms;
	}
	return $terms;
}
function term_exists( $term, $tax = '' ) {
	return array( 'term_id' => crc32( (string) $term ) );
}
function wp_insert_term( $term, $tax = '' ) {
	return array( 'term_id' => crc32( (string) $term ) );
}

class WP_Error {}
class WP_Query {
	public $posts = array();
	public function have_posts() { return false; }
}

require_once GEAR_ENGINE_PATH . 'includes/class-gear-engine-autoloader.php';
require_once GEAR_ENGINE_PATH . 'includes/class-gear-engine-cpt.php';
require_once GEAR_ENGINE_PATH . 'includes/class-gear-engine-product-service.php';
require_once GEAR_ENGINE_PATH . 'includes/class-gear-engine-product-upscaler.php';
require_once GEAR_ENGINE_PATH . 'modules/price-comparison/class-gear-engine-price-comparison.php';

$passed = 0;
$failed = 0;

function assert_test( $condition, $msg ) {
	global $passed, $failed;
	if ( $condition ) {
		echo " [PASS] $msg\n";
		$passed++;
	} else {
		echo " [FAIL] $msg\n";
		$failed++;
	}
}

echo "========================================================\n";
echo "Testing Price Comparison Module...\n";
echo "========================================================\n";

// 1. Fallback products
$fallback = Gear_Engine_Price_Comparison::get_fallback_products();
assert_test( is_array( $fallback ) && count( $fallback ) === 10, 'Fallback products returns exactly 10 sample items across 4 departments' );
assert_test( $fallback[0]['brand'] === 'Concept2', 'First fallback product is Concept2' );
assert_test( $fallback[0]['currentPrice'] === 12490, 'Concept2 price is 12490' );
assert_test( $fallback[0]['discount'] === 20, 'Concept2 discount is 20%' );
assert_test( $fallback[0]['otherShops'] === 3, 'Concept2 otherShops is 3' );
assert_test( ! empty( $fallback[0]['image'] ), 'Concept2 has valid image URL' );

// 2. Shortcode registration
assert_test( Gear_Engine_Price_Comparison::SHORTCODE_TAG === 'gear_engine_price_comparison', 'Shortcode tag constant is correct' );
assert_test( Gear_Engine_Price_Comparison::SHORTCODE_ALIAS === 'fysionett_prissammenligning', 'Shortcode alias constant is correct' );

// 3. Render method with fallback data
$module = new Gear_Engine_Price_Comparison();
$module->register_assets();
$html   = $module->render( array() );

assert_test(
	$registered_assets['style']['gear-engine-price-comparison'] === (string) filemtime( GEAR_ENGINE_PATH . 'modules/price-comparison/assets/css/price-comparison.css' ),
	'Price comparison stylesheet uses file modification time for cache busting'
);
assert_test(
	$registered_assets['script']['gear-engine-price-comparison'] === (string) filemtime( GEAR_ENGINE_PATH . 'modules/price-comparison/assets/js/price-comparison.js' ),
	'Price comparison script uses file modification time for cache busting'
);
assert_test( strpos( $html, 'id="fysionett-prissok-root"' ) !== false, 'Rendered HTML contains #fysionett-prissok-root' );
assert_test( strpos( $html, 'data-feed="' ) !== false, 'Rendered HTML contains encoded data-feed' );
assert_test( strpos( $html, 'ge-price-comp-guide-banner' ) !== false, 'Rendered HTML contains guide cross-link banner' );
assert_test( strpos( $html, '/kjopsveiledere/' ) !== false, 'Rendered HTML guide banner links to /kjopsveiledere/' );

// 4. Del 1: Sorting by savings_desc (størst kroneavslag)
$sorted_by_savings = $fallback;
usort(
	$sorted_by_savings,
	function ( $a, $b ) {
		$sav_a = ( isset( $a['originalPrice'], $a['currentPrice'] ) && $a['originalPrice'] > $a['currentPrice'] ) ? ( $a['originalPrice'] - $a['currentPrice'] ) : 0;
		$sav_b = ( isset( $b['originalPrice'], $b['currentPrice'] ) && $b['originalPrice'] > $b['currentPrice'] ) ? ( $b['originalPrice'] - $b['currentPrice'] ) : 0;
		return ( $sav_b <=> $sav_a ) ?: ( $a['currentPrice'] <=> $b['currentPrice'] );
	}
);

assert_test( $sorted_by_savings[0]['brand'] === 'Sole', 'Top savings product is Sole (4394 kr avslag)' );
assert_test( $sorted_by_savings[1]['brand'] === 'Concept2', 'Second savings product is Concept2 (3110 kr avslag)' );
$sole_savings = $sorted_by_savings[0]['originalPrice'] - $sorted_by_savings[0]['currentPrice'];
assert_test( $sole_savings === 4394, 'Sole F85 savings calculated to exactly 4394,-' );

// 5. Del 1: UI Assets & markup verification
$css_content = file_get_contents( GEAR_ENGINE_PATH . 'modules/price-comparison/assets/css/price-comparison.css' );
$js_content  = file_get_contents( GEAR_ENGINE_PATH . 'modules/price-comparison/assets/js/price-comparison.js' );

assert_test( strpos( $css_content, 'ge-comp-view-toggle-wrap' ) !== false, 'CSS contains .ge-comp-view-toggle-wrap' );
assert_test( strpos( $css_content, 'ge-savings-chip' ) !== false, 'CSS contains .ge-savings-chip styling' );
assert_test( strpos( $css_content, 'ge-comp-quick-filters' ) !== false, 'CSS contains .ge-comp-quick-filters' );
assert_test( strpos( $css_content, '.ge-comp-filter-panel.is-open' ) !== false && strpos( $css_content, 'transform: translateY(100%)' ) !== false, 'Filter sheet opens from the bottom on compact layouts' );
assert_test( strpos( $css_content, '@media (min-width: 840px)' ) !== false && strpos( $css_content, 'transform: translateX(100%)' ) !== false, 'Filter sheet opens from the side on desktop' );
assert_test( strpos( $css_content, 'border-radius: 9999px;' ) !== false && strpos( $css_content, '.ge-filter-pill-btn.is-active' ) !== false, 'Filter chips use rounded Material 3 shapes and selected states' );
assert_test( strpos( $css_content, '@container ge-m3-comp (max-width: 599px)' ) !== false, 'Quick filters move into the filter sheet on mobile' );
assert_test( strpos( $css_content, 'border-radius: var(--md-sys-shape-corner-full);' ) !== false, 'Search input uses Material 3 full pill shape' );
assert_test( strpos( $css_content, '.ge-card-grid-img-wrap .ge-discount-badge' ) !== false && strpos( $css_content, 'var(--md-sys-color-error-container)' ) !== false, 'Product discount badges use the prototype red tone' );
assert_test( strpos( $css_content, '.ge-m3-list-cta' ) !== false && strpos( $css_content, 'width: auto;' ) !== false, 'List view call-to-action keeps its compact prototype width on mobile' );
assert_test( strpos( $css_content, '@container ge-m3-comp (min-width: 840px)' ) !== false, 'CSS contains 2-column container query at >=840px' );
assert_test( strpos( $css_content, 'align-items: start' ) !== false, 'CSS list grid uses align-items: start' );

assert_test( strpos( $js_content, 'QUICK_FILTER_DEFS' ) !== false, 'JS contains QUICK_FILTER_DEFS' );
assert_test( strpos( $js_content, 'ge-savings-chip' ) !== false, 'JS conditionally renders ge-savings-chip' );
assert_test( strpos( $js_content, 'ge-list-main-row' ) !== false && strpos( $js_content, 'ge-list-shop-row' ) !== false, 'JS renders prototype list-card structure and retailer footer' );
assert_test( strpos( $js_content, 'ge-comp-list-column' ) !== false && strpos( $css_content, '@container ge-m3-comp (min-width: 840px)' ) !== false, 'List view uses the prototype two-column container breakpoint' );
assert_test( strpos( $js_content, 'ge-list-compare-box' ) !== false && strpos( $js_content, 'aria-expanded=' ) !== false, 'JS preserves accessible list comparison controls' );
assert_test( strpos( $js_content, 'Til butikk ' ) !== false, 'JS uses prototype list-card call-to-action' );
assert_test( strpos( $js_content, 'Rutenett' ) !== false, 'JS contains labeled Rutenett view button' );
assert_test( strpos( $js_content, 'Liste' ) !== false, 'JS contains labeled Liste view button' );
assert_test( strpos( $js_content, 'ge-filter-close-btn' ) !== false && strpos( $js_content, 'ge-comp-filter-scrim' ) !== false, 'JS renders modal filter sheet close and scrim controls' );
assert_test( strpos( $js_content, "filterScrim.addEventListener('click'" ) !== false && strpos( $js_content, "e.key === 'Escape'" ) !== false, 'JS closes filter sheet with backdrop and Escape key' );
assert_test( strpos( $js_content, 'root.clientWidth <= 599' ) !== false && strpos( $js_content, 'new ResizeObserver(syncQuickFiltersPlacement)' ) !== false, 'JS relocates mobile quick filters based on container width' );
assert_test( strpos( $js_content, 'ge-price-comp-filter-host-open' ) !== false && strpos( $css_content, '.ge-price-comp-wrapper.ge-price-comp-filter-host-open' ) !== false, 'Open filter sheet stacks above surrounding page content' );

// 6. Del 2: Specifications in fallback products
assert_test( isset( $fallback[0]['specs'] ) && is_array( $fallback[0]['specs'] ), 'Concept2 fallback product has specs array' );
assert_test( $fallback[0]['specs']['Motstand'] === 'Luftmotstand', 'Concept2 has Motstand = Luftmotstand' );
assert_test( isset( $fallback[1]['specs'] ) && $fallback[1]['specs']['Toppfart'] === '22 km/t', 'Sole F85 has Toppfart = 22 km/t' );
assert_test( isset( $fallback[5]['specs'] ) && $fallback[5]['specs']['Slaglengde'] === '16 mm', 'Theragun Pro has Slaglengde = 16 mm' );

// 7. Del 2: Template markup for dock and comparison modal
assert_test( strpos( $html, 'id="ge-floating-cmp-bar"' ) !== false, 'Container template contains #ge-floating-cmp-bar dock' );
assert_test( strpos( $html, 'id="ge-cmp-modal"' ) !== false, 'Container template contains #ge-cmp-modal dialog' );
assert_test( strpos( $html, 'id="ge-cmp-diff-only"' ) !== false, 'Container template contains diff-only toggle #ge-cmp-diff-only' );
assert_test( strpos( $html, 'id="ge-cmp-open-modal-btn"' ) !== false, 'Container template contains open modal button #ge-cmp-open-modal-btn' );

// 8. Del 2: CSS Comparison Matrix & Modal styling
assert_test( strpos( $css_content, '.ge-comp-dock' ) !== false, 'CSS contains .ge-comp-dock styling' );
assert_test( strpos( $css_content, '.ge-cmp-modal-scrim' ) !== false, 'CSS contains .ge-cmp-modal-scrim styling' );
assert_test( strpos( $css_content, '.ge-cmp-modal-card' ) !== false, 'CSS contains .ge-cmp-modal-card styling' );
assert_test( strpos( $css_content, '.ge-matrix-table' ) !== false, 'CSS contains .ge-matrix-table styling' );
assert_test( strpos( $css_content, '.matrix-prod-col.is-best-col' ) !== false, 'CSS contains Best Price column highlight (.is-best-col)' );
assert_test( strpos( $css_content, '.ge-matrix-best-tag' ) !== false, 'CSS contains .ge-matrix-best-tag badge' );
assert_test( strpos( $css_content, '.ge-spec-win' ) !== false, 'CSS contains numeric winner tag .ge-spec-win' );
assert_test( strpos( $css_content, '.ge-antifit-box' ) !== false, 'CSS contains anti-fit note box .ge-antifit-box' );
assert_test( strpos( $css_content, '.ge-card-cmp-label' ) !== false, 'CSS contains card compare checkbox label .ge-card-cmp-label' );

// 9. Del 2: JS Comparison Motor & Side-by-side Logic
assert_test( strpos( $js_content, 'selectedForCompare' ) !== false, 'JS state manages selectedForCompare array' );
assert_test( strpos( $js_content, 'showOnlyDiffs' ) !== false, 'JS state manages showOnlyDiffs boolean' );
assert_test( strpos( $js_content, 'renderCompareMatrix' ) !== false, 'JS defines renderCompareMatrix() function' );
assert_test( strpos( $js_content, 'updateDock' ) !== false, 'JS defines updateDock() function' );
assert_test( strpos( $js_content, 'getAntiFitNotes' ) !== false, 'JS defines getAntiFitNotes() deterministic rule engine' );
assert_test( strpos( $js_content, 'window.GearEnginePriceComparison' ) !== false, 'JS exports window.GearEnginePriceComparison API' );
assert_test( strpos( $js_content, 'Høyest' ) !== false, 'JS generates Høyest winner tag on numeric specs' );
assert_test( strpos( $js_content, 'Best pris' ) !== false, 'JS renders Best pris utheving in comparison matrix' );
assert_test( strpos( $js_content, 'Vær obs på' ) !== false, 'JS renders Vær obs på anti-fit section in comparison matrix' );

// 10. Del 3: 2-Level Departments in fallback data and infer_department helper
$valid_departments = array( 'kondisjon', 'styrke', 'rehabilitering', 'restitusjon' );
$all_have_valid_dept = true;
foreach ( $fallback as $prod ) {
	if ( empty( $prod['department'] ) || ! in_array( $prod['department'], $valid_departments, true ) ) {
		$all_have_valid_dept = false;
		break;
	}
}
assert_test( $all_have_valid_dept, 'All 10 fallback products have valid department set' );
assert_test( Gear_Engine_Price_Comparison::infer_department( 'Tredemøller' ) === 'kondisjon', 'infer_department maps Tredemøller to kondisjon' );
assert_test( Gear_Engine_Price_Comparison::infer_department( 'Manualer' ) === 'styrke', 'infer_department maps Manualer to styrke' );
assert_test( Gear_Engine_Price_Comparison::infer_department( 'Knestøtter' ) === 'rehabilitering', 'infer_department maps Knestøtter to rehabilitering' );
assert_test( Gear_Engine_Price_Comparison::infer_department( 'Massasjepistoler' ) === 'restitusjon', 'infer_department maps Massasjepistoler to restitusjon' );
assert_test( Gear_Engine_Price_Comparison::infer_department( 'Annet', 'Bauerfeind GenuTrain Knestøtte' ) === 'rehabilitering', 'infer_department detects rehabilitering from post title' );

// 11. Del 3: Shortcode attribute mapping and container data attributes
assert_test( strpos( $html, 'data-initial-department="kondisjon"' ) !== false, 'Default render sets data-initial-department="kondisjon"' );

$html_styrke = $module->render( array( 'department' => 'styrke' ) );
assert_test( strpos( $html_styrke, 'data-initial-department="styrke"' ) !== false, 'Shortcode department="styrke" sets data-initial-department="styrke"' );

$html_group = $module->render( array( 'group' => 'rehabilitering' ) );
assert_test( strpos( $html_group, 'data-initial-department="rehabilitering"' ) !== false, 'Shortcode group="rehabilitering" sets data-initial-department="rehabilitering"' );

$html_cat_infer = $module->render( array( 'category' => 'Massasjepistoler' ) );
assert_test( strpos( $html_cat_infer, 'data-initial-department="restitusjon"' ) !== false, 'Shortcode category="Massasjepistoler" automatically infers restitusjon department' );

// 12. Del 3: CSS 2-Level Navigation and Search Alert styles
assert_test( strpos( $css_content, '.ge-dept-section' ) !== false, 'CSS contains .ge-dept-section styling' );
assert_test( strpos( $css_content, '.ge-dept-track' ) !== false, 'CSS contains Level 1 track .ge-dept-track styling' );
assert_test( strpos( $css_content, '.ge-dept-btn' ) !== false, 'CSS contains Level 1 tab button .ge-dept-btn styling' );
assert_test( strpos( $css_content, '.ge-dept-badge' ) !== false, 'CSS contains department counter badge .ge-dept-badge styling' );
assert_test( strpos( $css_content, '.ge-dept-alert' ) !== false, 'CSS contains cross-department alert banner .ge-dept-alert styling' );
assert_test( strpos( $css_content, '.ge-dept-switch-btn' ) !== false, 'CSS contains alert switch button .ge-dept-switch-btn styling' );

// 13. Del 3: JS Navigation, Popstate and URL synchronization
assert_test( strpos( $js_content, 'DEPARTMENTS' ) !== false, 'JS contains DEPARTMENTS definition' );
assert_test( strpos( $js_content, 'inferDepartment' ) !== false, 'JS contains inferDepartment() function' );
assert_test( strpos( $js_content, 'renderDepartmentTabs' ) !== false, 'JS contains renderDepartmentTabs() function' );
assert_test( strpos( $js_content, 'renderCategoryChips' ) !== false, 'JS contains renderCategoryChips() function' );
assert_test( strpos( $js_content, 'switchDepartment' ) !== false, 'JS contains switchDepartment() function' );
assert_test( strpos( $js_content, 'ge-dept-alert' ) !== false, 'JS manages cross-department alert #ge-dept-alert' );
assert_test( strpos( $js_content, 'history.pushState' ) !== false, 'JS uses history.pushState for silent URL updates' );
assert_test( strpos( $js_content, 'popstate' ) !== false, 'JS handles browser popstate navigation' );

// 14. Del 4: Backend Dataflyt, CPT & Sikring av Eksisterende Moduler
class Test_Gear_Engine_Product_Upscaler extends Gear_Engine_Product_Upscaler {
	public function test_assign_category( int $post_id, string $category, string $title = '' ): void {
		$this->assign_category( $post_id, $category, $title );
	}
}

$test_upscaler = new Test_Gear_Engine_Product_Upscaler();

// Test 14.1: CPT meta constants
assert_test( Gear_Engine_CPT::META_SPECS === '_gear_engine_specs', 'Gear_Engine_CPT defines META_SPECS constant' );
assert_test( Gear_Engine_CPT::META_LOCK_CATEGORIES === '_gear_engine_lock_categories', 'Gear_Engine_CPT defines META_LOCK_CATEGORIES constant' );
assert_test( Gear_Engine_CPT::META_DEPARTMENT === '_gear_engine_department', 'Gear_Engine_CPT defines META_DEPARTMENT constant' );

// Test 14.2: Manual category lock (_gear_engine_lock_categories) protects against feed overwrites
$locked_post_id = 501;
$mock_post_meta[ $locked_post_id ][ Gear_Engine_CPT::META_LOCK_CATEGORIES ] = 1;
$mock_terms[ $locked_post_id ]['gear_category'] = array( 777 ); // existing custom category

$test_upscaler->test_assign_category( $locked_post_id, 'Tredemøller', 'Sole F85 Tredemølle' );
assert_test( $mock_terms[ $locked_post_id ]['gear_category'] === array( 777 ), 'Manual lock (_gear_engine_lock_categories) preserves custom terms from feed overwrites' );

// Test 14.3: Keyword mapping (Rule A) maps exercise items to standardized categories
$unlocked_post_id = 502;
$test_upscaler->test_assign_category( $unlocked_post_id, 'Annet', 'Abilica Rubber Band Treningsstrikk 3-pk' );
$strikk_term_id = crc32( 'Treningsstrikker' );
assert_test( in_array( $strikk_term_id, $mock_terms[ $unlocked_post_id ]['gear_category'], true ), 'Keyword mapping assigns Treningsstrikker when strikk keyword is present' );

$foam_post_id = 503;
$test_upscaler->test_assign_category( $foam_post_id, 'Annet', 'TriggerPoint GRID Foam Roller 33cm' );
$foam_term_id = crc32( 'Foam Rollers' );
assert_test( in_array( $foam_term_id, $mock_terms[ $foam_post_id ]['gear_category'], true ), 'Keyword mapping assigns Foam Rollers when foam roller keyword is present' );

// Test 14.4: Non-destructive fallback for items without keywords
$generic_post_id = 504;
$test_upscaler->test_assign_category( $generic_post_id, 'Sport > Tilbehør > Drikkeflaske Pro', 'Vannflaske 1L' );
$bottle_term_id = crc32( 'Drikkeflaske Pro' );
assert_test( in_array( $bottle_term_id, $mock_terms[ $generic_post_id ]['gear_category'], true ), 'Non-destructive fallback assigns leaf category when no keywords match' );

// Test 14.5: Spec synthesis in CPT & feed
$cpt_class_content = file_get_contents( GEAR_ENGINE_PATH . 'includes/class-gear-engine-cpt.php' );
assert_test( strpos( $cpt_class_content, 'gae_lock_categories' ) !== false, 'CPT metabox renders gae_lock_categories field' );
assert_test( strpos( $cpt_class_content, 'gae_department' ) !== false, 'CPT metabox renders gae_department select' );

// Test 15: Cross-link integration & Shortcode Guide
$hub_template = file_get_contents( GEAR_ENGINE_PATH . 'modules/gear-picker/templates/picker-hub.php' );
assert_test( strpos( $hub_template, 'gear-engine-hub__price-banner' ) !== false, 'Hub template contains price banner cross-link' );
assert_test( strpos( $hub_template, '/priser/' ) !== false, 'Hub price banner links to /priser/' );

$hub_css = file_get_contents( GEAR_ENGINE_PATH . 'modules/gear-picker/assets/css/gear-picker.css' );
assert_test( strpos( $hub_css, '.gear-engine-hub__price-banner' ) !== false, 'Gear picker CSS contains price banner styling' );

$guide_class = file_get_contents( GEAR_ENGINE_PATH . 'modules/admin/class-gear-engine-shortcode-guide.php' );
assert_test( strpos( $guide_class, 'gear_engine_price_comparison' ) !== false, 'Shortcode Guide references gear_engine_price_comparison tag' );
assert_test( strpos( $guide_class, "'priser'" ) !== false, 'Shortcode Guide registers /priser/ core page' );
assert_test( strpos( $guide_class, 'gear_engine_search' ) !== false, 'Shortcode Guide documents gear_engine_search shortcode' );

// Test 16: Global Omni-Search & Autosuggest Shortcode ([gear_engine_search])
assert_test( Gear_Engine_Price_Comparison::SEARCH_SHORTCODE_TAG === 'gear_engine_search', 'Defines SEARCH_SHORTCODE_TAG = gear_engine_search' );
assert_test( Gear_Engine_Price_Comparison::SEARCH_SHORTCODE_ALIAS === 'fysionett_sok', 'Defines SEARCH_SHORTCODE_ALIAS = fysionett_sok' );

$search_index = Gear_Engine_Price_Comparison::get_global_search_index();
assert_test( is_array( $search_index ) && isset( $search_index['tools'], $search_index['programs'] ), 'get_global_search_index() returns tools and programs arrays' );
assert_test( count( $search_index['tools'] ) >= 3, 'Global search index includes triage, chat, and buying guides' );
assert_test( count( $search_index['programs'] ) >= 1, 'Global search index includes training programs' );

$search_shortcode_html = $module->render_search_shortcode( array( 'placeholder' => 'Test søk...' ) );
assert_test( strpos( $search_shortcode_html, 'ge-m3-standalone-search' ) !== false, 'render_search_shortcode outputs .ge-m3-standalone-search container' );
assert_test( strpos( $search_shortcode_html, 'data-search-index=' ) !== false, 'render_search_shortcode embeds data-search-index' );
assert_test( strpos( $search_shortcode_html, 'Test søk...' ) !== false, 'render_search_shortcode respects custom placeholder attribute' );
assert_test( strpos( $html, 'data-search-index=' ) !== false, 'Main price comparison container also embeds data-search-index' );

assert_test( strpos( $js_content, 'attachOmniSearch' ) !== false && strpos( $js_content, 'initStandaloneSearchBars' ) !== false, 'JS defines attachOmniSearch and initStandaloneSearchBars' );
assert_test( strpos( $js_content, 'ge-m3-search-suggest' ) !== false && strpos( $css_content, '.ge-m3-search-suggest' ) !== false, 'JS and CSS support Material 3 autosuggest dropdown' );
assert_test( strpos( $js_content, "urlParams.get('open')" ) !== false, 'JS supports ?open={id} parameter to auto-expand product retailer prices' );

echo "========================================================\n";
if ( $failed === 0 ) {
	echo "ALL PRICE COMPARISON TESTS PASSED ($passed/$passed)\n";
} else {
	echo "TESTS FAILED ($failed failed, $passed passed)\n";
	exit( 1 );
}
echo "========================================================\n";
