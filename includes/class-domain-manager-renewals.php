<?php
/**
 * Domain Manager Renewals
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class WU_OpenSRS_Renewals {
    
    public static function init() {
        add_action( 'wu_opensrs_check_renewals', array( __CLASS__, 'check_renewals_cron' ) );
        add_action( 'wu_opensrs_check_expirations', array( __CLASS__, 'check_expirations_cron' ) );
        add_action( 'wu_opensrs_process_auto_renewals', array( __CLASS__, 'process_auto_renewals' ) );
    }

    public static function check_renewals_cron() {
        if ( ! WU_OpenSRS_Settings::is_enabled() ) { return; }
        global $wpdb;
        $table = $wpdb->prefix . 'wu_opensrs_domains';
        $domains = $wpdb->get_results( "SELECT * FROM $table WHERE status = 'active' ORDER BY expiration_date ASC" );
        foreach ( $domains as $domain ) {
            $info = WU_Domain_Provider::get_domain_info( $domain->domain_name );
            if ( is_wp_error( $info ) ) {
                error_log( sprintf( 'Failed to check renewal for domain %s: %s', $domain->domain_name, $info->get_error_message() ) );
                continue;
            }
            if ( 1 === $info['is_success'] && isset( $info['attributes']['expiry_date'] ) ) {
                $expiry_date = date( 'Y-m-d H:i:s', strtotime( $info['attributes']['expiry_date'] ) );
                $wpdb->update( $table, array( 'expiration_date' => $expiry_date ), array( 'id' => $domain->id ), array( '%s' ), array( '%d' ) );
            }
        }
    }

    public static function check_expirations_cron() {
        if ( ! WU_OpenSRS_Settings::is_enabled() ) { return; }
        global $wpdb;
        $table = $wpdb->prefix . 'wu_opensrs_domains';
        $now = current_time( 'mysql' );
        $threshold = date( 'Y-m-d H:i:s', strtotime( '+30 days', current_time( 'timestamp' ) ) );
        $domains = $wpdb->get_results( $wpdb->prepare( "SELECT * FROM $table WHERE status = %s AND expiration_date BETWEEN %s AND %s ORDER BY expiration_date ASC", 'active', $now, $threshold ) );
        if ( empty( $domains ) ) { return; }
        foreach ( $domains as $domain ) {
            $days_until = floor( ( strtotime( $domain->expiration_date ) - current_time( 'timestamp' ) ) / DAY_IN_SECONDS );
            $subject = sprintf( __( 'Domain expiration notice: %s', 'wu-opensrs' ), $domain->domain_name );
            $message = sprintf( __( "Hello,\n\nYour domain %s is set to expire in %d days on %s.\n\nIf you have auto-renew enabled it will be attempted automatically. Otherwise please renew to keep your domain active.\n\nThank you.", 'wu-opensrs' ), $domain->domain_name, $days_until, $domain->expiration_date );
            $sent_to_customer = false;
            if ( ! empty( $domain->customer_id ) ) {
                $user = get_user_by( 'ID', (int) $domain->customer_id );
                if ( $user && ! empty( $user->user_email ) ) {
                    wp_mail( $user->user_email, $subject, $message );
                    $sent_to_customer = true;
                }
            }
            $admin_email = get_site_option( 'admin_email' );
            if ( ! $sent_to_customer && $admin_email ) { wp_mail( $admin_email, $subject, $message ); }
        }
    }

    public static function process_auto_renewals() {
        if ( ! WU_OpenSRS_Settings::is_enabled() ) { return; }
        global $wpdb;
        $table = $wpdb->prefix . 'wu_opensrs_domains';
        $now = current_time( 'mysql' );
        $threshold = date( 'Y-m-d H:i:s', strtotime( '+7 days', current_time( 'timestamp' ) ) );
        $domains = $wpdb->get_results( $wpdb->prepare( "SELECT * FROM $table WHERE auto_renew = %d AND status = %s AND expiration_date <= %s ORDER BY expiration_date ASC", 1, 'active', $threshold ) );
        if ( empty( $domains ) ) { return; }
        foreach ( $domains as $domain ) {
            try {
                $res = WU_Domain_Provider::renew_domain( $domain->domain_name, 1, $domain->product_id );
                if ( is_wp_error( $res ) ) {
                    error_log( sprintf( 'Auto-renew failed (WP_Error) for %s: %s', $domain->domain_name, $res->get_error_message() ) );
                    $notify = sprintf( __( "Auto-renewal failed for domain %s: %s", 'wu-opensrs' ), $domain->domain_name, $res->get_error_message() );
                    wp_mail( get_site_option( 'admin_email' ), __( 'Auto-renewal failed', 'wu-opensrs' ), $notify );
                    $wpdb->update( $table, array( 'last_renewal_check' => current_time( 'mysql' ) ), array( 'id' => $domain->id ), array( '%s' ), array( '%d' ) );
                    continue;
                }
                $success = false;
                if ( is_array( $res ) && isset( $res['is_success'] ) ) { $success = (int) $res['is_success'] === 1; }
                elseif ( is_array( $res ) && isset( $res['success'] ) ) { $success = (bool) $res['success']; }
                if ( $success ) {
                    if ( isset( $res['attributes']['expiry_date'] ) ) {
                        $new_expiry = date( 'Y-m-d H:i:s', strtotime( $res['attributes']['expiry_date'] ) );
                        $wpdb->update( $table, array( 'expiration_date' => $new_expiry ), array( 'id' => $domain->id ), array( '%s' ), array( '%d' ) );
                    }
                    $wpdb->update( $table, array( 'renewal_date' => current_time( 'mysql' ), 'last_renewal_check' => current_time( 'mysql' ) ), array( 'id' => $domain->id ), array( '%s', '%s' ), array( '%d' ) );
                    $subject = sprintf( __( 'Domain renewed: %s', 'wu-opensrs' ), $domain->domain_name );
                    $message = sprintf( __( "Your domain %s has been successfully renewed for 1 year.", 'wu-opensrs' ), $domain->domain_name );
                    $sent = false;
                    if ( ! empty( $domain->customer_id ) ) {
                        $user = get_user_by( 'ID', (int) $domain->customer_id );
                        if ( $user && ! empty( $user->user_email ) ) { wp_mail( $user->user_email, $subject, $message ); $sent = true; }
                    }
                    if ( ! $sent ) { wp_mail( get_site_option( 'admin_email' ), $subject, $message ); }
                } else {
                    $error_text = is_array( $res ) && isset( $res['response_text'] ) ? $res['response_text'] : ( is_array( $res ) && isset( $res['message'] ) ? $res['message'] : __( 'Unknown error', 'wu-opensrs' ) );
                    error_log( sprintf( 'Auto-renew failed for %s: %s', $domain->domain_name, $error_text ) );
                    wp_mail( get_site_option( 'admin_email' ), __( 'Auto-renewal failed', 'wu-opensrs' ), sprintf( __( 'Auto-renewal failed for %s: %s', 'wu-opensrs' ), $domain->domain_name, $error_text ) );
                    $wpdb->update( $table, array( 'last_renewal_check' => current_time( 'mysql' ) ), array( 'id' => $domain->id ), array( '%s' ), array( '%d' ) );
                }
            } catch ( Exception $e ) {
                error_log( sprintf( 'Exception during auto-renew for %s: %s', $domain->domain_name, $e->getMessage() ) );
                wp_mail( get_site_option( 'admin_email' ), __( 'Auto-renewal exception', 'wu-opensrs' ), sprintf( __( 'Exception during auto-renewal for %s: %s', 'wu-opensrs' ), $domain->domain_name, $e->getMessage() ) );
                $wpdb->update( $table, array( 'last_renewal_check' => current_time( 'mysql' ) ), array( 'id' => $domain->id ), array( '%s' ), array( '%d' ) );
            }
        }
        return;
    }
}
