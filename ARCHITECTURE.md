# Plugin Architecture

## Overview

This document describes the architecture and data flow of the WooCommerce Email Verification Gate plugin.

---

## Component Diagram

```
┌─────────────────────────────────────────────────────────────────┐
│                   WordPress / WooCommerce                       │
└─────────────────────────────────────────────────────────────────┘
                              ▲ │
                              │ │ Hooks & Filters
                              │ ▼
┌─────────────────────────────────────────────────────────────────┐
│            wc-email-verification-gate.php (Bootstrap)           │
│  - Plugin initialization                                        │
│  - Component loading                                            │
│  - Order status registration                                    │
└─────────────────────────────────────────────────────────────────┘
                              │
        ┌─────────────────────┼─────────────────────┐
        │                     │                     │
        ▼                     ▼                     ▼
┌──────────────┐    ┌──────────────┐    ┌──────────────┐
│  WC_EV_Scope │    │WC_EV_Tokens  │    │WC_EV_Settings│
│              │    │              │    │              │
│ - is_enabled │    │ - generate   │    │ - UI fields  │
│ - is_in_scope│    │ - validate   │    │ - options    │
│ - is_verified│    │ - rotate     │    │ - guest mgmt │
└──────────────┘    └──────────────┘    └──────────────┘
        │                     │                     │
        └─────────────────────┼─────────────────────┘
                              │
        ┌─────────────────────┼─────────────────────┐
        │                     │                     │
        ▼                     ▼                     ▼
┌──────────────┐    ┌──────────────┐    ┌──────────────┐
│WC_EV_Login   │    │WC_EV_Order   │    │WC_EV_Endpoints│
│_Block        │    │_Gate         │    │              │
│              │    │              │    │ - verify     │
│ - block auth │    │ - hold order │    │ - resend     │
│ - show UI    │    │ - release    │    │ - redirect   │
└──────────────┘    └──────────────┘    └──────────────┘
        │                     │                     │
        └─────────────────────┼─────────────────────┘
                              │
        ┌─────────────────────┼─────────────────────┐
        │                     │                     │
        ▼                     ▼                     ▼
┌──────────────┐    ┌──────────────┐    ┌──────────────┐
│ WC_EV_Emails │    │ WC_EV_Admin  │    │ Database     │
│              │    │              │    │              │
│ - register   │    │ - user tools │    │ - user meta  │
│ - trigger    │    │ - order tools│    │ - options    │
│ - templates  │    │ - UI columns │    │ - orders     │
└──────────────┘    └──────────────┘    └──────────────┘
```

---

## Data Flow Diagrams

### 1. User Registration Flow

```
[Customer]
    │
    │ Creates account
    ▼
[WooCommerce]
    │
    │ user_register hook
    ▼
[WC_EV_Scope]
    │ Set wc_ev_created_at
    │ Set wc_ev_is_verified = 0
    ▼
[WC_EV_Tokens]
    │ Generate random token
    │ Hash with HMAC SHA-256
    │ Store hash + expiry
    ▼
[WC_EV_Emails]
    │ Send verification email
    │ Record sent timestamp
    ▼
[Customer]
    │ Receives email
    │ Clicks link
    ▼
[WC_EV_Endpoints]
    │ Validate token
    │ Mark verified
    │ Release orders
    ▼
[Customer] ✅ Verified
```

### 2. Checkout Flow (Unverified User)

```
[Customer]
    │
    │ Add to cart
    │ Go to checkout
    ▼
[WooCommerce]
    │ Guest checkout disabled
    │ Must create account
    ▼
[Customer]
    │ Create account during checkout
    │ Complete payment
    ▼
[Payment Gateway]
    │ Process payment
    │ Payment captured ✅
    ▼
[WooCommerce]
    │ woocommerce_checkout_order_processed
    │ woocommerce_store_api_checkout_order_processed
    ▼
[WC_EV_Order_Gate]
    │ Check: User verified?
    │ NO → Hold order
    │
    │ Set status: await-verification
    │ Add order note
    │ Send verification email
    ▼
[Order] ⏸️ Held
    │
    │ Customer verifies email
    ▼
[WC_EV_Endpoints]
    │ Verify token
    │ Mark user verified
    │ Release orders
    ▼
[Order] ✅ Processing
```

### 3. Login Blocking Flow

```
[Customer]
    │
    │ Attempt login
    ▼
[WordPress]
    │ authenticate filter
    ▼
[WC_EV_Login_Block]
    │ Check: Enabled?
    │ Check: User in scope?
    │ Check: User verified?
    │
    │ NO → Block login
    │
    │ Store user ID in transient
    │ Set tracking cookie
    │ Return WP_Error
    ▼
[Login Page]
    │ Show error message
    │ Show "Resend" link
    ▼
[Customer]
    │ Click "Resend"
    ▼
[WC_EV_Endpoints]
    │ Get user from transient
    │ Check rate limits
    │ Rotate token
    │ Send new email
    ▼
[Customer] 📧 New email sent
```

### 4. Token Security Flow

```
[Registration/Resend]
    │
    ▼
[WC_EV_Tokens::generate_token()]
    │
    │ random_bytes(32)
    ▼
[Raw Token: 44 chars]
    │
    │ hash_hmac('sha256', token, AUTH_SALT)
    ▼
[Hashed Token: 64 chars]
    │
    │ Store hash + expiry
    │ DO NOT store raw token
    ▼
[Database]
    │ wc_ev_token_hash
    │ wc_ev_token_expires_at
    │
    │ Send raw token in email
    ▼
[Customer receives email]
    │
    │ Clicks verification link
    ▼
[WC_EV_Endpoints::handle_verify()]
    │
    │ Get token from URL
    ▼
[WC_EV_Tokens::validate_token()]
    │
    │ hash_hmac('sha256', received_token, AUTH_SALT)
    │
    │ hash_equals(stored_hash, computed_hash)
    │ Constant-time comparison ⏱️
    │
    │ Check expiry
    │ Check not already used
    ▼
[Valid?]
    │
    YES ──► Mark verified
    │       Mark token used
    │       Release orders
    │
    NO ───► Show error
            Generic message
```

---

## Security Architecture

### Token Security Layers

```
┌─────────────────────────────────────────────┐
│ Layer 1: Random Generation                  │
│ - random_bytes(32)                          │
│ - 256 bits of entropy                       │
│ - Cryptographically secure                  │
└─────────────────────────────────────────────┘
                   │
                   ▼
┌─────────────────────────────────────────────┐
│ Layer 2: Hashing                            │
│ - HMAC SHA-256                              │
│ - Key: WordPress AUTH_SALT                  │
│ - 64-character hash                         │
└─────────────────────────────────────────────┘
                   │
                   ▼
┌─────────────────────────────────────────────┐
│ Layer 3: Storage                            │
│ - Only hash stored                          │
│ - Raw token NEVER stored                    │
│ - Expiry timestamp stored                   │
└─────────────────────────────────────────────┘
                   │
                   ▼
┌─────────────────────────────────────────────┐
│ Layer 4: Validation                         │
│ - hash_equals() constant-time              │
│ - Expiry check                              │
│ - Single-use check                          │
└─────────────────────────────────────────────┘
                   │
                   ▼
┌─────────────────────────────────────────────┐
│ Layer 5: Usage Tracking                     │
│ - Mark token as used                        │
│ - Record usage timestamp                    │
│ - Prevent replay attacks                    │
└─────────────────────────────────────────────┘
```

### Rate Limiting Architecture

```
┌─────────────────────────────────────────────┐
│ Resend Request                              │
└─────────────────────────────────────────────┘
                   │
                   ▼
┌─────────────────────────────────────────────┐
│ Check 1: Cooldown                           │
│ - Get last sent timestamp                   │
│ - Compare with current time                 │
│ - If < cooldown seconds → BLOCK             │
└─────────────────────────────────────────────┘
                   │ PASS
                   ▼
┌─────────────────────────────────────────────┐
│ Check 2: Daily Cap                          │
│ - Get resend count + reset time             │
│ - If 24h passed → Reset counter             │
│ - If count >= cap → BLOCK                   │
└─────────────────────────────────────────────┘
                   │ PASS
                   ▼
┌─────────────────────────────────────────────┐
│ Allow Resend                                │
│ - Rotate token (invalidate old)            │
│ - Send new email                            │
│ - Update last sent timestamp                │
│ - Increment daily counter                   │
└─────────────────────────────────────────────┘
```

---

## Database Schema

### User Meta

```sql
wp_usermeta
├── wc_ev_is_verified (string: '0' | '1')
├── wc_ev_verified_at (int: timestamp)
├── wc_ev_created_at (int: timestamp)
├── wc_ev_token_hash (string: 64 chars)
├── wc_ev_token_expires_at (int: timestamp)
├── wc_ev_token_used_at (int: timestamp | null)
├── wc_ev_verification_sent_at (int: timestamp)
└── wc_ev_resend_count_day (serialized array)
    ├── count (int)
    └── reset_time (int: timestamp)
```

### Options

```sql
wp_options
├── wc_ev_enable (string: 'yes' | 'no')
├── wc_ev_enabled_at (int: timestamp)
├── wc_ev_block_login (string: 'yes' | 'no')
├── wc_ev_token_validity (int: hours)
├── wc_ev_resend_cooldown (int: seconds)
├── wc_ev_daily_resend_cap (int)
├── wc_ev_enable_logging (string: 'yes' | 'no')
└── wc_ev_previous_guest_checkout (string: 'yes' | 'no')
```

### Orders

```sql
wp_wc_orders (or wp_posts if not HPOS)
├── status = 'wc-await-verification'
└── meta: customer_id → links to user
```

---

## Hook Integration Map

### Registration Hooks
```
user_register
    └─► WC_EV_Scope::set_user_created_at()

woocommerce_created_customer
    └─► WC_EV_Order_Gate::handle_new_customer()
        ├─► Set unverified
        └─► Send verification email
```

### Checkout Hooks
```
woocommerce_checkout_order_processed (Classic)
    └─► WC_EV_Order_Gate::maybe_hold_order()

woocommerce_store_api_checkout_order_processed (Blocks)
    └─► WC_EV_Order_Gate::maybe_hold_order_blocks()

woocommerce_new_order (Fallback)
    └─► WC_EV_Order_Gate::maybe_hold_order_fallback()
```

### Authentication Hooks
```
authenticate
    └─► WC_EV_Login_Block::block_unverified_login()
        ├─► Check if verified
        ├─► Store user ID in transient
        └─► Return WP_Error if not verified
```

### Admin Hooks
```
user_row_actions
    └─► WC_EV_Admin::add_user_actions()

woocommerce_admin_order_actions
    └─► WC_EV_Admin::add_order_actions()

manage_users_columns
    └─► WC_EV_Admin::add_user_column()
```

### Settings Hooks
```
woocommerce_get_settings_account
    └─► WC_EV_Settings::add_settings()

update_option_wc_ev_enable
    └─► WC_EV_Settings::handle_guest_checkout_option()
```

---

## State Machine: Order Status

```
[New Order Created]
        │
        ▼
    ┌───────┐
    │Is User│
    │Verified?│
    └───────┘
      │   │
   YES│   │NO
      │   │
      │   ▼
      │ ┌──────────────────┐
      │ │await-verification│
      │ └──────────────────┘
      │         │
      │         │ User verifies email
      │         ▼
      │   ┌──────────┐
      └──►│processing│
          └──────────┘
                │
                │ Payment, shipping, etc.
                ▼
          [Other Statuses]
```

---

## Scope Guard Logic

```
┌─────────────────────────────────────────────┐
│ Event: User Registration or Order Creation │
└─────────────────────────────────────────────┘
                   │
                   ▼
┌─────────────────────────────────────────────┐
│ Is feature enabled?                         │
│ (wc_ev_enable = 'yes')                     │
└─────────────────────────────────────────────┘
          YES │           │ NO
              │           └─► Allow (not in scope)
              ▼
┌─────────────────────────────────────────────┐
│ Does user/order have created_at timestamp?  │
└─────────────────────────────────────────────┘
          YES │           │ NO
              │           └─► Allow (created before feature)
              ▼
┌─────────────────────────────────────────────┐
│ Is created_at >= wc_ev_enabled_at?         │
└─────────────────────────────────────────────┘
          YES │           │ NO
              │           └─► Allow (created before enable)
              ▼
┌─────────────────────────────────────────────┐
│ IN SCOPE → Apply verification requirements │
└─────────────────────────────────────────────┘
```

---

## Error Handling Flow

```
[Error Occurs]
    │
    ├─► Token Invalid
    │   └─► Generic message + resend option
    │
    ├─► Token Expired
    │   └─► Generic message + resend option
    │
    ├─► Rate Limited (Cooldown)
    │   └─► Specific wait time message
    │
    ├─► Rate Limited (Daily Cap)
    │   └─► Try tomorrow message
    │
    ├─► User Not Found
    │   └─► Generic message (no enumeration)
    │
    └─► Already Verified
        └─► Redirect to success (idempotent)
```

---

## Performance Considerations

### Caching
- Uses WordPress transients (cache-friendly)
- Secure cookies for session tracking
- No PHP sessions (scales better)

### Database Queries
- Uses WooCommerce APIs (wc_get_orders)
- Compatible with HPOS
- Minimal custom queries
- Indexed by user_id for lookups

### Idempotency
- Order gating is idempotent (safe to run multiple times)
- Verification is idempotent (can verify already-verified user)
- No race conditions in order status changes

---

For implementation details, see [IMPLEMENTATION_SUMMARY.md](IMPLEMENTATION_SUMMARY.md)
