<?php
/**
 * OpenSRS Settings
 */

class WU_OpenSRS_Settings {
	
	private static $instance = null;
	
	public static function get_instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}
	
	private function __construct() {
		add_action( 'init', array( $this, 'register_settings' ) );
		// Render connection tester after fields
		add_action( 'wu_opensrs_settings_after_fields', array( $this, 'render_connection_test' ) );
	}

	/**
	 * Check if either provider is enabled
	 *
	 * @return bool
	 */
	public static function is_enabled() {
		$opensrs = wu_get_setting( 'opensrs_enabled', false );
		$namecheap = wu_get_setting( 'namecheap_enabled', false );
		return (bool) ( $opensrs || $namecheap );
	}

	/**
	 * Render connection test UI (button + JS)
	 */
	public function render_connection_test() {
		if ( ! current_user_can( 'manage_network' ) ) {
			return;
		}
		$opensrs_nonce = wp_create_nonce( 'wu-opensrs-test' );
		$namecheap_nonce = wp_create_nonce( 'wu-namecheap-test' );
		?>
		<div class="wu-styling wu-mt-6">
			<div class="wu-p-4 wu-bg-white wu-rounded wu-border wu-shadow-sm">
				<h3 class="wu-text-lg wu-font-bold wu-mb-3"><?php esc_html_e( 'Provider Connection Test', 'wu-opensrs' ); ?></h3>
				<p class="wu-text-sm wu-text-gray-600 wu-mb-3"><?php esc_html_e( 'Test connections to each configured provider.', 'wu-opensrs' ); ?></p>
				<div class="wu-flex wu-gap-3">
					<button type="button" class="button" id="wu-domain-provider-test-opensrs" data-nonce="<?php echo esc_attr( $opensrs_nonce ); ?>" data-provider="opensrs"><?php esc_html_e( 'Test OpenSRS', 'wu-opensrs' ); ?></button>
					<button type="button" class="button" id="wu-domain-provider-test-namecheap" data-nonce="<?php echo esc_attr( $namecheap_nonce ); ?>" data-provider="namecheap"><?php esc_html_e( 'Test NameCheap', 'wu-opensrs' ); ?></button>
					<span id="wu-domain-provider-test-result" class="wu-ml-3"></span>
				</div>
			</div>
		</div>
		<script>
		jQuery(function($){
			function handleResult(res) {
				if (res.success) {
					$('#wu-domain-provider-test-result').text(res.data.message).css('color','green');
				} else {
					$('#wu-domain-provider-test-result').text(res.data.message || '<?php echo esc_js( __( 'Connection failed', 'wu-opensrs' ) ); ?>').css('color','red');
				}
			}

			$('#wu-domain-provider-test-opensrs, #wu-domain-provider-test-namecheap').on('click', function(){
				var btn = $(this);
				var provider = btn.data('provider');
				var nonce = btn.data('nonce');
				btn.prop('disabled', true).text('<?php echo esc_js( __( 'Testing...', 'wu-opensrs' ) ); ?>');
				$('#wu-domain-provider-test-result').text('');
				$.post(ajaxurl, {
					action: 'wu_domain_provider_test_connection',
					provider: provider,
					nonce: nonce
				}, function(res){
					handleResult(res);
					btn.prop('disabled', false).text(provider === 'namecheap' ? '<?php echo esc_js( __( 'Test NameCheap', 'wu-opensrs' ) ); ?>' : '<?php echo esc_js( __( 'Test OpenSRS', 'wu-opensrs' ) ); ?>');
				}).fail(function(){
					$('#wu-domain-provider-test-result').text('<?php echo esc_js( __( 'Connection failed', 'wu-opensrs' ) ); ?>').css('color','red');
					btn.prop('disabled', false).text(provider === 'namecheap' ? '<?php echo esc_js( __( 'Test NameCheap', 'wu-opensrs' ) ); ?>' : '<?php echo esc_js( __( 'Test OpenSRS', 'wu-opensrs' ) ); ?>');
				});
			});
		});
		</script>
		<?php
	}
	
	public function register_settings() {
		// Register settings section
		wu_register_settings_section( 'opensrs', array(
			'title' => __( 'OpenSRS Domain Manager', 'wu-opensrs' ),
			'desc'  => __( 'Configure OpenSRS domain registration and management.', 'wu-opensrs' ),
			'icon'  => 'dashicons-admin-site-alt3',
		) );
		
		// Enable/Disable
		wu_register_settings_field( 'opensrs', 'opensrs_enabled', array(
			'title'   => __( 'Enable OpenSRS', 'wu-opensrs' ),
			'desc'    => __( 'Enable domain registration through OpenSRS', 'wu-opensrs' ),
			'type'    => 'toggle',
			'default' => false,
		) );
		
		// API Mode
		wu_register_settings_field( 'opensrs', 'opensrs_mode', array(
			'title'   => __( 'API Mode', 'wu-opensrs' ),
			'desc'    => __( 'Test mode or live production', 'wu-opensrs' ),
			'type'    => 'select',
			'options' => array(
				'test' => __( 'Test/Sandbox', 'wu-opensrs' ),
				'live' => __( 'Live Production', 'wu-opensrs' ),
			),
			'default' => 'test',
		) );
		
		// Username
		wu_register_settings_field( 'opensrs', 'opensrs_username', array(
			'title'       => __( 'Reseller Username', 'wu-opensrs' ),
			'desc'        => __( 'Your OpenSRS reseller username', 'wu-opensrs' ),
			'type'        => 'text',
			'placeholder' => 'your_username',
		) );
		
		// API Key
		wu_register_settings_field( 'opensrs', 'opensrs_api_key', array(
			'title'       => __( 'API Key', 'wu-opensrs' ),
			'desc'        => __( 'Your OpenSRS API key', 'wu-opensrs' ),
			'type'        => 'password',
			'placeholder' => '••••••••',
		) );
		
		// Add custom import/refresh section
		do_action( 'wu_opensrs_settings_after_fields' );

		// Default provider selection
		wu_register_settings_field( 'opensrs', 'wu_domain_provider_default', array(
			'title'   => __( 'Default Domain Provider', 'wu-opensrs' ),
			'desc'    => __( 'Choose the default domain provider for domain operations.', 'wu-opensrs' ),
			'type'    => 'select',
			'options' => array(
				'opensrs' => __( 'OpenSRS', 'wu-opensrs' ),
				'namecheap' => __( 'NameCheap', 'wu-opensrs' ),
			),
			'default' => 'opensrs',
		) );

		/**
		 * NameCheap settings
		 */
		wu_register_settings_field( 'opensrs', 'namecheap_enabled', array(
			'title'   => __( 'Enable NameCheap', 'wu-opensrs' ),
			'desc'    => __( 'Enable domain registration through NameCheap', 'wu-opensrs' ),
			'type'    => 'toggle',
			'default' => false,
		) );

		wu_register_settings_field( 'opensrs', 'namecheap_mode', array(
			'title'   => __( 'NameCheap Mode', 'wu-opensrs' ),
			'desc'    => __( 'Sandbox or Live', 'wu-opensrs' ),
			'type'    => 'select',
			'options' => array(
				'sandbox' => __( 'Sandbox', 'wu-opensrs' ),
				'live' => __( 'Live', 'wu-opensrs' ),
			),
			'default' => 'sandbox',
		) );

		wu_register_settings_field( 'opensrs', 'namecheap_api_user', array(
			'title'       => __( 'API User', 'wu-opensrs' ),
			'desc'        => __( 'NameCheap API username (ApiUser)', 'wu-opensrs' ),
			'type'        => 'text',
			'placeholder' => '',
		) );

		wu_register_settings_field( 'opensrs', 'namecheap_api_key', array(
			'title'       => __( 'API Key', 'wu-opensrs' ),
			'desc'        => __( 'NameCheap API key (ApiKey)', 'wu-opensrs' ),
			'type'        => 'password',
			'placeholder' => '••••••••',
		) );

		wu_register_settings_field( 'opensrs', 'namecheap_username', array(
			'title'       => __( 'Username', 'wu-opensrs' ),
			'desc'        => __( 'NameCheap username (UserName)', 'wu-opensrs' ),
			'type'        => 'text',
			'placeholder' => '',
		) );

		wu_register_settings_field( 'opensrs', 'namecheap_client_ip', array(
			'title'       => __( 'Client IP', 'wu-opensrs' ),
			'desc'        => __( 'Your server IP authorized in NameCheap API settings', 'wu-opensrs' ),
			'type'        => 'text',
			'placeholder' => '',
		) );
	}
}
