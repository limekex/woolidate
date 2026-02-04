<?php
/**
 * Email Verification Email - HTML Template
 *
 * @var string $email_heading Email heading
 * @var string $verification_url Verification URL
 * @var int $token_expiry_hours Token expiry hours
 * @var string $my_account_url My Account URL
 * @var int|null $order_id Order ID
 * @var WC_Email $email Email object
 */

defined( 'ABSPATH' ) || exit;

do_action( 'woocommerce_email_header', $email_heading, $email );
?>

<p><?php esc_html_e( 'Thank you for creating an account!', 'wc-email-verification-gate' ); ?></p>

<p><?php esc_html_e( 'To complete your registration and access your account, please verify your email address by clicking the button below:', 'wc-email-verification-gate' ); ?></p>

<p style="text-align: center; margin: 30px 0;">
	<a href="<?php echo esc_url( $verification_url ); ?>" 
	   style="background-color: #96588a; color: #ffffff; padding: 12px 24px; text-decoration: none; border-radius: 3px; display: inline-block; font-weight: bold;">
		<?php esc_html_e( 'Verify Email Address', 'wc-email-verification-gate' ); ?>
	</a>
</p>

<p><?php esc_html_e( 'Or copy and paste this link into your browser:', 'wc-email-verification-gate' ); ?></p>
<p style="word-break: break-all;"><a href="<?php echo esc_url( $verification_url ); ?>"><?php echo esc_html( $verification_url ); ?></a></p>

<?php if ( $order_id ) : ?>
<p><?php esc_html_e( 'Your order is on hold pending email verification. Once verified, we will process your order immediately.', 'wc-email-verification-gate' ); ?></p>
<?php endif; ?>

<p>
	<?php
	/* translators: %d: token expiry hours */
	printf( esc_html__( 'This verification link will expire in %d hours.', 'wc-email-verification-gate' ), absint( $token_expiry_hours ) );
	?>
</p>

<p><?php esc_html_e( 'If you did not create an account, please ignore this email.', 'wc-email-verification-gate' ); ?></p>

<?php
do_action( 'woocommerce_email_footer', $email );
