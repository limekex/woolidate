<?php
/**
 * Settings Management
 * Handles plugin settings integration with WooCommerce settings
 */

defined( 'ABSPATH' ) || exit;

class WC_EV_Settings {
	
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
		// Add settings to WooCommerce
		add_filter( 'woocommerce_get_settings_account', array( $this, 'add_settings' ), 10, 2 );
		
		// Handle guest checkout enforcement
		add_action( 'update_option_wc_ev_enable', array( $this, 'handle_guest_checkout_option' ), 10, 2 );
	}
	
	/**
	 * Add plugin settings to WooCommerce Accounts & Privacy settings
	 */
	public function add_settings( $settings, $current_section ) {
		if ( '' === $current_section ) {
			$wc_ev_settings = array(
				array(
					'title' => __( 'Email Verification', 'wc-email-verification-gate' ),
					'type'  => 'title',
					'desc'  => __( 'Configure email verification requirements for customer accounts.', 'wc-email-verification-gate' ),
					'id'    => 'wc_ev_settings',
				),
				
				array(
					'title'   => __( 'Enable Email Verification', 'wc-email-verification-gate' ),
					'desc'    => __( 'Require customers to verify their email address', 'wc-email-verification-gate' ),
					'id'      => 'wc_ev_enable',
					'default' => 'no',
					'type'    => 'checkbox',
				),
				
				array(
					'title'   => __( 'Block Unverified Login', 'wc-email-verification-gate' ),
					'desc'    => __( 'Prevent unverified customers from logging in', 'wc-email-verification-gate' ),
					'id'      => 'wc_ev_block_login',
					'default' => 'yes',
					'type'    => 'checkbox',
				),
				
				array(
					'title'    => __( 'Token Validity (hours)', 'wc-email-verification-gate' ),
					'desc'     => __( 'How long verification links remain valid', 'wc-email-verification-gate' ),
					'id'       => 'wc_ev_token_validity',
					'default'  => '24',
					'type'     => 'number',
					'css'      => 'width: 100px;',
					'custom_attributes' => array(
						'min'  => '1',
						'step' => '1',
					),
				),
				
				array(
					'title'    => __( 'Resend Cooldown (seconds)', 'wc-email-verification-gate' ),
					'desc'     => __( 'Minimum time between resend requests', 'wc-email-verification-gate' ),
					'id'       => 'wc_ev_resend_cooldown',
					'default'  => '60',
					'type'     => 'number',
					'css'      => 'width: 100px;',
					'custom_attributes' => array(
						'min'  => '1',
						'step' => '1',
					),
				),
				
				array(
					'title'    => __( 'Daily Resend Cap', 'wc-email-verification-gate' ),
					'desc'     => __( 'Maximum verification emails per user per day', 'wc-email-verification-gate' ),
					'id'       => 'wc_ev_daily_resend_cap',
					'default'  => '10',
					'type'     => 'number',
					'css'      => 'width: 100px;',
					'custom_attributes' => array(
						'min'  => '1',
						'step' => '1',
					),
				),
				
				array(
					'title'   => __( 'Enable Diagnostics Logging', 'wc-email-verification-gate' ),
					'desc'    => __( 'Log verification events for debugging', 'wc-email-verification-gate' ),
					'id'      => 'wc_ev_enable_logging',
					'default' => 'no',
					'type'    => 'checkbox',
				),
				
				array(
					'type' => 'sectionend',
					'id'   => 'wc_ev_settings',
				),
			);
			
			// Insert after account creation settings
			$insert_position = 0;
			foreach ( $settings as $index => $setting ) {
				if ( isset( $setting['id'] ) && 'account_registration_options' === $setting['id'] && 'sectionend' === $setting['type'] ) {
					$insert_position = $index + 1;
					break;
				}
			}
			
			if ( $insert_position > 0 ) {
				array_splice( $settings, $insert_position, 0, $wc_ev_settings );
			} else {
				$settings = array_merge( $settings, $wc_ev_settings );
			}
		}
		
		return $settings;
	}
	
	/**
	 * Handle guest checkout enforcement when feature is enabled/disabled
	 */
	public function handle_guest_checkout_option( $old_value, $new_value ) {
		if ( 'yes' === $new_value ) {
			// Feature is being enabled - disable guest checkout
			$current_guest_checkout = get_option( 'woocommerce_enable_guest_checkout', 'yes' );
			
			// Store current value if not already stored
			if ( false === get_option( 'wc_ev_previous_guest_checkout' ) ) {
				update_option( 'wc_ev_previous_guest_checkout', $current_guest_checkout );
			}
			
			// Disable guest checkout
			update_option( 'woocommerce_enable_guest_checkout', 'no' );
			
			// Update enabled timestamp if not set
			if ( ! get_option( 'wc_ev_enabled_at' ) ) {
				update_option( 'wc_ev_enabled_at', time() );
			}
		} elseif ( 'no' === $new_value && 'yes' === $old_value ) {
			// Feature is being disabled - restore guest checkout
			$previous_guest_checkout = get_option( 'wc_ev_previous_guest_checkout' );
			if ( false !== $previous_guest_checkout ) {
				update_option( 'woocommerce_enable_guest_checkout', $previous_guest_checkout );
				delete_option( 'wc_ev_previous_guest_checkout' );
			}
		}
	}
	
	/**
	 * Log diagnostic message if logging is enabled
	 */
	public static function log( $message, $level = 'info' ) {
		if ( 'yes' !== get_option( 'wc_ev_enable_logging', 'no' ) ) {
			return;
		}
		
		if ( function_exists( 'wc_get_logger' ) ) {
			$logger = wc_get_logger();
			$logger->log( $level, $message, array( 'source' => 'wc-email-verification-gate' ) );
		}
	}
}
