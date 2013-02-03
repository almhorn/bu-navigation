<?php

if ( ! defined( 'BU_NAVIGATION_LINK_POST_TYPE' ) )
	define( 'BU_NAVIGATION_LINK_POST_TYPE', 'bu_link' );

define('BU_NAV_META_TARGET', 'bu_link_target'); // name of meta_key used to hold target window

/**
 * Register 'link' as a private post type for use representing external links in page navigation menus
 */
function bu_navigation_register_link() {

	$labels = array(
		'name'                => _x( 'BU Navigation Links', 'Post Type General Name', 'bu_navigation' ),
		'singular_name'       => _x( 'BU Navigation Link', 'Post Type Singular Name', 'bu_navigation' ),
		'menu_name'           => __( 'Link', 'bu_navigation' ),
		'parent_item_colon'   => __( 'Parent Link:', 'bu_navigation' ),
		'all_items'           => __( 'All Links', 'bu_navigation' ),
		'view_item'           => __( 'View Link', 'bu_navigation' ),
		'add_new_item'        => __( 'Add New Link', 'bu_navigation' ),
		'add_new'             => __( 'New Link', 'bu_navigation' ),
		'edit_item'           => __( 'Edit Link', 'bu_navigation' ),
		'update_item'         => __( 'Update Link', 'bu_navigation' ),
		'search_items'        => __( 'Search links', 'bu_navigation' ),
		'not_found'           => __( 'No links found', 'bu_navigation' ),
		'not_found_in_trash'  => __( 'No links found in Trash', 'bu_navigation' ),
	);

	$args = array(
		'label'               => __( 'BU Navigation Link', 'bu_navigation' ),
		'description'         => __( 'External links used by BU Navigation plugin', 'bu_navigation' ),
		'labels'              => $labels,
		'hierarchical'        => false,
		'public'              => false,
		'show_ui'             => false,
		'show_in_menu'        => false,
		'show_in_nav_menus'   => false,
		'show_in_admin_bar'   => false,
		'menu_position'       => 5,
		'menu_icon'           => '',
		'can_export'          => true,	//
		'has_archive'         => false,
		'exclude_from_search' => true,
		'publicly_queryable'  => true,
		'rewrite'             => false,
		'capability_type'     => 'post',
	);

	register_post_type( BU_NAVIGATION_LINK_POST_TYPE, $args );
}

add_action( 'init', 'bu_navigation_register_link' );

/**
 * Filter pages before displaying navigation to set external URL and window target for external links
 * @return array Filtered list of pages
 */
function bu_navigation_filter_pages_external_links($pages)
{
	global $wpdb;

	$filtered = array();

	if ((is_array($pages)) && (count($pages) > 0))
	{
		$ids = array_keys($pages);

		$query = sprintf("SELECT post_id, meta_value FROM %s WHERE meta_key = '%s' AND post_id IN (%s)", $wpdb->postmeta, BU_NAV_META_TARGET, implode(',', $ids));

		$targets = $wpdb->get_results($query, OBJECT_K); // get results as objects in an array keyed on post_id

		foreach ($pages as $page)
		{
			if ( $page->post_type == BU_NAVIGATION_LINK_POST_TYPE )
			{
				$page->url = $page->post_content;

				if ((is_array($targets)) && (array_key_exists($page->ID, $targets))) $page->target = $targets[$page->ID]->meta_value;
			}

			$filtered[$page->ID] = $page;
		}
	}

	return $filtered;
}

add_filter('bu_navigation_filter_pages', 'bu_navigation_filter_pages_external_links');

/**
 * Filter HTML attributes set on a navigation item anchor element to add window target where applicable
 * @return array Filtered anchor attributes
 */
function bu_navigation_filter_anchor_attrs_external_links($attrs, $page = NULL)
{
	if ((!is_null($page)) && (isset($page->target)) && ($page->target == 'new')) $attrs['target'] = '_blank';

	return $attrs;
}
add_filter('bu_navigation_filter_anchor_attrs', 'bu_navigation_filter_anchor_attrs_external_links');

/**
 * Filter the page_link to support the custom post_type 'link'
 * @param string $link URI
 * @param int $id Post ID
 * @see get_page_link()
 */
function bu_navigation_page_link_filter($link, $id)
{
	if (($page = get_post($id, OBJECT, 'raw', FALSE)) && ($page->post_type === BU_NAVIGATION_LINK_POST_TYPE )) {
		$link = $page->post_content;
	}
	return $link;
}

add_filter('page_link', 'bu_navigation_page_link_filter', 10, 2);

?>
