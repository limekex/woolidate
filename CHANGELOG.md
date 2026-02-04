# Changelog

All notable changes to the WooCommerce Email Verification Gate plugin will be documented in this file.

## [1.0.0] - 2024-02-04

### Initial Release

#### Features
- **Email Verification System**
  - Secure token generation using random_bytes
  - HMAC SHA-256 token hashing
  - Constant-time comparison with hash_equals
  - Single-use tokens with expiration
  - Token rotation on resend

- **Custom Order Status**
  - "Awaiting verification" status for unverified orders
  - Automatic order release to "processing" after verification
  - Order notes tracking verification events

- **Dual Checkout Support**
  - Full support for Classic WooCommerce checkout
  - Full support for WooCommerce Blocks/Store API checkout
  - Idempotent order gating logic

- **Login Blocking**
  - Blocks unverified users from logging in
  - User-friendly error messages
  - Resend verification link on login page

- **Security Features**
  - Rate limiting with per-user cooldown
  - Daily resend cap per user
  - Anti-enumeration protections
  - Nonce-protected admin actions
  - wp_safe_redirect for redirect safety
  - Transients and cookies instead of PHP sessions

- **Scope Control**
  - Only affects users created after feature enabled
  - Only affects orders created after feature enabled
  - Existing users and orders unaffected

- **Admin Tools**
  - Mark users as verified manually
  - Send verification emails to users
  - Release orders manually
  - User verification status column
  - User profile verification section

- **WooCommerce Integration**
  - Settings in WooCommerce → Settings → Accounts & Privacy
  - Email settings in WooCommerce → Settings → Emails
  - Customizable email templates
  - Theme-overrideable templates
  - HTML and plain text email support

- **HPOS Compatibility**
  - Full compatibility with High-Performance Order Storage
  - Uses WooCommerce APIs (wc_get_orders)

- **Guest Checkout Management**
  - Automatically disables guest checkout when enabled
  - Stores previous setting
  - Restores setting when disabled

- **Diagnostics**
  - Optional logging for debugging
  - Logs to WooCommerce system logs

#### Documentation
- Comprehensive README
- Detailed plugin documentation
- Testing checklist
- Installation guide
- Changelog

#### Security
- No known vulnerabilities
- All inputs sanitized and escaped
- All database queries parameterized
- Admin actions require manage_woocommerce capability
- CSRF protection via nonces

## Version History

### Version Numbering

This plugin uses [Semantic Versioning](https://semver.org/):
- MAJOR version for incompatible API changes
- MINOR version for backwards-compatible functionality additions
- PATCH version for backwards-compatible bug fixes

### Future Roadmap

Potential features for future versions:
- SMS verification (v2.0)
- 2FA support (v2.0)
- Bulk user verification tools (v1.1)
- Advanced admin reporting (v1.1)
- Integration with popular email marketing services (v1.2)
- Customizable verification page design (v1.1)
- Multi-language improvements (v1.1)
