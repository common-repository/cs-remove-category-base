<?php
/**
 * Plugin Name: CS Remove Category Base
 * Plugin URI: https://wordpress.org/plugins/cs-remove-category-base/
 * Description: CS Remove Category Base is an easy and useful WordPress Plugin. Use this plugin to remove the category base.
 * Version: 1.0.2
 * Text Domain: cs-remove-category-base
 * Author: Codept Solutions
 * Author URI: https://codeptsolutions.com
 *
 * @package CSRemoveCategoryBase
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
} // Exit if accessed directly

if ( ! defined( 'CSRCB_PLUGIN_DIR' ) ) {
	define( 'CSRCB_PLUGIN_DIR', __FILE__ );
}

if ( ! defined( 'CSRCB_PLUGIN_URL' ) ) {
	define( 'CSRCB_PLUGIN_URL', untrailingslashit( plugins_url( '/', CSRCB_PLUGIN_DIR ) ) );
}

if ( ! defined( 'CSRCB_VERSION' ) ) {
	define( 'CSRCB_VERSION', '1.0.2' );
}

register_activation_hook( __FILE__, 'cs_remove_category_refresh_rules' );

add_action( 'created_category', 'cs_remove_category_refresh_rules' );
add_action( 'edited_category', 'cs_remove_category_refresh_rules' );
add_action( 'delete_category', 'cs_remove_category_refresh_rules' );

/**
 * Register activation hook for this plugin by invoking activate.
 *
 * @since 1.0
 */
function cs_remove_category_refresh_rules() {
	add_option( 'cs_remove_category_base_rewrite_rules_flush', true );
}

register_deactivation_hook( __FILE__, 'cs_remove_category_base_deactivate' );

/**
 * Register deactivation hook for this plugin by invoking deactivate.
 *
 * @since 1.0
 */
function cs_remove_category_base_deactivate() {
	remove_filter( 'category_rewrite_rules', 'cs_remove_category_refresh_rules' ); // We don't want to insert our custom rules again.
	delete_option( 'cs_remove_category_base_rewrite_rules_flush' );
}

// Remove category base.
add_action( 'init', 'cs_remove_category_base_perma_struct', 999999 );
/**
 * Remove category base
 *
 * @since 1.0
 */
function cs_remove_category_base_perma_struct() {
	global $wp_rewrite;
	$wp_rewrite->extra_permastructs['category'][0] = '%category%';

	$b_found = false;
	$a_rules = get_option( 'rewrite_rules' );
	if ( $a_rules && count( $a_rules ) > 0 ) {
		foreach ( $a_rules as $key => $value ) {
			if ( 'cs-tremove-cat-base-tweaks-detector-235hnguh9hq46j0909iasn0zzdfsAJ' === $key ) {
				$b_found = true;
				break;
			}
		}
	}

	if ( ! $b_found || get_option( 'cs_remove_category_base_rewrite_rules_flush' ) ) {
		flush_rewrite_rules();
		delete_option( 'cs_remove_category_base_rewrite_rules_flush' );
	}
}

// Add our custom category rewrite rules.
add_filter( 'category_rewrite_rules', 'cs_remove_category_base_rewrite_rules' );
/**
 * Add our custom category rewrite rules.
 *
 * @param array $category_rewrite Array of rewrite rules generated for the current permastruct, keyed by their regex pattern.
 *
 * @since 1.0
 */
function cs_remove_category_base_rewrite_rules( $category_rewrite ) {

	$b_amp = false;
	foreach ( $category_rewrite as $k => $v ) {
		if ( stripos( $k, '/amp' ) !== false ) {
			$b_amp = true;
		}
	}

	// First we need to get full URLs of our pages.
	$pages      = get_pages( 'number=0' );
	$pages_urls = array();
	foreach ( $pages as $pages_item ) {
		$pages_urls[] = trim( str_replace( get_bloginfo( 'url' ), '', get_permalink( $pages_item->ID ) ), '/' );
	}
		global $wp_rewrite;

	$category_rewrite = array();
	$categories       = get_categories( array( 'hide_empty' => false ) );
	foreach ( $categories as $category ) {
		$category_nicename = $category->slug;
		if ( $category->parent === $category->cat_ID ) { // recursive recursion.
			$category->parent = 0;
		} elseif ( 0 !== $category->parent ) {
			$category_nicename = get_category_parents( $category->parent, false, '/', true ) . $category_nicename;
		}

		// Let's check if any of the category full URLs matches any of the pages.
		if ( in_array( $category_nicename, $pages_urls, true ) ) {
			continue;
		}

		$category_rewrite[ '(' . $category_nicename . ')/(?:feed/)?(feed|rdf|rss|rss2|atom)/?$' ]                = 'index.php?category_name=$matches[1]&feed=$matches[2]';
		$category_rewrite[ '(' . $category_nicename . ')/' . $wp_rewrite->pagination_base . '/?([0-9]{1,})/?$' ] = 'index.php?category_name=$matches[1]&paged=$matches[2]';
		if ( $b_amp ) {
			$category_rewrite[ '(' . $category_nicename . ')/amp/' . $wp_rewrite->pagination_base . '/?([0-9]{1,})/?$' ] = 'index.php?amp&category_name=$matches[1]&paged=$matches[2]';
			$category_rewrite[ '(' . $category_nicename . ')/amp/?$' ] = 'index.php?amp&category_name=$matches[1]';
		}
		$category_rewrite[ '(' . $category_nicename . ')/?$' ] = 'index.php?category_name=$matches[1]';
	}

	// Redirect support from Old Category Base.
	$old_category_base                                 = get_option( 'category_base' ) ? get_option( 'category_base' ) : 'category';
	$old_category_base                                 = trim( $old_category_base, '/' );
	$category_rewrite[ $old_category_base . '/(.*)$' ] = 'index.php?category_redirect=$matches[1]';

	$category_rewrite['cs-tremove-cat-base-tweaks-detector-235hnguh9hq46j0909iasn0zzdfsAJ'] = 'index.php?cs-tremove-cat-base-tweaks-detector-235hnguh9hq46j0909iasn0zzdfsAJ=1';

	return $category_rewrite;
}

// Add 'category_redirect' query variable.
add_filter( 'query_vars', 'cs_remove_category_base_query_vars' );
/**
 * Add 'category_redirect' query variable.
 *
 * @param array $public_query_vars The array of allowed query variable names.
 *
 * @since 1.0
 */
function cs_remove_category_base_query_vars( $public_query_vars ) {
	$public_query_vars[] = 'category_redirect';
	return $public_query_vars;
}

// Redirect if 'category_redirect' is set.
add_filter( 'request', 'cs_remove_category_base_request' );
/**
 * Redirect if 'category_redirect' is set.
 *
 * @param array $query_vars Request data in WP_Http format.
 *
 * @since 1.0
 */
function cs_remove_category_base_request( $query_vars ) {
	if ( isset( $query_vars['category_redirect'] ) ) {
		$catlink = trailingslashit( get_option( 'home' ) ) . user_trailingslashit( $query_vars['category_redirect'], 'category' );
		status_header( 301 );
		header( "Location: $catlink" );
		exit();
	}
	return $query_vars;
}

// Change category link.
add_filter( 'category_link', 'cs_remove_category_base_cat_link' );
/**
 * Change category link.
 *
 * @param string $link Category link URL.
 *
 * @since 1.0
 */
function cs_remove_category_base_cat_link( $link ) {
	$category_base = get_option( 'category_base' );

	// WP uses "category/" as the default.
	if ( '' === $category_base ) {
		$category_base = 'category';
	}

	// Remove initial slash, if there is one (we remove the trailing slash in the regex replacement and don't want to end up short a slash).
	if ( substr( $category_base, 0, 1 ) === '/' ) {
		$category_base = substr( $category_base, 1 );
	}

	$category_base .= '/';

	return preg_replace( '|' . $category_base . '|', '', $link, 1 );
}
