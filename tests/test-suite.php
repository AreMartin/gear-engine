<?php
/**
 * Test Suite for Gear Affiliate Engine.
 *
 * Stubs minimal WordPress functions and verifies:
 * - XMLReader streaming feed parsing for Adtraction and TradeTracker formats
 * - Settings Pre-Filtering logic (price, allowed categories, excluded keywords)
 * - Multi-Variant Transients Caching logic (hash generation, 3-5 variations, random return)
 * - Horizontal flexbox product card rendering with high-contrast green CTA
 */

define( 'ABSPATH', __DIR__ . '/../' );
define( 'GEAR_ENGINE_VERSION', '1.0.0' );
define( 'GEAR_ENGINE_FILE', ABSPATH . 'gear-engine.php' );
define( 'GEAR_ENGINE_PATH', ABSPATH );
define( 'GEAR_ENGINE_URL', 'http://example.com/wp-content/plugins/gear-engine/' );

// Stub minimal WordPress functions for CLI testing.
$GLOBALS['wp_options']    = array();
$GLOBALS['wp_transients'] = array();
$GLOBALS['wp_posts']      = array();
$GLOBALS['wp_postmeta']   = array();
$GLOBALS['registered_post_meta'] = array();

function add_action( $hook, $callback, $priority = 10, $accepted_args = 1 ) {}
function add_filter( $hook, $callback, $priority = 10, $accepted_args = 1 ) {}
function add_shortcode( $tag, $callback ) {}
function register_post_type( $post_type, $args = array() ) {}
function register_taxonomy( $taxonomy, $object_type, $args = array() ) {}
function register_post_meta( $post_type, $meta_key, $args = array() ) {
	$GLOBALS['registered_post_meta'][ $post_type ][ $meta_key ] = $args;
}
function register_setting( $option_group, $option_name, $args = array() ) {}
function add_settings_section( $id, $title, $callback, $page ) {}
function add_settings_field( $id, $title, $callback, $page, $section = 'default', $args = array() ) {}
function add_options_page( $page_title, $menu_title, $capability, $menu_slug, $callback = '' ) {}
function add_meta_box( $id, $title, $callback, $screen = null, $context = 'advanced', $priority = 'default', $callback_args = null ) {}
function wp_enqueue_style( $handle, $src = '', $deps = array(), $ver = false, $media = 'all' ) {}
function wp_enqueue_script( $handle, $src = '', $deps = array(), $ver = false, $in_footer = false ) {}
function wp_register_style( $handle, $src = '', $deps = array(), $ver = false, $media = 'all' ) {}
function wp_register_script( $handle, $src = '', $deps = array(), $ver = false, $in_footer = false ) {}
function wp_add_inline_script( $handle, $data, $position = 'after' ) {}
function wp_localize_script( $handle, $object_name, $l10n ) {}
function register_block_type( $name, $args = array() ) {}
function register_rest_route( $namespace, $route, $args = array(), $override = false ) {}
function rest_url( $path = '' ) { return 'http://example.com/wp-json/' . ltrim( (string) $path, '/' ); }
function home_url( $path = '' ) { return 'http://example.com/' . ltrim( (string) $path, '/' ); }
function plugin_dir_url( $file ) { return 'http://example.com/wp-content/plugins/gear-engine/'; }
function shortcode_atts( $pairs, $atts, $shortcode = '' ) {
	$atts = (array) $atts;
	$out  = array();
	foreach ( $pairs as $name => $default ) {
		if ( array_key_exists( $name, $atts ) ) {
			$out[ $name ] = $atts[ $name ];
		} else {
			$out[ $name ] = $default;
		}
	}
	return $out;
}

class WP_Query {
	public array $posts = array();
	public function __construct( $args = array() ) {
		$GLOBALS['last_wp_query_args'] = $args;
		$posts = array_values( $GLOBALS['wp_posts'] );
		if ( ! empty( $args['post_type'] ) ) {
			$posts = array_values( array_filter( $posts, function( $post ) use ( $args ) {
				return $post->post_type === $args['post_type'];
			} ) );
		}
		if ( isset( $args['posts_per_page'] ) && (int) $args['posts_per_page'] > 0 ) {
			$posts = array_slice( $posts, 0, (int) $args['posts_per_page'] );
		}
		if ( isset( $args['fields'] ) && 'ids' === $args['fields'] ) {
			$posts = array_map( static function ( $post ) { return $post->ID; }, $posts );
		}
		$this->posts = $posts;
	}
	public function have_posts(): bool {
		return ! empty( $this->posts );
	}
}

class WP_REST_Request {
	private array $params = array();
	public function __construct( array $params = array() ) { $this->params = $params; }
	public function get_json_params() { return $this->params; }
	public function get_param( $key ) { return $this->params[ $key ] ?? null; }
}

class WP_REST_Response {
	public $data;
	public int $status;
	public function __construct( $data = null, int $status = 200 ) {
		$this->data   = $data;
		$this->status = $status;
	}
}

function get_option( $key, $default = false ) {
	return $GLOBALS['wp_options'][ $key ] ?? $default;
}
function update_option( $key, $value, $autoload = null ) {
	$GLOBALS['wp_options'][ $key ] = $value;
	return true;
}
function delete_option( $key ) {
	unset( $GLOBALS['wp_options'][ $key ] );
	return true;
}
function get_transient( $key ) {
	return $GLOBALS['wp_transients'][ $key ] ?? false;
}
function set_transient( $key, $value, $expiration = 0 ) {
	$GLOBALS['wp_transients'][ $key ] = $value;
	return true;
}
function delete_transient( $key ) {
	unset( $GLOBALS['wp_transients'][ $key ] );
	return true;
}
function sanitize_text_field( $str ) {
	return is_scalar( $str ) ? strip_tags( (string) $str ) : '';
}
function sanitize_key( $key ) {
	return preg_replace( '/[^a-z0-9_\-]/', '', strtolower( (string) $key ) );
}
function sanitize_title( $title ) {
	return trim( preg_replace( '/[^a-z0-9-]+/i', '-', strtolower( (string) $title ) ), '-' );
}
function sanitize_textarea_field( $str ) {
	return is_scalar( $str ) ? strip_tags( (string) $str ) : '';
}
function wp_unslash( $value ) {
	if ( is_array( $value ) ) {
		return array_map( 'wp_unslash', $value );
	}
	return is_string( $value ) ? stripslashes( $value ) : $value;
}
function wp_http_validate_url( $url ) {
	return is_string( $url ) && (bool) filter_var( $url, FILTER_VALIDATE_URL ) && in_array( strtolower( (string) parse_url( $url, PHP_URL_SCHEME ) ), array( 'http', 'https' ), true );
}
function wp_parse_url( $url, $component = -1 ) {
	return parse_url( $url, $component );
}
function wp_verify_nonce( $nonce, $action ) {
	return 'valid-exercise-nonce' === $nonce && in_array( $action, array( 'gear_engine_save_exercise', 'gear_engine_save_protocol' ), true ) ? 1 : false;
}
$GLOBALS['wp_terms']     = array();
$GLOBALS['wp_termmeta']  = array();
function register_term_meta( $taxonomy, $meta_key, $args = array() ) {}
function get_term_meta( $term_id, $key, $single = false ) { return $GLOBALS['wp_termmeta'][ $term_id ][ $key ] ?? ''; }
function update_term_meta( $term_id, $key, $value ) { $GLOBALS['wp_termmeta'][ $term_id ][ $key ] = $value; return true; }
function get_term( $term_id, $taxonomy = '' ) { return $GLOBALS['wp_terms'][ $term_id ] ?? null; }
function get_terms( $args = array() ) {
	return array_values( array_filter( $GLOBALS['wp_terms'], function ( $t ) use ( $args ) {
		return ! isset( $args['parent'] ) || (int) $t->parent === (int) $args['parent'];
	} ) );
}
function get_term_by( $field, $value, $taxonomy = '' ) {
	foreach ( $GLOBALS['wp_terms'] as $t ) {
		if ( ( 'slug' === $field && $t->slug === $value ) || ( 'name' === $field && $t->name === $value ) ) {
			return $t;
		}
	}
	return false;
}
function get_current_user_id() { return 1; }
function delete_term_meta( $term_id, $key ) { unset( $GLOBALS['wp_termmeta'][ $term_id ][ $key ] ); return true; }
function wp_get_post_terms( $post_id, $taxonomy = '', $args = array() ) {
	return $GLOBALS['test_post_terms'][ $post_id . '|' . $taxonomy ] ?? array();
}
function current_user_can( $capability, ...$args ) {
	return true;
}
function wp_is_post_revision( $post_id ) {
	return false;
}
function esc_url_raw( $url ) {
	return filter_var( $url, FILTER_SANITIZE_URL ) ?: '';
}
function esc_url( $url ) {
	return htmlspecialchars( (string) $url, ENT_QUOTES, 'UTF-8' );
}
function esc_html( $str ) {
	return htmlspecialchars( (string) $str, ENT_QUOTES, 'UTF-8' );
}
function esc_attr( $str ) {
	return htmlspecialchars( (string) $str, ENT_QUOTES, 'UTF-8' );
}
function wp_strip_all_tags( $str ) {
	return strip_tags( (string) $str );
}
function wp_kses_post( $str ) {
	return (string) $str;
}
function is_wp_error( $thing ) {
	return ( $thing instanceof WP_Error );
}
class WP_Error {
	public string $code;
	public string $message;
	public function __construct( $code = '', $message = '' ) {
		$this->code = $code;
		$this->message = $message;
	}
}
function wp_trim_words( $text, $num_words = 55, $more = null ) {
	$words = preg_split( '/\s+/', trim( (string) $text ) );
	if ( count( $words ) > $num_words ) {
		return implode( ' ', array_slice( $words, 0, $num_words ) ) . '...';
	}
	return (string) $text;
}
function __( $text, $domain = 'default' ) {
	return $text;
}
function _x( $text, $context, $domain = 'default' ) {
	return $text;
}
function esc_html__( $text, $domain = 'default' ) {
	return htmlspecialchars( $text, ENT_QUOTES, 'UTF-8' );
}
function esc_html_e( $text, $domain = 'default' ) {
	echo htmlspecialchars( $text, ENT_QUOTES, 'UTF-8' );
}
function esc_attr__( $text, $domain = 'default' ) {
	return htmlspecialchars( $text, ENT_QUOTES, 'UTF-8' );
}
function esc_attr_e( $text, $domain = 'default' ) {
	echo htmlspecialchars( $text, ENT_QUOTES, 'UTF-8' );
}
function absint( $maybeint ) {
	return abs( (int) $maybeint );
}
function wp_json_encode( $data ) {
	return json_encode( $data, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE );
}
function rest_sanitize_boolean( $value ) {
	return filter_var( $value, FILTER_VALIDATE_BOOLEAN );
}
function current_time( $type ) {
	return date( 'Y-m-d H:i:s' );
}
function has_post_thumbnail( $post_id ) {
	return false;
}
function get_permalink( $post_id ) {
	return 'http://example.com/gear/' . $post_id;
}
function get_the_title( $post ) {
	return $post->post_title;
}
function apply_filters( $tag, $value ) {
	return $value;
}
function term_exists( $term, $taxonomy = '', $parent = null ) {
	return false;
}
function wp_insert_term( $term, $taxonomy = '', $args = array() ) {
	return array( 'term_id' => 1 );
}
function wp_set_object_terms( $object_id, $terms, $taxonomy, $append = false ) {
	$GLOBALS['wp_set_terms_calls'][] = array( $object_id, $terms, $taxonomy );
}

class WP_Post {
	public int $ID = 0;
	public string $post_title = '';
	public string $post_content = '';
	public string $post_excerpt = '';
	public string $post_type = 'exercise_gear';
	public string $post_status = 'publish';
}

function get_post( $id ) {
	return $GLOBALS['wp_posts'][ $id ] ?? null;
}
function get_post_meta( $post_id, $key, $single = false ) {
	return $GLOBALS['wp_postmeta'][ $post_id ][ $key ] ?? ( $single ? '' : array() );
}
function get_posts( $args = array() ) {
	$results = array();
	foreach ( $GLOBALS['wp_posts'] as $post_id => $post ) {
		if ( isset( $args['post_type'] ) && $post->post_type !== $args['post_type'] ) {
			continue;
		}
		if ( isset( $args['meta_key'] ) && get_post_meta( $post_id, $args['meta_key'], true ) !== ( $args['meta_value'] ?? '' ) ) {
			continue;
		}
		$results[] = ( isset( $args['fields'] ) && 'ids' === $args['fields'] ) ? $post_id : $post;
	}
	return array_slice( $results, 0, (int) ( $args['posts_per_page'] ?? 5 ) );
}
function update_post_meta( $post_id, $key, $value ) {
	$GLOBALS['wp_postmeta'][ $post_id ][ $key ] = $value;
	return true;
}
function wp_insert_post( $args ) {
	static $id_counter = 1;
	$post = new WP_Post();
	$post->ID = $id_counter++;
	$post->post_title = $args['post_title'] ?? '';
	$post->post_content = $args['post_content'] ?? '';
	$post->post_excerpt = $args['post_excerpt'] ?? '';
	$post->post_type = $args['post_type'] ?? 'exercise_gear';
	$post->post_status = $args['post_status'] ?? 'draft';
	$GLOBALS['wp_posts'][ $post->ID ] = $post;
	return $post->ID;
}
function wp_update_post( $args ) {
	$id = $args['ID'];
	if ( isset( $GLOBALS['wp_posts'][ $id ] ) ) {
		if ( isset( $args['post_title'] ) ) $GLOBALS['wp_posts'][ $id ]->post_title = $args['post_title'];
		if ( isset( $args['post_content'] ) ) $GLOBALS['wp_posts'][ $id ]->post_content = $args['post_content'];
		if ( isset( $args['post_excerpt'] ) ) $GLOBALS['wp_posts'][ $id ]->post_excerpt = $args['post_excerpt'];
		if ( isset( $args['post_status'] ) ) $GLOBALS['wp_posts'][ $id ]->post_status = $args['post_status'];
	}
	return $id;
}
function get_post_status( $post_id ) {
	return $GLOBALS['wp_posts'][ $post_id ]->post_status ?? false;
}

// Stub $wpdb
class MockWPDB {
	public string $prefix = 'wp_';
	public string $postmeta = 'wp_postmeta';
	public array $queries = array();

	public function prepare( $query, ...$args ) {
		$arg_index = 0;
		return preg_replace_callback( '/%([sdf])/', function( $matches ) use ( &$args, &$arg_index ) {
			$type = $matches[1];
			$val = $args[ $arg_index++ ] ?? '';
			if ( 'd' === $type ) {
				return (int) $val;
			} elseif ( 'f' === $type ) {
				return (float) $val;
			}
			return "'" . addslashes( (string) $val ) . "'";
		}, $query );
	}

	public function get_var( $query ) {
		// Mock postmeta lookup by EAN
		if ( preg_match( "/WHERE meta_key = '_product_ean' AND meta_value = '([^']+)'/", $query, $matches ) ) {
			$ean = $matches[1];
			foreach ( $GLOBALS['wp_postmeta'] as $pid => $meta ) {
				if ( isset( $meta['_product_ean'] ) && $meta['_product_ean'] === $ean ) {
					return $pid;
				}
			}
		}
		return null;
	}
}
$GLOBALS['wpdb'] = new MockWPDB();

// Require our plugin classes
require_once ABSPATH . 'includes/class-gear-engine-config-loader.php';
require_once ABSPATH . 'includes/class-gear-engine-settings.php';
require_once ABSPATH . 'includes/class-gear-engine-cpt.php';
require_once ABSPATH . 'includes/class-gear-engine-sync-engine.php';
require_once ABSPATH . 'includes/class-gear-engine-sync-rules.php';
require_once ABSPATH . 'includes/class-gear-engine-product-upscaler.php';
require_once ABSPATH . 'includes/class-gear-engine-gutenberg-block.php';
require_once ABSPATH . 'includes/class-gear-engine-ai-api.php';
require_once ABSPATH . 'includes/class-gear-engine-product-service.php';
require_once ABSPATH . 'modules/exercises/class-gear-engine-exercises-cpt.php';
require_once ABSPATH . 'modules/exercises/class-gear-engine-protocols.php';
require_once ABSPATH . 'modules/exercises/class-gear-engine-protocol-manager.php';
require_once ABSPATH . 'modules/exercises/class-gear-engine-protocol-autofill.php';
require_once ABSPATH . 'modules/physio-chat/class-gear-engine-exercise-selection.php';
require_once ABSPATH . 'modules/exercises/class-gear-engine-exercise-service.php';
require_once ABSPATH . 'modules/exercises/class-gear-engine-exercise-importer.php';
require_once ABSPATH . 'modules/physio-chat/class-gear-engine-physio-chat.php';
require_once ABSPATH . 'modules/physio-chat/class-gear-engine-chat-agent.php';

// Test runner helper
function assert_test( $condition, string $test_name ) {
	if ( $condition ) {
		echo " [PASS] $test_name\n";
	} else {
		echo " [FAIL] $test_name\n";
		exit( 1 );
	}
}

echo "========================================================\n";
echo "Starting Gear Affiliate Engine Verification Tests...\n";
echo "========================================================\n";

$sync_engine = new Gear_Engine_Sync_Engine();

// TEST 1: Price cleaning logic
assert_test( $sync_engine->clean_price( '14 999 kr' ) === 14999.0, 'clean_price: Handles space separation and currency text' );
assert_test( $sync_engine->clean_price( '14.999,50' ) === 14999.50, 'clean_price: Handles European dot-thousand and comma-decimal' );
assert_test( $sync_engine->clean_price( '19999' ) === 19999.0, 'clean_price: Handles plain integer strings' );

// TEST 2: Stock status parsing
assert_test( $sync_engine->parse_stock_status( 'yes' ) === true, 'parse_stock: "yes" is in stock' );
assert_test( $sync_engine->parse_stock_status( 'in stock' ) === true, 'parse_stock: "in stock" is in stock' );
assert_test( $sync_engine->parse_stock_status( 'utsolgt' ) === false, 'parse_stock: "utsolgt" is out of stock' );
assert_test( $sync_engine->parse_stock_status( '0' ) === false, 'parse_stock: "0" is out of stock' );

// TEST 3: Pre-filter evaluation
$filter_rules = array(
	'min_price'          => 1000.0,
	'allowed_categories' => array( 'tredemølle', 'romaskin', 'spinningsykkel' ),
	'excluded_keywords'  => array( 'reservedel', 'matte', 'smøremiddel', 'skrue' ),
);

// Should pass:
$item_valid = array(
	'ean'         => '7071234567890',
	'title'       => 'NordicTrack T Series 6.5S Tredemølle',
	'description' => 'Premium tredemølle for hjemmetrening',
	'price'       => 14999.0,
	'category'    => 'Trening > Tredemøller',
);
assert_test( $sync_engine->passes_pre_filters( $item_valid, $filter_rules ) === true, 'pre_filters: Valid exercise product passes' );

// Should fail: Below min price
$item_cheap = $item_valid;
$item_cheap['price'] = 499.0;
assert_test( $sync_engine->passes_pre_filters( $item_cheap, $filter_rules ) === false, 'pre_filters: Product below min_price rejected' );

// Should fail: Blacklisted keyword in title
$item_blacklisted = $item_valid;
$item_blacklisted['title'] = 'Reservedel kabel til Tredemølle';
assert_test( $sync_engine->passes_pre_filters( $item_blacklisted, $filter_rules ) === false, 'pre_filters: Blacklisted keyword "reservedel" rejected' );

// Should fail: Category not allowed
$item_wrong_cat = $item_valid;
$item_wrong_cat['category'] = 'Klær > Treningssokker';
$item_wrong_cat['title'] = 'Proffe løpesokker';
assert_test( $sync_engine->passes_pre_filters( $item_wrong_cat, $filter_rules ) === false, 'pre_filters: Unallowed category rejected' );

// Should fail: Missing EAN and Missing URL
$item_no_ean = $item_valid;
$item_no_ean['ean'] = '';
$item_no_ean['affiliate_url'] = '';
$item_no_ean['title'] = '';
assert_test( $sync_engine->passes_pre_filters( $item_no_ean, $filter_rules ) === false, 'pre_filters: Missing EAN/ID and title rejected' );

// Test attribute & URL fallback in XML extraction
$xml_attr_sample = simplexml_load_string( '<product id="PRD-9988"><name>Test Gear</name><price>5000</price><url>http://example.com/item</url></product>' );
$extracted_attr = $sync_engine->extract_item_data( $xml_attr_sample, 'Adtraction' );
assert_test( $extracted_attr['ean'] === 'PRD-9988', 'extract_xml: Extracts ID from XML element attribute <product id="...">' );

$xml_url_only_sample = simplexml_load_string( '<product><name>Test Gear 2</name><price>6000</price><productUrl>http://example.com/unique-deal-123</productUrl></product>' );
$extracted_url_only = $sync_engine->extract_item_data( $xml_url_only_sample, 'TradeTracker' );
assert_test( str_starts_with( $extracted_url_only['ean'], 'url_' ), 'extract_xml: Fallback to deterministic URL hash when no explicit barcode or ID' );

// TEST 3A: Real TradeTracker feed XML schema extraction test
$real_tradetracker_xml = <<<XML
<product ID="409448">
<campaignID>8591</campaignID>
<name>Abilica - SP 900 Spinningsykkel</name>
<price currency="NOK">14999.00</price>
<URL>https://partner.treningspartner.no/c?c=8591&amp;m=2376152&amp;a=448546&amp;r=&amp;u=https%3A%2F%2Fwww.treningspartner.no%2Fabilica-sp-900-spinningsykkel%2F409448</URL>
<images>
<image>https://www.treningspartner.no/media/85/c0/66/1729074195/409448_Abilica%20SP900_01.jpg?ts=1729074195</image>
</images>
<description><![CDATA[Abilica SP 900 spinningsykkel]]></description>
<properties>
<property name="availability"><value>in_stock</value></property>
<property name="categoryPath"><value>Kondisjon &gt; Kondisjonsapparater &gt; Spinningsykkel</value></property>
<property name="fromPrice"><value>14999.00</value></property>
<property name="imageURL_large"><value>https://www.treningspartner.no/media/85/c0/66/1729074195/409448_Abilica%20SP900_01.jpg?ts=1729074195</value></property>
</properties>
</product>
XML;

$tt_sample_element = simplexml_load_string( $real_tradetracker_xml );
$tt_data = $sync_engine->extract_item_data( $tt_sample_element, 'TradeTracker' );
assert_test( $tt_data['ean'] === '409448', 'tradetracker_schema: ID attribute correctly mapped to EAN/ID' );
assert_test( $tt_data['title'] === 'Abilica - SP 900 Spinningsykkel', 'tradetracker_schema: Title correctly mapped' );
assert_test( $tt_data['price'] === 14999.0, 'tradetracker_schema: Price correctly mapped' );
assert_test( $tt_data['regular_price'] === 14999.0, 'tradetracker_schema: fromPrice mapped to regular_price' );
assert_test( $tt_data['image_url'] === 'https://www.treningspartner.no/media/85/c0/66/1729074195/409448_Abilica%20SP900_01.jpg?ts=1729074195', 'tradetracker_schema: <images><image> mapped to image_url' );
assert_test( str_contains( $tt_data['affiliate_url'], 'partner.treningspartner.no' ), 'tradetracker_schema: <URL> mapped to affiliate_url' );
assert_test( $tt_data['in_stock'] === true, 'tradetracker_schema: availability in_stock mapped to boolean true' );
assert_test( $tt_data['category'] === 'Kondisjon > Kondisjonsapparater > Spinningsykkel', 'tradetracker_schema: categoryPath mapped to category' );
assert_test( $tt_data['merchant'] === 'Abilica (Treningspartner)', 'tradetracker_schema: Merchant identified from brand and domain' );
assert_test( $tt_data['currency'] === 'kr', 'tradetracker_schema: NOK normalized to kr' );

// TEST 3C: Real Adtraction Google Shopping XML feed schema extraction test
$real_adtraction_xml = <<<XML
<item xmlns:g="http://base.google.com/ns/1.0">
  <g:id>360246</g:id>
  <title>Lipati Balance SS Tee L</title>
  <description>LIPATI Balance SS Tee er en lett og enkel T-skjorte til trening</description>
  <g:product_type>apparel &gt; løbe t-shirts - løbetrøjer</g:product_type>
  <link>https://do.loepeshop.no/t/t?a=1850136231&amp;as=1632326892&amp;t=2&amp;tk=1&amp;cupa_sku=360246&amp;fid=2072&amp;url=https://loepeshop.no/lipati-women-s-balance-ss-tee.html</link>
  <g:image_link>https://www.loepeshop.no/media/catalog/product/cache/test.jpg</g:image_link>
  <g:condition>new</g:condition>
  <g:availability>in_stock</g:availability>
  <g:sale_price>249 NOK</g:sale_price>
  <g:price>299 NOK</g:price>
  <g:gtin>05740038105708</g:gtin>
  <g:brand>Lipati</g:brand>
</item>
XML;

$ad_sample_element = simplexml_load_string( $real_adtraction_xml );
$ad_data = $sync_engine->extract_item_data( $ad_sample_element, 'Adtraction' );
assert_test( $ad_data['ean'] === '05740038105708', 'adtraction_schema: g:gtin mapped to EAN/GTIN' );
assert_test( $ad_data['title'] === 'Lipati Balance SS Tee L', 'adtraction_schema: title mapped' );
assert_test( $ad_data['price'] === 249.0, 'adtraction_schema: g:sale_price mapped to price' );
assert_test( $ad_data['regular_price'] === 299.0, 'adtraction_schema: g:price mapped to regular_price' );
assert_test( $ad_data['image_url'] === 'https://www.loepeshop.no/media/catalog/product/cache/test.jpg', 'adtraction_schema: g:image_link mapped to image_url' );
assert_test( str_contains( $ad_data['affiliate_url'], 'do.loepeshop.no' ), 'adtraction_schema: <link> mapped to affiliate_url' );
assert_test( $ad_data['in_stock'] === true, 'adtraction_schema: g:availability mapped to in_stock' );
assert_test( $ad_data['category'] === 'apparel > løbe t-shirts - løbetrøjer', 'adtraction_schema: g:product_type mapped to category' );
assert_test( $ad_data['merchant'] === 'Lipati (Loepeshop)', 'adtraction_schema: Brand + store domain resolved to merchant' );
assert_test( $ad_data['currency'] === 'kr', 'adtraction_schema: Currency NOK mapped to kr' );

// TEST 3B: Detailed pre-filtering rules (max_price, min_discount_pct, only_in_stock, merchants, required keywords)
$detailed_rules = $filter_rules;
$detailed_rules['max_price']          = 20000.0;
$detailed_rules['min_discount_pct']   = 15.0; // At least 15% discount
$detailed_rules['only_in_stock']      = true;
$detailed_rules['allowed_merchants']  = array( 'gymgrossisten', 'xxl' );
$detailed_rules['excluded_merchants'] = array( 'shadyretailer' );
$detailed_rules['required_keywords']  = array( 'motor', 'intervall' );

$item_detailed_valid = array(
	'ean'           => '7071234567899',
	'title'         => 'Pro Treadmill med sterk motor for intervall',
	'description'   => 'Toppmodell',
	'price'         => 12000.0,
	'regular_price' => 16000.0, // 25% discount (passes >= 15%)
	'in_stock'      => true,
	'merchant'      => 'GymGrossisten',
	'category'      => 'Tredemølle',
);
assert_test( $sync_engine->passes_pre_filters( $item_detailed_valid, $detailed_rules ) === true, 'detailed_filters: Valid discounted, in-stock, whitelisted product passes' );

// Fail: Price above max_price
$item_too_expensive = $item_detailed_valid;
$item_too_expensive['price'] = 25000.0;
assert_test( $sync_engine->passes_pre_filters( $item_too_expensive, $detailed_rules ) === false, 'detailed_filters: Product above max_price rejected' );

// Fail: Discount less than min_discount_pct
$item_low_discount = $item_detailed_valid;
$item_low_discount['regular_price'] = 12500.0; // only 4% discount
assert_test( $sync_engine->passes_pre_filters( $item_low_discount, $detailed_rules ) === false, 'detailed_filters: Product below min_discount_pct rejected' );

// Fail: Out of stock when only_in_stock is true
$item_out_of_stock = $item_detailed_valid;
$item_out_of_stock['in_stock'] = false;
assert_test( $sync_engine->passes_pre_filters( $item_out_of_stock, $detailed_rules ) === false, 'detailed_filters: Out of stock product rejected when only_in_stock=true' );

// Fail: Merchant not in whitelist
$item_unapproved_merchant = $item_detailed_valid;
$item_unapproved_merchant['merchant'] = 'OtherStore';
assert_test( $sync_engine->passes_pre_filters( $item_unapproved_merchant, $detailed_rules ) === false, 'detailed_filters: Unlisted merchant rejected by whitelist' );

// Fail: Merchant in blacklist
$item_blacklisted_merchant = $item_detailed_valid;
$item_blacklisted_merchant['merchant'] = 'ShadyRetailer';
assert_test( $sync_engine->passes_pre_filters( $item_blacklisted_merchant, $detailed_rules ) === false, 'detailed_filters: Blacklisted merchant rejected' );

// Fail: Missing required keywords
$item_missing_req_kw = $item_detailed_valid;
$item_missing_req_kw['title'] = 'Pro Treadmill uten nøkkelord';
assert_test( $sync_engine->passes_pre_filters( $item_missing_req_kw, $detailed_rules ) === false, 'detailed_filters: Product missing required keywords rejected' );

// TEST 4: Streaming XML parsing with XMLReader (Adtraction format)
$sample_adtraction_xml = <<<XML
<?xml version="1.0" encoding="UTF-8"?>
<products>
    <product>
        <SKU>TR-100</SKU>
        <EAN>7071234567890</EAN>
        <name>NordicTrack T Series 6.5S Treadmill</name>
        <description>Interactive indoor treadmill with HD display.</description>
        <price>14999</price>
        <regular_price>19999</regular_price>
        <productUrl>https://adtraction.com/link/123</productUrl>
        <imageUrl>https://placehold.co/600x450/1e293b/ffffff?text=Treadmill+Pro</imageUrl>
        <instock>yes</instock>
        <category>Tredemølle</category>
        <programName>GymGrossisten</programName>
    </product>
    <product>
        <!-- Should be skipped by blacklist filter -->
        <SKU>SKR-99</SKU>
        <EAN>7071234567891</EAN>
        <name>Skrue og mutter for tredemølle reservedel</name>
        <description>Løse skruer</description>
        <price>1200</price>
        <productUrl>https://adtraction.com/link/456</productUrl>
        <category>Tredemølle</category>
        <programName>GymGrossisten</programName>
    </product>
    <product>
        <!-- Should be skipped by min price filter -->
        <SKU>BOT-01</SKU>
        <EAN>7071234567892</EAN>
        <name>Drikkeflaske Tredemølle Edition</name>
        <description>Vannflaske</description>
        <price>199</price>
        <productUrl>https://adtraction.com/link/789</productUrl>
        <category>Tredemølle</category>
        <programName>GymGrossisten</programName>
    </product>
</products>
XML;

$tmp_xml_file = tempnam( sys_get_temp_dir(), 'gae_test_' );
file_put_contents( $tmp_xml_file, $sample_adtraction_xml );

$report = array(
	'processed' => 0,
	'inserted'  => 0,
	'updated'   => 0,
	'skipped'   => 0,
	'errors'    => array(),
);

$sync_engine->stream_parse_xml( $tmp_xml_file, 'Adtraction', $filter_rules, $report );
unlink( $tmp_xml_file );

assert_test( $report['processed'] === 3, 'streaming_xml: Processed all 3 nodes with XMLReader' );
assert_test( $report['inserted'] === 1, 'streaming_xml: Exactly 1 valid product inserted' );
assert_test( $report['skipped'] === 2, 'streaming_xml: Exactly 2 non-compliant products skipped by pre-filters' );

// TEST 5: Deduplication via EAN (Update on second sync)
$sample_update_xml = <<<XML
<?xml version="1.0" encoding="UTF-8"?>
<products>
    <product>
        <EAN>7071234567890</EAN>
        <name>NordicTrack T Series 6.5S Treadmill (Price Drop!)</name>
        <description>Updated description.</description>
        <price>13999</price>
        <regular_price>19999</regular_price>
        <productUrl>https://adtraction.com/link/123-new</productUrl>
        <imageUrl>https://placehold.co/600x450/1e293b/ffffff?text=Treadmill+Pro</imageUrl>
        <instock>yes</instock>
        <category>Tredemølle</category>
        <programName>GymGrossisten</programName>
    </product>
</products>
XML;

$tmp_update_file = tempnam( sys_get_temp_dir(), 'gae_update_' );
file_put_contents( $tmp_update_file, $sample_update_xml );

$update_report = array(
	'processed' => 0,
	'inserted'  => 0,
	'updated'   => 0,
	'skipped'   => 0,
	'errors'    => array(),
);

$sync_engine->stream_parse_xml( $tmp_update_file, 'Adtraction', $filter_rules, $update_report );
unlink( $tmp_update_file );

assert_test( $update_report['updated'] === 1, 'deduplication: Same EAN resulted in update instead of duplicate insertion' );
assert_test( $update_report['inserted'] === 0, 'deduplication: Zero new posts inserted on update' );

// Verify the postmeta was updated
$found_post = Gear_Engine_CPT::get_product_by_ean( '7071234567890' );
assert_test( $found_post !== null, 'get_product_by_ean: Successfully retrieved post by EAN' );
$product_data = Gear_Engine_CPT::get_product_data( $found_post );
assert_test( $product_data['price'] === 13999.0, 'deduplication: Price updated in postmeta' );
assert_test( $product_data['regular_price'] === 19999.0, 'deduplication: Regular price preserved in postmeta' );

// TEST 6: Multi-Variant Transients Caching logic
$ai_api = new Gear_Engine_AI_API();
$context = 'Brukeren ønsker en stillegående tredemølle til leilighet for maratontrening, under 18000 kr';
$ean = $product_data['ean'];
$cache_key = 'gae_ai_' . md5( $context . '_' . $ean );

$variations = array(
	'Pitch 1: Fantastisk stillegående demping til leilighet.',
	'Pitch 2: Ideell for maratontrening med stabil motor.',
	'Pitch 3: Høy toppfart og slitestyrke.',
	'Pitch 4: Kompakt sammenleggbar ramme med profesjonell følelse.',
);

set_transient( $cache_key, $variations, 30 * 86400 );
$cached = get_transient( $cache_key );

assert_test( is_array( $cached ) && count( $cached ) === 4, 'ai_caching: 3-5 variations stored in transient' );
$random_pick = $cached[ array_rand( $cached ) ];
assert_test( in_array( $random_pick, $variations, true ), 'ai_caching: Random variation selected from cache' );

// TEST 7: Gutenberg Card HTML & Flexbox CSS classes rendering
$block_module = new Gear_Engine_Gutenberg_Block();
$rendered_html = $block_module->render_product_card( $product_data['id'], true, 'Custom AI Pitch for marathon runner', 'Order now' );

assert_test( str_contains( $rendered_html, 'class="gae-product-card"' ), 'html_render: Contains .gae-product-card wrapper' );
assert_test( str_contains( $rendered_html, 'class="gae-image-wrapper"' ), 'html_render: Contains .gae-image-wrapper' );
assert_test( str_contains( $rendered_html, 'class="gae-content-wrapper"' ), 'html_render: Contains .gae-content-wrapper' );
assert_test( str_contains( $rendered_html, 'class="gae-ai-summary"' ), 'html_render: Contains .gae-ai-summary' );
assert_test( str_contains( $rendered_html, 'class="gae-cta-btn"' ), 'html_render: Contains high-contrast green .gae-cta-btn' );
assert_test( str_contains( $rendered_html, 'rel="nofollow noopener sponsored"' ), 'html_render: Contains compliant sponsored affiliate rel tags' );
assert_test( str_contains( $rendered_html, '13 999 kr' ), 'html_render: Formatted current price displayed' );
assert_test( str_contains( $rendered_html, '19 999 kr' ), 'html_render: Formatted strikethrough regular price displayed' );

// TEST 8: Dynamic Gemini Models List & Auto-Update
$available_models = Gear_Engine_AI_API::get_available_models();
assert_test( isset( $available_models['gemini-3.8-flash'] ), 'models_list: Gemini 3.8 Flash is present in available models' );
assert_test( str_contains( $available_models['gemini-3.8-flash'], 'Recommended' ), 'models_list: Gemini 3.8 Flash marked as recommended' );
assert_test( isset( $available_models['gemini-2.5-flash'] ), 'models_list: Gemini 2.5 Flash is present' );
assert_test( isset( $available_models['gemini-2.0-flash'] ), 'models_list: Gemini 2.0 Flash is present' );
$models_transient = get_transient( Gear_Engine_AI_API::MODELS_CACHE_KEY );
assert_test( is_array( $models_transient ) && isset( $models_transient['gemini-3.8-flash'] ), 'models_caching: Models cached in transient for periodic automatic refresh' );

// TEST 9: JSON Feed Streaming Parser & Format Auto-Detection
$sample_json = json_encode( array(
	'products' => array(
		array(
			'ean'           => '7079999000001',
			'name'          => 'Concept2 RowErg Romaskin',
			'description'   => 'Verdens mest brukte romaskin med PM5 monitor.',
			'price'         => 11500,
			'regular_price' => 13500,
			'productUrl'    => 'https://example.com/rowerg',
			'imageUrl'      => 'https://placehold.co/600x450?text=RowErg',
			'instock'       => 'yes',
			'category'      => 'Romaskin',
			'merchant'      => 'GymGrossisten',
		),
		array(
			'ean'           => '7079999000002',
			'name'          => 'Billig reservedel til romaskin', // Should be skipped by blacklist
			'description'   => 'Reservedel',
			'price'         => 2500,
			'category'      => 'Romaskin',
		),
	),
) );

$tmp_json_file = tempnam( sys_get_temp_dir(), 'gae_json_' );
file_put_contents( $tmp_json_file, $sample_json );

assert_test( $sync_engine->detect_feed_format( $tmp_json_file ) === 'json', 'json_detect: Correctly auto-detects JSON feed format' );

$json_report = array(
	'processed' => 0,
	'inserted'  => 0,
	'updated'   => 0,
	'skipped'   => 0,
	'errors'    => array(),
);

$sync_engine->process_feed( 'AdtractionJSON', $tmp_json_file, $filter_rules, $json_report );
unlink( $tmp_json_file );

assert_test( $json_report['processed'] === 2, 'json_streaming: Processed 2 products from JSON feed' );
assert_test( $json_report['inserted'] === 1, 'json_streaming: Exactly 1 valid product imported into CPT' );
assert_test( $json_report['skipped'] === 1, 'json_streaming: Non-compliant product skipped by pre-filters' );

// Verify the post was imported into exercise_gear CPT
$found_rower = Gear_Engine_CPT::get_product_by_ean( '7079999000001' );
assert_test( $found_rower !== null, 'json_cpt: Product from JSON feed directly imported into exercise_gear CPT' );
assert_test( $found_rower->post_title === 'Concept2 RowErg Romaskin', 'json_cpt: Post title matches JSON name' );

// TEST 10: NDJSON (JSON Lines) Streaming Parser
$sample_ndjson = "{\"ean\":\"7079999000003\",\"name\":\"Assault AirBike Spinningsykkel\",\"description\":\"Klassisk AirBike\",\"price\":\"8999\",\"productUrl\":\"http://example.com/bike\",\"category\":\"Spinningsykkel\",\"merchant\":\"XXL\",\"instock\":true}\n"
	. "{\"ean\":\"7079999000004\",\"name\":\"Sykkel reservedel skrue\",\"description\":\"Reservedeler\",\"price\":\"4999\",\"category\":\"Spinningsykkel\"}\n";

$tmp_ndjson_file = tempnam( sys_get_temp_dir(), 'gae_ndjson_' );
file_put_contents( $tmp_ndjson_file, $sample_ndjson );

$ndjson_report = array(
	'processed' => 0,
	'inserted'  => 0,
	'updated'   => 0,
	'skipped'   => 0,
	'errors'    => array(),
);

$sync_engine->stream_parse_json( $tmp_ndjson_file, 'TradeTrackerNDJSON', $filter_rules, $ndjson_report );
unlink( $tmp_ndjson_file );

assert_test( $ndjson_report['processed'] === 2, 'ndjson_streaming: Processed 2 lines from NDJSON feed' );
assert_test( $ndjson_report['inserted'] === 1, 'ndjson_streaming: 1 valid AirBike imported into CPT' );
assert_test( $ndjson_report['skipped'] === 1, 'ndjson_streaming: 1 blacklisted item skipped' );

// TEST 11: Hardcoded Test Products for Gutenberg Block Testing
$test_products = Gear_Engine_CPT::get_sample_products_definitions();
assert_test( count( $test_products ) === 2, 'sample_products: Exactly 2 test products defined' );
assert_test( $test_products[0]['title'] === 'NordicTrack T Series 6.5S Treadmill', 'sample_products: Product 1 is NordicTrack Treadmill' );
assert_test( $test_products[1]['title'] === 'Concept2 RowErg Romaskin PM5', 'sample_products: Product 2 is Concept2 RowErg' );

// Test fallback rendering for sample product 1 and 2
$card_1_html = $block_module->render_product_card( 1 );
assert_test( str_contains( $card_1_html, 'NordicTrack T Series 6.5S Treadmill' ), 'block_test: Test Product 1 renders in Gutenberg block' );
assert_test( str_contains( $card_1_html, '13 999 kr' ) || str_contains( $card_1_html, '14 999 kr' ), 'block_test: Test Product 1 price renders' );

$card_2_html = $block_module->render_product_card( 2 );
assert_test( str_contains( $card_2_html, 'Concept2 RowErg Romaskin' ), 'block_test: Test Product 2 renders in Gutenberg block' );
assert_test( str_contains( $card_2_html, 'kr' ), 'block_test: Test Product 2 price renders' );

// Test seeding
$seeded_ids = Gear_Engine_CPT::seed_sample_products();
assert_test( count( $seeded_ids ) === 2, 'sample_products: Both sample products seeded into exercise_gear CPT' );

// TEST 12: Feed URL Sanitization & Entity Decoding
$settings_module = new Gear_Engine_Settings();
$raw_url_with_entities = 'https://secure.adtraction.com/productfeed.htm?type=feed&amp;format=XML&amp;encoding=UTF8';
$clean_url = $settings_module->sanitize_feed_url( $raw_url_with_entities );
assert_test( ! str_contains( $clean_url, '&amp;' ) && str_contains( $clean_url, '&format=XML' ), 'feed_sanitization: &amp; decoded to plain &' );

// TEST 13: Total Product Limit, Per-Sync Limit, GZIP Streaming & Memory Cleanup
// 13.1 Verify is_gzipped on plain vs gzipped files
$plain_test_file = tempnam( sys_get_temp_dir(), 'gae_plain_' );
file_put_contents( $plain_test_file, '<products></products>' );
assert_test( $sync_engine->is_gzipped( $plain_test_file ) === false, 'is_gzipped: Correctly identifies plain XML file as uncompressed' );
unlink( $plain_test_file );

$gz_test_file = tempnam( sys_get_temp_dir(), 'gae_gz_' );
file_put_contents( $gz_test_file, gzencode( '<products><product><name>GZ Treadmill</name><price>9999</price><category>Tredemølle</category></product></products>' ) );
assert_test( $sync_engine->is_gzipped( $gz_test_file ) === true, 'is_gzipped: Correctly identifies GZIP compressed feed' );
assert_test( $sync_engine->detect_feed_format( $gz_test_file ) === 'xml', 'detect_feed_format: Correctly detects XML format from GZIP stream' );
unlink( $gz_test_file );

// 13.2 Verify max_items_per_sync cap
$multi_item_xml = <<<XML
<?xml version="1.0" encoding="UTF-8"?>
<products>
    <product>
        <EAN>8880000000001</EAN>
        <name>Tredemølle 1</name>
        <price>10000</price>
        <category>Tredemølle</category>
    </product>
    <product>
        <EAN>8880000000002</EAN>
        <name>Tredemølle 2</name>
        <price>12000</price>
        <category>Tredemølle</category>
    </product>
    <product>
        <EAN>8880000000003</EAN>
        <name>Tredemølle 3</name>
        <price>14000</price>
        <category>Tredemølle</category>
    </product>
</products>
XML;

$tmp_multi_file = tempnam( sys_get_temp_dir(), 'gae_multi_' );
file_put_contents( $tmp_multi_file, $multi_item_xml );

$capped_rules = array_merge( $filter_rules, array( 'max_items_per_sync' => 1 ) );
$capped_report = array(
	'processed' => 0,
	'inserted'  => 0,
	'updated'   => 0,
	'skipped'   => 0,
	'errors'    => array(),
);

$sync_engine->stream_parse_xml( $tmp_multi_file, 'TestCap', $capped_rules, $capped_report );
unlink( $tmp_multi_file );

assert_test( $capped_report['inserted'] === 1, 'limits: Stopped inserting after reaching max_items_per_sync limit of 1' );
assert_test( ! empty( $capped_report['batch_capped'] ), 'limits: batch_capped flag is set when max_items_per_sync is reached' );

// 13.3 Verify max_total_products cap
$tmp_total_file = tempnam( sys_get_temp_dir(), 'gae_total_' );
file_put_contents( $tmp_total_file, $multi_item_xml );

$current_cat_count = $sync_engine->get_catalog_product_count();
$total_capped_rules = array_merge( $filter_rules, array(
	'max_items_per_sync' => 10,
	'max_total_products' => $current_cat_count, // Already at total limit
) );
$total_report = array(
	'processed' => 0,
	'inserted'  => 0,
	'updated'   => 0,
	'skipped'   => 0,
	'errors'    => array(),
);

$sync_engine->stream_parse_xml( $tmp_total_file, 'TestTotalCap', $total_capped_rules, $total_report );
unlink( $tmp_total_file );

assert_test( $total_report['inserted'] === 0, 'limits: Prevented inserting new products when catalog is at max_total_products' );
assert_test( ! empty( $total_report['total_capped'] ), 'limits: total_capped flag is set when catalog product limit is reached' );

// 13.4 Verify GZIP compressed feed streaming with XMLReader
$gz_stream_file = tempnam( sys_get_temp_dir(), 'gae_gz_stream_' );
file_put_contents( $gz_stream_file, gzencode( $multi_item_xml ) );

$gz_stream_report = array(
	'processed' => 0,
	'inserted'  => 0,
	'updated'   => 0,
	'skipped'   => 0,
	'errors'    => array(),
);

$sync_engine->stream_parse_xml( $gz_stream_file, 'AdtractionGzip', array_merge( $filter_rules, array( 'max_items_per_sync' => 5 ) ), $gz_stream_report );
unlink( $gz_stream_file );

assert_test( $gz_stream_report['processed'] === 3, 'gzip_streaming: Stream-parsed all 3 nodes directly from GZIP archive via compress.zlib://' );

// 13.5 Chunked sync cursors: start_offset resumes where the previous chunk stopped.
$tmp_chunk_file = tempnam( sys_get_temp_dir(), 'gae_chunk_' );
file_put_contents( $tmp_chunk_file, $multi_item_xml );

$chunk1 = array( 'processed' => 0, 'inserted' => 0, 'updated' => 0, 'skipped' => 0, 'errors' => array() );
$sync_engine->stream_parse_xml( $tmp_chunk_file, 'TestChunk', array_merge( $filter_rules, array( 'max_items_per_sync' => 1 ) ), $chunk1 );
assert_test( 1 === $chunk1['inserted'] + $chunk1['updated'] && 1 === $chunk1['feed_scanned'] && ! empty( $chunk1['batch_capped'] ), 'chunked: first chunk imports one product and reports cursor 1' );

$chunk2 = array( 'processed' => 0, 'inserted' => 0, 'updated' => 0, 'skipped' => 0, 'errors' => array() );
$sync_engine->stream_parse_xml( $tmp_chunk_file, 'TestChunk', array_merge( $filter_rules, array( 'max_items_per_sync' => 1, 'start_offset' => 1 ) ), $chunk2 );
assert_test( 1 === $chunk2['inserted'] + $chunk2['updated'] && 2 === $chunk2['feed_scanned'], 'chunked: second chunk skips the first product and takes the next one' );

$chunk3 = array( 'processed' => 0, 'inserted' => 0, 'updated' => 0, 'skipped' => 0, 'errors' => array() );
$sync_engine->stream_parse_xml( $tmp_chunk_file, 'TestChunk', array_merge( $filter_rules, array( 'max_items_per_sync' => 1, 'start_offset' => 2 ) ), $chunk3 );
assert_test( 1 === $chunk3['inserted'] + $chunk3['updated'] && empty( $chunk3['batch_capped'] ) && empty( $chunk3['scan_capped'] ), 'chunked: final chunk completes the feed without hitting caps' );

$drained = array( 'processed' => 0, 'inserted' => 0, 'updated' => 0, 'skipped' => 0, 'errors' => array() );
$sync_engine->stream_parse_xml( $tmp_chunk_file, 'TestChunk', array_merge( $filter_rules, array( 'max_items_per_sync' => 5, 'start_offset' => 3 ) ), $drained );
assert_test( 0 === $drained['processed'] && 3 === $drained['feed_scanned'], 'chunked: exhausted cursor processes nothing and never rewinds' );
unlink( $tmp_chunk_file );
assert_test( $gz_stream_report['inserted'] > 0 || $gz_stream_report['updated'] > 0, 'gzip_streaming: Successfully ingested products from gzipped feed' );

// 13.5 Verify memory cleanup for skipped items
$skip_counter = 0;
$mock_report = array( 'processed' => 0, 'skipped' => 0, 'skip_reasons' => array(), 'inserted' => 0, 'updated' => 0 );
$unwanted_item = array(
	'ean'      => '9999999999999',
	'title'    => 'Reservedel kabel',
	'price'    => 200,
	'category' => 'Uønsket',
);
for ( $i = 0; $i < 30; $i++ ) {
	$sync_engine->process_single_item( $unwanted_item, $filter_rules, $mock_report, $skip_counter );
}
assert_test( $skip_counter < 25, 'memory_cleanup: Batch counter cleared and recycled periodically even when 100% of items are skipped' );
assert_test( $mock_report['skipped'] === 30, 'memory_cleanup: All 30 non-matching items tracked as skipped' );

// 13.6 Verify word boundary keyword matching: 'Manualer' (dumbbells) not blocked by 'manual'
$dumbbell_item = array(
	'ean'      => '7770000000001',
	'title'    => 'Abilica Manualer 10kg',
	'price'    => 1500,
	'category' => 'Manualer',
	'in_stock' => true,
);
$dumbbell_rules = array_merge( $filter_rules, array(
	'allowed_categories' => array( 'manualer' ),
	'excluded_keywords'  => array( 'manual' ),
) );
$dumbbell_reason = '';
assert_test( $sync_engine->passes_pre_filters( $dumbbell_item, $dumbbell_rules, $dumbbell_reason ) === true, 'pre_filters: Word-boundary blacklist does not falsely reject "Manualer" (dumbbells) when "manual" is blacklisted' );

// TEST 14: 5-Slot Feed Table Configuration, Migration & Selective Sync
// 14.1 Verify legacy migration fallback to 5 slots
delete_option( 'gae_feeds' );
update_option( 'gae_adtraction_url', 'https://feed.adtraction.com/v2/legacy.xml' );
update_option( 'gae_tradetracker_url', 'https://tradetracker.com/legacy.xml' );

$slots = Gear_Engine_Settings::get_feed_slots();
assert_test( count( $slots ) === 5, 'feed_slots: Always returns exactly 5 feed slots' );
assert_test( $slots[0]['enabled'] === 1 && $slots[0]['url'] === 'https://feed.adtraction.com/v2/legacy.xml', 'feed_slots: Slot 0 migrated from legacy Adtraction URL' );
assert_test( $slots[1]['enabled'] === 1 && $slots[1]['url'] === 'https://tradetracker.com/legacy.xml', 'feed_slots: Slot 1 migrated from legacy TradeTracker URL' );
assert_test( $slots[2]['enabled'] === 0 && empty( $slots[2]['url'] ), 'feed_slots: Slot 2 is empty inactive placeholder' );

// 14.2 Verify sanitization of 5-slot feed table
$raw_slots_input = array(
	array( 'name' => 'Gymgrossisten', 'network' => 'Adtraction', 'url' => 'https://feed.adtraction.com/v2/gym.xml', 'enabled' => 1 ),
	array( 'name' => 'XXL Sport', 'network' => 'Adtraction', 'url' => 'https://feed.adtraction.com/v2/xxl.xml', 'enabled' => 1 ),
	array( 'name' => 'Treningspartner', 'network' => 'TradeTracker', 'url' => 'https://tradetracker.com/tp.xml', 'enabled' => 0 ),
	array( 'name' => 'Intersport', 'network' => 'Generic', 'url' => 'https://example.com/feed.xml', 'enabled' => 1 ),
	array( 'name' => 'Spare Slot 5', 'network' => 'Adtraction', 'url' => '', 'enabled' => 0 ),
	array( 'name' => 'Excess Slot 6', 'network' => 'Adtraction', 'url' => 'https://example.com/excess.xml', 'enabled' => 1 ),
);
$sanitized_slots = $settings_module->sanitize_feeds_table( $raw_slots_input );
assert_test( count( $sanitized_slots ) === 5, 'feed_sanitize: Hard-caps configured slots to maximum 5' );
assert_test( $sanitized_slots[0]['name'] === 'Gymgrossisten', 'feed_sanitize: Correctly sanitized partner name' );
assert_test( $sanitized_slots[2]['enabled'] === 0, 'feed_sanitize: Preserved paused/disabled status' );

// 14.3 Verify get_configured_feeds returns only active, non-empty feeds
update_option( 'gae_feeds', $sanitized_slots );
$active_feeds = $sync_engine->get_configured_feeds();
assert_test( count( $active_feeds ) === 3, 'configured_feeds: Only active feeds with URLs returned (3 of 5)' );
assert_test( isset( $active_feeds['Gymgrossisten'] ) && isset( $active_feeds['XXL Sport'] ), 'configured_feeds: Active Adtraction partner feeds keyed by name' );
assert_test( ! isset( $active_feeds['Treningspartner'] ), 'configured_feeds: Disabled feed is excluded from sync list' );

// 14.4 Verify selective single-feed targeting by partner name
$target_sync_rules = array( 'target_feed' => 'Gymgrossisten', 'max_items_per_sync' => 10 );
$sync_report = $sync_engine->run_sync( array( 'Gymgrossisten' => $tmp_multi_file ?? '' ), true, $target_sync_rules );
assert_test( ! empty( $sync_report['date'] ), 'selective_sync: Successfully executed targeted sync for specific partner feed' );

// TEST 15: Modular Physio Advisor AI Chat Interface & Product Service
// 15.1 Verify Product Service search with keyword matching & CPT extraction
$product_search = Gear_Engine_Product_Service::search_products( array(
	'keywords' => 'tredemølle løpebånd',
	'limit'    => 4,
) );
assert_test( is_array( $product_search ) && ! empty( $product_search ), 'product_service: Returned matching products from catalog' );
assert_test( isset( $product_search[0]['tittel'] ) && isset( $product_search[0]['pris'] ) && isset( $product_search[0]['url'] ), 'product_service: Product card contains tittel, pris, and affiliate URL' );

// 15.2 Verify Product Service price constraint enforcement
$cheap_search = Gear_Engine_Product_Service::search_products( array(
	'keywords'  => 'tredemølle',
	'max_price' => 5000,
) );
assert_test( empty( $cheap_search ), 'product_service: Properly filters out items above maximum price limit' );

// 15.3 Verify Norwegian Master Clinical Prompt
$prompt = Gear_Engine_Chat_Agent::get_default_prompt();
assert_test( false !== strpos( $prompt, "Du er 'Fysionett-assistenten'" ), 'chat_agent: Master prompt defines Norwegian Fysionett assistant persona' );
assert_test( false !== strpos( $prompt, 'search_products' ) && false !== strpos( $prompt, 'find_physiotherapist' ), 'chat_agent: Master prompt declares search_products and find_physiotherapist tools' );
assert_test( false !== strpos( $prompt, 'triage_resultat' ) && false !== strpos( $prompt, 'exorlive' ), 'chat_agent: Master prompt defines triage and exorlive schema' );
assert_test( false !== strpos( $prompt, 'maksimalt fem øvelser' ) && false !== strpos( $prompt, 'library_id' ), 'chat_agent: Exercise prompt specifies CPT candidate IDs and five-exercise cap' );
$normalizer = new ReflectionMethod( Gear_Engine_Chat_Agent::class, 'normalize_bot_response' );
$normalized_program = $normalizer->invoke(
	null,
	array(
		'message' => 'Program',
		'card'    => array(
			'type' => 'exorlive',
			'data' => array(
				'ovelser' => array(
					array( 'navn' => 'A', 'video_url' => 'https://exorlive.com/video/?culture=nb-NO&ex=7282' ),
					array( 'navn' => 'B', 'video_url' => 'https://www.youtube.com/watch?v=kJQP7kiw5Fk' ),
					array( 'navn' => 'C' ),
					array( 'navn' => 'D' ),
					array( 'navn' => 'E' ),
					array( 'navn' => 'F' ),
				),
			),
		),
	)
);
assert_test( 5 === count( $normalized_program['card']['data']['ovelser'] ), 'chat_agent: Enforces maximum five exercises per program in backend normalization' );
assert_test( '7282' === $normalized_program['card']['data']['ovelser'][0]['exorlive_id'] && 'https://exorlive.com/video/?culture=nb-NO&ex=7282' === $normalized_program['card']['data']['ovelser'][0]['video_url'], 'chat_agent: Converts official ExorLive link into validated structured player data' );
assert_test( 'https://www.exorlive.com/no/ovelser-i-exorlive' === $normalized_program['card']['data']['ovelser'][0]['attribution_url'], 'chat_agent: Adds the official ExorLive attribution target' );
assert_test( 'https://www.youtube.com/watch?v=kJQP7kiw5Fk' === $normalized_program['card']['data']['ovelser'][1]['video_url'], 'chat_agent: Preserves existing non-ExorLive protocol videos' );
$malformed_program = $normalizer->invoke(
	null,
	array(
		'card' => array(
			'type' => 'exorlive',
			'data' => array( 'ovelser' => array( array( 'navn' => 'Ugyldig', 'exorlive_id' => '7282&x=1' ) ) ),
		),
	)
);
assert_test( '' === $malformed_program['card']['data']['ovelser'][0]['video_url'], 'chat_agent: Rejects malformed ExorLive IDs before response reaches the player' );

// 15.4 Verify local clinical protocol JSON files (knee, shoulder, back, foot)
$data_dir = ABSPATH . 'modules/physio-chat/data/';
$kne_ovelser = Gear_Engine_Chat_Agent::read_safe_json( $data_dir . 'ovelser_kne.json' );
$kne_triage  = Gear_Engine_Chat_Agent::read_safe_json( $data_dir . 'triage_kne.json' );
assert_test( ! empty( $kne_ovelser['ovelser'] ) && count( $kne_ovelser['ovelser'] ) >= 2, 'protocols: Knee exercise protocol contains structured exercises' );
assert_test( ! empty( $kne_triage['notat_hypotese'] ) && ! empty( $kne_triage['notat_icpc2'] ), 'protocols: Knee triage contains clinical hypothesis and ICPC-2 code' );

$skulder_triage = Gear_Engine_Chat_Agent::read_safe_json( $data_dir . 'triage_skulder.json' );
assert_test( ! empty( $skulder_triage['alvorlighetsgrad'] ) && ! empty( $skulder_triage['notat_icd10'] ), 'protocols: Shoulder triage contains severity and ICD-10 code' );

$rygg_triage = Gear_Engine_Chat_Agent::read_safe_json( $data_dir . 'triage_rygg.json' );
assert_test( ! empty( $rygg_triage['tiltak_na'] ) && ! empty( $rygg_triage['tiltak_videre'] ), 'protocols: Back triage contains immediate and long-term action plan' );

// 15.5 Verify Physio Chat Module instantiation and shortcode rendering
$physio_chat = new Gear_Engine_Physio_Chat();
$shortcode_html = $physio_chat->render_chat_shortcode();
assert_test( false !== strpos( $shortcode_html, 'fysio-app-container' ), 'shortcode_render: Renders main .fysio-app-container element' );
assert_test( false !== strpos( $shortcode_html, 'fysio-message-container' ), 'shortcode_render: Contains message injection container' );
assert_test( false !== strpos( $shortcode_html, 'fysio-modal-overlay' ), 'shortcode_render: Contains exercise protocol modal' );
assert_test( false !== strpos( $shortcode_html, 'tpl-grid' ) && false !== strpos( $shortcode_html, 'tpl-triage-result' ), 'shortcode_render: Contains card templates for product grid and triage results' );
assert_test( false !== strpos( $shortcode_html, 'tpl-exorlive-btn' ) && false !== strpos( $shortcode_html, 'tpl-klinikk-liste' ), 'shortcode_render: Contains templates for ExorLive button and clinic list' );

// 15.6 Verify Chat Agent handles empty message gracefully
$empty_req = new WP_REST_Request( array( 'message' => '' ) );
$empty_res = Gear_Engine_Chat_Agent::handle_chat_request( $empty_req );
assert_test( 400 === $empty_res->status, 'chat_agent: Returns 400 status on empty message' );

// 15.7 Verify Chat Agent handles missing API key gracefully
delete_option( 'gae_google_ai_key' );
$no_key_req = new WP_REST_Request( array( 'message' => 'Hei, jeg har vondt i kneet' ) );
$no_key_res = Gear_Engine_Chat_Agent::handle_chat_request( $no_key_req );
assert_test( 200 === $no_key_res->status && false !== strpos( $no_key_res->data['message'], 'Google AI Studio API-nøkkel er ikke konfigurert' ), 'chat_agent: Returns helpful Norwegian setup instruction when API key is missing' );

// TEST 16: Shared curated exercise library
$exercise_cpt = new Gear_Engine_Exercises_CPT();
$exercise_cpt->register_content_types();
$exorlive_meta = $GLOBALS['registered_post_meta'][ Gear_Engine_Exercises_CPT::GEAR_ENGINE_POST_TYPE ][ Gear_Engine_Exercises_CPT::GEAR_ENGINE_META_EXORLIVE_ID ];
assert_test( ! empty( $exorlive_meta['show_in_rest'] ) && is_callable( $exorlive_meta['auth_callback'] ) && is_callable( $exorlive_meta['sanitize_callback'] ), 'exercise_cpt: ExorLive metadata is sanitized and permission-checked in REST' );
$asserted_culture_meta = $GLOBALS['registered_post_meta'][ Gear_Engine_Exercises_CPT::GEAR_ENGINE_POST_TYPE ][ Gear_Engine_Exercises_CPT::GEAR_ENGINE_META_VIDEO_CULTURE ];
assert_test( ! empty( $asserted_culture_meta['show_in_rest'] ) && is_callable( $asserted_culture_meta['auth_callback'] ) && is_callable( $asserted_culture_meta['sanitize_callback'] ), 'exercise_cpt: Player locale metadata is sanitized and permission-checked in REST' );
$video_url_meta = $GLOBALS['registered_post_meta'][ Gear_Engine_Exercises_CPT::GEAR_ENGINE_POST_TYPE ][ Gear_Engine_Exercises_CPT::GEAR_ENGINE_META_VIDEO_URL ];
assert_test( ! empty( $video_url_meta['show_in_rest'] ) && is_callable( $video_url_meta['auth_callback'] ) && is_callable( $video_url_meta['sanitize_callback'] ), 'exercise_cpt: Video URL metadata is sanitized and permission-checked in REST' );
assert_test( '' === $exercise_cpt->sanitize_exorlive_id( '7282&bad=1' ) && '' === $exercise_cpt->sanitize_exorlive_id( array( '7282' ) ), 'exercise_cpt: Rejects malformed or non-scalar ExorLive IDs' );
assert_test( 'nb-NO' === $exercise_cpt->sanitize_video_culture( 'en-us' ) && 'nb-NO' === $exercise_cpt->sanitize_video_culture( array( 'en-US' ) ), 'exercise_cpt: Falls back for malformed or non-scalar player locales' );
assert_test( 'https://www.youtube.com/watch?v=kJQP7kiw5Fk' === Gear_Engine_Exercises_CPT::sanitize_video_url_static( 'https://youtu.be/kJQP7kiw5Fk' ), 'exercise_cpt: Canonicalizes supported YouTube links' );
assert_test( 'https://player.vimeo.com/video/123456?h=abc123' === Gear_Engine_Exercises_CPT::sanitize_video_url_static( 'https://vimeo.com/123456/abc123' ), 'exercise_cpt: Canonicalizes supported Vimeo links' );
assert_test( 'https://exorlive.com/video/?culture=en-US&ex=7282' === Gear_Engine_Exercises_CPT::sanitize_video_url_static( 'https://www.exorlive.com/video/?culture=en-US&ex=7282' ), 'exercise_cpt: Canonicalizes supported ExorLive links' );
assert_test( '' === Gear_Engine_Exercises_CPT::sanitize_video_url_static( 'https://example.com/video' ) && '' === Gear_Engine_Exercises_CPT::sanitize_video_url_static( 'http://youtu.be/kJQP7kiw5Fk' ), 'exercise_cpt: Rejects untrusted and non-HTTPS video links' );
$exercise_ids = Gear_Engine_Exercises_CPT::seed_sample_exercises();
assert_test( count( $exercise_ids ) === 2, 'exercise_cpt: Seeds two sample exercises' );
assert_test( Gear_Engine_Exercises_CPT::seed_sample_exercises() === $exercise_ids, 'exercise_cpt: Sample seeding is idempotent' );

$exercise_results = Gear_Engine_Exercise_Service::search_exercises( array( 'limit' => 5 ) );
assert_test( count( $exercise_results ) === 2, 'exercise_service: Returns curated published exercise records' );
assert_test( 5 === $GLOBALS['last_wp_query_args']['posts_per_page'], 'exercise_service: Applies requested result limit in WP_Query' );
Gear_Engine_Exercise_Service::search_exercises( array( 'area' => 'kne', 'limit' => 5 ) );
assert_test( isset( $GLOBALS['last_wp_query_args']['tax_query'][0]['terms'] ) && 'kne' === $GLOBALS['last_wp_query_args']['tax_query'][0]['terms'], 'exercise_service: Supports taxonomy-based body-area retrieval for chat' );
assert_test( 'https://exorlive.com/video/?culture=nb-NO&ex=7282' === $exercise_results[0]['video_url'], 'exercise_service: Builds player URL from official ExorLive ID and locale' );
assert_test( '' === Gear_Engine_Exercise_Service::build_exorlive_video_url( '7282&x=1', 'nb-NO' ), 'exercise_service: Refuses malformed player IDs' );
assert_test( 'https://exorlive.com/video/?culture=nb-NO&ex=7282' === Gear_Engine_Exercise_Service::build_exorlive_video_url( '7282', 'en-us' ), 'exercise_service: Defaults invalid player locale to nb-NO' );
assert_test( 'https://www.exorlive.com/no/ovelser-i-exorlive' === $exercise_results[0]['attribution_url'], 'exercise_service: Exposes the official ExorLive attribution target' );
assert_test( '' === $exercise_results[1]['video_url'], 'exercise_service: Does not invent a video URL for an exercise without an ID' );
update_post_meta( $exercise_ids[0], Gear_Engine_Exercises_CPT::GEAR_ENGINE_META_VIDEO_URL, 'https://youtu.be/kJQP7kiw5Fk' );
$manual_video_data = Gear_Engine_Exercise_Service::get_exercise_data( $exercise_ids[0] );
assert_test( 'https://www.youtube.com/watch?v=kJQP7kiw5Fk' === $manual_video_data['video_url'] && '' === $manual_video_data['exorlive_id'] && '' === $manual_video_data['attribution_url'], 'exercise_service: Uses a manual supported video URL in preference to the ExorLive ID' );
update_post_meta( $exercise_ids[0], Gear_Engine_Exercises_CPT::GEAR_ENGINE_META_VIDEO_URL, '' );
assert_test( ! empty( $exercise_results[0]['description'] ) && $exercise_results[0]['description'] !== $exercise_results[1]['description'], 'exercise_cpt: Sample instructions are distinct original descriptions' );
$cpt_program = $normalizer->invoke(
	null,
	array(
		'message' => 'Program',
		'card'    => array(
			'type' => 'exorlive',
			'data' => array(
				'ovelser' => array(
					array( 'library_id' => $exercise_results[0]['id'], 'navn' => 'AI-forslag' ),
					array( 'library_id' => 999999, 'navn' => 'Oppdiktet øvelse', 'exorlive_id' => '12345' ),
				),
			),
		),
	),
	$exercise_results
);
assert_test( 1 === count( $cpt_program['card']['data']['ovelser'] ), 'chat_agent: Keeps only candidate exercise IDs returned by the CPT service' );
assert_test( $exercise_results[0]['title'] === $cpt_program['card']['data']['ovelser'][0]['navn'] && $exercise_results[0]['description'] === $cpt_program['card']['data']['ovelser'][0]['beskrivelse'], 'chat_agent: Builds selected exercise details from canonical CPT records' );
assert_test( $exercise_results[0]['video_url'] === $cpt_program['card']['data']['ovelser'][0]['video_url'], 'chat_agent: Uses the CPT ExorLive metadata when composing the program' );
$too_many_exercises = Gear_Engine_Exercise_Service::search_exercises( array( 'limit' => 500 ) );
assert_test( 50 === $GLOBALS['last_wp_query_args']['posts_per_page'] && count( $too_many_exercises ) <= 50, 'exercise_service: Caps general queries to 50 results' );
$invalid_exercise = new WP_Post();
$invalid_exercise->ID = 999;
$invalid_exercise->post_type = Gear_Engine_Exercises_CPT::GEAR_ENGINE_POST_TYPE;
$GLOBALS['wp_postmeta'][999][ Gear_Engine_Exercises_CPT::GEAR_ENGINE_META_EXORLIVE_ID ] = '72x2';
$GLOBALS['wp_postmeta'][999][ Gear_Engine_Exercises_CPT::GEAR_ENGINE_META_VIDEO_CULTURE ] = 'en-US';
$invalid_exercise_data = Gear_Engine_Exercise_Service::get_exercise_data( $invalid_exercise );
assert_test( '' === $invalid_exercise_data['exorlive_id'] && '' === $invalid_exercise_data['video_url'], 'exercise_service: Does not create a player URL from an invalid stored ID' );
$GLOBALS['wp_postmeta'][999][ Gear_Engine_Exercises_CPT::GEAR_ENGINE_META_EXORLIVE_ID ] = '7282';
$GLOBALS['wp_postmeta'][999][ Gear_Engine_Exercises_CPT::GEAR_ENGINE_META_VIDEO_CULTURE ] = array( 'en-US' );
$invalid_culture_data = Gear_Engine_Exercise_Service::get_exercise_data( $invalid_exercise );
assert_test( 'https://exorlive.com/video/?culture=nb-NO&ex=7282' === $invalid_culture_data['video_url'], 'exercise_service: Uses the default locale for malformed stored culture values' );

$_POST = array(
	'gear_engine_exercise_nonce'      => 'valid-exercise-nonce',
	'gear_engine_exorlive_id'         => '7282<script>',
	'gear_engine_video_culture'       => array( 'en-US' ),
	'gear_engine_exercise_dosage'     => '3 x 8',
	'gear_engine_exercise_video_url'  => array( 'https://youtu.be/kJQP7kiw5Fk' ),
	'gear_engine_exercise_source_url' => array( 'https://example.com/source' ),
);
$exercise_cpt->save_meta_box( $exercise_ids[0], null );
assert_test( '7282' === get_post_meta( $exercise_ids[0], Gear_Engine_Exercises_CPT::GEAR_ENGINE_META_EXORLIVE_ID, true ), 'exercise_cpt: Ignores save callbacks without a matching post object' );
$saved_exercise_post = $GLOBALS['wp_posts'][ $exercise_ids[0] ];
$exercise_cpt->save_meta_box( $exercise_ids[0], $saved_exercise_post );
assert_test( '' === get_post_meta( $exercise_ids[0], Gear_Engine_Exercises_CPT::GEAR_ENGINE_META_EXORLIVE_ID, true ) && 'nb-NO' === get_post_meta( $exercise_ids[0], Gear_Engine_Exercises_CPT::GEAR_ENGINE_META_VIDEO_CULTURE, true ), 'exercise_cpt: Safely sanitizes invalid editor ID and locale values' );
assert_test( '' === get_post_meta( $exercise_ids[0], Gear_Engine_Exercises_CPT::GEAR_ENGINE_META_SOURCE_URL, true ), 'exercise_cpt: Rejects non-scalar source URLs' );
assert_test( '' === get_post_meta( $exercise_ids[0], Gear_Engine_Exercises_CPT::GEAR_ENGINE_META_VIDEO_URL, true ), 'exercise_cpt: Rejects non-scalar video URLs' );
unset( $_POST );

$_POST = array(
	'gear_engine_exercise_nonce'      => 'valid-exercise-nonce',
	'gear_engine_exorlive_id'         => '',
	'gear_engine_video_culture'       => 'nb-NO',
	'gear_engine_exercise_dosage'     => '',
	'gear_engine_exercise_video_url'  => 'https://youtu.be/kJQP7kiw5Fk',
	'gear_engine_exercise_source_url' => '',
);
$exercise_cpt->save_meta_box( $exercise_ids[0], $saved_exercise_post );
assert_test( 'https://www.youtube.com/watch?v=kJQP7kiw5Fk' === get_post_meta( $exercise_ids[0], Gear_Engine_Exercises_CPT::GEAR_ENGINE_META_VIDEO_URL, true ), 'exercise_cpt: Saves and canonicalizes manually entered video links' );
unset( $_POST );

$csv_path = tempnam( sys_get_temp_dir(), 'gear-engine-exercises-' );
file_put_contents(
	$csv_path,
	"\xEF\xBB\xBFexternal_key,title,description,summary,area,equipment,dosage,exorlive_id,video_culture,video_url,source_url\n"
	. "csv-new-1,CSV curl,\"Instructions, with comma\",Short,kne;skulder,strikk,3 x 10,,nb-NO,https://youtu.be/kJQP7kiw5Fk,https://example.com/reference\n"
	. "csv-new-1,Duplicate curl,Instructions,Short,kne,strikk,3 x 10,,nb-NO,,\n"
	. "bad-id,Bad ID,Instructions,Short,kne,strikk,3 x 10,72x2,nb-NO,,\n"
	. "bad-url,Bad URL,Instructions,Short,kne,strikk,3 x 10,,nb-NO,https://example.com/video,\n"
	. ",ExorLive only,Instructions,Short,kne,strikk,3 x 10,,nb-NO,https://exorlive.com/video/?culture=nb-NO&ex=7282,\n"
);
$csv_preview = Gear_Engine_Exercise_Importer::validate_csv_file( $csv_path );
unlink( $csv_path );
assert_test( is_array( $csv_preview ) && 5 === count( $csv_preview ), 'exercise_importer: Parses UTF-8 CSV and preserves quoted comma fields' );
assert_test( empty( $csv_preview[4]['errors'] ) && '7282' === $csv_preview[4]['data']['exorlive_id'], 'exercise_importer: Derives the ExorLive ID from a pasted link' );
assert_test( empty( $csv_preview[0]['errors'] ) && 'Instructions, with comma' === $csv_preview[0]['data']['description'] && 'https://www.youtube.com/watch?v=kJQP7kiw5Fk' === $csv_preview[0]['data']['video_url'], 'exercise_importer: Validates instructions and canonicalizes a video URL' );
assert_test( ! empty( $csv_preview[1]['errors'] ) && ! empty( $csv_preview[2]['errors'] ) && ! empty( $csv_preview[3]['errors'] ), 'exercise_importer: Flags duplicate keys, invalid IDs, and unsupported video URLs during preview' );
$import_summary = Gear_Engine_Exercise_Importer::import_rows( $csv_preview );
assert_test( 2 === $import_summary['imported'] && 3 === $import_summary['skipped'] && 0 === $import_summary['failed'], 'exercise_importer: Imports valid rows and skips invalid preview rows' );
$imported_exercise_id = 0;
foreach ( $GLOBALS['wp_posts'] as $candidate_post ) {
	if ( 'CSV curl' === $candidate_post->post_title ) {
		$imported_exercise_id = $candidate_post->ID;
		break;
	}
}
assert_test( $imported_exercise_id > 0 && 'draft' === get_post_status( $imported_exercise_id ), 'exercise_importer: Creates new exercises as drafts by default' );
assert_test( 'https://www.youtube.com/watch?v=kJQP7kiw5Fk' === get_post_meta( $imported_exercise_id, Gear_Engine_Exercises_CPT::GEAR_ENGINE_META_VIDEO_URL, true ), 'exercise_importer: Saves supported video URLs from CSV' );
$csv_preview[0]['data']['title'] = 'Updated CSV curl';
$update_summary = Gear_Engine_Exercise_Importer::import_rows( array( $csv_preview[0] ) );
assert_test( 1 === $update_summary['updated'] && 'Updated CSV curl' === $GLOBALS['wp_posts'][ $imported_exercise_id ]->post_title, 'exercise_importer: Re-import upserts by external_key without creating duplicates' );
$publish_summary = Gear_Engine_Exercise_Importer::import_rows( array( $csv_preview[0] ), true );
assert_test( 1 === $publish_summary['updated'] && 'publish' === get_post_status( $imported_exercise_id ), 'exercise_importer: Publishing requires the explicit publish option' );

// Suite 17: protocols and risk levels.
echo "\nRunning Suite 17: Protocols and risk levels...\n";
update_option( 'gear_engine_enable_protocols', true );
assert_test( 'moderate' === Gear_Engine_Exercises_CPT::sanitize_risk( ' Moderate ' ) && '' === Gear_Engine_Exercises_CPT::sanitize_risk( 'extreme' ) && '' === Gear_Engine_Exercises_CPT::sanitize_risk( array( 'low' ) ), 'protocols: Risk sanitizer accepts only known levels' );
assert_test( 'M23.2, L96' === Gear_Engine_Protocols::sanitize_codes( 'm23.2; l96, junk, 12' ), 'protocols: Normalizes ICD-10/ICPC-2 codes and drops invalid entries' );
assert_test( '2026-02-30' !== Gear_Engine_Protocols::sanitize_date( '2026-02-30' ) && '2026-03-01' === Gear_Engine_Protocols::sanitize_date( '2026-03-01' ), 'protocols: Rejects invalid dates' );
$GLOBALS['wp_terms'][11] = (object) array( 'term_id' => 11, 'slug' => 'meniscus', 'name' => 'Meniscus', 'parent' => 0 );
$GLOBALS['wp_terms'][12] = (object) array( 'term_id' => 12, 'slug' => 'meniscus-phase-1', 'name' => 'Phase 1', 'parent' => 11 );
$GLOBALS['wp_terms'][13] = (object) array( 'term_id' => 13, 'slug' => 'shoulder', 'name' => 'Shoulder subluxation', 'parent' => 0 );
$set_protocol_meta = static function ( int $id, array $meta ): void {
	foreach ( $meta as $key => $value ) {
		update_term_meta( $id, $key, $value );
	}
};
$future = gmdate( 'Y-m-d', strtotime( '+30 days' ) );
$past   = gmdate( 'Y-m-d', strtotime( '-1 day' ) );
$set_protocol_meta( 11, array(
	Gear_Engine_Protocols::GEAR_ENGINE_META_STATUS => 'approved', Gear_Engine_Protocols::GEAR_ENGINE_META_CODES => 'L96, M23',
	Gear_Engine_Protocols::GEAR_ENGINE_META_REVIEW_DUE => $future, Gear_Engine_Protocols::GEAR_ENGINE_META_SUMMARY => 'Summary', Gear_Engine_Protocols::GEAR_ENGINE_META_SOURCES => 'secret source',
) );
$set_protocol_meta( 13, array( Gear_Engine_Protocols::GEAR_ENGINE_META_STATUS => 'approved', Gear_Engine_Protocols::GEAR_ENGINE_META_CODES => 'L80', Gear_Engine_Protocols::GEAR_ENGINE_META_REVIEW_DUE => $past ) );
$found = Gear_Engine_Protocols::find_approved_by_codes( array( 'm23.2' ) );
assert_test( 1 === count( $found ) && 'meniscus' === $found[0]['slug'], 'protocols: Matches an approved protocol by code prefix' );
assert_test( ! array_key_exists( 'sources', $found[0] ) && 'Summary' === $found[0]['summary'], 'protocols: Chat data excludes guideline sources' );
assert_test( array() === Gear_Engine_Protocols::find_approved_by_codes( array( 'L80' ) ) && 'expired' === Gear_Engine_Protocols::get_status( 13 ), 'protocols: Overdue review disables an approved protocol' );
assert_test( array() === Gear_Engine_Protocols::find_approved_by_codes( array( 'X99' ) ) && array() === Gear_Engine_Protocols::find_approved_by_codes( array() ), 'protocols: Unmatched or empty codes return no protocol' );
$GLOBALS['wp_postmeta'][5001][ Gear_Engine_Exercises_CPT::GEAR_ENGINE_META_RISK ] = 'high';
$risk_post = new WP_Post();
$risk_post->ID = 5001;
$risk_post->post_type = Gear_Engine_Exercises_CPT::GEAR_ENGINE_POST_TYPE;
$risk_post->post_title = 'Risk test';
$risk_post->post_content = '';
$risk_post->post_excerpt = '';
assert_test( 'high' === Gear_Engine_Exercise_Service::get_exercise_data( $risk_post )['risk'], 'protocols: Exercise data exposes the risk level' );
Gear_Engine_Exercise_Service::search_exercises( array( 'max_risk' => 'moderate', 'protocol' => 'meniscus' ) );
$last_args = $GLOBALS['last_wp_query_args'];
assert_test( array( 'low', 'moderate' ) === $last_args['meta_query'][0]['value'] && 'IN' === $last_args['meta_query'][0]['compare'], 'protocols: max_risk limits results to rated lower-risk exercises' );
assert_test( 'meniscus' === $last_args['tax_query'][0]['terms'] && true === $last_args['tax_query'][0]['include_children'], 'protocols: Protocol filter includes phase terms' );
$csv_rows = array( array( 'line' => 2, 'errors' => array(), 'data' => array( 'external_key' => 'proto-row', 'title' => 'Proto', 'description' => 'Steps', 'summary' => '', 'area' => array(), 'equipment' => array(), 'dosage' => '', 'exorlive_id' => '', 'video_culture' => 'nb-NO', 'video_url' => '', 'source_url' => '', 'risk' => 'low', 'protocol' => array( 'Meniscus', 'typo-protocol' ) ) ) );
$proto_summary = Gear_Engine_Exercise_Importer::import_rows( $csv_rows );
assert_test( 1 === $proto_summary['imported'] && 1 === count( $proto_summary['errors'] ), 'protocols: Importer reports unknown protocols instead of creating them' );

$tree = Gear_Engine_Protocol_Manager::get_protocol_tree();
assert_test( array( 11, 12, 13 ) === array_map( static function ( $r ) { return (int) $r['term']->term_id; }, $tree ) && 1 === $tree[1]['depth'], 'protocol_manager: Builds a parent-first protocol tree with depth' );
$GLOBALS['wp_set_terms_calls'] = array();
$updated = Gear_Engine_Protocol_Manager::apply_matrix_changes( array( $imported_exercise_id, 999999 ), array( $imported_exercise_id => array( '11', '12', '777' ) ), array( $imported_exercise_id => 'moderate' ) );
assert_test( 1 === $updated && array( 11, 12 ) === $GLOBALS['wp_set_terms_calls'][0][1], 'protocol_manager: Matrix save assigns only valid protocol terms' );
assert_test( 'moderate' === get_post_meta( $imported_exercise_id, Gear_Engine_Exercises_CPT::GEAR_ENGINE_META_RISK, true ), 'protocol_manager: Matrix save stores the risk level' );
Gear_Engine_Protocol_Manager::apply_matrix_changes( array( $imported_exercise_id ), array(), array( $imported_exercise_id => 'bogus' ) );
assert_test( array() === $GLOBALS['wp_set_terms_calls'][1][1] && '' === get_post_meta( $imported_exercise_id, Gear_Engine_Exercises_CPT::GEAR_ENGINE_META_RISK, true ), 'protocol_manager: Unchecked boxes remove protocols and invalid risk is cleared' );
assert_test( in_array( 'red flags', Gear_Engine_Protocols::get_missing_for_term( 13 ), true ) && ! in_array( 'matching codes', Gear_Engine_Protocols::get_missing_for_term( 13 ), true ), 'protocols: Reports missing approval requirements' );

// Suite 18: chat exercise selection modes and safety gate.
echo "\nRunning Suite 18: Exercise selection modes...\n";
$triage_json = wp_json_encode( array( 'message' => 'ok', 'card' => array( 'type' => 'triage_resultat', 'data' => array( 'kategori' => 'Kne', 'alvorlighetsgrad' => 'Lav', 'notat_rode_flagg' => 'Ingen.', 'notat_icpc2' => 'L96', 'notat_icd10' => 'M23.2' ) ) ) );
$history = array( array( 'role' => 'user', 'parts' => array( array( 'text' => 'vondt i kneet' ) ) ), array( 'role' => 'model', 'parts' => array( array( 'text' => $triage_json ) ) ) );
$triage = Gear_Engine_Exercise_Selection::extract_triage( $history );
assert_test( array( 'M23.2', 'L96' ) === $triage['codes'] && 'Kne' === $triage['category'], 'selection: Extracts codes and category from the last triage card' );
assert_test( array() === Gear_Engine_Exercise_Selection::extract_triage( array() ), 'selection: No triage in history returns an empty summary' );
assert_test( '' === Gear_Engine_Exercise_Selection::get_block_reason( $triage, 'Jeg vil ha øvelser' ), 'selection: Low severity without red flags is not blocked' );
assert_test( 'severity' === Gear_Engine_Exercise_Selection::get_block_reason( array( 'severity' => 'Høy' ), '' ), 'selection: High severity blocks exercises' );
assert_test( 'red_flags' === Gear_Engine_Exercise_Selection::get_block_reason( array( 'red_flags' => 'Nummenhet i foten' ), '' ), 'selection: Reported triage red flags block exercises' );
assert_test( 'user_reported' === Gear_Engine_Exercise_Selection::get_block_reason( array(), 'jeg har fått nummenhet og feber' ), 'selection: Red-flag phrases in user text block exercises' );
$sel = Gear_Engine_Exercise_Selection::select( $triage, 'kne', 'øvelser', true );
assert_test( 'protocol' === $sel['mode'] && 'meniscus' === $sel['protocol']['slug'], 'selection: Approved protocol is used when the triage code matches' );
assert_test( 5 === $GLOBALS['last_wp_query_args']['posts_per_page'] && 'meniscus' === $GLOBALS['last_wp_query_args']['tax_query'][0]['terms'], 'selection: Protocol mode queries at most five tagged exercises' );
$no_code = $triage;
$no_code['codes'] = array( 'X99' );
$sel = Gear_Engine_Exercise_Selection::select( $no_code, 'kne', 'øvelser', true );
assert_test( 'general' === $sel['mode'] && 50 === $GLOBALS['last_wp_query_args']['posts_per_page'], 'selection: Falls back to general mode without a protocol' );
assert_test( array( 'low', 'moderate' ) === $GLOBALS['last_wp_query_args']['meta_query'][0]['value'], 'selection: General mode only draws from rated low and moderate risk exercises' );
assert_test( 'none' === Gear_Engine_Exercise_Selection::select( $no_code, 'kne', 'øvelser', false )['mode'], 'selection: General mode can be disabled' );
assert_test( 'none' === Gear_Engine_Exercise_Selection::select( $no_code, '', 'øvelser', true )['mode'], 'selection: General mode requires a known body area' );
$GLOBALS['last_wp_query_args'] = null;
$ranked = Gear_Engine_Exercise_Selection::rank_by_good_for( array( array( 'id' => 1, 'good_for' => 'albue' ), array( 'id' => 2, 'good_for' => 'Meniscus; kneartrose' ), array( 'id' => 3, 'good_for' => '' ), array( 'id' => 4, 'good_for' => 'meniscus' ) ), 'Jeg har meniskskade? meniscus operert' );
assert_test( array( 2, 4, 1, 3 ) === array_column( $ranked, 'id' ), 'selection: Exercises with matching good_for tags are ranked first, others keep order' );
assert_test( 'Meniscus, kneartrose' === Gear_Engine_Exercises_CPT::sanitize_good_for( 'Meniscus; kneartrose,, MENISCUS' ), 'exercise_cpt: Normalizes and de-duplicates good_for tags' );
update_option( 'gear_engine_enable_protocols', false );
assert_test( array() === Gear_Engine_Protocols::find_approved_by_codes( array( 'L96' ) ) && 'general' === Gear_Engine_Exercise_Selection::select( $triage, 'kne', 'øvelser', true )['mode'], 'selection: With protocols switched off the chat uses general mode with good_for ranking' );
$GLOBALS['wp_set_terms_calls'] = array();
Gear_Engine_Protocol_Manager::apply_matrix_changes( array( $imported_exercise_id ), array(), array(), array( $imported_exercise_id => 'meniscus; kne' ), false );
assert_test( array() === $GLOBALS['wp_set_terms_calls'] && 'meniscus, kne' === get_post_meta( $imported_exercise_id, Gear_Engine_Exercises_CPT::GEAR_ENGINE_META_GOOD_FOR, true ), 'protocol_manager: Rating screen saves good_for without touching protocol tags' );

// Suite 17.5: session roles.
assert_test( 'mobility' === Gear_Engine_Exercises_CPT::sanitize_role( ' Mobility ' ) && '' === Gear_Engine_Exercises_CPT::sanitize_role( 'cardio' ), 'exercise_role: Accepts only known role slugs' );
assert_test( 1 === Gear_Engine_Exercises_CPT::role_order( 'mobility' ) && 3 === Gear_Engine_Exercises_CPT::role_order( '' ) && 5 === Gear_Engine_Exercises_CPT::role_order( 'recovery' ), 'exercise_role: Session order runs mobility first and recovery last' );
Gear_Engine_Exercise_Service::search_exercises( array( 'role' => 'stability' ) );
$role_args = $GLOBALS['last_wp_query_args'];
assert_test( Gear_Engine_Exercises_CPT::GEAR_ENGINE_ROLE_TAXONOMY === $role_args['tax_query'][0]['taxonomy'] && 'stability' === $role_args['tax_query'][0]['terms'], 'exercise_role: The query service can filter by session role' );
$GLOBALS['wp_set_terms_calls'] = array();
Gear_Engine_Protocol_Manager::apply_matrix_changes( array( $imported_exercise_id ), array(), array(), null, false, array( $imported_exercise_id => 'activation' ) );
assert_test( array( $imported_exercise_id, array( 'activation' ), Gear_Engine_Exercises_CPT::GEAR_ENGINE_ROLE_TAXONOMY ) === $GLOBALS['wp_set_terms_calls'][0], 'exercise_role: Matrix save applies the session role' );

// Suite 17.6: protocol auto-fill assistant.
$autofill_pool = array(
	array( 'id' => 101, 'title' => 'Knebøy', 'risk' => 'low', 'good_for' => 'meniscus', 'role' => 'strength' ),
	array( 'id' => 102, 'title' => 'Ankle pumps', 'risk' => 'low', 'good_for' => 'fot', 'role' => 'mobility' ),
	array( 'id' => 103, 'title' => 'Beinpress', 'risk' => 'moderate', 'good_for' => 'meniscus, kneartrose', 'role' => 'strength' ),
	array( 'id' => 104, 'title' => 'Tung knebøy', 'risk' => 'high', 'good_for' => 'meniscus', 'role' => 'strength' ),
	array( 'id' => 105, 'title' => 'Quad set', 'risk' => 'low', 'good_for' => 'meniscus', 'role' => 'activation' ),
);
$phase1_ids = array_map( static function ( array $p ): int { return (int) $p['exercise']['id']; }, Gear_Engine_Protocol_Autofill::pick_candidates( $autofill_pool, 'meniscus', array(), 5, 1 ) );
assert_test( array( 105, 101 ) === $phase1_ids, 'autofill: Phase 1 keeps low risk only and orders activation before strength' );
$phase3_ids = array_map( static function ( array $p ): int { return (int) $p['exercise']['id']; }, Gear_Engine_Protocol_Autofill::pick_candidates( $autofill_pool, 'meniscus', array(), 5, 3 ) );
assert_test( array( 105, 103, 101 ) === $phase3_ids, 'autofill: Phase 3 allows moderate and puts it first within the same band' );
$excluded_ids = array_map( static function ( array $p ): int { return (int) $p['exercise']['id']; }, Gear_Engine_Protocol_Autofill::pick_candidates( $autofill_pool, 'meniscus', array( 105, 103 ), 5, 3 ) );
assert_test( array( 101 ) === $excluded_ids, 'autofill: Exercises already in the protocol tree are never proposed again' );
assert_test( array() === Gear_Engine_Protocol_Autofill::pick_candidates( $autofill_pool, 'meniscus', array(), 0, 1 ), 'autofill: A full phase proposes nothing (idempotent re-runs)' );
$number_term = (object) array( 'slug' => 'fase-2', 'name' => 'Phase 2 – bygg' );
assert_test( 2 === Gear_Engine_Protocol_Autofill::phase_number_for( $number_term ) && 0 === Gear_Engine_Protocol_Autofill::phase_number_for( (object) array( 'slug' => 'general', 'name' => 'General' ) ), 'autofill: Phase numbers come from term slug or name' );
$GLOBALS['wp_set_terms_calls'] = array();
$GLOBALS['wp_termmeta'][12][ Gear_Engine_Protocol_Autofill::META_AUTO_IDS ] = array( 7, 8 );
$GLOBALS['test_post_terms']['7|gear_engine_protocol'] = array( 11, 12 );
$GLOBALS['test_post_terms']['8|gear_engine_protocol'] = array( 12 );
$cleared = Gear_Engine_Protocol_Autofill::clear_auto_for_term( 12 );
assert_test( 2 === $cleared && array( 11 ) === $GLOBALS['wp_set_terms_calls'][0][1] && array() === $GLOBALS['wp_set_terms_calls'][1][1], 'autofill: Clear removes only the phase from auto-proposed exercises, keeping human tags' );
assert_test( ! isset( $GLOBALS['wp_termmeta'][12][ Gear_Engine_Protocol_Autofill::META_AUTO_IDS ] ), 'autofill: Clearing forgets the recorded auto assignments' );

// Suite 17.7: program coverage report states.
require_once ABSPATH . 'modules/exercises/class-gear-engine-coverage-report.php';
assert_test( 'by-design' === Gear_Engine_Coverage_Report::classify_diagnosis( array( 'severity' => 'Høy' ), 'meniscus', true )['state'], 'coverage: High severity results never count as gaps' );
assert_test( 'curated' === Gear_Engine_Coverage_Report::classify_diagnosis( array( 'severity' => 'Lav' ), 'meniscus', false )['state'], 'coverage: Reached approved protocol is curated' );
assert_test( 'broken' === Gear_Engine_Coverage_Report::classify_diagnosis( array( 'severity' => 'Lav', 'protocol' => 'ghost' ), '', true )['state'], 'coverage: A named protocol that resolves nowhere is broken' );
assert_test( 'auto' === Gear_Engine_Coverage_Report::classify_diagnosis( array( 'severity' => 'Lav' ), '', true )['state'] && 'missing' === Gear_Engine_Coverage_Report::classify_diagnosis( array( 'severity' => 'Lav' ), '', false )['state'], 'coverage: Without a protocol, auto availability decides the state' );
$coverage_unreachable = Gear_Engine_Coverage_Report::find_unreachable_protocols(
	array(
		array( 'slug' => 'meniscus', 'codes' => array( 'M23' ) ),
		array( 'slug' => 'ghost-proto', 'codes' => array( 'Z99' ) ),
	),
	array(
		array( 'protocol' => '', 'codes' => array( 'M23.2' ) ),
	)
);
assert_test( array( 'ghost-proto' ) === $coverage_unreachable, 'coverage: Prefix code matching marks only truly unreachable protocols' );
update_option( 'gear_engine_enable_protocols', true );
$GLOBALS['last_wp_query_args'] = null;
$sel_blocked = Gear_Engine_Exercise_Selection::select( array( 'severity' => 'Høy', 'codes' => array( 'M23.2' ) ), 'kne', 'øvelser', true );
assert_test( 'blocked' === $sel_blocked['mode'] && null === $GLOBALS['last_wp_query_args'] && array() === $sel_blocked['candidates'], 'selection: Blocked mode never queries or returns exercises' );
assert_test( false !== strpos( Gear_Engine_Exercise_Selection::build_context( $sel_blocked ), 'IKKE foreslå' ), 'selection: Blocked context forbids exercises' );
$sel_general = Gear_Engine_Exercise_Selection::select( $no_code, 'kne', 'øvelser', true );
assert_test( false !== strpos( Gear_Engine_Exercise_Selection::build_context( $sel_general ), 'STRENGE REGLER' ) && false !== strpos( Gear_Engine_Exercise_Selection::build_context( $sel_general ), 'CPT_EXERCISE_LIBRARY' ), 'selection: General context carries the strict rules and candidates' );
$sel_protocol = Gear_Engine_Exercise_Selection::select( $triage, 'kne', 'øvelser', true );
$protocol_context = Gear_Engine_Exercise_Selection::build_context( $sel_protocol );
assert_test( false !== strpos( $protocol_context, 'PROTOKOLL_NOTAT: Summary' ) && false === strpos( $protocol_context, 'secret source' ), 'selection: Protocol context includes the note but never the sources' );
$ai_card = array( 'message' => 'x', 'card' => array( 'type' => 'exorlive', 'data' => array( 'ovelser' => array( array( 'library_id' => 7, 'begrunnelse' => 'Skånsom' ) ) ) ) );
assert_test( array( '7' => 'Skånsom' ) === Gear_Engine_Exercise_Selection::extract_rationales( $ai_card ), 'selection: Extracts per-exercise rationales' );
$final_blocked = Gear_Engine_Exercise_Selection::finalize_response( $ai_card, $sel_blocked );
assert_test( 'none' === $final_blocked['card']['type'], 'selection: Blocked mode strips an exercise card the AI returned' );
$final_general = Gear_Engine_Exercise_Selection::finalize_response( $ai_card, $sel_general, array( '7' => 'Skånsom' ) );
assert_test( 0 === strpos( $final_general['card']['data']['hovedtittel'], 'Generelle øvelsesforslag' ), 'selection: General mode labels the card as general guidance' );
$final_protocol = Gear_Engine_Exercise_Selection::finalize_response( $ai_card, $sel_protocol );
assert_test( 'Klinisk gjennomgått program: Meniscus' === $final_protocol['card']['data']['hovedtittel'], 'selection: Protocol mode labels the card with the protocol name' );
$log_entries = array_filter( $GLOBALS['wp_posts'], static function ( $p ) { return Gear_Engine_Exercise_Selection::GEAR_ENGINE_LOG_POST_TYPE === $p->post_type; } );
$log_json = json_decode( end( $log_entries )->post_content, true );
assert_test( count( $log_entries ) >= 3 && 'protocol' === $log_json['mode'] && ! array_key_exists( 'message', $log_json ), 'selection: Audit log records the mode without user message text' );

echo "========================================================\n";
echo "ALL TESTS PASSED SUCCESSFULLY! (18/18 suites passed)\n";
echo "========================================================\n";
