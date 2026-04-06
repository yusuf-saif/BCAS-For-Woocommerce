=== BCAS to WhatsApp ===
Contributors: saifyusuf
Tags: woocommerce, bank transfer, bacs, whatsapp, payment instructions
Requires at least: 6.0
Tested up to: 6.7
Requires PHP: 7.4
WC requires at least: 7.0
WC tested up to: 9.9
Stable tag: 1.1
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

WooCommerce bank transfer helper — configurable bank details, WhatsApp receipt flow, custom order status, and a mobile-friendly payment experience.

== Description ==

BCAS to WhatsApp turns WooCommerce's Direct Bank Transfer (BACS) gateway into a complete manual-payment workflow — giving customers clear transfer instructions and a one-tap WhatsApp receipt button, while giving store admins full control over bank details, order status, and customer communication.

**This is not a payment gateway.** No money is processed by this plugin. It is a bank-transfer instruction and WhatsApp receipt-confirmation system.

= What it does =

* Shows customers exactly where to transfer money — bank name, account name, account number, sort code, IBAN, SWIFT/BIC
* Provides a "Complete Your Bank Transfer" block on the order-received page with one-click copy buttons
* Sends customers to WhatsApp with a pre-filled receipt message via a prominent CTA button
* Shows a reminder popup a few seconds after the page loads
* Moves BACS orders to a custom "Awaiting Receipt" status instead of On Hold
* Gives admins a "Mark as Payment Confirmed" action on order edit screens
* Lets admins message any customer directly from the order screen via WhatsApp
* Supports multiple bank accounts — customers can choose at checkout
* Auto-enables WooCommerce Direct Bank Transfer when setup is complete
* Syncs the default bank account to WooCommerce native BACS settings for email compatibility

= Features =

* **Admin settings page** under WooCommerce — configure everything without touching code
* **Multiple bank accounts** with drag-to-reorder and customer selector at checkout
* **Custom order status** — "Awaiting Receipt" for clear order workflow
* **Editable WhatsApp message templates** with {placeholder} support
* **Copy-to-clipboard** buttons for account number and other key fields
* **Mobile-first design** — responsive, large tap targets, accessible popup
* **HPOS compatible** — fully tested with WooCommerce High-Performance Order Storage
* **Email integration** — bank instructions injected into relevant WooCommerce emails
* **WooCommerce BACS sync** — default bank mirrored to native BACS settings automatically

= WhatsApp Roles =

The plugin separates three distinct WhatsApp concepts:

1. **Store WhatsApp Number** — the number customers send payment receipts to
2. **Internal/Admin WhatsApp Number** — optional fallback for admin use
3. **Customer billing phone** — used by admin when messaging a customer from an order

= Data Integrity =

* Plugin settings are the source of truth for active configuration
* Order snapshots are immutable historical records (bank details frozen at checkout)
* WooCommerce BACS settings are a compatibility mirror of the default bank only

== Installation ==

1. Upload the `bcas-to-whatsapp` folder to `/wp-content/plugins/`
2. Activate via **Plugins > Installed Plugins**
3. Go to **WooCommerce > BCAS to WhatsApp**
4. Add your bank account(s) on the **Bank Accounts** tab
5. Enter your **Store WhatsApp Number** on the WhatsApp tab
6. Save — the plugin will auto-enable WooCommerce Direct Bank Transfer if needed

== Frequently Asked Questions ==

= Does this process payments? =

No. This plugin only displays bank transfer instructions to customers and helps them send a payment receipt via WhatsApp. All payments happen outside WooCommerce via your bank.

= Can I have multiple bank accounts? =

Yes. Add as many as you need. If more than one is configured, customers will see a bank selector at checkout. The default bank is mirrored to WooCommerce's native BACS settings.

= What happens to old orders if I change bank details? =

Nothing — each order stores a snapshot of the bank account at the time of checkout. Historical orders always show the bank they were actually paid to, regardless of any later changes.

= Does it work with HPOS (High-Performance Order Storage)? =

Yes. The plugin declares HPOS compatibility and uses WooCommerce order meta APIs throughout.

= What is the "Awaiting Receipt" order status? =

A custom order status that replaces "On Hold" for new BACS orders. It signals that the order is waiting for the customer to send their payment receipt via WhatsApp. Once confirmed, use "Mark as Payment Confirmed" to move it to Processing.

== Screenshots ==

1. Order-received page — "Complete Your Bank Transfer" block with copy buttons and WhatsApp CTA
2. Reminder popup — appears after a short delay as a secondary nudge
3. Admin settings — Bank Accounts tab with drag-to-reorder
4. Admin settings — WhatsApp tab with role-separated number fields
5. Order edit screen — WhatsApp Actions meta box with "Message Customer on WhatsApp"

== Changelog ==

= 1.1 =
* Added setup readiness check (is_ready()) covering all four required conditions
* Auto-enable WooCommerce Direct Bank Transfer when plugin setup is complete
* New admin notice on auto-enable: "WooCommerce Direct Bank Transfer has been enabled automatically"
* Setup-incomplete summary notice — lists exactly what still needs configuring
* Clarified WhatsApp role labels: Store WhatsApp Number / Internal/Admin WhatsApp Number
* Renamed admin templates: Customer Receipt Message Template / Message Customer Template
* Admin order meta box button renamed to "Message Customer on WhatsApp"
* Inline bank block heading changed to "Complete Your Bank Transfer"
* Popup is now a secondary reminder — appears after 5 seconds, not immediately
* Banks tab: added note explaining default-only WC BACS mirror behaviour
* Popup tab: added note explaining popup is a secondary reminder, not the primary experience
* Extracted WhatsApp number validation into shared BCASW_Settings::is_valid_wa_number()
* WC BACS sync docblock updated to document the compatibility-mirror contract explicitly
* Data integrity contracts documented in plugin header

= 1.0 =
* Initial release
* Admin settings page under WooCommerce with 5 tabs (General, Bank Accounts, WhatsApp, Popup, Instructions)
* Multiple bank account support with drag-to-reorder
* Customer bank selector at checkout
* Order bank snapshot — bank details frozen at time of checkout
* Custom "Awaiting Receipt" order status (BACS orders bypass On Hold)
* "Mark as Payment Confirmed" admin order action (moves to Processing)
* Admin WhatsApp contact button on the order edit screen
* Editable WhatsApp message templates with {placeholder} support
* Instruction template for thank-you page, popup, and BACS emails
* Copy-to-clipboard buttons for key payment fields
* Mobile-first popup with focus-trap and ESC-to-close
* WooCommerce BACS sync — default bank mirrored to native settings
* HPOS compatibility declared
* v1 → v1.0 auto-migration from legacy single-file version

== Upgrade Notice ==

= 1.1 =
Refinement pass. No breaking changes. Existing orders, bank snapshots, and settings are fully preserved. Update recommended for improved setup guidance and customer payment experience.
