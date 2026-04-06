<?php
/**
 * Admin settings page — main tabbed view.
 *
 * Variables available in scope:
 *   $active_tab  (string)
 *   $saved       (bool)
 *
 * @package BCAS_To_WhatsApp
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$tabs = array(
	'general'      => __( '⚙️ General', 'bcas-to-whatsapp' ),
	'banks'        => __( '🏦 Bank Accounts', 'bcas-to-whatsapp' ),
	'whatsapp'     => __( '💬 WhatsApp', 'bcas-to-whatsapp' ),
	'popup'        => __( '🔔 Popup', 'bcas-to-whatsapp' ),
	'instructions' => __( '📄 Instructions', 'bcas-to-whatsapp' ),
);

$all_placeholders = array(
	'{site_name}', '{order_number}', '{customer_name}', '{order_total}',
	'{currency}', '{bank_name}', '{account_name}', '{account_number}',
	'{sort_code}', '{iban}', '{swift_bic}', '{billing_phone}', '{billing_email}',
);
?>
<div class="bcasw-settings-wrap">

	<!-- Header -->
	<div class="bcasw-settings-header">
		<h1><?php esc_html_e( 'BCAS to WhatsApp', 'bcas-to-whatsapp' ); ?></h1>
		<span class="bcasw-settings-badge">v<?php echo esc_html( BCASW_VERSION ); ?></span>
	</div>

	<?php if ( $saved ) : ?>
		<div class="bcasw-saved-notice">
			✅ <?php esc_html_e( 'Settings saved successfully.', 'bcas-to-whatsapp' ); ?>
		</div>
	<?php endif; ?>

	<!-- Tabs -->
	<div class="bcasw-tabs" role="tablist">
		<?php foreach ( $tabs as $slug => $label ) : ?>
			<button type="button" role="tab"
				class="bcasw-tab-btn <?php echo $active_tab === $slug ? 'is-active' : ''; ?>"
				data-tab="<?php echo esc_attr( $slug ); ?>"
				aria-selected="<?php echo $active_tab === $slug ? 'true' : 'false'; ?>"
				aria-controls="bcasw-panel-<?php echo esc_attr( $slug ); ?>">
				<?php echo esc_html( $label ); ?>
			</button>
		<?php endforeach; ?>
	</div>

	<!-- Form -->
	<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
		<input type="hidden" name="action"          value="bcasw_save">
		<input type="hidden" name="bcasw_active_tab" id="bcasw_active_tab" value="<?php echo esc_attr( $active_tab ); ?>">
		<?php wp_nonce_field( 'bcasw_save_settings', 'bcasw_nonce' ); ?>

		<!-- ── GENERAL ───────────────────────────────────────────────────── -->
		<div id="bcasw-panel-general" class="bcasw-tab-panel <?php echo 'general' === $active_tab ? 'is-active' : ''; ?>" role="tabpanel">

			<div class="bcasw-card">
				<h2 class="bcasw-card__title"><?php esc_html_e( 'General Settings', 'bcas-to-whatsapp' ); ?></h2>

				<div class="bcasw-field">
					<label for="bcasw_enabled"><?php esc_html_e( 'Enable Plugin', 'bcas-to-whatsapp' ); ?></label>
					<div>
						<label class="bcasw-toggle-row">
							<input type="checkbox" id="bcasw_enabled" name="bcasw_enabled" value="1" <?php checked( BCASW_Settings::get( 'bcasw_enabled' ), '1' ); ?>>
							<?php esc_html_e( 'Enable BCAS to WhatsApp', 'bcas-to-whatsapp' ); ?>
						</label>
					</div>
				</div>

				<div class="bcasw-field">
					<label for="bcasw_site_name"><?php esc_html_e( 'Store Name', 'bcas-to-whatsapp' ); ?></label>
					<div>
						<input type="text" id="bcasw_site_name" name="bcasw_site_name"
							value="<?php echo esc_attr( BCASW_Settings::get( 'bcasw_site_name' ) ); ?>"
							placeholder="<?php echo esc_attr( get_bloginfo( 'name' ) ); ?>">
						<p class="description"><?php esc_html_e( 'Used in WhatsApp message templates. Defaults to your site name.', 'bcas-to-whatsapp' ); ?></p>
					</div>
				</div>

				<div class="bcasw-field">
					<label for="bcasw_bacs_only"><?php esc_html_e( 'BACS-Only Mode', 'bcas-to-whatsapp' ); ?></label>
					<div>
						<label class="bcasw-toggle-row">
							<input type="checkbox" id="bcasw_bacs_only" name="bcasw_bacs_only" value="1" <?php checked( BCASW_Settings::get( 'bcasw_bacs_only' ), '1' ); ?>>
							<?php esc_html_e( 'Hide all payment methods except Direct Bank Transfer', 'bcas-to-whatsapp' ); ?>
						</label>
					</div>
				</div>

				<div class="bcasw-field">
					<label><?php esc_html_e( 'Features', 'bcas-to-whatsapp' ); ?></label>
					<div style="display:flex;flex-direction:column;gap:10px;">
						<label class="bcasw-toggle-row">
							<input type="checkbox" name="bcasw_enable_popup" value="1" <?php checked( BCASW_Settings::get( 'bcasw_enable_popup' ), '1' ); ?>>
							<?php esc_html_e( 'Enable thank-you page popup', 'bcas-to-whatsapp' ); ?>
						</label>
						<label class="bcasw-toggle-row">
							<input type="checkbox" name="bcasw_enable_inline" value="1" <?php checked( BCASW_Settings::get( 'bcasw_enable_inline' ), '1' ); ?>>
							<?php esc_html_e( 'Enable inline bank details block', 'bcas-to-whatsapp' ); ?>
						</label>
						<label class="bcasw-toggle-row">
							<input type="checkbox" name="bcasw_enable_copy" value="1" <?php checked( BCASW_Settings::get( 'bcasw_enable_copy' ), '1' ); ?>>
							<?php esc_html_e( 'Enable copy-to-clipboard buttons', 'bcas-to-whatsapp' ); ?>
						</label>
						<label class="bcasw-toggle-row">
							<input type="checkbox" name="bcasw_enable_email_sync" value="1" <?php checked( BCASW_Settings::get( 'bcasw_enable_email_sync' ), '1' ); ?>>
							<?php esc_html_e( 'Inject bank instructions into BACS-related emails', 'bcas-to-whatsapp' ); ?>
						</label>
					</div>
				</div>
			</div>

			<div class="bcasw-submit-row">
				<button type="submit" class="bcasw-btn-save">💾 <?php esc_html_e( 'Save General Settings', 'bcas-to-whatsapp' ); ?></button>
			</div>
		</div><!-- /general -->

		<!-- ── BANK ACCOUNTS ─────────────────────────────────────────────── -->
		<div id="bcasw-panel-banks" class="bcasw-tab-panel <?php echo 'banks' === $active_tab ? 'is-active' : ''; ?>" role="tabpanel">

			<div class="bcasw-card">
				<h2 class="bcasw-card__title"><?php esc_html_e( 'Bank Accounts', 'bcas-to-whatsapp' ); ?></h2>
				<p style="color:#6b7280;font-size:.9rem;margin-bottom:12px;">
					<?php esc_html_e( 'Add one or more bank accounts. If multiple accounts exist, customers can choose which one to transfer to. Mark one as default.', 'bcas-to-whatsapp' ); ?>
				</p>
				<p style="color:#374151;font-size:.85rem;margin:0 0 20px;padding:10px 14px;background:#eff6ff;border-left:3px solid #3b82f6;border-radius:4px;">
					<?php esc_html_e( 'Only the default bank account is mirrored in WooCommerce Direct Bank Transfer settings. Additional bank accounts are managed exclusively by this plugin and are not exposed to WooCommerce directly.', 'bcas-to-whatsapp' ); ?>
				</p>

				<?php
				// Hidden field to capture selected default.
				$accounts    = BCASW_Bank_Accounts::get_all();
				$default_id  = '';
				foreach ( $accounts as $a ) {
					if ( ! empty( $a['is_default'] ) ) {
						$default_id = $a['id'];
						break;
					}
				}
				if ( ! $default_id && ! empty( $accounts ) ) {
					$default_id = $accounts[0]['id'];
				}
				?>
				<input type="hidden" name="bcasw_default_bank" id="bcasw_default_bank" value="<?php echo esc_attr( $default_id ); ?>">

				<div class="bcasw-banks-list" id="bcasw-banks-list">
					<?php foreach ( $accounts as $account ) : ?>
						<?php BCASW_Admin_Page::render_bank_row( $account ); ?>
					<?php endforeach; ?>
				</div>

				<button type="button" class="bcasw-add-bank-btn" id="bcasw-add-bank">
					＋ <?php esc_html_e( 'Add Bank Account', 'bcas-to-whatsapp' ); ?>
				</button>
			</div>

			<div class="bcasw-submit-row">
				<button type="submit" class="bcasw-btn-save">💾 <?php esc_html_e( 'Save Bank Accounts', 'bcas-to-whatsapp' ); ?></button>
			</div>
		</div><!-- /banks -->

		<!-- ── WHATSAPP ──────────────────────────────────────────────────── -->
		<div id="bcasw-panel-whatsapp" class="bcasw-tab-panel <?php echo 'whatsapp' === $active_tab ? 'is-active' : ''; ?>" role="tabpanel">

			<div class="bcasw-card">
				<h2 class="bcasw-card__title"><?php esc_html_e( 'WhatsApp Numbers', 'bcas-to-whatsapp' ); ?></h2>
			<p style="color:#6b7280;font-size:.88rem;margin-bottom:20px;line-height:1.6;">
				<?php esc_html_e( 'Three distinct WhatsApp roles exist in this plugin: (1) Store WhatsApp Number — customers send receipts here. (2) Internal/Admin Number — fallback for admin use. (3) Customer billing phone — used by admin when messaging a customer from an order.', 'bcas-to-whatsapp' ); ?>
			</p>

				<div class="bcasw-field">
					<label for="bcasw_wa_customer_number"><?php esc_html_e( 'Store WhatsApp Number', 'bcas-to-whatsapp' ); ?></label>
					<div>
						<input type="tel" id="bcasw_wa_customer_number" name="bcasw_wa_customer_number"
							value="<?php echo esc_attr( BCASW_Settings::get( 'bcasw_wa_customer_number' ) ); ?>"
							placeholder="+2347032896514">
						<p class="description"><?php esc_html_e( 'Customers send their payment receipt to this number. Include country code, no spaces. Example: +2347032896514', 'bcas-to-whatsapp' ); ?></p>
					</div>
				</div>

				<div class="bcasw-field">
					<label for="bcasw_wa_admin_number"><?php esc_html_e( 'Internal/Admin WhatsApp Number', 'bcas-to-whatsapp' ); ?></label>
					<div>
						<input type="tel" id="bcasw_wa_admin_number" name="bcasw_wa_admin_number"
							value="<?php echo esc_attr( BCASW_Settings::get( 'bcasw_wa_admin_number' ) ); ?>"
							placeholder="+2347032896514">
						<p class="description"><?php esc_html_e( 'Optional. Used as a fallback when a customer has no billing phone. The &ldquo;Message Customer on WhatsApp&rdquo; button in orders always uses the customer&rsquo;s own billing phone first.', 'bcas-to-whatsapp' ); ?></p>
					</div>
				</div>
			</div>

			<div class="bcasw-card">
				<h2 class="bcasw-card__title"><?php esc_html_e( 'Message Templates', 'bcas-to-whatsapp' ); ?></h2>

				<div class="bcasw-field">
					<label for="bcasw_wa_customer_tpl"><?php esc_html_e( 'Customer Receipt Message Template', 'bcas-to-whatsapp' ); ?></label>
					<div>
						<textarea id="bcasw_wa_customer_tpl" name="bcasw_wa_customer_tpl"><?php echo esc_textarea( BCASW_Settings::get( 'bcasw_wa_customer_tpl' ) ); ?></textarea>
						<p class="description"><?php esc_html_e( 'Pre-filled WhatsApp message sent by the customer when they tap &ldquo;Send Receipt on WhatsApp&rdquo;. Sent to the Store WhatsApp Number above.', 'bcas-to-whatsapp' ); ?></p>
						<div class="bcasw-vars">
							<?php foreach ( $all_placeholders as $ph ) : ?>
								<span class="bcasw-var-pill" title="<?php esc_attr_e( 'Click to insert', 'bcas-to-whatsapp' ); ?>"><?php echo esc_html( $ph ); ?></span>
							<?php endforeach; ?>
						</div>
					</div>
				</div>

				<div class="bcasw-field">
					<label for="bcasw_wa_admin_tpl"><?php esc_html_e( 'Message Customer Template', 'bcas-to-whatsapp' ); ?></label>
					<div>
						<textarea id="bcasw_wa_admin_tpl" name="bcasw_wa_admin_tpl"><?php echo esc_textarea( BCASW_Settings::get( 'bcasw_wa_admin_tpl' ) ); ?></textarea>
						<p class="description"><?php esc_html_e( 'Pre-filled message when admin taps &ldquo;Message Customer on WhatsApp&rdquo; from an order page. Sent to the customer&rsquo;s billing phone.', 'bcas-to-whatsapp' ); ?></p>
						<div class="bcasw-vars">
							<?php foreach ( array( '{order_number}', '{customer_name}', '{order_total}', '{billing_phone}', '{billing_email}' ) as $ph ) : ?>
								<span class="bcasw-var-pill"><?php echo esc_html( $ph ); ?></span>
							<?php endforeach; ?>
						</div>
					</div>
				</div>
			</div>

			<div class="bcasw-submit-row">
				<button type="submit" class="bcasw-btn-save">💾 <?php esc_html_e( 'Save WhatsApp Settings', 'bcas-to-whatsapp' ); ?></button>
			</div>
		</div><!-- /whatsapp -->

		<!-- ── POPUP ─────────────────────────────────────────────────────── -->
		<div id="bcasw-panel-popup" class="bcasw-tab-panel <?php echo 'popup' === $active_tab ? 'is-active' : ''; ?>" role="tabpanel">

			<div class="bcasw-card">
				<h2 class="bcasw-card__title"><?php esc_html_e( 'Popup Content', 'bcas-to-whatsapp' ); ?></h2>
				<p style="color:#6b7280;font-size:.85rem;margin-bottom:18px;line-height:1.6;">
					<?php esc_html_e( 'The popup is a secondary reminder. Payment instructions are always shown immediately on the order-received page. The popup appears a few seconds later as a follow-up nudge.', 'bcas-to-whatsapp' ); ?>
				</p>

				<div class="bcasw-field">
					<label for="bcasw_popup_title"><?php esc_html_e( 'Popup Title', 'bcas-to-whatsapp' ); ?></label>
					<input type="text" id="bcasw_popup_title" name="bcasw_popup_title"
						value="<?php echo esc_attr( BCASW_Settings::get( 'bcasw_popup_title' ) ); ?>">
				</div>

				<div class="bcasw-field">
					<label for="bcasw_popup_body"><?php esc_html_e( 'Popup Body Text', 'bcas-to-whatsapp' ); ?></label>
					<textarea id="bcasw_popup_body" name="bcasw_popup_body" style="min-height:80px;"><?php echo esc_textarea( BCASW_Settings::get( 'bcasw_popup_body' ) ); ?></textarea>
				</div>

				<div class="bcasw-field">
					<label for="bcasw_popup_btn_label"><?php esc_html_e( 'WhatsApp Button Label', 'bcas-to-whatsapp' ); ?></label>
					<input type="text" id="bcasw_popup_btn_label" name="bcasw_popup_btn_label"
						value="<?php echo esc_attr( BCASW_Settings::get( 'bcasw_popup_btn_label' ) ); ?>">
				</div>
			</div>

			<div class="bcasw-submit-row">
				<button type="submit" class="bcasw-btn-save">💾 <?php esc_html_e( 'Save Popup Settings', 'bcas-to-whatsapp' ); ?></button>
			</div>
		</div><!-- /popup -->

		<!-- ── INSTRUCTIONS ──────────────────────────────────────────────── -->
		<div id="bcasw-panel-instructions" class="bcasw-tab-panel <?php echo 'instructions' === $active_tab ? 'is-active' : ''; ?>" role="tabpanel">

			<div class="bcasw-card">
				<h2 class="bcasw-card__title"><?php esc_html_e( 'Payment Instructions', 'bcas-to-whatsapp' ); ?></h2>
				<p style="color:#6b7280;font-size:.88rem;margin-bottom:16px;">
					<?php esc_html_e( 'These instructions appear on the thank-you page and in BACS-related customer emails.', 'bcas-to-whatsapp' ); ?>
				</p>

				<div class="bcasw-field">
					<label for="bcasw_checkout_desc"><?php esc_html_e( 'Checkout Description', 'bcas-to-whatsapp' ); ?></label>
					<div>
						<textarea id="bcasw_checkout_desc" name="bcasw_checkout_desc" style="min-height:80px;"><?php echo esc_textarea( BCASW_Settings::get( 'bcasw_checkout_desc' ) ); ?></textarea>
						<p class="description"><?php esc_html_e( 'Short text shown below the BACS payment option at checkout.', 'bcas-to-whatsapp' ); ?></p>
					</div>
				</div>

				<div class="bcasw-field">
					<label for="bcasw_instr_template"><?php esc_html_e( 'Instruction Template', 'bcas-to-whatsapp' ); ?></label>
					<div>
						<textarea id="bcasw_instr_template" name="bcasw_instr_template"><?php echo esc_textarea( BCASW_Settings::get( 'bcasw_instr_template' ) ); ?></textarea>
						<p class="description"><?php esc_html_e( 'Used in the thank-you page inline block and in emails. Supports placeholders.', 'bcas-to-whatsapp' ); ?></p>
						<div class="bcasw-vars">
							<?php foreach ( array( '{bank_name}', '{account_name}', '{account_number}', '{sort_code}', '{iban}', '{swift_bic}', '{order_number}', '{order_total}', '{customer_name}' ) as $ph ) : ?>
								<span class="bcasw-var-pill"><?php echo esc_html( $ph ); ?></span>
							<?php endforeach; ?>
						</div>
					</div>
				</div>
			</div>

			<div class="bcasw-submit-row">
				<button type="submit" class="bcasw-btn-save">💾 <?php esc_html_e( 'Save Instructions', 'bcas-to-whatsapp' ); ?></button>
			</div>
		</div><!-- /instructions -->

	</form>
</div><!-- /.bcasw-settings-wrap -->
