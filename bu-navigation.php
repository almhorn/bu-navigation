<?php
/*
Plugin Name: Page Navigation 
Version: 0.1
Author URI: http://www.bu.edu/tech/help/
Description: Provides alternative navigation elements designed for blogs with large page counts
Author: Networked Information Services
*/

/* BU Navigation constants */
define('BU_NAV_META_PAGE_LABEL', '_bu_cms_navigation_page_label'); // name of meta_key used to hold navigation labels
define('BU_NAV_META_PAGE_EXCLUDE', '_bu_cms_navigation_exclude'); // name of meta_key used to exclude pages from navigation

/* Widgets */
require_once('bu-navigation-widget.php');

/**
* Returns an array of page objects indexed by page ID
* TODO: Function incomplete; most arguments ignored. Sort order should allow +1 column
* @param $args mixed Wordpress-style arguments (string or array)
* @return array Array of pages keyed on page ID or FALSE on problem
*/
function bu_navigation_get_pages($args = '')
{
	global $wpdb;

	$defaults = array(
		'in_section' => NULL,
		'child_of' => 0, 
		'sort_order' => 'ASC',
		'sort_column' => 'post_title', 
		'hierarchical' => 1,
		'exclude' => '', 
		'include' => '',
		'meta_key' => '', 
		'meta_value' => '',
		'primary' => '',
		'authors' => '', 
		'max_items' => '',
		'post_status' => 'publish'
		);

	$r = wp_parse_args($args, $defaults); // parsed arguments

	/* list of fields to SELECT */

	$fields = array(
		'ID',
		'post_date',
		'post_title',
		'post_excerpt',
		'post_name',
		'post_parent',
		'guid',
		'menu_order',
		'post_type'
		);

	$params = array(); /* will hold parameters to pass to query */

	/* build the query */
	$query = sprintf('SELECT %s FROM %s ', implode(',', $fields), $wpdb->posts);

	/* only get pages and external links */
	$query .= " WHERE post_type IN('page','link') ";

	/* restrict status of pages */
	$query .= ' AND post_status = %s ';
	array_push($params, $r['post_status']);

	/* result sorting */
	$sort_order = (strtoupper($r['sort_order']) == 'DESC') ? 'DESC' : 'ASC'; // will default to ASC if invalid arg found
	$sort_field = (in_array($r['sort_column'], $fields)) ? $r['sort_column'] : 'post_title'; // defaults to post_title if invalid arg found
	$query .= sprintf(' ORDER BY %s %s ', $sort_field, $sort_order);

	$sql = $wpdb->prepare($query, $params);

	error_log($sql);
	
	$pages = $wpdb->get_results($sql, OBJECT_K); // get results as objects in an array keyed on posts.ID 

	if ((!is_array($pages)) || (count($pages) == 0)) return FALSE;

	return $pages;
}

function bu_navigation_pages_by_parent($pages)
{
	$pages_by_parent = array();
	
	if ((is_array($pages)) && (count($pages) > 0))
	{
		foreach ($pages as $page)
		{
			if (!array_key_exists($page->post_parent, $pages_by_parent)) $pages_by_parent[$page->post_parent] = array();
			array_push($pages_by_parent[$page->post_parent], $page);
		}
	}
	else
	{
		error_log('bu_navigation_pages_by_parent pages is not an array or is empty');
	}
	
	return $pages_by_parent;
}

function bu_navigation_list_section($parent_id, $pages_by_parent)
{
	$html = '';
	
	if (array_key_exists($parent_id, $pages_by_parent))
	{
		$children = $pages_by_parent[$parent_id];
		
		if ((is_array($children)) && (count($children) > 0))
		{
			error_log(sprintf('Section %d has %d children', $parent_id, count($children)));
			
			$html .= "<ul>\n";
			
			foreach ($children as $page)
			{
				$child_html .= bu_navigation_list_section($page->ID, $pages_by_parent);
				$html .= bu_navigation_format_page($page, $child_html);
			}
			
			$html .= "</ul>\n";
		}
	}
	
	return $html;
}

function bu_navigation_format_page($page, $html = '')
{
	$html = sprintf('<li><a href="">%s</a> %s</li>', $page->post_title, $html);
	$html .= "\n";
	
	return $html;
}

function bu_navigation_list_pages($args = '')
{
	$defaults = array(
		'in_section' => NULL,
		'depth' => 0, 
		'show_date' => '',
		'date_format' => get_option('date_format'),
		'child_of' => 0, 
		'exclude' => '',
		'title_li' => __('Pages'), 
		'echo' => 1,
		'authors' => '', 
		'sort_column' => 'menu_order, post_title'
		);

	$r = wp_parse_args($args, $defaults);
	
	$pages = bu_navigation_get_pages($r);

	$output = '';

	// error_log('enter walk_page_tree');
	// 	$output .= walk_page_tree($pages, $r['depth'], $current_page, $r);
	// 	error_log('exit walk_page_tree');
	
	error_log('enter bu_navigation_construct_tree');

	$html = "<ul>\n";
	$pages_by_parent = bu_navigation_pages_by_parent($pages);
	error_log(sprintf('pages_by_parent contains %d pages', count($pages_by_parent)));
	
	if (array_key_exists($r['child_of'], $pages_by_parent))
	{
		$root_pages = $pages_by_parent[$r['child_of']];
		
		foreach ($root_pages as $page)
		{
			$child_html .= bu_navigation_list_section($page->ID, $pages_by_parent);
			$html .= bu_navigation_format_page($page);
		}
	}
	else
	{
		error_log(sprintf('pages_by_parent does not contain desired section %d', $r['child_of']));
	}
	
	$html .= "</ul>\n";
	
	error_log('exit bu_navigation_construct_tree');
	
	return $html;
}

function bu_navigation_widgets_init() 
{
	if ( !is_blog_installed() )
		return;

	register_widget('BU_Widget_Pages');

	do_action('widgets_init');
}

add_action('init', 'bu_navigation_widgets_init', 1);
?>