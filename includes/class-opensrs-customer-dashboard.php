<?php
/**
 * OpenSRS Customer Dashboard
 * 
 * File: includes/class-opensrs-customer-dashboard.php
 *
 * @package WU_OpenSRS
 */

// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * OpenSRS Customer Dashboard Class
 */
class WU_OpenSRS_Customer_Dashboard {
	
	private static $instance = null;
	
	public static function get_instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}
	
	private function __construct() {
		// Add domains tab
		add_filter( 'wu_account_tabs', array( $this, 'add_domains_tab' ), 10 );
		
		// Render domains page
		add_action( 'wu_account_tab_domains', array( $this, 'render_domains_page' ) );
		
		// AJAX handlers
		add_action( 'wp_ajax_wu_opensrs_update_nameservers', array( $this, 'ajax_update_nameservers' ) );
		add_action( 'wp_ajax_wu_opensrs_toggle_whois', array( $this, 'ajax_toggle_whois' ) );
		add_action( 'wp_ajax_wu_opensrs_toggle_lock', array( $this, 'ajax_toggle_lock' ) );
		add_action( 'wp_ajax_wu_opensrs_toggle_autorenew', array( $this, 'ajax_toggle_autorenew' ) );
		add_action( 'wp_ajax_wu_opensrs_renew_domain', array( $this, 'ajax_renew_domain' ) );
	}
	
	public function add_domains_tab( $tabs ) {
		if ( ! WU_OpenSRS_Settings::is_enabled() ) {
			return $tabs;
		}
		
		$tabs['domains'] = array(
			'title' => __( 'My Domains', 'wu-opensrs' ),
			'icon' => 'dashicons-admin-site-alt3',
		);
		
		return $tabs;
	}
	
	public function render_domains_page() {
		$customer = wu_get_current_customer();
		
		if ( ! $customer ) {
			return;
		}
		
		global $wpdb;
		$table = $wpdb->prefix . 'wu_opensrs_domains';
		
		$domains = $wpdb->get_results( $wpdb->prepare(
			"SELECT * FROM $table WHERE customer_id = %d ORDER BY created_at DESC",
			$customer->get_id()
		) );
		
		?>
		<div class="wu-domains-manager">
			<h2><?php esc_html_e( 'My Domains', 'wu-opensrs' ); ?></h2>
			
			<?php if ( empty( $domains ) ) : ?>
				<div class="wu-empty-state wu-p-8 wu-text-center wu-bg-gray-50 wu-rounded">
					<p class="wu-text-gray-600"><?php esc_html_e( 'You don\'t have any domains yet.', 'wu-opensrs' ); ?></p>
				</div>
			<?php else : ?>
				<div class="wu-domains-list">
					<?php foreach ( $domains as $domain ) : ?>
						<div class="wu-domain-card wu-mb-4 wu-p-4 wu-border wu-rounded wu-bg-white">
							<div class="wu-flex wu-justify-between wu-items-start">
								<div class="wu-flex-1">
									<h3 class="wu-text-lg wu-font-semibold wu-mb-2">
										<?php echo esc_html( $domain->domain_name ); ?>
										<?php if ( 'active' === $domain->status ) : ?>
											<span class="wu-badge wu-bg-green-100 wu-text-green-800 wu-text-xs wu-px-2 wu-py-1 wu-rounded">
												<?php esc_html_e( 'Active', 'wu-opensrs' ); ?>
											</span>
										<?php endif; ?>
									</h3>
									
									<div class="wu-text-sm wu-text-gray-600 wu-space-y-1">
										<p>
											<strong><?php esc_html_e( 'Registered:', 'wu-opensrs' ); ?></strong>
											<?php echo wp_date( get_option( 'date_format' ), strtotime( $domain->registration_date ) ); ?>
										</p>
										<p>
											<strong><?php esc_html_e( 'Expires:', 'wu-opensrs' ); ?></strong>
											<?php echo wp_date( get_option( 'date_format' ), strtotime( $domain->expiration_date ) ); ?>
										</p>
									</div>
								</div>
								
								<button class="wu-button wu-button-sm wu-toggle-details" data-domain-id="<?php echo esc_attr( $domain->id ); ?>">
									<?php esc_html_e( 'Manage', 'wu-opensrs' ); ?>
								</button>
							</div>
							
							<!-- Domain Management Details -->
							<div class="wu-domain-details wu-mt-4 wu-pt-4 wu-border-t" data-domain-id="<?php echo esc_attr( $domain->id ); ?>" style="display:none;">
								
								<!-- Auto-Renew Toggle -->
								<div class="wu-mb-4">
									<h4 class="wu-font-semibold wu-mb-2"><?php esc_html_e( 'Auto-Renewal', 'wu-opensrs' ); ?></h4>
									<label class="wu-flex wu-items-center">
										<input type="checkbox" 
											class="wu-toggle-autorenew wu-mr-2"
											data-domain-id="<?php echo esc_attr( $domain->id ); ?>"
											<?php checked( $domain->auto_renew, 1 ); ?>>
										<span><?php esc_html_e( 'Automatically renew this domain before expiration', 'wu-opensrs' ); ?></span>
									</label>
									<p class="wu-text-sm wu-text-gray-600 wu-mt-1">
										<?php esc_html_e( 'When enabled, your domain will be automatically renewed before it expires.', 'wu-opensrs' ); ?>
									</p>
								</div>
								
								<!-- Nameservers -->
								<div class="wu-mb-4">
									<h4 class="wu-font-semibold wu-mb-2"><?php esc_html_e( 'Nameservers', 'wu-opensrs' ); ?></h4>
									<form class="wu-nameservers-form" data-domain-id="<?php echo esc_attr( $domain->id ); ?>">
										<?php
										$nameservers = json_decode( $domain->nameservers, true ) ?: array( '', '', '', '' );
										for ( $i = 1; $i <= 4; $i++ ) :
										?>
											<input type="text" 
												name="nameserver<?php echo $i; ?>" 
												value="<?php echo esc_attr( $nameservers[ $i - 1 ] ?? '' ); ?>"
												placeholder="ns<?php echo $i; ?>.example.com"
												class="wu-w-full wu-p-2 wu-border wu-rounded wu-mb-2">
										<?php endfor; ?>
										<button type="submit" class="wu-button wu-button-primary wu-button-sm">
											<?php esc_html_e( 'Update Nameservers', 'wu-opensrs' ); ?>
										</button>
									</form>
								</div>
								
								<!-- WHOIS Privacy -->
								<div class="wu-mb-4">
									<h4 class="wu-font-semibold wu-mb-2"><?php esc_html_e( 'WHOIS Privacy', 'wu-opensrs' ); ?></h4>
									<label class="wu-flex wu-items-center">
										<input type="checkbox" 
											class="wu-toggle-whois wu-mr-2"
											data-domain-id="<?php echo esc_attr( $domain->id ); ?>"
											<?php checked( $domain->whois_privacy, 1 ); ?>>
										<span><?php esc_html_e( 'Enable WHOIS Privacy Protection', 'wu-opensrs' ); ?></span>
									</label>
								</div>
								
								<!-- Domain Lock -->
								<div class="wu-mb-4">
									<h4 class="wu-font-semibold wu-mb-2"><?php esc_html_e( 'Domain Lock', 'wu-opensrs' ); ?></h4>
									<label class="wu-flex wu-items-center">
										<input type="checkbox" 
											class="wu-toggle-lock wu-mr-2"
											data-domain-id="<?php echo esc_attr( $domain->id ); ?>"
											<?php checked( $domain->domain_lock, 1 ); ?>>
										<span><?php esc_html_e( 'Lock domain to prevent unauthorized transfers', 'wu-opensrs' ); ?></span>
									</label>
								</div>
								
								<!-- Renewal -->
								<div class="wu-mb-4">
									<h4 class="wu-font-semibold wu-mb-2"><?php esc_html_e( 'Renewal', 'wu-opensrs' ); ?></h4>
									<?php
									$days_until_expiry = floor( ( strtotime( $domain->expiration_date ) - time() ) / DAY_IN_SECONDS );
									?>
									<p class="wu-text-sm wu-mb-2">
										<?php
										printf(
											esc_html__( 'Your domain expires in %d days', 'wu-opensrs' ),
											$days_until_expiry
										);
										?>
									</p>
									<button class="wu-button wu-button-primary wu-button-sm wu-renew-domain"
										data-domain-id="<?php echo esc_attr( $domain->id ); ?>">
										<?php esc_html_e( 'Renew Now', 'wu-opensrs' ); ?>
									</button>
								</div>
							</div>
						</div>
					<?php endforeach; ?>
				</div>
			<?php endif; ?>
		</div>
		
		<script>
		jQuery(document).ready(function($) {
			// Toggle domain details
			$('.wu-toggle-details').on('click', function() {
				var domainId = $(this).data('domain-id');
				$('.wu-domain-details[data-domain-id="' + domainId + '"]').slideToggle();
			});
			
			// Update nameservers
			$('.wu-nameservers-form').on('submit', function(e) {
				e.preventDefault();
				var form = $(this);
				var domainId = form.data('domain-id');
				var nameservers = [];
				
				form.find('input[type="text"]').each(function() {
					if ($(this).val()) nameservers.push($(this).val());
				});
				
				$.post(ajaxurl, {
					action: 'wu_opensrs_update_nameservers',
					domain_id: domainId,
					nameservers: nameservers,
					nonce: '<?php echo wp_create_nonce( "wu-opensrs-manage" ); ?>'
				}, function(response) {
					alert(response.success ? '<?php esc_html_e( "Nameservers updated", "wu-opensrs" ); ?>' : '<?php esc_html_e( "Error updating nameservers", "wu-opensrs" ); ?>');
				});
			});
			
			// Toggle auto-renew
			$('.wu-toggle-autorenew').on('change', function() {
				var checkbox = $(this);
				var domainId = checkbox.data('domain-id');
				var enabled = checkbox.is(':checked');
				
				$.post(ajaxurl, {
					action: 'wu_opensrs_toggle_autorenew',
					domain_id: domainId,
					enabled: enabled ? 1 : 0,
					nonce: '<?php echo wp_create_nonce( "wu-opensrs-manage" ); ?>'
				}, function(response) {
					if (!response.success) {
						checkbox.prop('checked', !enabled);
						alert('<?php esc_html_e( "Error updating auto-renew", "wu-opensrs" ); ?>');
					}
				});
			});
			
			// Toggle WHOIS privacy
			$('.wu-toggle-whois').on('change', function() {
				var checkbox = $(this);
				var domainId = checkbox.data('domain-id');
				var enabled = checkbox.is(':checked');
				
				$.post(ajaxurl, {
					action: 'wu_opensrs_toggle_whois',
					domain_id: domainId,
					enabled: enabled ? 1 : 0,
					nonce: '<?php echo wp_create_nonce( "wu-opensrs-manage" ); ?>'
				}, function(response) {
					if (!response.success) {
						checkbox.prop('checked', !enabled);
						alert('<?php esc_html_e( "Error updating WHOIS privacy", "wu-opensrs" ); ?>');
					}
				});
			});
			
			// Toggle domain lock
			$('.wu-toggle-lock').on('change', function() {
				var checkbox = $(this);
				var domainId = checkbox.data('domain-id');
				var locked = checkbox.is(':checked');
				
				$.post(ajaxurl, {
					action: 'wu_opensrs_toggle_lock',
					domain_id: domainId,
					locked: locked ? 1 : 0,
					nonce: '<?php echo wp_create_nonce( "wu-opensrs-manage" ); ?>'
				}, function(response) {
					if (!response.success) {
						checkbox.prop('checked', !locked);
						alert('<?php esc_html_e( "Error updating domain lock", "wu-opensrs" ); ?>');
					}
				});
			});
			
			// Renew domain
			$('.wu-renew-domain').on('click', function() {
				if (!confirm('<?php esc_html_e( "Renew this domain for 1 year?", "wu-opensrs" ); ?>')) return;
				
				var button = $(this);
				var domainId = button.data('domain-id');
				button.prop('disabled', true);
				
				$.post(ajaxurl, {
					action: 'wu_opensrs_renew_domain',
					domain_id: domainId,
					nonce: '<?php echo wp_create_nonce( "wu-opensrs-manage" ); ?>'
				}, function(response) {
					if (response.success) {
						alert('<?php esc_html_e( "Domain renewed successfully", "wu-opensrs" ); ?>');
						location.reload();
					} else {
						alert('<?php esc_html_e( "Error renewing domain", "wu-opensrs" ); ?>');
						button.prop('disabled', false);
					}
				});
			});
		});
		</script>
		<?php
	}
	
	public function ajax_toggle_autorenew() {
		check_ajax_referer( 'wu-opensrs-manage', 'nonce' );
		
		$domain_id = isset( $_POST['domain_id'] ) ? absint( $_POST['domain_id'] ) : 0;
		$enabled = isset( $_POST['enabled'] ) && '1' === $_POST['enabled'];
		
		global $wpdb;
		$table = $wpdb->prefix . 'wu_opensrs_domains';
		
		$domain = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM $table WHERE id = %d", $domain_id ) );
		
		if ( ! $domain ) {
			wp_send_json_error();
		}
		
		$customer = wu_get_current_customer();
		if ( ! $customer || $customer->get_id() !== (int) $domain->customer_id ) {
			wp_send_json_error();
		}
		
		$wpdb->update(
			$table,
			array( 'auto_renew' => $enabled ? 1 : 0 ),
			array( 'id' => $domain_id ),
			array( '%d' ),
			array( '%d' )
		);
		
		wp_send_json_success();
	}
	
	// Add similar methods for ajax_update_nameservers, ajax_toggle_whois, ajax_toggle_lock, ajax_renew_domain
	// (implementation similar to above, calling OpenSRS API functions)
}