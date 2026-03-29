<?php
/**
 * Email integration — injects shared instruction block into BACS-related emails.
 *
 * @package BCAS_To_WhatsApp
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class BCASW_Email {

	public function init(): void {
		if ( ! BCASW_Settings::get( 'bcasw_enable_email_sync' ) ) {
			return;
		}

		// Inject before order table in customer emails.
		add_action( 'woocommerce_email_before_order_table', array( $this, 'inject_instructions' ), 10, 4 );

		// Override the BACS gateway description shown in emails.
		add_filter( 'woocommerce_bacs_accounts', array( $this, 'filter_bacs_email_accounts' ), 10, 2 );
	}

	// ─── Instruction injection ────────────────────────────────────────────────

	/**
	 * Add the shared instruction block above the order table in BACS-related emails.
	 *
	 * @param WC_Order $order         Order object.
	 * @param bool     $sent_to_admin Whether email goes to admin.
	 * @param bool     $plain_text    Whether this is a plain-text email.
	 * @param WC_Email $email         Email instance.
	 */
	public function inject_instructions( WC_Order $order, bool $sent_to_admin, bool $plain_text, WC_Email $email ): void {
		// Only for BACS orders.
		if ( $order->get_payment_method() !== 'bacs' ) {
			return;
		}

		// WC_Email_Customer_On_Hold_Order already renders bank account details
		// natively via the woocommerce_bacs_accounts option (which we override
		// via filter_bacs_email_accounts below). Skip to avoid duplication.
		$emails_wc_handles = array(
			'WC_Email_Customer_On_Hold_Order',
		);
		if ( in_array( get_class( $email ), $emails_wc_handles, true ) ) {
			return;
		}

		// Inject only for emails where WC does NOT show bank details.
		$target_emails = array(
			'WC_Email_Customer_Processing_Order',
			'WC_Email_New_Order',
		);
		if ( ! in_array( get_class( $email ), $target_emails, true ) ) {
			return;
		}

		$bank     = BCASW_Order_Actions::get_order_bank( $order );
		$template = BCASW_Settings::get( 'bcasw_instr_template' );
		$rendered = BCASW_Template_Renderer::render( $template, $order, $bank );

		if ( $plain_text ) {
			echo "\n" . wp_strip_all_tags( $rendered ) . "\n\n"; // phpcs:ignore WordPress.Security.EscapeOutput
		} else {
			echo $this->wrap_html( $rendered ); // phpcs:ignore WordPress.Security.EscapeOutput
		}
	}

	/**
	 * Wrap rendered instructions in WooCommerce-styled email HTML.
	 */
	private function wrap_html( string $content ): string {
		$content = nl2br( esc_html( $content ) );
		return '<div style="background:#f7f7f7;border:1px solid #e5e5e5;border-radius:6px;padding:16px 20px;margin:0 0 20px;font-family:inherit;">'
			. '<p style="margin:0 0 8px;font-weight:700;color:#444;">'
			. esc_html__( 'Bank Transfer Instructions', 'bcas-to-whatsapp' )
			. '</p>'
			. '<p style="margin:0;color:#555;line-height:1.7;">' . $content . '</p>'
			. '</div>';
	}

	// ─── BACS accounts filter ─────────────────────────────────────────────────

	/**
	 * Replace WooCommerce's default BACS account list (from WC settings) with
	 * the plugin-managed account(s). Allows the WC BACS email class to handle
	 * the rest of the formatting naturally.
	 *
	 * @param array    $accounts  BACS accounts from WC settings.
	 * @param WC_Order $order     Order object.
	 * @return array
	 */
	public function filter_bacs_email_accounts( array $accounts, WC_Order $order ): array {
		$bank = BCASW_Order_Actions::get_order_bank( $order );
		if ( ! $bank ) {
			return $accounts;
		}

		// Map our bank structure to WC's expected BACS account fields.
		return array(
			array(
				'account_name'      => $bank['account_name'] ?? '',
				'account_number'    => $bank['account_number'] ?? '',
				'bank_name'         => $bank['bank_name'] ?? '',
				'sort_code'         => $bank['sort_code'] ?? '',
				'iban'              => $bank['iban'] ?? '',
				'bic'               => $bank['swift_bic'] ?? '',
			),
		);
	}
}
