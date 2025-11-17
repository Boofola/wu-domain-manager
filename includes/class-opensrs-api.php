<?php
/**
 * OpenSRS API Handler
 * 
 * File: includes/class-opensrs-api.php
 *
 * @package WU_OpenSRS
 */

// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * OpenSRS API Class
 */
class WU_OpenSRS_API {
	
	/**
	 * API endpoints
	 */
	const TEST_ENDPOINT = 'https://horizon.opensrs.net:55443';
	const LIVE_ENDPOINT = 'https://rr-n1-tor.opensrs.net:55443';
	
	/**
	 * Get API endpoint based on mode
	 *
	 * @return string
	 */
	private static function get_endpoint() {
		$mode = wu_get_setting( 'opensrs_mode', 'test' );
		return ( 'live' === $mode ) ? self::LIVE_ENDPOINT : self::TEST_ENDPOINT;
	}
	
	/**
	 * Generate MD5 signature for authentication
	 *
	 * @param string $xml XML content
	 * @return string
	 */
	private static function generate_signature( $xml ) {
		$api_key = wu_get_setting( 'opensrs_api_key', '' );
		$md5_signature = md5( md5( $xml . $api_key ) . $api_key );
		return $md5_signature;
	}
	
	/**
	 * Make API request
	 *
	 * @param string $xml XML request
	 * @return array|WP_Error
	 */
	private static function make_request( $xml ) {
		$username = wu_get_setting( 'opensrs_username', '' );
		$signature = self::generate_signature( $xml );
		$endpoint = self::get_endpoint();
		
		$headers = array(
			'Content-Type' => 'text/xml',
			'X-Username' => $username,
			'X-Signature' => $signature,
		);
		
		$response = wp_remote_post( $endpoint, array(
			'body' => $xml,
			'headers' => $headers,
			'timeout' => 30,
			'sslverify' => true,
		) );
		
		if ( is_wp_error( $response ) ) {
			return $response;
		}
		
		$body = wp_remote_retrieve_body( $response );
		$parsed = self::parse_xml_response( $body );
		
		return $parsed;
	}
	
	/**
	 * Parse XML response
	 *
	 * @param string $xml XML response
	 * @return array
	 */
	private static function parse_xml_response( $xml ) {
		if ( empty( $xml ) ) {
			return array(
				'is_success' => 0,
				'response_code' => '',
				'response_text' => 'Empty response from API',
				'attributes' => array(),
			);
		}
		
		libxml_use_internal_errors( true );
		$doc = new \DOMDocument();
		
		if ( ! $doc->loadXML( $xml ) ) {
			return array(
				'is_success' => 0,
				'response_code' => '',
				'response_text' => 'Invalid XML response',
				'attributes' => array(),
			);
		}
		
		$result = array(
			'is_success' => 0,
			'response_code' => '',
			'response_text' => '',
			'attributes' => array(),
		);
		
		$xpath = new \DOMXPath( $doc );
		
		// Get success status
		$is_success = $xpath->query( '//item[@key="is_success"]' );
		if ( $is_success->length > 0 ) {
			$result['is_success'] = (int) $is_success->item(0)->nodeValue;
		}
		
		// Get response code
		$response_code = $xpath->query( '//item[@key="response_code"]' );
		if ( $response_code->length > 0 ) {
			$result['response_code'] = $response_code->item(0)->nodeValue;
		}
		
		// Get response text
		$response_text = $xpath->query( '//item[@key="response_text"]' );
		if ( $response_text->length > 0 ) {
			$result['response_text'] = $response_text->item(0)->nodeValue;
		}
		
		// Get attributes
		$attributes = $xpath->query( '//item[@key="attributes"]/dt_assoc/item' );
		foreach ( $attributes as $attr ) {
			$key = $attr->getAttribute( 'key' );
			$value = $attr->nodeValue;
			$result['attributes'][ $key ] = $value;
		}
		
		return $result;
	}
	
	/**
	 * Build XML request
	 *
	 * @param string $object Object type
	 * @param string $action Action type
	 * @param array  $attributes Request attributes
	 * @return string
	 */
	private static function build_xml_request( $object, $action, $attributes = array() ) {
		$xml = '<?xml version="1.0" encoding="UTF-8" standalone="no" ?>';
		$xml .= '<!DOCTYPE OPS_envelope SYSTEM "ops.dtd">';
		$xml .= '<OPS_envelope>';
		$xml .= '<header><version>0.9</version></header>';
		$xml .= '<body><data_block><dt_assoc>';
		$xml .= '<item key="protocol">XCP</item>';
		$xml .= '<item key="action">' . esc_xml( $action ) . '</item>';
		$xml .= '<item key="object">' . esc_xml( $object ) . '</item>';
		
		if ( ! empty( $attributes ) ) {
			$xml .= '<item key="attributes"><dt_assoc>';
			foreach ( $attributes as $key => $value ) {
				if ( is_array( $value ) ) {
					$xml .= '<item key="' . esc_xml( $key ) . '"><dt_array>';
					foreach ( $value as $item ) {
						$xml .= '<item>' . esc_xml( $item ) . '</item>';
					}
					$xml .= '</dt_array></item>';
				} else {
					$xml .= '<item key="' . esc_xml( $key ) . '">' . esc_xml( $value ) . '</item>';
				}
			}
			$xml .= '</dt_assoc></item>';
		}
		
		$xml .= '</dt_assoc></data_block></body>';
		$xml .= '</OPS_envelope>';
		
		return $xml;
	}
	
	/**
	 * Test API connection
	 *
	 * @return array
	 */
	public static function test_connection() {
		$xml = self::build_xml_request( 'BALANCE', 'GET' );
		$response = self::make_request( $xml );
		
		if ( is_wp_error( $response ) ) {
			return array(
				'success' => false,
				'message' => $response->get_error_message(),
			);
		}
		
		if ( 1 === $response['is_success'] ) {
			return array(
				'success' => true,
				'message' => __( 'Connection successful!', 'wu-opensrs' ),
			);
		}
		
		return array(
			'success' => false,
			'message' => isset( $response['response_text'] ) ? $response['response_text'] : __( 'Connection failed', 'wu-opensrs' ),
		);
	}
	
	/**
	 * 1. Domain availability lookup
	 *
	 * @param string $domain Domain name to check
	 * @return array|WP_Error
	 */
	public static function lookup_domain( $domain ) {
		$xml = self::build_xml_request( 'DOMAIN', 'LOOKUP', array(
			'domain' => $domain,
		) );
		
		return self::make_request( $xml );
	}
	
	/**
	 * 2. Register domain
	 *
	 * @param array $data Domain registration data
	 * @return array|WP_Error
	 */
	public static function register_domain( $data ) {
		$attributes = array(
			'domain' => $data['domain'],
			'period' => isset( $data['period'] ) ? $data['period'] : 1,
			'custom_nameservers' => isset( $data['custom_nameservers'] ) ? $data['custom_nameservers'] : 0,
			'reg_username' => $data['username'],
			'reg_password' => $data['password'],
			'handle' => 'process',
		);
		
		// Add contact information
		if ( isset( $data['contact'] ) ) {
			$attributes = array_merge( $attributes, $data['contact'] );
		}
		
		$xml = self::build_xml_request( 'DOMAIN', 'SW_REGISTER', $attributes );
		
		return self::make_request( $xml );
	}
	
	/**
	 * 3. Renew domain
	 *
	 * @param string $domain Domain name
	 * @param int    $period Renewal period in years
	 * @return array|WP_Error
	 */
	public static function renew_domain( $domain, $period = 1 ) {
		$xml = self::build_xml_request( 'DOMAIN', 'RENEW', array(
			'domain' => $domain,
			'period' => $period,
			'handle' => 'process',
		) );
		
		return self::make_request( $xml );
	}
	
	/**
	 * 4. Transfer domain
	 *
	 * @param array $data Transfer data
	 * @return array|WP_Error
	 */
	public static function transfer_domain( $data ) {
		$attributes = array(
			'domain' => $data['domain'],
			'auth_info' => $data['auth_code'],
			'period' => isset( $data['period'] ) ? $data['period'] : 1,
			'reg_username' => $data['username'],
			'reg_password' => $data['password'],
		);
		
		$xml = self::build_xml_request( 'DOMAIN', 'TRANSFER', $attributes );
		
		return self::make_request( $xml );
	}
	
	/**
	 * 5. Manage DNS/Nameservers
	 *
	 * @param string $domain Domain name
	 * @param array  $nameservers Array of nameservers
	 * @return array|WP_Error
	 */
	public static function update_nameservers( $domain, $nameservers ) {
		$attributes = array(
			'domain' => $domain,
			'op_type' => 'assign',
		);
		
		// Add nameservers
		for ( $i = 1; $i <= count( $nameservers ); $i++ ) {
			$attributes[ 'ns' . $i ] = $nameservers[ $i - 1 ];
		}
		
		$xml = self::build_xml_request( 'DOMAIN', 'MODIFY', $attributes );
		
		return self::make_request( $xml );
	}
	
	/**
	 * 6. Enable/Disable WHOIS privacy
	 *
	 * @param string $domain Domain name
	 * @param bool   $enable Enable or disable privacy
	 * @return array|WP_Error
	 */
	public static function toggle_whois_privacy( $domain, $enable = true ) {
		$state = $enable ? 'enable' : 'disable';
		
		$xml = self::build_xml_request( 'DOMAIN', 'MODIFY', array(
			'domain' => $domain,
			'data' => $state . '_whois_privacy',
		) );
		
		return self::make_request( $xml );
	}
	
	/**
	 * 7. Lock/Unlock domain
	 *
	 * @param string $domain Domain name
	 * @param bool   $lock Lock or unlock domain
	 * @return array|WP_Error
	 */
	public static function toggle_domain_lock( $domain, $lock = true ) {
		$state = $lock ? 'lock' : 'unlock';
		
		$xml = self::build_xml_request( 'DOMAIN', 'MODIFY', array(
			'domain' => $domain,
			'data' => $state,
		) );
		
		return self::make_request( $xml );
	}
	
	/**
	 * 8. Update contact information
	 *
	 * @param string $domain Domain name
	 * @param array  $contact_data Contact information
	 * @return array|WP_Error
	 */
	public static function update_contact_info( $domain, $contact_data ) {
		$attributes = array(
			'domain' => $domain,
			'contact_set' => 'all',
		);
		
		$attributes = array_merge( $attributes, $contact_data );
		
		$xml = self::build_xml_request( 'DOMAIN', 'MODIFY', $attributes );
		
		return self::make_request( $xml );
	}
	
	/**
	 * Get domain info
	 *
	 * @param string $domain Domain name
	 * @return array|WP_Error
	 */
	public static function get_domain_info( $domain ) {
		$xml = self::build_xml_request( 'DOMAIN', 'GET', array(
			'domain' => $domain,
			'type' => 'all_info',
		) );
		
		return self::make_request( $xml );
	}
	
	/**
	 * Get pricing for TLDs
	 *
	 * @return array|WP_Error
	 */
	public static function get_pricing() {
		$xml = self::build_xml_request( 'PRICE', 'GET_PRICE', array(
			'product' => 'domain',
		) );
		
		$response = self::make_request( $xml );
		
		if ( is_wp_error( $response ) ) {
			return $response;
		}
		
		// Parse pricing data from response
		// Note: The actual structure depends on OpenSRS API response format
		// This is a simplified version
		$pricing_data = array();
		
		if ( isset( $response['attributes'] ) && is_array( $response['attributes'] ) ) {
			foreach ( $response['attributes'] as $tld => $prices ) {
				if ( is_array( $prices ) ) {
					$pricing_data[ $tld ] = $prices;
				}
			}
		}
		
		return $pricing_data;
	}
	
	/**
	 * Get available TLDs list
	 *
	 * @return array|WP_Error
	 */
	public static function get_tld_list() {
		$xml = self::build_xml_request( 'DOMAIN', 'GET_TLD_LIST' );
		
		$response = self::make_request( $xml );
		
		if ( is_wp_error( $response ) ) {
			return $response;
		}
		
		$tlds = array();
		
		if ( isset( $response['attributes']['tld_list'] ) ) {
			$tlds = $response['attributes']['tld_list'];
		}
		
		return $tlds;
	}
}