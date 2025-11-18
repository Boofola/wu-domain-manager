<?php
/**
 * OpenSRS Pricing Class
 */
class WU_OpenSRS_Pricing {
	
	/**
	 * Update pricing from OpenSRS API
	 */
	public static function update_pricing_cron() {
		if ( ! wu_get_setting( 'opensrs_enabled', false ) ) {
			return;
		}
		
		$pricing_data = OpenSRS_API::get_pricing();
		
		if ( is_wp_error( $pricing_data ) ) {
			error_log( 'OpenSRS Pricing Update Failed: ' . $pricing_data->get_error_message() );
			return;
		}
		
		self::store_pricing( $pricing_data );
	}
	
	/**
	 * Store pricing in database
	 *
	 * @param array $pricing_data Pricing data from API
	 */
	private static function store_pricing( $pricing_data ) {
		global $wpdb;
		
		$table = $wpdb->prefix . 'wu_opensrs_pricing';
		
		// Parse and store pricing data
		// This is a simplified version - actual implementation depends on API response format
		foreach ( $pricing_data as $tld => $prices ) {
			$wpdb->replace(
				$table,
				array(
					'tld' => $tld,
					'registration_price' => $prices['registration'],
					'renewal_price' => $prices['renewal'],
					'transfer_price' => $prices['transfer'],
					'whois_privacy_price' => isset( $prices['privacy'] ) ? $prices['privacy'] : 0,
					'currency' => 'USD',
					'last_updated' => current_time( 'mysql' ),
				),
				array( '%s', '%f', '%f', '%f', '%f', '%s', '%s' )
			);
		}
	}
	
	/**
	 * Get price for a TLD
	 *
	 * @param string $tld TLD (e.g., 'com', 'net')
	 * @param string $type Type of price (registration, renewal, transfer)
	 * @return float|null
	 */
	public static function get_price( $tld, $type = 'registration' ) {
		global $wpdb;
		
		$table = $wpdb->prefix . 'wu_opensrs_pricing';
		$column = $type . '_price';
		
		$price = $wpdb->get_var( $wpdb->prepare(
			"SELECT $column FROM $table WHERE tld = %s",
			$tld
		) );
		
		return $price ? (float) $price : null;
	}
}
