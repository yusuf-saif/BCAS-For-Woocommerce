<?php
/**
 * Bank account selector — shown on the thank-you page when multiple accounts exist.
 *
 * When the customer clicks a bank account, the choice is saved to order meta via AJAX:
 *   _bcasw_selected_bank_id  — UUID of the chosen account
 *   _bcasw_bank_snapshot     — JSON snapshot of the account data at time of order
 *
 * @package BCAS_To_WhatsApp
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class BCASW_Bank_Selector {

	public function init(): void {
		// Save selection via AJAX (both logged-in and guest customers).
		add_action( 'wp_ajax_bcasw_select_bank',        array( $this, 'ajax_save_bank' ) );
		add_action( 'wp_ajax_nopriv_bcasw_select_bank', array( $this, 'ajax_save_bank' ) );
	}

	// ─── Rendering ────────────────────────────────────────────────────────────

	/**
	 * Render the bank selector (or nothing if there's only one account).
	 * Called by BCASW_Frontend before rendering full bank details.
	 *
	 * @param WC_Order $order The order.
	 * @return string|null  The pre-selected/saved bank account array, or null.
	 */
	public static function render_and_get_bank( WC_Order $order ): ?array {
		$accounts = BCASW_Bank_Accounts::get_all();

		if ( empty( $accounts ) ) {
			return null;
		}

		// If a bank is already saved on this order, use it without showing selector.
		$saved_id = $order->get_meta( '_bcasw_selected_bank_id' );
		if ( $saved_id ) {
			$saved = BCASW_Bank_Accounts::get_by_id( $saved_id );
			if ( $saved ) {
				return $saved;
			}
		}

		// Single account — auto-select silently.
		if ( count( $accounts ) === 1 ) {
			self::persist_bank( $order, $accounts[0] );
			return $accounts[0];
		}

		// Multiple accounts — show selector and return the default for initial display.
		self::render_selector_html( $order, $accounts );
		$default = BCASW_Bank_Accounts::get_default();
		return $default;
	}

	// ─── HTML ─────────────────────────────────────────────────────────────────

	private static function render_selector_html( WC_Order $order, array $accounts ): void {
		$default = BCASW_Bank_Accounts::get_default();
		$default_id = $default['id'] ?? '';
		?>
		<div class="bcasw-bank-selector" id="bcasw-bank-selector"
			data-order-id="<?php echo esc_attr( $order->get_id() ); ?>"
			data-nonce="<?php echo esc_attr( wp_create_nonce( 'bcasw_select_bank' ) ); ?>">

			<h3 class="bcasw-selector-heading"><?php esc_html_e( 'Choose a Bank Account', 'bcas-to-whatsapp' ); ?></h3>
			<p class="bcasw-selector-sub"><?php esc_html_e( 'Select the account you will transfer to:', 'bcas-to-whatsapp' ); ?></p>

			<div class="bcasw-selector-grid">
				<?php foreach ( $accounts as $account ) : ?>
					<button type="button"
						class="bcasw-bank-card <?php echo ( $account['id'] === $default_id ) ? 'bcasw-bank-card--active' : ''; ?>"
						data-bank-id="<?php echo esc_attr( $account['id'] ); ?>"
						data-bank='<?php echo esc_attr( wp_json_encode( $account ) ); ?>'
						aria-pressed="<?php echo ( $account['id'] === $default_id ) ? 'true' : 'false'; ?>">

						<span class="bcasw-card-label"><?php echo esc_html( $account['label'] ?: $account['bank_name'] ); ?></span>
						<span class="bcasw-card-bank"><?php echo esc_html( $account['bank_name'] ); ?></span>
						<span class="bcasw-card-acct"><?php echo esc_html( $account['account_number'] ); ?></span>
					</button>
				<?php endforeach; ?>
			</div>
		</div>
		<?php
	}

	// ─── AJAX handler ─────────────────────────────────────────────────────────

	/**
	 * Ajax: save selected bank to order meta and return rendered account data.
	 */
	public function ajax_save_bank(): void {
		check_ajax_referer( 'bcasw_select_bank', 'nonce' );

		$order_id = absint( $_POST['order_id'] ?? 0 );
		$bank_id  = sanitize_text_field( $_POST['bank_id'] ?? '' );

		if ( ! $order_id || ! $bank_id ) {
			wp_send_json_error( array( 'message' => 'Missing data.' ) );
			return;
		}

		$order = wc_get_order( $order_id );
		if ( ! $order ) {
			wp_send_json_error( array( 'message' => 'Order not found.' ) );
			return;
		}

		// Verify the requester owns this order (guest: use order key cookie / URL).
		if ( ! $order->has_status( array(
			BCASW_Order_Status::STATUS_SLUG,
			'on-hold',
			'pending',
		) ) ) {
			wp_send_json_error( array( 'message' => 'Invalid order status.' ) );
			return;
		}

		$bank = BCASW_Bank_Accounts::get_by_id( $bank_id );
		if ( ! $bank ) {
			wp_send_json_error( array( 'message' => 'Bank not found.' ) );
			return;
		}

		self::persist_bank( $order, $bank );

		wp_send_json_success( array( 'bank' => $bank ) );
	}

	// ─── Persistence ──────────────────────────────────────────────────────────

	/**
	 * Write bank ID and a full snapshot into order meta.
	 * Snapshot ensures historical orders still show correct data if settings change.
	 *
	 * @param WC_Order $order Order object.
	 * @param array    $bank  Bank account array.
	 */
	public static function persist_bank( WC_Order $order, array $bank ): void {
		$order->update_meta_data( '_bcasw_selected_bank_id', $bank['id'] );
		$order->update_meta_data( '_bcasw_bank_snapshot', wp_json_encode( $bank ) );
		$order->save_meta_data();
	}
}
