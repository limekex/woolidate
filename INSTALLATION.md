# Installation Guide

## Requirements

- WordPress 5.8 or higher
- WooCommerce 6.0 or higher
- PHP 7.4 or higher
- Working email configuration

## Installation Steps

### 1. Upload Plugin

**Option A: Via WordPress Admin**
1. Download the plugin as a ZIP file
2. Go to WordPress admin → Plugins → Add New
3. Click "Upload Plugin"
4. Choose the ZIP file and click "Install Now"
5. Click "Activate Plugin"

**Option B: Via FTP/File Manager**
1. Extract the plugin ZIP file
2. Upload the `wc-email-verification-gate` folder to `/wp-content/plugins/`
3. Go to WordPress admin → Plugins
4. Find "WooCommerce Email Verification Gate" and click "Activate"

### 2. Configure Settings

1. Go to **WooCommerce → Settings → Accounts & Privacy**
2. Scroll to the "Email Verification" section
3. Check **"Enable Email Verification"**
4. Configure optional settings:
   - Block Unverified Login (recommended: enabled)
   - Token Validity (default: 24 hours)
   - Resend Cooldown (default: 60 seconds)
   - Daily Resend Cap (default: 10 emails/day)
5. Click **"Save changes"**

### 3. Configure Email Template (Optional)

1. Go to **WooCommerce → Settings → Emails**
2. Find **"Email Verification"** in the list
3. Configure:
   - Subject line
   - Email heading
   - Email format (HTML or Plain text)
4. Click **"Save changes"**

### 4. Test Configuration

1. Log out of WordPress
2. Create a new customer account
3. Check email inbox for verification email
4. Click the verification link
5. Verify you can now log in

## What Happens When You Activate

When you enable the email verification feature:

1. **Guest checkout is automatically disabled** - Customers must create an account to checkout
2. **A timestamp is recorded** - Only users/orders created after this time are affected
3. **Existing users are not affected** - Users created before activation can log in normally
4. **Existing orders are not affected** - Orders created before activation are not gated

## Post-Activation Checklist

- [ ] Verify guest checkout is disabled
- [ ] Test new user registration flow
- [ ] Test verification email delivery
- [ ] Test verification link works
- [ ] Test checkout with new account
- [ ] Test order is held until verification
- [ ] Test order is released after verification

## Troubleshooting

### Verification emails not arriving

1. Check WooCommerce → Settings → Emails → Email Verification is **Enabled**
2. Test other WooCommerce emails (e.g., order confirmation)
3. Check spam/junk folder
4. Enable diagnostics logging (WooCommerce → Settings → Accounts & Privacy)
5. Check logs at WooCommerce → Status → Logs

### Guest checkout still available

- The plugin automatically disables guest checkout when enabled
- If guest checkout is still available, check WooCommerce → Settings → Accounts & Privacy → "Allow customers to place orders without an account"
- It should be unchecked

### Orders not being held

1. Verify the user was created **after** the feature was enabled
2. Verify the order was created **after** the feature was enabled
3. Check order notes for details
4. Enable diagnostics logging to debug

### Cannot log in after verification

1. Check if verification link was already used
2. Check if token has expired
3. Request a new verification email using the resend link

## Uninstallation

To deactivate the plugin:

1. Go to WordPress admin → Plugins
2. Find "WooCommerce Email Verification Gate"
3. Click "Deactivate"

When deactivated:
- Guest checkout setting is restored to previous value
- Existing user verification status is preserved
- Orders remain in their current status

## Support

For issues and support, please refer to:
- [PLUGIN_README.md](PLUGIN_README.md) - Full documentation
- [TESTING_CHECKLIST.md](TESTING_CHECKLIST.md) - Comprehensive testing guide
- GitHub Issues - Report bugs and request features

## Next Steps

After installation:
1. Review the [PLUGIN_README.md](PLUGIN_README.md) for detailed feature documentation
2. Customize email templates if needed
3. Test thoroughly with test orders before going live
4. Configure rate limiting settings based on your needs
5. Consider theme customization for email templates
