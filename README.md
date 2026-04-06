# BCAS to WhatsApp for WooCommerce

**Version:** 1.1 | **Requires:** WordPress 6.0+, WooCommerce 7.0+, PHP 7.4+

A configurable WooCommerce manual-payment workflow plugin. It is **not** a payment gateway — it is a bank-transfer instruction and WhatsApp receipt-confirmation system.

---

## What it does

- Displays a **"Complete Your Bank Transfer"** block on the order-received page with full bank details and one-click copy buttons
- Provides a prominent **"I Have Paid — Send Receipt on WhatsApp"** CTA button
- Shows a **reminder popup** after a short delay (5 seconds by default) as a secondary nudge
- Moves BACS orders to a custom **"Awaiting Receipt"** status
- Lets admins **"Mark as Payment Confirmed"** from the order edit screen
- Lets admins **message any customer** directly from the order screen via WhatsApp
- Supports **multiple bank accounts** — customers choose at checkout
- **Auto-enables** WooCommerce Direct Bank Transfer when setup is complete

---

## Data integrity contract

| Layer | Role |
|-------|------|
| Plugin settings | Source of truth for active configuration |
| Order snapshot | Immutable historical record per order (bank frozen at checkout) |
| WooCommerce BACS | Compatibility mirror of the default bank only |

Invalid or placeholder bank data is never synced to WooCommerce BACS settings.

---

## WhatsApp roles

| Setting | Purpose |
|---------|---------|
| Store WhatsApp Number | Customers send payment receipts here |
| Internal/Admin WhatsApp Number | Optional fallback when customer has no billing phone |
| Customer billing phone | Used by admin "Message Customer on WhatsApp" button |

---

## Installation

1. Upload the `bcas-to-whatsapp` folder to `/wp-content/plugins/`
2. Activate via **Plugins › Installed Plugins**
3. Go to **WooCommerce › BCAS to WhatsApp**
4. Add bank accounts on the **Bank Accounts** tab
5. Set your **Store WhatsApp Number** on the **WhatsApp** tab
6. Save — the plugin auto-enables WooCommerce Direct Bank Transfer if needed

---

## File structure

```
bcas-to-whatsapp/
├── bcas-to-whatsapp.php                  ← bootstrap, constants, autoloader
├── includes/
│   ├── class-bcasw-plugin.php            ← orchestrator, is_ready(), auto-enable BACS, notices
│   ├── class-bcasw-settings.php          ← Settings API, get(), is_valid_wa_number()
│   ├── class-bcasw-bank-accounts.php     ← bank CRUD + WC BACS mirror sync
│   ├── class-bcasw-order-status.php      ← Awaiting Receipt custom status
│   ├── class-bcasw-order-actions.php     ← admin order action + WhatsApp meta box
│   ├── class-bcasw-template-renderer.php ← {placeholder} engine + WhatsApp URL builder
│   ├── class-bcasw-bank-selector.php     ← checkout bank selector + order snapshot
│   ├── class-bcasw-frontend.php          ← thank-you page block + popup
│   └── class-bcasw-email.php             ← BACS email instruction injection
├── admin/
│   ├── class-bcasw-admin-page.php        ← admin menu + form save handler
│   └── views/
│       ├── settings-page.php             ← tabbed settings UI
│       └── bank-account-row.php          ← bank repeater row partial
└── assets/
    ├── css/
    │   ├── frontend.css                  ← mobile-first frontend styles
    │   └── admin.css                     ← admin settings styles
    └── js/
        ├── frontend.js                   ← clipboard, popup delay, bank selector
        └── admin.js                      ← repeater, tabs, variable pills
```

---

## Changelog

### 1.1
- Added `BCASW_Plugin::is_ready()` — returns `true` only when plugin is enabled, ≥1 valid bank account, store WhatsApp number is valid (≥10 digits), and WC BACS gateway exists
- `maybe_auto_enable_bacs()` — automatically enables WooCommerce Direct Bank Transfer when setup becomes complete after saving settings
- New admin notice (a): green success — "WooCommerce Direct Bank Transfer has been enabled automatically"
- New admin notice (b): setup-incomplete summary with direct links, scoped to WC/BCAS admin pages only
- Clarified WhatsApp field labels: **Store WhatsApp Number** / **Internal/Admin WhatsApp Number**
- Clarified template labels: **Customer Receipt Message Template** / **Message Customer Template**
- Added role-clarification paragraph to the WhatsApp settings card explaining all 3 distinct roles
- Inline bank block heading changed from "Bank Transfer Details" → **"Complete Your Bank Transfer"**
- Inline block intro text updated to be action-oriented
- Popup is now a secondary reminder: starts hidden, appears after 5-second delay (`data-popup-delay` attribute)
- Banks tab: added info note — only the default bank is mirrored to WC BACS settings
- Popup tab: added context note — popup is a reminder, not the primary experience
- Admin order meta box button: "Contact Customer" → **"Message Customer on WhatsApp"**
- Empty-state message in meta box updated to reference Internal/Admin WhatsApp Number correctly
- Extracted `is_valid_wa_number()` to `BCASW_Settings` as shared canonical helper
- `sync_to_woocommerce()` docblock updated: documents the COMPATIBILITY MIRROR contract explicitly
- Data integrity contracts documented in plugin file header

### 1.0
- Initial release
- Admin settings page under WooCommerce with 5 tabs (General, Bank Accounts, WhatsApp, Popup, Instructions)
- Multiple bank account support with drag-to-reorder and customer selector at checkout
- Order bank snapshot — bank details frozen at time of checkout, immutable thereafter
- Custom "Awaiting Receipt" order status — BACS orders bypass "On Hold"
- "Mark as Payment Confirmed" admin order action — moves order to Processing with audit note
- Admin WhatsApp button on the order edit screen to contact customer
- Editable WhatsApp message templates with `{placeholder}` support
- Shared instruction template rendered on thank-you page, in popup, and in BACS emails
- Copy-to-clipboard buttons for account number and other key payment fields
- Mobile-first popup with focus-trap, ESC-to-close, and overlay-click-to-dismiss
- `woocommerce_bacs_only` mode — hides all other payment gateways at checkout
- WooCommerce BACS sync — default bank account mirrored to native WC BACS settings
- HPOS (High-Performance Order Storage) compatibility declared
- Auto-migration from legacy v1 single-file version on first activation
