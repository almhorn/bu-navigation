<?php

/**
 * Traditional unit tests for BU Navigation plugin
 * 
 * @group bu
 * @group bu-navigation
 */
class BU_Navigation_Settings_Test extends WP_UnitTestCase {

	public $plugin;

	public function setUp() {

		parent::setUp();

		// Store reference to navigation plugin instance
		$this->plugin = $GLOBALS['bu_navigation_plugin'];

	}

	/**
	 * @group bu-navigation-settings
	 */ 
	public function test_get_setting() {
		
		$this->assertTrue( $this->plugin->get_setting( 'display' ) );
		$this->assertEquals( BU_NAVIGATION_PRIMARY_MAX, $this->plugin->get_setting( 'max' ) );
		$this->assertTrue( $this->plugin->get_setting( 'dive' ) );
		$this->assertEquals( BU_NAVIGATION_PRIMARY_DEPTH, $this->plugin->get_setting( 'depth' ) );
		$this->assertFalse( $this->plugin->get_setting( 'allow_top' ) );

	}

	/**
	 * @group bu-navigation-settings
	 */ 
	public function test_get_settings() {

		$expected_settings = array(
			'display' => true,
			'max' => BU_NAVIGATION_PRIMARY_MAX,
			'dive' => true,
			'depth' => BU_NAVIGATION_PRIMARY_DEPTH,
			'allow_top' => false
			);

		$this->assertSame( $expected_settings, $this->plugin->get_settings() );

	}

	/**
	 * @group bu-navigation-settings
	 */ 
	public function test_update_settings() {

		$updates = array(
			'display' => false,
			'max' => 3,
			'dive' => false,
			'depth' => 2,
			'allow_top' => true
			);

		$this->plugin->update_settings( $updates );

		$this->assertSame( $updates, $this->plugin->get_settings() );

	}

}