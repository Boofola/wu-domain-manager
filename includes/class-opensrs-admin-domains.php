<?php
// File: includes/class-opensrs-admin-domains.php

// Load WP_List_Table
if ( ! class_exists( 'WP_List_Table' ) ) {
	require_once ABSPATH . 'wp-admin/includes/class-wp-list-table.php';
}

class WU_OpenSRS_Admin_Domains {
	
	private static $instance = null;
	
	public static function get_instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}
	
	private function __construct() {
		// Remove menu registration; handled by new Domain Reseller Manager menu.
	}
	
	public function add_menu_page() {
		add_menu_page(
			__( 'Domains', 'wu-opensrs' ),
			__( 'Domains', 'wu-opensrs' ),
			'manage_network',
			'wu-opensrs-domains',
			array( $this, 'render_domains_page' ),
			'dashicons-rest-api',
			30
		);
	}
	
	public function render_domains_page() {
		?>
		<div class="wrap">
			<h1><?php esc_html_e( 'Domains', 'wu-opensrs' ); ?></h1>
			
			<!-- Add list table rendering here -->
			<p><?php esc_html_e( 'Domain management coming soon.', 'wu-opensrs' ); ?></p>
		</div>
		<?php
	}
}