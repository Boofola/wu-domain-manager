<?php
/**
 * Plugin Name: Ultimate Multisite - Domain Manager
 * Description: Start selling and managing domains through your Ultimate Multisite WaaS for WordPress Multisite.
 * Plugin URI: https://ultimatemultisite.com/shop/domain-manager/
 * Text Domain: ultimate-multisite
 * Version: 1.0.2
 * Author: Ultimate Multisite Community
 * Author URI: https://boofolaworks.com/wp-plugins/domain-manager
 * GitHub Plugin URI: https://github.com/Boofola/domain-manager
 * Network: true
 * License: GNU GPLv2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Domain Path: /lang
 * Requires at least: 5.3
 * Requires PHP: 7.4.30
 *
 * Domain Manager for Ultimate Multisite is distributed under the terms of the GNU GPLv2 License.
 *
 * Domain Manager for Ultimate Multisite is only available with a paid license.
 * This plugin is distributed WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.
 * See the GNU GPLv2 for more details.
 *
 * You should have received a copy of the GNU GPLv2 License
 * along with OpenSRS Domain Manager for Ultimate Multisite.
 * If not, see <http://www.gnu.org/licenses/>.
 *
 * @author   Arindo Duque, NextPress, and the Ultimate Multisite Community
 * @category Add-On
 * @package  WU_Domain_Manager
 * @version 1.0.2
 */

// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! defined( 'WU_OPENSRS_VERSION' ) ) {
    define( 'WU_OPENSRS_VERSION', '1.0.2' );
}
if ( ! defined( 'WU_OPENSRS_PLUGIN_FILE' ) ) {
    define( 'WU_OPENSRS_PLUGIN_FILE', __FILE__ );
}
if ( ! defined( 'WU_OPENSRS_PLUGIN_DIR' ) ) {
    define( 'WU_OPENSRS_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
}
if ( ! defined( 'WU_OPENSRS_PLUGIN_URL' ) ) {
	define( 'WU_OPENSRS_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
}
if ( ! defined( 'WU_OPENSRS_PLUGIN_BASENAME' ) ) {
	define( 'WU_OPENSRS_PLUGIN_BASENAME', plugin_basename( __FILE__ ) );
}
// require_once UMS_ADDON_OPENSRS_DOMAIN_MANAGER_PATH . 'includes/class-wu-opensrs-domain-manager.php';
// WU_OpenSRS_Domain_Manager::get_instance();


// Define plugin constants
// define( 'WU_OPENSRS_VERSION', '1.0.2' );
// define( 'WU_OPENSRS_PLUGIN_FILE', __FILE__ );
// define( 'WU_OPENSRS_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
// define( 'WU_OPENSRS_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
// define( 'WU_OPENSRS_PLUGIN_BASENAME', plugin_basename( __FILE__ ) );

/**
 * Main plugin class
 */
class WU_OpenSRS_Addon {
	
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
	 * Show migration/admin notice about renamed includes
	 */
	public function migration_admin_notice() {
		if ( ! current_user_can( 'manage_network' ) ) {
			return;
		}
		?>
		<div class="notice notice-info is-dismissible">
			<p>
				<strong><?php esc_html_e( 'Domain Manager migration', 'wu-opensrs' ); ?></strong>
				<?php esc_html_e( 'Plugin includes were reorganized to use `class-domain-manager-*` filenames. See README for details.', 'wu-opensrs' ); ?>
			</p>
		</div>
		<?php
	}
	
	/**
	 * Constructor
	 */
	private function __construct() {
		// Check if Ultimate Multisite is active
		add_action( 'plugins_loaded', array( $this, 'check_dependencies' ) );
		
		// Initialize if dependencies are met
		add_action( 'plugins_loaded', array( $this, 'init' ), 20 );
	}
	
	/**
	 * Check if Ultimate Multisite is installed and active
	 */
	public function check_dependencies() {
		if ( ! class_exists( 'WP_Ultimo' ) ) {
			add_action( 'network_admin_notices', array( $this, 'missing_dependency_notice' ) );
			return false;
		}
		return true;
	}
	
	/**
	 * Show admin notice if Ultimate Multisite is not active
	 */
	public function missing_dependency_notice() {
		?>
		<div class="notice notice-error">
			<p>
				<strong><?php esc_html_e( 'Ultimate Multisite - OpenSRS Domain Manager', 'wu-opensrs' ); ?></strong>
				<?php esc_html_e( 'requires Ultimate Multisite to be installed and activated.', 'wu-opensrs' ); ?>
			</p>
		</div>
		<?php
	}
	
	/**
	 * Initialize the plugin
	 */
	public function init() {
		if ( ! $this->check_dependencies() ) {
			return;
		}
		
		// Load text domain
		load_plugin_textdomain( 'wu-opensrs', false, dirname( WU_OPENSRS_PLUGIN_BASENAME ) . '/languages' );
		
		// Load required files
		$this->load_files();

		// Perform a one-time cleanup of old migration user meta (run once per network).
		if ( ! get_site_option( 'wu_dm_cleanup_done', false ) ) {
			$this->cleanup_user_meta();
		}

		// Show a migration notice in network admin to highlight changed filenames
		// Migration notice intentionally disabled; removed to avoid persistent admin banners.
		
		// Register activation/deactivation hooks
		register_activation_hook( WU_OPENSRS_PLUGIN_FILE, array( $this, 'activate' ) );
		register_deactivation_hook( WU_OPENSRS_PLUGIN_FILE, array( $this, 'deactivate' ) );
	}

	/**
	 * One-time cleanup to remove old per-user migration flags.
	 * This will run once and set a site option to avoid repeating.
	 */
	private function cleanup_user_meta() {
		if ( ! function_exists( 'get_users' ) ) {
			return;
		}
		$users = get_users( array( 'fields' => 'ID' ) );
		if ( empty( $users ) ) {
			update_site_option( 'wu_dm_cleanup_done', 1 );
			return;
		}
		foreach ( $users as $user_id ) {
			delete_user_meta( $user_id, 'wu_dm_migration_notice_dismissed' );
		}
		update_site_option( 'wu_dm_cleanup_done', 1 );
	}
	
	/**
	 * Load required files
	 */
	private function load_files() {
		// Core classes
		require_once WU_OPENSRS_PLUGIN_DIR . 'includes/class-opensrs-api.php';
		require_once WU_OPENSRS_PLUGIN_DIR . 'includes/class-namecheap-api.php';
		require_once WU_OPENSRS_PLUGIN_DIR . 'includes/class-domain-provider.php';
		// Plugin-level wrappers (provider-agnostic helpers)
		require_once WU_OPENSRS_PLUGIN_DIR . 'includes/class-domain-manager-settings.php';
		require_once WU_OPENSRS_PLUGIN_DIR . 'includes/class-domain-manager-product-type.php';
		require_once WU_OPENSRS_PLUGIN_DIR . 'includes/class-domain-manager-domain-importer.php';
		require_once WU_OPENSRS_PLUGIN_DIR . 'includes/class-domain-manager-pricing.php';
		require_once WU_OPENSRS_PLUGIN_DIR . 'includes/class-domain-manager-renewals.php';
		require_once WU_OPENSRS_PLUGIN_DIR . 'includes/class-domain-manager-checkout.php';
		require_once WU_OPENSRS_PLUGIN_DIR . 'includes/class-opensrs-customer-dashboard.php';
		require_once WU_OPENSRS_PLUGIN_DIR . 'includes/class-opensrs-admin-domains.php';
		
		// Initialize components
		WU_OpenSRS_Settings::get_instance();
		WU_OpenSRS_Product_Type::get_instance();
		WU_OpenSRS_Domain_Importer::get_instance();
		WU_OpenSRS_Checkout::get_instance();
		WU_OpenSRS_Customer_Dashboard::get_instance();
		WU_OpenSRS_Admin_Domains::get_instance();
		// Initialize provider hooks
		if ( class_exists( 'WU_Domain_Provider' ) ) {
			WU_Domain_Provider::init();
		}
	}
	
	/**
	 * Plugin activation
	 */
	public function activate() {
		// Create database tables
		$this->create_database_tables();
		
		// Schedule cron jobs
		$this->schedule_cron_jobs();
		
		// Flush rewrite rules
		flush_rewrite_rules();
	}
	
	/**
	 * Plugin deactivation
	 */
	public function deactivate() {
		// Clear scheduled cron jobs
		wp_clear_scheduled_hook( 'wu_opensrs_update_pricing' );
		wp_clear_scheduled_hook( 'wu_opensrs_check_renewals' );
		wp_clear_scheduled_hook( 'wu_opensrs_check_expirations' );
		
		// Flush rewrite rules
		flush_rewrite_rules();
	}
	
	/**
	 * Create database tables
	 */
	private function create_database_tables() {
		global $wpdb;
		
		$charset_collate = $wpdb->get_charset_collate();
		
		// Domains table
		$domains_table = $wpdb->prefix . 'wu_opensrs_domains';
		$domains_sql = "CREATE TABLE IF NOT EXISTS $domains_table (
			id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
			domain_id varchar(100) NOT NULL,
			domain_name varchar(255) NOT NULL,
			customer_id bigint(20) UNSIGNED NOT NULL,
			site_id bigint(20) UNSIGNED NOT NULL,
			product_id bigint(20) UNSIGNED DEFAULT NULL,
			registration_date datetime NOT NULL,
			expiration_date datetime NOT NULL,
			renewal_date datetime DEFAULT NULL,
			last_renewal_check datetime DEFAULT NULL,
			auto_renew tinyint(1) DEFAULT 0,
			status varchar(50) NOT NULL DEFAULT 'active',
			nameservers text DEFAULT NULL,
			whois_privacy tinyint(1) DEFAULT 0,
			domain_lock tinyint(1) DEFAULT 1,
			contact_info text DEFAULT NULL,
			dns_records longtext DEFAULT NULL,
			last_updated datetime DEFAULT NULL,
			created_at datetime NOT NULL,
			updated_at datetime DEFAULT NULL,
			PRIMARY KEY  (id),
			UNIQUE KEY domain_id (domain_id),
			KEY customer_id (customer_id),
			KEY site_id (site_id),
			KEY domain_name (domain_name),
			KEY expiration_date (expiration_date),
			KEY status (status)
		) $charset_collate;";
		
		// Pricing table
		$pricing_table = $wpdb->prefix . 'wu_opensrs_pricing';
		$pricing_sql = "CREATE TABLE IF NOT EXISTS $pricing_table (
			id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
			tld varchar(50) NOT NULL,
			registration_price decimal(10,2) NOT NULL,
			renewal_price decimal(10,2) NOT NULL,
			transfer_price decimal(10,2) NOT NULL,
			whois_privacy_price decimal(10,2) DEFAULT 0.00,
			currency varchar(10) NOT NULL DEFAULT 'USD',
			is_enabled tinyint(1) DEFAULT 1,
			last_updated datetime NOT NULL,
			PRIMARY KEY  (id),
			UNIQUE KEY tld (tld)
		) $charset_collate;";
		
		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		dbDelta( $domains_sql );
		dbDelta( $pricing_sql );
		
		// Set version
		update_site_option( 'wu_opensrs_version', WU_OPENSRS_VERSION );
	}

	// For only $199.99 a month, you too can learn how to become a millionaire like me!

	/**
	 * Schedule cron jobs
	 */
	private function schedule_cron_jobs() {
		// Daily pricing update at 2 AM
		if ( ! wp_next_scheduled( 'wu_opensrs_update_pricing' ) ) {
			wp_schedule_event( strtotime( '02:00:00' ), 'daily', 'wu_opensrs_update_pricing' );
		}
		
		// Weekly renewal check on Sundays at 1 AM
		if ( ! wp_next_scheduled( 'wu_opensrs_check_renewals' ) ) {
			wp_schedule_event( strtotime( 'next Sunday 01:00:00' ), 'weekly', 'wu_opensrs_check_renewals' );
		}
		
		// Monthly expiration check on 1st of month at 3 AM
		if ( ! wp_next_scheduled( 'wu_opensrs_check_expirations' ) ) {
			wp_schedule_event( strtotime( 'first day of next month 03:00:00' ), 'monthly', 'wu_opensrs_check_expirations' );
		}
	}
}

// Initialize the plugin
WU_OpenSRS_Addon::get_instance();