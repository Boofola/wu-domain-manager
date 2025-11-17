<?php
/**
 * OpenSRS Renewals Manager
 * 
 * File: includes/class-opensrs-renewals.php
 *
 * @package WU_OpenSRS
 */

// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * OpenSRS Renewals Class
 */
class WU_OpenSRS_Renewals {
	
	/**
	 * Initialize hooks
	 */
	public static function init() {
		// Register cron jobs
		add_action( 'wu_opensrs_check_renewals', array( __CLASS__, 'check_renewals_cron' ) );
		add_action( 'wu_opensrs_check_expirations', array( __CLASS__, 'check_expirations_cron' ) );
		
		// Auto-renewal processor
		add_action( 'wu_opensrs_process_auto_renewals', array( __CLASS__, 'process_auto_renewals' ) );
	}
	
	/**
	 * Check renewal status for all domains (weekly cron)
	 */
	public static function check_renewals_cron() {
		if ( ! WU_OpenSRS_Settings::is_enabled() ) {
			return;
		}
		
		global $wpdb;
		$table = $wpdb->prefix . 'wu_opensrs_domains';
		
		// Get all active domains
		$domains = $wpdb->get_results(
			"SELECT * FROM $table WHERE status = 'active' ORDER BY expiration_date ASC"
		);
		
		foreach ( $domains as $domain ) {
			// Get domain info from OpenSRS
			$info = WU_OpenSRS_API::get_domain_info( $domain->domain_name );
			
			if ( is_wp_error( $info ) ) {
				error_log( sprintf(
					'Failed to check renewal for domain %s: %s',
					$domain->domain_name,
					$info->get_error_message()
				) );
				continue;
			}
			
			if ( 1 === $info['is_success'] && isset( $info['attributes']['expiry_date'] ) ) {
                $expiry_date = date( 'Y-m-d H:i:s', strtotime( $info['attributes']['expiry_date'] ) );
                
                // Update expiration date in database
                $wpdb->update(
                    $table,
                    array( 'expiration_date' => $expiry_date ),
                    array( 'id' => $domain->id ),
                    array( '%s' ),
                    array( '%d' )
                );
            }