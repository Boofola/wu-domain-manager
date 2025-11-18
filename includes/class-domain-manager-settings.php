<?php
// Migration notice dismissed: no-op handler removed — notice disabled below.

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
		// Register network admin menu and pages for Domain Reseller Manager
		add_action( 'network_admin_menu', array( $this, 'register_admin_pages' ) );
	}

	public function register_admin_pages() {
		if ( ! current_user_can( 'manage_network' ) ) {
			return;
		}

		$cap = 'manage_network';

		// If the UMS settings renderer is available, try to attach under WP Ultimo's menu.
		if ( function_exists( 'wu_render_settings_section' ) ) {
			global $menu;
			$ums_parent = null;
			if ( is_array( $menu ) ) {
				foreach ( $menu as $m ) {
					if ( isset( $m[2] ) && false !== strpos( $m[2], 'wp-ultimo' ) ) {
						$ums_parent = $m[2];
						break;
					}
				}
			}
			// Prefer detected menu slug, fall back to guessed slug if detection failed.
			$parent = $ums_parent ? $ums_parent : 'wp-ultimo-settings';
			add_submenu_page(
				$parent,
				__( 'Domain Reseller Manager', 'wu-opensrs' ),
				__( 'Domain Reseller Manager', 'wu-opensrs' ),
				$cap,
				'wu-domain-reseller-manager',
				array( $this, 'page_overview' )
			);
		} else {
			// Fallback: create a top-level Network Admin menu so the tool is discoverable.
			add_menu_page(
				__( 'Domain Reseller Manager', 'wu-opensrs' ),
				__( 'Domain Reseller Manager', 'wu-opensrs' ),
				$cap,
				'wu-domain-reseller-manager',
				array( $this, 'page_overview' ),
				'dashicons-admin-network',
				60
			);
			// Note: WP adds the top-level page as a submenu automatically; don't duplicate.
			$parent = 'wu-domain-reseller-manager';
		}

		// Hidden/linked pages under the chosen parent
		$hidden_parent = isset( $parent ) ? $parent : 'wu-domain-reseller-manager';
		add_submenu_page( $hidden_parent, __( 'Default API', 'wu-opensrs' ), __( 'Default API', 'wu-opensrs' ), $cap, 'wu-domain-reseller-manager-default', array( $this, 'page_default_api' ) );
		add_submenu_page( $hidden_parent, __( 'OpenSRS', 'wu-opensrs' ), __( 'OpenSRS', 'wu-opensrs' ), $cap, 'wu-domain-reseller-manager-opensrs', array( $this, 'page_opensrs' ) );
		add_submenu_page( $hidden_parent, __( 'NameCheap', 'wu-opensrs' ), __( 'NameCheap', 'wu-opensrs' ), $cap, 'wu-domain-reseller-manager-namecheap', array( $this, 'page_namecheap' ) );

		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_admin_assets' ) );
	}

	public function enqueue_admin_assets( $hook ) {
		wp_enqueue_style( 'dashicons' );
	}

	public static function is_enabled() {
		$opensrs = wu_get_setting( 'opensrs_enabled', false );
		$namecheap = wu_get_setting( 'namecheap_enabled', false );
		return (bool) ( $opensrs || $namecheap );
	}

	/** Render connection test UI (button + JS) */
	public function render_connection_test() {
		if ( ! current_user_can( 'manage_network' ) ) {
			return;
		}

		$opensrs_nonce   = wp_create_nonce( 'wu-opensrs-test' );
		$namecheap_nonce = wp_create_nonce( 'wu-namecheap-test' );
		?>
		<div>
			<h3><?php esc_html_e( 'Provider Connection Test', 'wu-opensrs' ); ?></h3>
			<p><?php esc_html_e( 'Test connections to each configured provider.', 'wu-opensrs' ); ?></p>
			<p>
				<button type="button" class="button" id="wu-domain-provider-test-opensrs" data-nonce="<?php echo esc_attr( $opensrs_nonce ); ?>" data-provider="opensrs"><?php echo esc_html( __( 'Test OpenSRS', 'wu-opensrs' ) ); ?></button>
				<button type="button" class="button" id="wu-domain-provider-test-namecheap" data-nonce="<?php echo esc_attr( $namecheap_nonce ); ?>" data-provider="namecheap"><?php echo esc_html( __( 'Test NameCheap', 'wu-opensrs' ) ); ?></button>
				<span id="wu-domain-provider-test-result" style="margin-left:12px"></span>
			</p>
		</div>
		<script>
		jQuery(function($){
			var ajaxurl = '<?php echo esc_js( admin_url( "admin-ajax.php" ) ); ?>';

			$('#wu-domain-provider-test-opensrs, #wu-domain-provider-test-namecheap').on('click', function(){
				var btn = $(this);
				var provider = btn.data('provider');
				var nonce = btn.data('nonce');
				var label = provider === 'namecheap' ? <?php echo json_encode( __( 'Test NameCheap', 'wu-opensrs' ) ); ?> : <?php echo json_encode( __( 'Test OpenSRS', 'wu-opensrs' ) ); ?>;
				btn.prop('disabled', true).text(<?php echo json_encode( __( 'Testing...', 'wu-opensrs' ) ); ?>);
				$('#wu-domain-provider-test-result').text('');
				$.post(ajaxurl, {
					action: 'wu_domain_provider_test_connection',
					provider: provider,
					nonce: nonce
				}, function(res){
					if (res.success) {
						$('#wu-domain-provider-test-result').text(res.data.message).css('color','green');
					} else {
						$('#wu-domain-provider-test-result').text(res.data.message || <?php echo json_encode( __( 'Connection failed', 'wu-opensrs' ) ); ?>).css('color','red');
					}
					btn.prop('disabled', false).text(label);
				}).fail(function(){
					$('#wu-domain-provider-test-result').text(<?php echo json_encode( __( 'Connection failed', 'wu-opensrs' ) ); ?>).css('color','red');
					btn.prop('disabled', false).text(label);
				});
			});
		});
		</script>
		<?php
	}

	public function page_overview() {
		if ( ! current_user_can( 'manage_network' ) ) {
			wp_die( __( 'Permission denied.', 'wu-opensrs' ) );
		}
		echo '<div class="wrap"><h1>' . esc_html__( 'Domain Reseller Manager', 'wu-opensrs' ) . '</h1>';
		echo '<p>' . esc_html__( 'Manage domain provider connections, import TLDs, and configure provider-specific settings.', 'wu-opensrs' ) . '</p>';
		echo '</div>';
	}

	public function page_default_api() {
		if ( ! current_user_can( 'manage_network' ) ) {
			wp_die( __( 'Permission denied.', 'wu-opensrs' ) );
		}
		$current = wu_get_setting( 'wu_domain_provider_default', 'opensrs' );
		echo '<div class="wrap"><h1>' . esc_html__( 'Default Domain Provider', 'wu-opensrs' ) . '</h1>';
		echo '<form method="post">';
		echo '<p>' . esc_html__( 'Choose the default provider to use when products do not specify a provider.', 'wu-opensrs' ) . '</p>';
		echo '<select name="wu_domain_provider_default">';
		echo '<option value="opensrs" ' . selected( $current, 'opensrs', false ) . '>OpenSRS</option>';
		echo '<option value="namecheap" ' . selected( $current, 'namecheap', false ) . '>NameCheap</option>';
		echo '</select>';
		submit_button( __( 'Save Default', 'wu-opensrs' ) );
		echo '</form></div>';
	}

	public function page_opensrs() {
		if ( ! current_user_can( 'manage_network' ) ) {
			wp_die( __( 'Permission denied.', 'wu-opensrs' ) );
		}
		echo '<div class="wrap">';
		echo '<h1>' . esc_html__( 'OpenSRS Settings', 'wu-opensrs' ) . '</h1>';
		$this->register_settings();

		if ( function_exists( 'wu_render_settings_section' ) ) {
			wu_render_settings_section( 'opensrs' );
		} elseif ( function_exists( 'wu_render_settings' ) ) {
			wu_render_settings( 'opensrs' );
		} else {
			echo '<form method="post">';
			$fields = array(
				'opensrs_enabled'   => __( 'Enable OpenSRS', 'wu-opensrs' ),
				'opensrs_mode'      => __( 'API Mode', 'wu-opensrs' ),
				'opensrs_username'  => __( 'Reseller Username', 'wu-opensrs' ),
				'opensrs_api_key'   => __( 'API Key', 'wu-opensrs' ),
			);
			foreach ( $fields as $key => $label ) {
				$val = wu_get_setting( $key, '' );
				echo '<p>';
				echo '<label>' . esc_html( $label ) . '</label><br />';
				if ( $key === 'opensrs_enabled' ) {
					echo '<input type="checkbox" name="' . esc_attr( $key ) . '" value="1"' . checked( $val, '1', false ) . '> ' . esc_html__( 'Enable', 'wu-opensrs' );
				} elseif ( $key === 'opensrs_mode' ) {
					echo '<select name="' . esc_attr( $key ) . '"><option value="test"' . selected( $val, 'test', false ) . '>Test/Sandbox</option><option value="live"' . selected( $val, 'live', false ) . '>Live Production</option></select>';
				} else {
					echo '<input type="text" name="' . esc_attr( $key ) . '" value="' . esc_attr( $val ) . '" class="regular-text">';
				}
				echo '</p>';
			}
			submit_button( __( 'Save Settings', 'wu-opensrs' ) );
			echo '</form>';
			if ( class_exists( 'WU_OpenSRS_Domain_Importer' ) ) {
				WU_OpenSRS_Domain_Importer::get_instance()->render_import_section();
			}
			$this->render_connection_test_provider('opensrs');
		}
		echo '</div>';
	}

	public function page_namecheap() {
		if ( ! current_user_can( 'manage_network' ) ) {
			wp_die( __( 'Permission denied.', 'wu-opensrs' ) );
		}
		echo '<div class="wrap">';
		echo '<h1>' . esc_html__( 'NameCheap Settings', 'wu-opensrs' ) . '</h1>';
		echo '<form method="post">';
		$fields = array(
			'namecheap_enabled'  => __( 'Enable NameCheap', 'wu-opensrs' ),
			'namecheap_mode'     => __( 'NameCheap Mode', 'wu-opensrs' ),
			'namecheap_api_user' => __( 'API User', 'wu-opensrs' ),
			'namecheap_api_key'  => __( 'API Key', 'wu-opensrs' ),
			'namecheap_username' => __( 'Username', 'wu-opensrs' ),
			'namecheap_client_ip'=> __( 'Client IP', 'wu-opensrs' ),
		);
		foreach ( $fields as $key => $label ) {
			$val = wu_get_setting( $key, '' );
			echo '<p>';
			echo '<label>' . esc_html( $label ) . '</label><br />';
			if ( $key === 'namecheap_enabled' ) {
				echo '<input type="checkbox" name="' . esc_attr( $key ) . '" value="1"' . checked( $val, '1', false ) . '> ' . esc_html__( 'Enable', 'wu-opensrs' );
			} elseif ( $key === 'namecheap_mode' ) {
				echo '<select name="' . esc_attr( $key ) . '"><option value="sandbox"' . selected( $val, 'sandbox', false ) . '>Sandbox</option><option value="live"' . selected( $val, 'live', false ) . '>Live</option></select>';
			} else {
				echo '<input type="text" name="' . esc_attr( $key ) . '" value="' . esc_attr( $val ) . '" class="regular-text">';
			}
			echo '</p>';
		}
		submit_button( __( 'Save Settings', 'wu-opensrs' ) );
		echo '</form>';
		$this->render_connection_test_provider('namecheap');
		echo '</div>';
	}

	/** Render only the test button for a specific provider */
	public function render_connection_test_provider( $provider ) {
		if ( ! current_user_can( 'manage_network' ) ) {
			return;
		}
		$nonce = $provider === 'namecheap' ? wp_create_nonce( 'wu-namecheap-test' ) : wp_create_nonce( 'wu-opensrs-test' );
		$label = $provider === 'namecheap' ? __( 'Test NameCheap', 'wu-opensrs' ) : __( 'Test OpenSRS', 'wu-opensrs' );
		?>
		<div>
			<h3><?php esc_html_e( 'Provider Connection Test', 'wu-opensrs' ); ?></h3>
			<p><?php esc_html_e( 'Test connection to this provider.', 'wu-opensrs' ); ?></p>
			<p>
				<button type="button" class="button" id="wu-domain-provider-test-<?php echo esc_attr( $provider ); ?>" data-nonce="<?php echo esc_attr( $nonce ); ?>" data-provider="<?php echo esc_attr( $provider ); ?>"><?php echo esc_html( $label ); ?></button>
				<span id="wu-domain-provider-test-result" style="margin-left:12px"></span>
			</p>
		</div>
		<script>
		jQuery(function($){
			var ajaxurl = '<?php echo esc_js( admin_url( "admin-ajax.php" ) ); ?>';
			$('#wu-domain-provider-test-<?php echo esc_js( $provider ); ?>').on('click', function(){
				var btn = $(this);
				var provider = btn.data('provider');
				var nonce = btn.data('nonce');
				var label = <?php echo json_encode( $label ); ?>;
				btn.prop('disabled', true).text(<?php echo json_encode( __( 'Testing...', 'wu-opensrs' ) ); ?>);
				$('#wu-domain-provider-test-result').text('');
				$.post(ajaxurl, {
					action: 'wu_domain_provider_test_connection',
					provider: provider,
					nonce: nonce
				}, function(res){
					if (res.success) {
						$('#wu-domain-provider-test-result').text(res.data.message).css('color','green');
					} else {
						$('#wu-domain-provider-test-result').text(res.data.message || <?php echo json_encode( __( 'Connection failed', 'wu-opensrs' ) ); ?>).css('color','red');
					}
					btn.prop('disabled', false).text(label);
				}).fail(function(){
					$('#wu-domain-provider-test-result').text(<?php echo json_encode( __( 'Connection failed', 'wu-opensrs' ) ); ?>).css('color','red');
					btn.prop('disabled', false).text(label);
				});
			});
		});
		</script>
		<?php
	}

	/** Migration notice disabled. */
	public function render_migration_notice() {
		// Intentionally disabled — migration messaging removed per request.
		return;
	}

	public function register_settings() {
		// Disabled; settings are rendered via the Domain Reseller Manager pages.
		return;
	}
}
        
