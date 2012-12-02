<?php
require_once(dirname(__FILE__) . '/classes.nav-tree.php' );
require_once(dirname(__FILE__) . '/classes.reorder.php' );
/*
@todo
	- test more thoroughly with multiple custom post types

@todo unit tests
	- lock methods
	- processing post methods

@todo selenium tests
	- locking behavior
	- check validation / invalid save
*/

/**
 * BU Navigation Admin Navigation Manager interface
 */
class BU_Navigation_Admin_Navman {

	public $page;
	public $reorder_tracker;

	const OPTION_LOCK_TIME = '_bu_navman_lock_time';
	const OPTION_LOCK_USER = '_bu_navman_lock_user';

	const MESSAGE_UPDATED = 1;
	const NOTICE_ERRORS = 1;
	const NOTICE_LOCKED =2;

	private $messages = array();
	private $plugin;

	public function __construct( $post_type, $plugin ) {

		$this->plugin = $plugin;
		$this->post_type = $post_type;
		$this->pages = array();

		// Attach WP actions/filters
		$this->register_hooks();

	}

	/**
	* Attach WP actions and filters utilized by our meta boxes
	*/
	public function register_hooks() {

		add_action('admin_menu', array( $this, 'register_menu' ) );
		add_action('admin_enqueue_scripts', array( $this, 'add_scripts' ) );
		
	}

	/**
	 * Generate admin menu cap for the given post type
	 *
	 * Includes logic that makes menu accessible for section editors
	 */
	public function get_menu_cap_for_post_type( $post_type ) {
		$pto = get_post_type_object( $post_type );

		if ( $pto->map_meta_cap ) {
			if ( current_user_can( $pto->cap->edit_published_posts ) ) {
				$cap = $pto->cap->edit_published_posts;
			} else {
				$cap = 'edit_' . $post_type . '_in_section';
			}
		} else {
			if ( current_user_can( $pto->cap->edit_others_posts ) ) {
				$cap = $pto->cap->edit_others_posts;
			} else {
				$cap = 'edit_' . $post_type . '_in_section';
			}
		}

		return $cap;
	}

	/**
	 * Add "Edit Order" submenu pages to allow editing the navigation of the supported post types
	 */
	public function register_menu() {

		// Add "Edit Order" links to the submenu of each supported post type
		$post_types = bu_navigation_supported_post_types();

		foreach( $post_types as $pt ) {

			$parent_slug = 'edit.php?post_type=' . $pt;

			$page = add_submenu_page(
				$parent_slug,
				__('Edit Order'),
				__('Edit Order'),
				$this->get_menu_cap_for_post_type( $pt ),
				'bu-navigation-manager',
				array( $this, 'render' )
				);

			$this->pages[] = $page;

			add_action('load-' . $page, array( $this, 'load' ) );

		}

		// @todo check if current page is navman before clearing
		$this->clear_lock();

	}

	/**
	 * Register dependent Javscript and CSS files
	 */
	public function add_scripts( $page ) {

		if( is_array( $this->pages ) && in_array( $page, $this->pages ) ) {

			$suffix = defined('SCRIPT_DEBUG') && SCRIPT_DEBUG ? '.dev' : '';

			// Scripts
			wp_register_script('bu-jquery-validate', plugins_url('js/vendor/jquery.validate' . $suffix . '.js', __FILE__), array('jquery'), '1.8.1', true );
			wp_register_script('bu-navman', plugins_url('js/manage' . $suffix . '.js', __FILE__), array('bu-navigation','jquery-ui-dialog','bu-jquery-validate'), BU_Navigation_Plugin::VERSION, true );

			// Setup dynamic script context for manage.js
			$post_types = ( $this->post_type == 'page' ? array( 'page', 'link' ) : array( $this->post_type ) );

			$script_context = array(
				'postTypes' => $post_types,
				'nodePrefix' => 'nm',
				'lazyLoad' => true,
				'showCounts' => true
				);
			// Navigation tree view will handle actual enqueuing of our script
			$treeview = new BU_Navigation_Tree_View( 'bu_navman', $script_context );
			$treeview->enqueue_script('bu-navman');
			
			// Styles
			if ( 'classic' == get_user_option( 'admin_color') ) {
				wp_enqueue_style ( 'bu-jquery-ui-css',  plugins_url( '/css/jquery-ui-classic.css', __FILE__ ), array(), BU_Navigation_Plugin::VERSION );
			} else {
				wp_enqueue_style ( 'bu-jquery-ui-css',  plugins_url( '/css/jquery-ui-fresh.css', __FILE__ ), array(), BU_Navigation_Plugin::VERSION );
			}

			wp_enqueue_style('bu-navman', plugins_url('css/manage.css', __FILE__), array(), BU_Navigation_Plugin::VERSION );

		}

	}

	/**
	 * Handle admin page setup
	 */
	public function load() {

		// Save if post data is present
		$saved = $this->save();

		// Post/Redirect/Get
		if( ! is_null( $saved ) ) {

			// Prune redirect uri
			$url = remove_query_arg(array('message','notice'), wp_get_referer());

			// Notifications
			if( $saved === true ) $url = add_query_arg( 'message', 1, $url );
			else $url = add_query_arg( 'notice', 1, $url );

			wp_redirect( $url );

		}

		$this->setup_notices();
		$this->setup_locks();

	}

	/**
	 * Add notices if we have any in the queue
	 */
	public function setup_notices() {

		// Setup initial empty data structure
		$this->messages['message'] = array();
		$this->messages['notice'] = array();

		// Grab any notices from query string
		$message_code = isset($_GET['message']) ? intval($_GET['message']) : 0;
		$notice_code = isset($_GET['notice']) ? intval($_GET['notice']) : 0;

		$message = $this->get_notice_by_code( 'message', $message_code );
		$notice = $this->get_notice_by_code( 'notice', $notice_code );

		// Append to member property for display during get_notice_list
		if( $message ) $this->messages['message'][] = $message;
		if( $notice ) $this->messages['notice'][] = $notice;

	}

	/**
	 * Retrieve notice message by type and numeric code:
	 *
	 * @param string $type the type of notice (either 'message' or 'notice')
	 * @param int $code the notice code (see const NOTICE_* and const MESSAGE_*)
	 */
	public function get_notice_by_code( $type, $code ) {

		$notices = array(
			'message' => array(
				0 => '', // Unused. Messages start at index 1.
				1 => __('Your navigation changes have been saved')
			),
			'notice' => array(
				0 => '',
				1 => __('Errors occurred while saving your navigation changes.'),
				2 => __('Warning: <strong>%s</strong> is currently editing this site\'s navigation.')
			)
		);

		if( array_key_exists( $type, $notices ) && array_key_exists( $code, $notices[$type] )) {
			return $notices[$type][$code];
		}

		return '';

	}

	/**
	 * Formats existing messages & notices for display
	 */
	public function get_notice_list() {

		$output = '';

		foreach( $this->messages as $type => $messages ) {

			$i = 0;
			$inner_content = '';

			if( count( $messages ) > 0 ) {
				$classes = 'message' == $type ? 'updated fade' : 'error';

				while( $i < count( $messages ) ) {
					$inner_content = sprintf( "<p>%s</p>\n", $messages[$i] );
					$output .= sprintf( "<div class=\"%s below-h2\">%s</div>\n", $classes, $inner_content );

					$i++;
				}

			}

		}

		return $output;

	}

	/**
	 * Set and check user locks
	 */
	public function setup_locks() {

		// Attempt to set lock
		$this->set_lock();

		// Check the lock to see if there is a user currently editing this page
		$editing_user = $this->check_lock();

		// Push locked notice to admin_notices
		if( is_numeric( $editing_user ) ) {
			$user_detail = get_userdata(intval($editing_user));
			$notice = $this->get_notice_by_code( 'notice', self::NOTICE_LOCKED );
			$this->messages['notice'][] = sprintf( $notice, $user_detail->display_name );
		}

	}

	/**
	 * Display navigation manager page
	 */
	public function render() {

		if( is_null( $this->post_type ) ) {
			wp_die('Edit order page is not available for post type: ' . $this->post_type );
			return;
		}

		$cap = $this->get_menu_cap_for_post_type( $this->post_type );

		if( ! current_user_can( $cap ) ) {
			wp_die('Cheatin, uh?');
		}

		$ajax_spinner = plugins_url( '/images/wpspin_light.gif', __FILE__);
		
		// If link was a registered post type, we would use its publish meta cap here instead
		$disable_add_link = ! $this->can_publish_top_level();
		$post_type = $this->post_type;
		$notices = $this->get_notice_list();
		$pt_labels = $this->plugin->get_post_type_labels( $post_type );

		// Render interface
		include(BU_NAV_PLUGIN_DIR . '/interface/manage.php');

	}

	/**
	 * Handle $_POST submissions for navigation management page
	 *
	 * @todo decide how best to handle failures
	 */
	public function save() {
		$saved = NULL;
		$errors = array();

		if( array_key_exists( 'bu_navman_save', $_POST ) ) {

			// error_log('===== Starting navman save =====');
			// $time_start = microtime(true);

			$saved = false;

			$this->reorder_tracker = new BU_Navigation_Reorder_Tracker( $this->post_type );

			// Process post removals
			$deletions = json_decode( stripslashes($_POST['navman-deletions']) );
			$result = $this->process_deletions( $deletions );

			if( is_wp_error( $result ) ) {
				array_push( $errors, $result );
			}

			// Process link updates
			$updates = (array) json_decode( stripslashes($_POST['navman-updates']) );
			$result = $this->process_updates( $updates );

			if( is_wp_error( $result ) ) {
				array_push( $errors, $result );
			}

			// Process link insertions
			$inserts = (array) json_decode( stripslashes($_POST['navman-inserts']) );
			$result = $this->process_insertions( $inserts );

			if( is_wp_error( $result ) ) {
				array_push( $errors, $result );
			}

			// Process moves
			$moves = (array) json_decode( stripslashes($_POST['navman-moves']) );
			$result = $this->process_moves( $moves );

			if( is_wp_error( $result ) ) {
				array_push( $errors, $result );
			}

			// Update menu order for affected children
			$result = $this->reorder_tracker->run();

			if( false === $result ) {
				array_merge( $errors, $this->reorder_tracker->errors );
			}

			// error_log('Finished navman save in ' . sprintf('%f',(microtime(true) - $time_start)) . ' seconds');

			if (function_exists('invalidate_blog_cache')) invalidate_blog_cache();

			if( 0 == count( $errors ) ) {
				$saved = true;
			} else {
				// @todo notify user of error messages from WP_Error objects
				error_log('Errors encountered during navman save:' . print_r( $errors, true ) );
			}

		}

		return $saved;
	}

	/**
	 * Trashes posts that have been removed using the navman interface
	 *
	 * @param array $post_ids an array of post ID's for trashing
	 * @return bool|WP_Error $result the result of the post deletions
	 */
	public function process_deletions( $post_ids ) {
		// error_log('===== Processing deletions =====');
		// error_log('To delete: ' . print_r($post_ids,true ) );

		$result = null;
		$failures = array();

		if ( ( is_array( $post_ids ) ) && ( count( $post_ids ) > 0 ) ) {

			foreach( $post_ids as $id ) {

				$post = get_post( $id );

				$deleted = $force_delete = false;

				// Permanently delete links, as there is currently no way to recover them from trash
				if( 'link' == $post->post_type ) {
					$force_delete = true;
				}

				if( $this->can_delete( $post ) ) {
					$deleted = wp_delete_post( (int) $id, $force_delete );
				}

				if( ! $deleted ) {
					error_log(sprintf('[BU Navigation Navman] Unable to delete post %d', $id));
					array_push( $failures, $id );
				} else {

					$this->reorder_tracker->mark_section_for_reordering( $post->post_parent );

					// Temporary logging
					// error_log('Post deleted: ' . $id );
					// error_log('Marking old section for reordering: ' . $post->post_parent);
				}

			}

		}

		if( count( $failures ) ) {
			$result = new WP_Error( 'bu_navigation_save_error', 'Could not delete post(s): ' . implode(', ', $failures ) );
		} else {
			$result = true;
		}

		return $result;
	}

	/**
	 * Updates posts (really just links at this time) that have been modified using the navman interface
	 *
	 * @todo write unit tests
	 *
	 * @param array $posts an array of posts which have been modified
	 * @return bool|WP_Error $result the result of the post updates
	 */
	public function process_updates( $posts ) {
		// error_log('===== Processing updates =====');
		// error_log('To update: ' . print_r($posts,true ) );

		$result = null;
		$failures = array();

		if( ( is_array( $posts ) ) && ( count( $posts ) > 0 ) ) {

			foreach( $posts as $post ) {

				$updated = false;

				$post->ID = (int) $post->ID;

				if( $this->can_edit( $post ) ) {

					$data = array(
						'ID' => $post->ID,
						'post_title' => $post->post_title,	// sanitize?
						'post_content' => $post->post_content // sanitize?
						);

					$updated = wp_update_post( $data, true );

				}

				if( false == $updated || is_wp_error( $updated ) ) {

					error_log(sprintf('[BU Navigation Navman] Could not update link: %s', print_r($post, true)));
					error_log(print_r($updated,true));
					array_push( $failures, $post->post_title );

				} else {

					$target = ($post->post_meta->bu_link_target === 'new') ? 'new' : 'same';
					update_post_meta( $post->ID, 'bu_link_target', $target );

					// Temporary logging
					// error_log('Link updated: ' . $post->post_title );
				}

			}

		}

		if( count( $failures ) ) {
			$result = new WP_Error( 'bu_navigation_save_error', 'Could not update link(s): ' . implode(', ', $failures ) );
		} else {
			$result = true;
		}

		return $result;
	}

	/**
	 * Insert posts (really just links at this time) that have been added using the navman interface
	 *
	 * @todo write unit tests
	 *
	 * @param array $posts an array of posts which have been added
	 * @return bool|WP_Error $result the result of the post insertions
	 */
	public function process_insertions( $posts ) {
		// error_log('===== Processing insertions =====');
		// error_log('To insert: ' . print_r($posts,true ) );

		$result = null;
		$failures = array();

		if( ( is_array( $posts ) ) && ( count( $posts ) > 0 ) ) {

			foreach( $posts as $post ) {

				// Special handling for new links -- need to get a valid post ID
				if ( 'link' == $post->post_type ) {

					$inserted = false;

					$post->post_parent = (int) $post->post_parent;
					$post->menu_order = (int) $post->menu_order;

					if( $this->can_place_in_section( $post ) ) {

						$data = array(
							'post_title' => $post->post_title,
							'post_content' => $post->post_content,
							'post_excerpt' => '',
							'post_status' => 'publish',
							'post_type' => 'link',
							'post_parent' => $post->post_parent,
							'menu_order' => $post->menu_order
							);

						$inserted = wp_insert_post( $data, true );

					}

					if( false == $inserted || is_wp_error( $inserted ) ) {

						error_log(sprintf('[BU Navigation Navman] Could not create link: %s', print_r($post, true)));
						error_log(print_r($inserted,true));
						array_push( $failures, $post->post_title );

					} else {

						$post->ID = $inserted;

						$target = ($post->post_meta->bu_link_target === 'new') ? 'new' : 'same';
						update_post_meta($post->ID, 'bu_link_target', $target );

						// Mark for reordering
						$this->reorder_tracker->mark_post_as_moved( $post );

						// Temporary logging
						// error_log('Link inserted: ' . $post->post_title . ' (' . $post->ID . ')' );

					}

				}

			}

		}

		if( count( $failures ) ) {
			$result = new WP_Error( 'bu_navigation_save_error', 'Could not insert link(s): ' . implode(', ', $failures ) );
		} else {
			$result = true;
		}

		return $result;
	}

	/**
	 * Updates posts that have been moved using the navman interface
	 *
	 * @todo write unit tests
	 *
	 * @param array $posts an array of posts which have new menu_order or post_parent fields
	 * @return bool|WP_Error $result the result of the post movements
	 */
	public function process_moves( $posts  ) {
		// error_log('===== Processing moves =====');
		// error_log('To move: ' . print_r($posts,true ) );

		$result = null;
		$failures = array();

		if( ( is_array( $posts ) ) && ( count( $posts ) > 0 ) ) {

			do_action('bu_navman_pages_pre_move');

			foreach( $posts as $post ) {

				$updated = false;

				$original = get_post($post->ID);

				if( $post->post_parent == $original->post_parent && $post->menu_order == $original->menu_order ) {
					error_log('Post was marked as moved, but neither parent or menu order has actually changed -- skipping...');
					continue;
				}

				if( $this->can_move( $post, $original ) ) {

					// Update post parent and menu order
					$updated = wp_update_post(array('ID'=>$post->ID,'post_parent'=>$post->post_parent,'menu_order'=>$post->menu_order), true );

				}

				// @todo handle ugly case where wp_update_post returns failure but has actually updated the post (i.e. invalid_page_template error)
				if( false == $updated || is_wp_error( $updated ) ) {

					error_log(sprintf('[BU Navigation Navman] Could not move post: %s', print_r($post, true)));
					error_log(print_r($updated, true));
					array_push( $failures, $post->ID );

				} else {

					// Mark for reordering
					$this->reorder_tracker->mark_post_as_moved( $post );

					// Temporary logging
					// error_log('Post moved: ' . $post->ID );

					if( $post->post_parent != $original->post_parent ) {
						// error_log('Post has changed parent, marking old section for reordering: ' . $original->post_parent );
						$this->reorder_tracker->mark_section_for_reordering( $original->post_parent );
					}

				}

			}

			do_action('bu_navman_pages_moved');

		}

		if( count( $failures ) ) {
			$result = new WP_Error( 'bu_navigation_save_error', 'Could not move post(s): ' . implode(', ', $failures ) );
		} else {
			$result = true;
		}

		return $result;

	}

	/**
	 * Whether or not the current user can publish top level content
	 *
	 * @todo decouple from section editing plugin
	 */
	public function can_publish_top_level() {

		$allow_top = $this->plugin->get_setting('allow_top');
		$is_section_editor = ! is_super_admin() && current_user_can( 'edit_in_section' );

		return $allow_top && !$is_section_editor;

	}

	/**
	 * Can the current user edit the supplied post
	 *
	 * Needed because links are not registered post types and therefore current_user_can checks are insufficient
	 *
	 * @param object|int $post post obj or post ID to check edit caps for
	 */
	public function can_edit( $post ) {
		$allowed = false;

		if( is_numeric( $post ) ) {
			$post = get_post($post);
		}
		if( ! is_object( $post ) ) {
			return false;
		}

		// @todo we can't respect section editing permissions for links via current_user_can
		// until they are a registered post type
		if( 'link' == $post->post_type ) {
			$is_section_editor = ! is_super_admin() && current_user_can( 'edit_in_section' );

			if( class_exists('BU_Group_Permissions') && $is_section_editor ) {
				$allowed = BU_Group_Permissions::can_edit_section( wp_get_current_user(), $post->ID );
			} else {
				$allowed = current_user_can('edit_pages');
			}
		} else {
			$allowed = current_user_can( 'edit_post', $post->ID );
		}

		return $allowed;
	}

	/**
	 * Can the current user delete the supplied post
	 *
	 * Needed because links are not registered post types and therefore current_user_can checks are insufficient
	 *
	 * @param object|int $post post obj or post ID to check delete caps for
	 */
	public function can_delete( $post ) {
		$allowed = false;

		if( is_numeric( $post ) ) {
			$post = get_post($post);
		}
		if( ! is_object( $post ) ) {
			return false;
		}

		if( 'link' == $post->post_type ) {
			$is_section_editor = ! is_super_admin() && current_user_can( 'edit_in_section' );

			if( class_exists('BU_Group_Permissions') && $is_section_editor ) {
				$allowed = BU_Group_Permissions::can_edit_section( wp_get_current_user(), $post->ID );
			} else {
				$allowed = current_user_can('edit_pages');
			}
		} else {
			$allowed = current_user_can( 'delete_post', $post->ID );
		}

		return $allowed;
	}

	/**
	 * Can the current user switch post parent for the supplied post
	 *
	 * @param object|int $post post obj or post ID to check move for
	 */
	public function can_place_in_section( $post, $prev_parent = null ) {
		$allowed = false;

		if( is_numeric( $post ) ) {
			$post = get_post($post);
		}
		if( ! is_object( $post ) ) {
			return false;
		}

		// Top level move
		if( 0 == $post->post_parent ) {

			// Move is promotion to top level
			if( 0 !== $prev_parent ) {
				$allowed = $this->can_publish_top_level();
			} else {
				// Post was already top level, move is allowed
				$allowed = true;
			}

		} else {

			// Move under another post -- check if parent is editable
			$allowed = current_user_can( 'edit_post', $post->post_parent );

			// Don't allow movement of published posts under non-published posts
			if( $post->post_status == 'publish') {
				$parent = get_post($post->post_parent);
				$allowed = $allowed && $parent->post_status == 'publish';
			}
		}

		return $allowed;
	}

	/**
	 * Can the current user move the supplied post
	 *
	 * @param object|int $post post obj or post ID to check move for
	 * @param object|int $original post obj or post ID of previous parent
	 */
	public function can_move( $post, $original ) {
		// error_log('===== Checking can_move =====');
		// error_log('For Post: ' . print_r( $post, true ) );

		if( is_numeric( $post ) ) {
			$post = get_post($post);
		}
		if( ! is_object( $post ) ) {
			return false;
		}

		$prev_parent = null;

		if( is_numeric( $original ) ) {
			$original = get_post($original);
		}
		if( is_object( $original ) ) {
			$prev_parent = $original->post_parent;
		}

		$can_edit_post = $this->can_edit( $post );
		$can_edit_parent = $this->can_place_in_section( $post, $prev_parent );

		return $can_edit_post && $can_edit_parent;
	}

	/**
	 * @todo needs unit tests
	 */
	public function set_lock() {
		global $current_user;

		if( ! $this->check_lock() ) {
			$now = time();

			update_option( self::OPTION_LOCK_TIME , $now);
			update_option( self::OPTION_LOCK_USER , $current_user->ID);

		}
	}

	/**
	 * @todo needs unit tests
	 */
	public function check_lock() {
		global $current_user;

		$lock_time = get_option( self::OPTION_LOCK_TIME );
		$lock_user = get_option( self::OPTION_LOCK_USER );

		$time_window = apply_filters('wp_check_post_lock_window', AUTOSAVE_INTERVAL * 2);

		if ( $lock_time && $lock_time > time() - $time_window && $lock_user != $current_user->ID ) {
			return $lock_user;
		}

		return false;
	}

	/**
	 * @todo needs unit tests
	 */
	public function clear_lock() {
		global $current_user;

		$lock_user = get_option( self::OPTION_LOCK_USER );

		if( $lock_user == $current_user->ID ) {
			delete_option( self::OPTION_LOCK_TIME );
			delete_option( self::OPTION_LOCK_USER );
		}

	}

}

?>
