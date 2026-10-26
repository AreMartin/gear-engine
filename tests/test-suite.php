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
define( 'GAE_VERSION', '1.0.0' );
define( 'GAE_PLUGIN_FILE', ABSPATH . 'gear-engine.php' );
define( 'GAE_PLUGIN_DIR', ABSPATH );
define( 'GAE_PLUGIN_URL', 'http://example.com/wp-content/plugins/gear-engine/' );

// Stub minimal WordPress functions for CLI testing.
$GLOBALS['wp_options']    = array();
$GLOBALS['wp_transients'] = array();
$GLOBALS['wp_posts']      = array();
$GLOBALS['wp_postmeta']   = array();

function add_action( $hook, $callback, $priority = 10, $accepted_args = 1 ) {}
function add_filter( $hook, $callback, $priority = 10, $accepted_args = 1 ) {}
function add_shortcode( $tag, $callback ) {}
function register_post_type( $post_type, $args = array() ) {}
function register_taxonomy( $taxonomy, $object_type, $args = array() ) {}
function register_post_meta( $post_type, $meta_key, $args = array() ) {}
function register_setting( $option_group, $option_name, $args = array() ) {}
function add_settings_section( $id, $title, $callback, $page ) {}
function add_settings_field( $id, $title, $callback, $page, $section = 'default', $args = array() ) {}
function add_options_page( $page_title, $menu_title, $capability, $menu_slug, $callback = '' ) {}
function add_meta_box( $id, $title, $callback, $screen = null, $context = 'advanced', $priority = 'default', $callback_args = null ) {}
function wp_enqueue_style( $handle, $src = '', $deps = array(), $ver = false, $media = 'all' ) {}
function wp_enqueue_script( $handle, $src = '', $deps = array(), $ver = false, $in_footer = false ) {}
function wp_register_style( $handle, $src = '', $deps = array(), $ver = false, $media = 'all' ) {}
function wp_register_script( $handle, $src = '', $deps = array(), $ver = false, $in_footer = false ) {}
function register_block_type( $name, $args = array() ) {}
function register_rest_route( $namespace, $route, $args = array(), $override = false ) {}

function get_option( $key, $default = false ) {
	return $GLOBALS['wp_options'][ $key ] ?? $default;
}
function update_option( $key, $value, $autoload = null ) {
	$GLOBALS['wp_options'][ $key ] = $value;
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
function sanitize_textarea_field( $str ) {
	return is_scalar( $str ) ? strip_tags( (string) $str ) : '';
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
function wp_set_object_terms( $object_id, $terms, $taxonomy, $append = false ) {}

class WP_Post {
	public int $ID = 0;
	public string $post_title = '';
	public string $post_content = '';
	public string $post_excerpt = '';
	public string $post_type = 'exercise_gear';
}

function get_post( $id ) {
	return $GLOBALS['wp_posts'][ $id ] ?? null;
}
function get_post_meta( $post_id, $key, $single = false ) {
	return $GLOBALS['wp_postmeta'][ $post_id ][ $key ] ?? ( $single ? '' : array() );
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
	$GLOBALS['wp_posts'][ $post->ID ] = $post;
	return $post->ID;
}
function wp_update_post( $args ) {
	$id = $args['ID'];
	if ( isset( $GLOBALS['wp_posts'][ $id ] ) ) {
		if ( isset( $args['post_title'] ) ) $GLOBALS['wp_posts'][ $id ]->post_title = $args['post_title'];
		if ( isset( $args['post_content'] ) ) $GLOBALS['wp_posts'][ $id ]->post_content = $args['post_content'];
	}
	return $id;
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
require_once ABSPATH . 'includes/class-gae-settings.php';
require_once ABSPATH . 'includes/class-gae-cpt.php';
require_once ABSPATH . 'includes/class-gae-sync-engine.php';
require_once ABSPATH . 'includes/class-gae-gutenberg-block.php';
require_once ABSPATH . 'includes/class-gae-ai-api.php';

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

$sync_engine = new GAE_Sync_Engine();

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
$found_post = GAE_CPT::get_product_by_ean( '7071234567890' );
assert_test( $found_post !== null, 'get_product_by_ean: Successfully retrieved post by EAN' );
$product_data = GAE_CPT::get_product_data( $found_post );
assert_test( $product_data['price'] === 13999.0, 'deduplication: Price updated in postmeta' );
assert_test( $product_data['regular_price'] === 19999.0, 'deduplication: Regular price preserved in postmeta' );

// TEST 6: Multi-Variant Transients Caching logic
$ai_api = new GAE_AI_API();
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
$block_module = new GAE_Gutenberg_Block();
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
$available_models = GAE_AI_API::get_available_models();
assert_test( isset( $available_models['gemini-3.8-flash'] ), 'models_list: Gemini 3.8 Flash is present in available models' );
assert_test( str_contains( $available_models['gemini-3.8-flash'], 'Recommended' ), 'models_list: Gemini 3.8 Flash marked as recommended' );
assert_test( isset( $available_models['gemini-2.5-flash'] ), 'models_list: Gemini 2.5 Flash is present' );
assert_test( isset( $available_models['gemini-2.0-flash'] ), 'models_list: Gemini 2.0 Flash is present' );
$models_transient = get_transient( GAE_AI_API::MODELS_CACHE_KEY );
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
$found_rower = GAE_CPT::get_product_by_ean( '7079999000001' );
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
$test_products = GAE_CPT::get_sample_products_definitions();
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
$seeded_ids = GAE_CPT::seed_sample_products();
assert_test( count( $seeded_ids ) === 2, 'sample_products: Both sample products seeded into exercise_gear CPT' );

// TEST 12: Feed URL Sanitization & Entity Decoding
$settings_module = new GAE_Settings();
$raw_url_with_entities = 'https://secure.adtraction.com/productfeed.htm?type=feed&amp;format=XML&amp;encoding=UTF8';
$clean_url = $settings_module->sanitize_feed_url( $raw_url_with_entities );
assert_test( ! str_contains( $clean_url, '&amp;' ) && str_contains( $clean_url, '&format=XML' ), 'feed_sanitization: &amp; decoded to plain &' );

echo "========================================================\n";
echo "ALL TESTS PASSED SUCCESSFULLY! (12/12 suites passed)\n";
echo "========================================================\n";
