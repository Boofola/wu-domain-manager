<?php
/**
 * NameCheap API Handler
 *
 * File: includes/class-namecheap-api.php
 *
 * @package WU_OpenSRS
 */

// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class WU_NameCheap_API {

    const SANDBOX_ENDPOINT = 'https://api.sandbox.namecheap.com/xml.response';
    const LIVE_ENDPOINT = 'https://api.namecheap.com/xml.response';

    private static function get_endpoint() {
        $mode = wu_get_setting( 'namecheap_mode', 'sandbox' );
        return ( 'live' === $mode ) ? self::LIVE_ENDPOINT : self::SANDBOX_ENDPOINT;
    }

    private static function build_query( $command, $params = array() ) {
        $query = array(
            'ApiUser'  => wu_get_setting( 'namecheap_api_user', '' ),
            'ApiKey'   => wu_get_setting( 'namecheap_api_key', '' ),
            'UserName' => wu_get_setting( 'namecheap_username', '' ),
            'ClientIp' => wu_get_setting( 'namecheap_client_ip', '' ),
            'Command'  => $command,
        );

        if ( ! empty( $params ) ) {
            $query = array_merge( $query, $params );
        }

        return $query;
    }

    private static function make_request( $command, $params = array() ) {
        $endpoint = self::get_endpoint();
        $query = self::build_query( $command, $params );

        $url = add_query_arg( $query, $endpoint );

        $response = wp_remote_get( $url, array( 'timeout' => 30, 'sslverify' => true ) );

        if ( is_wp_error( $response ) ) {
            return $response;
        }

        $body = wp_remote_retrieve_body( $response );

        if ( empty( $body ) ) {
            return new WP_Error( 'empty_response', 'Empty response from NameCheap API' );
        }

        libxml_use_internal_errors( true );
        $xml = simplexml_load_string( $body );
        if ( false === $xml ) {
            return new WP_Error( 'invalid_xml', 'Invalid XML response from NameCheap' );
        }

        // Convert to array-ish structure for compatibility with existing code
        $result = array(
            'is_success'    => ( (string) $xml['Status'] === 'OK' ) ? 1 : 0,
            'response_code' => '',
            'response_text' => '',
            'attributes'    => array(),
        );

        // If there's an Errors node, get first Error
        if ( isset( $xml->Errors ) && isset( $xml->Errors->Error ) ) {
            $result['response_text'] = (string) $xml->Errors->Error;
        }

        // Parse common responses for domain checks and info
        if ( isset( $xml->CommandResponse ) ) {
            // Domain check
            if ( isset( $xml->CommandResponse->DomainCheckResult ) ) {
                foreach ( $xml->CommandResponse->DomainCheckResult->Domain as $d ) {
                    $attrs = $d->attributes();
                    if ( isset( $attrs['Domain'] ) ) {
                        $domainName = (string) $attrs['Domain'];
                        $availableAttr = isset( $attrs['Available'] ) ? (string) $attrs['Available'] : null;
                        if ( null !== $availableAttr ) {
                            $result['attributes']['available'] = $availableAttr;
                        }
                        $result['attributes']['domain'] = $domainName;
                    }
                }
            }

            // GetInfo
            if ( isset( $xml->CommandResponse->DomainGetInfoResult ) ) {
                $info = $xml->CommandResponse->DomainGetInfoResult;
                if ( isset( $info->Domain ) ) {
                    $domainNode = $info->Domain;
                    if ( isset( $domainNode->Expires ) ) {
                        $result['attributes']['expiry_date'] = (string) $domainNode->Expires;
                    }
                }
            }
        }

        return $result;
    }

    public static function test_connection() {
        // Use a harmless command: namecheap.users.getPricing is not available; use namecheap.users.getInfo
        $res = self::make_request( 'namecheap.users.getInfo' );

        if ( is_wp_error( $res ) ) {
            return array( 'success' => false, 'message' => $res->get_error_message() );
        }

        if ( 1 === $res['is_success'] ) {
            return array( 'success' => true, 'message' => __( 'Connection successful!', 'wu-opensrs' ) );
        }

        return array( 'success' => false, 'message' => $res['response_text'] );
    }

    public static function lookup_domain( $domain ) {
        // Split domain into SLD and TLD
        $parts = explode( '.', $domain, 2 );
        if ( count( $parts ) !== 2 ) {
            return new WP_Error( 'invalid_domain', 'Invalid domain format' );
        }

        $sld = $parts[0];
        $tld = $parts[1];

        return self::make_request( 'namecheap.domains.check', array( 'DomainList' => $sld . '.' . $tld ) );
    }

    public static function register_domain( $data ) {
        if ( empty( $data['domain'] ) ) {
            return new WP_Error( 'missing_domain', 'Domain is required' );
        }

        // Minimal implementation: NameCheap registration requires many contact fields; caller should supply what is needed.
        $parts = explode( '.', $data['domain'], 2 );
        $sld = $parts[0];
        $tld = $parts[1];

        $params = array(
            'DomainName' => $sld,
            'SLD'        => $sld,
            'TLD'        => $tld,
            'Years'      => isset( $data['period'] ) ? intval( $data['period'] ) : 1,
        );

        // Contacts and other required fields may be passed in $data['contact'] as an associative array
        if ( isset( $data['contact'] ) && is_array( $data['contact'] ) ) {
            $params = array_merge( $params, $data['contact'] );
        }

        return self::make_request( 'namecheap.domains.create', $params );
    }

    public static function renew_domain( $domain, $period = 1 ) {
        $parts = explode( '.', $domain, 2 );
        $sld = $parts[0];
        $tld = $parts[1];

        $params = array(
            'SLD'   => $sld,
            'TLD'   => $tld,
            'Years' => intval( $period ),
        );

        return self::make_request( 'namecheap.domains.renew', $params );
    }

    public static function transfer_domain( $data ) {
        // NameCheap transfer: requires Auth code in EPPCode parameter
        if ( empty( $data['domain'] ) || empty( $data['auth_code'] ) ) {
            return new WP_Error( 'missing_data', 'Domain and auth code required' );
        }

        $parts = explode( '.', $data['domain'], 2 );
        $sld = $parts[0];
        $tld = $parts[1];

        $params = array(
            'SLD'     => $sld,
            'TLD'     => $tld,
            'EPPCode' => $data['auth_code'],
            'Years'   => isset( $data['period'] ) ? intval( $data['period'] ) : 1,
        );

        return self::make_request( 'namecheap.domains.transfer', $params );
    }

    public static function update_nameservers( $domain, $nameservers ) {
        $parts = explode( '.', $domain, 2 );
        $sld = $parts[0];
        $tld = $parts[1];

        $params = array(
            'SLD' => $sld,
            'TLD' => $tld,
        );

        // NameCheap expects Nameservers parameter as comma separated list
        if ( is_array( $nameservers ) ) {
            $params['Nameservers'] = implode( ',', $nameservers );
        } else {
            $params['Nameservers'] = (string) $nameservers;
        }

        return self::make_request( 'namecheap.domains.dns.setCustom', $params );
    }

    public static function get_domain_info( $domain ) {
        $parts = explode( '.', $domain, 2 );
        $sld = $parts[0];
        $tld = $parts[1];

        $params = array( 'DomainName' => $sld . '.' . $tld );

        return self::make_request( 'namecheap.domains.getInfo', $params );
    }

    public static function get_pricing() {
        // NameCheap does not offer a single pricing endpoint like OpenSRS; return WP_Error to indicate unsupported
        return new WP_Error( 'unsupported', 'Pricing import is not supported for NameCheap via this integration' );
    }

    public static function get_tld_list() {
        // Not supported via API reliably; return empty array
        return array();
    }
}
