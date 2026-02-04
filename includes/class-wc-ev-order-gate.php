<?php
/**
 * Order Gating
 * Handles order status gating for unverified users
 */

defined( 'ABSPATH' ) || exit;

class WC_EV_Order_Gate {
	
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
		// Hook into order creation - support both Classic and Blocks checkout
		
		// Classic checkout (primary)
		add_action( 'woocommerce_checkout_order_processed', array( $this, 'maybe_hold_order' ), 10, 3 );
		
		// WooCommerce Blocks / Store API (primary when available)
		add_action( 'woocommerce_store_api_checkout_order_processed', array( $this, 'maybe_hold_order_blocks' ), 10, 1 );
		
		// Fallback for resilience (idempotent)
		add_action( 'woocommerce_new_order', array( $this, 'maybe_hold_order_fallback' ), 10, 1 );
		
		// Handle new user registration during checkout
		add_action( 'woocommerce_created_customer', array( $this, 'handle_new_customer' ), 10, 1 );
		
		// Make await-verification orders visible in customer account
		add_filter( 'woocommerce_my_account_my_orders_query', array( $this, 'include_await_verification_in_my_orders' ), 10, 1 );
	}
	
	/**
	 * Handle new customer registration
	 */
	public function handle_new_customer( $user_id ) {
		if ( ! WC_EV_Scope::instance()->is_enabled() ) {
			return;
		}
		
		// Set as unverified
		update_user_meta( $user_id, 'wc_ev_is_verified', '0' );
		
		// Generate and send verification email
		$this->send_verification_email( $user_id );
		
		WC_EV_Settings::log( sprintf( 'New customer registered (ID: %d), verification email sent', $user_id ) );
	}
	
	/**
	 * Maybe hold order for verification (Classic checkout)
	 */
	public function maybe_hold_order( $order_id, $posted_data, $order ) {
		$this->maybe_hold_order_for_verification( $order );
	}
	
	/**
	 * Maybe hold order for verification (Blocks checkout)
	 */
	public function maybe_hold_order_blocks( $order ) {
		$this->maybe_hold_order_for_verification( $order );
	}
	
	/**
	 * Maybe hold order for verification (Fallback)
	 */
	public function maybe_hold_order_fallback( $order_id ) {
		$order = wc_get_order( $order_id );
		if ( $order ) {
			$this->maybe_hold_order_for_verification( $order );
		}
	}
	
	/**
	 * Central logic to hold order for verification (idempotent)
	 */
	private function maybe_hold_order_for_verification( $order ) {
		// Skip if not enabled
		if ( ! WC_EV_Scope::instance()->is_enabled() ) {
			return;
		}
		
		// Get customer user ID
		$user_id = $order->get_customer_id();
		
		// Skip if no user (guest order - shouldn't happen but handle gracefully)
		if ( ! $user_id ) {
			WC_EV_Settings::log( sprintf( 'Order %d: Guest order detected (unexpected), skipping verification gate', $order->get_id() ) );
			// Add admin note for visibility
			$order->add_order_note( __( 'Email verification gate: Guest order created (unexpected). Verification not enforced.', 'wc-email-verification-gate' ) );
			return;
		}
		
		// Check if user needs verification
		if ( ! WC_EV_Scope::instance()->user_needs_verification( $user_id ) ) {
			return;
		}
		
		// Check if order is in scope
		if ( ! WC_EV_Scope::instance()->is_order_in_scope( $order ) ) {
			return;
		}
		
		// Check if order is already in await-verification status (idempotency)
		if ( $order->has_status( 'await-verification' ) ) {
			return;
		}
		
		// Hold the order
		$order->update_status( 'await-verification', __( 'Order held pending email verification.', 'wc-email-verification-gate' ) );
		
		WC_EV_Settings::log( sprintf( 'Order %d: Held for verification (User ID: %d)', $order->get_id(), $user_id ) );
		
		// Only send verification email if one hasn't been sent very recently
		// This handles existing unverified users placing orders, while avoiding
		// duplicate emails during checkout registration flow
		$last_sent = (int) get_user_meta( $user_id, 'wc_ev_verification_sent_at', true );
		$time_since_last_email = time() - $last_sent;
		
		// Only send if no email sent in last 5 minutes (300 seconds)
		// For new customers, handle_new_customer() sends the email immediately,
		// so this check prevents duplicate sends during checkout
		if ( $time_since_last_email > 300 ) {
			$this->send_verification_email( $user_id, $order->get_id() );
		}
	}
	
	/**
	 * Send verification email
	 */
	private function send_verification_email( $user_id, $order_id = null ) {
		$tokens = WC_EV_Tokens::instance();
		
		// Check if can send (rate limiting)
		$can_send = $tokens->can_send_verification( $user_id );
		if ( is_wp_error( $can_send ) ) {
			WC_EV_Settings::log( sprintf( 'Cannot send verification email to user %d: %s', $user_id, $can_send->get_error_message() ), 'warning' );
			return;
		}
		
		// Generate new token
		$token = $tokens->generate_token( $user_id );
		
		// Send email
		$emails = WC()->mailer()->get_emails();
		if ( isset( $emails['WC_EV_Verification_Email'] ) ) {
			$emails['WC_EV_Verification_Email']->trigger( $user_id, $token, $order_id );
		}
		
		// Record that email was sent
		$tokens->record_verification_sent( $user_id );
	}
	
	/**
	 * Release orders for verified user
	 */
	public function release_orders( $user_id ) {
		// Get all orders with await-verification status for this user
		$orders = wc_get_orders( array(
			'customer_id' => $user_id,
			'status'      => 'await-verification',
			'limit'       => -1,
		) );
		
		if ( empty( $orders ) ) {
			return;
		}
		
		foreach ( $orders as $order ) {
			// Double-check order is in scope
			if ( ! WC_EV_Scope::instance()->is_order_in_scope( $order ) ) {
				continue;
			}
			
			// Release to processing
			$order->update_status( 'processing', __( 'Email verified; order released to processing.', 'wc-email-verification-gate' ) );
			
			WC_EV_Settings::log( sprintf( 'Order %d: Released to processing after verification (User ID: %d)', $order->get_id(), $user_id ) );
		}
	}
	
	/**
	 * Include await-verification orders in My Account orders
	 */
	public function include_await_verification_in_my_orders( $args ) {
		// Add await-verification to the status filter if it exists
		if ( isset( $args['status'] ) ) {
			// If status is an array, add our status to it
			if ( is_array( $args['status'] ) ) {
				$args['status'][] = 'await-verification';
			} else {
				// If it's a single status, convert to array and add ours
				$args['status'] = array( $args['status'], 'await-verification' );
			}
		}
		
		return $args;
	}
}
