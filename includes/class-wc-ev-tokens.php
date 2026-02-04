<?php
/**
 * Token Generation and Validation
 * Handles secure token generation, hashing, validation, and rotation
 */

defined( 'ABSPATH' ) || exit;

class WC_EV_Tokens {
	
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
	private function __construct() {}
	
	/**
	 * Generate a new verification token for user
	 * 
	 * @param int $user_id User ID
	 * @return string The plaintext token (not stored)
	 */
	public function generate_token( $user_id ) {
		// Generate random token
		$token = $this->generate_random_token();
		
		// Hash the token using HMAC SHA-256
		$token_hash = $this->hash_token( $token );
		
		// Get token validity from settings
		$token_validity_hours = (int) get_option( 'wc_ev_token_validity', 24 );
		$expires_at = time() + ( $token_validity_hours * HOUR_IN_SECONDS );
		
		// Store hashed token and expiry
		update_user_meta( $user_id, 'wc_ev_token_hash', $token_hash );
		update_user_meta( $user_id, 'wc_ev_token_expires_at', $expires_at );
		update_user_meta( $user_id, 'wc_ev_token_used_at', null );
		
		return $token;
	}
	
	/**
	 * Generate random token
	 * 
	 * @return string Base64url encoded token
	 */
	private function generate_random_token() {
		$bytes = random_bytes( 32 );
		// Use base64url encoding (URL-safe)
		return rtrim( strtr( base64_encode( $bytes ), '+/', '-_' ), '=' );
	}
	
	/**
	 * Hash token using HMAC SHA-256
	 * 
	 * @param string $token Plaintext token
	 * @return string Hashed token
	 */
	private function hash_token( $token ) {
		// Use WordPress AUTH_SALT as key
		$key = defined( 'AUTH_SALT' ) ? AUTH_SALT : 'wc-ev-default-salt';
		return hash_hmac( 'sha256', $token, $key );
	}
	
	/**
	 * Validate token for user
	 * 
	 * @param int $user_id User ID
	 * @param string $token Plaintext token to validate
	 * @return bool|WP_Error True if valid, WP_Error on failure
	 */
	public function validate_token( $user_id, $token ) {
		// Get stored token hash
		$stored_hash = get_user_meta( $user_id, 'wc_ev_token_hash', true );
		if ( empty( $stored_hash ) ) {
			return new WP_Error( 'invalid_token', __( 'Invalid verification token.', 'wc-email-verification-gate' ) );
		}
		
		// Hash the provided token
		$token_hash = $this->hash_token( $token );
		
		// Constant-time comparison
		if ( ! hash_equals( $stored_hash, $token_hash ) ) {
			return new WP_Error( 'invalid_token', __( 'Invalid verification token.', 'wc-email-verification-gate' ) );
		}
		
		// Check if already used
		$used_at = get_user_meta( $user_id, 'wc_ev_token_used_at', true );
		if ( ! empty( $used_at ) ) {
			return new WP_Error( 'token_already_used', __( 'This verification link has already been used.', 'wc-email-verification-gate' ) );
		}
		
		// Check if expired
		$expires_at = (int) get_user_meta( $user_id, 'wc_ev_token_expires_at', true );
		if ( time() > $expires_at ) {
			return new WP_Error( 'token_expired', __( 'This verification link has expired.', 'wc-email-verification-gate' ) );
		}
		
		return true;
	}
	
	/**
	 * Mark token as used
	 * 
	 * @param int $user_id User ID
	 */
	public function mark_token_used( $user_id ) {
		update_user_meta( $user_id, 'wc_ev_token_used_at', time() );
		// Optionally clear the hash for extra security
		delete_user_meta( $user_id, 'wc_ev_token_hash' );
	}
	
	/**
	 * Rotate token (invalidate old, generate new)
	 * 
	 * @param int $user_id User ID
	 * @return string New plaintext token
	 */
	public function rotate_token( $user_id ) {
		// Mark old token as used (invalidates it)
		$this->mark_token_used( $user_id );
		
		// Generate new token
		return $this->generate_token( $user_id );
	}
	
	/**
	 * Check if user can receive verification email (cooldown + daily cap)
	 * 
	 * @param int $user_id User ID
	 * @return bool|WP_Error True if can send, WP_Error if rate limited
	 */
	public function can_send_verification( $user_id ) {
		$now = time();
		
		// Check cooldown
		$cooldown_seconds = (int) get_option( 'wc_ev_resend_cooldown', 60 );
		$last_sent = (int) get_user_meta( $user_id, 'wc_ev_verification_sent_at', true );
		
		if ( $last_sent && ( $now - $last_sent ) < $cooldown_seconds ) {
			$wait_time = $cooldown_seconds - ( $now - $last_sent );
			return new WP_Error( 
				'rate_limit_cooldown',
				/* translators: %d: seconds to wait */
				sprintf( __( 'Please wait %d seconds before requesting another verification email.', 'wc-email-verification-gate' ), $wait_time )
			);
		}
		
		// Check daily cap
		$daily_cap = (int) get_option( 'wc_ev_daily_resend_cap', 10 );
		$resend_count_meta = get_user_meta( $user_id, 'wc_ev_resend_count_day', true );
		
		if ( empty( $resend_count_meta ) ) {
			$resend_count = 0;
			$reset_time = $now;
		} else {
			$resend_data = maybe_unserialize( $resend_count_meta );
			$resend_count = isset( $resend_data['count'] ) ? (int) $resend_data['count'] : 0;
			$reset_time = isset( $resend_data['reset_time'] ) ? (int) $resend_data['reset_time'] : $now;
		}
		
		// Reset counter if 24 hours have passed
		if ( ( $now - $reset_time ) >= DAY_IN_SECONDS ) {
			$resend_count = 0;
			$reset_time = $now;
		}
		
		if ( $resend_count >= $daily_cap ) {
			return new WP_Error( 
				'rate_limit_daily_cap',
				__( 'You have reached the maximum number of verification emails for today. Please try again tomorrow.', 'wc-email-verification-gate' )
			);
		}
		
		return true;
	}
	
	/**
	 * Record verification email sent
	 * 
	 * @param int $user_id User ID
	 */
	public function record_verification_sent( $user_id ) {
		$now = time();
		
		// Update last sent time
		update_user_meta( $user_id, 'wc_ev_verification_sent_at', $now );
		
		// Update daily counter
		$resend_count_meta = get_user_meta( $user_id, 'wc_ev_resend_count_day', true );
		
		if ( empty( $resend_count_meta ) ) {
			$resend_count = 1;
			$reset_time = $now;
		} else {
			$resend_data = maybe_unserialize( $resend_count_meta );
			$resend_count = isset( $resend_data['count'] ) ? (int) $resend_data['count'] : 0;
			$reset_time = isset( $resend_data['reset_time'] ) ? (int) $resend_data['reset_time'] : $now;
			
			// Reset if 24 hours have passed
			if ( ( $now - $reset_time ) >= DAY_IN_SECONDS ) {
				$resend_count = 1;
				$reset_time = $now;
			} else {
				$resend_count++;
			}
		}
		
		update_user_meta( $user_id, 'wc_ev_resend_count_day', array(
			'count' => $resend_count,
			'reset_time' => $reset_time,
		) );
	}
	
	/**
	 * Build verification URL
	 * 
	 * @param int $user_id User ID
	 * @param string $token Plaintext token
	 * @return string Verification URL
	 */
	public function build_verification_url( $user_id, $token ) {
		return add_query_arg( array(
			'wc_ev' => 'verify',
			'uid' => $user_id,
			'token' => urlencode( $token ),
		), home_url( '/' ) );
	}
}
