<?php
/**
 * Product pre-filter rule evaluation for Gear Engine.
 *
 * Pure evaluation of a normalized product item against the sync filter
 * rules (price bounds, stock, merchant lists, keywords). Lives outside the
 * sync engine so other features can apply the same catalog hygiene rules.
 *
 * @package GearEngine
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

/**
 * Class Gear_Engine_Sync_Rules
 */
class Gear_Engine_Sync_Rules {

	/**
	 * Evaluate a normalized product against the configured filter rules.
	 *
	 * @param array<string, mixed> $item         Normalized product data.
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
				if ( '' !== $keyword ) {
					// Use word boundary regex so keywords like 'manual' do not match 'manualer' (dumbbells).
					$pattern = '/(?:\b|_)' . preg_quote( $keyword, '/' ) . '(?:\b|_)/iu';
					if ( preg_match( $pattern, $searchable ) ) {
						$reason = sprintf( 'Matched blacklisted keyword "%s"', $keyword );
						return false;
					}
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
}
