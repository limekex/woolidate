<?php
/**
 * Login Blocking
 * Blocks unverified users from logging in and provides resend functionality
 */

defined( 'ABSPATH' ) || exit;

class WC_EV_Login_Block {
	
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
		// Block login for unverified users
		add_filter( 'authenticate', array( $this, 'block_unverified_login' ), 30, 3 );
		
		// Add resend action to login form
		add_action( 'woocommerce_login_form_end', array( $this, 'add_resend_notice' ) );
		add_action( 'login_form', array( $this, 'add_resend_notice' ) );
		
		// Handle notices
		add_action( 'template_redirect', array( $this, 'display_notices' ) );
	}
	
	/**
	 * Block unverified users from logging in
	 */
	public function block_unverified_login( $user, $username, $password ) {
		// Skip if not enabled or login blocking disabled
		if ( ! WC_EV_Scope::instance()->is_enabled() || 'yes' !== get_option( 'wc_ev_block_login', 'yes' ) ) {
			return $user;
		}
		
		// Skip if already error or not a user object
		if ( is_wp_error( $user ) || ! is_a( $user, 'WP_User' ) ) {
			return $user;
		}
		
		// Check if user needs verification
		if ( WC_EV_Scope::instance()->user_needs_verification( $user->ID ) ) {
			WC_EV_Settings::log( sprintf( 'Login blocked for unverified user ID: %d', $user->ID ) );
			
			// Store user ID in transient for resend functionality (expires in 1 hour)
			$transient_key = 'wc_ev_blocked_' . md5( $user->user_email );
			set_transient( $transient_key, $user->ID, HOUR_IN_SECONDS );
			
			// Set cookie to track transient key (secure, httponly)
			setcookie( 'wc_ev_blocked_key', $transient_key, time() + HOUR_IN_SECONDS, COOKIEPATH, COOKIE_DOMAIN, is_ssl(), true );
			
			return new WP_Error( 
				'unverified_email',
				__( '<strong>Email verification required:</strong> Please verify your email address to continue. Check your inbox for the verification link.', 'wc-email-verification-gate' )
			);
		}
		
		return $user;
	}
	
	/**
	 * Add resend notice to login form
	 */
	public function add_resend_notice() {
		// Only show if login was just blocked (cookie exists)
		$has_blocked_cookie = isset( $_COOKIE['wc_ev_blocked_key'] );
		$has_resend_param = isset( $_GET['wc_ev_resend'] );
		
		if ( $has_blocked_cookie || $has_resend_param ) {
			?>
			<p class="wc-ev-resend-notice">
				<?php esc_html_e( "Didn't receive the email?", 'wc-email-verification-gate' ); ?>
				<a href="<?php echo esc_url( $this->get_resend_url() ); ?>" class="wc-ev-resend-link">
					<?php esc_html_e( 'Resend verification email', 'wc-email-verification-gate' ); ?>
				</a>
			</p>
			<?php
		}
	}
	
	/**
	 * Get resend URL
	 */
	private function get_resend_url() {
		return add_query_arg( array(
			'wc_ev' => 'resend',
			'redirect_to' => urlencode( wc_get_page_permalink( 'myaccount' ) ),
		), home_url( '/' ) );
	}
	
	/**
	 * Display notices after redirect
	 */
	public function display_notices() {
		// Check for success/error query params
		if ( isset( $_GET['wc_ev_verified'] ) && 'success' === $_GET['wc_ev_verified'] ) {
			if ( function_exists( 'wc_add_notice' ) ) {
				wc_add_notice( __( 'Your email has been verified successfully! You can now log in.', 'wc-email-verification-gate' ), 'success' );
			}
		}
		
		if ( isset( $_GET['wc_ev_resend'] ) && 'sent' === $_GET['wc_ev_resend'] ) {
			if ( function_exists( 'wc_add_notice' ) ) {
				wc_add_notice( __( 'If your account requires verification, we have sent you an email.', 'wc-email-verification-gate' ), 'success' );
			}
		}
		
		if ( isset( $_GET['wc_ev_error'] ) ) {
			if ( function_exists( 'wc_add_notice' ) ) {
				$error_messages = array(
					'invalid' => __( 'Verification link is invalid or expired.', 'wc-email-verification-gate' ),
					'rate_limit' => __( 'Please wait before requesting another verification email.', 'wc-email-verification-gate' ),
				);
				
				$error = sanitize_text_field( $_GET['wc_ev_error'] );
				$message = isset( $error_messages[ $error ] ) ? $error_messages[ $error ] : __( 'An error occurred.', 'wc-email-verification-gate' );
				wc_add_notice( $message, 'error' );
			}
		}
	}
}
