<?php
/**
 * Verification Endpoints
 * Handles verification and resend endpoints
 */

defined( 'ABSPATH' ) || exit;

class WC_EV_Endpoints {
	
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
		// Handle verification and resend endpoints
		add_action( 'template_redirect', array( $this, 'handle_endpoints' ), 1 );
	}
	
	/**
	 * Handle verification and resend endpoints
	 */
	public function handle_endpoints() {
		if ( ! isset( $_GET['wc_ev'] ) ) {
			return;
		}
		
		$action = sanitize_text_field( $_GET['wc_ev'] );
		
		switch ( $action ) {
			case 'verify':
				$this->handle_verify();
				break;
				
			case 'resend':
				$this->handle_resend();
				break;
		}
	}
	
	/**
	 * Handle verification endpoint
	 */
	private function handle_verify() {
		// Get and validate parameters
		if ( ! isset( $_GET['uid'] ) || ! isset( $_GET['token'] ) ) {
			$this->redirect_with_error( 'invalid' );
			return;
		}
		
		$user_id = absint( $_GET['uid'] );
		$token = sanitize_text_field( $_GET['token'] );
		
		// Validate user exists
		$user = get_user_by( 'id', $user_id );
		if ( ! $user ) {
			$this->redirect_with_error( 'invalid' );
			return;
		}
		
		// Check if user is in scope
		if ( ! WC_EV_Scope::instance()->is_user_in_scope( $user_id ) ) {
			$this->redirect_with_error( 'invalid' );
			return;
		}
		
		// Check if already verified
		if ( WC_EV_Scope::instance()->is_user_verified( $user_id ) ) {
			// Already verified - redirect to success
			$this->redirect_with_success();
			return;
		}
		
		// Validate token
		$tokens = WC_EV_Tokens::instance();
		$validation = $tokens->validate_token( $user_id, $token );
		
		if ( is_wp_error( $validation ) ) {
			WC_EV_Settings::log( sprintf( 'Token validation failed for user %d: %s', $user_id, $validation->get_error_message() ), 'warning' );
			$this->redirect_with_error( 'invalid' );
			return;
		}
		
		// Mark user as verified
		WC_EV_Scope::instance()->mark_user_verified( $user_id );
		$tokens->mark_token_used( $user_id );
		
		WC_EV_Settings::log( sprintf( 'User %d successfully verified', $user_id ) );
		
		// Release held orders
		WC_EV_Order_Gate::instance()->release_orders( $user_id );
		
		// Redirect to success
		$this->redirect_with_success();
	}
	
	/**
	 * Handle resend endpoint
	 */
	private function handle_resend() {
		$user_id = null;
		
		// Try to get user ID from transient (after blocked login)
		if ( isset( $_COOKIE['wc_ev_blocked_key'] ) ) {
			$transient_key = sanitize_text_field( $_COOKIE['wc_ev_blocked_key'] );
			$user_id = get_transient( $transient_key );
			
			if ( $user_id ) {
				$user_id = absint( $user_id );
				// Clear transient and cookie
				delete_transient( $transient_key );
				setcookie( 'wc_ev_blocked_key', '', time() - 3600, COOKIEPATH, COOKIE_DOMAIN, is_ssl(), true );
			}
		}
		
		// If currently logged in and needs verification
		if ( ! $user_id && is_user_logged_in() ) {
			$current_user_id = get_current_user_id();
			if ( WC_EV_Scope::instance()->user_needs_verification( $current_user_id ) ) {
				$user_id = $current_user_id;
			}
		}
		
		// Generic response to avoid account enumeration
		$redirect_url = isset( $_GET['redirect_to'] ) ? esc_url_raw( $_GET['redirect_to'] ) : wc_get_page_permalink( 'myaccount' );
		
		if ( ! $user_id ) {
			// Always show generic success message even if no valid user
			wp_safe_redirect( add_query_arg( 'wc_ev_resend', 'sent', $redirect_url ) );
			exit;
		}
		
		// Check rate limiting
		$tokens = WC_EV_Tokens::instance();
		$can_send = $tokens->can_send_verification( $user_id );
		
		if ( is_wp_error( $can_send ) ) {
			WC_EV_Settings::log( sprintf( 'Resend rate limited for user %d: %s', $user_id, $can_send->get_error_message() ), 'warning' );
			wp_safe_redirect( add_query_arg( 'wc_ev_error', 'rate_limit', $redirect_url ) );
			exit;
		}
		
		// Rotate token (invalidate old, generate new)
		$token = $tokens->rotate_token( $user_id );
		
		// Send verification email
		$emails = WC()->mailer()->get_emails();
		if ( isset( $emails['WC_EV_Verification_Email'] ) ) {
			$emails['WC_EV_Verification_Email']->trigger( $user_id, $token );
		}
		
		// Record that email was sent
		$tokens->record_verification_sent( $user_id );
		
		WC_EV_Settings::log( sprintf( 'Verification email resent to user %d', $user_id ) );
		
		// Redirect with generic success message
		wp_safe_redirect( add_query_arg( 'wc_ev_resend', 'sent', $redirect_url ) );
		exit;
	}
	
	/**
	 * Redirect to My Account with success message
	 */
	private function redirect_with_success() {
		$redirect_url = wc_get_page_permalink( 'myaccount' );
		wp_safe_redirect( add_query_arg( 'wc_ev_verified', 'success', $redirect_url ) );
		exit;
	}
	
	/**
	 * Redirect to My Account with error message
	 */
	private function redirect_with_error( $error_code ) {
		$redirect_url = wc_get_page_permalink( 'myaccount' );
		wp_safe_redirect( add_query_arg( 'wc_ev_error', $error_code, $redirect_url ) );
		exit;
	}
}
