<?php
/**
 * Plugin Name: WooCommerce Email Verification Gate
 * Plugin URI: https://github.com/limekex/woolidate
 * Description: Adds email verification to WooCommerce customer accounts. Customers must verify their email before logging in and before orders proceed operationally.
 * Version: 1.0.0
 * Author: Woolidate
 * Author URI: https://github.com/limekex/woolidate
 * Text Domain: wc-email-verification-gate
 * Domain Path: /languages
 * Requires at least: 5.8
 * Requires PHP: 7.4
 * WC requires at least: 6.0
 * WC tested up to: 8.5
 * License: GPL v2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 */

defined( 'ABSPATH' ) || exit;

// Define plugin constants
define( 'WC_EV_VERSION', '1.0.0' );
define( 'WC_EV_PLUGIN_FILE', __FILE__ );
define( 'WC_EV_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'WC_EV_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
define( 'WC_EV_INCLUDES_DIR', WC_EV_PLUGIN_DIR . 'includes/' );
define( 'WC_EV_TEMPLATES_DIR', WC_EV_PLUGIN_DIR . 'templates/' );

/**
 * Main plugin class
 */
class WC_Email_Verification_Gate {
	
	/**
	 * Single instance
	 */
	private static $instance = null;
	
	/**
	 * Get instance
	 */
	public static function instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}
	
	/**
	 * Constructor
	 */
	private function __construct() {
		// Check if WooCommerce is active
		if ( ! $this->is_woocommerce_active() ) {
			add_action( 'admin_notices', array( $this, 'woocommerce_missing_notice' ) );
			return;
		}
		
		// Load plugin
		add_action( 'plugins_loaded', array( $this, 'init' ), 20 );
		
		// Activation/deactivation hooks
		register_activation_hook( __FILE__, array( $this, 'activate' ) );
		register_deactivation_hook( __FILE__, array( $this, 'deactivate' ) );
	}
	
	/**
	 * Check if WooCommerce is active
	 */
	private function is_woocommerce_active() {
		return in_array( 'woocommerce/woocommerce.php', apply_filters( 'active_plugins', get_option( 'active_plugins' ) ), true );
	}
	
	/**
	 * WooCommerce missing notice
	 */
	public function woocommerce_missing_notice() {
		?>
		<div class="error">
			<p><?php esc_html_e( 'WooCommerce Email Verification Gate requires WooCommerce to be installed and active.', 'wc-email-verification-gate' ); ?></p>
		</div>
		<?php
	}
	
	/**
	 * Initialize plugin
	 */
	public function init() {
		// Load text domain
		load_plugin_textdomain( 'wc-email-verification-gate', false, dirname( plugin_basename( __FILE__ ) ) . '/languages' );
		
		// Include files
		$this->includes();
		
		// Initialize components
		$this->init_components();
		
		// Register custom order status
		add_action( 'init', array( $this, 'register_order_status' ) );
		add_filter( 'wc_order_statuses', array( $this, 'add_order_status' ) );
		
		// Add HPOS compatibility declaration
		add_action( 'before_woocommerce_init', array( $this, 'declare_hpos_compatibility' ) );
	}
	
	/**
	 * Include required files
	 */
	private function includes() {
		require_once WC_EV_INCLUDES_DIR . 'class-wc-ev-scope.php';
		require_once WC_EV_INCLUDES_DIR . 'class-wc-ev-tokens.php';
		require_once WC_EV_INCLUDES_DIR . 'class-wc-ev-settings.php';
		require_once WC_EV_INCLUDES_DIR . 'class-wc-ev-login-block.php';
		require_once WC_EV_INCLUDES_DIR . 'class-wc-ev-order-gate.php';
		require_once WC_EV_INCLUDES_DIR . 'class-wc-ev-endpoints.php';
		require_once WC_EV_INCLUDES_DIR . 'class-wc-ev-emails.php';
		require_once WC_EV_INCLUDES_DIR . 'class-wc-ev-admin.php';
	}
	
	/**
	 * Initialize components
	 */
	private function init_components() {
		WC_EV_Scope::instance();
		WC_EV_Tokens::instance();
		WC_EV_Settings::instance();
		WC_EV_Login_Block::instance();
		WC_EV_Order_Gate::instance();
		WC_EV_Endpoints::instance();
		WC_EV_Emails::instance();
		WC_EV_Admin::instance();
	}
	
	/**
	 * Register custom order status
	 */
	public function register_order_status() {
		register_post_status( 'wc-await-verification', array(
			'label'                     => _x( 'Awaiting verification', 'Order status', 'wc-email-verification-gate' ),
			'public'                    => true,
			'exclude_from_search'       => false,
			'show_in_admin_all_list'    => true,
			'show_in_admin_status_list' => true,
			/* translators: %s: number of orders */
			'label_count'               => _n_noop( 'Awaiting verification <span class="count">(%s)</span>', 'Awaiting verification <span class="count">(%s)</span>', 'wc-email-verification-gate' ),
		) );
	}
	
	/**
	 * Add order status to WooCommerce statuses
	 */
	public function add_order_status( $order_statuses ) {
		$new_order_statuses = array();
		
		foreach ( $order_statuses as $key => $status ) {
			$new_order_statuses[ $key ] = $status;
			
			// Add after 'pending' status
			if ( 'wc-pending' === $key ) {
				$new_order_statuses['wc-await-verification'] = _x( 'Awaiting verification', 'Order status', 'wc-email-verification-gate' );
			}
		}
		
		return $new_order_statuses;
	}
	
	/**
	 * Declare HPOS compatibility
	 */
	public function declare_hpos_compatibility() {
		if ( class_exists( '\Automattic\WooCommerce\Utilities\FeaturesUtil' ) ) {
			\Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility( 'custom_order_tables', __FILE__, true );
		}
	}
	
	/**
	 * Plugin activation
	 */
	public function activate() {
		// Set enabled timestamp if not already set
		if ( ! get_option( 'wc_ev_enabled_at' ) ) {
			update_option( 'wc_ev_enabled_at', time() );
		}
		
		// Flush rewrite rules
		flush_rewrite_rules();
	}
	
	/**
	 * Plugin deactivation
	 */
	public function deactivate() {
		// Restore guest checkout setting if it was modified
		$previous_guest_checkout = get_option( 'wc_ev_previous_guest_checkout' );
		if ( false !== $previous_guest_checkout ) {
			update_option( 'woocommerce_enable_guest_checkout', $previous_guest_checkout );
			delete_option( 'wc_ev_previous_guest_checkout' );
		}
		
		// Flush rewrite rules
		flush_rewrite_rules();
	}
}

/**
 * Get main plugin instance
 */
function wc_ev() {
	return WC_Email_Verification_Gate::instance();
}

// Initialize plugin
wc_ev();
