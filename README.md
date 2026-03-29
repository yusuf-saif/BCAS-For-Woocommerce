# BCAS to WhatsApp for WooCommerce

**Version:** 2.0.0  
**Requires:** WordPress 6.0+, WooCommerce 7.0+, PHP 7.4+

## What it does

A production-ready WooCommerce plugin that makes Direct Bank Transfer (BACS) orders fast, friendly, and WhatsApp-connected — without hardcoding any business settings.

### Features (v2)

- **Admin settings page** (`WooCommerce > BCAS to WhatsApp`) — configure everything without touching code
- **Multiple bank accounts** with drag-to-reorder + customer selector on the thank-you page
- **Custom order status** — "Awaiting Receipt" replaces "On Hold" for new BACS orders
- **Admin order action** — "Mark as Payment Confirmed" moves orders to Processing and adds an audit note
- **Admin WhatsApp button** — contact any customer directly from the order edit screen
- **Editable message templates** with `{placeholder}` support for both customer and admin messages
- **Shared instruction block** — one template used for the thank-you page, popup, and BACS emails
- **Mobile-first UI** — responsive popup, large tap targets, reliable clipboard copy

---

## Installation

1. Upload the `bcas-to-whatsapp` folder to `/wp-content/plugins/`
2. Activate via **Plugins > Installed Plugins**
3. Go to **WooCommerce > BCAS to WhatsApp**
4. Add your bank account(s), set your WhatsApp number, and customise templates
5. Save — you're done

> **Upgrading from v1?** The plugin auto-migrates on first activation. A placeholder bank account will be created; just fill in your real details in Settings.

---

## File structure

```
bcas-to-whatsapp/
├── bcas-to-whatsapp.php          ← bootstrap, constants, autoloader
├── includes/
│   ├── class-bcasw-plugin.php           ← orchestrator + migration
│   ├── class-bcasw-settings.php         ← Settings API + get() helper
│   ├── class-bcasw-bank-accounts.php    ← bank CRUD
│   ├── class-bcasw-order-status.php     ← Awaiting Receipt status
│   ├── class-bcasw-order-actions.php    ← admin action + meta box
│   ├── class-bcasw-template-renderer.php← placeholder engine
│   ├── class-bcasw-bank-selector.php    ← multi-bank selector + AJAX
│   ├── class-bcasw-frontend.php         ← thank-you page rendering
│   └── class-bcasw-email.php            ← BACS email injection
├── admin/
│   ├── class-bcasw-admin-page.php       ← admin menu + form handler
│   └── views/
│       ├── settings-page.php            ← tabbed settings HTML
│       └── bank-account-row.php         ← repeater row partial
└── assets/
    ├── css/
    │   ├── frontend.css                 ← mobile-first frontend styles
    │   └── admin.css                    ← admin page styles
    └── js/
        ├── frontend.js                  ← clipboard, popup, bank selector
        └── admin.js                     ← repeater, tabs, var pills
```

---

## Changelog

### 2.0.0
- Complete rewrite into a multi-file, class-based architecture
- Admin settings page with 5 tabs (General, Bank Accounts, WhatsApp, Popup, Instructions)
- Multiple bank account support with drag-to-reorder
- Custom "Awaiting Receipt" order status
- "Mark as Payment Confirmed" admin order action
- Admin WhatsApp contact button on the order edit screen
- Editable WhatsApp templates with placeholder support
- Shared instruction template for thank-you page + emails
- Mobile-first CSS/JS rewrite with focus-trap popup

### 1.1.0
- Initial public release (single-file, hardcoded)
