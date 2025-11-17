<?php
/**
 * OpenSRS Product Type
 * 
 * Registers "Domain" as a new product type in Ultimate Multisite
 *
 * @package WU_OpenSRS
 */

// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * OpenSRS Product Type Class
 */
class WU_OpenSRS_Product_Type {
	
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
		// Register domain product type
		add_filter( 'wu_product_types', array( $this, 'register_domain_product_type' ), 10 );
		
		// Add domain-specific fields to product editor
		add_action( 'wu_product_edit_after_general', array( $this, 'render_domain_fields' ), 10 );
		
		// Save domain product settings
		add_action( 'wu_save_product', array( $this, 'save_domain_settings' ), 10, 2 );
		
		// Modify product display for domains
		add_filter( 'wu_product_get_description', array( $this, 'modify_domain_description' ), 10, 2 );
	}
	
	/**
	 * Register Domain as a product type
	 */
	public function register_domain_product_type( $types ) {
		$types['domain'] = array(
			'name'        => __( 'Domain', 'wu-opensrs' ),
			'description' => __( 'Domain registration, renewal, and transfer services', 'wu-opensrs' ),
			'icon'        => 'dashicons-admin-site-alt3',
			'color'       => '#2563eb',
			'supports'    => array( 'pricing', 'limitations' ),
		);
		
		return $types;
	}
	
	/**
	 * Render domain-specific fields in product editor
	 */
	public function render_domain_fields( $product ) {
		// Only show for domain products
		if ( 'domain' !== $product->get_type() ) {
			return;
		}
		
		$allowed_tlds = get_post_meta( $product->get_id(), '_wu_opensrs_allowed_tlds', true );
		$pricing_model = get_post_meta( $product->get_id(), '_wu_opensrs_pricing_model', true );
		$auto_renew_default = get_post_meta( $product->get_id(), '_wu_opensrs_auto_renew_default', true );
		$whois_privacy_included = get_post_meta( $product->get_id(), '_wu_opensrs_whois_privacy_included', true );
		
		?>
		<div class="wu-styling">
			<div class="wu-p-4 wu-my-4 wu-bg-blue-50 wu-rounded wu-border wu-border-blue-200">
				<h3 class="wu-text-lg wu-font-bold wu-mb-4 wu-flex wu-items-center">
					<span class="dashicons dashicons-admin-site-alt3 wu-mr-2"></span>
					<?php esc_html_e( 'Domain Product Settings', 'wu-opensrs' ); ?>
				</h3>
				
				<!-- Allowed TLDs -->
				<div class="wu-mb-4">
					<label class="wu-block wu-font-semibold wu-mb-2">
						<?php esc_html_e( 'Allowed TLDs', 'wu-opensrs' ); ?>
						<span class="wu-text-red-500">*</span>
					</label>
					<div class="wu-flex wu-gap-2 wu-mb-2">
						<select id="wu-opensrs-tld-selector" class="wu-flex-1">
							<option value=""><?php esc_html_e( 'Select TLDs to add...', 'wu-opensrs' ); ?></option>
							<?php
							global $wpdb;
							$table = $wpdb->prefix . 'wu_opensrs_pricing';
							$available_tlds = $wpdb->get_results( "SELECT tld FROM $table WHERE is_enabled = 1 ORDER BY tld ASC" );
							
							foreach ( $available_tlds as $tld_obj ) {
								echo '<option value="' . esc_attr( $tld_obj->tld ) . '">' . esc_html( '.' . $tld_obj->tld ) . '</option>';
							}
							?>
						</select>
						<button type="button" id="wu-opensrs-add-tld" class="button">
							<?php esc_html_e( 'Add TLD', 'wu-opensrs' ); ?>
						</button>
						<button type="button" id="wu-opensrs-add-all-tlds" class="button">
							<?php esc_html_e( 'Add All', 'wu-opensrs' ); ?>
						</button>
					</div>
					
					<div id="wu-opensrs-selected-tlds" class="wu-flex wu-flex-wrap wu-gap-2 wu-mb-2">
						<?php
						if ( ! empty( $allowed_tlds ) ) {
							$tlds = explode( ',', $allowed_tlds );
							foreach ( $tlds as $tld ) {
								$tld = trim( $tld );
								if ( ! empty( $tld ) ) {
									echo '<span class="wu-opensrs-tld-tag wu-inline-flex wu-items-center wu-bg-blue-100 wu-text-blue-800 wu-px-3 wu-py-1 wu-rounded-full wu-text-sm">';
									echo '.' . esc_html( $tld );
									echo '<button type="button" class="wu-opensrs-remove-tld wu-ml-2 wu-text-blue-600 hover:wu-text-blue-800" data-tld="' . esc_attr( $tld ) . '">×</button>';
									echo '</span>';
								}
							}
						}
						?>
					</div>
					
					<input type="hidden" name="wu_opensrs_allowed_tlds" id="wu-opensrs-allowed-tlds-input" value="<?php echo esc_attr( $allowed_tlds ); ?>">
					
					<p class="wu-text-sm wu-text-gray-600">
						<?php esc_html_e( 'Select which TLDs customers can register with this product. Import TLDs from OpenSRS in Settings → OpenSRS.', 'wu-opensrs' ); ?>
					</p>
				</div>
				
				<!-- Pricing Model -->
				<div class="wu-mb-4">
					<label class="wu-block wu-font-semibold wu-mb-2">
						<?php esc_html_e( 'Pricing Model', 'wu-opensrs' ); ?>
					</label>
					
					<label class="wu-flex wu-items-center wu-mb-2">
						<input type="radio" name="wu_opensrs_pricing_model" value="dynamic" <?php checked( $pricing_model, 'dynamic' ); ?> class="wu-mr-2">
						<span><?php esc_html_e( 'Dynamic Pricing (Use OpenSRS prices)', 'wu-opensrs' ); ?></span>
					</label>
					
					<label class="wu-flex wu-items-center wu-mb-2">
						<input type="radio" name="wu_opensrs_pricing_model" value="fixed" <?php checked( $pricing_model, 'fixed' ); ?> class="wu-mr-2">
						<span><?php esc_html_e( 'Fixed Price (Use product price)', 'wu-opensrs' ); ?></span>
					</label>
					
					<p class="wu-text-sm wu-text-gray-600">
						<?php esc_html_e( 'Dynamic pricing fetches current prices from OpenSRS. Fixed pricing uses the product price set above.', 'wu-opensrs' ); ?>
					</p>
				</div>
				
				<!-- Auto-Renew Default -->
				<div class="wu-mb-4">
					<label class="wu-flex wu-items-center">
						<input type="checkbox" name="wu_opensrs_auto_renew_default" value="1" <?php checked( $auto_renew_default, '1' ); ?> class="wu-mr-2">
						<span class="wu-font-semibold"><?php esc_html_e( 'Enable Auto-Renew by Default', 'wu-opensrs' ); ?></span>
					</label>
					<p class="wu-text-sm wu-text-gray-600 wu-mt-1">
						<?php esc_html_e( 'Domains registered with this product will have auto-renewal enabled by default.', 'wu-opensrs' ); ?>
					</p>
				</div>
				
				<!-- WHOIS Privacy Included -->
				<div class="wu-mb-4">
					<label class="wu-flex wu-items-center">
						<input type="checkbox" name="wu_opensrs_whois_privacy_included" value="1" <?php checked( $whois_privacy_included, '1' ); ?> class="wu-mr-2">
						<span class="wu-font-semibold"><?php esc_html_e( 'Include WHOIS Privacy', 'wu-opensrs' ); ?></span>
					</label>
					<p class="wu-text-sm wu-text-gray-600 wu-mt-1">
						<?php esc_html_e( 'WHOIS privacy protection will be automatically enabled for domains registered with this product.', 'wu-opensrs' ); ?>
					</p>
				</div>
			</div>
		</div>
		
		<script>
		jQuery(document).ready(function($) {
			// Add selected TLD
			$('#wu-opensrs-add-tld').on('click', function() {
				var tld = $('#wu-opensrs-tld-selector').val();
				if (tld && !$('.wu-opensrs-tld-tag[data-tld="' + tld + '"]').length) {
					addTLD(tld);
				}
			});
			
			// Add all TLDs
			$('#wu-opensrs-add-all-tlds').on('click', function() {
				if (!confirm('<?php esc_html_e( "Add all available TLDs to this product?", "wu-opensrs" ); ?>')) {
					return;
				}
				
				$('#wu-opensrs-tld-selector option').each(function() {
					var tld = $(this).val();
					if (tld && !$('.wu-opensrs-remove-tld[data-tld="' + tld + '"]').length) {
						addTLD(tld);
					}
				});
			});
			
			// Remove TLD
			$(document).on('click', '.wu-opensrs-remove-tld', function() {
				var tld = $(this).data('tld');
				$(this).parent().remove();
				updateTLDInput();
			});
			
			function addTLD(tld) {
				var tag = '<span class="wu-opensrs-tld-tag wu-inline-flex wu-items-center wu-bg-blue-100 wu-text-blue-800 wu-px-3 wu-py-1 wu-rounded-full wu-text-sm">';
				tag += '.' + tld;
				tag += '<button type="button" class="wu-opensrs-remove-tld wu-ml-2 wu-text-blue-600 hover:wu-text-blue-800" data-tld="' + tld + '">×</button>';
				tag += '</span>';
				
				$('#wu-opensrs-selected-tlds').append(tag);
				updateTLDInput();
			}
			
			function updateTLDInput() {
				var tlds = [];
				$('.wu-opensrs-remove-tld').each(function() {
					tlds.push($(this).data('tld'));
				});
				$('#wu-opensrs-allowed-tlds-input').val(tlds.join(','));
			}
		});
		</script>
		
		<style>
		.wu-opensrs-tld-tag {
			display: inline-flex;
			align-items: center;
			background-color: #dbeafe;
			color: #1e40af;
			padding: 0.25rem 0.75rem;
			border-radius: 9999px;
			font-size: 0.875rem;
		}
		.wu-opensrs-remove-tld {
			margin-left: 0.5rem;
			color: #2563eb;
			cursor: pointer;
			border: none;
			background: none;
			font-size: 1.25rem;
			line-height: 1;
		}
		.wu-opensrs-remove-tld:hover {
			color: #1e40af;
		}
		</style>
		<?php
	}
	
	/**
	 * Save domain product settings
	 */
	public function save_domain_settings( $product_id, $product ) {
		// Only save for domain products
		if ( 'domain' !== $product->get_type() ) {
			return;
		}
		
		if ( ! current_user_can( 'manage_network' ) ) {
			return;
		}
		
		// Save allowed TLDs
		if ( isset( $_POST['wu_opensrs_allowed_tlds'] ) ) {
			$allowed_tlds = sanitize_text_field( $_POST['wu_opensrs_allowed_tlds'] );
			update_post_meta( $product_id, '_wu_opensrs_allowed_tlds', $allowed_tlds );
		}
		
		// Save pricing model
		if ( isset( $_POST['wu_opensrs_pricing_model'] ) ) {
			$pricing_model = sanitize_text_field( $_POST['wu_opensrs_pricing_model'] );
			update_post_meta( $product_id, '_wu_opensrs_pricing_model', $pricing_model );
		}
		
		// Save auto-renew default
		$auto_renew = isset( $_POST['wu_opensrs_auto_renew_default'] ) ? '1' : '0';
		update_post_meta( $product_id, '_wu_opensrs_auto_renew_default', $auto_renew );
		
		// Save WHOIS privacy included
		$whois_privacy = isset( $_POST['wu_opensrs_whois_privacy_included'] ) ? '1' : '0';
		update_post_meta( $product_id, '_wu_opensrs_whois_privacy_included', $whois_privacy );
	}
	
	/**
	 * Modify product description for domain products
	 */
	public function modify_domain_description( $description, $product ) {
		if ( 'domain' !== $product->get_type() ) {
			return $description;
		}
		
		$allowed_tlds = get_post_meta( $product->get_id(), '_wu_opensrs_allowed_tlds', true );
		$whois_privacy = get_post_meta( $product->get_id(), '_wu_opensrs_whois_privacy_included', true );
		
		$additions = array();
		
		if ( ! empty( $allowed_tlds ) ) {
			$tld_array = explode( ',', $allowed_tlds );
			$tld_count = count( $tld_array );
			$additions[] = sprintf(
				_n( '%d TLD available', '%d TLDs available', $tld_count, 'wu-opensrs' ),
				$tld_count
			);
		}
		
		if ( '1' === $whois_privacy ) {
			$additions[] = __( 'WHOIS Privacy included', 'wu-opensrs' );
		}
		
		if ( ! empty( $additions ) ) {
			$description .= '<br><small class="wu-text-gray-600">' . implode( ' • ', $additions ) . '</small>';
		}
		
		return $description;
	}
}
WU_OpenSRS_Product_Type::get_instance();
