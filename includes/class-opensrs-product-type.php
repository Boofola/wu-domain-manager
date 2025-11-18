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
		
		global $wpdb;
		$available_tlds = array();
		$table = $wpdb->prefix . 'wu_opensrs_pricing';
		if ( $wpdb ) {
			$available_tlds = $wpdb->get_col( "SELECT tld FROM $table WHERE is_enabled = 1 ORDER BY tld ASC" );
		}
		
		?>
		<div class="postbox">
			<h2 class="hndle"><span><?php esc_html_e( 'Domain Product Settings', 'wu-opensrs' ); ?></span></h2>
			<div class="inside">
				<!-- Allowed TLDs -->
				<p>
					<label for="wu-opensrs-tld-selector"><?php esc_html_e( 'Allowed TLDs', 'wu-opensrs' ); ?></label><br />
					<select id="wu-opensrs-tld-selector">
						<option value=""><?php esc_html_e( 'Select TLDs to add...', 'wu-opensrs' ); ?></option>
						<?php foreach ( (array) $available_tlds as $tld_obj ) : ?>
							<option value="<?php echo esc_attr( $tld_obj ); ?>"><?php echo esc_html( '.' . $tld_obj ); ?></option>
						<?php endforeach; ?>
					</select>
					<button type="button" id="wu-opensrs-add-tld" class="button"><?php esc_html_e( 'Add TLD', 'wu-opensrs' ); ?></button>
					<button type="button" id="wu-opensrs-add-all-tlds" class="button"><?php esc_html_e( 'Add All', 'wu-opensrs' ); ?></button>
				</p>
				
				<div id="wu-opensrs-selected-tlds">
					<?php
					if ( ! empty( $allowed_tlds ) ) {
						$tlds = array_filter( array_map( 'trim', explode( ',', $allowed_tlds ) ) );
						foreach ( $tlds as $tld ) {
							if ( empty( $tld ) ) {
								continue;
							}
							?>
							<span class="opensrs-tld-tag">.<?php echo esc_html( $tld ); ?> <button type="button" class="opensrs-remove-tld" data-tld="<?php echo esc_attr( $tld ); ?>">&times;</button></span>
							<?php
						}
					}
					?>
				</div>
				<input type="hidden" name="wu_opensrs_allowed_tlds" id="wu-opensrs-allowed-tlds-input" value="<?php echo esc_attr( $allowed_tlds ); ?>">
				<p class="description"><?php esc_html_e( 'Select which TLDs customers can register with this product. Import TLDs from OpenSRS in Settings → OpenSRS.', 'wu-opensrs' ); ?></p>
				
				<!-- Pricing model -->
				<p>
					<label><?php esc_html_e( 'Pricing Model', 'wu-opensrs' ); ?></label><br />
					<label><input type="radio" name="wu_opensrs_pricing_model" value="fixed" <?php checked( $pricing_model, 'fixed' ); ?>> <?php esc_html_e( 'Fixed Price (Use product price)', 'wu-opensrs' ); ?></label>
					<label><input type="radio" name="wu_opensrs_pricing_model" value="dynamic" <?php checked( $pricing_model, 'dynamic' ); ?>> <?php esc_html_e( 'Dynamic Pricing (Use OpenSRS prices)', 'wu-opensrs' ); ?></label>
				</p>
				<p class="description"><?php esc_html_e( 'Dynamic pricing fetches current prices from OpenSRS. Fixed pricing uses the product price set above.', 'wu-opensrs' ); ?></p>
				
				<!-- Auto-Renew Default -->
				<p>
					<label><input type="checkbox" name="wu_opensrs_auto_renew_default" value="1" <?php checked( $auto_renew_default, '1' ); ?>> <?php esc_html_e( 'Enable Auto-Renew by Default', 'wu-opensrs' ); ?></label>
					<p class="description"><?php esc_html_e( 'Domains registered with this product will have auto-renewal enabled by default.', 'wu-opensrs' ); ?></p>
				</p>
				
				<!-- WHOIS Privacy Included -->
				<p>
					<label><input type="checkbox" name="wu_opensrs_whois_privacy_included" value="1" <?php checked( $whois_privacy_included, '1' ); ?>> <?php esc_html_e( 'Include WHOIS Privacy', 'wu-opensrs' ); ?></label>
					<p class="description"><?php esc_html_e( 'WHOIS privacy protection will be automatically enabled for domains registered with this product.', 'wu-opensrs' ); ?></p>
				</p>
				
				<!-- Provider Selection -->
				<p>
					<label for="wu-domain-provider-select"><?php esc_html_e( 'Domain Provider', 'wu-opensrs' ); ?></label><br />
					<select name="wu_domain_provider" id="wu-domain-provider-select">
						<option value=""><?php esc_html_e( 'Use default provider (settings)', 'wu-opensrs' ); ?></option>
						<option value="opensrs" <?php selected( get_post_meta( $product->get_id(), '_wu_domain_provider', true ), 'opensrs' ); ?>><?php esc_html_e( 'OpenSRS', 'wu-opensrs' ); ?></option>
						<option value="namecheap" <?php selected( get_post_meta( $product->get_id(), '_wu_domain_provider', true ), 'namecheap' ); ?>><?php esc_html_e( 'NameCheap', 'wu-opensrs' ); ?></option>
					</select>
					<p class="description"><?php esc_html_e( 'Select which provider should handle domain operations for this product. Leave empty to use the global default.', 'wu-opensrs' ); ?></p>
				</p>
			</div>
		</div>
		
		<script>
		jQuery(document).ready(function($) {
			$('#wu-opensrs-add-tld').on('click', function() {
				var tld = $('#wu-opensrs-tld-selector').val();
				if (tld && !$('.opensrs-tld-tag[data-tld="' + tld + '"]').length) {
					addTLD(tld);
				}
			});
			
			$('#wu-opensrs-add-all-tlds').on('click', function() {
				if (!confirm('<?php esc_html_e( "Add all available TLDs to this product?", "wu-opensrs" ); ?>')) {
					return;
				}
				$('#wu-opensrs-tld-selector option').each(function() {
					var tld = $(this).val();
					if (tld && !$('.opensrs-remove-tld[data-tld="' + tld + '"]').length) {
						addTLD(tld);
					}
				});
			});
			
			$(document).on('click', '.opensrs-remove-tld', function() {
				var tld = $(this).data('tld');
				$(this).parent().remove();
				updateTLDInput();
			});
			
			function addTLD(tld) {
				var tag = '<span class="opensrs-tld-tag" data-tld="' + tld + '">';
				tag += '.' + tld;
				tag += ' <button type="button" class="opensrs-remove-tld" data-tld="' + tld + '">&times;</button>';
				tag += '</span>';
				
				$('#wu-opensrs-selected-tlds').append(tag);
				updateTLDInput();
			}
			
			function updateTLDInput() {
				var tlds = [];
				$('.opensrs-remove-tld').each(function() {
					tlds.push($(this).data('tld'));
				});
				$('#wu-opensrs-allowed-tlds-input').val(tlds.join(','));
			}
		});
		</script>
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

		// Save provider selection
		if ( isset( $_POST['wu_domain_provider'] ) ) {
			$provider = sanitize_text_field( $_POST['wu_domain_provider'] );
			if ( empty( $provider ) ) {
				delete_post_meta( $product_id, '_wu_domain_provider' );
			} else {
				update_post_meta( $product_id, '_wu_domain_provider', $provider );
			}
		}
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
