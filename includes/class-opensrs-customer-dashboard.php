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
		<div>
			<h2><?php esc_html_e( 'My Domains', 'wu-opensrs' ); ?></h2>

			<?php if ( empty( $domains ) ) : ?>
				<div>
					<p><?php esc_html_e( 'You don\'t have any domains yet.', 'wu-opensrs' ); ?></p>
				</div>
			<?php else : ?>
				<div>
					<?php foreach ( $domains as $domain ) : ?>
						<div>
							<div>
								<div>
									<h3>
										<?php echo esc_html( $domain->domain_name ); ?>
										<?php if ( 'active' === $domain->status ) : ?>
											<span><?php esc_html_e( 'Active', 'wu-opensrs' ); ?></span>
										<?php endif; ?>
									</h3>
                                    
									<div>
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

								<button class="button" data-toggle="details" data-domain-id="<?php echo esc_attr( $domain->id ); ?>">
									<?php esc_html_e( 'Manage', 'wu-opensrs' ); ?>
								</button>
							</div>

							<!-- Domain Management Details -->
							<div data-domain-details="1" data-domain-id="<?php echo esc_attr( $domain->id ); ?>" style="display:none;">
								
								<!-- Auto-Renew Toggle -->
								<div>
									<h4><?php esc_html_e( 'Auto-Renewal', 'wu-opensrs' ); ?></h4>
									<label>
										<input type="checkbox" 
											data-action="toggle-autorenew"
											data-domain-id="<?php echo esc_attr( $domain->id ); ?>"
											<?php checked( $domain->auto_renew, 1 ); ?>>
										<span><?php esc_html_e( 'Automatically renew this domain before expiration', 'wu-opensrs' ); ?></span>
									</label>
									<p class="description">
										<?php esc_html_e( 'When enabled, your domain will be automatically renewed before it expires.', 'wu-opensrs' ); ?>
									</p>
								</div>
								
								<!-- Nameservers -->
								<div>
									<h4><?php esc_html_e( 'Nameservers', 'wu-opensrs' ); ?></h4>
									<form data-action="nameservers-form" data-domain-id="<?php echo esc_attr( $domain->id ); ?>">
										<?php
										$nameservers = json_decode( $domain->nameservers, true ) ?: array( '', '', '', '' );
										for ( $i = 1; $i <= 4; $i++ ) :
										?>
												<input type="text" 
													name="nameserver<?php echo $i; ?>" 
													value="<?php echo esc_attr( $nameservers[ $i - 1 ] ?? '' ); ?>"
													placeholder="ns<?php echo $i; ?>.example.com">
										<?php endfor; ?>
										<button type="submit" class="button">
											<?php esc_html_e( 'Update Nameservers', 'wu-opensrs' ); ?>
										</button>
									</form>
								</div>
								
								<!-- WHOIS Privacy -->
								<div>
									<h4><?php esc_html_e( 'WHOIS Privacy', 'wu-opensrs' ); ?></h4>
									<label>
										<input type="checkbox" 
											data-action="toggle-whois"
											data-domain-id="<?php echo esc_attr( $domain->id ); ?>"
											<?php checked( $domain->whois_privacy, 1 ); ?>>
										<span><?php esc_html_e( 'Enable WHOIS Privacy Protection', 'wu-opensrs' ); ?></span>
									</label>
								</div>
								
								<!-- Domain Lock -->
								<div>
									<h4><?php esc_html_e( 'Domain Lock', 'wu-opensrs' ); ?></h4>
									<label>
										<input type="checkbox" 
											data-action="toggle-lock"
											data-domain-id="<?php echo esc_attr( $domain->id ); ?>"
											<?php checked( $domain->domain_lock, 1 ); ?>>
										<span><?php esc_html_e( 'Lock domain to prevent unauthorized transfers', 'wu-opensrs' ); ?></span>
									</label>
								</div>
								
								<!-- Renewal -->
								<div>
									<h4><?php esc_html_e( 'Renewal', 'wu-opensrs' ); ?></h4>
									<?php
									$days_until_expiry = floor( ( strtotime( $domain->expiration_date ) - time() ) / DAY_IN_SECONDS );
									?>
									<p>
										<?php
										printf(
											esc_html__( 'Your domain expires in %d days', 'wu-opensrs' ),
											$days_until_expiry
										);
										?>
									</p>
									<button class="button" data-action="renew-domain"
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