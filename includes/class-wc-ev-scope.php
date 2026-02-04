<?php
/**
 * Scope Guard Logic
 * Manages plugin enable timestamp and determines which users/orders are in scope
 */

defined( 'ABSPATH' ) || exit;

class WC_EV_Scope {
	
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
		// Hook to track user creation time
		add_action( 'user_register', array( $this, 'set_user_created_at' ), 10, 1 );
	}
	
	/**
	 * Check if feature is enabled
	 */
	public function is_enabled() {
		return 'yes' === get_option( 'wc_ev_enable', 'no' );
	}
	
	/**
	 * Get enabled timestamp
	 */
	public function get_enabled_at() {
		return (int) get_option( 'wc_ev_enabled_at', 0 );
	}
	
	/**
	 * Set user created at timestamp
	 */
	public function set_user_created_at( $user_id ) {
		if ( ! $this->is_enabled() ) {
			return;
		}
		
		// Only set if not already set
		if ( ! get_user_meta( $user_id, 'wc_ev_created_at', true ) ) {
			update_user_meta( $user_id, 'wc_ev_created_at', time() );
		}
	}
	
	/**
	 * Check if user is in scope (created after feature enabled)
	 */
	public function is_user_in_scope( $user_id ) {
		if ( ! $this->is_enabled() ) {
			return false;
		}
		
		$enabled_at = $this->get_enabled_at();
		if ( ! $enabled_at ) {
			return false;
		}
		
		$user_created_at = (int) get_user_meta( $user_id, 'wc_ev_created_at', true );
		
		// If user doesn't have created_at meta, they were created before feature was enabled
		if ( ! $user_created_at ) {
			return false;
		}
		
		return $user_created_at >= $enabled_at;
	}
	
	/**
	 * Check if order is in scope (created after feature enabled)
	 */
	public function is_order_in_scope( $order ) {
		if ( ! $this->is_enabled() ) {
			return false;
		}
		
		$enabled_at = $this->get_enabled_at();
		if ( ! $enabled_at ) {
			return false;
		}
		
		if ( is_numeric( $order ) ) {
			$order = wc_get_order( $order );
		}
		
		if ( ! $order ) {
			return false;
		}
		
		$order_created = $order->get_date_created();
		if ( ! $order_created ) {
			return false;
		}
		
		return $order_created->getTimestamp() >= $enabled_at;
	}
	
	/**
	 * Check if user is verified
	 */
	public function is_user_verified( $user_id ) {
		return '1' === get_user_meta( $user_id, 'wc_ev_is_verified', true );
	}
	
	/**
	 * Mark user as verified
	 */
	public function mark_user_verified( $user_id ) {
		update_user_meta( $user_id, 'wc_ev_is_verified', '1' );
		update_user_meta( $user_id, 'wc_ev_verified_at', time() );
	}
	
	/**
	 * Check if user needs verification (in scope and not verified)
	 */
	public function user_needs_verification( $user_id ) {
		return $this->is_enabled() && 
		       $this->is_user_in_scope( $user_id ) && 
		       ! $this->is_user_verified( $user_id );
	}
}
