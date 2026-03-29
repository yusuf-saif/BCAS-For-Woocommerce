<?php
/**
 * Admin order action: "Mark as Payment Confirmed".
 *
 * Moves order from Awaiting Receipt → Processing and adds an audit note.
 *
 * @package BCAS_To_WhatsApp
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class BCASW_Order_Actions {

	public function init(): void {
		// Add action to the order meta box dropdown.
		add_filter( 'woocommerce_order_actions',       array( $this, 'add_order_action' ) );
		add_action( 'woocommerce_order_action_bcasw_confirm_payment', array( $this, 'handle_confirm_payment' ) );

		// Admin WhatsApp button on the order edit page.
		add_action( 'add_meta_boxes',                  array( $this, 'add_admin_whatsapp_meta_box' ) );

		// Status colour CSS.
		add_action( 'admin_head',                      array( 'BCASW_Order_Status', 'admin_status_css' ) );
	}

	// ─── "Mark as Payment Confirmed" action ───────────────────────────────────

	/**
	 * Register the action in the WooCommerce order action select.
	 *
	 * @param array $actions Existing actions.
	 * @return array
	 */
	public function add_order_action( array $actions ): array {
		global $theorder;

		if ( ! $theorder instanceof WC_Order ) {
			return $actions;
		}
		if ( $theorder->get_payment_method() !== 'bacs' ) {
			return $actions;
		}

		$eligible_statuses = array(
			BCASW_Order_Status::STATUS_SLUG,
			'on-hold',
			'pending',
		);

		if ( in_array( $theorder->get_status(), $eligible_statuses, true ) ) {
			$actions['bcasw_confirm_payment'] = __( 'Mark as Payment Confirmed', 'bcas-to-whatsapp' );
		}

		return $actions;
	}

	/**
	 * Handle the action — change status and add note.
	 *
	 * @param WC_Order $order Order object, passed by WooCommerce.
	 */
	public function handle_confirm_payment( WC_Order $order ): void {
		$current_user = wp_get_current_user();
		$admin_name   = $current_user->display_name ?: $current_user->user_login;
		$timestamp    = current_time( 'mysql' );

		/* translators: 1: admin display name, 2: date/time */
		$note = sprintf(
			__( 'Payment confirmed manually by %1$s on %2$s.', 'bcas-to-whatsapp' ),
			esc_html( $admin_name ),
			esc_html( $timestamp )
		);

		// Update status to processing; standard WC email fires automatically.
		$order->update_status( 'processing', $note );
		$order->save();
	}

	// ─── Admin WhatsApp meta box ───────────────────────────────────────────────

	/**
	 * Add a meta box on the order edit page with a WhatsApp admin contact button.
	 */
	public function add_admin_whatsapp_meta_box(): void {
		$screen = function_exists( 'wc_get_page_screen_id' ) ? wc_get_page_screen_id( 'shop-order' ) : 'shop_order';

		add_meta_box(
			'bcasw-admin-whatsapp',
			__( 'WhatsApp Actions', 'bcas-to-whatsapp' ),
			array( $this, 'render_admin_whatsapp_meta_box' ),
			$screen,
			'side',
			'default'
		);
	}

	/**
	 * Render the WhatsApp meta box content.
	 *
	 * @param WP_Post|WC_Order $post_or_order Post or order object.
	 */
	public function render_admin_whatsapp_meta_box( $post_or_order ): void {
		$order = ( $post_or_order instanceof WC_Order )
			? $post_or_order
			: wc_get_order( $post_or_order->ID );

		if ( ! $order || $order->get_payment_method() !== 'bacs' ) {
			echo '<p>' . esc_html__( 'Not a BACS order.', 'bcas-to-whatsapp' ) . '</p>';
			return;
		}

		$admin_number  = BCASW_Settings::get( 'bcasw_wa_admin_number' );
		$admin_tpl     = BCASW_Settings::get( 'bcasw_wa_admin_tpl' );

		if ( empty( $admin_number ) ) {
			echo '<p class="description">' . esc_html__( 'Set an admin WhatsApp number in BCAS to WhatsApp settings.', 'bcas-to-whatsapp' ) . '</p>';
			return;
		}

		$bank     = self::get_order_bank( $order );
		$message  = BCASW_Template_Renderer::render( $admin_tpl, $order, $bank );
		$wa_url   = BCASW_Template_Renderer::whatsapp_url( $admin_number, $message );

		echo '<p>'
			. '<a href="' . esc_url( $wa_url ) . '" target="_blank" rel="noopener" class="button button-secondary" style="width:100%;text-align:center;">'
			. esc_html__( '💬 Contact Customer on WhatsApp', 'bcas-to-whatsapp' )
			. '</a>'
			. '</p>';

		echo '<p style="font-size:11px;color:#666;">'
			. esc_html__( 'Opens WhatsApp with a pre-filled message using order details.', 'bcas-to-whatsapp' )
			. '</p>';
	}

	// ─── Helper ───────────────────────────────────────────────────────────────

	/**
	 * Retrieve the stored bank account for an order, falling back to the default.
	 *
	 * @param WC_Order $order Order object.
	 * @return array|null
	 */
	public static function get_order_bank( WC_Order $order ): ?array {
		$bank_id = $order->get_meta( '_bcasw_selected_bank_id' );
		if ( $bank_id ) {
			$bank = BCASW_Bank_Accounts::get_by_id( $bank_id );
			if ( $bank ) {
				return $bank;
			}
		}
		// Fallback: use stored snapshot if available.
		$snapshot = $order->get_meta( '_bcasw_bank_snapshot' );
		if ( $snapshot ) {
			$decoded = json_decode( $snapshot, true );
			if ( is_array( $decoded ) ) {
				return $decoded;
			}
		}
		return BCASW_Bank_Accounts::get_default();
	}
}
