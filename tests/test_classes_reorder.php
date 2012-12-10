<?php

/**
 * BU Navigation - Classes - Reorder
 *
 * @group bu
 * @group bu-navigation
 * @group bu-navigation-reorder
 */
class BU_Navigation_Reorder_Tests extends WP_UnitTestCase {

	public $plugin;
	public $posts;

	public function setUp() {

		parent::setUp();

		$this->plugin = new BU_Navigation_Plugin();
		$this->plugin->load_admin();


		register_post_type( 'link', array('name' => 'Link') );

		// Setup posts
		$posts_json = file_get_contents( dirname(__FILE__) . '/data/test_posts.json');
		$posts = json_decode($posts_json, true);
		$this->load_test_posts( $posts );

	}

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

	public function test_construct() {

		$tracker = new BU_Navigation_Reorder_Tracker('page');

		$this->assertInternalType('array', $tracker->post_types);
		$this->assertContains('page',$tracker->post_types);
		$this->assertContains('link',$tracker->post_types);

	}

	public function test_mark_post_as_moved() {

		$post = get_post( $this->posts['child'] );
		$post->post_parent = $this->posts['edit'];	// new parent

		$tracker = new BU_Navigation_Reorder_Tracker('page');
		$tracker->mark_post_as_moved( $post );

		$this->assertInternalType( 'array', $tracker->already_moved );
		$this->assertArrayHasKey( $post->post_parent, $tracker->already_moved );
		$this->assertArrayHasKey( 'ids', $tracker->already_moved[$post->post_parent] );
		$this->assertArrayHasKey( 'positions', $tracker->already_moved[$post->post_parent] );
		$this->assertContains( $post->ID, $tracker->already_moved[$post->post_parent]['ids'] );
		$this->assertContains( $post->menu_order, $tracker->already_moved[$post->post_parent]['positions'] );

	}


	public function test_mark_section_for_reordering() {

		$post = get_post( $this->posts['parent'] );

		$tracker = new BU_Navigation_Reorder_Tracker('page');
		$tracker->mark_section_for_reordering( $post->ID );

		$this->assertInternalType( 'array', $tracker->already_moved );
		$this->assertArrayHasKey( $post->ID, $tracker->already_moved );
		$this->assertArrayHasKey( 'ids', $tracker->already_moved[$post->ID] );
		$this->assertArrayHasKey( 'positions', $tracker->already_moved[$post->ID] );

	}

	public function test_post_already_moved() {

		$tracker = new BU_Navigation_Reorder_Tracker('page');

		// Check against post that has not moved
		$parent = get_post( $this->posts['parent'] );
		$this->assertFalse( $tracker->post_already_moved( $parent ) );
		$this->assertFalse( $tracker->post_already_moved( $parent->ID ) ); // check pass by ID

		// Check against post that has moved
		$post = get_post( $this->posts['child'] );
		wp_update_post(array('ID'=>$post->ID,'post_parent'=>$this->posts['edit'],'menu_order'=>1));
		$moved = get_post( $this->posts['child'] );
		$tracker->mark_post_as_moved( $moved );

		$this->assertTrue( $tracker->post_already_moved( $moved ) );
		$this->assertTrue( $tracker->post_already_moved( $moved->ID ) ); // check pass by ID

	}

	public function test_position_already_set() {

		$tracker = new BU_Navigation_Reorder_Tracker('page');

		// Check against post that has moved
		$post = get_post( $this->posts['grandchild_one'] );
		wp_update_post(array('ID'=>$post->ID,'menu_order'=>2));
		$moved = get_post( $this->posts['grandchild_one'] );

		$tracker->mark_post_as_moved( $moved );

		$this->assertTrue( $tracker->position_already_set( 2, $this->posts['child'] ) );
		$this->assertFalse( $tracker->position_already_set( 1, $this->posts['child'] ) );

	}

	public function test_has_moves() {

		$tracker = new BU_Navigation_Reorder_Tracker('page');

		$this->assertFalse( $tracker->has_moves() );

		// Check against post that has moved
		$post = get_post( $this->posts['grandchild_one'] );
		wp_update_post(array('ID'=>$post->ID,'menu_order'=>2));
		$moved = get_post( $this->posts['grandchild_one'] );
		$tracker->mark_post_as_moved( $moved );

		$this->assertTrue( $tracker->has_moves() );

	}

	/**
	 * @group bu-cache
	 */
	public function test_run() {

		// Before state:
		$this->assertEquals( $this->posts['parent'], get_post($this->posts['child'])->post_parent );
		$this->assertEquals( $this->posts['child'], get_post($this->posts['grandchild_one'])->post_parent );
		$this->assertEquals( 1, get_post($this->posts['grandchild_one'])->menu_order );
		$this->assertEquals( 2, get_post($this->posts['grandchild_two'])->menu_order );

		$this->assertEquals( 0, get_post($this->posts['hidden'])->post_parent );
		$this->assertEquals( 1, get_post($this->posts['parent'])->menu_order ); // should have been reordered
		$this->assertEquals( 2, get_post($this->posts['hidden'])->menu_order );
		$this->assertEquals( 3, get_post($this->posts['edit'])->menu_order ); // should have been reordered
		$this->assertEquals( 4, get_post($this->posts['google'])->menu_order ); // should have been reordered
		$this->assertEquals( 5, get_post($this->posts['last_page'])->menu_order ); // should have been reordered

		$tracker = new BU_Navigation_Reorder_Tracker('page');

		wp_update_post(array('ID'=>$this->posts['hidden'],'post_parent'=>$this->posts['child'],'menu_order'=>1));
		wp_update_post(array('ID'=>$this->posts['child'],'post_parent'=>0,'menu_order'=>1));
		wp_delete_post($this->posts['grandchild_two'], true);

		$move_one = get_post($this->posts['hidden']);
		$move_two = get_post($this->posts['child']);

		// Mark two posts as moved, reorder section that contained deleted child
		$tracker->mark_post_as_moved($move_one);
		$tracker->mark_post_as_moved($move_two);
		$tracker->mark_section_for_reordering( $this->posts['child'] );

		// Perform reordering
		$tracker->run();

		$this->assertEquals( $this->posts['child'], get_post($this->posts['hidden'])->post_parent );
		$this->assertEquals( 1, get_post($this->posts['hidden'])->menu_order );
		$this->assertEquals( 2, get_post($this->posts['grandchild_one'])->menu_order ); // should have been reordered
		$this->assertEquals( 0, get_post($this->posts['child'])->post_parent );
		$this->assertEquals( 1, get_post($this->posts['child'])->menu_order );
		$this->assertEquals( 2, get_post($this->posts['parent'])->menu_order ); // should have been reordered
		$this->assertEquals( 3, get_post($this->posts['edit'])->menu_order ); // should have been reordered
		$this->assertEquals( 4, get_post($this->posts['google'])->menu_order ); // should have been reordered
		$this->assertEquals( 5, get_post($this->posts['last_page'])->menu_order ); // should have been reordered

	}

}