<?php
/**
 * Email Verification Email - Plain Text Template
 *
 * @var string $email_heading Email heading
 * @var string $verification_url Verification URL
 * @var int $token_expiry_hours Token expiry hours
 * @var string $my_account_url My Account URL
 * @var int|null $order_id Order ID
 * @var WC_Email $email Email object
 */

defined( 'ABSPATH' ) || exit;

echo "= " . esc_html( $email_heading ) . " =\n\n";

esc_html_e( 'Thank you for creating an account!', 'wc-email-verification-gate' );

echo "\n\n";

esc_html_e( 'To complete your registration and access your account, please verify your email address by clicking the link below:', 'wc-email-verification-gate' );

echo "\n\n";

echo esc_url( $verification_url );

echo "\n\n";

if ( $order_id ) {
	esc_html_e( 'Your order is on hold pending email verification. Once verified, we will process your order immediately.', 'wc-email-verification-gate' );
	echo "\n\n";
}

/* translators: %d: token expiry hours */
printf( esc_html__( 'This verification link will expire in %d hours.', 'wc-email-verification-gate' ), absint( $token_expiry_hours ) );

echo "\n\n";

esc_html_e( 'If you did not create an account, please ignore this email.', 'wc-email-verification-gate' );

echo "\n\n";

echo esc_html( apply_filters( 'woocommerce_email_footer_text', get_option( 'woocommerce_email_footer_text' ) ) );
