# Quick Reference Guide

## Essential Information

### Plugin Details
- **Name**: WooCommerce Email Verification Gate
- **Version**: 1.0.0
- **Text Domain**: wc-email-verification-gate
- **Requires**: WordPress 5.8+, WooCommerce 6.0+, PHP 7.4+

### Key Files
- Main: `wc-email-verification-gate.php`
- Settings: `includes/class-wc-ev-settings.php`
- Scope: `includes/class-wc-ev-scope.php`
- Tokens: `includes/class-wc-ev-tokens.php`

## Settings Location
**WooCommerce → Settings → Accounts & Privacy → Email Verification**

## User Meta Keys
```
wc_ev_is_verified           => '0' | '1'
wc_ev_verified_at           => timestamp
wc_ev_created_at            => timestamp
wc_ev_token_hash            => string (HMAC SHA-256)
wc_ev_token_expires_at      => timestamp
wc_ev_token_used_at         => timestamp|null
wc_ev_verification_sent_at  => timestamp
wc_ev_resend_count_day      => array(count, reset_time)
```

## Options
```
wc_ev_enable                => 'yes' | 'no'
wc_ev_enabled_at            => timestamp
wc_ev_block_login           => 'yes' | 'no'
wc_ev_token_validity        => int (hours)
wc_ev_resend_cooldown       => int (seconds)
wc_ev_daily_resend_cap      => int
wc_ev_enable_logging        => 'yes' | 'no'
wc_ev_previous_guest_checkout => 'yes' | 'no' (temporary)
```

## Custom Order Status
- Key: `wc-await-verification`
- Label: "Awaiting verification"

## Verification URL Format
```
https://example.com/?wc_ev=verify&uid=123&token=...
```

## Resend URL Format
```
https://example.com/?wc_ev=resend&redirect_to=...
```

## Hooks Used

### Actions
- `user_register` - Track user creation
- `woocommerce_created_customer` - Handle new customer
- `woocommerce_checkout_order_processed` - Classic checkout
- `woocommerce_store_api_checkout_order_processed` - Blocks checkout
- `woocommerce_new_order` - Fallback
- `authenticate` - Login blocking
- `template_redirect` - Handle endpoints

### Filters
- `woocommerce_get_settings_account` - Add settings
- `user_row_actions` - User actions
- `woocommerce_admin_order_actions` - Order actions
- `woocommerce_email_classes` - Register email
- `wc_order_statuses` - Add order status

## Email Template Override
Copy templates to theme:
```
yourtheme/woocommerce/emails/email-verification.php
yourtheme/woocommerce/emails/plain/email-verification.php
```

## Common Tasks

### Check if User Needs Verification
```php
$needs_verification = WC_EV_Scope::instance()->user_needs_verification( $user_id );
```

### Mark User as Verified
```php
WC_EV_Scope::instance()->mark_user_verified( $user_id );
```

### Generate Token
```php
$token = WC_EV_Tokens::instance()->generate_token( $user_id );
```

### Send Verification Email
```php
$emails = WC()->mailer()->get_emails();
$emails['WC_EV_Verification_Email']->trigger( $user_id, $token );
```

### Release Orders
```php
WC_EV_Order_Gate::instance()->release_orders( $user_id );
```

## Debugging

### Enable Logging
WooCommerce → Settings → Accounts & Privacy → Enable Diagnostics Logging

### View Logs
WooCommerce → Status → Logs → Select "wc-email-verification-gate"

### Log Function
```php
WC_EV_Settings::log( 'Message', 'info|warning|error' );
```

## Security Notes

### Token Generation
- Uses `random_bytes(32)`
- Base64url encoded
- No URL-unsafe characters

### Token Hashing
- Algorithm: HMAC SHA-256
- Key: WordPress AUTH_SALT
- Comparison: `hash_equals()` (constant-time)

### Rate Limiting
- Cooldown: Per-user, configurable seconds
- Daily cap: Per-user, configurable count
- Reset: After 24 hours

## Query Examples

### Get Unverified Users
```php
$users = get_users( array(
    'meta_query' => array(
        array(
            'key' => 'wc_ev_is_verified',
            'value' => '0',
        ),
    ),
) );
```

### Get Held Orders
```php
$orders = wc_get_orders( array(
    'status' => 'await-verification',
    'limit' => -1,
) );
```

### Get Orders for User
```php
$orders = wc_get_orders( array(
    'customer_id' => $user_id,
    'status' => 'await-verification',
) );
```

## Troubleshooting Quick Fixes

### Emails Not Sending
1. Check: WooCommerce → Settings → Emails → Email Verification (Enabled?)
2. Test other WooCommerce emails
3. Enable logging
4. Check spam folder

### Guest Checkout Still Available
- Should auto-disable when feature enabled
- Check: WooCommerce → Settings → Accounts & Privacy
- "Allow customers to place orders without an account" should be unchecked

### Orders Not Being Held
- Verify user created AFTER feature enabled
- Verify order created AFTER feature enabled
- Check order notes
- Enable logging

### Cannot Log In After Verification
- Check token hasn't expired
- Check token hasn't been used already
- Request new verification email

## Admin Actions

### From Users List
- Hover over user → "Mark as verified"
- Hover over user → "Send verification email"

### From Order Actions
- Order with "Awaiting verification" status → "Release order"

## Constants
```php
WC_EV_VERSION           => '1.0.0'
WC_EV_PLUGIN_FILE       => __FILE__
WC_EV_PLUGIN_DIR        => /path/to/plugin/
WC_EV_PLUGIN_URL        => https://example.com/wp-content/plugins/...
WC_EV_INCLUDES_DIR      => /path/to/plugin/includes/
WC_EV_TEMPLATES_DIR     => /path/to/plugin/templates/
```

## Singleton Access
```php
WC_EV_Scope::instance()
WC_EV_Tokens::instance()
WC_EV_Settings::instance()
WC_EV_Login_Block::instance()
WC_EV_Order_Gate::instance()
WC_EV_Endpoints::instance()
WC_EV_Emails::instance()
WC_EV_Admin::instance()
```

## Useful Filters (Developer)

### Custom Email Template Location
```php
add_filter( 'woocommerce_locate_template', function( $template, $template_name, $template_path ) {
    // Your custom logic
    return $template;
}, 10, 3 );
```

### Custom Verification URL
Hook into token generation to customize URL structure if needed.

### Custom Order Release Status
Currently fixed to 'processing' - would require filter addition in future version.

---

For complete documentation, see [PLUGIN_README.md](PLUGIN_README.md)
