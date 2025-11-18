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

		// Validate checkout submissions for required domain contact fields
		if ( function_exists( 'add_action' ) ) {
			add_action( 'wu_setup_checkout', array( $this, 'validate_checkout_submission' ), 10, 1 );
		}
		
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
	 * Enqueue frontend scripts and localize data
	 */
	public function enqueue_scripts() {
		wp_enqueue_script( 'wu-opensrs-checkout', WU_OPENSRS_PLUGIN_URL . 'assets/js/opensrs-checkout.js', array( 'jquery' ), WU_OPENSRS_VERSION, true );

		$strings = array(
			'ajax_url' => admin_url( 'admin-ajax.php' ),
			'nonce'    => wp_create_nonce( 'wu-opensrs-check' ),
			'checking' => __( 'Checking domain availability...', 'wu-opensrs' ),
			'error'    => __( 'An error occurred. Please try again.', 'wu-opensrs' ),
			'available'=> __( 'Domain is available!', 'wu-opensrs' ),
			'unavailable'=> __( 'Domain is not available', 'wu-opensrs' ),
		);

		wp_localize_script( 'wu-opensrs-checkout', 'wu_opensrs', $strings );
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
		<?php
		// Inline error message area for domain errors
		if ( isset( $_GET['wu_domain_error'] ) && $_GET['wu_domain_error'] === 'missing_contact' ) : ?>
			<div class="wu-alert wu-alert-error wu-mb-3">
				<?php esc_html_e( 'Please complete the registrant contact details to proceed with registration.', 'wu-opensrs' ); ?>
			</div>
		<?php endif; ?>
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
		                    <input type="hidden" id="wu-opensrs-domain-price" name="domain_price" value="0">
							<input type="hidden" id="wu-opensrs-product-id" name="product_id" value="<?php echo esc_attr( $product_id ); ?>">
							<input type="hidden" id="wu-opensrs-product-provider" name="product_provider" value="<?php echo esc_attr( WU_Domain_Provider::get_provider_for_product( $product_id ) ); ?>">

						<!-- Domain contact fields (required for providers like NameCheap) -->
						<div id="wu-opensrs-domain-contact">
							<h4 class="wu-font-semibold wu-mb-2"><?php esc_html_e( 'Registrant Contact (required for some providers)', 'wu-opensrs' ); ?></h4>
							<div class="wu-grid wu-gap-2" style="grid-template-columns:repeat(2,1fr);">
								<input type="text" name="domain_contact[first_name]" placeholder="<?php esc_attr_e( 'First name', 'wu-opensrs' ); ?>" class="wu-p-2 wu-border wu-rounded">
								<input type="text" name="domain_contact[last_name]" placeholder="<?php esc_attr_e( 'Last name', 'wu-opensrs' ); ?>" class="wu-p-2 wu-border wu-rounded">
								<input type="email" name="domain_contact[email]" placeholder="<?php esc_attr_e( 'Email', 'wu-opensrs' ); ?>" class="wu-p-2 wu-border wu-rounded">
								<input type="text" name="domain_contact[phone]" placeholder="<?php esc_attr_e( 'Phone', 'wu-opensrs' ); ?>" class="wu-p-2 wu-border wu-rounded">
								<input type="text" name="domain_contact[addr1]" placeholder="<?php esc_attr_e( 'Address 1', 'wu-opensrs' ); ?>" class="wu-p-2 wu-border wu-rounded">
								<input type="text" name="domain_contact[city]" placeholder="<?php esc_attr_e( 'City', 'wu-opensrs' ); ?>" class="wu-p-2 wu-border wu-rounded">
								<input type="text" name="domain_contact[state]" placeholder="<?php esc_attr_e( 'State/Province', 'wu-opensrs' ); ?>" class="wu-p-2 wu-border wu-rounded">
								<input type="text" name="domain_contact[postal_code]" placeholder="<?php esc_attr_e( 'Postal Code', 'wu-opensrs' ); ?>" class="wu-p-2 wu-border wu-rounded">
								<input type="text" name="domain_contact[country]" placeholder="<?php esc_attr_e( 'Country', 'wu-opensrs' ); ?>" class="wu-p-2 wu-border wu-rounded">
							</div>
							<p class="wu-text-sm wu-text-gray-600 wu-mt-2"><?php esc_html_e( 'These fields are required by some registrars (for example NameCheap). If you leave them blank, registration may fail.', 'wu-opensrs' ); ?></p>
						</div>
                </div>
		                <?php
			}

			/**
			 * AJAX handler: check domain availability
			 */
			public function ajax_check_domain() {
				check_ajax_referer( 'wu-opensrs-check', 'nonce' );

				$domain = isset( $_POST['domain'] ) ? sanitize_text_field( wp_unslash( $_POST['domain'] ) ) : '';
				$tld = isset( $_POST['tld'] ) ? sanitize_text_field( wp_unslash( $_POST['tld'] ) ) : '';
				$product_id = isset( $_POST['product_id'] ) ? absint( $_POST['product_id'] ) : 0;

				if ( empty( $domain ) || empty( $tld ) ) {
					wp_send_json_error( array( 'message' => __( 'Invalid domain', 'wu-opensrs' ) ) );
				}

				$full = $domain . '.' . $tld;

				$result = WU_Domain_Provider::lookup_domain( $full, $product_id );

				if ( is_wp_error( $result ) ) {
					wp_send_json_error( array( 'message' => $result->get_error_message() ) );
				}

				$available = false;
				// Check common response attributes
				if ( isset( $result['attributes']['available'] ) ) {
					$available = filter_var( $result['attributes']['available'], FILTER_VALIDATE_BOOLEAN );
				} elseif ( isset( $result['attributes']['is_registered'] ) ) {
					$available = ! (bool) $result['attributes']['is_registered'];
				} elseif ( isset( $result['is_success'] ) ) {
					// If success and no error text, assume available
					$available = ( 1 === $result['is_success'] && empty( $result['response_text'] ) );
				}

				$price = 0;
				$formatted = '';
				// If dynamic pricing enabled for product, fetch from pricing table
				$pricing_model = get_post_meta( $product_id, '_wu_opensrs_pricing_model', true );
				if ( 'dynamic' === $pricing_model ) {
					global $wpdb;
					$table = $wpdb->prefix . 'wu_opensrs_pricing';
					$row = $wpdb->get_row( $wpdb->prepare( "SELECT registration_price, currency FROM $table WHERE tld = %s", $tld ) );
					if ( $row ) {
						$price = floatval( $row->registration_price );
						$formatted = number_format_i18n( $price, 2 ) . ' ' . $row->currency;
					}
				}

				wp_send_json_success( array( 'available' => $available, 'domain' => $full, 'price' => $price, 'formatted_price' => $formatted ) );
			}

			/**
			 * Process domain registration after checkout purchase
			 *
			 * This method attempts to register a domain if the checkout contained a domain entry.
			 */
			public function process_domain_registration( $order, $cart ) {
				// Check POST for domain info
				if ( empty( $_POST['domain_full'] ) || empty( $_POST['domain_available'] ) ) {
					return;
				}

				$domain_full = sanitize_text_field( wp_unslash( $_POST['domain_full'] ) );
				$available = sanitize_text_field( wp_unslash( $_POST['domain_available'] ) );
				$product_id = isset( $_POST['product_id'] ) ? absint( $_POST['product_id'] ) : 0;

				if ( '1' !== $available ) {
					return; // Not available or not selected
				}

				$data = array(
					'domain' => $domain_full,
					'period' => isset( $_POST['domain_period'] ) ? absint( $_POST['domain_period'] ) : 1,
				);

				// Pass through contact details if provided
				if ( isset( $_POST['domain_contact'] ) && is_array( $_POST['domain_contact'] ) ) {
					$data['contact'] = array_map( 'sanitize_text_field', wp_unslash( $_POST['domain_contact'] ) );
				}

				// Server-side validation: if provider is NameCheap, require contact fields
				$provider = WU_Domain_Provider::get_provider_for_product( $product_id );
				if ( 'namecheap' === $provider ) {
					$required = array( 'first_name', 'last_name', 'email', 'phone', 'addr1', 'city', 'postal_code', 'country' );
					$missing = array();
					if ( empty( $data['contact'] ) || ! is_array( $data['contact'] ) ) {
						$missing = $required;
					} else {
						foreach ( $required as $f ) {
							if ( empty( $data['contact'][ $f ] ) ) {
								$missing[] = $f;
							}
						}
					}

					if ( ! empty( $missing ) ) {
						// Log and attach note to order if possible
						error_log( 'Domain registration aborted: missing contact fields for NameCheap: ' . implode( ', ', $missing ) );
						if ( is_object( $order ) && method_exists( $order, 'add_note' ) ) {
							$order->add_note( 'Domain registration aborted: missing registrant contact fields for NameCheap: ' . implode( ', ', $missing ) );
						}
						return;
					}
				}

				$result = WU_Domain_Provider::register_domain( $data, $product_id );

				if ( is_wp_error( $result ) ) {
					error_log( 'Domain registration failed for ' . $domain_full . ': ' . $result->get_error_message() );
					return;
				}

				// Optionally, store domain record or response attributes as needed
			}

			/**
			 * Validate checkout submission server-side.
			 * If NameCheap is the provider for the product and required contact fields are missing,
			 * redirect back to the referring page with an error query param to block completion.
			 */
			/**
			 * Validate checkout submission server-side.
			 * If NameCheap is the provider for the product and required contact fields are missing,
			 * add an error to the checkout errors array (if provided), or fallback to redirect/alert.
			 *
			 * @param array $checkout_errors (optional, by reference)
			 */
			public function validate_checkout_submission( &$checkout_errors = null ) {
				if ( 'POST' !== strtoupper( $_SERVER['REQUEST_METHOD'] ) ) {
					return;
				}

				// Only act if a domain was submitted
				if ( empty( $_POST['domain_full'] ) ) {
					return;
				}

				$product_id = isset( $_POST['product_id'] ) ? absint( $_POST['product_id'] ) : 0;
				if ( ! $product_id ) {
					return;
				}

				$provider = WU_Domain_Provider::get_provider_for_product( $product_id );
				if ( 'namecheap' !== $provider ) {
					return;
				}

				// Validate contact fields
				$required = array( 'first_name', 'last_name', 'email', 'phone', 'addr1', 'city', 'postal_code', 'country' );
				$missing = array();
				$contact = isset( $_POST['domain_contact'] ) && is_array( $_POST['domain_contact'] ) ? wp_unslash( $_POST['domain_contact'] ) : array();

				foreach ( $required as $f ) {
					if ( empty( $contact[ $f ] ) ) {
						$missing[] = $f;
					}
				}

				if ( ! empty( $missing ) ) {
					$msg = __( 'Please complete registrant contact details for NameCheap domains.', 'wu-opensrs' );
					// If this is an AJAX request, return JSON error
					if ( defined( 'DOING_AJAX' ) && DOING_AJAX ) {
						wp_send_json_error( array( 'message' => $msg ) );
					}
					// If $checkout_errors is provided (by reference), add error for inline display
					if ( is_array( $checkout_errors ) ) {
						$checkout_errors[] = $msg;
						return;
					}
					// Fallback: redirect back with error param
					$ref = wp_get_referer() ? wp_get_referer() : home_url();
					$redirect = add_query_arg( 'wu_domain_error', 'missing_contact', $ref );
					wp_safe_redirect( $redirect );
					exit;
				}
			}