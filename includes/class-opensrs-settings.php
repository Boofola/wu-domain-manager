<?php
/**
 * OpenSRS Settings
 */

class WU_OpenSRS_Settings {
	
	private static $instance = null;
	
	public static function get_instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}
	
	private function __construct() {
		add_action( 'init', array( $this, 'register_settings' ) );
	}
	
	public function register_settings() {
		// Register settings section
		wu_register_settings_section( 'opensrs', array(
			'title' => __( 'OpenSRS Domain Manager', 'wu-opensrs' ),
			'desc'  => __( 'Configure OpenSRS domain registration and management.', 'wu-opensrs' ),
			'icon'  => 'dashicons-admin-site-alt3',
		) );
		
		// Enable/Disable
		wu_register_settings_field( 'opensrs', 'opensrs_enabled', array(
			'title'   => __( 'Enable OpenSRS', 'wu-opensrs' ),
			'desc'    => __( 'Enable domain registration through OpenSRS', 'wu-opensrs' ),
			'type'    => 'toggle',
			'default' => false,
		) );
		
		// API Mode
		wu_register_settings_field( 'opensrs', 'opensrs_mode', array(
			'title'   => __( 'API Mode', 'wu-opensrs' ),
			'desc'    => __( 'Test mode or live production', 'wu-opensrs' ),
			'type'    => 'select',
			'options' => array(
				'test' => __( 'Test/Sandbox', 'wu-opensrs' ),
				'live' => __( 'Live Production', 'wu-opensrs' ),
			),
			'default' => 'test',
		) );
		
		// Username
		wu_register_settings_field( 'opensrs', 'opensrs_username', array(
			'title'       => __( 'Reseller Username', 'wu-opensrs' ),
			'desc'        => __( 'Your OpenSRS reseller username', 'wu-opensrs' ),
			'type'        => 'text',
			'placeholder' => 'your_username',
		) );
		
		// API Key
		wu_register_settings_field( 'opensrs', 'opensrs_api_key', array(
			'title'       => __( 'API Key', 'wu-opensrs' ),
			'desc'        => __( 'Your OpenSRS API key', 'wu-opensrs' ),
			'type'        => 'password',
			'placeholder' => '••••••••',
		) );
		
		// Add custom import/refresh section
		do_action( 'wu_opensrs_settings_after_fields' );
	}
}
