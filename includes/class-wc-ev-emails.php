<?php
/**
 * Email Handler
 * Registers and manages verification emails
 */

defined( 'ABSPATH' ) || exit;

class WC_EV_Emails {
	
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
		// Register custom email class
		add_filter( 'woocommerce_email_classes', array( $this, 'register_email_class' ) );
		
		// Add email template path
		add_filter( 'woocommerce_locate_template', array( $this, 'locate_template' ), 10, 3 );
	}
	
	/**
	 * Register email class
	 */
	public function register_email_class( $emails ) {
		require_once WC_EV_INCLUDES_DIR . 'emails/class-wc-ev-verification-email.php';
		$emails['WC_EV_Verification_Email'] = new WC_EV_Verification_Email();
		return $emails;
	}
	
	/**
	 * Locate email template
	 */
	public function locate_template( $template, $template_name, $template_path ) {
		// Check if this is our email template
		if ( strpos( $template_name, 'emails/email-verification' ) === false ) {
			return $template;
		}
		
		$plugin_template = WC_EV_TEMPLATES_DIR . $template_name;
		
		if ( file_exists( $plugin_template ) ) {
			return $plugin_template;
		}
		
		return $template;
	}
}
