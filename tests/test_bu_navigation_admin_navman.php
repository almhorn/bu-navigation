<?php

/**
 * BU Navigation - Admin - Navman (Edit Order page)
 *
 * These tests depend on the BU Section Editing plugin
 *
 * @group bu
 * @group bu-navigation
 * @group bu-navigation-admin
 * @group bu-navigation-navman
 */
class BU_Navigation_Navman_Tests extends WP_UnitTestCase {

	public $plugin;
	public $navman;

	public $users;
	public $posts;
	public $section_groups;

	public $buse_is_active;

	public function setUp() {

		parent::setUp();

		$this->plugin = new BU_Navigation_Plugin();
		$this->plugin->load_admin();

		$this->navman = $this->plugin->admin->load_navman_page();
		$this->navman->reorder_tracker = new BU_Navigation_Reorder_Tracker('page');

		register_post_type( 'link', array('name' => 'Link') );

		// Setup users
		$this->users = array(
			'admin' => $this->factory->user->create(array('role'=>'administrator','user_email'=>'wpcms01@bu.edu')),
			'contrib' => $this->factory->user->create(array('role'=>'contributor','user_email'=>'wpcms02@bu.edu'))
			);

		// Setup posts
		$posts_json = file_get_contents( dirname(__FILE__) . '/data/test_posts.json');
		$posts = json_decode($posts_json, true);
		$this->load_test_posts( $posts );

		// Requires the BU Section Editing plugin to be activated
		$this->create_test_group();

	}

	/**
	 * Helper method that processes a specially formatted array structure to insert
	 * posts and post metadata recursively
	 *
	 * Handles
	 *  - all post fields (through the "data" attribute)
	 *  - post meta data (by specifying "metakey" => "value" for the "metadata" attribute)
	 * 	- hierarchical posts (by nesting post data in the "children" attribute)
	 *
	 * Each post should be given a unique key, which will be used to store the post ID
	 * for reference during tests.
	 *
	 * See the file for an example:
	 * 	tests/data/test_posts.json
	 */
	public function load_test_posts( $posts, $parent_id = 0 ) {

		foreach( $posts as $key => $post ) {

			$data = $post['data'];

			// Maybe set parent
			if( $parent_id )
				$data['post_parent'] = $parent_id;

			$id = $this->factory->post->create( $data );

			// Post meta
			$metadata = $post['metadata'];

			if( !empty( $metadata ) ) {
				foreach( $metadata as $meta_key => $meta_val ) {
					update_post_meta( $id, $meta_key, $meta_val );
				}
			}

			// Load children
			$children = $post['children'];
			if( !empty( $children ) ) {
				$this->load_test_posts( $children, $id );
			}

			// Cache internally for access during tests
			$this->posts[$key] = $id;

		}

	}

	public function create_test_group() {

		$section_editor = $this->factory->user->create(array('role'=>'section_editor','user_email'=>'wptest3@bu.edu'));
		$this->users['section_editor'] = $section_editor;

		$allowed = array( $this->posts['child'], $this->posts['grandchild_one'], $this->posts['grandchild_two'] );

		$groupdata = array(
			'name' => 'Test group',
			'description' => 'Test description',
			'users' => array($this->users['section_editor']),
			'perms' => array(
				'page' => array( 'allowed' => $allowed )
			)
		);

		$group = BU_Edit_Groups::get_instance()->add_group( $groupdata );

		$this->section_groups = array( 'test' => $group );

	}

	public function test_can_publish_top_level() {

		wp_set_current_user( $this->users['admin'] );

		$this->assertTrue( $this->navman->can_publish_top_level() );

		// Don't allow top level posts
		$this->plugin->update_settings(array('allow_top'=>false));

		$this->assertFalse( $this->navman->can_publish_top_level() );

		// @todo section editor integration

	}

	public function test_can_edit() {

		wp_set_current_user( $this->users['admin'] );

		$this->assertTrue( $this->navman->can_edit( $this->posts['parent'] ) );
		$this->assertTrue( $this->navman->can_edit( $this->posts['child'] ) );
		$this->assertTrue( $this->navman->can_edit( $this->posts['grandchild_one'] ) );
		$this->assertTrue( $this->navman->can_edit( $this->posts['grandchild_two'] ) );
		$this->assertTrue( $this->navman->can_edit( $this->posts['google'] ) );

		wp_set_current_user( $this->users['contrib'] );

		$this->assertFalse( $this->navman->can_edit( $this->posts['parent'] ) );
		$this->assertFalse( $this->navman->can_edit( $this->posts['child'] ) );
		$this->assertFalse( $this->navman->can_edit( $this->posts['grandchild_one'] ) );
		$this->assertFalse( $this->navman->can_edit( $this->posts['grandchild_two'] ) );
		$this->assertFalse( $this->navman->can_edit( $this->posts['google'] ) );

		wp_set_current_user( $this->users['section_editor'] );

		$this->assertTrue( $this->navman->can_edit( $this->posts['child'] ) );
		$this->assertTrue( $this->navman->can_edit( $this->posts['grandchild_one'] ) );
		$this->assertTrue( $this->navman->can_edit( $this->posts['grandchild_two'] ) );
		$this->assertFalse( $this->navman->can_edit( $this->posts['parent'] ) );
		$this->assertFalse( $this->navman->can_edit( $this->posts['google'] ) );
	}

	public function test_can_delete() {

		wp_set_current_user( $this->users['admin'] );

		$this->assertTrue( $this->navman->can_delete( $this->posts['parent'] ) );
		$this->assertTrue( $this->navman->can_delete( $this->posts['google'] ) );

		wp_set_current_user( $this->users['contrib'] );

		$this->assertFalse( $this->navman->can_delete( $this->posts['parent'] ) );
		$this->assertFalse( $this->navman->can_delete( $this->posts['google'] ) );

		wp_set_current_user( $this->users['section_editor'] );

		$this->assertTrue( $this->navman->can_delete( $this->posts['child'] ) );
		$this->assertTrue( $this->navman->can_delete( $this->posts['grandchild_one'] ) );
		$this->assertFalse( $this->navman->can_delete( $this->posts['parent'] ) );
		$this->assertFalse( $this->navman->can_delete( $this->posts['google'] ) );

	}

	public function test_can_place_in_section() {

		wp_set_current_user( $this->users['admin'] );

		// Simulate move to top level
		$post = get_post( $this->posts['grandchild_one'] );
		$post->post_parent = 0;

		$this->assertTrue( $this->navman->can_place_in_section( $post, $this->posts['child'] ) );

		// Don't allow top level posts
		$this->plugin->update_settings(array('allow_top'=>false));

		$this->assertFalse( $this->navman->can_place_in_section( $post, $this->posts['child'] ) );

		// Simulate previously in nav exception
		$this->assertTrue( $this->navman->can_place_in_section( $post, 0 ) );

		// Re-allow top level posts
		$this->plugin->update_settings(array('allow_top'=>true));

		// Coverage for section editor logic
		wp_set_current_user( $this->users['section_editor'] );

		// Simulate move to top level
		$post = get_post( $this->posts['grandchild_one'] );
		$post->post_parent = 0;

		// Top level moves deneid, regardless of allow top setting or previously in navigation condition
		$this->assertFalse( $this->navman->can_place_in_section( $post, $this->posts['child'] ) );
		$this->assertFalse( $this->navman->can_place_in_section( $post, 0 ) );

		// Simulate move under sibling (allowed) page
		$post->post_parent = $this->posts['grandchild_two'];
		$this->assertTrue( $this->navman->can_place_in_section( $post, $this->posts['child'] ) );

		// Simulate move under parent (denied) page
		$post->post_parent = $this->posts['parent'];
		$this->assertFalse( $this->navman->can_place_in_section( $post, $this->posts['child'] ) );

	}

	public function test_can_move() {

		wp_set_current_user( $this->users['admin'] );

		// Simulate move to top level
		$post = get_post( $this->posts['grandchild_one'] );
		$original = clone $post;

		$post->post_parent = 0;

		// Test different arguments
		$this->assertTrue( $this->navman->can_move( $post, $original ) );
		$this->assertTrue( $this->navman->can_move( $post->ID, $original ) );
		$this->assertTrue( $this->navman->can_move( $post->ID, $original->ID ) );
		$this->assertTrue( $this->navman->can_move( $post, $original->ID ) );

		// Don't allow top level posts
		$this->plugin->update_settings(array('allow_top'=>false));
		$this->assertFalse( $this->navman->can_move( $post, $original ) );

		// Fake original location was top level
		$original->post_parent = 0;
		$this->assertTrue( $this->navman->can_move( $post, $original ) );

		// @todo section editor integration

	}

	public function test_can_move_contrib() {

		wp_set_current_user( $this->users['contrib'] );

		// Simulate move to top level
		$post = get_post( $this->posts['grandchild_one'] );
		$original = clone $post;

		$post->post_parent = 0;

		// Test different arguments
		$this->assertFalse( $this->navman->can_move( $post, $original ) );
		$this->assertFalse( $this->navman->can_move( $post->ID, $original ) );
		$this->assertFalse( $this->navman->can_move( $post->ID, $original->ID ) );
		$this->assertFalse( $this->navman->can_move( $post, $original->ID ) );

		// Don't allow top level posts
		$this->plugin->update_settings(array('allow_top'=>false));
		$this->assertFalse( $this->navman->can_move( $post, $original ) );

		// Fake original location was top level
		$original->post_parent = 0;
		$this->assertFalse( $this->navman->can_move( $post, $original ) );

		// @todo section editor integration

	}

	public function test_process_deletions() {

		wp_set_current_user( $this->users['admin'] );

		// array of post ID's to delete
		$deletions = array( $this->posts['grandchild_two'], $this->posts['google'] );

		$result =$this->navman->process_deletions( $deletions );

		$this->assertNotInstanceOf( 'WP_Error', $result );

		$deleted_page = get_post( $this->posts['grandchild_two'] );
		$this->assertEquals( 'trash', $deleted_page->post_status );

		$deleted_link = get_post( $this->posts['google'] );
		$this->assertNull( $deleted_link );

		// @todo section editor integration

	}

	public function test_process_deletions_contrib() {

		wp_set_current_user( $this->users['contrib'] );

		// array of post ID's to delete
		$deletions = array( $this->posts['grandchild_two'], $this->posts['google'] );

		$result =$this->navman->process_deletions( $deletions );

		$this->assertInstanceOf( 'WP_Error', $result );

		$deleted_page = get_post( $this->posts['grandchild_two'] );
		$this->assertEquals( 'publish', $deleted_page->post_status );

		$deleted_link = get_post( $this->posts['google'] );
		$this->assertEquals( 'publish', $deleted_link->post_status );

		// @todo section editor integration

	}

	public function test_process_insertions() {

		wp_set_current_user( $this->users['admin'] );

		$link1 = array(
			'post_title' => 'Example Link 1',
			'post_type' => 'link',
			'post_content' => 'http://www.example.com',
			'post_status' => 'publish',
			'post_meta' => (object) array( 'bu_link_target' => 'new' ),
			'post_parent' => "0",
			'menu_order' => "1"
			);

		$link2 = array(
			'post_title' => 'Example Link 2',
			'post_type' => 'link',
			'post_content' => 'http://www.example2.com',
			'post_status' => 'publish',
			'post_meta' => (object) array( 'bu_link_target' => 'same' ),
			'post_parent' => $this->posts['child'],
			'menu_order' => 1
			);

		// array of post objects to insert
		$insertions = array( 'post-new-0' => (object) $link1, 'post-new-1' => (object) $link2 );

		$result = $this->navman->process_insertions( $insertions );

		$this->assertTrue( $result );

		// @todo get newly created links by title

		// @todo section editor integration
	}

	public function test_process_updates() {

		wp_set_current_user( $this->users['admin'] );

		$updates = array(
			$this->posts['google'] => (object) array(
				'ID' => $this->posts['google'],
				'post_title' => 'Bing',
				'post_type' => 'link',
				'post_content' => 'http://www.bing.com',
				'post_meta' => (object) array( 'bu_link_target' => 'same' ),
				)
			);

		$result = $this->navman->process_updates( $updates );

		$this->assertTrue( $result );

		$updated = get_post( $this->posts['google'] );

		$this->assertEquals( 'Bing', $updated->post_title );
		$this->assertEquals( 'http://www.bing.com', $updated->post_content );

		$target = get_post_meta( $updated->ID, 'bu_link_target', true );

		$this->assertEquals( 'same', $target );

	}

	public function test_process_moves() {

		wp_set_current_user( $this->users['admin'] );

		$moves = array(
			$this->posts['hidden'] => (object) array(
				'ID' => $this->posts['hidden'],
				'post_type' => 'page',
				'post_status' => 'publish',
				'post_parent' => $this->posts['child'],
				'menu_order' => 1
				),
			$this->posts['child'] => (object) array(
				'ID' => $this->posts['child'],
				'post_type' => 'page',
				'post_status' => 'publish',
				'post_parent' => 0,
				'menu_order' => 2
				),
			$this->posts['edit'] => (object) array(
				'ID' => $this->posts['edit'],
				'post_type' => 'page',
				'post_status' => 'publish',
				'post_parent' => $this->posts['last_page'],
				'menu_order' => 1
				)
			);

		$result = $this->navman->process_moves( $moves );

		$this->assertTrue( $result );

		$hidden = get_post( $this->posts['hidden'] );
		$child = get_post( $this->posts['child'] );
		$edit = get_post( $this->posts['edit'] );

		$this->assertEquals( $this->posts['child'], $hidden->post_parent );
		$this->assertEquals( 1, $hidden->menu_order );

		$this->assertEquals( 0, $child->post_parent );
		$this->assertEquals( 2, $child->menu_order );

		$this->assertEquals( $this->posts['last_page'], $edit->post_parent );
		$this->assertEquals( 1, $edit->menu_order );

	}

	/**
	 * Put it all together
	 *
	 * @group bu-navigation-navman-save
	 * @group bu-cache
	 */
	public function test_save() {

		wp_set_current_user( $this->users['admin'] );

		// Construct moves $_POST array from JSON file
		$updates_json = file_get_contents( dirname(__FILE__) . '/data/test_navman_save.json');
		$updates = $this->_process_save_file( $updates_json );

		$_POST['bu_navman_save'] = 'Publish Changes';
		$_POST['navman-moves'] = json_encode($updates['navman-moves']);
		$_POST['navman-inserts'] = json_encode($updates['navman-inserts']);
		$_POST['navman-updates'] = json_encode($updates['navman-updates']);
		$_POST['navman-deletions'] = json_encode($updates['navman-deletions']);

		// Run the save
		$this->navman->save();

		// Inserts
		$new_link = array_pop(get_posts(array('s'=>'New Link','post_type'=>'link')));
		$this->assertEquals( 'New Link', $new_link->post_title );
		$this->assertEquals( 'http://newlink.com', $new_link->post_content );
		$this->assertEquals( $this->posts['last_page'], $new_link->post_parent );
		$this->assertEquals( 1, $new_link->menu_order );
		$this->assertEquals( 'new', get_post_meta( $new_link->ID, 'bu_link_target', true ) );

		$new_link_two = array_pop(get_posts(array('s'=>'Top Level Link','post_type'=>'link')));
		$this->assertEquals( 'Top Level Link', $new_link_two->post_title );
		$this->assertEquals( 'http://toplevel.com', $new_link_two->post_content );
		$this->assertEquals( 0, $new_link_two->post_parent );
		$this->assertEquals( 4, $new_link_two->menu_order );
		$this->assertEquals( 'same', get_post_meta( $new_link_two->ID, 'bu_link_target', true ) );

		// Updates
		$this->assertEquals( 'Bing', get_post($this->posts['google'])->post_title );
		$this->assertEquals( 'http://www.bing.com', get_post($this->posts['google'])->post_content );
		$this->assertEquals( 'same', get_post_meta($this->posts['google'], 'bu_link_target', true ) );

		// Deletions
		$this->assertEquals( 'trash', get_post($this->posts['hidden'])->post_status );

		// Moves
		$this->assertEquals( 0, get_post($this->posts['grandchild_one'])->post_parent);
		$this->assertEquals( 1, get_post($this->posts['grandchild_one'])->menu_order);
		$this->assertEquals( $this->posts['child'], get_post($this->posts['edit'])->post_parent);
		$this->assertEquals( 1, get_post($this->posts['edit'])->menu_order);

		// Reordering
		$this->assertEquals( 1, get_post($this->posts['grandchild_one'])->menu_order);
		$this->assertEquals( 2, get_post($this->posts['parent'])->menu_order);
		$this->assertEquals( 3, get_post($this->posts['google'])->menu_order);
		/* new link two should be 4 */
		$this->assertEquals( 5, get_post($this->posts['last_page'])->menu_order);

		$this->assertEquals( 1, get_post($this->posts['edit'])->menu_order);
		$this->assertEquals( 2, get_post($this->posts['grandchild_two'])->menu_order);

	}

	/**
	 * Reads in a JSON file formatted to mock a navman $_POST submission
	 *
	 * Since we don't know the post ID's until they are created in self::setUp(),
	 * the json file contains a special template structure for referencing
	 * post ID's by the keys used in $this->posts.
	 *
	 * For instance:
	 * 	%grandchild_one% => $this->posts['grandchild_one']
	 *
	 * Those keys are determined by the json file used to load initial post data:
	 * 	tests/data/test_posts.json
	 */
	protected function _process_save_file( $input ) {

		$save = (array) json_decode( stripslashes( $input ) );

		$output = array(
			'navman-moves' => array(),
			'navman-inserts' => array(),
			'navman-updates' => array(),
			'navman-deletions' => array()
			);

		$moves = (array) $save['navman-moves'];
		$inserts = (array) $save['navman-inserts'];
		$updates = (array) $save['navman-updates'];
		$deletions = $save['navman-deletions'];

		foreach( $moves as $id => $move ) {
			// Convert ID fields
			$id = $this->_convert_post_id( $id );
			$move->ID = $this->_convert_post_id( $move->ID );
			$move->post_parent = $this->_convert_post_id( $move->post_parent );
			$output['navman-moves'][$id] = $move;
		}
		foreach( $inserts as $id => $insert ) {
			// Convert ID fields
			$id = $this->_convert_post_id( $id );
			$insert->post_parent = $this->_convert_post_id( $insert->post_parent );
			$output['navman-inserts'][$id] = $insert;
		}
		foreach( $updates as $id => $update ) {
			// Convert ID fields
			$id = $this->_convert_post_id( $id );
			$update->ID = $this->_convert_post_id( $update->ID );
			$update->post_parent = $this->_convert_post_id( $update->post_parent );
			$output['navman-updates'][$id] = $update;
		}
		foreach( $deletions as $id ) {
			// Convert ID fields
			$id = $this->_convert_post_id( $id );
			array_push( $output['navman-deletions'], $id );
		}

		return $output;

	}

	protected function _convert_post_id( $id ) {
		return preg_replace_callback( '|%(.*?)%|', array( $this, '_replace_id' ), $id );
	}

	protected function _replace_id( $matches ) {
		if ( ! empty( $matches[1] ) && array_key_exists( $matches[1], $this->posts ) ) {
			return $this->posts[$matches[1]];
		}
		return $matches[0];
	}

}