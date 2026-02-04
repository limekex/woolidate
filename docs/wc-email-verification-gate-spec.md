# WooCommerce Email Verification Gate — Plugin Spec / Scope (v1)

**Language:** English  (i18n + l10n ready)
**Platform:** WordPress + WooCommerce  
**Checkout support:** **Classic Checkout + WooCommerce Blocks / Store API Checkout (both)**  

## Locked decisions
- Guest checkout: **NOT allowed** (forced account creation)
- Payment policy: **Capture payment before verification (if applicable)** — plugin must not block payment capture
- Release status after verification: **processing**
- Login policy: **Block login** for unverified users with notice + “click to resend”
- Scope: **Only new users and new orders** created after the feature is enabled (no retroactive changes)

---

## 1) Purpose
Build a WordPress plugin that adds **email verification** to WooCommerce customer accounts.  
Customers must verify their email address before they can **log in** and before their **orders proceed operationally**.

Core behaviors:
- New customer accounts are **unverified** until a verification link is clicked.
- Unverified customers are **blocked from logging in** and prompted to verify, with a **Resend verification** action.
- New orders placed by unverified customers are set to a custom order status: **Awaiting verification**.
- Payments may be captured successfully (gateway dependent) **before** verification; the plugin must not interfere with payment authorization/capture.
- Once verified, held orders are automatically moved to **processing**.

---

## 2) In-Scope User Stories

### 2.1 Registration
- As a customer, when I register, I receive a verification email containing a secure link.
- I cannot log in until I verify my email.

### 2.2 Checkout (Account Required)
- As a customer, I must create an account to checkout (no guest checkout).
- If my account is unverified, my order is created and payment may be captured, but the order is set to **Awaiting verification**.

### 2.3 Verification
- When I click the verification link, my account becomes verified.
- All my held orders with status **Awaiting verification** are released to **processing**.

### 2.4 Login Blocking + Resend
- If I attempt to log in while unverified, the login is blocked with a notice:
  - “Please verify your email to continue.”
  - Includes a “Resend verification email” action.

---

## 3) Admin Requirements

### 3.1 Settings UI
Add settings under **WooCommerce → Settings → Accounts & Privacy** (preferred), or a dedicated submenu.

**Settings (configurable):**
- Enable email verification gate (on/off)
- Enable login blocking (on/off, default on)
- Token validity (hours; default 24)
- Resend cooldown (seconds; default 60)
- Daily resend cap (default e.g. 10 per user per day; configurable)
- Email subject + heading for verification email
- Email template format: HTML + Plain text (Woo standard)
- Optional diagnostics logging toggle (on/off)

**Locked behavior in v1 (not configurable):**
- Guest checkout forced OFF while enabled
- Release status after verification: **processing**
- Scope enforcement: only users/orders created after enable timestamp

### 3.2 Admin Tools
- User admin action: **Mark as verified**
- User admin action: **Send verification email**
- Order admin action: **Release order** (moves to processing)
  - Must add order note and require `manage_woocommerce`
  - Must be nonce-protected

---

## 4) Data Model

### 4.1 User Meta Keys
- `wc_ev_is_verified` = `0|1`
- `wc_ev_verified_at` = timestamp (optional)
- `wc_ev_created_at` = timestamp (set at registration; used for scope rules)
- `wc_ev_token_hash` = string (HMAC SHA-256 of token)
- `wc_ev_token_expires_at` = timestamp
- `wc_ev_token_used_at` = timestamp|null
- `wc_ev_verification_sent_at` = timestamp (for cooldown)
- `wc_ev_resend_count_day` = int (daily cap tracking; implementation-defined)

### 4.2 Plugin Enable Timestamp (Scope Guard)
Store activation/enable time:
- Option key: `wc_ev_enabled_at`

Scope rules:
- Only users with `wc_ev_created_at >= wc_ev_enabled_at` are subject to verification gating.
- Only orders with `date_created >= wc_ev_enabled_at` are gated/released automatically.

---

## 5) Custom Order Status: Awaiting verification
Register a WooCommerce order status:
- Key: `wc-await-verification`
- Label: **Awaiting verification**
- Visible in admin order statuses and filters

Implementation notes:
- Use `register_post_status()` and add via `wc_order_statuses` filter.
- Add order notes on transitions:
  - “Order held pending email verification.”
  - “Email verified; order released to processing.”

---

## 6) Workflow (Exact)

### 6.1 On Registration (new user)
Trigger on Woo customer creation:
1. If feature enabled:
2. Set `wc_ev_created_at = now`
3. Set `wc_ev_is_verified = 0`
4. Generate token:
   - `token = base64url(random_bytes(32))` (or hex)
   - Store `wc_ev_token_hash = hash_hmac('sha256', token, AUTH_SALT)`
   - Set `wc_ev_token_expires_at = now + token_validity_hours`
   - Set `wc_ev_token_used_at = null`
5. Send verification email (respect resend cooldown if re-triggered)
6. Display notice: “Check your email to verify your account.”

### 6.2 Force Account Creation at Checkout (no guest)
When feature enabled:
- Set Woo option `woocommerce_enable_guest_checkout = no`
- Store prior value in a plugin option and restore when feature disabled
- Best-effort fallback: if a guest order still occurs (unexpected), do not break checkout; skip verification gating and optionally add an admin-facing order note.

### 6.3 On Order Creation (unverified user)
When an order is created:
- If feature enabled AND customer exists AND customer is unverified AND
  - `user_created_at >= enabled_at` AND `order_created_at >= enabled_at`
Then:
1. Set order status to `await_verification` (idempotent; safe if already set)
2. Add order note: held pending verification
3. Trigger verification email if not recently sent (cooldown)
4. **Do not interfere with payment capture**
   - Do not cancel, void, or block gateway callbacks
   - If a gateway sets the order to `processing` immediately after payment, plugin may move it to `await_verification` while preserving payment metadata

### 6.4 Login Blocking (unverified user)
During authentication:
- If feature enabled AND user in-scope AND `wc_ev_is_verified = 0`
Then:
- Block login (return `WP_Error`)
- Display notice with:
  - Verify requirement message
  - Resend action

Resend action rules:
- If user not logged in, resend endpoint must avoid account enumeration:
  - Always show generic response: “If your account requires verification, we sent an email.”
- Enforce cooldown + daily cap and rotate token on resend.

### 6.5 Verification Endpoint
Verification URL format:
- `https://example.com/?wc_ev=verify&uid=123&token=...`

On request:
1. Validate uid exists
2. Validate user in-scope (`wc_ev_created_at >= wc_ev_enabled_at`)
3. Validate token:
   - Hash received token using HMAC SHA-256 with AUTH_SALT
   - Compare with stored hash using `hash_equals`
   - Enforce not expired and not already used
4. Mark verified:
   - `wc_ev_is_verified = 1`
   - `wc_ev_verified_at = now`
   - `wc_ev_token_used_at = now` (and optionally clear `wc_ev_token_hash`)
5. Release orders:
   - Fetch orders for user with status `await_verification`
   - For each order where `date_created >= wc_ev_enabled_at`:
     - set status to `processing`
     - add note “Email verified; order released to processing.”
6. Redirect:
   - Redirect to My Account page with success notice
   - Use `wp_safe_redirect`, internal URL only

Failure behavior:
- Invalid/expired token: generic message “Verification link invalid or expired.” plus a resend path (without confirming account existence to anonymous users).

---

## 7) Email Templates (WooCommerce Email System)
Implement a custom Woo email class extending `WC_Email`:
- ID: `wc_ev_verification_email`

Triggers:
- After registration
- After order created while unverified (if cooldown allows)
- After resend action

Templates:
- `templates/emails/email-verification.php`
- `templates/emails/plain/email-verification.php`

Template variables:
- `verification_url`
- `token_expiry_hours`
- `my_account_url`
- Optional: `order_number` (when triggered during checkout)

Theme override support:
- `yourtheme/woocommerce/emails/email-verification.php`
- `yourtheme/woocommerce/emails/plain/email-verification.php`

---

## 8) Security Requirements (Must-Haves)

### 8.1 Token Security
- Generate tokens using `random_bytes`
- Store only **hashed** token server-side
- Constant-time compare using `hash_equals`
- Single-use + expiration enforced
- Rotate token on resend (invalidate previous token)

### 8.2 Anti-enumeration
- Resend endpoints must not reveal whether an account exists.
- Verification failure messages should be generic.

### 8.3 Rate limiting / abuse controls
- Enforce resend cooldown (per user)
- Enforce daily resend cap (per user)
- Optional IP-based throttling (nice-to-have)

### 8.4 Admin action protections
- Require capability `manage_woocommerce`
- Use WP nonces for wp-admin actions

### 8.5 Redirect Safety
- Use `wp_safe_redirect` and validate redirects
- Prevent open redirect vulnerabilities

### 8.6 Compatibility & robustness
- Use WooCommerce APIs for orders (`wc_get_orders`, `$order->update_status`)
- Must work with HPOS enabled/disabled (avoid direct SQL that assumes posts table)
- Verification endpoints should be excluded from caching if the site uses aggressive page caches

---

## 9) Checkout Support: Classic + Blocks / Store API (Both)

### 9.1 Requirement
The plugin must support both:
- **Classic checkout** (shortcode-based / legacy Woo flow)
- **WooCommerce Blocks / Store API checkout**

### 9.2 Hooking Strategy (Implementation Guidance)
Centralize gating logic in a shared method:

- `maybe_hold_order_for_verification( WC_Order $order ): void`
  - Must be **idempotent** (safe to run multiple times)
  - Must check: feature enabled, user exists, user in-scope, unverified, order in-scope

Call it from multiple hooks to cover both pipelines:

**Classic checkout (primary):**
- `woocommerce_checkout_order_processed`

**Blocks / Store API (primary when available):**
- `woocommerce_store_api_checkout_order_processed`

**Fallback (for resilience; must remain idempotent):**
- `woocommerce_new_order`

The plugin must not rely solely on detecting checkout type; it should work by hooking both pipelines.

---

## 10) Suggested Architecture / File Layout
- `wc-email-verification-gate.php` (bootstrap)
- `/includes/`
  - `class-wc-ev-settings.php`
  - `class-wc-ev-scope.php` (enabled_at + in-scope checks)
  - `class-wc-ev-tokens.php` (token generate/validate/rotate)
  - `class-wc-ev-login-block.php` (auth hooks + messaging)
  - `class-wc-ev-order-gate.php` (order status gating + notes)
  - `class-wc-ev-endpoints.php` (verify + resend endpoints)
  - `class-wc-ev-emails.php` (WC_Email registration)
  - `class-wc-ev-admin.php` (admin actions + UI helpers)
- `/templates/emails/`
  - `email-verification.php`
  - `plain/email-verification.php`

---

## 11) Acceptance Criteria (Definition of Done)
1. Feature enabled → newly created users are unverified and receive a verification email.
2. Unverified in-scope users cannot log in; they see notice + resend option.
3. Guest checkout is disabled while enabled (account required).
4. New orders by unverified in-scope users are set to `await_verification` even if payment captures successfully.
5. Verification link marks user verified and releases all in-scope `await_verification` orders to `processing`.
6. Verification email templates are configurable in Woo email settings and overrideable via theme.
7. Token implementation is secure: random, hashed storage, expiry, single-use, constant-time compare.
8. Resend is rate-limited via cooldown + daily cap and avoids account enumeration.
9. Existing users and orders created before `wc_ev_enabled_at` are not modified or gated.
10. Works in both Classic and Blocks checkout flows by hooking both pipelines (plus safe fallback).

---

## 12) Non-Goals (Out of Scope for v1)
- SMS/2FA verification
- Retroactive enforcement on existing users/orders
- Deep gateway-specific payment orchestration per provider (best-effort; do not break capture)
- Verifying guest checkout without account creation (guest checkout disabled)
