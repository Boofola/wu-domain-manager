<?php
/**
 * OpenSRS Checkout Integration
 * 
 * File: includes/class-opensrs-checkout.php
 *
 * @package WU_OpenSRS
 */

// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * OpenSRS Checkout Class
 */
class WU_OpenSRS_Checkout {
	
	/**
	 * Singleton instance
	 */
	private static $instance = null;
	
	/**
	 * Get singleton instance
	 */
	public static function get_instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}
	
	/**
	 * Constructor
	 */
	private function __construct() {
		// Add domain field to checkout
		add_action( 'wu_checkout_form_after_products', array( $this, 'render_domain_field' ), 10 );
		
		// Enqueue scripts
		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_scripts' ) );
		
		// AJAX handlers
		add_action( 'wp_ajax_wu_opensrs_check_domain', array( $this, 'ajax_check_domain' ) );
		add_action( 'wp_ajax_nopriv_wu_opensrs_check_domain', array( $this, 'ajax_check_domain' ) );
		
		// Process domain registration
		add_action( 'wu_checkout_after_purchase', array( $this, 'process_domain_registration' ), 10, 2 );
		
		// Add domain to cart
		add_filter( 'wu_cart_line_items', array( $this, 'add_domain_to_cart' ), 10, 2 );
	}
	
	/**
	 * Render domain field on checkout
	 */
	public function render_domain_field() {
		if ( ! WU_OpenSRS_Settings::is_enabled() ) {
			return;
		}
		
		// Get selected product
		$product_id = isset( $_GET['product'] ) ? absint( $_GET['product'] ) : 0;
		
		if ( ! $product_id ) {
			return;
		}
		
		$product = wu_get_product( $product_id );
		
		if ( ! $product || 'domain' !== $product->get_type() ) {
			return;
		}
		
		$allowed_tlds = get_post_meta( $product_id, '_wu_opensrs_allowed_tlds', true );
		$pricing_model = get_post_meta( $product_id, '_wu_opensrs_pricing_model', true );
		
		if ( empty( $allowed_tlds ) ) {
			return;
		}
		
		$tlds = explode( ',', $allowed_tlds );
		
		?>
		<div class="wu-widget wu-mb-4" id="wu-opensrs-domain-widget">
			<div class="wu-widget-content">
				<h3 class="wu-widget-title">
					<?php esc_html_e( 'Choose Your Domain', 'wu-opensrs' ); ?>
				</h3>
				
				<div class="wu-p-4">
					<div class="wu-flex wu-gap-2 wu-mb-3">
						<input type="text" 
							id="wu-opensrs-domain-search" 
							name="domain_name" 
							placeholder="<?php esc_attr_e( 'yourdomain', 'wu-opensrs' ); ?>"
							class="wu-flex-1 wu-p-2 wu-border wu-rounded"
							autocomplete="off">
						
						<select id="wu-opensrs-tld-select" 
							name="domain_tld" 
							class="wu-p-2 wu-border wu-rounded">
							<?php foreach ( $tlds as $tld ) : 
								$tld = trim( $tld );
								if ( empty( $tld ) ) continue;
							?>
								<option value="<?php echo esc_attr( $tld ); ?>">
									.<?php echo esc_html( $tld ); ?>
								</option>
							<?php endforeach; ?>
						</select>
						
						<button type="button" 
							id="wu-opensrs-check-btn" 
							class="wu-button wu-button-primary">
							<?php esc_html_e( 'Check', 'wu-opensrs' ); ?>
						</button>
					</div>
					
					<div id="wu-opensrs-result" class="wu-mt-3"></div>
					
					<div id="wu-opensrs-pricing" class="wu-mt-3" style="display:none;">
						<?php if ( 'dynamic' === $pricing_model ) : ?>
							<p class="wu-text-sm wu-text-gray-600">
								<?php esc_html_e( 'Price:', 'wu-opensrs' ); ?>
								<span id="wu-opensrs-price" class="wu-font-semibold"></span>
							</p>
						<?php endif; ?>
					</div>
					
					<input type="hidden" id="wu-opensrs-domain-available" name="domain_available" value="0">
					<input type="hidden" id="wu-opensrs-domain-full" name="domain_full" value="">
                </div>