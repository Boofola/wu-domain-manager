<?php
// AJAX handler for dismissing migration notice
add_action( 'wp_ajax_wu_dm_dismiss_migration_notice', function() {
	check_ajax_referer( 'wu_dm_dismiss_notice', 'nonce' );
	if ( ! current_user_can( 'manage_network' ) ) {
		wp_send_json_error();
	}
	update_user_meta( get_current_user_id(), 'wu_dm_migration_notice_dismissed', 1 );
	wp_send_json_success();
} );
/**
 * Domain Manager Settings
 *
 * Wrapper for plugin settings (provider selection, API credentials)
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
		// Remove old settings registration from UMS Settings section
		// add_action( 'init', array( $this, 'register_settings' ) );
		// Register network admin menu and pages for Domain Reseller Manager
		add_action( 'network_admin_menu', array( $this, 'register_admin_pages' ) );
	}

	/**
	 * Register network admin pages for Domain Reseller Manager
	 */
	public function register_admin_pages() {
		if ( ! current_user_can( 'manage_network' ) ) {
			return;
		}

		$cap = 'manage_network';
		$parent_slug = 'wp-ultimo-settings'; // Parent is Ultimate Multisite main menu

		// Add as last submenu under Ultimate Multisite (visible)
		add_submenu_page(
			$parent_slug,
			__( 'Domain Reseller Manager', 'wu-opensrs' ),
			__( 'Domain Reseller Manager', 'wu-opensrs' ),
			$cap,
			'wu-domain-reseller-manager',
			array( $this, 'page_overview' )
		);

		// Sub-pages (as hidden pages, accessible via links/tabs)
		add_submenu_page(
			null,
			__( 'Default API', 'wu-opensrs' ),
			__( 'Default API', 'wu-opensrs' ),
			$cap,
			'wu-domain-reseller-manager-default',
			array( $this, 'page_default_api' )
		);
		add_submenu_page(
			null,
			__( 'OpenSRS', 'wu-opensrs' ),
			__( 'OpenSRS', 'wu-opensrs' ),
			$cap,
			'wu-domain-reseller-manager-opensrs',
			array( $this, 'page_opensrs' )
		);
		add_submenu_page(
			null,
			__( 'NameCheap', 'wu-opensrs' ),
			__( 'NameCheap', 'wu-opensrs' ),
			$cap,
			'wu-domain-reseller-manager-namecheap',
			array( $this, 'page_namecheap' )
		);

		// Enqueue Dashicons and admin styles on our pages
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_admin_assets' ) );
	}

	/**
	 * Enqueue admin assets needed for network admin pages
	 */
	public function enqueue_admin_assets( $hook ) {
		// Ensure Dashicons are available on our admin pages
		wp_enqueue_style( 'dashicons' );
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
			if ( typeof ajaxurl === 'undefined' ) {
				var ajaxurl = '<?php echo esc_js( admin_url( "admin-ajax.php" ) ); ?>';
			}
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

	/**
	 * Top-level overview page (simple landing)
	 */
	public function page_overview() {
		if ( ! current_user_can( 'manage_network' ) ) {
			wp_die( __( 'Permission denied.', 'wu-opensrs' ) );
		}
		echo '<div class="wrap"><h1>' . esc_html__( 'Domain Reseller Manager', 'wu-opensrs' ) . '</h1>';
		echo '<p>' . esc_html__( 'Manage domain provider connections, import TLDs, and configure provider-specific settings.', 'wu-opensrs' ) . '</p>';
		echo '</div>';
	}

	/**
	 * Default API selection page
	 */
	public function page_default_api() {
		if ( ! current_user_can( 'manage_network' ) ) {
			wp_die( __( 'Permission denied.', 'wu-opensrs' ) );
		}
		echo '<div class="wrap"><h1>' . esc_html__( 'Default Domain Provider', 'wu-opensrs' ) . '</h1>';
		echo '<form method="post">';
		// Render the registered default provider field manually
		$current = wu_get_setting( 'wu_domain_provider_default', 'opensrs' );
		echo '<p>' . esc_html__( 'Choose the default provider to use when products do not specify a provider.', 'wu-opensrs' ) . '</p>';
		echo '<select name="wu_domain_provider_default">';
		echo '<option value="opensrs" ' . selected( $current, 'opensrs', false ) . '>OpenSRS</option>';
		echo '<option value="namecheap" ' . selected( $current, 'namecheap', false ) . '>NameCheap</option>';
		echo '</select>';
		submit_button( __( 'Save Default', 'wu-opensrs' ) );
		echo '</form></div>';
	}

	/**
	 * OpenSRS settings page (includes importer + connection test)
	 */
	public function page_opensrs() {
		if ( ! current_user_can( 'manage_network' ) ) {
			wp_die( __( 'Permission denied.', 'wu-opensrs' ) );
		}
		echo '<div class="wrap wu-ums-settings">';
		echo '<h1>' . esc_html__( 'OpenSRS Settings', 'wu-opensrs' ) . '</h1>';
		// Render OpenSRS settings fields styled like UMS settings
		echo '<form method="post">';
		// Render each OpenSRS field (simulate UMS settings style)
		$fields = array(
			'opensrs_enabled' => __( 'Enable OpenSRS', 'wu-opensrs' ),
			'opensrs_mode' => __( 'API Mode', 'wu-opensrs' ),
			'opensrs_username' => __( 'Reseller Username', 'wu-opensrs' ),
			'opensrs_api_key' => __( 'API Key', 'wu-opensrs' ),
		);
		foreach ( $fields as $key => $label ) {
			$val = wu_get_setting( $key, '' );
			echo '<div class="wu-field wu-mb-4">';
			echo '<label class="wu-block wu-font-semibold wu-mb-2">' . esc_html( $label ) . '</label>';
			if ( $key === 'opensrs_enabled' ) {
				echo '<input type="checkbox" name="' . esc_attr( $key ) . '" value="1"' . checked( $val, '1', false ) . '> ' . esc_html__( 'Enable', 'wu-opensrs' );
			} elseif ( $key === 'opensrs_mode' ) {
				echo '<select name="' . esc_attr( $key ) . '"><option value="test"' . selected( $val, 'test', false ) . '>Test/Sandbox</option><option value="live"' . selected( $val, 'live', false ) . '>Live Production</option></select>';
			} else {
				echo '<input type="text" name="' . esc_attr( $key ) . '" value="' . esc_attr( $val ) . '" class="regular-text">';
			}
			echo '</div>';
		}
		submit_button( __( 'Save Settings', 'wu-opensrs' ) );
		echo '</form>';
		// Render importer and only OpenSRS test button
		if ( class_exists( 'WU_OpenSRS_Domain_Importer' ) ) {
			WU_OpenSRS_Domain_Importer::get_instance()->render_import_section();
		}
		// Only show OpenSRS test button
		$this->render_connection_test_provider('opensrs');
		echo '</div>';
	}

	/**
	 * NameCheap settings page (connection test)
	 */
	public function page_namecheap() {
		if ( ! current_user_can( 'manage_network' ) ) {
			wp_die( __( 'Permission denied.', 'wu-opensrs' ) );
		}
		echo '<div class="wrap wu-ums-settings">';
		echo '<h1>' . esc_html__( 'NameCheap Settings', 'wu-opensrs' ) . '</h1>';
		// Render NameCheap settings fields styled like UMS settings
		echo '<form method="post">';
		$fields = array(
			'namecheap_enabled' => __( 'Enable NameCheap', 'wu-opensrs' ),
			'namecheap_mode' => __( 'NameCheap Mode', 'wu-opensrs' ),
			'namecheap_api_user' => __( 'API User', 'wu-opensrs' ),
			'namecheap_api_key' => __( 'API Key', 'wu-opensrs' ),
			'namecheap_username' => __( 'Username', 'wu-opensrs' ),
			'namecheap_client_ip' => __( 'Client IP', 'wu-opensrs' ),
		);
		foreach ( $fields as $key => $label ) {
			$val = wu_get_setting( $key, '' );
			echo '<div class="wu-field wu-mb-4">';
			echo '<label class="wu-block wu-font-semibold wu-mb-2">' . esc_html( $label ) . '</label>';
			if ( $key === 'namecheap_enabled' ) {
				echo '<input type="checkbox" name="' . esc_attr( $key ) . '" value="1"' . checked( $val, '1', false ) . '> ' . esc_html__( 'Enable', 'wu-opensrs' );
			} elseif ( $key === 'namecheap_mode' ) {
				echo '<select name="' . esc_attr( $key ) . '"><option value="sandbox"' . selected( $val, 'sandbox', false ) . '>Sandbox</option><option value="live"' . selected( $val, 'live', false ) . '>Live</option></select>';
			} else {
				echo '<input type="text" name="' . esc_attr( $key ) . '" value="' . esc_attr( $val ) . '" class="regular-text">';
			}
			echo '</div>';
		}
		submit_button( __( 'Save Settings', 'wu-opensrs' ) );
		echo '</form>';
		// Only show NameCheap test button
		$this->render_connection_test_provider('namecheap');
		echo '</div>';
	}
	/**
	 * Render only the test button for a specific provider
	 */
	public function render_connection_test_provider( $provider ) {
		if ( ! current_user_can( 'manage_network' ) ) {
			return;
		}
		$nonce = $provider === 'namecheap' ? wp_create_nonce( 'wu-namecheap-test' ) : wp_create_nonce( 'wu-opensrs-test' );
		$label = $provider === 'namecheap' ? __( 'Test NameCheap', 'wu-opensrs' ) : __( 'Test OpenSRS', 'wu-opensrs' );
		?>
		<div class="wu-styling wu-mt-6">
			<div class="wu-p-4 wu-bg-white wu-rounded wu-border wu-shadow-sm">
				<h3 class="wu-text-lg wu-font-bold wu-mb-3"><?php esc_html_e( 'Provider Connection Test', 'wu-opensrs' ); ?></h3>
				<p class="wu-text-sm wu-text-gray-600 wu-mb-3"><?php esc_html_e( 'Test connection to this provider.', 'wu-opensrs' ); ?></p>
				<div class="wu-flex wu-gap-3">
					<button type="button" class="button" id="wu-domain-provider-test-<?php echo esc_attr( $provider ); ?>" data-nonce="<?php echo esc_attr( $nonce ); ?>" data-provider="<?php echo esc_attr( $provider ); ?>"><?php echo esc_html( $label ); ?></button>
					<span id="wu-domain-provider-test-result" class="wu-ml-3"></span>
				</div>
			</div>
		</div>
		<script>
		jQuery(function($){
			if ( typeof ajaxurl === 'undefined' ) {
				var ajaxurl = '<?php echo esc_js( admin_url( "admin-ajax.php" ) ); ?>';
			}
			$('#wu-domain-provider-test-<?php echo esc_js( $provider ); ?>').on('click', function(){
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
					if (res.success) {
						$('#wu-domain-provider-test-result').text(res.data.message).css('color','green');
					} else {
						$('#wu-domain-provider-test-result').text(res.data.message || '<?php echo esc_js( __( 'Connection failed', 'wu-opensrs' ) ); ?>').css('color','red');
					}
					btn.prop('disabled', false).text('<?php echo esc_js( $label ); ?>');
				}).fail(function(){
					$('#wu-domain-provider-test-result').text('<?php echo esc_js( __( 'Connection failed', 'wu-opensrs' ) ); ?>').css('color','red');
					btn.prop('disabled', false).text('<?php echo esc_js( $label ); ?>');
				});
			});
		});
		</script>
		<?php
	}

	/**
	 * Render a migration notice on the settings page to inform admins about filename changes
	 */
	public function render_migration_notice() {
		if ( ! current_user_can( 'manage_network' ) ) {
			return;
		}
		// Use user meta to persist dismissal
		$user_id = get_current_user_id();
		$dismissed = get_user_meta( $user_id, 'wu_dm_migration_notice_dismissed', true );
		if ( $dismissed ) {
			return;
		}
		$ajax_url = admin_url( 'admin-ajax.php' );
		?>
		<div class="notice notice-info is-dismissible wu-dm-migration-notice">
			<p>
				<strong><?php esc_html_e( 'Domain Manager update:', 'wu-opensrs' ); ?></strong>
				<?php esc_html_e( 'Includes were reorganized to use clearer `class-domain-manager-*` filenames. See the README for migration details.', 'wu-opensrs' ); ?>
			</p>
		</div>
		<script>
		jQuery(function($){
			$(document).on('click', '.wu-dm-migration-notice .notice-dismiss', function(){
				$.post('<?php echo esc_js( $ajax_url ); ?>', {
					action: 'wu_dm_dismiss_migration_notice',
					nonce: '<?php echo esc_js( wp_create_nonce( 'wu_dm_dismiss_notice' ) ); ?>'
				});
			});
		});
		</script>
		<?php
	}

	public function register_settings() {
		// Disabled: settings are now managed under the Domain Reseller Manager admin pages.
		return;
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
