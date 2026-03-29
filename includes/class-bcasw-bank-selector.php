<?php
/**
 * Bank account selector — rendered at CHECKOUT before payment methods.
 *
 * The customer picks a bank account before submitting the order.
 * The selection is POSTed with the checkout form and saved to order meta:
 *
 *   _bcasw_selected_bank_id  — UUID of the chosen account
 *   _bcasw_bank_snapshot     — JSON snapshot of account data at time of order
 *
 * Snapshots ensure historical orders remain accurate even if accounts change.
 * No AJAX is needed — everything is handled at checkout POST time.
 *
 * @package BCAS_To_WhatsApp
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class BCASW_Bank_Selector {

	public function init(): void {
		// Render selector in the checkout order-review area.
		add_action( 'woocommerce_review_order_before_payment', array( $this, 'render_checkout_selector' ) );

		// Save selection when order is created during checkout.
		add_action( 'woocommerce_checkout_order_processed', array( $this, 'save_bank_on_checkout' ), 5, 3 );
	}

	// ─── Checkout UI ──────────────────────────────────────────────────────────

	/**
	 * Output the bank-account selector inside the checkout order review.
	 * Hidden when a non-BACS payment method is selected (JS-controlled).
	 */
	public function render_checkout_selector(): void {
		$accounts = BCASW_Bank_Accounts::get_all();

		if ( empty( $accounts ) ) {
			return;
		}

		$default    = BCASW_Bank_Accounts::get_default();
		$default_id = $default ? $default['id'] : '';
		$multiple   = count( $accounts ) > 1;

		// Detect whether BACS is the only gateway (affects initial visibility).
		$bacs_only = (bool) BCASW_Settings::get( 'bcasw_bacs_only' );
		?>
		<div class="bcasw-checkout-selector-wrap" id="bcasw-checkout-selector-wrap"
			data-bacs-only="<?php echo $bacs_only ? 'true' : 'false'; ?>">

			<?php // Hidden field always submitted with checkout form. ?>
			<input type="hidden"
				name="bcasw_selected_bank_id"
				id="bcasw_selected_bank_id"
				value="<?php echo esc_attr( $default_id ); ?>">

			<?php if ( $multiple ) : ?>

				<div class="bcasw-selector-checkout">
					<h4 class="bcasw-selector-heading"><?php esc_html_e( 'Select Bank Account to Transfer To', 'bcas-to-whatsapp' ); ?></h4>

					<div class="bcasw-selector-grid">
						<?php foreach ( $accounts as $account ) :
							$is_active = ( $account['id'] === $default_id );
						?>
							<button type="button"
								class="bcasw-bank-card <?php echo $is_active ? 'bcasw-bank-card--active' : ''; ?>"
								data-bank-id="<?php echo esc_attr( $account['id'] ); ?>"
								aria-pressed="<?php echo $is_active ? 'true' : 'false'; ?>">
								<span class="bcasw-card-label"><?php echo esc_html( $account['label'] ?: $account['bank_name'] ); ?></span>
								<span class="bcasw-card-bank"><?php echo esc_html( $account['bank_name'] ); ?></span>
								<span class="bcasw-card-acct"><?php echo esc_html( $account['account_number'] ); ?></span>
							</button>
						<?php endforeach; ?>
					</div>
				</div>

			<?php else :
				// Single account — show info, no choice needed.
				$acc = $accounts[0];
			?>
				<div class="bcasw-checkout-bank-info">
					<p class="bcasw-checkout-bank-label"><?php esc_html_e( 'Pay to:', 'bcas-to-whatsapp' ); ?></p>
					<p class="bcasw-checkout-bank-details">
						<strong><?php echo esc_html( $acc['bank_name'] ); ?></strong> &mdash;
						<?php echo esc_html( $acc['account_name'] ); ?>
						&mdash; <span class="bcasw-acct-mono"><?php echo esc_html( $acc['account_number'] ); ?></span>
					</p>
				</div>
			<?php endif; ?>

		</div>
		<?php
	}

	// ─── Order creation ───────────────────────────────────────────────────────

	/**
	 * Save selected bank account to order meta when checkout is processed.
	 * Fires at `woocommerce_checkout_order_processed` (priority 5, before status change).
	 *
	 * @param int      $order_id    Order ID.
	 * @param array    $posted_data Checkout POST data (already processed by WC).
	 * @param WC_Order $order       Order object.
	 */
	public function save_bank_on_checkout( int $order_id, array $posted_data, WC_Order $order ): void {
		if ( $order->get_payment_method() !== 'bacs' ) {
			return;
		}

		// Read from POST; wp_unslash avoids double-slashing.
		$bank_id = sanitize_text_field( wp_unslash( $_POST['bcasw_selected_bank_id'] ?? '' ) );
		$bank    = $bank_id ? BCASW_Bank_Accounts::get_by_id( $bank_id ) : null;

		// Fallback: no selection or invalid ID → use default account.
		if ( ! $bank ) {
			$bank = BCASW_Bank_Accounts::get_default();
		}

		if ( $bank ) {
			self::persist_bank( $order, $bank );
		}
	}

	// ─── Read helpers ─────────────────────────────────────────────────────────

	/**
	 * Get the bank account for an order.
	 * Priority: snapshot → stored ID → current default.
	 *
	 * @param WC_Order $order Order object.
	 * @return array|null
	 */
	public static function get_bank_for_order( WC_Order $order ): ?array {
		// Snapshot is the most reliable (per-order immutable record).
		$snapshot = $order->get_meta( '_bcasw_bank_snapshot' );
		if ( $snapshot ) {
			$decoded = json_decode( $snapshot, true );
			if ( is_array( $decoded ) && ! empty( $decoded ) ) {
				return $decoded;
			}
		}

		// Fallback: look up live account by stored ID.
		$bank_id = $order->get_meta( '_bcasw_selected_bank_id' );
		if ( $bank_id ) {
			$bank = BCASW_Bank_Accounts::get_by_id( $bank_id );
			if ( $bank ) {
				return $bank;
			}
		}

		// Last resort: current default.
		return BCASW_Bank_Accounts::get_default();
	}

	// ─── Write helper ─────────────────────────────────────────────────────────

	/**
	 * Store bank ID and a full data snapshot on an order.
	 *
	 * @param WC_Order $order Order object.
	 * @param array    $bank  Bank account data array.
	 */
	public static function persist_bank( WC_Order $order, array $bank ): void {
		$order->update_meta_data( '_bcasw_selected_bank_id', $bank['id'] );
		$order->update_meta_data( '_bcasw_bank_snapshot', wp_json_encode( $bank ) );
		$order->save_meta_data();
	}
}
