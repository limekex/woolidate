# WooCommerce Email Verification Gate - Testing Checklist

This document provides a comprehensive testing checklist to verify all requirements from the specification.

## Pre-requisites

- WordPress 5.8+ installed
- WooCommerce 6.0+ installed and configured
- Test email configuration working
- Test products and pages configured

## 1. Installation & Activation

- [ ] Plugin activates successfully
- [ ] No PHP errors in debug.log
- [ ] WooCommerce dependency check works
- [ ] Settings appear under WooCommerce → Settings → Accounts & Privacy
- [ ] Custom order status "Awaiting verification" appears in order statuses
- [ ] Email settings appear under WooCommerce → Settings → Emails

## 2. Plugin Settings

Navigate to WooCommerce → Settings → Accounts & Privacy

- [ ] "Enable Email Verification" checkbox present
- [ ] "Block Unverified Login" checkbox present (default: yes)
- [ ] "Token Validity (hours)" field present (default: 24)
- [ ] "Resend Cooldown (seconds)" field present (default: 60)
- [ ] "Daily Resend Cap" field present (default: 10)
- [ ] "Enable Diagnostics Logging" checkbox present

### Enable the Feature

- [ ] Enable "Email Verification"
- [ ] Save settings
- [ ] Verify guest checkout is now disabled (WooCommerce → Settings → Accounts & Privacy)
- [ ] Verify `wc_ev_enabled_at` option is set in database

## 3. Email Configuration

Navigate to WooCommerce → Settings → Emails → Email Verification

- [ ] Email enabled by default
- [ ] Subject field customizable
- [ ] Heading field customizable
- [ ] Email type (HTML/Plain text) selectable
- [ ] Save settings successfully

## 4. User Registration Flow

### 4.1 New User Registration

- [ ] Create new user via WooCommerce account creation
- [ ] User receives verification email
- [ ] Email contains verification link
- [ ] Email mentions expiry time
- [ ] User meta `wc_ev_is_verified` is set to '0'
- [ ] User meta `wc_ev_created_at` is set
- [ ] User meta `wc_ev_token_hash` is set
- [ ] User meta `wc_ev_token_expires_at` is set

### 4.2 Login Blocking

- [ ] Attempt to log in with unverified account
- [ ] Login is blocked with error message
- [ ] Error message mentions email verification requirement
- [ ] "Resend verification email" link is displayed

### 4.3 Email Verification

- [ ] Click verification link from email
- [ ] User redirected to My Account page
- [ ] Success message displayed
- [ ] User meta `wc_ev_is_verified` is now '1'
- [ ] User meta `wc_ev_verified_at` is set
- [ ] User meta `wc_ev_token_used_at` is set

### 4.4 Login After Verification

- [ ] Log in with verified account
- [ ] Login successful
- [ ] No verification messages shown

## 5. Checkout Flow (Classic Checkout)

### 5.1 Checkout with New Account

- [ ] Add product to cart
- [ ] Go to checkout
- [ ] Guest checkout option NOT available
- [ ] Create new account during checkout
- [ ] Complete payment (use test gateway)
- [ ] Payment processes successfully
- [ ] Order created with status "Awaiting verification"
- [ ] Order note added: "Order held pending email verification"
- [ ] Verification email sent to customer

### 5.2 Order Release After Verification

- [ ] Click verification link from email
- [ ] Order status changes to "Processing"
- [ ] Order note added: "Email verified; order released to processing"

## 6. Checkout Flow (WooCommerce Blocks)

If WooCommerce Blocks is available:

### 6.1 Checkout with New Account (Blocks)

- [ ] Add product to cart
- [ ] Use block-based checkout
- [ ] Guest checkout option NOT available
- [ ] Create new account during checkout
- [ ] Complete payment
- [ ] Payment processes successfully
- [ ] Order created with status "Awaiting verification"
- [ ] Order note added
- [ ] Verification email sent

### 6.2 Order Release (Blocks)

- [ ] Verify email
- [ ] Order status changes to "Processing"
- [ ] Order note added

## 7. Token Security

### 7.1 Token Validation

- [ ] Generate verification token for user
- [ ] Verify token is random and unique
- [ ] Verify only hash is stored (not plaintext)
- [ ] Valid token verifies successfully
- [ ] Invalid token rejected
- [ ] Expired token rejected (change `wc_ev_token_expires_at` to past time)
- [ ] Already-used token rejected (verify twice)

### 7.2 Token Properties

- [ ] Token generated using `random_bytes(32)`
- [ ] Token hashed with HMAC SHA-256
- [ ] Hash uses WordPress `AUTH_SALT` as key
- [ ] Comparison uses `hash_equals()` (constant-time)
- [ ] Token expires after configured hours
- [ ] Single-use enforcement works

## 8. Rate Limiting

### 8.1 Resend Cooldown

- [ ] Request verification email
- [ ] Immediately request again
- [ ] Second request blocked with cooldown message
- [ ] Wait for cooldown period
- [ ] Request succeeds after cooldown

### 8.2 Daily Cap

- [ ] Request verification email 10 times (default cap)
- [ ] 11th request blocked with daily cap message
- [ ] Change date in `wc_ev_resend_count_day` meta to yesterday
- [ ] Request succeeds (counter reset)

### 8.3 Token Rotation

- [ ] Request verification email
- [ ] Note the token hash in user meta
- [ ] Request verification email again (after cooldown)
- [ ] Verify token hash has changed
- [ ] Old token is invalidated
- [ ] New token works for verification

## 9. Anti-Enumeration

### 9.1 Resend Endpoint

- [ ] Request resend for non-existent user
- [ ] Generic success message shown (no error revealing account doesn't exist)
- [ ] Request resend for existing but unverified user
- [ ] Same generic success message shown
- [ ] Email only sent if account actually exists

### 9.2 Verification Endpoint

- [ ] Try verification link with invalid user ID
- [ ] Generic error message (not revealing user existence)
- [ ] Try verification link with valid user but invalid token
- [ ] Generic error message (not revealing specific issue)

## 10. Scope Enforcement

### 10.1 Existing Users (Before Enable)

- [ ] Create user BEFORE enabling feature
- [ ] Enable feature
- [ ] Verify user has no `wc_ev_created_at` meta
- [ ] User can log in without verification
- [ ] User not subject to verification requirements

### 10.2 New Users (After Enable)

- [ ] Feature already enabled
- [ ] Create new user
- [ ] User has `wc_ev_created_at` meta >= `wc_ev_enabled_at`
- [ ] User subject to verification requirements
- [ ] Cannot log in without verification

### 10.3 Existing Orders (Before Enable)

- [ ] Create order BEFORE enabling feature
- [ ] Enable feature
- [ ] Order not affected by plugin
- [ ] Order status not changed

### 10.4 New Orders (After Enable)

- [ ] Feature enabled
- [ ] Create order with unverified user
- [ ] Order status set to "Awaiting verification"
- [ ] Order `date_created` >= `wc_ev_enabled_at`

## 11. Admin Tools

### 11.1 User Management

Navigate to Users → All Users

- [ ] "Email Verified" column shows verification status
- [ ] Verified users show green checkmark
- [ ] Unverified users show red X
- [ ] Out-of-scope users show "—"
- [ ] Click user to edit
- [ ] "Email Verification Status" section visible
- [ ] Status, creation date, and verification date shown

### 11.2 User Actions

From Users → All Users, hover over unverified user:

- [ ] "Mark as verified" action available
- [ ] "Send verification email" action available
- [ ] Click "Mark as verified"
- [ ] User marked as verified
- [ ] Success notice displayed
- [ ] Any held orders released
- [ ] Click "Send verification email"
- [ ] Email sent
- [ ] Success notice displayed

### 11.3 Order Actions

Navigate to WooCommerce → Orders, find order with "Awaiting verification" status:

- [ ] "Release order" action available in order actions dropdown
- [ ] Click "Release order"
- [ ] Order status changes to "Processing"
- [ ] Order note added: "Order manually released by admin"
- [ ] Success notice displayed

### 11.4 Nonce Protection

- [ ] All admin actions require `manage_woocommerce` capability
- [ ] Actions without valid nonce rejected
- [ ] Actions with valid nonce succeed

## 12. Guest Checkout Enforcement

### 12.1 When Feature Enabled

- [ ] Enable feature
- [ ] `woocommerce_enable_guest_checkout` set to 'no'
- [ ] Previous value stored in `wc_ev_previous_guest_checkout`
- [ ] Guest checkout not available at checkout

### 12.2 When Feature Disabled

- [ ] Disable feature
- [ ] `woocommerce_enable_guest_checkout` restored to previous value
- [ ] `wc_ev_previous_guest_checkout` option deleted
- [ ] Guest checkout availability restored

### 12.3 Guest Order Fallback

If a guest order somehow occurs:

- [ ] Plugin does not break checkout
- [ ] Order created successfully
- [ ] No verification gating applied
- [ ] Admin note added about unexpected guest order

## 13. Multiple Orders

### 13.1 Multiple Held Orders

- [ ] Create 3 orders with unverified user
- [ ] All orders set to "Awaiting verification"
- [ ] Verify email
- [ ] All 3 orders released to "Processing"
- [ ] Order notes added to all orders

## 14. Email Templates

### 14.1 Theme Override

- [ ] Copy email template to theme directory
- [ ] Modify template (add custom text)
- [ ] Send verification email
- [ ] Custom template used
- [ ] Modifications visible in email

### 14.2 Template Variables

Verify all variables work in templates:

- [ ] `verification_url` - clickable link works
- [ ] `token_expiry_hours` - correct value displayed
- [ ] `my_account_url` - correct URL
- [ ] `order_id` - displayed when triggered during checkout
- [ ] `email_heading` - custom heading respected

### 14.3 Plain Text Template

- [ ] Set email type to "Plain text"
- [ ] Send verification email
- [ ] Plain text template used
- [ ] No HTML in email body

## 15. HPOS Compatibility

### 15.1 With HPOS Enabled

If HPOS is available:

- [ ] Enable HPOS (WooCommerce → Settings → Advanced → Features)
- [ ] Create order with unverified user
- [ ] Order gated correctly
- [ ] Verify email
- [ ] Order released correctly
- [ ] All order queries work (`wc_get_orders`)

## 16. Logging

### 16.1 Diagnostics

- [ ] Enable "Enable Diagnostics Logging"
- [ ] Perform various actions (register, verify, etc.)
- [ ] Check WooCommerce logs (WooCommerce → Status → Logs)
- [ ] Verify events logged with source "wc-email-verification-gate"

## 17. Edge Cases

### 17.1 Already Verified User

- [ ] User already verified
- [ ] Create new order
- [ ] Order goes directly to "Processing" or appropriate status
- [ ] Not gated for verification

### 17.2 Expired Token

- [ ] Generate verification token
- [ ] Manually change `wc_ev_token_expires_at` to past time
- [ ] Attempt verification
- [ ] Rejected with expiry message
- [ ] Resend verification email
- [ ] New token generated
- [ ] New token works

### 17.3 Payment Already Captured

- [ ] Create order with unverified user
- [ ] Payment gateway captures payment
- [ ] Order initially set to "Processing" by gateway
- [ ] Plugin changes to "Awaiting verification"
- [ ] Payment metadata preserved
- [ ] After verification, order returns to "Processing"

### 17.4 Concurrent Requests

- [ ] Generate verification token
- [ ] Open verification link in 2 tabs
- [ ] First tab: verification succeeds
- [ ] Second tab: verification fails (already used)

## 18. Deactivation

- [ ] Deactivate plugin
- [ ] Guest checkout setting restored
- [ ] Verify `wc_ev_previous_guest_checkout` option deleted
- [ ] No errors on deactivation

## 19. Security Verification

### 19.1 SQL Injection

- [ ] Try SQL injection in user ID parameter
- [ ] No SQL errors
- [ ] Queries properly escaped/parameterized

### 19.2 XSS

- [ ] Try XSS in token parameter
- [ ] No script execution
- [ ] Input properly sanitized and escaped

### 19.3 CSRF

- [ ] Try admin action without nonce
- [ ] Action rejected
- [ ] Valid nonce required

### 19.4 Open Redirect

- [ ] Try verification with external redirect
- [ ] Only internal redirects allowed
- [ ] `wp_safe_redirect` used

## 20. Acceptance Criteria (Final Check)

- [ ] 1. Feature enabled → newly created users are unverified and receive verification email
- [ ] 2. Unverified in-scope users cannot log in; they see notice + resend option
- [ ] 3. Guest checkout disabled while enabled (account required)
- [ ] 4. New orders by unverified in-scope users set to "await_verification" even if payment captures
- [ ] 5. Verification link marks user verified and releases all in-scope "await_verification" orders to "processing"
- [ ] 6. Verification email templates configurable and theme-overrideable
- [ ] 7. Token implementation secure: random, hashed storage, expiry, single-use, constant-time compare
- [ ] 8. Resend rate-limited via cooldown + daily cap and avoids account enumeration
- [ ] 9. Existing users and orders created before `wc_ev_enabled_at` not modified or gated
- [ ] 10. Works in both Classic and Blocks checkout flows

## Test Results Summary

Date: _____________
Tester: _____________
WordPress Version: _____________
WooCommerce Version: _____________
PHP Version: _____________

Total Tests: _______
Passed: _______
Failed: _______
N/A: _______

Notes:
_____________________________________________________________
_____________________________________________________________
_____________________________________________________________
