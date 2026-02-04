# WooCommerce Email Verification Gate

A WordPress plugin that adds email verification to WooCommerce customer accounts. Customers must verify their email address before they can log in and before their orders proceed operationally.

## Features

- **Email Verification Required**: New customers must verify their email before logging in
- **Order Gating**: Orders from unverified customers are held with custom "Awaiting verification" status
- **Payment Protection**: Payments can be captured before verification - plugin does not interfere
- **Dual Checkout Support**: Works with both Classic and WooCommerce Blocks checkout
- **Security First**: 
  - Secure token generation with HMAC SHA-256
  - Constant-time comparison
  - Single-use tokens with expiration
  - Anti-enumeration protections
  - Rate limiting (cooldown + daily cap)
- **Admin Tools**: Mark users as verified, send verification emails, release orders manually
- **Scope Control**: Only affects users and orders created after feature is enabled
- **Customizable Email Templates**: Full WooCommerce email system integration

## Requirements

- WordPress 5.8+
- WooCommerce 6.0+
- PHP 7.4+

## Installation

1. Upload the plugin files to `/wp-content/plugins/wc-email-verification-gate/`
2. Activate the plugin through the 'Plugins' menu in WordPress
3. Configure settings under WooCommerce → Settings → Accounts & Privacy

## Configuration

### Settings

Navigate to **WooCommerce → Settings → Accounts & Privacy** to configure:

- **Enable Email Verification**: Turn the feature on/off
- **Block Unverified Login**: Prevent unverified users from logging in
- **Token Validity**: How long verification links remain valid (default: 24 hours)
- **Resend Cooldown**: Minimum time between resend requests (default: 60 seconds)
- **Daily Resend Cap**: Maximum emails per user per day (default: 10)
- **Diagnostics Logging**: Enable debugging logs

### Email Customization

Customize verification emails under **WooCommerce → Settings → Emails → Email Verification**:

- Subject line
- Email heading
- Email format (HTML/Plain text)

Email templates can be overridden in your theme:
- `yourtheme/woocommerce/emails/email-verification.php`
- `yourtheme/woocommerce/emails/plain/email-verification.php`

## How It Works

### Registration Flow

1. Customer creates account
2. Account marked as unverified
3. Verification email sent with secure link
4. Customer clicks link to verify
5. Account marked verified
6. Any held orders released to processing

### Checkout Flow

1. Customer creates account during checkout (guest checkout disabled)
2. Order created and payment may be captured
3. Order status set to "Awaiting verification"
4. Verification email sent
5. Customer verifies email
6. Order automatically released to "processing"

### Login Blocking

- Unverified users cannot log in
- Clear message with "Resend verification" link
- Rate limiting prevents abuse

## Admin Features

### User Management

- View verification status in user list
- Mark users as verified manually
- Send verification emails to users

### Order Management

- "Awaiting verification" status in order list
- Manual order release action
- Order notes track verification events

## Security Features

- **Token Security**: Random tokens hashed with HMAC SHA-256
- **Single-use Tokens**: Cannot be reused after verification
- **Expiration**: Tokens expire after configured hours
- **Rate Limiting**: Cooldown period and daily cap prevent abuse
- **Anti-enumeration**: Generic responses prevent account discovery
- **Nonce Protection**: All admin actions protected
- **Redirect Safety**: Only internal URLs allowed

## HPOS Compatibility

Fully compatible with WooCommerce High-Performance Order Storage (HPOS).

## Developer Hooks

### Filters

- `woocommerce_email_classes` - Register custom email class
- `wc_order_statuses` - Add custom order status

### Actions

- `user_register` - Track user creation
- `woocommerce_created_customer` - Handle new customer
- `woocommerce_checkout_order_processed` - Classic checkout gating
- `woocommerce_store_api_checkout_order_processed` - Blocks checkout gating
- `authenticate` - Login blocking

## Troubleshooting

### Verification emails not sending

1. Check WooCommerce → Settings → Emails → Email Verification is enabled
2. Test other WooCommerce emails to verify email is working
3. Enable diagnostics logging to debug

### Orders not being released

1. Verify user is in scope (created after feature enabled)
2. Check order created after feature enabled
3. Review order notes for details

### Guest checkout still available

- Guest checkout is automatically disabled when feature is enabled
- Check WooCommerce → Settings → Accounts & Privacy

## License

GPL v2 or later

## Support

For issues, please use the GitHub issue tracker.
