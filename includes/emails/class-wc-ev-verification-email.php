<?php
/**
 * Verification Email Class
 * Extends WC_Email for verification emails
 */

defined( 'ABSPATH' ) || exit;

if ( ! class_exists( 'WC_EV_Verification_Email' ) && class_exists( 'WC_Email' ) ) {

class WC_EV_Verification_Email extends WC_Email {
	
	/**
	 * User ID
	 */
	public $user_id;
	
	/**
	 * Verification token
	 */
	public $token;
	
	/**
	 * Verification URL
	 */
	public $verification_url;
	
	/**
	 * Order ID (optional)
	 */
	public $order_id;
	
	/**
	 * Constructor
	 */
	public function __construct() {
		$this->id             = 'wc_ev_verification_email';
		$this->title          = __( 'Email Verification', 'wc-email-verification-gate' );
		$this->description    = __( 'Email sent to customers to verify their email address.', 'wc-email-verification-gate' );
		$this->template_html  = 'emails/email-verification.php';
		$this->template_plain = 'emails/plain/email-verification.php';
		$this->template_base  = WC_EV_TEMPLATES_DIR;
		
		// Call parent constructor
		parent::__construct();
		
		$this->manual = false;
	}
	
	/**
	 * Get default subject
	 */
	public function get_default_subject() {
		return __( 'Verify your email address', 'wc-email-verification-gate' );
	}
	
	/**
	 * Get default heading
	 */
	public function get_default_heading() {
		return __( 'Verify Your Email', 'wc-email-verification-gate' );
	}
	
	/**
	 * Trigger email
	 */
	public function trigger( $user_id, $token, $order_id = null ) {
		$this->setup_locale();
		
		$user = get_user_by( 'id', $user_id );
		if ( ! $user ) {
			$this->restore_locale();
			return;
		}
		
		$this->user_id = $user_id;
		$this->token = $token;
		$this->order_id = $order_id;
		$this->recipient = $user->user_email;
		
		// Build verification URL
		$tokens = WC_EV_Tokens::instance();
		$this->verification_url = $tokens->build_verification_url( $user_id, $token );
		
		if ( $this->is_enabled() && $this->get_recipient() ) {
			$this->send( $this->get_recipient(), $this->get_subject(), $this->get_content(), $this->get_headers(), $this->get_attachments() );
		}
		
		$this->restore_locale();
	}
	
	/**
	 * Get content HTML
	 */
	public function get_content_html() {
		return wc_get_template_html(
			$this->template_html,
			array(
				'email_heading'      => $this->get_heading(),
				'verification_url'   => $this->verification_url,
				'token_expiry_hours' => get_option( 'wc_ev_token_validity', 24 ),
				'my_account_url'     => wc_get_page_permalink( 'myaccount' ),
				'order_id'           => $this->order_id,
				'sent_to_admin'      => false,
				'plain_text'         => false,
				'email'              => $this,
			),
			'',
			$this->template_base
		);
	}
	
	/**
	 * Get content plain
	 */
	public function get_content_plain() {
		return wc_get_template_html(
			$this->template_plain,
			array(
				'email_heading'      => $this->get_heading(),
				'verification_url'   => $this->verification_url,
				'token_expiry_hours' => get_option( 'wc_ev_token_validity', 24 ),
				'my_account_url'     => wc_get_page_permalink( 'myaccount' ),
				'order_id'           => $this->order_id,
				'sent_to_admin'      => false,
				'plain_text'         => true,
				'email'              => $this,
			),
			'',
			$this->template_base
		);
	}
	
	/**
	 * Initialize settings form fields
	 */
	public function init_form_fields() {
		$this->form_fields = array(
			'enabled' => array(
				'title'   => __( 'Enable/Disable', 'wc-email-verification-gate' ),
				'type'    => 'checkbox',
				'label'   => __( 'Enable this email notification', 'wc-email-verification-gate' ),
				'default' => 'yes',
			),
			'subject' => array(
				'title'       => __( 'Subject', 'wc-email-verification-gate' ),
				'type'        => 'text',
				'desc_tip'    => true,
				'description' => __( 'Email subject line.', 'wc-email-verification-gate' ),
				'placeholder' => $this->get_default_subject(),
				'default'     => '',
			),
			'heading' => array(
				'title'       => __( 'Email heading', 'wc-email-verification-gate' ),
				'type'        => 'text',
				'desc_tip'    => true,
				'description' => __( 'Main heading shown in the email.', 'wc-email-verification-gate' ),
				'placeholder' => $this->get_default_heading(),
				'default'     => '',
			),
			'email_type' => array(
				'title'       => __( 'Email type', 'wc-email-verification-gate' ),
				'type'        => 'select',
				'description' => __( 'Choose which format of email to send.', 'wc-email-verification-gate' ),
				'default'     => 'html',
				'class'       => 'email_type wc-enhanced-select',
				'options'     => $this->get_email_type_options(),
				'desc_tip'    => true,
			),
		);
	}
}

}
