<?php
/**
 * Front-end renderer — thank-you page inline block and popup.
 *
 * @package BCAS_To_WhatsApp
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class BCASW_Frontend {

	public function init(): void {
		add_filter( 'woocommerce_available_payment_gateways', array( $this, 'maybe_keep_only_bacs' ) );
		add_action( 'woocommerce_thankyou',                   array( $this, 'render_inline_block' ), 8 );
		add_action( 'woocommerce_thankyou',                   array( $this, 'render_popup' ), 25 );
		add_action( 'wp_enqueue_scripts',                     array( $this, 'enqueue_assets' ) );
		// Fix #5: apply plugin checkout description to BACS gateway.
		add_filter( 'woocommerce_gateway_description',        array( $this, 'filter_bacs_description' ), 10, 2 );
	}

	// ─── BACS-only mode ───────────────────────────────────────────────────────

	public function maybe_keep_only_bacs( array $gateways ): array {
		if ( is_admin() || ! is_checkout() ) {
			return $gateways;
		}
		if ( ! BCASW_Settings::get( 'bcasw_bacs_only' ) ) {
			return $gateways;
		}
		if ( isset( $gateways['bacs'] ) ) {
			return array( 'bacs' => $gateways['bacs'] );
		}
		return $gateways;
	}

	// ─── Asset enqueue ────────────────────────────────────────────────────────

	public function enqueue_assets(): void {
		$is_thankyou = function_exists( 'is_order_received_page' ) && is_order_received_page();
		$is_checkout = function_exists( 'is_checkout' ) && is_checkout() && ! $is_thankyou;

		if ( ! $is_thankyou && ! $is_checkout ) {
			return;
		}

		wp_enqueue_style(
			'bcasw-frontend',
			BCASW_URL . 'assets/css/frontend.css',
			array(),
			BCASW_VERSION
		);

		wp_enqueue_script(
			'bcasw-frontend',
			BCASW_URL . 'assets/js/frontend.js',
			array(),
			BCASW_VERSION,
			true // footer
		);

		wp_localize_script(
			'bcasw-frontend',
			'bcaswData',
			array(
				'ajaxurl'   => admin_url( 'admin-ajax.php' ),
				'nonce'     => wp_create_nonce( 'bcasw_select_bank' ),
				'i18n'      => array(
					'copied'  => __( 'Copied!', 'bcas-to-whatsapp' ),
					'copy'    => __( 'Copy', 'bcas-to-whatsapp' ),
					'copying' => __( 'Copying…', 'bcas-to-whatsapp' ),
				),
			)
		);
	}

	// ─── BACS gateway description ─────────────────────────────────────────────

	/**
	 * Replace the BACS checkout description with the plugin setting.
	 *
	 * @param string $description Current gateway description.
	 * @param string $payment_id  Payment gateway ID.
	 * @return string
	 */
	public function filter_bacs_description( string $description, string $payment_id ): string {
		if ( 'bacs' !== $payment_id ) {
			return $description;
		}
		$custom = BCASW_Settings::get( 'bcasw_checkout_desc' );
		return $custom ?: $description;
	}

	// ─── Helpers ──────────────────────────────────────────────────────────────

	/**
	 * Resolve the BACS order, returning false for non-BACS orders.
	 */
	private function get_bacs_order( int $order_id ): ?WC_Order {
		if ( ! $order_id || ! function_exists( 'wc_get_order' ) ) {
			return null;
		}
		$order = wc_get_order( $order_id );
		if ( ! $order || $order->get_payment_method() !== 'bacs' ) {
			return null;
		}
		return $order;
	}

	/**
	 * Get the customer-facing WhatsApp URL for an order.
	 * Returns empty string if no valid WhatsApp number is configured.
	 */
	private function get_customer_wa_url( WC_Order $order, array $bank ): string {
		$number = BCASW_Settings::get( 'bcasw_wa_customer_number' );
		if ( ! self::has_valid_wa_number( $number ) ) {
			return '';
		}
		$tpl     = BCASW_Settings::get( 'bcasw_wa_customer_tpl' );
		$message = BCASW_Template_Renderer::render( $tpl, $order, $bank );
		return BCASW_Template_Renderer::whatsapp_url( $number, $message );
	}

	/**
	 * Check if a phone number is valid for WhatsApp.
	 *
	 * Strips all non-digit characters and enforces a minimum length of 10 digits.
	 * This avoids accidentally building a wa.me/ URL from partial or test numbers
	 * like "123" which would produce a broken or misdirected link.
	 *
	 * @param string $number Raw phone number (may include +, spaces, dashes).
	 * @return bool
	 */
	private static function has_valid_wa_number( string $number ): bool {
		$digits = preg_replace( '/[^0-9]/', '', $number );
		// Minimum 10 digits — shorter values are incomplete or test data.
		return strlen( $digits ) >= 10;
	}

	// ─── Inline block ─────────────────────────────────────────────────────────

	public function render_inline_block( int $order_id ): void {
		if ( ! BCASW_Settings::get( 'bcasw_enable_inline' ) ) {
			return;
		}

		$order = $this->get_bacs_order( $order_id );
		if ( ! $order ) {
			return;
		}

		// Resolve bank details using fallback chain:
		// 1. order snapshot (immutable historical record)
		// 2. live account by stored ID (if snapshot missing)
		// 3. current default account (last resort)
		// If none of these produces valid data, do not render misleading instructions.
		$bank = BCASW_Bank_Selector::get_bank_for_order( $order );
		if ( ! $bank || ! BCASW_Bank_Accounts::is_account_valid( $bank ) ) {
			return;
		}

		$order_number   = esc_html( $order->get_order_number() );
		$order_total    = esc_html( wp_strip_all_tags( wc_price( (float) $order->get_total(), array( 'currency' => $order->get_currency() ) ) ) );
		$wa_url         = $this->get_customer_wa_url( $order, (array) $bank );
		$enable_copy    = BCASW_Settings::get( 'bcasw_enable_copy' );
		$btn_label      = esc_html( BCASW_Settings::get( 'bcasw_popup_btn_label' ) ?: __( 'I Have Paid — Send Receipt on WhatsApp', 'bcas-to-whatsapp' ) );
		$has_wa         = ! empty( $wa_url );

		// Instruction block.
		$instr_tpl = BCASW_Settings::get( 'bcasw_instr_template' );
		?>
		<div class="bcasw-box" id="bcasw-inline-block">

			<h2 class="bcasw-box__title"><?php esc_html_e( 'Bank Transfer Details', 'bcas-to-whatsapp' ); ?></h2>
			<p class="bcasw-box__intro"><?php esc_html_e( 'Your order is awaiting payment confirmation. Transfer the exact amount below and send your receipt on WhatsApp.', 'bcas-to-whatsapp' ); ?></p>

			<div class="bcasw-details-grid" id="bcasw-details-grid"
				data-order-id="<?php echo esc_attr( $order->get_id() ); ?>"
				data-wa-tpl="<?php echo esc_attr( BCASW_Settings::get( 'bcasw_wa_customer_tpl' ) ); ?>"
				data-wa-number="<?php echo esc_attr( BCASW_Settings::get( 'bcasw_wa_customer_number' ) ); ?>"
				data-site-name="<?php echo esc_attr( BCASW_Settings::get( 'bcasw_site_name' ) ?: get_bloginfo( 'name' ) ); ?>"
				data-customer-name="<?php echo esc_attr( trim( $order->get_billing_first_name() . ' ' . $order->get_billing_last_name() ) ); ?>"
				data-order-number="<?php echo esc_attr( $order->get_order_number() ); ?>"
				data-order-total="<?php echo esc_attr( wp_strip_all_tags( wc_price( (float) $order->get_total(), array( 'currency' => $order->get_currency() ) ) ) ); ?>"
				data-currency="<?php echo esc_attr( $order->get_currency() ); ?>">

				<?php $this->detail_item( __( 'Order Number', 'bcas-to-whatsapp' ), '#' . $order_number, '#' . $order->get_order_number(), $enable_copy ); ?>
				<?php $this->detail_item( __( 'Amount to Pay', 'bcas-to-whatsapp' ), $order_total, '', false ); ?>
				<?php $this->render_bank_detail_items( (array) $bank, (bool) $enable_copy ); ?>

			</div>

			<p class="bcasw-reference-note">
				<?php
				printf(
					/* translators: %s: order number */
					esc_html__( 'Use Order #%s as your payment reference.', 'bcas-to-whatsapp' ),
					esc_html( $order->get_order_number() )
				);
				?>
			</p>

			<div class="bcasw-actions">
				<?php if ( $has_wa ) : ?>
					<a class="bcasw-btn bcasw-btn--wa" id="bcasw-wa-btn-inline"
						href="<?php echo esc_url( $wa_url ); ?>"
						target="_blank" rel="noopener noreferrer">
						<svg width="22" height="22" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.298-.347.446-.52.149-.174.198-.298.298-.497.099-.198.05-.371-.025-.52-.075-.149-.669-1.612-.916-2.207-.242-.579-.487-.5-.669-.51-.173-.008-.371-.01-.57-.01-.198 0-.52.074-.792.372-.272.297-1.04 1.016-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.096 3.2 5.077 4.487.709.306 1.262.489 1.694.625.712.227 1.36.195 1.871.118.571-.085 1.758-.719 2.006-1.413.248-.694.248-1.289.173-1.413-.074-.124-.272-.198-.57-.347m-5.421 7.403h-.004a9.87 9.87 0 01-5.031-1.378l-.361-.214-3.741.982.998-3.648-.235-.374a9.86 9.86 0 01-1.51-5.26c.001-5.45 4.436-9.884 9.888-9.884 2.64 0 5.122 1.03 6.988 2.898a9.825 9.825 0 012.893 6.994c-.003 5.45-4.437 9.884-9.885 9.884m8.413-18.297A11.815 11.815 0 0012.05 0C5.495 0 .16 5.335.157 11.892c0 2.096.547 4.142 1.588 5.945L.057 24l6.305-1.654a11.882 11.882 0 005.683 1.448h.005c6.554 0 11.89-5.335 11.893-11.893a11.821 11.821 0 00-3.48-8.413z"/></svg>
						<?php echo esc_html( $btn_label ); ?>
					</a>
				<?php else : ?>
					<p class="bcasw-reference-note"><?php esc_html_e( 'Please contact the store to confirm your payment.', 'bcas-to-whatsapp' ); ?></p>
				<?php endif; ?>
			</div>

		</div>
		<?php
	}

	/**
	 * Render individual bank detail fields into the grid.
	 */
	private function render_bank_detail_items( array $bank, bool $enable_copy ): void {
		$fields = array(
			array( __( 'Bank', 'bcas-to-whatsapp' ), $bank['bank_name'] ?? '', 'bank_name' ),
			array( __( 'Account Name', 'bcas-to-whatsapp' ), $bank['account_name'] ?? '', 'account_name' ),
			array( __( 'Account Number', 'bcas-to-whatsapp' ), $bank['account_number'] ?? '', 'account_number' ),
		);
		if ( ! empty( $bank['sort_code'] ) ) {
			$fields[] = array( __( 'Sort Code', 'bcas-to-whatsapp' ), $bank['sort_code'], 'sort_code' );
		}
		if ( ! empty( $bank['iban'] ) ) {
			$fields[] = array( __( 'IBAN', 'bcas-to-whatsapp' ), $bank['iban'], 'iban' );
		}
		if ( ! empty( $bank['swift_bic'] ) ) {
			$fields[] = array( __( 'SWIFT/BIC', 'bcas-to-whatsapp' ), $bank['swift_bic'], 'swift_bic' );
		}
		foreach ( $fields as [ $label, $value, $data_key ] ) {
			$copyable = $enable_copy && ! empty( $value );
			$this->detail_item( $label, $value, $value, $copyable, 'data-bank-field="' . esc_attr( $data_key ) . '"' );
		}
	}

	/**
	 * Render one detail grid item.
	 */
	private function detail_item( string $label, string $display, string $copy_val, bool $copyable, string $extra_attrs = '' ): void {
		?>
		<div class="bcasw-detail-item" <?php echo $extra_attrs; // phpcs:ignore WordPress.Security.EscapeOutput ?>>
			<span class="bcasw-detail-label"><?php echo esc_html( $label ); ?></span>
			<strong class="bcasw-detail-value"><?php echo esc_html( $display ); ?></strong>
			<?php if ( $copyable ) : ?>
				<button type="button" class="bcasw-copy-btn" data-copy="<?php echo esc_attr( $copy_val ); ?>" aria-label="<?php echo esc_attr( sprintf( __( 'Copy %s', 'bcas-to-whatsapp' ), $label ) ); ?>">
					<?php esc_html_e( 'Copy', 'bcas-to-whatsapp' ); ?>
				</button>
			<?php endif; ?>
		</div>
		<?php
	}

	// ─── Popup ────────────────────────────────────────────────────────────────

	public function render_popup( int $order_id ): void {
		if ( ! BCASW_Settings::get( 'bcasw_enable_popup' ) ) {
			return;
		}

		$order = $this->get_bacs_order( $order_id );
		if ( ! $order ) {
			return;
		}

		// Resolve bank details using the same fallback chain as the inline block.
		// Snapshot wins — do not override historical order bank data with live settings.
		// If no valid bank is found at all, do not render misleading payment instructions.
		$bank = BCASW_Order_Actions::get_order_bank( $order );
		if ( ! $bank || ! BCASW_Bank_Accounts::is_account_valid( $bank ) ) {
			return;
		}

		$popup_title  = esc_html( BCASW_Settings::get( 'bcasw_popup_title' ) );
		$popup_body   = esc_html( BCASW_Settings::get( 'bcasw_popup_body' ) );
		$btn_label    = esc_html( BCASW_Settings::get( 'bcasw_popup_btn_label' ) ?: __( 'Send Receipt on WhatsApp', 'bcas-to-whatsapp' ) );
		$wa_url       = $this->get_customer_wa_url( $order, $bank );
		$has_wa       = ! empty( $wa_url );
		$enable_copy  = BCASW_Settings::get( 'bcasw_enable_copy' );

		$order_number = $order->get_order_number();
		$order_total  = wp_strip_all_tags( wc_price( (float) $order->get_total(), array( 'currency' => $order->get_currency() ) ) );
		?>
		<div class="bcasw-overlay" id="bcasw-overlay" role="dialog" aria-modal="true" aria-labelledby="bcasw-popup-title">
			<div class="bcasw-popup">

				<button type="button" class="bcasw-popup__close" id="bcasw-closePopup" aria-label="<?php esc_attr_e( 'Close', 'bcas-to-whatsapp' ); ?>">
					<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" aria-hidden="true"><path d="M18 6 6 18M6 6l12 12"/></svg>
				</button>

				<div class="bcasw-popup__header">
					<div class="bcasw-popup__icon">🏦</div>
					<h2 class="bcasw-popup__title" id="bcasw-popup-title"><?php echo $popup_title; // phpcs:ignore — escaped above ?></h2>
					<p class="bcasw-popup__subtitle"><?php echo $popup_body; // phpcs:ignore — escaped above ?></p>
				</div>

				<div class="bcasw-popup__summary">
					<div class="bcasw-popup__summary-item">
						<span><?php esc_html_e( 'Order', 'bcas-to-whatsapp' ); ?></span>
						<strong>#<?php echo esc_html( $order_number ); ?></strong>
					</div>
					<div class="bcasw-popup__summary-item bcasw-popup__summary-item--total">
						<span><?php esc_html_e( 'Amount', 'bcas-to-whatsapp' ); ?></span>
						<strong><?php echo esc_html( $order_total ); ?></strong>
					</div>
				</div>

				<div class="bcasw-popup__bank">
					<?php if ( ! empty( $bank['bank_name'] ) ) : ?>
						<div class="bcasw-popup__bank-row">
							<span><?php esc_html_e( 'Bank', 'bcas-to-whatsapp' ); ?></span>
							<strong><?php echo esc_html( $bank['bank_name'] ); ?></strong>
						</div>
					<?php endif; ?>
					<?php if ( ! empty( $bank['account_name'] ) ) : ?>
						<div class="bcasw-popup__bank-row">
							<span><?php esc_html_e( 'Account Name', 'bcas-to-whatsapp' ); ?></span>
							<strong><?php echo esc_html( $bank['account_name'] ); ?></strong>
						</div>
					<?php endif; ?>
					<?php if ( ! empty( $bank['account_number'] ) ) : ?>
						<div class="bcasw-popup__bank-row">
							<span><?php esc_html_e( 'Account Number', 'bcas-to-whatsapp' ); ?></span>
							<div class="bcasw-popup__bank-copy-row">
								<strong><?php echo esc_html( $bank['account_number'] ); ?></strong>
								<?php if ( $enable_copy ) : ?>
									<button type="button" class="bcasw-copy-btn bcasw-copy-btn--sm"
										data-copy="<?php echo esc_attr( $bank['account_number'] ); ?>"
										aria-label="<?php esc_attr_e( 'Copy account number', 'bcas-to-whatsapp' ); ?>">
										<?php esc_html_e( 'Copy', 'bcas-to-whatsapp' ); ?>
									</button>
								<?php endif; ?>
							</div>
						</div>
					<?php endif; ?>
					<?php if ( ! empty( $bank['sort_code'] ) ) : ?>
						<div class="bcasw-popup__bank-row">
							<span><?php esc_html_e( 'Sort Code', 'bcas-to-whatsapp' ); ?></span>
							<strong><?php echo esc_html( $bank['sort_code'] ); ?></strong>
						</div>
					<?php endif; ?>
				</div>

				<p class="bcasw-popup__reference">
					<?php
					printf(
						/* translators: %s: order number */
						esc_html__( 'Use Order #%s as your payment reference.', 'bcas-to-whatsapp' ),
						esc_html( $order_number )
					);
					?>
				</p>

				<div class="bcasw-popup__actions">
					<?php if ( $has_wa ) : ?>
						<a class="bcasw-btn bcasw-btn--wa bcasw-btn--full" id="bcasw-wa-btn-popup"
							href="<?php echo esc_url( $wa_url ); ?>"
							target="_blank" rel="noopener noreferrer">
							<svg width="20" height="20" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.298-.347.446-.52.149-.174.198-.298.298-.497.099-.198.05-.371-.025-.52-.075-.149-.669-1.612-.916-2.207-.242-.579-.487-.5-.669-.51-.173-.008-.371-.01-.57-.01-.198 0-.52.074-.792.372-.272.297-1.04 1.016-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.096 3.2 5.077 4.487.709.306 1.262.489 1.694.625.712.227 1.36.195 1.871.118.571-.085 1.758-.719 2.006-1.413.248-.694.248-1.289.173-1.413-.074-.124-.272-.198-.57-.347m-5.421 7.403h-.004a9.87 9.87 0 01-5.031-1.378l-.361-.214-3.741.982.998-3.648-.235-.374a9.86 9.86 0 01-1.51-5.26c.001-5.45 4.436-9.884 9.888-9.884 2.64 0 5.122 1.03 6.988 2.898a9.825 9.825 0 012.893 6.994c-.003 5.45-4.437 9.884-9.885 9.884m8.413-18.297A11.815 11.815 0 0012.05 0C5.495 0 .16 5.335.157 11.892c0 2.096.547 4.142 1.588 5.945L.057 24l6.305-1.654a11.882 11.882 0 005.683 1.448h.005c6.554 0 11.89-5.335 11.893-11.893a11.821 11.821 0 00-3.48-8.413z"/></svg>
							<?php echo esc_html( $btn_label ); ?>
						</a>
					<?php else : ?>
						<p class="bcasw-popup__reference" style="text-align:center;"><?php esc_html_e( 'Please contact the store to confirm your payment.', 'bcas-to-whatsapp' ); ?></p>
					<?php endif; ?>
					<button type="button" class="bcasw-btn bcasw-btn--light bcasw-popup__dismiss" id="bcasw-closePopup2">
						<?php esc_html_e( 'I\'ll do this later', 'bcas-to-whatsapp' ); ?>
					</button>
				</div>

			</div>
		</div>
		<?php
	}
}
