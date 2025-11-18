<?php
/**
 * Domain Provider Facade
 *
 * Chooses between OpenSRS and NameCheap based on settings or per-product meta.
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class WU_Domain_Provider {

    public static function init() {
        // AJAX tester for admin: test provider connection
        add_action( 'wp_ajax_wu_domain_provider_test_connection', array( __CLASS__, 'ajax_test_connection' ) );
    }

    public static function get_default_provider() {
        $provider = wu_get_setting( 'wu_domain_provider_default', 'opensrs' );
        return $provider;
    }

    public static function get_provider_for_product( $product_id = null ) {
        if ( $product_id ) {
            $p = get_post_meta( $product_id, '_wu_domain_provider', true );
            if ( ! empty( $p ) ) {
                return $p;
            }
        }

        return self::get_default_provider();
    }

    // Forwarding methods used across the plugin
    public static function test_connection() {
        $provider = self::get_default_provider();
        if ( 'namecheap' === $provider ) {
            return WU_NameCheap_API::test_connection();
        }
        return WU_OpenSRS_API::test_connection();
    }

    public static function lookup_domain( $domain, $product_id = null ) {
        $provider = self::get_provider_for_product( $product_id );
        if ( 'namecheap' === $provider ) {
            return WU_NameCheap_API::lookup_domain( $domain );
        }
        return WU_OpenSRS_API::lookup_domain( $domain );
    }

    public static function register_domain( $data, $product_id = null ) {
        $provider = self::get_provider_for_product( $product_id );
        if ( 'namecheap' === $provider ) {
            return WU_NameCheap_API::register_domain( $data );
        }
        return WU_OpenSRS_API::register_domain( $data );
    }

    public static function renew_domain( $domain, $period = 1, $product_id = null ) {
        $provider = self::get_provider_for_product( $product_id );
        if ( 'namecheap' === $provider ) {
            return WU_NameCheap_API::renew_domain( $domain, $period );
        }
        return WU_OpenSRS_API::renew_domain( $domain, $period );
    }

    public static function transfer_domain( $data, $product_id = null ) {
        $provider = self::get_provider_for_product( $product_id );
        if ( 'namecheap' === $provider ) {
            return WU_NameCheap_API::transfer_domain( $data );
        }
        return WU_OpenSRS_API::transfer_domain( $data );
    }

    public static function update_nameservers( $domain, $nameservers, $product_id = null ) {
        $provider = self::get_provider_for_product( $product_id );
        if ( 'namecheap' === $provider ) {
            return WU_NameCheap_API::update_nameservers( $domain, $nameservers );
        }
        return WU_OpenSRS_API::update_nameservers( $domain, $nameservers );
    }

    public static function get_domain_info( $domain, $product_id = null ) {
        $provider = self::get_provider_for_product( $product_id );
        if ( 'namecheap' === $provider ) {
            return WU_NameCheap_API::get_domain_info( $domain );
        }
        return WU_OpenSRS_API::get_domain_info( $domain );
    }

    public static function get_pricing( $product_id = null ) {
        $provider = self::get_provider_for_product( $product_id );
        if ( 'namecheap' === $provider ) {
            return WU_NameCheap_API::get_pricing();
        }
        return WU_OpenSRS_API::get_pricing();
    }

    public static function get_tld_list( $product_id = null ) {
        $provider = self::get_provider_for_product( $product_id );
        if ( 'namecheap' === $provider ) {
            return WU_NameCheap_API::get_tld_list();
        }
        return WU_OpenSRS_API::get_tld_list();
    }

    public static function ajax_test_connection() {
        if ( ! current_user_can( 'manage_network' ) ) {
            wp_send_json_error( array( 'message' => __( 'Permission denied', 'wu-opensrs' ) ) );
        }

        // Determine provider from request or default
        $provider = isset( $_POST['provider'] ) ? sanitize_text_field( wp_unslash( $_POST['provider'] ) ) : self::get_default_provider();

        // Verify provider-specific nonce
        if ( 'namecheap' === $provider ) {
            check_ajax_referer( 'wu-namecheap-test', 'nonce' );
        } else {
            check_ajax_referer( 'wu-opensrs-test', 'nonce' );
        }

        if ( 'namecheap' === $provider ) {
            $res = WU_NameCheap_API::test_connection();
        } else {
            $res = WU_OpenSRS_API::test_connection();
        }

        if ( is_array( $res ) && isset( $res['success'] ) && $res['success'] ) {
            wp_send_json_success( array( 'message' => $res['message'] ) );
        }

        wp_send_json_error( array( 'message' => is_array( $res ) && isset( $res['message'] ) ? $res['message'] : __( 'Connection failed', 'wu-opensrs' ) ) );
    }
}
