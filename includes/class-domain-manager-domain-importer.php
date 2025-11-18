<?php
/**
 * Domain Manager - Domain Importer
 *
 * Handles importing TLD list and pricing from the configured provider
 *
 * @package WU_OpenSRS
 */

// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class WU_OpenSRS_Domain_Importer {
    
    private static $instance = null;
    
    public static function get_instance() {
        if ( null === self::$instance ) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    private function __construct() {
        // Importer no longer auto-renders via the global settings hook. It is rendered explicitly
        // on the OpenSRS admin page registered by `WU_OpenSRS_Settings::register_admin_pages()`.
        add_action( 'wp_ajax_wu_opensrs_import_tlds', array( $this, 'ajax_import_tlds' ) );
        add_action( 'wp_ajax_wu_opensrs_refresh_tlds', array( $this, 'ajax_refresh_tlds' ) );
    }

    public function render_import_section() {
        global $wpdb;
        $table = $wpdb->prefix . 'wu_opensrs_pricing';
        $tld_count = $wpdb->get_var( "SELECT COUNT(*) FROM $table" );
        $last_import = get_site_option( 'wu_opensrs_last_import' );
        ?>
        <div class="wu-styling">
            <div class="wu-p-6 wu-my-4 wu-bg-white wu-rounded wu-border wu-shadow-sm">
                <h3 class="wu-text-lg wu-font-bold wu-mb-4 wu-flex wu-items-center">
                    <span class="dashicons dashicons-download wu-mr-2"></span>
                    <?php esc_html_e( 'TLD Management', 'wu-opensrs' ); ?>
                </h3>
                <div class="wu-mb-4">
                    <p class="wu-text-gray-700 wu-mb-2">
                        <?php
                        if ( $tld_count > 0 ) {
                            printf(
                                esc_html__( 'Currently managing %d TLDs in the system.', 'wu-opensrs' ),
                                (int) $tld_count
                            );
                        } else {
                            esc_html_e( 'No TLDs imported yet. Click "Import TLDs" to fetch the available TLDs from your OpenSRS account.', 'wu-opensrs' );
                        }
                        ?>
                    </p>
                    <?php if ( $last_import ) : ?>
                        <p class="wu-text-sm wu-text-gray-600">
                            <?php
                            printf(
                                esc_html__( 'Last import: %s', 'wu-opensrs' ),
                                wp_date( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), strtotime( $last_import ) )
                            );
                            ?>
                        </p>
                    <?php endif; ?>
                </div>
                <div class="wu-flex wu-gap-3">
                    <button type="button" id="wu-opensrs-import-tlds" class="button button-primary">
                        <span class="dashicons dashicons-download wu-mr-1"></span>
                        <?php echo $tld_count > 0 ? esc_html__( 'Re-import TLDs', 'wu-opensrs' ) : esc_html__( 'Import TLDs from OpenSRS', 'wu-opensrs' ); ?>
                    </button>
                    <?php if ( $tld_count > 0 ) : ?>
                        <button type="button" id="wu-opensrs-refresh-pricing" class="button">
                            <span class="dashicons dashicons-update wu-mr-1"></span>
                            <?php esc_html_e( 'Refresh Pricing', 'wu-opensrs' ); ?>
                        </button>
                    <?php endif; ?>
                </div>
                <div id="wu-opensrs-import-status" class="wu-mt-4" style="display:none;">
                    <div class="wu-p-4 wu-bg-blue-50 wu-border wu-border-blue-200 wu-rounded">
                        <p class="wu-text-blue-800">
                            <span class="dashicons dashicons-update wu-animate-spin"></span>
                            <span id="wu-opensrs-import-message"><?php esc_html_e( 'Processing...', 'wu-opensrs' ); ?></span>
                        </p>
                    </div>
                </div>
                <div id="wu-opensrs-import-result" class="wu-mt-4" style="display:none;"></div>
                <div class="wu-mt-4 wu-p-4 wu-bg-gray-50 wu-rounded">
                    <h4 class="wu-font-semibold wu-mb-2"><?php esc_html_e( 'About TLD Import', 'wu-opensrs' ); ?></h4>
                    <ul class="wu-list-disc wu-list-inside wu-text-sm wu-text-gray-700 wu-space-y-1">
                        <li><?php esc_html_e( 'Import fetches all TLDs available in your provider account', 'wu-opensrs' ); ?></li>
                        <li><?php esc_html_e( 'Pricing information is automatically retrieved for each TLD', 'wu-opensrs' ); ?></li>
                        <li><?php esc_html_e( 'Only enabled TLDs in your account will be imported', 'wu-opensrs' ); ?></li>
                        <li><?php esc_html_e( 'Use "Refresh Pricing" to update prices without re-importing TLDs', 'wu-opensrs' ); ?></li>
                        <li><?php esc_html_e( 'Pricing automatically updates daily via cron job', 'wu-opensrs' ); ?></li>
                    </ul>
                </div>
            </div>
        </div>
        <script>
        jQuery(document).ready(function($) {
            $('#wu-opensrs-import-tlds').on('click', function() {
                var button = $(this);
                var originalText = button.html();
                if (!confirm('<?php esc_html_e( "This will fetch all available TLDs from your provider account. This may take a minute. Continue?", "wu-opensrs" ); ?>')) {
                    return;
                }
                button.prop('disabled', true).html('<span class="dashicons dashicons-update wu-animate-spin"></span> <?php esc_html_e( "Importing...", "wu-opensrs" ); ?>');
                $('#wu-opensrs-import-status').show();
                $('#wu-opensrs-import-result').hide();
                $('#wu-opensrs-import-message').text('<?php esc_html_e( "Connecting to provider API...", "wu-opensrs" ); ?>');
                $.post(ajaxurl, {
                    action: 'wu_opensrs_import_tlds',
                    nonce: '<?php echo wp_create_nonce( "wu-opensrs-import" ); ?>'
                }, function(response) {
                    $('#wu-opensrs-import-status').hide();
                    if (response.success) {
                        $('#wu-opensrs-import-result').html(
                            '<div class="notice notice-success"><p><strong>' + response.data.message + '</strong></p>' +
                            '<ul><li><?php esc_html_e( "TLDs imported:", "wu-opensrs" ); ?> ' + response.data.count + '</li></ul></div>'
                        ).show();
                        setTimeout(function() { location.reload(); }, 2000);
                    } else {
                        $('#wu-opensrs-import-result').html('<div class="notice notice-error"><p>' + response.data.message + '</p></div>').show();
                    }
                    button.prop('disabled', false).html(originalText);
                }).fail(function() {
                    $('#wu-opensrs-import-status').hide();
                    $('#wu-opensrs-import-result').html('<div class="notice notice-error"><p><?php esc_html_e( "Connection failed. Please check your API credentials.", "wu-opensrs" ); ?></p></div>').show();
                    button.prop('disabled', false).html(originalText);
                });
            });

            $('#wu-opensrs-refresh-pricing').on('click', function() {
                var button = $(this);
                var originalText = button.html();
                button.prop('disabled', true).html('<span class="dashicons dashicons-update wu-animate-spin"></span> <?php esc_html_e( "Refreshing...", "wu-opensrs" ); ?>');
                $('#wu-opensrs-import-status').show();
                $('#wu-opensrs-import-result').hide();
                $('#wu-opensrs-import-message').text('<?php esc_html_e( "Updating pricing information...", "wu-opensrs" ); ?>');
                $.post(ajaxurl, {
                    action: 'wu_opensrs_refresh_tlds',
                    nonce: '<?php echo wp_create_nonce( "wu-opensrs-import" ); ?>'
                }, function(response) {
                    $('#wu-opensrs-import-status').hide();
                    if (response.success) {
                        $('#wu-opensrs-import-result').html('<div class="notice notice-success"><p>' + response.data.message + '</p></div>').show();
                    } else {
                        $('#wu-opensrs-import-result').html('<div class="notice notice-error"><p>' + response.data.message + '</p></div>').show();
                    }
                    button.prop('disabled', false).html(originalText);
                });
            });
        });
        </script>
        <style>
        .wu-animate-spin { animation: spin 1s linear infinite; }
        @keyframes spin { from { transform: rotate(0deg); } to { transform: rotate(360deg); } }
        </style>
        <?php
    }

    public function ajax_import_tlds() {
        check_ajax_referer( 'wu-opensrs-import', 'nonce' );
        if ( ! current_user_can( 'manage_network' ) ) {
            wp_send_json_error( array( 'message' => __( 'Permission denied.', 'wu-opensrs' ) ) );
        }
        $result = $this->import_tlds_from_provider();
        if ( is_wp_error( $result ) ) {
            wp_send_json_error( array( 'message' => $result->get_error_message() ) );
        }
        wp_send_json_success( array( 'message' => __( 'TLDs imported successfully!', 'wu-opensrs' ), 'count' => $result ) );
    }

    public function ajax_refresh_tlds() {
        check_ajax_referer( 'wu-opensrs-import', 'nonce' );
        if ( ! current_user_can( 'manage_network' ) ) {
            wp_send_json_error( array( 'message' => __( 'Permission denied.', 'wu-opensrs' ) ) );
        }
        do_action( 'wu_opensrs_update_pricing' );
        wp_send_json_success( array( 'message' => __( 'Pricing refreshed successfully!', 'wu-opensrs' ) ) );
    }

    private function import_tlds_from_provider() {
        $pricing_data = WU_Domain_Provider::get_pricing();
        if ( is_wp_error( $pricing_data ) ) {
            return $pricing_data;
        }
        if ( empty( $pricing_data ) || ! is_array( $pricing_data ) ) {
            return new \WP_Error( 'no_tlds', __( 'No TLDs found in your account.', 'wu-opensrs' ) );
        }
        global $wpdb;
        $table = $wpdb->prefix . 'wu_opensrs_pricing';
        $count = 0;
        $wpdb->query( 'START TRANSACTION' );
        try {
            foreach ( $pricing_data as $tld => $prices ) {
                if ( empty( $prices ) ) continue;
                $wpdb->replace( $table, array( 'tld' => $tld, 'registration_price' => isset( $prices['registration'] ) ? $prices['registration'] : 0, 'renewal_price' => isset( $prices['renewal'] ) ? $prices['renewal'] : 0, 'transfer_price' => isset( $prices['transfer'] ) ? $prices['transfer'] : 0, 'whois_privacy_price' => isset( $prices['privacy'] ) ? $prices['privacy'] : 0, 'currency' => 'USD', 'is_enabled' => 1, 'last_updated' => current_time( 'mysql' ) ), array( '%s', '%f', '%f', '%f', '%f', '%s', '%d', '%s' ) );
                $count++;
            }
            $wpdb->query( 'COMMIT' );
            update_site_option( 'wu_opensrs_last_import', current_time( 'mysql' ) );
            return $count;
        } catch ( \Exception $e ) {
            $wpdb->query( 'ROLLBACK' );
            return new \WP_Error( 'import_failed', $e->getMessage() );
        }
    }

    public static function get_available_tlds() {
        global $wpdb;
        $table = $wpdb->prefix . 'wu_opensrs_pricing';
        return $wpdb->get_results( "SELECT * FROM $table WHERE is_enabled = 1 ORDER BY tld ASC" );
    }

    public static function toggle_tld( $tld, $enabled = true ) {
        global $wpdb;
        $table = $wpdb->prefix . 'wu_opensrs_pricing';
        return $wpdb->update( $table, array( 'is_enabled' => $enabled ? 1 : 0 ), array( 'tld' => $tld ), array( '%d' ), array( '%s' ) );
    }
}
WU_OpenSRS_Domain_Importer::get_instance();
